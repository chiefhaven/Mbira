<?php

namespace Modules\Hms\Http\Controllers;

use App\System;
use App\Utils\Util;
use Illuminate\Routing\Controller;
use Menu;
use App\Utils\ModuleUtil;
use App\Transaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Modules\Hms\Entities\HmsRoomType;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;


class DataController extends Controller
{
      /**
     * Defines user permissions for the module.
     *
     * @return array
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'hms.manage_rooms',
                'label' => __('hms::lang.manage_rooms'),
                'default' => false,
            ],
            [
                'value' => 'hms.manage_price',
                'label' => __('hms::lang.manage_price'),
                'default' => false,
            ],
            [
                'value' => 'hms.manage_unavailable',
                'label' => __('hms::lang.manage_unavailable'),
                'default' => false,
            ],
            [
                'value' => 'hms.manage_extra',
                'label' => __('hms::lang.manage_extra'),
                'default' => false,
            ],
            [
                'value' => 'hms.manage_coupon',
                'label' => __('hms::lang.manage_coupon'),
                'default' => false,
            ],
            [
                'value' => 'hms.add_booking',
                'label' => __('hms::lang.add_booking'),
                'default' => false,
            ],
            [
                'value' => 'hms.edit_booking',
                'label' => __('hms::lang.edit_booking'),
                'default' => false,
            ],
            [
                'value' => 'hms.delete_booking',
                'label' => __('hms::lang.delete_booking'),
                'default' => false,
            ],
            [
                'value' => 'hms.manage_amenities',
                'label' => __('hms::lang.manage_amenities'),
                'default' => false,
            ],

            [
                'value' => 'hms.manage_settings',
                'label' => __('hms::lang.manage_settings'),
                'default' => false,
            ],

            [
                'value' => 'hms.add_booking_payment',
                'label' => __('hms::lang.add_booking_payment'),
                'default' => false,
            ],
            [
                'value' => 'hms.edit_booking_payment',
                'label' => __('hms::lang.edit_booking_payment'),
                'default' => false,
            ],
            [
                'value' => 'hms.delete_booking_payment',
                'label' => __('hms::lang.delete_booking_payment'),
                'default' => false,
            ],

        ];
    }

    /**
     * Superadmin package permissions
     *
     * @return array
     */
    public function superadmin_package()
    {
        return [
            [
                'name' => 'hms_module',
                'label' => __('hms::lang.hms_module'),
                'default' => false,
            ],
        ];
    }


    /**
     * Hms Report options
     *
     * @return array
     */
    public function InboxReportOptions()
    {
        return [
            [
                'name' => 'hms_report',
                'label' => __('hms::lang.hms_report'),
                'default' => false,
            ],
        ];
    }

      /**
     * Inbox Report hms
     *
     * @return array
     */

    public function hms_report($business){

        $business_id = $business->id;

        $inbox_settings = json_decode($business->inbox_report_settings);
        $frequency = $inbox_settings->frequency ?? null;

        $commonUtil = new Util();

        $transactionUtil = new TransactionUtil();

            
        
        $date_from =  Carbon::now()->subDays(1)->format('Y-m-d') . ' 23:59:59';

        $dates = ['date_to' => null, 'date_from' => $date_from];

       
        if($frequency === 'weekly'){
            $date_to = Carbon::now()->startOfWeek()->format('Y-m-d') . ' 00:00:00';
            $dates = ['date_to' => $date_to, 'date_from' => $date_from];
        }elseif($frequency === 'monthly'){
            $date_to = Carbon::now()->startOfMonth()->format('Y-m-d') . ' 00:00:00';
            $dates = ['date_to' => $date_to, 'date_from' => $date_from];
        }elseif($frequency === 'daily'){
            $date_to =  Carbon::now()->subDays(1)->format('Y-m-d') . ' 00:00:00';
            $dates = ['date_to' => $date_to, 'date_from' => null];
        }

        $pending_room_types = $this->room_type_count('pending', $date_to, $date_from, $business);

        $cancelled_room_types = $this->room_type_count('cancelled', $date_to, $date_from, $business);  
        $confirmed_room_types = $this->room_type_count('confirmed', $date_to, $date_from, $business);


        $all_room_types = HmsRoomType::select(
            'hms_room_types.type',
            DB::raw('(SELECT COUNT(DISTINCT transactions.id) FROM hms_booking_lines
                        LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                        WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                            AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ? ) as transactions_count'),
            DB::raw('(SELECT SUM(hms_booking_lines.total_price) FROM hms_booking_lines
                        LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                        WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                            AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ? ) as total_price'),
            DB::raw('(SELECT SUM( DATEDIFF(transactions.hms_booking_departure_date_time, transactions.hms_booking_arrival_date_time)) FROM hms_booking_lines
                        LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                        WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                            AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ? ) as total_days'),
            DB::raw('(SELECT SUM(hms_booking_lines.adults + hms_booking_lines.childrens) FROM hms_booking_lines
                        LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                        WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                            AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?) as no_of_guest'),
        )
        ->setBindings([$date_to, $date_from, $date_to, $date_from, $date_to, $date_from, $date_to, $date_from])
        ->where('hms_room_types.business_id', $business_id)
        ->get();
       
        return [
            'html' => view('hms::report.email_report', compact('pending_room_types', 'cancelled_room_types', 'confirmed_room_types', 'all_room_types', 'business', 'transactionUtil', 'dates'))
        ];

    }

    /**
     * Adds hms menus
     *
     * @return null
     */
    public function modifyAdminMenu()
    {
        $module_util = new ModuleUtil();

        $business_id = session()->get('user.business_id');
        $is_hms_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'hms_module');

        if ($is_hms_enabled) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->url(
                    action([\Modules\Hms\Http\Controllers\HmsController::class, 'index']),
                    __('hms::lang.hms'),
                    ['icon' => 'fas fa-hotel', 'style' => config('app.env') == 'demo' ? 'background-color: yellow !important;' : '', 'active' => request()->segment(1) == 'hms']
                )->order(50);
            });
        }
          
    }

      /**
     * Function to add essential module taxonomies
     *
     * @return array
     */
    public function addTaxonomies()
    {
        $module_util = new ModuleUtil();
        $business_id = request()->session()->get('user.business_id');

        $output = [
            'amenities' => [],
        ];
    
            if (auth()->user()->can('hms.manage_amenities')) {
                $output['amenities'] = [
                    'taxonomy_label' => __('hms::lang.amenity'),
                    'heading' => __('hms::lang.amenities'),
                    'sub_heading' => __('hms::lang.amenities'),
                    'enable_taxonomy_code' => false,
                    'enable_sub_taxonomy' => false,
                    'heading_tooltip' => __('hms::lang.amenity_help_text'),
                    'navbar' => 'hms::layouts.nav',
                ];
            }
        return $output;
    }

    public function profitLossReportData($data)
    {
        $business_id = $data['business_id'];
        $location_id = ! empty($data['location_id']) ? $data['location_id'] : null;
        $start_date = ! empty($data['start_date']) ? $data['start_date'] : null;
        $end_date = ! empty($data['end_date']) ? $data['end_date'] : null;
        $user_id = ! empty($data['user_id']) ? $data['user_id'] : null;

        $final_total = $this->get_hms_total(
            $business_id,
            $start_date,
            $end_date,
            $location_id,
            $user_id
        ); 

        $report_data = [
            //left side data
            [],
            //right side data
            [
                [
                    'value' => $final_total,
                    'label' => __('hms::lang.hms_total'),
                    'add_to_net_profit' => true,
                ],
            ],
        ];

        return $report_data;
    }

/**
     * get gross project from
     * project
     *
     * @param $business_id, $start_date, $end_date,
     *  $location_id
     * @return decimal
*/
    public function grossProfit($data)
    {
        $business_id = $data['business_id'];
        $location_id = ! empty($data['location_id']) ? $data['location_id'] : null;
        $start_date = ! empty($data['start_date']) ? $data['start_date'] : null;
        $end_date = ! empty($data['end_date']) ? $data['end_date'] : null;
        $user_id = ! empty($data['user_id']) ? $data['user_id'] : null;

        $final_total = $this->get_hms_total(
            $business_id,
            $start_date,
            $end_date,
            $location_id,
            $user_id
        ); 

        $data = [
            'value' => $final_total,
            'label' => __('hms::lang.hms_total'),
        ];

        return $data;
    }
    
 /**
    * Calculates final total of hms booking 
    *
    * @param  int  $business_id
    * @param  string  $start_date = null
    * @param  string  $end_date = null
    * @param  int  $location_id = null
    * @return array
*/

    public function get_hms_total( $business_id, $start_date = null, $end_date = null, $location_id = null, $user_id = null){
        
        $transaction = Transaction::where('business_id', $business_id)
                        ->where('type', 'hms_booking')
                        ->where('status', 'confirmed');

                        if (! empty($start_date) && ! empty($end_date)) {
                            if ($start_date == $end_date) {
                                $transaction->whereDate('hms_booking_arrival_date_time', $end_date);
                            } else {
                                $transaction->whereBetween(DB::raw('hms_booking_arrival_date_time'), [$start_date, $end_date]);
                            }
                        }

                        // if(!empty($location_id)){
                        //     $transaction->where('location_id', $location_id);
                        // }

                        if(!empty($user_id)){
                            $transaction->where('created_by', $user_id);
                        }

                        $transaction = $transaction->sum('final_total');

                        return $transaction;
    }

    public function room_type_count($status, $date_to, $date_from , $business){
        $business_id = $business->id;
        
        return HmsRoomType::select(
            'hms_room_types.type',
            DB::raw('(SELECT COUNT(DISTINCT transactions.id) FROM hms_booking_lines
                       LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                       WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                         AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?
                         AND transactions.status = "'.$status.'") as transactions_count'),
            DB::raw('(SELECT SUM(hms_booking_lines.total_price) FROM hms_booking_lines
                       LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                       WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                         AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?
                         AND transactions.status = "'.$status.'") as total_price'),
            DB::raw('(SELECT SUM( DATEDIFF(transactions.hms_booking_departure_date_time, transactions.hms_booking_arrival_date_time)) FROM hms_booking_lines
                       LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                       WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                         AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?
                         AND transactions.status = "'.$status.'") as total_days'),
            DB::raw('(SELECT SUM(hms_booking_lines.adults + hms_booking_lines.childrens) FROM hms_booking_lines
                       LEFT JOIN transactions ON hms_booking_lines.transaction_id = transactions.id
                       WHERE hms_booking_lines.hms_room_type_id = hms_room_types.id
                         AND transactions.hms_booking_arrival_date_time BETWEEN ? AND ?
                         AND transactions.status = "'.$status.'") as no_of_guest'),
        )
        ->setBindings([$date_to, $date_from, $date_to, $date_from, $date_to, $date_from, $date_to, $date_from])
        ->where('hms_room_types.business_id', $business_id)
        ->get();
    }
}
