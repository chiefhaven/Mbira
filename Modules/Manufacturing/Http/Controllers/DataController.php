<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\Transaction;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    /**
     * Defines module as a superadmin package.
     * @return Array
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'manufacturing_module',
                'label' => __('manufacturing::lang.manufacturing_module'),
                'default' => false
            ]
        ];
    }

    /**
     * Defines user permissions for the module.
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'manufacturing.access_recipe',
                'label' => __('manufacturing::lang.access_recipe'),
                'default' => false
            ],
            [
                'value' => 'manufacturing.add_recipe',
                'label' => __('manufacturing::lang.add_recipe'),
                'default' => false
            ],
            [
                'value' => 'manufacturing.edit_recipe',
                'label' => __('manufacturing::lang.edit_recipe'),
                'default' => false
            ],
            [
                'value' => 'manufacturing.access_production',
                'label' => __('manufacturing::lang.access_production'),
                'default' => false
            ]
        ];
    }

    /**
     * Adds Manufacturing menus
     * @return null
     */
    public function modifyAdminMenu()
    {
        $business_id = session()->get('user.business_id');
        $module_util = new ModuleUtil();
        $is_mfg_enabled = (boolean)$module_util->hasThePermissionInSubscription($business_id, 'manufacturing_module', 'superadmin_package');

        if ($is_mfg_enabled && (auth()->user()->can('manufacturing.access_recipe') || auth()->user()->can('manufacturing.access_production'))) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->url(
                        action('\Modules\Manufacturing\Http\Controllers\RecipeController@index'),
                        __('manufacturing::lang.manufacturing'),
                        ['icon' => 'fa fas fa-industry', 'style' => config('app.env') == 'demo' ? 'background-color: #ff851b;' : '', 'active' => request()->segment(1) == 'manufacturing']
                    )
                ->order(21);
            });
        }
    }

    public function profitLossReportData($data)
    {
        $business_id = $data['business_id'];
        $location_id = !empty($data['location_id']) ? $data['location_id'] : null;
        $start_date = !empty($data['start_date']) ? $data['start_date'] : null;
        $end_date = !empty($data['end_date']) ? $data['end_date'] : null;

        $total_production_cost = $this->__getTotalProductionCost(
            $business_id,
            $start_date,
            $end_date,
            $location_id
        );

        $report_data = [
            //left side data
            [
                [
                    'value' => $total_production_cost,
                    'label' => __('manufacturing::lang.total_production_cost'),
                    'add_to_net_profit' => true
                ]
            ],

            //right side data
            []
        ];

        return $report_data;
    }


    /**
     * Calculates total production cost
     *
     * @param  int $business_id
     * @param  string $start_date = null
     * @param  string $end_date = null
     * @param  int $location_id = null
     *
     * @return array
     */
    private function __getTotalProductionCost(
        $business_id,
        $start_date = null,
        $end_date = null,
        $location_id = null
        ) {
        $query = Transaction::where('business_id', $business_id)
                            ->where('type', 'production_purchase')
                            ->where('mfg_is_final', 1);
        
        //Check for permitted locations of a user
        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $query->whereIn('transactions.location_id', $permitted_locations);
        }

        if (!empty($start_date) && !empty($end_date)) {
            $query->whereBetween(DB::raw('date(transaction_date)'), [$start_date, $end_date]);
        }

        if (empty($start_date) && !empty($end_date)) {
            $query->whereDate('transaction_date', '<=', $end_date);
        }

        //Filter by the location
        if (!empty($location_id)) {
            $query->where('transactions.location_id', $location_id);
        }

        $total = $query->select(
            DB::raw('SUM(final_total - ((final_total * 100) / (mfg_production_cost + 100) ) ) as total_production_cost')
            )->first();

        
        $total_production_cost = !empty($total->total_production_cost) ? $total->total_production_cost : 0;

        return $total_production_cost;
    }
}
