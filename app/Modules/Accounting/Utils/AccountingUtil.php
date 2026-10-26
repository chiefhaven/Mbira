<?php
namespace Modules\Accounting\Utils;

use App\Utils\Util;
use DB;
use App\Business;
use App\Transaction;

class AccountingUtil extends Util
{
    public function balanceFormula($accounting_accounts_alias = 'accounting_accounts',
                                 $accounting_account_transaction_alias = 'AAT'){

        return "SUM( IF(
            ($accounting_accounts_alias.account_primary_type='asset' AND $accounting_account_transaction_alias.type='debit')
            OR ($accounting_accounts_alias.account_primary_type='expense' AND $accounting_account_transaction_alias.type='debit')
            OR ($accounting_accounts_alias.account_primary_type='income' AND $accounting_account_transaction_alias.type='credit')
            OR ($accounting_accounts_alias.account_primary_type='equity' AND $accounting_account_transaction_alias.type='credit')
            OR ($accounting_accounts_alias.account_primary_type='liability' AND $accounting_account_transaction_alias.type='credit'), 
            amount, -1*amount)) as balance";
    }

    public function getAccountingSettings($business_id)
    {
        $accounting_settings = Business::where('id', $business_id)
                                ->value('accounting_settings');

        $accounting_settings = !empty($accounting_settings) ? json_decode($accounting_settings, true) : [];

        return $accounting_settings;
    }

    public function getAgeingReport($business_id, $type, $group_by, $location_id=null)
    {
        $today = \Carbon::now()->format('Y-m-d');
        $query = Transaction::where('transactions.business_id', $business_id);

        if($type=='sell') {
            $query->where('transactions.type', 'sell')
            ->where('transactions.status', 'final');
        } elseif($type=='purchase') {
            $query->where('transactions.type', 'purchase')
                ->where('transactions.status', 'received');
        }

        if(!empty($location_id)) {
            $query->where('transactions.location_id', $location_id);
        }

        $dues = $query->whereNotNull('transactions.pay_term_number')
                ->whereIn('transactions.payment_status', ['partial', 'due'])
                ->join('contacts as c', 'c.id', '=', 'transactions.contact_id')
                ->select(
                    DB::raw(
                        'DATEDIFF(
                            "' . $today . '", 
                            IF(
                                transactions.pay_term_type="days",
                                DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY),
                                DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH)
                            )
                        ) as diff'
                    ),
                    DB::raw('SUM(transactions.final_total - 
                        (SELECT COALESCE(SUM(IF(tp.is_return = 1, -1*tp.amount, tp.amount)), 0) 
                        FROM transaction_payments as tp WHERE tp.transaction_id = transactions.id) )  
                        as total_due'),

                    'c.name as contact_name',
                    'transactions.contact_id'
                )
                ->groupBy('transactions.id')
                ->get();

        $report_details = [];
        foreach($dues as $due) {
            if(!isset($report_details[$due->contact_id])) {
                $report_details[$due->contact_id]= [
                    'name' => $due->contact_name,
                    '<1' => 0,
                    '1_30' => 0,
                    '31_60' => 0,
                    '61_90'  => 0,
                    '>90' => 0,
                    'total_due' => 0
                ];
            }

            if($due->diff<1) {
                $report_details[$due->contact_id]['<1'] += $due->total_due;
            } elseif ($due->diff>=1 && $due->diff<=30) {
                $report_details[$due->contact_id]['1_30'] += $due->total_due;
            } elseif ($due->diff>=31 && $due->diff<=60) {
                $report_details[$due->contact_id]['31_60'] += $due->total_due;
            } elseif ($due->diff>=61 && $due->diff<=90) {
                $report_details[$due->contact_id]['61_90'] += $due->total_due;
            } elseif ($due->diff>90) {
                $report_details[$due->contact_id]['>90'] += $due->total_due;
            }

            $report_details[$due->contact_id]['total_due']+=$due->total_due;
        }

        return $report_details;
    }
}



?>