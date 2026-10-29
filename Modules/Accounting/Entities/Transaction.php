<?php

namespace Modules\Accounting\Entities;

use App\Transaction as AppTransaction;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Entities\JournalEntry;

class Transaction extends AppTransaction
{
    protected $appends = ['days_passed', 'amount_due'];

    public function journal_entry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id')->withDefault();
    }

    public function scopeExpenses($query)
    {
        return $query->whereIn('transactions.type', ['expense', 'expense_refund'])
            ->selectRaw('COALESCE(total_before_tax + tax_amount, 0) AS amount');
    }

    public function scopeIncome($query)
    {
        return $query->whereIn('transactions.type', ['income', 'income_refund'])
            ->selectRaw('COALESCE(total_before_tax + tax_amount, 0) AS amount');
    }

    public function scopeForBusiness($query)
    {
        return $query->where('transactions.business_id', session('business.id'));
    }

    public function getDaysPassedAttribute()
    {
        $earlier = new \DateTime($this->transaction_date);
        $now = new \DateTime();
        $abs_diff = $now->diff($earlier)->format("%a");

        if ($abs_diff <= 30) {
            return 'one_to_thirty_past_due';
        } elseif ($abs_diff <= 60) {
            return 'thirty_one_to_sixty_past_due';
        } elseif ($abs_diff <= 90) {
            return 'sixty_one_to_ninety_past_due';
        } else {
            return 'ninety_one_and_over';
        }
    }

    public function getAmountDueAttribute()
    {
        return $this->total_before_tax - $this->tax_amount;
    }

    public function getTransactionTypeAttribute($value)
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    public static function getDaysPassedOptions()
    {
        return [
            'one_to_thirty_past_due' => trans('accounting::general.one_to_thirty_past_due'),
            'thirty_one_to_sixty_past_due' => trans('accounting::general.thirty_one_to_sixty_past_due'),
            'sixty_one_to_ninety_past_due' => trans('accounting::general.sixty_one_to_ninety_past_due'),
            'ninety_one_and_over' => trans('accounting::general.ninety_one_and_over')
        ];
    }

    public function getTransactionDateAttribute($value)
    {
        $date = date_create($value);
        return  date_format($date, "Y-m-d");
    }

    public static function getPayrollGroups()
    {
        return Transaction::where('transactions.business_id', session('business.id'))
            ->where('transactions.type', 'payroll')
            ->join('users as u', 'u.id', '=', 'transactions.expense_for')
            ->leftJoin('categories as dept', 'u.essentials_department_id', '=', 'dept.id')
            ->leftJoin('categories as dsgn', 'u.essentials_designation_id', '=', 'dsgn.id')
            ->leftJoin('essentials_payroll_group_transactions as epgt', 'transactions.id', '=', 'epgt.transaction_id')
            ->select([
                'transactions.id as transaction_id',
                'u.id as user_id',
                DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user"),
                'final_total',
                'transaction_date',
                'ref_no',
                'transactions.payment_status',
                'dept.name as department',
                'dsgn.name as designation',
                'epgt.payroll_group_id as id'
            ])
            ->get();
    }
}
