<?php

namespace Modules\Accounting\Services;

class AccountingReportService
{
    public function getReports()
    {
        return (object) [
            // Business Overview
            (object) [
                'section_title' => trans('accounting::report.business_overview'),
                'items' => [
                     (object) [
                        'title' => trans_choice('accounting::report.balance_sheet', 1),
                        'description' => trans('accounting::report.balance_sheet_description'),
                        'url' =>  url('report/accounting/balance_sheet'),
                    ],
                    (object) [
                        'title' => trans('accounting::report.statement_of_cash_flows'),
                        'description' => trans('accounting::report.statement_of_cash_flows_description'),
                        'url' => url('report/accounting/cash_flow'),
                    ],
                    (object) [
                        'title' => trans_choice('accounting::report.profit_and_loss', 1),
                        'description' => trans('accounting::report.profit_and_loss_description'),
                        'url' =>  url('report/accounting/profit_and_loss'),
                    ],
                ]
            ],

            // Bookkeeping
            (object) [
                'section_title' => trans('accounting::report.bookkeeping'),
                'items' => [
                    (object) [
                        'title' => trans_choice('accounting::report.trial_balance', 1),
                        'description' => trans('accounting::report.trial_balance_description'),
                        'url' => url('report/accounting/trial_balance'),
                    ],
                    (object) [
                        'title' => trans_choice('accounting::report.general_ledger', 1),
                        'description' => trans('accounting::report.general_ledger_description'),
                        'url' =>  url('report/accounting/ledger'),
                    ],
                    (object) [
                        'title' => trans_choice('accounting::report.journal', 1),
                        'description' => trans('accounting::report.journal_description'),
                        'url' =>  url('report/accounting/journal'),
                    ],
                ]
            ],

            //Budget
            (object) [
                'section_title' => trans_choice('accounting::report.budget', 1),
                'items' => [
                    (object) [
                        'title' => trans('accounting::report.budget_overview'),
                        'description' => trans('accounting::report.budget_overview'),
                        'url' => url('report/accounting/budget_overview?view=monthly&year=' . get_financial_year())
                    ],
                ]
            ],

            //Who owes you
            (object) [
                'section_title' => trans('accounting::report.who_owes_you'),
                'items' => [
                    (object) [
                        'title' => trans('accounting::report.accounts_receivable_ageing_summary'),
                        'description' => trans('accounting::report.accounts_receivable_ageing_summary_description'),
                        'url' => url('report/accounting/accounts_receivable_ageing_summary')
                    ],
                    (object) [
                        'title' => trans('accounting::report.accounts_receivable_ageing_detail'),
                        'description' => trans('accounting::report.accounts_receivable_ageing_detail_description'),
                        'url' => url('report/accounting/accounts_receivable_ageing_detail')
                    ],
                ]
            ],

            // What you owe
            (object) [
                'section_title' => trans('accounting::report.what_you_owe'),
                'items' => [
                    (object) [
                        'title' => trans('accounting::report.accounts_payable_ageing_summary'),
                        'description' => trans('accounting::report.accounts_payable_ageing_summary_description'),
                        'url' => url('report/accounting/accounts_payable_ageing_summary')
                    ],
                    (object) [
                        'title' => trans('accounting::report.accounts_payable_ageing_detail'),
                        'description' => trans('accounting::report.accounts_payable_ageing_detail_description'),
                        'url' => url('report/accounting/accounts_payable_ageing_detail')
                    ],
                ]
            ],
        ];
    }
}
