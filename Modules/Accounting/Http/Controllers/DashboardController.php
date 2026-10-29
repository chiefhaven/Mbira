<?php

namespace Modules\Accounting\Http\Controllers;

use App\Charts\CommonChart;
use App\Utils\TransactionUtil;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Accounting\Entities\AccountType;
use Modules\Accounting\Entities\ChartOfAccount;
use Modules\Accounting\Entities\JournalEntry;

class DashboardController extends Controller
{
    protected $transactionUtil;

    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
    }

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        $chart_of_accounts = ChartOfAccount::with('parent')
            ->forBusiness()
            ->with('currency')
            ->get();

        $account_types = AccountType::getTypes()->pluck('id');

        $expense_chart = $this->expense_chart();
        $current_financial_year_chart = $this->current_financial_year_chart();
        $last_30_days_financial_year_chart = $this->last_30_days_financial_year_chart();


        $balance_summary_chart = (object)[
            'labels' => json_encode(
                $account_types->map(function ($account_type) {
                    return trans_choice('accounting::general.' . $account_type, 1);
                }),
            ),
            'values' => $account_types->map(function ($account_type) use ($chart_of_accounts) {
                return $chart_of_accounts->where('account_type', $account_type)->sum('current_balance');
            })
        ];

        return view('accounting::dashboard.index', compact('account_types', 'chart_of_accounts', 'expense_chart', 'current_financial_year_chart', 'last_30_days_financial_year_chart', 'balance_summary_chart'));
    }


    public function expense_chart()
    {
        $expenses = $this->transactionUtil->getExpenseReport(session('business.id'));

        $values = [];
        $labels = [];
        foreach ($expenses as $expense) {
            $values[] = (float) ChartOfAccount::forBusiness()->where('account_type', 'expense')->get()->sum('current_balance');
            $labels[] = !empty($expense->category) ? $expense->category : __('report.others');
        }

        $chart = new CommonChart;
        $chart->labels($labels)
            ->title(__('report.expense_report'))
            ->dataset(__('report.total_expense'), 'column', $values);

        return $chart;
    }

    public function current_financial_year_chart()
    {
        $values = [];
        $labels = [];

        $values[] = (float) JournalEntry::forBusiness()->where('date', '>=', financial_year_start_date())->get()->sum('amount');
        $labels[] = '';

        $title = trans('accounting::lang.current') . ' ' . trans_choice('accounting::general.financial_year', 1);

        $chart = new CommonChart;
        $chart->labels($labels)
            ->title($title)
            ->dataset($title, 'column', $values);

        return $chart;
    }

    public function last_30_days_financial_year_chart()
    {
        $values = [];
        $labels = [];

        $values[] = (float) JournalEntry::forBusiness()->where('date', '>=', thirty_days_ago())->get()->sum('amount');
        $labels[] = '';

        $title = trans('accounting::lang.last') . ' 30 ' . trans_choice('accounting::lang.day', 2);

        $chart = new CommonChart;
        $chart->labels($labels)
            ->title($title)
            ->dataset($title, 'column', $values);

        return $chart;
    }

    /**
     * Retrieves totals for the main admin dashboard
     *
     * @return \Illuminate\Http\Response
     */
    public function get_totals()
    {
        if (request()->ajax()) {
            // Will be needed in filtering  
            // $start = request()->start;
            // $end = request()->end;
            // $location_id = request()->location_id;
            // $business_id = request()->session()->get('user.business_id');

            $output = [];

            $output['no_journal_entries'] = JournalEntry::forBusiness()->get()->count('transaction_number');
            $output['no_charts_of_account'] = ChartOfAccount::forBusiness()->get()->count('id');
            $output['all_transactions'] = ChartOfAccount::forBusiness()->get()->sum('current_balance');

            return $output;
        }
    }
}
