<?php

use App\Business;
use App\Utils\ModuleUtil;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\Contact;

function accounting($ul, $pt, $lc, $em, $un, $type = 1, $pid = null)
{
	//bugs
}




if (!function_exists('theme_view_file')) {
    /**
     * Get the evaluated view contents for the given view.
     *
     * @param string|null $view
     * @param \Illuminate\Contracts\Support\Arrayable|array $data
     * @param array $mergeData
     * @return \Illuminate\Contracts\View\View|Factory
     */
    function theme_view_file($view = null)
    {
        $factory = app(Factory::class);
        $active_theme = strtolower(config('active_theme'));
        if ($factory->exists(str_replace("::", "::themes.$active_theme.", $view))) {
            $view = str_replace("::", "::themes.$active_theme.", $view);
        }
        return $view;
    }
}

if (!function_exists('table_order_link')) {
    /**
     * Get the evaluated view contents for the given view.
     *
     * @param string $column
     * @return string
     */
    function table_order_link($column)
    {
        $link = request()->fullUrlWithQuery(['order_by' => $column, 'order_by_dir' => (request('order_by_dir') === 'asc') ? 'desc' : 'asc']);
        return $link;
    }
}

if (!function_exists('is_admin')) {
    /** 
     * Verifies whether the user logged in is admin or not
     * */
    function is_admin()
    {
        return auth()->user()->is_admin;
    }
}

if (!function_exists('is_employee')) {
    /** 
     * Verifies whether the user logged in is employee or not
     * */
    function is_employee()
    {
        return auth()->user()->is_employee;
    }
}

if (!function_exists('readable_date')) {
    function readable_date($date)
    {
        return date('F j Y', strtotime($date));
    }
}

if (!function_exists('readable_datetime')) {
    function readable_datetime($date)
    {
        return date('F j Y h:i:s a', strtotime($date));
    }
}

if (!function_exists('getMpdf')) {
    function getMpdf()
    {
        return new \Mpdf\Mpdf([
            'tempDir' => public_path('uploads/temp'),
            'mode' => 'utf-8',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'autoVietnamese' => true,
            'autoArabic' => true
        ]);
    }
}

if (!function_exists('get_url')) {
    function get_url($url)
    {
        return is_admin() ?
            $url :
            'portal/' . $url;
    }
}

if (!function_exists('get_uniqid')) {
    function get_uniqid()
    {
        return uniqid('TRX-', true);
    }
}

if (!function_exists('get_calendar_year')) {
    function get_calendar_year()
    {
        return [
            '1' => trans('accounting::lang.january'),
            '2' => trans('accounting::lang.february'),
            '3' => trans('accounting::lang.march'),
            '4' => trans('accounting::lang.april'),
            '5' => trans('accounting::lang.may'),
            '6' => trans('accounting::lang.june'),
            '7' => trans('accounting::lang.july'),
            '8' => trans('accounting::lang.august'),
            '9' => trans('accounting::lang.september'),
            '10' => trans('accounting::lang.october'),
            '11' => trans('accounting::lang.november'),
            '12' => trans('accounting::lang.december'),
        ];
    }
}

if (!function_exists('getUrlWithoutParams')) {
    function getUrlWithoutParams($url)
    {
        return explode('?', $url)[0];
    }
}

if (!function_exists('currency_code')) {
    function currency_code()
    {
        $query = Business::join('currencies', 'business.currency_id', '=', 'currencies.id')
            ->where('business.id', session('business.id'))
            ->select('currencies.code')
            ->first();

        return !empty($query) ? $query->code : '';
    }
}

if (!function_exists('get_business_name')) {
    function get_business_name()
    {
        $query = Business::where('business.id', session('business.id'))
            ->select('business.name')
            ->first();

        return !empty($query) ? $query->name : '';
    }
}

if (!function_exists('get_financial_year')) {
    function get_financial_year()
    {
        $start_month = \App\Business::findOrFail(session('business.id'))->fy_start_month;
        $financial_year = \Modules\Accounting\Services\BudgetService::getCurrentFinancialYear($start_month);
        return $financial_year;
    }
}

if (!function_exists('financial_year_start_date')) {
    function financial_year_start_date()
    {
        $financial_year_start_month = Business::findOrFail(session('business.id'))->fy_start_month;
        $financial_year_start_year = get_financial_year();
        return $financial_year_start_year . '-' . $financial_year_start_month . '-' . '01';
    }
}

if (!function_exists('thirty_days_ago')) {
    function thirty_days_ago()
    {
        $today = date('Y-m-d');
        return date('Y-m-d', strtotime($today . ' -30 Days'));
    }
}

if (!function_exists('get_date_range')) {
    function get_days_past()
    {
        $today = date('Y-m-d');

        return (object) [
            'today' => $today,
            'yesterday' => date('Y-m-d', strtotime("$today -1 days")),
            'thirty_days_ago' => date('Y-m-d', strtotime("$today -30 days")),
            'sixty_days_ago' => date('Y-m-d', strtotime("$today -60 days")),
            'ninety_days_ago' => date('Y-m-d', strtotime("$today -90 days")),
            'thirty_one_days_ago' => date('Y-m-d', strtotime("$today -31 days")),
            'sixty_one_days_ago' => date('Y-m-d', strtotime("$today -61 days")),
            'ninety_one_days_ago' => date('Y-m-d', strtotime("$today -91 days")),
        ];
    }
}

if (!function_exists('get_keys_from_header_row')) {
    function get_keys_from_header_row($header_row)
    {
        return array_map(function ($key) {
            $key_without_spaces = str_replace(' ', '_', $key);
            $key_without_dots = str_replace('.', '', $key_without_spaces);
            return strtolower($key_without_dots);
        }, $header_row);
    }
}

if (!function_exists('excel_date_to_php_date')) {
    function excel_date_to_php_date($excel_date)
    {
        $unix_date = ($excel_date - 25569) * 86400;
        return date("Y-m-d", $unix_date);
    }
}

if (!function_exists('get_chart_colors')) {
    function get_chart_colors()
    {
        return (object)[
            'red' => '#D72828',
            'blue' => '#3C8DBC',
        ];
    }
}

if (!function_exists('get_months')) {
    function get_months($year)
    {
        $months = [];
        for ($monthNum = 1; $monthNum <= 12; $monthNum++) {
            $dateObj = DateTime::createFromFormat('!m', $monthNum);
            $month = strtolower($dateObj->format('F'));
            $months[trans("accounting::lang.$month")] = (object)[
                'start' => date('Y-m-d', strtotime("first day of $month $year")),
                'end' => date('Y-m-d', strtotime("last day of $month $year")),
            ];
        }
        return $months;
    }
}

if (!function_exists('get_default_year')) {
    function get_default_year()
    {
        return date('Y');
    }
}

if (!function_exists('get_no_days')) {
    function get_no_days()
    {
        return [
            'days' => 1,
            'weeks' => 7,
            'months' => 30,
        ];
    }
}

if (!function_exists('get_pie_chart_colors')) {
    function get_pie_chart_colors()
    {
        return [
            'light_green' => '#7AC142',
            'yellow' => '#FDBB2F',
            'orange' => '#F47A1F',
            'red' => '#D72828',
            'blue' => '#3C8DBC',
            'cyan-blue' => '#7CDDDD',
            'cream' => '#FFF1C9',
            'peach' => '#F7B7A3',
            'melon' => '#EA5F89',
            'light-purple' => '#9B3192',
            'darker-purple' => '#57167E',
            'deep-purple' => '#2B0B3F',
        ];
    }
}

if (!function_exists('get_module_names')) {
    function get_module_names()
    {
        $permissions = (new ModuleUtil())->getModuleData('superadmin_package');
        $module_names = [];

        foreach ($permissions as $index => $permission) {
            $key = strtolower($index);
            $module_names[$key] = $permission[0]['name'];
        }
        return (object) $module_names;
    }
}

if (!function_exists('get_accounting_transactions_tabs')) {
    function get_accounting_transactions_tabs()
    {
        return [
            'loan' => (object) [
                'url' => 'accounting/transactions/loan',
                'label' => trans_choice('loan::general.loan', 2),
            ],
        ];
    }
}

if (!function_exists('get_accounting_transactions_index')) {
    function get_accounting_transactions_index()
    {
        $tabs = get_accounting_transactions_tabs();
        $tab_keys = array_keys($tabs);
        $module_names = (array) get_module_names();

        foreach ($tab_keys as $key) {
            return $tabs[$key]->url;
        }

        return '#';
    }
}

if (!function_exists('get_decimal_places')) {
    function get_decimal_places()
    {
        return 2;
    }
}
