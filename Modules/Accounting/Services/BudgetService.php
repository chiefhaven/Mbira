<?php

namespace Modules\Accounting\Services;

class BudgetService
{
    private $starting_month;
    private $financial_year;

    public function __construct($starting_month = '', $financial_year = '')
    {
        $this->starting_month = $starting_month;
        $this->financial_year = $financial_year;
    }

    public function setStartingMonth($starting_month)
    {
        $this->starting_month = $starting_month;
        return $this;
    }

    public function getMonths()
    {
        $first_month = $this->financial_year . '-' . $this->starting_month;

        $months = collect([
            $this->readableMonth($first_month)
        ]);

        //Init the first month
        $current_month = $first_month;

        // Iterate over the nex 
        for ($i = 0; $i < 11; $i++) {
            $current_month = date('Y-m', strtotime("+1 months", strtotime($current_month)));
            $readable_month = $this->readableMonth($current_month);
            $months->push($readable_month);
        }

        return $months;
    }

    public static function getCurrentFinancialYear($start_month)
    {
        $current_month = date('Y-m');
        $financial_year_starts = date('Y') . '-' . $start_month;
        $previous_financial_year_starts = date('Y-m', strtotime("-1 year", strtotime($financial_year_starts)));

        $financial_year =
            (strtotime($current_month) < strtotime($financial_year_starts)) ?
            date('Y', strtotime($previous_financial_year_starts)) :
            date('Y', strtotime($financial_year_starts));

        return $financial_year;
    }

    public function readableMonth($date)
    {
        return date('F Y', strtotime($date));
    }

    public function quartelyBudgetToMonthly(array $months, int $quarter_budget, bool $eliminate_decimals)
    {
        $monthly_budget = [];

        switch ($eliminate_decimals) {
            case true:
                $single_month = intdiv($quarter_budget, 3);
                $first_two_months = $single_month * 2;
                $last_month = $quarter_budget - $first_two_months;

                foreach ($months as $index => $month) {
                    $month_number = $index + 1;
                    if ($month_number == 3) {
                        $monthly_budget[$month] = $last_month;
                        break;
                    }
                    $monthly_budget[$month] = $single_month;
                }
                break;

            case false:
                $single_month = $quarter_budget / 3;
                foreach ($months as $month) {
                    $monthly_budget[$month] = $single_month;
                }
                break;
        }

        return $monthly_budget;
    }

    public function yearlyBudgetToMonthly(int $yearly_budget, bool $eliminate_decimals)
    {
        $monthly_budget = [];

        switch ($eliminate_decimals) {
            case true:
                $single_month = intdiv($yearly_budget, 12);
                $first_eleven_months = $single_month * 11;
                $last_month = $yearly_budget - $first_eleven_months;

                for ($i = 1; $i <= 12; $i++) {
                    $month_number = "month_$i";
                    if ($i == 12) {
                        $monthly_budget[$month_number] = $last_month;
                        break;
                    }
                    $monthly_budget[$month_number] = $single_month;
                }
                break;

            case false:
                $single_month = $yearly_budget / 12;
                for ($i = 1; $i <= 12; $i++) {
                    $month_number = "month_$i";
                    $monthly_budget[$month_number] = $single_month;
                }
                break;
        }
        
        return $monthly_budget;
    }
}
