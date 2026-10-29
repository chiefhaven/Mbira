<?php

namespace  Modules\Accounting\Utils;

use Modules\Accounting\Entities\BusinessLocation;
use Modules\Accounting\Entities\Contact;
use Modules\Accounting\Entities\Transaction;
use App\Utils\BusinessUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PurchaseUtil
{
    /**
     * All Utils instance.
     *
     */
    protected $productUtil;
    protected $transactionUtil;
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param ProductUtils $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, TransactionUtil $transactionUtil, BusinessUtil $businessUtil)
    {
        $this->productUtil = $productUtil;
        $this->transactionUtil = $transactionUtil;
        $this->businessUtil = $businessUtil;

        $this->purchaseOrderStatuses = [
            'ordered' => [
                'label' => __('lang_v1.ordered'),
                'class' => 'bg-info'
            ],
            'partial' => [
                'label' => __('lang_v1.partial'),
                'class' => 'bg-yellow'
            ],
            'completed' => [
                'label' => __('restaurant.completed'),
                'class' => 'bg-green'
            ]
        ];

        $this->shipping_status_colors = [
            'ordered' => 'bg-yellow',
            'packed' => 'bg-info',
            'shipped' => 'bg-navy',
            'delivered' => 'bg-green',
            'cancelled' => 'bg-red',
        ];
    }

    public function getPurchaseOrdersDataTable()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());
        $shipping_statuses = $this->transactionUtil->shipping_statuses();
        $business_id = request()->session()->get('user.business_id');

        $purchase_orders = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            ->join(
                'business_locations AS BS',
                'transactions.location_id',
                '=',
                'BS.id'
            )
            ->leftJoin('purchase_lines as pl', 'transactions.id', '=', 'pl.transaction_id')
            ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'purchase_order')
            ->select(
                'transactions.id',
                'transactions.document',
                'transactions.transaction_date',
                'transactions.ref_no',
                'transactions.status',
                'contacts.name',
                'contacts.supplier_business_name',
                'transactions.final_total',
                'BS.name as location_name',
                'transactions.pay_term_number',
                'transactions.pay_term_type',
                'transactions.shipping_status',
                DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by"),
                DB::raw('SUM(pl.quantity - pl.po_quantity_purchased) as po_qty_remaining')
            )
            ->groupBy('transactions.id');

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $purchase_orders->whereIn('transactions.location_id', $permitted_locations);
        }

        if (!empty(request()->supplier_id)) {
            $purchase_orders->where('contacts.id', request()->supplier_id);
        }
        if (!empty(request()->location_id)) {
            $purchase_orders->where('transactions.location_id', request()->location_id);
        }

        if (!empty(request()->status)) {
            $purchase_orders->where('transactions.status', request()->status);
        }

        if (!empty(request()->from_dashboard)) {
            $purchase_orders->where('transactions.status', '!=', 'completed')
                ->orHavingRaw('po_qty_remaining > 0');
        }

        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start = request()->start_date;
            $end =  request()->end_date;
            $purchase_orders->whereDate('transactions.transaction_date', '>=', $start)
                ->whereDate('transactions.transaction_date', '<=', $end);
        }

        if (!auth()->user()->can('purchase_order.view_all') && auth()->user()->can('purchase_order.view_own')) {
            $purchase_orders->where('transactions.created_by', request()->session()->get('user.id'));
        }

        if (!empty(request()->input('shipping_status'))) {
            $purchase_orders->where('transactions.shipping_status', request()->input('shipping_status'));
        }

        return DataTables::of($purchase_orders)
            ->addColumn('action', function ($row) use ($is_admin) {
                $html = '<div class="btn-group">
                            <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                data-toggle="dropdown" aria-expanded="false">' .
                    __("messages.actions") .
                    '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-left" role="menu">';
                if (auth()->user()->can("purchase_order.view_all") || auth()->user()->can("purchase_order.view_own")) {
                    $html .= '<li><a href="#" data-href="' . action('PurchaseOrderController@show', [$row->id]) . '" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i>' . __("messages.view") . '</a></li>';

                    $html .= '<li><a href="#" class="print-invoice" data-href="' . action('PurchaseController@printInvoice', [$row->id]) . '"><i class="fas fa-print" aria-hidden="true"></i>' . __("messages.print") . '</a></li>';
                }
                if (config('constants.enable_download_pdf') && (auth()->user()->can("purchase_order.view_all") || auth()->user()->can("purchase_order.view_own"))) {
                    $html .= '<li><a href="' . route('purchaseOrder.downloadPdf', [$row->id]) . '" target="_blank"><i class="fas fa-print" aria-hidden="true"></i> ' . __("lang_v1.download_pdf") . '</a></li>';
                }
                if (auth()->user()->can("purchase_order.update")) {
                    $html .= '<li><a href="' . action('PurchaseOrderController@edit', [$row->id]) . '"><i class="fas fa-edit"></i>' . __("messages.edit") . '</a></li>';
                }
                if (auth()->user()->can("purchase_order.delete")) {
                    $html .= '<li><a href="' . action('PurchaseOrderController@destroy', [$row->id]) . '" class="delete-purchase-order"><i class="fas fa-trash"></i>' . __("messages.delete") . '</a></li>';
                }

                if ($is_admin || auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
                    $html .= '<li><a href="#" data-href="' . action('SellController@editShipping', [$row->id]) . '" class="btn-modal" data-container=".view_modal"><i class="fas fa-truck" aria-hidden="true"></i>' . __("lang_v1.edit_shipping") . '</a></li>';
                }

                if ((auth()->user()->can("purchase_order.view_all") || auth()->user()->can("purchase_order.view_own")) && !empty($row->document)) {
                    $document_name = !empty(explode("_", $row->document, 2)[1]) ? explode("_", $row->document, 2)[1] : $row->document;
                    $html .= '<li><a href="' . url('uploads/documents/' . $row->document) . '" download="' . $document_name . '"><i class="fas fa-download" aria-hidden="true"></i>' . __("purchase.download_document") . '</a></li>';
                    if (isFileImage($document_name)) {
                        $html .= '<li><a href="#" data-href="' . url('uploads/documents/' . $row->document) . '" class="view_uploaded_document"><i class="fas fa-image" aria-hidden="true"></i>' . __("lang_v1.view_document") . '</a></li>';
                    }
                }

                $html .=  '</ul></div>';
                return $html;
            })
            ->removeColumn('id')
            ->editColumn(
                'final_total',
                '<span class="final_total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
            )
            ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
            ->editColumn('po_qty_remaining', '{{@format_quantity($po_qty_remaining)}}')
            ->editColumn('name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$name}}')
            ->editColumn('status', function ($row) use ($is_admin) {
                $status = '';
                $order_statuses = $this->purchaseOrderStatuses;
                if (array_key_exists($row->status, $order_statuses)) {
                    if ($is_admin && $row->status != 'completed') {
                        $status = '<span class="edit-po-status label ' . $order_statuses[$row->status]['class']
                            . '" data-href="' . action("PurchaseOrderController@getEditPurchaseOrderStatus", ['id' => $row->id]) . '">' . $order_statuses[$row->status]['label'] . '</span>';
                    } else {
                        $status = '<span class="label ' . $order_statuses[$row->status]['class']
                            . '" >' . $order_statuses[$row->status]['label'] . '</span>';
                    }
                }

                return $status;
            })
            ->editColumn('shipping_status', function ($row) use ($shipping_statuses) {
                $status_color = !empty($this->shipping_status_colors[$row->shipping_status]) ? $this->shipping_status_colors[$row->shipping_status] : 'bg-gray';
                $status = !empty($row->shipping_status) ? '<a href="#" class="btn-modal" data-href="' . action('SellController@editShipping', [$row->id]) . '" data-container=".view_modal"><span class="label ' . $status_color . '">' . $shipping_statuses[$row->shipping_status] . '</span></a>' : '';

                return $status;
            })
            ->setRowAttr([
                'data-href' => function ($row) {
                    return  action('PurchaseOrderController@show', [$row->id]);
                }
            ])
            ->rawColumns(['final_total', 'action', 'ref_no', 'name', 'status', 'shipping_status'])
            ->make(true);
    }

    public function getPurchaseOrderTransactionsDataTable()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());
        $shipping_statuses = $this->transactionUtil->shipping_statuses();
        $business_id = request()->session()->get('user.business_id');

        $purchase_orders = Transaction::with('journal_entry')
            ->leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            ->join(
                'business_locations AS BS',
                'transactions.location_id',
                '=',
                'BS.id'
            )
            ->leftJoin('purchase_lines as pl', 'transactions.id', '=', 'pl.transaction_id')
            ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'purchase_order')
            ->select(
                'transactions.id',
                'transactions.journal_entry_id',
                'transactions.document',
                'transactions.transaction_date',
                'transactions.ref_no',
                'transactions.status',
                'contacts.name',
                'contacts.supplier_business_name',
                'transactions.final_total',
                'BS.name as location_name',
                'transactions.pay_term_number',
                'transactions.pay_term_type',
                'transactions.shipping_status',
                DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by"),
                DB::raw('SUM(pl.quantity - pl.po_quantity_purchased) as po_qty_remaining')
            )
            ->groupBy('transactions.id');

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $purchase_orders->whereIn('transactions.location_id', $permitted_locations);
        }

        if (!empty(request()->supplier_id)) {
            $purchase_orders->where('contacts.id', request()->supplier_id);
        }
        if (!empty(request()->location_id)) {
            $purchase_orders->where('transactions.location_id', request()->location_id);
        }

        if (!empty(request()->status)) {
            $purchase_orders->where('transactions.status', request()->status);
        }

        if (!empty(request()->from_dashboard)) {
            $purchase_orders->where('transactions.status', '!=', 'completed')
                ->orHavingRaw('po_qty_remaining > 0');
        }

        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start = request()->start_date;
            $end =  request()->end_date;
            $purchase_orders->whereDate('transactions.transaction_date', '>=', $start)
                ->whereDate('transactions.transaction_date', '<=', $end);
        }

        if (!auth()->user()->can('purchase_order.view_all') && auth()->user()->can('purchase_order.view_own')) {
            $purchase_orders->where('transactions.created_by', request()->session()->get('user.id'));
        }

        if (!empty(request()->input('shipping_status'))) {
            $purchase_orders->where('transactions.shipping_status', request()->input('shipping_status'));
        }

        return DataTables::of($purchase_orders)
            ->addColumn('mapping', function ($row) {
                if (!empty($row->journal_entry_id)) {
                    return '<span class="label label-success">' . trans('accounting::general.has_been_mapped') . '</span>';
                } else {
                    return '<button class="btn btn-xs btn-success" onclick="setTransactionId(' . $row->id . ')" data-toggle="modal" data-target="#mapTransactionsModal">
                            MAP <span class="fas fa-share"></span>
                        </button>';
                }
            })
            ->addColumn('chart_of_account', function ($row) {
                if (!empty($row->journal_entry_id)) {
                    return '<a href="' . url("accounting/chart_of_account/" . $row->journal_entry->chart_of_account->id . "/show") . '">
                    <span>' . $row->journal_entry->chart_of_account->name . '</span> <i class="fas fa-external-link-alt"></i>
                </a>';
                } else {
                    return '<span class="label label-warning">' . trans('accounting::general.has_not_been_mapped') . '</span>';
                }
            })
            ->removeColumn('id')
            ->editColumn(
                'final_total',
                '<span class="final_total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
            )
            ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
            ->editColumn('po_qty_remaining', '{{@format_quantity($po_qty_remaining)}}')
            ->editColumn('name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$name}}')
            ->editColumn('status', function ($row) use ($is_admin) {
                $status = '';
                $order_statuses = $this->purchaseOrderStatuses;
                if (array_key_exists($row->status, $order_statuses)) {
                    if ($is_admin && $row->status != 'completed') {
                        $status = '<span class="edit-po-status label ' . $order_statuses[$row->status]['class']
                            . '" data-href="' . action("PurchaseOrderController@getEditPurchaseOrderStatus", ['id' => $row->id]) . '">' . $order_statuses[$row->status]['label'] . '</span>';
                    } else {
                        $status = '<span class="label ' . $order_statuses[$row->status]['class']
                            . '" >' . $order_statuses[$row->status]['label'] . '</span>';
                    }
                }

                return $status;
            })
            ->editColumn('shipping_status', function ($row) use ($shipping_statuses) {
                $status_color = !empty($this->shipping_status_colors[$row->shipping_status]) ? $this->shipping_status_colors[$row->shipping_status] : 'bg-gray';
                $status = !empty($row->shipping_status) ? '<a href="#" class="btn-modal" data-href="' . action('SellController@editShipping', [$row->id]) . '" data-container=".view_modal"><span class="label ' . $status_color . '">' . $shipping_statuses[$row->shipping_status] . '</span></a>' : '';

                return $status;
            })
            ->setRowAttr([
                'data-href' => function ($row) {
                    return  action('PurchaseOrderController@show', [$row->id]);
                }
            ])
            ->rawColumns(['final_total', 'mapping', 'ref_no', 'name', 'status', 'shipping_status', 'chart_of_account'])
            ->make(true);
    }

    public function getPurchaseOrdersData()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());
        $shipping_statuses = $this->transactionUtil->shipping_statuses();
        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id);
        $suppliers = Contact::suppliersDropdown($business_id, false);
        $purchaseOrderStatuses = [];
        foreach ($this->purchaseOrderStatuses as $key => $value) {
            $purchaseOrderStatuses[$key] = $value['label'];
        }

        return compact('business_locations', 'suppliers', 'purchaseOrderStatuses', 'shipping_statuses');
    }

    public function getPurchaseDataTable()
    {
        $business_id = request()->session()->get('user.business_id');

        $purchases = $this->getListPurchases($business_id);

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $purchases->whereIn('transactions.location_id', $permitted_locations);
        }

        if (!empty(request()->supplier_id)) {
            $purchases->where('contacts.id', request()->supplier_id);
        }
        if (!empty(request()->location_id)) {
            $purchases->where('transactions.location_id', request()->location_id);
        }
        if (!empty(request()->input('payment_status')) && request()->input('payment_status') != 'overdue') {
            $purchases->where('transactions.payment_status', request()->input('payment_status'));
        } elseif (request()->input('payment_status') == 'overdue') {
            $purchases->whereIn('transactions.payment_status', ['due', 'partial'])
                ->whereNotNull('transactions.pay_term_number')
                ->whereNotNull('transactions.pay_term_type')
                ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
        }

        if (!empty(request()->status)) {
            $purchases->where('transactions.status', request()->status);
        }

        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start = request()->start_date;
            $end =  request()->end_date;
            $purchases->whereDate('transactions.transaction_date', '>=', $start)
                ->whereDate('transactions.transaction_date', '<=', $end);
        }

        if (!auth()->user()->can('purchase.view') && auth()->user()->can('view_own_purchase')) {
            $purchases->where('transactions.created_by', request()->session()->get('user.id'));
        }

        return Datatables::of($purchases)
            ->addColumn('action', function ($row) {
                $html = '<div class="btn-group">
                            <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                data-toggle="dropdown" aria-expanded="false">' .
                    __("messages.actions") .
                    '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-left" role="menu">';
                if (auth()->user()->can("purchase.view")) {
                    $html .= '<li><a href="#" data-href="' . action('PurchaseController@show', [$row->id]) . '" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i>' . __("messages.view") . '</a></li>';
                }
                if (auth()->user()->can("purchase.view")) {
                    $html .= '<li><a href="#" class="print-invoice" data-href="' . action('PurchaseController@printInvoice', [$row->id]) . '"><i class="fas fa-print" aria-hidden="true"></i>' . __("messages.print") . '</a></li>';
                }
                if (auth()->user()->can("purchase.update")) {
                    $html .= '<li><a href="' . action('PurchaseController@edit', [$row->id]) . '"><i class="fas fa-edit"></i>' . __("messages.edit") . '</a></li>';
                }
                if (auth()->user()->can("purchase.delete")) {
                    $html .= '<li><a href="' . action('PurchaseController@destroy', [$row->id]) . '" class="delete-purchase"><i class="fas fa-trash"></i>' . __("messages.delete") . '</a></li>';
                }

                $html .= '<li><a href="' . action('LabelsController@show') . '?purchase_id=' . $row->id . '" data-toggle="tooltip" title="' . __('lang_v1.label_help') . '"><i class="fas fa-barcode"></i>' . __('barcode.labels') . '</a></li>';

                if (auth()->user()->can("purchase.view") && !empty($row->document)) {
                    $document_name = !empty(explode("_", $row->document, 2)[1]) ? explode("_", $row->document, 2)[1] : $row->document;
                    $html .= '<li><a href="' . url('uploads/documents/' . $row->document) . '" download="' . $document_name . '"><i class="fas fa-download" aria-hidden="true"></i>' . __("purchase.download_document") . '</a></li>';
                    if (isFileImage($document_name)) {
                        $html .= '<li><a href="#" data-href="' . url('uploads/documents/' . $row->document) . '" class="view_uploaded_document"><i class="fas fa-image" aria-hidden="true"></i>' . __("lang_v1.view_document") . '</a></li>';
                    }
                }

                if (auth()->user()->can("purchase.create")) {
                    $html .= '<li class="divider"></li>';
                    if ($row->payment_status != 'paid' && auth()->user()->can("purchase.payments")) {
                        $html .= '<li><a href="' . action('TransactionPaymentController@addPayment', [$row->id]) . '" class="add_payment_modal"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>' . __("purchase.add_payment") . '</a></li>';
                    }
                    $html .= '<li><a href="' . action('TransactionPaymentController@show', [$row->id]) .
                        '" class="view_payment_modal"><i class="fas fa-money-bill-alt" aria-hidden="true" ></i>' . __("purchase.view_payments") . '</a></li>';
                }

                if (auth()->user()->can("purchase.update")) {
                    $html .= '<li><a href="' . action('PurchaseReturnController@add', [$row->id]) .
                        '"><i class="fas fa-undo" aria-hidden="true" ></i>' . __("lang_v1.purchase_return") . '</a></li>';
                }

                if (auth()->user()->can("purchase.update") || auth()->user()->can("purchase.update_status")) {
                    $html .= '<li><a href="#" data-purchase_id="' . $row->id .
                        '" data-status="' . $row->status . '" class="update_status"><i class="fas fa-edit" aria-hidden="true" ></i>' . __("lang_v1.update_status") . '</a></li>';
                }

                if ($row->status == 'ordered') {
                    $html .= '<li><a href="#" data-href="' . action('NotificationController@getTemplate', ["transaction_id" => $row->id, "template_for" => "new_order"]) . '" class="btn-modal" data-container=".view_modal"><i class="fas fa-envelope" aria-hidden="true"></i> ' . __("lang_v1.new_order_notification") . '</a></li>';
                } elseif ($row->status == 'received') {
                    $html .= '<li><a href="#" data-href="' . action('NotificationController@getTemplate', ["transaction_id" => $row->id, "template_for" => "items_received"]) . '" class="btn-modal" data-container=".view_modal"><i class="fas fa-envelope" aria-hidden="true"></i> ' . __("lang_v1.item_received_notification") . '</a></li>';
                } elseif ($row->status == 'pending') {
                    $html .= '<li><a href="#" data-href="' . action('NotificationController@getTemplate', ["transaction_id" => $row->id, "template_for" => "items_pending"]) . '" class="btn-modal" data-container=".view_modal"><i class="fas fa-envelope" aria-hidden="true"></i> ' . __("lang_v1.item_pending_notification") . '</a></li>';
                }

                $html .=  '</ul></div>';
                return $html;
            })
            ->removeColumn('id')
            ->editColumn('ref_no', function ($row) {
                return !empty($row->return_exists) ? $row->ref_no . ' <small class="label bg-red label-round no-print" title="' . __('lang_v1.some_qty_returned') . '"><i class="fas fa-undo"></i></small>' : $row->ref_no;
            })
            ->editColumn(
                'final_total',
                '<span class="final_total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
            )
            ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
            ->editColumn('name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$name}}')
            ->editColumn(
                'status',
                '<a href="#" @if(auth()->user()->can("purchase.update") || auth()->user()->can("purchase.update_status")) class="update_status no-print" data-purchase_id="{{$id}}" data-status="{{$status}}" @endif><span class="label @transaction_status($status) status-label" data-status-name="{{__(\'lang_v1.\' . $status)}}" data-orig-value="{{$status}}">{{__(\'lang_v1.\' . $status)}}
                        </span></a>'
            )
            ->editColumn(
                'payment_status',
                function ($row) {
                    $payment_status = Transaction::getPaymentStatus($row);
                    return (string) view('sell.partials.payment_status', ['payment_status' => $payment_status, 'id' => $row->id, 'for_purchase' => true]);
                }
            )
            ->addColumn('payment_due', function ($row) {
                $due = $row->final_total - $row->amount_paid;
                $due_html = '<strong>' . __('lang_v1.purchase') . ':</strong> <span class="payment_due" data-orig-value="' . $due . '">' . $this->transactionUtil->num_f($due, true) . '</span>';

                if (!empty($row->return_exists)) {
                    $return_due = $row->amount_return - $row->return_paid;
                    $due_html .= '<br><strong>' . __('lang_v1.purchase_return') . ':</strong> <a href="' . action("TransactionPaymentController@show", [$row->return_transaction_id]) . '" class="view_purchase_return_payment_modal"><span class="purchase_return" data-orig-value="' . $return_due . '">' . $this->transactionUtil->num_f($return_due, true) . '</span></a>';
                }
                return $due_html;
            })
            ->setRowAttr([
                'data-href' => function ($row) {
                    if (auth()->user()->can("purchase.view")) {
                        return  action('PurchaseController@show', [$row->id]);
                    } else {
                        return '';
                    }
                }
            ])
            ->rawColumns(['final_total', 'action', 'payment_due', 'payment_status', 'status', 'ref_no', 'name'])
            ->make(true);
    }

    public function getPurchaseTransactionsDataTable()
    {
        $business_id = request()->session()->get('user.business_id');

        $purchases = $this->getListPurchases($business_id);

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $purchases->whereIn('transactions.location_id', $permitted_locations);
        }

        if (!empty(request()->supplier_id)) {
            $purchases->where('contacts.id', request()->supplier_id);
        }
        if (!empty(request()->location_id)) {
            $purchases->where('transactions.location_id', request()->location_id);
        }
        if (!empty(request()->input('payment_status')) && request()->input('payment_status') != 'overdue') {
            $purchases->where('transactions.payment_status', request()->input('payment_status'));
        } elseif (request()->input('payment_status') == 'overdue') {
            $purchases->whereIn('transactions.payment_status', ['due', 'partial'])
                ->whereNotNull('transactions.pay_term_number')
                ->whereNotNull('transactions.pay_term_type')
                ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
        }

        if (!empty(request()->status)) {
            $purchases->where('transactions.status', request()->status);
        }

        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start = request()->start_date;
            $end =  request()->end_date;
            $purchases->whereDate('transactions.transaction_date', '>=', $start)
                ->whereDate('transactions.transaction_date', '<=', $end);
        }

        if (!auth()->user()->can('purchase.view') && auth()->user()->can('view_own_purchase')) {
            $purchases->where('transactions.created_by', request()->session()->get('user.id'));
        }

        return Datatables::of($purchases)
            ->addColumn('mapping', function ($row) {
                if (!empty($row->journal_entry_id)) {
                    return '<span class="label label-success">' . trans('accounting::general.has_been_mapped') . '</span>';
                } else {
                    return '<button class="btn btn-xs btn-success" onclick="setTransactionId(' . $row->id . ')" data-toggle="modal" data-target="#mapTransactionsModal">
                            MAP <span class="fas fa-share"></span>
                        </button>';
                }
            })
            ->addColumn('chart_of_account', function ($row) {
                if (!empty($row->journal_entry_id)) {
                    return '<a href="' . url("accounting/chart_of_account/" . $row->journal_entry->chart_of_account->id . "/show") . '">
                    <span>' . $row->journal_entry->chart_of_account->name . '</span> <i class="fas fa-external-link-alt"></i>
                </a>';
                } else {
                    return '<span class="label label-warning">' . trans('accounting::general.has_not_been_mapped') . '</span>';
                }
            })
            ->removeColumn('id')
            ->editColumn('ref_no', function ($row) {
                return !empty($row->return_exists) ? $row->ref_no . ' <small class="label bg-red label-round no-print" title="' . __('lang_v1.some_qty_returned') . '"><i class="fas fa-undo"></i></small>' : $row->ref_no;
            })
            ->editColumn(
                'final_total',
                '<span class="final_total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
            )
            ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
            ->editColumn('name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$name}}')
            ->editColumn(
                'status',
                '<a href="#" @if(auth()->user()->can("purchase.update") || auth()->user()->can("purchase.update_status")) class="update_status no-print" data-purchase_id="{{$id}}" data-status="{{$status}}" @endif><span class="label @transaction_status($status) status-label" data-status-name="{{__(\'lang_v1.\' . $status)}}" data-orig-value="{{$status}}">{{__(\'lang_v1.\' . $status)}}
                        </span></a>'
            )
            ->editColumn(
                'payment_status',
                function ($row) {
                    $payment_status = Transaction::getPaymentStatus($row);
                    return (string) view('sell.partials.payment_status', ['payment_status' => $payment_status, 'id' => $row->id, 'for_purchase' => true]);
                }
            )
            ->addColumn('payment_due', function ($row) {
                $due = $row->final_total - $row->amount_paid;
                $due_html = '<strong>' . __('lang_v1.purchase') . ':</strong> <span class="payment_due" data-orig-value="' . $due . '">' . $this->transactionUtil->num_f($due, true) . '</span>';

                if (!empty($row->return_exists)) {
                    $return_due = $row->amount_return - $row->return_paid;
                    $due_html .= '<br><strong>' . __('lang_v1.purchase_return') . ':</strong> <a href="' . action("TransactionPaymentController@show", [$row->return_transaction_id]) . '" class="view_purchase_return_payment_modal"><span class="purchase_return" data-orig-value="' . $return_due . '">' . $this->transactionUtil->num_f($return_due, true) . '</span></a>';
                }
                return $due_html;
            })
            ->setRowAttr([
                'data-href' => function ($row) {
                    if (auth()->user()->can("purchase.view")) {
                        return  action('PurchaseController@show', [$row->id]);
                    } else {
                        return '';
                    }
                }
            ])
            ->rawColumns(['final_total', 'mapping', 'payment_due', 'payment_status', 'status', 'ref_no', 'name', 'chart_of_account'])
            ->make(true);
    }

    public function getPurchaseData()
    {
        $business_id = request()->session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($business_id);
        $suppliers = Contact::suppliersDropdown($business_id, false);
        $orderStatuses = $this->productUtil->orderStatuses();

        return compact('business_locations', 'suppliers', 'orderStatuses');
    }

    /**
     * common function to get
     * list purchase
     * @param int $business_id
     *
     * @return object
     */
    public function getListPurchases($business_id)
    {
        $purchases = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            ->join(
                'business_locations AS BS',
                'transactions.location_id',
                '=',
                'BS.id'
            )
            ->leftJoin(
                'transaction_payments AS TP',
                'transactions.id',
                '=',
                'TP.transaction_id'
            )
            ->leftJoin(
                'transactions AS PR',
                'transactions.id',
                '=',
                'PR.return_parent_id'
            )
            ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', 'purchase')
            ->select(
                'transactions.id',
                'transactions.journal_entry_id',
                'transactions.document',
                'transactions.transaction_date',
                'transactions.ref_no',
                'contacts.name',
                'contacts.supplier_business_name',
                'transactions.status',
                'transactions.payment_status',
                'transactions.final_total',
                'BS.name as location_name',
                'transactions.pay_term_number',
                'transactions.pay_term_type',
                'PR.id as return_transaction_id',
                DB::raw('SUM(TP.amount) as amount_paid'),
                DB::raw('(SELECT SUM(TP2.amount) FROM transaction_payments AS TP2 WHERE
                        TP2.transaction_id=PR.id ) as return_paid'),
                DB::raw('COUNT(PR.id) as return_exists'),
                DB::raw('COALESCE(PR.final_total, 0) as amount_return'),
                DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by")
            )
            ->groupBy('transactions.id');

        return $purchases;
    }
}
