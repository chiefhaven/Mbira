<?php

namespace App\Http\Controllers\Restaurant;

use App\TransactionSellLine;
use App\Utils\RestaurantUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Services\Sales\RestaurantBillService;
use App\Services\Sales\SavedSplitException;
use Illuminate\Support\Facades\DB;

class KitchenController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    protected $restUtil;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @param  RestaurantUtil  $restUtil
     * @return void
     */
    public function __construct(Util $commonUtil, RestaurantUtil $restUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->restUtil = $restUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        // if (!auth()->user()->can('sell.view')) {
        //     abort(403, 'Unauthorized action.');
        // }

        $business_id = request()->session()->get('user.business_id');
        
        $orders = $this->restUtil->getAllOrders($business_id, ['line_order_status' => 'received', 'is_kitchen_order' => 1]);

        $kitchen_tickets = $this->managedKitchenOrders();
        return view('restaurant.kitchen.index', compact('orders', 'kitchen_tickets'));
    }

    private function managedKitchenOrders(): array
    {
        $user = auth()->user();
        if (!DB::getSchemaBuilder()->hasTable('restaurant_bills') ||
            !DB::table('restaurant_bills')->where('business_id', $user->business_id)->exists() ||
            (!$user->hasRole('Admin#'.$user->business_id) && !$user->can('superadmin') && !$user->can('restaurant.kitchen'))) {
            return [];
        }
        return (new RestaurantBillService(DB::connection()))->kitchenOrders($user);
    }

    public function markTicketAsCooked(Request $request, $id)
    {
        $data = $request->validate(['ticket_id' => 'required|integer|min:1', 'operation_id' => 'required|uuid']);
        try {
            (new RestaurantBillService(DB::connection()))->ticket($request->user(), (int) $id, [
                'ticket_id' => (int) $data['ticket_id'], 'operation_id' => $data['operation_id'],
                'station' => 'kitchen', 'status' => 'cooked',
            ]);
            return response()->json(['success' => true, 'msg' => trans('restaurant.order_successfully_marked_cooked')]);
        } catch (SavedSplitException $e) {
            return response()->json(['success' => false, 'msg' => $e->getMessage()], $e->httpStatus);
        }
    }

    /**
     * Marks an order as cooked
     *
     * @return json $output
     */
    public function markAsCooked($id)
    {
        if (DB::getSchemaBuilder()->hasTable('restaurant_bills') &&
            DB::table('restaurant_bills')->where('transaction_id', $id)->where('business_id', auth()->user()->business_id)->exists()) {
            return response()->json(['success' => false, 'msg' => trans('restaurant.use_round_ticket')], 409);
        }
        // if (!auth()->user()->can('sell.update')) {
        //     abort(403, 'Unauthorized action.');
        // }
        try {
            $business_id = request()->session()->get('user.business_id');
            $sl = TransactionSellLine::leftJoin('transactions as t', 't.id', '=', 'transaction_sell_lines.transaction_id')
                        ->where('t.business_id', $business_id)
                        ->where('transaction_id', $id)
                        ->where(function ($q) {
                            $q->whereNull('res_line_order_status')
                                ->orWhere('res_line_order_status', 'received');
                        })
                        ->update(['res_line_order_status' => 'cooked']);

            $output = ['success' => 1,
                'msg' => trans('restaurant.order_successfully_marked_cooked'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Retrives fresh orders
     *
     * @return Json $output
     */
    public function refreshOrdersList(Request $request)
    {

        // if (!auth()->user()->can('sell.view')) {
        //     abort(403, 'Unauthorized action.');
        // }
        $business_id = request()->session()->get('user.business_id');
        $orders_for = $request->orders_for;
        $filter = [];
        $service_staff_id = request()->session()->get('user.id');

        if (! $this->restUtil->is_service_staff($service_staff_id) && ! empty($request->input('service_staff_id'))) {
            $service_staff_id = $request->input('service_staff_id');
        }

        if ($orders_for == 'kitchen') {
            $filter['line_order_status'] = 'received';
            $filter['is_kitchen_order'] = 1;
        } elseif ($orders_for == 'waiter') {
            $filter['waiter_id'] = $service_staff_id;
        }

        $orders = $this->restUtil->getAllOrders($business_id, $filter);

        $kitchen_tickets = $orders_for === 'kitchen' ? $this->managedKitchenOrders() : [];
        return view('restaurant.partials.show_orders', compact('orders', 'orders_for', 'kitchen_tickets'));
    }

    /**
     * Retrives fresh orders
     *
     * @return Json $output
     */
    public function refreshLineOrdersList(Request $request)
    {

        // if (!auth()->user()->can('sell.view')) {
        //     abort(403, 'Unauthorized action.');
        // }
        $business_id = request()->session()->get('user.business_id');
        $orders_for = $request->orders_for;
        $filter = [];
        $service_staff_id = request()->session()->get('user.id');

        if (! $this->restUtil->is_service_staff($service_staff_id) && ! empty($request->input('service_staff_id'))) {
            $service_staff_id = $request->input('service_staff_id');
        }

        if ($orders_for == 'kitchen') {
            $filter['order_status'] = 'received';
        } elseif ($orders_for == 'waiter') {
            $filter['waiter_id'] = $service_staff_id;
        }

        $line_orders = $this->restUtil->getLineOrders($business_id, $filter);

        return view('restaurant.partials.line_orders', compact('line_orders', 'orders_for'));
    }
}
