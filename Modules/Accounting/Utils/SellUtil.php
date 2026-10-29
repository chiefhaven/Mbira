<?php

namespace Modules\Accounting\Utils;

use Modules\Accounting\Entities\BusinessLocation;
use Modules\Accounting\Entities\Contact;
use Modules\Accounting\Entities\Transaction;
use Modules\Accounting\Entities\User;
use App\Utils\BusinessUtil;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SellUtil
{
    /**
     * All Utils instance.
     *
     */
    protected $contactUtil;
    protected $businessUtil;
    protected $transactionUtil;
    protected $productUtil;


    /**
     * Constructor
     *
     * @param ProductUtils $product
     * @return void
     */
    public function __construct(ContactUtil $contactUtil, BusinessUtil $businessUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil, ProductUtil $productUtil)
    {
        $this->contactUtil = $contactUtil;
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->productUtil = $productUtil;

        $this->dummyPaymentLine = [
            'method' => '', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
            'is_return' => 0, 'transaction_no' => ''
        ];

        $this->shipping_status_colors = [
            'ordered' => 'bg-yellow',
            'packed' => 'bg-info',
            'shipped' => 'bg-navy',
            'delivered' => 'bg-green',
            'cancelled' => 'bg-red',
        ];
    }


    public function getData()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (!$is_admin && !auth()->user()->hasAnyPermission(['sell.view', 'sell.create', 'direct_sell.access', 'direct_sell.view', 'view_own_sell_only', 'view_commission_agent_sell', 'access_shipping', 'access_own_shipping', 'access_commission_agent_shipping', 'so.view_all', 'so.view_own'])) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $is_woocommerce = $this->moduleUtil->isModuleInstalled('Woocommerce');
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        $is_tables_enabled = $this->transactionUtil->isModuleEnabled('tables');
        $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');
        $is_types_service_enabled = $this->moduleUtil->isModuleEnabled('types_of_service');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);
        $sales_representative = User::forDropdown($business_id, false, false, true);

        //Commission agent filter
        $is_cmsn_agent_enabled = request()->session()->get('business.sales_cmsn_agnt');
        $commission_agents = [];
        if (!empty($is_cmsn_agent_enabled)) {
            $commission_agents = User::forDropdown($business_id, false, true, true);
        }

        //Service staff filter
        $service_staffs = null;
        if ($this->productUtil->isModuleEnabled('service_staff')) {
            $service_staffs = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $sources = $this->transactionUtil->getSources($business_id);
        if ($is_woocommerce) {
            $sources['woocommerce'] = 'Woocommerce';
        }

        return compact('business_locations', 'customers', 'is_woocommerce', 'sales_representative', 'is_cmsn_agent_enabled', 'commission_agents', 'service_staffs', 'is_tables_enabled', 'is_service_staff_enabled', 'is_types_service_enabled', 'shipping_statuses', 'sources');
    }

    public function getDataTable()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        $business_id = request()->session()->get('user.business_id');
        $is_tables_enabled = $this->transactionUtil->isModuleEnabled('tables');
        $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');

        $payment_types = $this->transactionUtil->payment_types($business_id);
        $with = [];
        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $sale_type = !empty(request()->input('sale_type')) ? request()->input('sale_type') : 'sell';

        $sells = $this->getListSells($business_id, $sale_type);

        //Add condition to filter by sale payment or invoice
        // Payments = sales where status == paid
        // Invoices = sales where status != paid
        if (request()->has('type')) {
            switch (request()->input('type')) {
                case 'payment':
                    $sells->where('transactions.payment_status', 'paid');
                    break;

                case 'invoice':
                    $sells->where('transactions.payment_status', '!=', 'paid');
                    break;

                default:
                    break;
            }
        }

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $sells->whereIn('transactions.location_id', $permitted_locations);
        }

        //Add condition for created_by,used in sales representative sales report
        if (request()->has('created_by')) {
            $created_by = request()->get('created_by');
            if (!empty($created_by)) {
                $sells->where('transactions.created_by', $created_by);
            }
        }

        $partial_permissions = ['view_own_sell_only', 'view_commission_agent_sell', 'access_own_shipping', 'access_commission_agent_shipping'];
        if (!auth()->user()->can('direct_sell.view')) {
            $sells->where(function ($q) {
                if (auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                    $q->where('transactions.created_by', request()->session()->get('user.id'));
                }

                //if user is commission agent display only assigned sells
                if (auth()->user()->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                    $q->orWhere('transactions.commission_agent', request()->session()->get('user.id'));
                }
            });
        }

        $only_shipments = request()->only_shipments == 'true' ? true : false;
        if ($only_shipments) {
            $sells->whereNotNull('transactions.shipping_status');

            if (auth()->user()->hasAnyPermission(['access_pending_shipments_only'])) {
                $sells->where('transactions.shipping_status', '!=', 'delivered');
            }
        }

        if (!$is_admin && !$only_shipments && $sale_type != 'sales_order') {
            $payment_status_arr = [];
            if (auth()->user()->can('view_paid_sells_only')) {
                $payment_status_arr[] = 'paid';
            }

            if (auth()->user()->can('view_due_sells_only')) {
                $payment_status_arr[] = 'due';
            }

            if (auth()->user()->can('view_partial_sells_only')) {
                $payment_status_arr[] = 'partial';
            }

            if (empty($payment_status_arr)) {
                if (auth()->user()->can('view_overdue_sells_only')) {
                    $sells->OverDue();
                }
            } else {
                if (auth()->user()->can('view_overdue_sells_only')) {
                    $sells->where(function ($q) use ($payment_status_arr) {
                        $q->whereIn('transactions.payment_status', $payment_status_arr)
                            ->orWhere(function ($qr) {
                                $qr->OverDue();
                            });
                    });
                } else {
                    $sells->whereIn('transactions.payment_status', $payment_status_arr);
                }
            }
        }

        if (!empty(request()->input('payment_status')) && request()->input('payment_status') != 'overdue') {
            $sells->where('transactions.payment_status', request()->input('payment_status'));
        } elseif (request()->input('payment_status') == 'overdue') {
            $sells->whereIn('transactions.payment_status', ['due', 'partial'])
                ->whereNotNull('transactions.pay_term_number')
                ->whereNotNull('transactions.pay_term_type')
                ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
        }


        //Add condition for location,used in sales representative expense report
        if (request()->has('location_id')) {
            $location_id = request()->get('location_id');
            if (!empty($location_id)) {
                $sells->where('transactions.location_id', $location_id);
            }
        }

        if (!empty(request()->input('rewards_only')) && request()->input('rewards_only') == true) {
            $sells->where(function ($q) {
                $q->whereNotNull('transactions.rp_earned')
                    ->orWhere('transactions.rp_redeemed', '>', 0);
            });
        }

        if (!empty(request()->customer_id)) {
            $customer_id = request()->customer_id;
            $sells->where('contacts.id', $customer_id);
        }
        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start = request()->start_date;
            $end =  request()->end_date;
            $sells->whereDate('transactions.transaction_date', '>=', $start)
                ->whereDate('transactions.transaction_date', '<=', $end);
        }

        //Check is_direct sell
        if (request()->has('is_direct_sale')) {
            $is_direct_sale = request()->is_direct_sale;
            if ($is_direct_sale == 0) {
                $sells->where('transactions.is_direct_sale', 0);
                $sells->whereNull('transactions.sub_type');
            }
        }

        //Add condition for commission_agent,used in sales representative sales with commission report
        if (request()->has('commission_agent')) {
            $commission_agent = request()->get('commission_agent');
            if (!empty($commission_agent)) {
                $sells->where('transactions.commission_agent', $commission_agent);
            }
        }

        if (!empty(request()->input('source'))) {
            //only exception for woocommerce
            if (request()->input('source') == 'woocommerce') {
                $sells->whereNotNull('transactions.woocommerce_order_id');
            } else {
                $sells->where('transactions.source', request()->input('source'));
            }
        }

        if ($is_crm) {
            $sells->addSelect('transactions.crm_is_order_request');

            if (request()->has('crm_is_order_request')) {
                $sells->where('transactions.crm_is_order_request', 1);
            }
        }

        if (request()->only_subscriptions) {
            $sells->where(function ($q) {
                $q->whereNotNull('transactions.recur_parent_id')
                    ->orWhere('transactions.is_recurring', 1);
            });
        }

        if (!empty(request()->list_for) && request()->list_for == 'service_staff_report') {
            $sells->whereNotNull('transactions.res_waiter_id');
        }

        if (!empty(request()->res_waiter_id)) {
            $sells->where('transactions.res_waiter_id', request()->res_waiter_id);
        }

        if (!empty(request()->input('sub_type'))) {
            $sells->where('transactions.sub_type', request()->input('sub_type'));
        }

        if (!empty(request()->input('created_by'))) {
            $sells->where('transactions.created_by', request()->input('created_by'));
        }

        if (!empty(request()->input('status'))) {
            $sells->where('transactions.status', request()->input('status'));
        }

        if (!empty(request()->input('sales_cmsn_agnt'))) {
            $sells->where('transactions.commission_agent', request()->input('sales_cmsn_agnt'));
        }

        if (!empty(request()->input('service_staffs'))) {
            $sells->where('transactions.res_waiter_id', request()->input('service_staffs'));
        }

        $only_pending_shipments = request()->only_pending_shipments == 'true' ? true : false;
        if ($only_pending_shipments) {
            $sells->where('transactions.shipping_status', '!=', 'delivered')
                ->whereNotNull('transactions.shipping_status');
            $only_shipments = true;
        }

        if (!empty(request()->input('shipping_status'))) {
            $sells->where('transactions.shipping_status', request()->input('shipping_status'));
        }

        if (!empty(request()->input('for_dashboard_sales_order'))) {
            $sells->whereIn('transactions.status', ['partial', 'ordered'])
                ->orHavingRaw('so_qty_remaining > 0');
        }

        if ($sale_type == 'sales_order') {
            if (!auth()->user()->can('so.view_all') && auth()->user()->can('so.view_own')) {
                $sells->where('transactions.created_by', request()->session()->get('user.id'));
            }
        }

        $sells->groupBy('transactions.id');

        if (!empty(request()->suspended)) {
            $transaction_sub_type = request()->get('transaction_sub_type');
            if (!empty($transaction_sub_type)) {
                $sells->where('transactions.sub_type', $transaction_sub_type);
            } else {
                $sells->where('transactions.sub_type', null);
            }

            $with = ['sell_lines'];

            if ($is_tables_enabled) {
                $with[] = 'table';
            }

            if ($is_service_staff_enabled) {
                $with[] = 'service_staff';
            }

            $sales = $sells->where('transactions.is_suspend', 1)
                ->with($with)
                ->addSelect('transactions.is_suspend', 'transactions.res_table_id', 'transactions.res_waiter_id', 'transactions.additional_notes')
                ->get();

            return view('sale_pos.partials.suspended_sales_modal')->with(compact('sales', 'is_tables_enabled', 'is_service_staff_enabled', 'transaction_sub_type'));
        }

        $with[] = 'payment_lines';
        if (!empty($with)) {
            $sells->with($with);
        }

        //$business_details = $this->businessUtil->getDetails($business_id);
        if ($this->businessUtil->isModuleEnabled('subscription')) {
            $sells->addSelect('transactions.is_recurring', 'transactions.recur_parent_id');
        }
        $sales_order_statuses = Transaction::sales_order_statuses();
        $datatable = DataTables::of($sells)
            ->addColumn(
                'action',
                function ($row) use ($only_shipments, $is_admin, $sale_type) {
                    $html = '<div class="btn-group">
                                    <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                        data-toggle="dropdown" aria-expanded="false">' .
                        __("messages.actions") .
                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                        </span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    if (auth()->user()->can("sell.view") || auth()->user()->can("direct_sell.view") || auth()->user()->can("view_own_sell_only")) {
                        $html .= '<li><a href="#" data-href="' . action("SellController@show", [$row->id]) . '" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> ' . __("messages.view") . '</a></li>';
                    }
                    if (!$only_shipments) {
                        if ($row->is_direct_sale == 0) {
                            if (auth()->user()->can("sell.update")) {
                                $html .= '<li><a target="_blank" href="' . action('SellPosController@edit', [$row->id]) . '"><i class="fas fa-edit"></i> ' . __("messages.edit") . '</a></li>';
                            }
                        } elseif ($row->type == 'sales_order') {
                            if (auth()->user()->can("so.update")) {
                                $html .= '<li><a target="_blank" href="' . action('SellController@edit', [$row->id]) . '"><i class="fas fa-edit"></i> ' . __("messages.edit") . '</a></li>';
                            }
                        } else {
                            if (auth()->user()->can("direct_sell.update")) {
                                $html .= '<li><a target="_blank" href="' . action('SellController@edit', [$row->id]) . '"><i class="fas fa-edit"></i> ' . __("messages.edit") . '</a></li>';
                            }
                        }

                        $delete_link = '<li><a href="' . action('SellPosController@destroy', [$row->id]) . '" class="delete-sale"><i class="fas fa-trash"></i> ' . __("messages.delete") . '</a></li>';
                        if ($row->is_direct_sale == 0) {
                            if (auth()->user()->can("sell.delete")) {
                                $html .= $delete_link;
                            }
                        } elseif ($row->type == 'sales_order') {
                            if (auth()->user()->can("so.delete")) {
                                $html .= $delete_link;
                            }
                        } else {
                            if (auth()->user()->can("direct_sell.delete")) {
                                $html .= $delete_link;
                            }
                        }
                    }

                    if (config('constants.enable_download_pdf') && auth()->user()->can("print_invoice") && $sale_type != 'sales_order') {
                        $html .= '<li><a href="' . route('sell.downloadPdf', [$row->id]) . '" target="_blank"><i class="fas fa-print" aria-hidden="true"></i> ' . __("lang_v1.download_pdf") . '</a></li>';

                        if (!empty($row->shipping_status)) {
                            $html .= '<li><a href="' . route('packing.downloadPdf', [$row->id]) . '" target="_blank"><i class="fas fa-print" aria-hidden="true"></i> ' . __("lang_v1.download_paking_pdf") . '</a></li>';
                        }
                    }

                    if (auth()->user()->can("sell.view") || auth()->user()->can("direct_sell.access")) {
                        if (!empty($row->document)) {
                            $document_name = !empty(explode("_", $row->document, 2)[1]) ? explode("_", $row->document, 2)[1] : $row->document;
                            $html .= '<li><a href="' . url('uploads/documents/' . $row->document) . '" download="' . $document_name . '"><i class="fas fa-download" aria-hidden="true"></i>' . __("purchase.download_document") . '</a></li>';
                            if (isFileImage($document_name)) {
                                $html .= '<li><a href="#" data-href="' . url('uploads/documents/' . $row->document) . '" class="view_uploaded_document"><i class="fas fa-image" aria-hidden="true"></i>' . __("lang_v1.view_document") . '</a></li>';
                            }
                        }
                    }

                    if ($is_admin || auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
                        $html .= '<li><a href="#" data-href="' . action('SellController@editShipping', [$row->id]) . '" class="btn-modal" data-container=".view_modal"><i class="fas fa-truck" aria-hidden="true"></i>' . __("lang_v1.edit_shipping") . '</a></li>';
                    }

                    if ($row->type == 'sell') {
                        if (auth()->user()->can("print_invoice")) {
                            $html .= '<li><a href="#" class="print-invoice" data-href="' . route('sell.printInvoice', [$row->id]) . '"><i class="fas fa-print" aria-hidden="true"></i> ' . __("lang_v1.print_invoice") . '</a></li>
                                    <li><a href="#" class="print-invoice" data-href="' . route('sell.printInvoice', [$row->id]) . '?package_slip=true"><i class="fas fa-file-alt" aria-hidden="true"></i> ' . __("lang_v1.packing_slip") . '</a></li>';
                        }
                        $html .= '<li class="divider"></li>';
                        if (!$only_shipments) {
                            if ($row->payment_status != "paid" && auth()->user()->can("sell.payments")) {
                                $html .= '<li><a href="' . action('TransactionPaymentController@addPayment', [$row->id]) . '" class="add_payment_modal"><i class="fas fa-money-bill-alt"></i> ' . __("purchase.add_payment") . '</a></li>';
                            }

                            $html .= '<li><a href="' . action('TransactionPaymentController@show', [$row->id]) . '" class="view_payment_modal"><i class="fas fa-money-bill-alt"></i> ' . __("purchase.view_payments") . '</a></li>';

                            if (auth()->user()->can("sell.create")) {
                                $html .= '<li><a href="' . action('SellController@duplicateSell', [$row->id]) . '"><i class="fas fa-copy"></i> ' . __("lang_v1.duplicate_sell") . '</a></li>

                                    <li><a href="' . action('SellReturnController@add', [$row->id]) . '"><i class="fas fa-undo"></i> ' . __("lang_v1.sell_return") . '</a></li>

                                    <li><a href="' . action('SellPosController@showInvoiceUrl', [$row->id]) . '" class="view_invoice_url"><i class="fas fa-eye"></i> ' . __("lang_v1.view_invoice_url") . '</a></li>';
                            }
                        }

                        $html .= '<li><a href="#" data-href="' . action('NotificationController@getTemplate', ["transaction_id" => $row->id, "template_for" => "new_sale"]) . '" class="btn-modal" data-container=".view_modal"><i class="fa fa-envelope" aria-hidden="true"></i>' . __("lang_v1.new_sale_notification") . '</a></li>';
                    } else {
                        $html .= '<li><a href="#" data-href="' . action('SellController@viewMedia', ["model_id" => $row->id, "model_type" => "Modules\Accounting\Entities\Transaction", 'model_media_type' => 'shipping_document']) . '" class="btn-modal" data-container=".view_modal"><i class="fas fa-paperclip" aria-hidden="true"></i>' . __("lang_v1.shipping_documents") . '</a></li>';
                    }

                    $html .= '</ul></div>';

                    return $html;
                }
            )
            ->removeColumn('id')
            ->editColumn(
                'final_total',
                '<span class="final-total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
            )
            ->editColumn(
                'tax_amount',
                '<span class="total-tax" data-orig-value="{{$tax_amount}}">@format_currency($tax_amount)</span>'
            )
            ->editColumn(
                'total_paid',
                '<span class="total-paid" data-orig-value="{{$total_paid}}">@format_currency($total_paid)</span>'
            )
            ->editColumn(
                'total_before_tax',
                '<span class="total_before_tax" data-orig-value="{{$total_before_tax}}">@format_currency($total_before_tax)</span>'
            )
            ->editColumn(
                'discount_amount',
                function ($row) {
                    $discount = !empty($row->discount_amount) ? $row->discount_amount : 0;

                    if (!empty($discount) && $row->discount_type == 'percentage') {
                        $discount = $row->total_before_tax * ($discount / 100);
                    }

                    return '<span class="total-discount" data-orig-value="' . $discount . '">' . $this->transactionUtil->num_f($discount, true) . '</span>';
                }
            )
            ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
            ->editColumn(
                'payment_status',
                function ($row) {
                    $payment_status = Transaction::getPaymentStatus($row);
                    return (string) view('sell.partials.payment_status', ['payment_status' => $payment_status, 'id' => $row->id]);
                }
            )
            ->editColumn(
                'types_of_service_name',
                '<span class="service-type-label" data-orig-value="{{$types_of_service_name}}" data-status-name="{{$types_of_service_name}}">{{$types_of_service_name}}</span>'
            )
            ->addColumn('total_remaining', function ($row) {
                $total_remaining =  $row->final_total - $row->total_paid;
                $total_remaining_html = '<span class="payment_due" data-orig-value="' . $total_remaining . '">' . $this->transactionUtil->num_f($total_remaining, true) . '</span>';


                return $total_remaining_html;
            })
            ->addColumn('return_due', function ($row) {
                $return_due_html = '';
                if (!empty($row->return_exists)) {
                    $return_due = $row->amount_return - $row->return_paid;
                    $return_due_html .= '<a href="' . action("TransactionPaymentController@show", [$row->return_transaction_id]) . '" class="view_purchase_return_payment_modal"><span class="sell_return_due" data-orig-value="' . $return_due . '">' . $this->transactionUtil->num_f($return_due, true) . '</span></a>';
                }

                return $return_due_html;
            })
            ->editColumn('invoice_no', function ($row) use ($is_crm) {
                $invoice_no = $row->invoice_no;
                if (!empty($row->woocommerce_order_id)) {
                    $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="' . __('lang_v1.synced_from_woocommerce') . '"></i>';
                }
                if (!empty($row->return_exists)) {
                    $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="' . __('lang_v1.some_qty_returned_from_sell') . '"><i class="fas fa-undo"></i></small>';
                }
                if (!empty($row->is_recurring)) {
                    $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="' . __('lang_v1.subscribed_invoice') . '"><i class="fas fa-recycle"></i></small>';
                }

                if (!empty($row->recur_parent_id)) {
                    $invoice_no .= ' &nbsp;<small class="label bg-info label-round no-print" title="' . __('lang_v1.subscription_invoice') . '"><i class="fas fa-recycle"></i></small>';
                }

                if (!empty($row->is_export)) {
                    $invoice_no .= '</br><small class="label label-default no-print" title="' . __('lang_v1.export') . '">' . __('lang_v1.export') . '</small>';
                }

                if ($is_crm && !empty($row->crm_is_order_request)) {
                    $invoice_no .= ' &nbsp;<small class="label bg-yellow label-round no-print" title="' . __('crm::lang.order_request') . '"><i class="fas fa-tasks"></i></small>';
                }

                return $invoice_no;
            })
            ->editColumn('shipping_status', function ($row) use ($shipping_statuses) {
                $status_color = !empty($this->shipping_status_colors[$row->shipping_status]) ? $this->shipping_status_colors[$row->shipping_status] : 'bg-gray';
                $status = !empty($row->shipping_status) ? '<a href="#" class="btn-modal" data-href="' . action('SellController@editShipping', [$row->id]) . '" data-container=".view_modal"><span class="label ' . $status_color . '">' . $shipping_statuses[$row->shipping_status] . '</span></a>' : '';

                return $status;
            })
            ->addColumn('contact_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$name}}')
            ->editColumn('total_items', '{{@format_quantity($total_items)}}')
            ->filterColumn('contact_name', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('contacts.name', 'like', "%{$keyword}%")
                        ->orWhere('contacts.supplier_business_name', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('payment_methods', function ($row) use ($payment_types) {
                $methods = array_unique($row->payment_lines->pluck('method')->toArray());
                $count = count($methods);
                $payment_method = '';
                if ($count == 1) {
                    $payment_method = $payment_types[$methods[0]];
                } elseif ($count > 1) {
                    $payment_method = __('lang_v1.checkout_multi_pay');
                }

                $html = !empty($payment_method) ? '<span class="payment-method" data-orig-value="' . $payment_method . '" data-status-name="' . $payment_method . '">' . $payment_method . '</span>' : '';

                return $html;
            })
            ->editColumn('status', function ($row) use ($sales_order_statuses, $is_admin) {
                $status = '';

                if ($row->type == 'sales_order') {
                    if ($is_admin && $row->status != 'completed') {
                        $status = '<span class="edit-so-status label ' . $sales_order_statuses[$row->status]['class'] . '" data-href="' . action("SalesOrderController@getEditSalesOrderStatus", ['id' => $row->id]) . '">' . $sales_order_statuses[$row->status]['label'] . '</span>';
                    } else {
                        $status = '<span class="label ' . $sales_order_statuses[$row->status]['class'] . '" >' . $sales_order_statuses[$row->status]['label'] . '</span>';
                    }
                }

                return $status;
            })
            ->editColumn('so_qty_remaining', '{{@format_quantity($so_qty_remaining)}}')
            ->setRowAttr([
                'data-href' => function ($row) {
                    if (auth()->user()->can("sell.view") || auth()->user()->can("view_own_sell_only")) {
                        return  action('SellController@show', [$row->id]);
                    } else {
                        return '';
                    }
                }
            ]);

        $rawColumns = ['final_total', 'action', 'total_paid', 'total_remaining', 'payment_status', 'invoice_no', 'discount_amount', 'tax_amount', 'total_before_tax', 'shipping_status', 'types_of_service_name', 'payment_methods', 'return_due', 'contact_name', 'status'];

        return $datatable->rawColumns($rawColumns)->make(true);
    }

    public function getTransactionsDataTable()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        $business_id = request()->session()->get('user.business_id');
        $is_tables_enabled = $this->transactionUtil->isModuleEnabled('tables');
        $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');

        $payment_types = $this->transactionUtil->payment_types($business_id);
        $with = [];
        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $sale_type = !empty(request()->input('sale_type')) ? request()->input('sale_type') : 'sell';

        $sells = $this->getListSells($business_id, $sale_type);

        //Add condition to filter by sale payment or invoice
        // Payments = sales where status == paid
        // Invoices = sales where status != paid
        if (request()->has('type')) {
            switch (request()->input('type')) {
                case 'payment':
                    $sells->where('transactions.payment_status', 'paid');
                    break;

                case 'invoice':
                    $sells->where('transactions.payment_status', '!=', 'paid');
                    break;

                default:
                    break;
            }
        }

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $sells->whereIn('transactions.location_id', $permitted_locations);
        }

        //Add condition for created_by,used in sales representative sales report
        if (request()->has('created_by')) {
            $created_by = request()->get('created_by');
            if (!empty($created_by)) {
                $sells->where('transactions.created_by', $created_by);
            }
        }

        $partial_permissions = ['view_own_sell_only', 'view_commission_agent_sell', 'access_own_shipping', 'access_commission_agent_shipping'];
        if (!auth()->user()->can('direct_sell.view')) {
            $sells->where(function ($q) {
                if (auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                    $q->where('transactions.created_by', request()->session()->get('user.id'));
                }

                //if user is commission agent display only assigned sells
                if (auth()->user()->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                    $q->orWhere('transactions.commission_agent', request()->session()->get('user.id'));
                }
            });
        }

        $only_shipments = request()->only_shipments == 'true' ? true : false;
        if ($only_shipments) {
            $sells->whereNotNull('transactions.shipping_status');

            if (auth()->user()->hasAnyPermission(['access_pending_shipments_only'])) {
                $sells->where('transactions.shipping_status', '!=', 'delivered');
            }
        }

        if (!$is_admin && !$only_shipments && $sale_type != 'sales_order') {
            $payment_status_arr = [];
            if (auth()->user()->can('view_paid_sells_only')) {
                $payment_status_arr[] = 'paid';
            }

            if (auth()->user()->can('view_due_sells_only')) {
                $payment_status_arr[] = 'due';
            }

            if (auth()->user()->can('view_partial_sells_only')) {
                $payment_status_arr[] = 'partial';
            }

            if (empty($payment_status_arr)) {
                if (auth()->user()->can('view_overdue_sells_only')) {
                    $sells->OverDue();
                }
            } else {
                if (auth()->user()->can('view_overdue_sells_only')) {
                    $sells->where(function ($q) use ($payment_status_arr) {
                        $q->whereIn('transactions.payment_status', $payment_status_arr)
                            ->orWhere(function ($qr) {
                                $qr->OverDue();
                            });
                    });
                } else {
                    $sells->whereIn('transactions.payment_status', $payment_status_arr);
                }
            }
        }

        if (!empty(request()->input('payment_status')) && request()->input('payment_status') != 'overdue') {
            $sells->where('transactions.payment_status', request()->input('payment_status'));
        } elseif (request()->input('payment_status') == 'overdue') {
            $sells->whereIn('transactions.payment_status', ['due', 'partial'])
                ->whereNotNull('transactions.pay_term_number')
                ->whereNotNull('transactions.pay_term_type')
                ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
        }


        //Add condition for location,used in sales representative expense report
        if (request()->has('location_id')) {
            $location_id = request()->get('location_id');
            if (!empty($location_id)) {
                $sells->where('transactions.location_id', $location_id);
            }
        }

        if (!empty(request()->input('rewards_only')) && request()->input('rewards_only') == true) {
            $sells->where(function ($q) {
                $q->whereNotNull('transactions.rp_earned')
                    ->orWhere('transactions.rp_redeemed', '>', 0);
            });
        }

        if (!empty(request()->customer_id)) {
            $customer_id = request()->customer_id;
            $sells->where('contacts.id', $customer_id);
        }
        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start = request()->start_date;
            $end =  request()->end_date;
            $sells->whereDate('transactions.transaction_date', '>=', $start)
                ->whereDate('transactions.transaction_date', '<=', $end);
        }

        //Check is_direct sell
        if (request()->has('is_direct_sale')) {
            $is_direct_sale = request()->is_direct_sale;
            if ($is_direct_sale == 0) {
                $sells->where('transactions.is_direct_sale', 0);
                $sells->whereNull('transactions.sub_type');
            }
        }

        //Add condition for commission_agent,used in sales representative sales with commission report
        if (request()->has('commission_agent')) {
            $commission_agent = request()->get('commission_agent');
            if (!empty($commission_agent)) {
                $sells->where('transactions.commission_agent', $commission_agent);
            }
        }

        if (!empty(request()->input('source'))) {
            //only exception for woocommerce
            if (request()->input('source') == 'woocommerce') {
                $sells->whereNotNull('transactions.woocommerce_order_id');
            } else {
                $sells->where('transactions.source', request()->input('source'));
            }
        }

        if ($is_crm) {
            $sells->addSelect('transactions.crm_is_order_request');

            if (request()->has('crm_is_order_request')) {
                $sells->where('transactions.crm_is_order_request', 1);
            }
        }

        if (request()->only_subscriptions) {
            $sells->where(function ($q) {
                $q->whereNotNull('transactions.recur_parent_id')
                    ->orWhere('transactions.is_recurring', 1);
            });
        }

        if (!empty(request()->list_for) && request()->list_for == 'service_staff_report') {
            $sells->whereNotNull('transactions.res_waiter_id');
        }

        if (!empty(request()->res_waiter_id)) {
            $sells->where('transactions.res_waiter_id', request()->res_waiter_id);
        }

        if (!empty(request()->input('sub_type'))) {
            $sells->where('transactions.sub_type', request()->input('sub_type'));
        }

        if (!empty(request()->input('created_by'))) {
            $sells->where('transactions.created_by', request()->input('created_by'));
        }

        if (!empty(request()->input('status'))) {
            $sells->where('transactions.status', request()->input('status'));
        }

        if (!empty(request()->input('sales_cmsn_agnt'))) {
            $sells->where('transactions.commission_agent', request()->input('sales_cmsn_agnt'));
        }

        if (!empty(request()->input('service_staffs'))) {
            $sells->where('transactions.res_waiter_id', request()->input('service_staffs'));
        }

        $only_pending_shipments = request()->only_pending_shipments == 'true' ? true : false;
        if ($only_pending_shipments) {
            $sells->where('transactions.shipping_status', '!=', 'delivered')
                ->whereNotNull('transactions.shipping_status');
            $only_shipments = true;
        }

        if (!empty(request()->input('shipping_status'))) {
            $sells->where('transactions.shipping_status', request()->input('shipping_status'));
        }

        if (!empty(request()->input('for_dashboard_sales_order'))) {
            $sells->whereIn('transactions.status', ['partial', 'ordered'])
                ->orHavingRaw('so_qty_remaining > 0');
        }

        if ($sale_type == 'sales_order') {
            if (!auth()->user()->can('so.view_all') && auth()->user()->can('so.view_own')) {
                $sells->where('transactions.created_by', request()->session()->get('user.id'));
            }
        }

        $sells->groupBy('transactions.id');

        if (!empty(request()->suspended)) {
            $transaction_sub_type = request()->get('transaction_sub_type');
            if (!empty($transaction_sub_type)) {
                $sells->where('transactions.sub_type', $transaction_sub_type);
            } else {
                $sells->where('transactions.sub_type', null);
            }

            $with = ['sell_lines'];

            if ($is_tables_enabled) {
                $with[] = 'table';
            }

            if ($is_service_staff_enabled) {
                $with[] = 'service_staff';
            }

            $sales = $sells->where('transactions.is_suspend', 1)
                ->with($with)
                ->addSelect('transactions.is_suspend', 'transactions.res_table_id', 'transactions.res_waiter_id', 'transactions.additional_notes')
                ->get();

            return view('sale_pos.partials.suspended_sales_modal')->with(compact('sales', 'is_tables_enabled', 'is_service_staff_enabled', 'transaction_sub_type'));
        }

        $with[] = 'payment_lines';
        if (!empty($with)) {
            $sells->with($with);
        }

        //$business_details = $this->businessUtil->getDetails($business_id);
        if ($this->businessUtil->isModuleEnabled('subscription')) {
            $sells->addSelect('transactions.is_recurring', 'transactions.recur_parent_id');
        }
        $sales_order_statuses = Transaction::sales_order_statuses();
        $datatable = DataTables::of($sells)
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
                '<span class="final-total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
            )
            ->editColumn(
                'tax_amount',
                '<span class="total-tax" data-orig-value="{{$tax_amount}}">@format_currency($tax_amount)</span>'
            )
            ->editColumn(
                'total_paid',
                '<span class="total-paid" data-orig-value="{{$total_paid}}">@format_currency($total_paid)</span>'
            )
            ->editColumn(
                'total_before_tax',
                '<span class="total_before_tax" data-orig-value="{{$total_before_tax}}">@format_currency($total_before_tax)</span>'
            )
            ->editColumn(
                'discount_amount',
                function ($row) {
                    $discount = !empty($row->discount_amount) ? $row->discount_amount : 0;

                    if (!empty($discount) && $row->discount_type == 'percentage') {
                        $discount = $row->total_before_tax * ($discount / 100);
                    }

                    return '<span class="total-discount" data-orig-value="' . $discount . '">' . $this->transactionUtil->num_f($discount, true) . '</span>';
                }
            )
            ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
            ->editColumn(
                'payment_status',
                function ($row) {
                    $payment_status = Transaction::getPaymentStatus($row);
                    return (string) view('sell.partials.payment_status', ['payment_status' => $payment_status, 'id' => $row->id]);
                }
            )
            ->editColumn(
                'types_of_service_name',
                '<span class="service-type-label" data-orig-value="{{$types_of_service_name}}" data-status-name="{{$types_of_service_name}}">{{$types_of_service_name}}</span>'
            )
            ->addColumn('total_remaining', function ($row) {
                $total_remaining =  $row->final_total - $row->total_paid;
                $total_remaining_html = '<span class="payment_due" data-orig-value="' . $total_remaining . '">' . $this->transactionUtil->num_f($total_remaining, true) . '</span>';

                return $total_remaining_html;
            })
            ->addColumn('return_due', function ($row) {
                $return_due_html = '';
                if (!empty($row->return_exists)) {
                    $return_due = $row->amount_return - $row->return_paid;
                    $return_due_html .= '<a href="' . action("TransactionPaymentController@show", [$row->return_transaction_id]) . '" class="view_purchase_return_payment_modal"><span class="sell_return_due" data-orig-value="' . $return_due . '">' . $this->transactionUtil->num_f($return_due, true) . '</span></a>';
                }

                return $return_due_html;
            })
            ->addColumn('checkbox', function () {
                return '<input type="checkbox" class="select-row"/>';
            })
            ->editColumn('invoice_no', function ($row) use ($is_crm) {
                $invoice_no = $row->invoice_no;
                if (!empty($row->woocommerce_order_id)) {
                    $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="' . __('lang_v1.synced_from_woocommerce') . '"></i>';
                }
                if (!empty($row->return_exists)) {
                    $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="' . __('lang_v1.some_qty_returned_from_sell') . '"><i class="fas fa-undo"></i></small>';
                }
                if (!empty($row->is_recurring)) {
                    $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="' . __('lang_v1.subscribed_invoice') . '"><i class="fas fa-recycle"></i></small>';
                }

                if (!empty($row->recur_parent_id)) {
                    $invoice_no .= ' &nbsp;<small class="label bg-info label-round no-print" title="' . __('lang_v1.subscription_invoice') . '"><i class="fas fa-recycle"></i></small>';
                }

                if (!empty($row->is_export)) {
                    $invoice_no .= '</br><small class="label label-default no-print" title="' . __('lang_v1.export') . '">' . __('lang_v1.export') . '</small>';
                }

                if ($is_crm && !empty($row->crm_is_order_request)) {
                    $invoice_no .= ' &nbsp;<small class="label bg-yellow label-round no-print" title="' . __('crm::lang.order_request') . '"><i class="fas fa-tasks"></i></small>';
                }

                return $invoice_no;
            })
            ->addColumn('contact_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$name}}')
            ->editColumn('total_items', '{{@format_quantity($total_items)}}')
            ->filterColumn('contact_name', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('contacts.name', 'like', "%{$keyword}%")
                        ->orWhere('contacts.supplier_business_name', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('payment_methods', function ($row) use ($payment_types) {
                $methods = array_unique($row->payment_lines->pluck('method')->toArray());
                $count = count($methods);
                $payment_method = '';
                if ($count == 1) {
                    $payment_method = $payment_types[$methods[0]];
                } elseif ($count > 1) {
                    $payment_method = __('lang_v1.checkout_multi_pay');
                }

                $html = !empty($payment_method) ? '<span class="payment-method" data-orig-value="' . $payment_method . '" data-status-name="' . $payment_method . '">' . $payment_method . '</span>' : '';

                return $html;
            })
            ->editColumn('status', function ($row) use ($sales_order_statuses, $is_admin) {
                $status = '';

                if ($row->type == 'sales_order') {
                    if ($is_admin && $row->status != 'completed') {
                        $status = '<span class="edit-so-status label ' . $sales_order_statuses[$row->status]['class'] . '" data-href="' . action("SalesOrderController@getEditSalesOrderStatus", ['id' => $row->id]) . '">' . $sales_order_statuses[$row->status]['label'] . '</span>';
                    } else {
                        $status = '<span class="label ' . $sales_order_statuses[$row->status]['class'] . '" >' . $sales_order_statuses[$row->status]['label'] . '</span>';
                    }
                }

                return $status;
            })
            ->editColumn('so_qty_remaining', '{{@format_quantity($so_qty_remaining)}}')
            ->setRowAttr([
                'data-href' => function ($row) {
                    if (auth()->user()->can("sell.view") || auth()->user()->can("view_own_sell_only")) {
                        return  action('SellController@show', [$row->id]);
                    } else {
                        return '';
                    }
                }
            ]);

        $rawColumns = ['final_total', 'mapping', 'total_paid', 'total_remaining', 'payment_status', 'invoice_no', 'discount_amount', 'tax_amount', 'total_before_tax', 'types_of_service_name', 'payment_methods', 'return_due', 'contact_name', 'status', 'chart_of_account', 'checkbox'];

        return $datatable->rawColumns($rawColumns)->make(true);
    }

    /**
     * common function to get
     * list sell
     * @param int $business_id
     *
     * @return object
     */
    public function getListSells($business_id, $sale_type = 'sell')
    {
        $sells = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
            // ->leftJoin('transaction_payments as tp', 'transactions.id', '=', 'tp.transaction_id')
            ->leftJoin('transaction_sell_lines as tsl', function ($join) {
                $join->on('transactions.id', '=', 'tsl.transaction_id')
                    ->whereNull('tsl.parent_sell_line_id');
            })
            ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
            ->leftJoin('users as ss', 'transactions.res_waiter_id', '=', 'ss.id')
            ->leftJoin('res_tables as tables', 'transactions.res_table_id', '=', 'tables.id')
            ->join(
                'business_locations AS bl',
                'transactions.location_id',
                '=',
                'bl.id'
            )
            ->leftJoin(
                'transactions AS SR',
                'transactions.id',
                '=',
                'SR.return_parent_id'
            )
            ->leftJoin(
                'types_of_services AS tos',
                'transactions.types_of_service_id',
                '=',
                'tos.id'
            )
            ->where('transactions.business_id', $business_id)
            ->where('transactions.type', $sale_type)
            ->select(
                'transactions.id',
                'transactions.transaction_date',
                'transactions.type',
                'transactions.is_direct_sale',
                'transactions.invoice_no',
                'transactions.invoice_no as invoice_no_text',
                'contacts.name',
                'contacts.mobile',
                'contacts.contact_id',
                'contacts.supplier_business_name',
                'transactions.status',
                'transactions.payment_status',
                'transactions.final_total',
                'transactions.tax_amount',
                'transactions.discount_amount',
                'transactions.discount_type',
                'transactions.total_before_tax',
                'transactions.rp_redeemed',
                'transactions.rp_redeemed_amount',
                'transactions.rp_earned',
                'transactions.types_of_service_id',
                'transactions.shipping_status',
                'transactions.pay_term_number',
                'transactions.pay_term_type',
                'transactions.additional_notes',
                'transactions.staff_note',
                'transactions.shipping_details',
                'transactions.document',
                'transactions.shipping_custom_field_1',
                'transactions.shipping_custom_field_2',
                'transactions.shipping_custom_field_3',
                'transactions.shipping_custom_field_4',
                'transactions.shipping_custom_field_5',
                'transactions.custom_field_1',
                'transactions.custom_field_2',
                'transactions.custom_field_3',
                'transactions.custom_field_4',
                'transactions.journal_entry_id',
                DB::raw('DATE_FORMAT(transactions.transaction_date, "%Y/%m/%d") as sale_date'),
                DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by"),
                DB::raw('(SELECT SUM(IF(TP.is_return = 1,-1*TP.amount,TP.amount)) FROM transaction_payments AS TP WHERE
                        TP.transaction_id=transactions.id) as total_paid'),
                'bl.name as business_location',
                DB::raw('COUNT(SR.id) as return_exists'),
                DB::raw('(SELECT SUM(TP2.amount) FROM transaction_payments AS TP2 WHERE
                        TP2.transaction_id=SR.id ) as return_paid'),
                DB::raw('COALESCE(SR.final_total, 0) as amount_return'),
                'SR.id as return_transaction_id',
                'tos.name as types_of_service_name',
                'transactions.service_custom_field_1',
                DB::raw('COUNT( DISTINCT tsl.id) as total_items'),
                DB::raw("CONCAT(COALESCE(ss.surname, ''),' ',COALESCE(ss.first_name, ''),' ',COALESCE(ss.last_name,'')) as waiter"),
                'tables.name as table_name',
                DB::raw('SUM(tsl.quantity - tsl.so_quantity_invoiced) as so_qty_remaining'),
                'transactions.is_export'
            );

        if ($sale_type == 'sell') {
            $sells->where('transactions.status', 'final');
        }

        return $sells;
    }

    public function getInvoiceDataTable()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        $business_id = request()->session()->get('user.business_id');
        $is_tables_enabled = $this->transactionUtil->isModuleEnabled('tables');
        $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');

        $payment_types = $this->transactionUtil->payment_types($business_id);
        $with = [];
        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $sale_type = !empty(request()->input('sale_type')) ? request()->input('sale_type') : 'sell';

        $sells = $this->getListSells($business_id, $sale_type);

        // Add condition to filter by sale payment or invoice
        // Payments = sales where status == paid
        // Invoices = sales where status != paid
        if (request()->has('type')) {
            switch (request()->input('type')) {
                case 'payment':
                    $sells->where('transactions.payment_status', 'paid');
                    break;

                case 'invoice':
                    $sells->where('transactions.payment_status', '!=', 'paid');
                    break;

                default:
                    break;
            }
        }

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $sells->whereIn('transactions.location_id', $permitted_locations);
        }

        //Add condition for created_by,used in sales representative sales report
        if (request()->has('created_by')) {
            $created_by = request()->get('created_by');
            if (!empty($created_by)) {
                $sells->where('transactions.created_by', $created_by);
            }
        }

        $partial_permissions = ['view_own_sell_only', 'view_commission_agent_sell', 'access_own_shipping', 'access_commission_agent_shipping'];
        if (!auth()->user()->can('direct_sell.view')) {
            $sells->where(function ($q) {
                if (auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                    $q->where('transactions.created_by', request()->session()->get('user.id'));
                }

                //if user is commission agent display only assigned sells
                if (auth()->user()->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                    $q->orWhere('transactions.commission_agent', request()->session()->get('user.id'));
                }
            });
        }

        $only_shipments = request()->only_shipments == 'true' ? true : false;
        if ($only_shipments) {
            $sells->whereNotNull('transactions.shipping_status');

            if (auth()->user()->hasAnyPermission(['access_pending_shipments_only'])) {
                $sells->where('transactions.shipping_status', '!=', 'delivered');
            }
        }

        if (!$is_admin && !$only_shipments && $sale_type != 'sales_order') {
            $payment_status_arr = [];
            if (auth()->user()->can('view_paid_sells_only')) {
                $payment_status_arr[] = 'paid';
            }

            if (auth()->user()->can('view_due_sells_only')) {
                $payment_status_arr[] = 'due';
            }

            if (auth()->user()->can('view_partial_sells_only')) {
                $payment_status_arr[] = 'partial';
            }

            if (empty($payment_status_arr)) {
                if (auth()->user()->can('view_overdue_sells_only')) {
                    $sells->OverDue();
                }
            } else {
                if (auth()->user()->can('view_overdue_sells_only')) {
                    $sells->where(function ($q) use ($payment_status_arr) {
                        $q->whereIn('transactions.payment_status', $payment_status_arr)
                            ->orWhere(function ($qr) {
                                $qr->OverDue();
                            });
                    });
                } else {
                    $sells->whereIn('transactions.payment_status', $payment_status_arr);
                }
            }
        }

        if (!empty(request()->input('payment_status')) && request()->input('payment_status') != 'overdue') {
            $sells->where('transactions.payment_status', request()->input('payment_status'));
        } elseif (request()->input('payment_status') == 'overdue') {
            $sells->whereIn('transactions.payment_status', ['due', 'partial'])
                ->whereNotNull('transactions.pay_term_number')
                ->whereNotNull('transactions.pay_term_type')
                ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
        }


        //Add condition for location,used in sales representative expense report
        if (request()->has('location_id')) {
            $location_id = request()->get('location_id');
            if (!empty($location_id)) {
                $sells->where('transactions.location_id', $location_id);
            }
        }

        if (!empty(request()->input('rewards_only')) && request()->input('rewards_only') == true) {
            $sells->where(function ($q) {
                $q->whereNotNull('transactions.rp_earned')
                    ->orWhere('transactions.rp_redeemed', '>', 0);
            });
        }

        if (!empty(request()->customer_id)) {
            $customer_id = request()->customer_id;
            $sells->where('contacts.id', $customer_id);
        }
        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start = request()->start_date;
            $end =  request()->end_date;
            $sells->whereDate('transactions.transaction_date', '>=', $start)
                ->whereDate('transactions.transaction_date', '<=', $end);
        }

        //Check is_direct sell
        if (request()->has('is_direct_sale')) {
            $is_direct_sale = request()->is_direct_sale;
            if ($is_direct_sale == 0) {
                $sells->where('transactions.is_direct_sale', 0);
                $sells->whereNull('transactions.sub_type');
            }
        }

        //Add condition for commission_agent,used in sales representative sales with commission report
        if (request()->has('commission_agent')) {
            $commission_agent = request()->get('commission_agent');
            if (!empty($commission_agent)) {
                $sells->where('transactions.commission_agent', $commission_agent);
            }
        }

        if (!empty(request()->input('source'))) {
            //only exception for woocommerce
            if (request()->input('source') == 'woocommerce') {
                $sells->whereNotNull('transactions.woocommerce_order_id');
            } else {
                $sells->where('transactions.source', request()->input('source'));
            }
        }

        if ($is_crm) {
            $sells->addSelect('transactions.crm_is_order_request');

            if (request()->has('crm_is_order_request')) {
                $sells->where('transactions.crm_is_order_request', 1);
            }
        }

        if (request()->only_subscriptions) {
            $sells->where(function ($q) {
                $q->whereNotNull('transactions.recur_parent_id')
                    ->orWhere('transactions.is_recurring', 1);
            });
        }

        if (!empty(request()->list_for) && request()->list_for == 'service_staff_report') {
            $sells->whereNotNull('transactions.res_waiter_id');
        }

        if (!empty(request()->res_waiter_id)) {
            $sells->where('transactions.res_waiter_id', request()->res_waiter_id);
        }

        if (!empty(request()->input('sub_type'))) {
            $sells->where('transactions.sub_type', request()->input('sub_type'));
        }

        if (!empty(request()->input('created_by'))) {
            $sells->where('transactions.created_by', request()->input('created_by'));
        }

        if (!empty(request()->input('status'))) {
            $sells->where('transactions.status', request()->input('status'));
        }

        if (!empty(request()->input('sales_cmsn_agnt'))) {
            $sells->where('transactions.commission_agent', request()->input('sales_cmsn_agnt'));
        }

        if (!empty(request()->input('service_staffs'))) {
            $sells->where('transactions.res_waiter_id', request()->input('service_staffs'));
        }

        $only_pending_shipments = request()->only_pending_shipments == 'true' ? true : false;
        if ($only_pending_shipments) {
            $sells->where('transactions.shipping_status', '!=', 'delivered')
                ->whereNotNull('transactions.shipping_status');
            $only_shipments = true;
        }

        if (!empty(request()->input('shipping_status'))) {
            $sells->where('transactions.shipping_status', request()->input('shipping_status'));
        }

        if (!empty(request()->input('for_dashboard_sales_order'))) {
            $sells->whereIn('transactions.status', ['partial', 'ordered'])
                ->orHavingRaw('so_qty_remaining > 0');
        }

        if ($sale_type == 'sales_order') {
            if (!auth()->user()->can('so.view_all') && auth()->user()->can('so.view_own')) {
                $sells->where('transactions.created_by', request()->session()->get('user.id'));
            }
        }

        $sells->groupBy('transactions.id');

        if (!empty(request()->suspended)) {
            $transaction_sub_type = request()->get('transaction_sub_type');
            if (!empty($transaction_sub_type)) {
                $sells->where('transactions.sub_type', $transaction_sub_type);
            } else {
                $sells->where('transactions.sub_type', null);
            }

            $with = ['sell_lines'];

            if ($is_tables_enabled) {
                $with[] = 'table';
            }

            if ($is_service_staff_enabled) {
                $with[] = 'service_staff';
            }

            $sales = $sells->where('transactions.is_suspend', 1)
                ->with($with)
                ->addSelect('transactions.is_suspend', 'transactions.res_table_id', 'transactions.res_waiter_id', 'transactions.additional_notes')
                ->get();

            return view('sale_pos.partials.suspended_sales_modal')->with(compact('sales', 'is_tables_enabled', 'is_service_staff_enabled', 'transaction_sub_type'));
        }

        $with[] = 'payment_lines';
        if (!empty($with)) {
            $sells->with($with);
        }

        //$business_details = $this->businessUtil->getDetails($business_id);
        if ($this->businessUtil->isModuleEnabled('subscription')) {
            $sells->addSelect('transactions.is_recurring', 'transactions.recur_parent_id');
        }
        $sales_order_statuses = Transaction::sales_order_statuses();
        $datatable = DataTables::of($sells)
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
                '<span class="final-total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
            )
            ->editColumn(
                'tax_amount',
                '<span class="total-tax" data-orig-value="{{$tax_amount}}">@format_currency($tax_amount)</span>'
            )
            ->editColumn(
                'total_paid',
                '<span class="total-paid" data-orig-value="{{$total_paid}}">@format_currency($total_paid)</span>'
            )
            ->editColumn(
                'total_before_tax',
                '<span class="total_before_tax" data-orig-value="{{$total_before_tax}}">@format_currency($total_before_tax)</span>'
            )
            ->editColumn(
                'discount_amount',
                function ($row) {
                    $discount = !empty($row->discount_amount) ? $row->discount_amount : 0;

                    if (!empty($discount) && $row->discount_type == 'percentage') {
                        $discount = $row->total_before_tax * ($discount / 100);
                    }

                    return '<span class="total-discount" data-orig-value="' . $discount . '">' . $this->transactionUtil->num_f($discount, true) . '</span>';
                }
            )
            ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
            ->editColumn(
                'payment_status',
                function ($row) {
                    $payment_status = Transaction::getPaymentStatus($row);
                    return (string) view('sell.partials.payment_status', ['payment_status' => $payment_status, 'id' => $row->id]);
                }
            )
            ->editColumn(
                'types_of_service_name',
                '<span class="service-type-label" data-orig-value="{{$types_of_service_name}}" data-status-name="{{$types_of_service_name}}">{{$types_of_service_name}}</span>'
            )
            ->addColumn('total_remaining', function ($row) {
                $total_remaining =  $row->final_total - $row->total_paid;
                $total_remaining_html = '<span class="payment_due" data-orig-value="' . $total_remaining . '">' . $this->transactionUtil->num_f($total_remaining, true) . '</span>';

                return $total_remaining_html;
            })
            ->addColumn('return_due', function ($row) {
                $return_due_html = '';
                if (!empty($row->return_exists)) {
                    $return_due = $row->amount_return - $row->return_paid;
                    $return_due_html .= '<a href="' . action("TransactionPaymentController@show", [$row->return_transaction_id]) . '" class="view_purchase_return_payment_modal"><span class="sell_return_due" data-orig-value="' . $return_due . '">' . $this->transactionUtil->num_f($return_due, true) . '</span></a>';
                }

                return $return_due_html;
            })
            ->addColumn('checkbox', function () {
                return '<input type="checkbox" class="select-row"/>';
            })
            ->editColumn('invoice_no', function ($row) use ($is_crm) {
                $invoice_no = $row->invoice_no;
                if (!empty($row->woocommerce_order_id)) {
                    $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="' . __('lang_v1.synced_from_woocommerce') . '"></i>';
                }
                if (!empty($row->return_exists)) {
                    $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="' . __('lang_v1.some_qty_returned_from_sell') . '"><i class="fas fa-undo"></i></small>';
                }
                if (!empty($row->is_recurring)) {
                    $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="' . __('lang_v1.subscribed_invoice') . '"><i class="fas fa-recycle"></i></small>';
                }

                if (!empty($row->recur_parent_id)) {
                    $invoice_no .= ' &nbsp;<small class="label bg-info label-round no-print" title="' . __('lang_v1.subscription_invoice') . '"><i class="fas fa-recycle"></i></small>';
                }

                if (!empty($row->is_export)) {
                    $invoice_no .= '</br><small class="label label-default no-print" title="' . __('lang_v1.export') . '">' . __('lang_v1.export') . '</small>';
                }

                if ($is_crm && !empty($row->crm_is_order_request)) {
                    $invoice_no .= ' &nbsp;<small class="label bg-yellow label-round no-print" title="' . __('crm::lang.order_request') . '"><i class="fas fa-tasks"></i></small>';
                }

                return $invoice_no;
            })
            ->addColumn('contact_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$name}}')
            ->editColumn('total_items', '{{@format_quantity($total_items)}}')
            ->filterColumn('contact_name', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('contacts.name', 'like', "%{$keyword}%")
                        ->orWhere('contacts.supplier_business_name', 'like', "%{$keyword}%");
                });
            })
            ->addColumn('payment_methods', function ($row) use ($payment_types) {
                $methods = array_unique($row->payment_lines->pluck('method')->toArray());
                $count = count($methods);
                $payment_method = '';
                if ($count == 1) {
                    $payment_method = $payment_types[$methods[0]];
                } elseif ($count > 1) {
                    $payment_method = __('lang_v1.checkout_multi_pay');
                }

                $html = !empty($payment_method) ? '<span class="payment-method" data-orig-value="' . $payment_method . '" data-status-name="' . $payment_method . '">' . $payment_method . '</span>' : '';

                return $html;
            })
            ->editColumn('status', function ($row) use ($sales_order_statuses, $is_admin) {
                $status = '';

                if ($row->type == 'sales_order') {
                    if ($is_admin && $row->status != 'completed') {
                        $status = '<span class="edit-so-status label ' . $sales_order_statuses[$row->status]['class'] . '" data-href="' . action("SalesOrderController@getEditSalesOrderStatus", ['id' => $row->id]) . '">' . $sales_order_statuses[$row->status]['label'] . '</span>';
                    } else {
                        $status = '<span class="label ' . $sales_order_statuses[$row->status]['class'] . '" >' . $sales_order_statuses[$row->status]['label'] . '</span>';
                    }
                }

                return $status;
            })
            ->editColumn('so_qty_remaining', '{{@format_quantity($so_qty_remaining)}}')
            ->setRowAttr([
                'data-href' => function ($row) {
                    if (auth()->user()->can("sell.view") || auth()->user()->can("view_own_sell_only")) {
                        return  action('SellController@show', [$row->id]);
                    } else {
                        return '';
                    }
                }
            ]);

        $rawColumns = ['final_total', 'mapping', 'total_paid', 'total_remaining', 'payment_status', 'invoice_no', 'discount_amount', 'tax_amount', 'total_before_tax', 'types_of_service_name', 'payment_methods', 'return_due', 'contact_name', 'status', 'chart_of_account', 'checkbox'];

        return $datatable->rawColumns($rawColumns)->make(true);
    }
}
