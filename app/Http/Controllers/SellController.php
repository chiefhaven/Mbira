<?php

namespace App\Http\Controllers;

use App\Account;
use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\InvoiceScheme;
use App\Media;
use App\Product;
use App\SellingPriceGroup;
use App\TaxRate;
use App\Transaction;
use App\TransactionSellLine;
use App\TypesOfService;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Variation;
use App\Warranty;
use DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Modules\EIS\Services\Sales\SaleSubmissionRule;
use Modules\EIS\Services\Sales\DirectSaleSubmissionService;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class SellController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $contactUtil;

    protected $businessUtil;

    protected $transactionUtil;

    protected $productUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ContactUtil $contactUtil, BusinessUtil $businessUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil, ProductUtil $productUtil)
    {
        $this->contactUtil = $contactUtil;
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->productUtil = $productUtil;

        $this->dummyPaymentLine = ['method' => '', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
            'is_return' => 0, 'transaction_no' => '', ];

        $this->shipping_status_colors = [
            'ordered' => 'bg-yellow',
            'packed' => 'bg-info',
            'shipped' => 'bg-navy',
            'delivered' => 'bg-green',
            'cancelled' => 'bg-red',
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['sell.view', 'sell.create', 'direct_sell.access', 'direct_sell.view', 'view_own_sell_only', 'view_commission_agent_sell', 'access_shipping', 'access_own_shipping', 'access_commission_agent_shipping', 'so.view_all', 'so.view_own'])) {
            abort(403, 'Unauthorized action.');
        }

        $eis_status = [
            'pending' => 'Pending',
            'submitted' => 'Submmited',
            'failed' => 'Failed', 
        ];

        $business_id = request()->session()->get('user.business_id');
        $canUseEis = \App\Services\EisProductPolicy::configured($business_id);
        $is_woocommerce = $this->moduleUtil->isModuleInstalled('Woocommerce');
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        $is_tables_enabled = $this->transactionUtil->isModuleEnabled('tables');
        $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');
        $is_types_service_enabled = $this->moduleUtil->isModuleEnabled('types_of_service');

        if (request()->ajax()) {
            $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);
            $with = [];
            if (DB::getSchemaBuilder()->hasTable('eis_sales')) {
                $with[] = 'eisSale';
            }
            $shipping_statuses = $this->transactionUtil->shipping_statuses();

            $sale_type = ! empty(request()->input('sale_type')) ? request()->input('sale_type') : 'sell';

            $sells = $this->transactionUtil->getListSells($business_id, $sale_type);

            // Apply all filters in a single reusable method
            $this->applySellListFilters($sells, $business_id, $sale_type, false);

            // Create cache key based on all filters for count query optimization
            $cache_key_params = [
                'business_id' => $business_id,
                'sale_type' => $sale_type,
                'location_id' => request()->input('location_id'),
                'customer_id' => request()->input('customer_id'),
                'payment_status' => request()->input('payment_status'),
                'payment_method' => request()->input('payment_method'),
                'start_date' => request()->input('start_date'),
                'end_date' => request()->input('end_date'),
                'created_by' => request()->input('created_by'),
                'user_id' => auth()->id(),
                'only_shipments' => request()->input('only_shipments'),
                'shipping_status' => request()->input('shipping_status'),
                'source' => request()->input('source'),
                'crm_is_order_request' => request()->input('crm_is_order_request'),
                'only_subscriptions' => request()->input('only_subscriptions'),
                'res_waiter_id' => request()->input('res_waiter_id'),
                'sub_type' => request()->input('sub_type'),
                'status' => request()->input('status'),
                'eis_status' => request()->input('eis_status'),
                'eis_void_status' => request()->input('eis_void_status'),
                'sales_cmsn_agnt' => request()->input('sales_cmsn_agnt'),
                'service_staffs' => request()->input('service_staffs'),
                'only_pending_shipments' => request()->input('only_pending_shipments'),
                'for_dashboard_sales_order' => request()->input('for_dashboard_sales_order'),
                'delivery_person' => request()->input('delivery_person'),
                'is_zatca' => request()->input('is_zatca'),
                'zatca_status' => request()->input('zatca_status'),
                'rewards_only' => request()->input('rewards_only'),
                'commission_agent' => request()->input('commission_agent'),
            ];
            $cache_key = 'sell_list_count_' . md5(json_encode($cache_key_params));

            // Get total count using cache (expires in 60 seconds)
            $total_records = Cache::remember($cache_key, 300, function () use ($business_id, $sale_type) {
                $count_query = $this->transactionUtil->getListSells($business_id, $sale_type, true);
                // Apply same filters (count-safe)
                $this->applySellListFilters($count_query, $business_id, $sale_type, true);
                // IMPORTANT: do NOT groupBy here; just count distinct ids
                return $count_query->distinct('transactions.id')->count('transactions.id');
            });

            $sells->groupBy('transactions.id');

            if (! empty(request()->suspended)) {
                $transaction_sub_type = request()->get('transaction_sub_type');
                if (! empty($transaction_sub_type)) {
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
                foreach ($with as $relation) {
                    if ($relation == 'payment_lines' && !empty(request()->input('payment_method'))) {
                        $sells->whereHas($relation, function ($query) {
                            $query->where('method', request()->input('payment_method'));
                        });
                    } else {
                        $sells->with($relation);
                    }
                }
            }

            //$business_details = $this->businessUtil->getDetails($business_id);
            if ($is_crm) {
                // Ensure crm_is_order_request is available for UI labels
                $sells->addSelect('transactions.crm_is_order_request');
            }

            if ($this->businessUtil->isModuleEnabled('subscription')) {
                $sells->addSelect('transactions.is_recurring', 'transactions.recur_parent_id');
            }

            $sales_order_statuses = Transaction::sales_order_statuses();

            // for zatca module Retrieve the 'is_zatca' parameter from the request; default to 0 if not provided and only comes 1 from zatca module
            $is_zatca = !empty(request()->input('is_zatca')) ? request()->input('is_zatca') : 0;

            if ($is_zatca) {
                $sells->addSelect('transactions.zatca_status');

                if (! empty(request()->input('zatca_status'))) {
                    if (request()->input('zatca_status') == 'pending') {
                        $sells->whereNull('transactions.zatca_status');
                    } else {
                        $sells->where('transactions.zatca_status', request()->input('zatca_status'));
                    }
                }

                // Only include locations that have a non-empty sync_from_datetime set in bl.zatca_sync_from_datetime
                $sells->whereNotNull('bl.zatca_sync_from_datetime');
                // Include transactions on or after the location's sync_from_datetime
                $sells->whereRaw('transactions.transaction_date >= bl.zatca_sync_from_datetime');
            }

            // Expose shipment-only flag to table rendering
            $only_shipments = request()->only_shipments == 'true' ? true : false;

            $datatable = Datatables::of($sells)
                ->addColumn(
                    'action',
                    function ($row) use ($only_shipments, $is_admin, $sale_type, $is_zatca, $canUseEis) {

                        // this action button for zatca module
                        if ($is_zatca) {
                            if ($row->zatca_status == 'success') {
                                return '<div class="btn-group">
                                <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info tw-w-max dropdown-toggle"
                                    data-toggle="dropdown" aria-expanded="false">' .
                                    __('messages.actions') .
                                    '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-left" role="menu">
                                    <li><a class="download-xml" href="'.action([\Modules\ZatcaIntegrationKsa\Http\Controllers\ZatcaInvoiceController::class, 'downloadXml'], [$row->id]).'">
                                            <i class="fas fa-file-download"></i> '.__('zatcaintegrationksa::lang.download_xml').'
                                        </a>
                                    </li>
                                    <li>
                                        <a class="download-a3-pdf" target="_blank" href="'.action([\Modules\ZatcaIntegrationKsa\Http\Controllers\ZatcaInvoiceController::class, 'sale_print_pdf'], [$row->id]).'">
                                            <i class="fas fa-file-download"></i> '.__('zatcaintegrationksa::lang.download_a3_pdf').'
                                        </a>
                                    </li>
                                </ul></div>';
                            }else {
                                return '<a href="' . action([\Modules\ZatcaIntegrationKsa\Http\Controllers\ZatcaInvoiceController::class, 'sycs_sale'], [$row->id]) . '" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info tw-w-max sycs_sale">' . __('zatcaintegrationksa::lang.sync') . '</a>';
                            }


                        }
                        $html = '<div class="btn-group">
                                    <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max dropdown-toggle"
                                        data-toggle="dropdown" aria-expanded="false">' .
                        __('messages.actions') .
                            '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                        </span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                        if (auth()->user()->can('sell.view') || auth()->user()->can('direct_sell.view') || auth()->user()->can('view_own_sell_only')) {
                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';
                        }
                        if (auth()->user()->can('sell.view') || auth()->user()->can('direct_sell.view') || auth()->user()->can('view_own_sell_only')) {
                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'splitBill'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-hand-scissors" aria-hidden="true"></i> '.__('lang_v1.split_bill').'</a></li>';
                        }
                        if (! $only_shipments) {
                            if ($row->is_direct_sale == 0) {
                                if (auth()->user()->can('sell.update')) {
                                    $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellPosController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
                                }
                            } elseif ($row->type == 'sales_order') {
                                if (auth()->user()->can('so.update')) {
                                    $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
                                }
                            } else {
                                if (auth()->user()->can('direct_sell.update')) {
                                    $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
                                }
                            }

                            $delete_link = '<li><a href="'.action([\App\Http\Controllers\SellPosController::class, 'destroy'], [$row->id]).'" class="delete-sale"><i class="fas fa-trash"></i> '.__('messages.delete').'</a></li>';
                            if ($row->is_direct_sale == 0) {
                                if (auth()->user()->can('sell.delete')) {
                                    $html .= $delete_link;
                                }
                            } elseif ($row->type == 'sales_order') {
                                if (auth()->user()->can('so.delete')) {
                                    $html .= $delete_link;
                                }
                            } else {
                                if (auth()->user()->can('direct_sell.delete')) {
                                    $html .= $delete_link;
                                }
                            }
                        }

                        if (config('constants.enable_download_pdf') && auth()->user()->can('print_invoice') && $sale_type != 'sales_order') {
                            $html .= '<li><a href="'.route('sell.downloadPdf', [$row->id]).'" target="_blank"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.download_pdf').'</a></li>';

                            if (! empty($row->shipping_status)) {
                                $html .= '<li><a href="'.route('packing.downloadPdf', [$row->id]).'" target="_blank"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.download_paking_pdf').'</a></li>';
                            }
                        }

                        if (auth()->user()->can('sell.view') || auth()->user()->can('direct_sell.access')) {
                            if (! empty($row->document)) {
                                $document_name = ! empty(explode('_', $row->document, 2)[1]) ? explode('_', $row->document, 2)[1] : $row->document;
                                $html .= '<li><a href="'.url('uploads/documents/'.$row->document).'" download="'.$document_name.'"><i class="fas fa-download" aria-hidden="true"></i>'.__('purchase.download_document').'</a></li>';
                                if (isFileImage($document_name)) {
                                    $html .= '<li><a href="#" data-href="'.url('uploads/documents/'.$row->document).'" class="view_uploaded_document"><i class="fas fa-image" aria-hidden="true"></i>'.__('lang_v1.view_document').'</a></li>';
                                }
                            }
                        }

                        if ($is_admin || auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'editShipping'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-truck" aria-hidden="true"></i>'.__('lang_v1.edit_shipping').'</a></li>';
                        }

                        if ($row->type == 'sell') {
                            if (auth()->user()->can('print_invoice')) {
                                $html .= '<li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.print_invoice').'</a></li>
                                    <li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'?package_slip=true"><i class="fas fa-file-alt" aria-hidden="true"></i> '.__('lang_v1.packing_slip').'</a></li>';

                                $html .= '<li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'?delivery_note=true"><i class="fas fa-file-alt" aria-hidden="true"></i> '.__('lang_v1.delivery_note').'</a></li>';
                            }
                            $html .= '<li class="divider"></li>';
                            if (! $only_shipments) {
                                if ($row->is_direct_sale == 0 && ! auth()->user()->can('sell.update') &&
                                auth()->user()->can('edit_pos_payment')) {
                                    $html .= '<li><a href="'.route('edit-pos-payment', [$row->id]).'" 
                                    ><i class="fas fa-money-bill-alt"></i> '.__('lang_v1.add_edit_payment').
                                    '</a></li>';
                                }

                                if (auth()->user()->can('sell.payments') ||
                                    auth()->user()->can('edit_sell_payment') ||
                                    auth()->user()->can('delete_sell_payment')) {
                                    if ($row->payment_status != 'paid') {
                                        $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$row->id]).'" class="add_payment_modal"><i class="fas fa-money-bill-alt"></i> '.__('purchase.add_payment').'</a></li>';
                                    }

                                    $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$row->id]).'" class="view_payment_modal"><i class="fas fa-money-bill-alt"></i> '.__('purchase.view_payments').'</a></li>';
                                }

                                if (auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access')) {
                                    // $html .= '<li><a href="' . action([\App\Http\Controllers\SellController::class, 'duplicateSell'], [$row->id]) . '"><i class="fas fa-copy"></i> ' . __("lang_v1.duplicate_sell") . '</a></li>';

                                    $html .= '<li><a href="'.action([\App\Http\Controllers\SellReturnController::class, 'add'], [$row->id]).'"><i class="fas fa-undo"></i> '.__('lang_v1.sell_return').'</a></li>

                                    <li><a href="'.action([\App\Http\Controllers\SellPosController::class, 'showInvoiceUrl'], [$row->id]).'" class="view_invoice_url"><i class="fas fa-eye"></i> '.__('lang_v1.view_invoice_url').'</a></li>';
                                }
                            }

                            if (auth()->user()->can('business_settings.access')) {
                                $fiscalSale = $row->relationLoaded('eisSale') ? $row->eisSale : null;
                                $eisInvoiceNumber = $fiscalSale ? $fiscalSale->getRawOriginal('fiscal_invoice_number') : null;
                                if ($fiscalSale && in_array($fiscalSale->status, ['pending', 'failed', 'submitted'], true)
                                    && $eisInvoiceNumber && $fiscalSale->eis_setting_id) {
                                    $html .= '<li><a href="#" class="eis-sale-sync" data-sync-url="'
                                        .e(route('eis.sales.sync', ['id' => $fiscalSale->id])).'">'
                                        .'<i class="fas fa-sync"></i> Sync EIS Invoice/Receipt</a></li>';
                                }
                            }
                            if ($canUseEis && auth()->user()->can('sell.delete')) {
                                $fiscalSale = $row->relationLoaded('eisSale') ? $row->eisSale : null;
                                $eisInvoiceNumber = $fiscalSale ? $fiscalSale->getRawOriginal('fiscal_invoice_number') : null;
                                $receiptNumber = $fiscalSale
                                    ? ($fiscalSale->getRawOriginal('receipt_number') ?: $eisInvoiceNumber)
                                    : null;
                                if ($fiscalSale && $fiscalSale->status === 'submitted'
                                    && $eisInvoiceNumber && $receiptNumber && $fiscalSale->eis_setting_id) {
                                    $html .= '<li><a href="#" class="eis-cancel-receipt" data-terminal-id="'
                                        .e((string) $fiscalSale->eis_setting_id).'" data-invoice-number="'
                                        .e($receiptNumber).'"><i class="fas fa-ban"></i> Request EIS receipt cancellation</a></li>';
                                    if (auth()->user()->can('business_settings.access')) {
                                        $html .= '<li><a href="#" class="eis-void-sync" data-terminal-id="'
                                            .e((string) $fiscalSale->eis_setting_id).'" data-invoice-number="'
                                            .e($eisInvoiceNumber).'"><i class="fas fa-sync"></i> Check EIS void status</a></li>';
                                    }
                                }
                            }

                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $row->id, 'template_for' => 'new_sale']).'" class="btn-modal" data-container=".view_modal"><i class="fa fa-envelope" aria-hidden="true"></i>'.__('lang_v1.new_sale_notification').'</a></li>';
                        } else {
                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'viewMedia'], ['model_id' => $row->id, 'model_type' => \App\Transaction::class, 'model_media_type' => 'shipping_document']).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-paperclip" aria-hidden="true"></i>'.__('lang_v1.shipping_documents').'</a></li>';
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
                    $total_remaining = $row->final_total - $row->total_paid;
                    $total_remaining_html = '<span class="payment_due" data-orig-value="' . $total_remaining . '">' . $this->transactionUtil->num_f($total_remaining, true) . '</span>';

                    return $total_remaining_html;
                })
                ->addColumn('return_due', function ($row) {
                    $return_due_html = '';
                    if (!empty($row->return_exists)) {
                        $return_due = $row->amount_return - $row->return_paid;
                        $return_due_html .= '<a href="' . action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$row->return_transaction_id]) . '" class="view_purchase_return_payment_modal"><span class="sell_return_due" data-orig-value="' . $return_due . '">' . $this->transactionUtil->num_f($return_due, true) . '</span></a>';
                    }

                    return $return_due_html;
                })
                ->editColumn('invoice_no', function ($row) use ($is_crm) {
                    $invoice_no = $row->invoice_no;
                    if (! empty($row->woocommerce_order_id)) {
                        $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="'.__('lang_v1.synced_from_woocommerce').'"></i>';
                    }
                    if (! empty($row->return_exists)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="'.__('lang_v1.some_qty_returned_from_sell').'"><i class="fas fa-undo"></i></small>';
                    }
                    if (! empty($row->is_recurring)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="'.__('lang_v1.subscribed_invoice').'"><i class="fas fa-recycle"></i></small>';
                    }

                    if (! empty($row->recur_parent_id)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-info label-round no-print" title="'.__('lang_v1.subscription_invoice').'"><i class="fas fa-recycle"></i></small>';
                    }

                    if (! empty($row->is_export)) {
                        $invoice_no .= '</br><small class="label label-default no-print" title="'.__('lang_v1.export').'">'.__('lang_v1.export').'</small>';
                    }

                    if ($is_crm && ! empty($row->crm_is_order_request)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-yellow label-round no-print" title="'.__('crm::lang.order_request').'"><i class="fas fa-tasks"></i></small>';
                    }

                    return $invoice_no;
                })
                ->editColumn('shipping_status', function ($row) use ($shipping_statuses) {
                    $status_color = ! empty($this->shipping_status_colors[$row->shipping_status]) ? $this->shipping_status_colors[$row->shipping_status] : 'bg-gray';
                    $status = ! empty($row->shipping_status) ? '<a href="#" class="btn-modal" data-href="'.action([\App\Http\Controllers\SellController::class, 'editShipping'], [$row->id]).'" data-container=".view_modal"><span class="label '.$status_color.'">'.$shipping_statuses[$row->shipping_status].'</span></a>' : '';

                    return $status;
                })
                ->addColumn('conatct_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$name}}')
                ->editColumn('total_items', '{{@format_quantity($total_items)}}')
                ->filterColumn('conatct_name', function ($query, $keyword) {
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
                        $payment_method = $payment_types[$methods[0]] ?? '';
                    } elseif ($count > 1) {
                        $payment_method = __('lang_v1.checkout_multi_pay');
                    }

                    $html = ! empty($payment_method) ? '<span class="payment-method" data-orig-value="'.$payment_method.'" data-status-name="'.$payment_method.'">'.$payment_method.'</span>' : '';

                    return $html;
                })
                ->addColumn('eis_status', function ($row) use ($eis_status) {
                    $status = optional($row->eisSale)->status;
                    return $status ? ($eis_status[$status] ?? $status) : '';
                })
                ->addColumn('eis_void_status', function ($row) {
                    $sale = $row->eisSale;
                    if (!$sale || !$sale->void_synced_at) {
                        return '';
                    }
                    if ($sale->void_status === 'void') {
                        return '<span class="label label-danger">Void</span>';
                    }
                    return $sale->void_approval_status
                        ? '<span class="label label-default">'.e($sale->void_approval_status).'</span>' : '';
                })
                ->editColumn('status', function ($row) use ($sales_order_statuses, $is_admin) {
                    $status = '';

                    if ($row->type == 'sales_order') {
                        if ($is_admin && $row->status != 'completed') {
                            $status = '<span class="edit-so-status label '.$sales_order_statuses[$row->status]['class'].'" data-href="'.action([\App\Http\Controllers\SalesOrderController::class, 'getEditSalesOrderStatus'], ['id' => $row->id]).'">'.$sales_order_statuses[$row->status]['label'].'</span>';
                        } else {
                            $status = '<span class="label '.$sales_order_statuses[$row->status]['class'].'" >'.$sales_order_statuses[$row->status]['label'].'</span>';
                        }
                    }

                    return $status;
                })
                ->editColumn('zatca_status', function ($row) use ($is_zatca) {
                    $status = '';
                    if($is_zatca){
                        if (empty($row->zatca_status) || is_null($row->zatca_status)) {
                            $status = '<small class="label bg-primary tw-dw-btn-xs no-print">'.__('zatcaintegrationksa::lang.pending').'</small>';
                        } elseif ($row->zatca_status == 'success') {
                            $status = '<small class="label bg-light-green tw-dw-btn-xs no-print">' . ucfirst($row->zatca_status) . '</small>';
                        } elseif ($row->zatca_status == 'failed') {
                                $lastDoc = \Modules\ZatcaIntegrationKsa\Entities\ZatcaDocument::where('transaction_id', $row->id)
                                    ->where('sent_to_zatca_status', 'failed')
                                    ->orderBy('created_at', 'desc')
                                    ->latest()
                                    ->first();

                                if ($lastDoc && $lastDoc->response_source == 'self' && !empty($lastDoc->response)) {
                                    $safeMsg = htmlspecialchars($lastDoc->response, ENT_QUOTES, 'UTF-8');
                                    $status = '<small class="label bg-red tw-dw-btn-xs no-print mb-1">' . ucfirst($row->zatca_status) . '</small><br><span class="text-danger">' . $safeMsg . '</span>';
                                } else if ($lastDoc) {
                                    $label = '<small class="label bg-red tw-dw-btn-xs no-print mb-5">' . ucfirst($row->zatca_status) . '</small>';
                                    $button = '<a href="' . action([\Modules\ZatcaIntegrationKsa\Http\Controllers\ZatcaInvoiceController::class, 'showInvoiceError'], ['id' => $row->id]) . '" class="btn btn-xs btn-danger no-print mt-2 status_fail" style="margin-top: 10px;">' . e(__('zatcaintegrationksa::lang.view_error')) . '</a>';
                                    $status = $label . '<br>' . $button;
                                }
                        }
                    }
                    return $status;
                })
                ->editColumn('so_qty_remaining', '{{@format_quantity($so_qty_remaining)}}')
                ->setRowAttr([
                    'data-href' => function ($row) {
                        if (auth()->user()->can('sell.view') || auth()->user()->can('view_own_sell_only')) {
                            return  action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]);
                        } else {
                            return '';
                        }
                    }, ]);

            $rawColumns = ['final_total', 'action', 'total_paid', 'total_remaining', 'payment_status', 'invoice_no', 'discount_amount', 'tax_amount', 'total_before_tax', 'shipping_status', 'types_of_service_name', 'payment_methods', 'return_due', 'conatct_name', 'status', 'zatca_status', 'eis_void_status'];

            return $datatable->rawColumns($rawColumns)
                      ->skipTotalRecords()
                      ->setFilteredRecords($total_records)
                      ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);
        $sales_representative = User::forDropdown($business_id, false, false, true);

        //Commission agent filter
        $is_cmsn_agent_enabled = request()->session()->get('business.sales_cmsn_agnt');
        $commission_agents = [];
        if (! empty($is_cmsn_agent_enabled)) {
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

        $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);
        return view('sell.index')
        ->with(compact('business_locations', 'customers', 'is_woocommerce', 'sales_representative', 'is_cmsn_agent_enabled', 'commission_agents', 'service_staffs', 'is_tables_enabled', 'is_service_staff_enabled', 'is_types_service_enabled', 'shipping_statuses', 'sources', 'payment_types', 'canUseEis'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $sale_type = request()->get('sale_type', '');

        if ($sale_type == 'sales_order') {
            if (! auth()->user()->can('so.create')) {
                abort(403, 'Unauthorized action.');
            }
        } else {
            if (! auth()->user()->can('direct_sell.access')) {
                abort(403, 'Unauthorized action.');
            }
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not, then check for users quota
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        } elseif (! $this->moduleUtil->isQuotaAvailable('invoices', $business_id)) {
            return $this->moduleUtil->quotaExpiredResponse('invoices', $business_id, action([\App\Http\Controllers\SellController::class, 'index']));
        }

        $walk_in_customer = $this->contactUtil->getWalkInCustomer($business_id);

        $business_details = $this->businessUtil->getDetails($business_id);
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $business_locations = BusinessLocation::forDropdown($business_id, false, true);
        $bl_attributes = $business_locations['attributes'];
        $business_locations = $business_locations['locations'];

        $default_location = null;
        foreach ($business_locations as $id => $name) {
            $default_location = BusinessLocation::findOrFail($id);
            break;
        }

        $commsn_agnt_setting = $business_details->sales_cmsn_agnt;
        $commission_agent = [];
        if ($commsn_agnt_setting == 'user') {
            $commission_agent = User::forDropdown($business_id);
        } elseif ($commsn_agnt_setting == 'cmsn_agnt') {
            $commission_agent = User::saleCommissionAgentsDropdown($business_id);
        }

        $types = [];
        if (auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }
        $customer_groups = CustomerGroup::forDropdown($business_id);

        $payment_line = $this->dummyPaymentLine;
        $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

        //Selling Price Group Dropdown
        $price_groups = SellingPriceGroup::forDropdown($business_id);

        $default_price_group_id = ! empty($default_location->selling_price_group_id) && array_key_exists($default_location->selling_price_group_id, $price_groups) ? $default_location->selling_price_group_id : null;

        $default_datetime = $this->businessUtil->format_date('now', true);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $invoice_schemes = InvoiceScheme::forDropdown($business_id);
        $default_invoice_schemes = InvoiceScheme::getDefault($business_id);
        if (! empty($default_location) && !empty($default_location->sale_invoice_scheme_id)) {
            $default_invoice_schemes = InvoiceScheme::where('business_id', $business_id)
                                        ->findorfail($default_location->sale_invoice_scheme_id);
        }
        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        //Types of service
        $types_of_service = [];
        if ($this->moduleUtil->isModuleEnabled('types_of_service')) {
            $types_of_service = TypesOfService::forDropdown($business_id);
        }

        //Accounts
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }

        $status = request()->get('status', '');

        $statuses = Transaction::sell_statuses();

        if ($sale_type == 'sales_order') {
            $status = 'ordered';
        }

        $is_order_request_enabled = false;
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        if ($is_crm) {
            $crm_settings = Business::where('id', auth()->user()->business_id)
                                ->value('crm_settings');
            $crm_settings = ! empty($crm_settings) ? json_decode($crm_settings, true) : [];

            if (! empty($crm_settings['enable_order_request'])) {
                $is_order_request_enabled = true;
            }
        }

        //Added check because $users is of no use if enable_contact_assign if false
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        $change_return = $this->dummyPaymentLine;

        return view('sell.create')
            ->with(compact(
                'business_details',
                'taxes',
                'walk_in_customer',
                'business_locations',
                'bl_attributes',
                'default_location',
                'commission_agent',
                'types',
                'customer_groups',
                'payment_line',
                'payment_types',
                'price_groups',
                'default_datetime',
                'pos_settings',
                'invoice_schemes',
                'default_invoice_schemes',
                'types_of_service',
                'accounts',
                'shipping_statuses',
                'status',
                'sale_type',
                'statuses',
                'is_order_request_enabled',
                'users',
                'default_price_group_id',
                'change_return'
            ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // if (!auth()->user()->can('sell.view') && !auth()->user()->can('direct_sell.access') && !auth()->user()->can('view_own_sell_only')) {
        //     abort(403, 'Unauthorized action.');
        // }

        $business_id = request()->session()->get('user.business_id');
        $taxes = TaxRate::where('business_id', $business_id)
                            ->pluck('name', 'id');
        $query = Transaction::where('business_id', $business_id)
                    ->where('id', $id)
                    ->with(['contact', 'eisSale', 'delivery_person_user', 'sell_lines' => function ($q) {
                        $q->whereNull('parent_sell_line_id');
                    }, 'sell_lines.product', 'sell_lines.product.unit', 'sell_lines.product.second_unit', 'sell_lines.variations', 'sell_lines.variations.product_variation', 'payment_lines', 'sell_lines.modifiers', 'sell_lines.lot_details', 'tax', 'sell_lines.sub_unit', 'table', 'service_staff', 'sell_lines.service_staff', 'types_of_service', 'sell_lines.warranties', 'media']);

        if (! auth()->user()->can('sell.view') && ! auth()->user()->can('direct_sell.access') && auth()->user()->can('view_own_sell_only')) {
            $query->where('transactions.created_by', request()->session()->get('user.id'));
        }

        $sell = $query->firstOrFail();

        $activities = Activity::forSubject($sell)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        $line_taxes = [];
        foreach ($sell->sell_lines as $key => $value) {
            if (! empty($value->sub_unit_id)) {
                $formated_sell_line = $this->transactionUtil->recalculateSellLineTotals($business_id, $value);
                $sell->sell_lines[$key] = $formated_sell_line;
            }

            if (! empty($taxes[$value->tax_id])) {
                if (isset($line_taxes[$taxes[$value->tax_id]])) {
                    $line_taxes[$taxes[$value->tax_id]] += ($value->item_tax * $value->quantity);
                } else {
                    $line_taxes[$taxes[$value->tax_id]] = ($value->item_tax * $value->quantity);
                }
            }
        }

        $payment_types = $this->transactionUtil->payment_types($sell->location_id, true);
        $order_taxes = [];
        if (! empty($sell->tax)) {
            if ($sell->tax->is_tax_group) {
                $order_taxes = $this->transactionUtil->sumGroupTaxDetails($this->transactionUtil->groupTaxDetails($sell->tax, $sell->tax_amount));
            } else {
                $order_taxes[$sell->tax->name] = $sell->tax_amount;
            }
        }

        $business_details = $this->businessUtil->getDetails($business_id);
        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);
        $shipping_statuses = $this->transactionUtil->shipping_statuses();
        $shipping_status_colors = $this->shipping_status_colors;
        $common_settings = session()->get('business.common_settings');
        $is_warranty_enabled = ! empty($common_settings['enable_product_warranty']) ? true : false;

        $statuses = Transaction::sell_statuses();

        if ($sell->type == 'sales_order') {
            $sales_order_statuses = Transaction::sales_order_statuses(true);
            $statuses = array_merge($statuses, $sales_order_statuses);
        }
        $status_color_in_activity = Transaction::sales_order_statuses();
        $sales_orders = $sell->salesOrders();

        return view('sale_pos.show')
            ->with(compact(
                'taxes',
                'sell',
                'payment_types',
                'order_taxes',
                'pos_settings',
                'shipping_statuses',
                'shipping_status_colors',
                'is_warranty_enabled',
                'activities',
                'statuses',
                'status_color_in_activity',
                'sales_orders',
                'line_taxes'
            ));
    }

    /**
     * Show split bill modal
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function splitBill($id)
    {
        if (!auth()->user()->can('sell.view') && !auth()->user()->can('direct_sell.access') && !auth()->user()->can('view_own_sell_only')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        
        $sell = Transaction::where('business_id', $business_id)
                    ->where('id', $id)
                    ->with([
                        'contact',
                        'sell_lines' => function ($q) {
                            $q->whereNull('parent_sell_line_id');
                        },
                        'sell_lines.product',
                        'sell_lines.product.unit',
                        'sell_lines.variations',
                        'sell_lines.variations.product_variation',
                        'location'
                    ])
                    ->firstOrFail();

        try {
            $split_preview = (new \App\Services\Sales\SavedSaleSplitService(DB::connection()))
                ->preview($sell->id, auth()->user());
        } catch (\App\Services\Sales\SavedSplitException $e) {
            return response()->json(['success' => false, 'msg' => $e->getMessage()], $e->httpStatus);
        }

        if (!$split_preview['eligible']) {
            return response()->json([
                'success' => false,
                'msg' => $split_preview['reason']['message'],
            ], 409);
        }

        return view('sale_pos.split_bill')
            ->with(compact('sell', 'split_preview'));
    }

    /**
     * Process split bill
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function processSplitBill(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|integer|min:1',
        ]);

        try {
            $result = (new \App\Services\Sales\SavedSaleSplitService(DB::connection()))
                ->split((int) $validated['transaction_id'], auth()->user(), $request->only([
                    'operation_id', 'expected_version', 'lines',
                ]));

            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.split_bill_success').' '.$result['bill_b']['invoice_no'],
                'new_transaction_id' => $result['result_id'],
                'new_invoice_no' => $result['bill_b']['invoice_no'],
                'source_invoice_no' => $result['bill_a']['invoice_no'],
            ]);
        } catch (\App\Services\Sales\SavedSplitException $e) {
            return response()->json([
                'success' => false,
                'code' => $e->errorCode,
                'msg' => $e->getMessage(),
            ], $e->httpStatus);
        } catch (\Throwable $e) {
            \Log::error('Saved sale split failed', [
                'transaction_id' => $request->input('transaction_id'),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ], 500);
        }
    }

    /**
     * Perform the actual split bill operation
     * This is called within a split-aware checkout context
     */
    private function performSplitBillOperation($request, $business_id, $user_id)
    {
        $original_transaction = Transaction::where('business_id', $business_id)
                                ->where('id', $request->transaction_id)
                                ->lockForUpdate()
                                ->with(['sell_lines' => function($q) {
                                    $q->whereNull('parent_sell_line_id');
                                }])
                                ->firstOrFail();

        // Check if EIS already submitted
        if (!empty($original_transaction->eisSale) && $original_transaction->eisSale->status == 'submitted') {
            throw new \Exception(__('lang_v1.cannot_split_after_eis_submission'));
        }

        // Get all line IDs that will be affected
        $all_line_ids = $original_transaction->sell_lines->pluck('id')->toArray();
        $selected_ids = [];
        $selected_lines_data = [];
        $split_items = [];

            foreach ($request->sell_lines as $line_data) {
                if (is_array($line_data)) {
                    $line_id = $line_data['id'];
                    $quantity = !empty($line_data['quantity']) ? floatval($line_data['quantity']) : null;
                } else {
                    $line_id = $line_data;
                    $quantity = null;
                }

                $selected_ids[] = $line_id;

                $original_line = $original_transaction->sell_lines->where('id', $line_id)->first();
                if (!$original_line) {
                    throw new \Exception(__('lang_v1.sell_line_not_found'));
                }

                // If quantity not specified, use full quantity
                if ($quantity === null) {
                    $quantity = $original_line->quantity;
                }

                // Validate quantity
                if ($quantity <= 0) {
                    throw new \Exception(__('lang_v1.quantity_must_be_greater_than_zero'));
                }

                if ($quantity > $original_line->quantity) {
                    throw new \Exception(__('lang_v1.quantity_exceeds_available', ['available' => $original_line->quantity]));
                }

                // Check if quantity can be split (for combo products)
                if ($original_line->product->type == 'combo') {
                    $combo_lines = TransactionSellLine::where('parent_sell_line_id', $original_line->id)
                                    ->where('children_type', 'combo')
                                    ->get();
                    foreach ($combo_lines as $combo_line) {
                        $combo_qty = ($combo_line->quantity / $original_line->quantity) * $quantity;
                        if (fmod($combo_qty, 1) != 0) {
                            throw new \Exception(__('lang_v1.cannot_split_combo_product_quantity'));
                        }
                    }
                }

                $selected_lines_data[] = [
                    'line' => $original_line,
                    'quantity' => $quantity
                ];
                $split_items[] = [
                    'id' => $line_id,
                    'quantity' => $quantity
                ];
            }

            // Check if splitting all items or leaving at least 1
            $lines_not_selected = array_diff($all_line_ids, $selected_ids);
            $remaining_items = 0;
            
            foreach ($lines_not_selected as $line_id) {
                $line = $original_transaction->sell_lines->where('id', $line_id)->first();
                if ($line) {
                    $remaining_items += $line->quantity;
                }
            }
            
            // Check if selected lines have full quantity taken
            foreach ($selected_lines_data as $item) {
                $line = $item['line'];
                $qty = $item['quantity'];
                if ($qty == $line->quantity) {
                    // Full quantity taken from this line
                    continue;
                } else {
                    // Partial quantity taken, so there will be remaining items
                    $remaining_items += ($line->quantity - $qty);
                }
            }

            // If all items are being moved, throw error
            if ($remaining_items == 0) {
                throw new \Exception(__('lang_v1.cannot_split_all_items'));
            }

            // Calculate totals for new transaction
            $split_total = 0;
            $split_total_before_tax = 0;
            $split_tax_amount = 0;
            $split_discount_amount = 0;
            $split_line_discount_total = 0;

            foreach ($selected_lines_data as $item) {
                $line = $item['line'];
                $qty = $item['quantity'];
                
                // Calculate line totals
                $line_total = $line->unit_price_inc_tax * $qty;
                $line_total_before_tax = $line->unit_price * $qty;
                $line_tax = $line->item_tax * $qty;
                $line_discount = $line->line_discount_amount * $qty;

                $split_total += $line_total;
                $split_total_before_tax += $line_total_before_tax;
                $split_tax_amount += $line_tax;
                $split_line_discount_total += $line_discount;

                // Calculate proportional discount from original transaction
                if ($original_transaction->discount_amount > 0) {
                    $proportional_discount = ($line_total / $original_transaction->final_total) * $original_transaction->discount_amount;
                    $split_discount_amount += $proportional_discount;
                }
            }

            // Create new transaction for split
            $split_transaction_data = [
                'business_id' => $business_id,
                'location_id' => $request->location_id,
                'type' => 'sell',
                'status' => $request->status ?? 'final',
                'contact_id' => $request->customer_id,
                'invoice_no' => $this->transactionUtil->getInvoiceNumber(
                    $business_id, 
                    $request->status ?? 'final', 
                    $request->location_id
                ),
                'transaction_date' => \Carbon::now(),
                'total_before_tax' => $split_total_before_tax,
                'tax_amount' => $split_tax_amount,
                'final_total' => $split_total - $split_discount_amount,
                'discount_type' => $original_transaction->discount_type,
                'discount_amount' => $split_discount_amount,
                'created_by' => $user_id,
                'additional_notes' => $request->notes ?? 'Split from invoice: ' . $original_transaction->invoice_no,
                'is_direct_sale' => 1,
                'payment_status' => 'due',
                'shipping_charges' => ($original_transaction->shipping_charges / $original_transaction->final_total) * $split_total,
                'tax_id' => $original_transaction->tax_id,
            ];

            // Apply tax recalculation
            if (!empty($original_transaction->tax_id)) {
                $tax = TaxRate::find($original_transaction->tax_id);
                if ($tax && $tax->is_tax_group) {
                    // Recalculate group tax
                    $tax_amount = $this->transactionUtil->calculateGroupTax($tax, $split_total_before_tax);
                    $split_transaction_data['tax_amount'] = $tax_amount;
                    $split_transaction_data['final_total'] = $split_total_before_tax + $tax_amount - $split_discount_amount;
                }
            }

            $split_transaction = Transaction::create($split_transaction_data);

            // Create sell lines for split transaction
            $new_sell_lines = [];
            foreach ($selected_lines_data as $item) {
                $line = $item['line'];
                $qty = $item['quantity'];

                $new_line = [
                    'product_id' => $line->product_id,
                    'variation_id' => $line->variation_id,
                    'quantity' => $qty,
                    'unit_price_before_discount' => $line->unit_price_before_discount,
                    'unit_price' => $line->unit_price,
                    'line_discount_type' => $line->line_discount_type,
                    'line_discount_amount' => $line->line_discount_amount,
                    'item_tax' => $line->item_tax,
                    'tax_id' => $line->tax_id,
                    'unit_price_inc_tax' => $line->unit_price_inc_tax,
                    'sub_unit_id' => $line->sub_unit_id,
                    'sell_line_note' => $line->sell_line_note . ' (Split from invoice ' . $original_transaction->invoice_no . ')',
                    'so_line_id' => $line->so_line_id,
                ];
                $new_sell_lines[] = $new_line;
            }

            $split_transaction->sell_lines()->createMany($new_sell_lines);

            // Copy modifiers if any
            foreach ($selected_lines_data as $item) {
                $line = $item['line'];
                $qty = $item['quantity'];
                
                $modifiers = TransactionSellLine::where('parent_sell_line_id', $line->id)
                                ->where('children_type', 'modifier')
                                ->get();
                
                if ($modifiers->count() > 0) {
                    $new_modifiers = [];
                    foreach ($modifiers as $modifier) {
                        $modifier_qty = ($modifier->quantity / $line->quantity) * $qty;
                        $new_modifiers[] = [
                            'product_id' => $modifier->product_id,
                            'variation_id' => $modifier->variation_id,
                            'quantity' => $modifier_qty,
                            'unit_price_before_discount' => $modifier->unit_price_before_discount,
                            'unit_price' => $modifier->unit_price,
                            'line_discount_type' => $modifier->line_discount_type,
                            'line_discount_amount' => $modifier->line_discount_amount,
                            'item_tax' => $modifier->item_tax,
                            'tax_id' => $modifier->tax_id,
                            'unit_price_inc_tax' => $modifier->unit_price_inc_tax,
                            'sub_unit_id' => $modifier->sub_unit_id,
                            'children_type' => 'modifier',
                        ];
                    }
                    
                    // Get the new parent line ID
                    $new_parent_line = $split_transaction->sell_lines()
                                        ->where('product_id', $line->product_id)
                                        ->where('variation_id', $line->variation_id)
                                        ->orderBy('id', 'desc')
                                        ->first();
                    
                    if ($new_parent_line) {
                        foreach ($new_modifiers as &$modifier) {
                            $modifier['parent_sell_line_id'] = $new_parent_line->id;
                        }
                        $split_transaction->sell_lines()->createMany($new_modifiers);
                    }
                }
            }

            // Update original transaction - remove selected lines or reduce quantity
            foreach ($selected_lines_data as $item) {
                $line = $item['line'];
                $qty = $item['quantity'];
                
                if ($qty == $line->quantity) {
                    // Remove entire line
                    $line->delete();
                } else {
                    // Reduce quantity
                    $line->quantity = $line->quantity - $qty;
                    
                    // Recalculate line totals
                    $line->save();
                }

                // Handle modifiers for original transaction
                $modifiers = TransactionSellLine::where('parent_sell_line_id', $line->id)
                                ->where('children_type', 'modifier')
                                ->get();
                
                foreach ($modifiers as $modifier) {
                    if ($qty == $line->quantity) {
                        $modifier->delete();
                    } else {
                        $modifier->quantity = ($modifier->quantity / $line->quantity) * ($line->quantity - $qty);
                        $modifier->save();
                    }
                }
            }

            // Recalculate original transaction totals
            $this->transactionUtil->updateSellTransactionTotals($original_transaction->id);

            // Update payment status for both transactions
            $this->transactionUtil->updatePaymentStatus($original_transaction->id);
            $this->transactionUtil->updatePaymentStatus($split_transaction->id);

            // Adjust stock for both transactions
            $this->adjustStockForSplitTransaction($original_transaction, $split_transaction, $split_items);

            // Copy EIS data if exists
            if (!empty($original_transaction->eisSale)) {
                $this->copyEisData($original_transaction, $split_transaction, $split_items);
            }

            // Return split result for the calling method to use
            return [
                'split_transaction' => $split_transaction,
                'original_transaction' => $original_transaction
            ];
        }

    /**
     * Adjust stock for split transaction
     */
    private function adjustStockForSplitTransaction($original_transaction, $split_transaction, $split_items)
    {
        $business_id = request()->session()->get('user.business_id');
        
        foreach ($split_items as $item) {
            $sell_line = TransactionSellLine::find($item['id']);
            if (!$sell_line) continue;
            
            // Check if product has stock enabled
            $product = Product::find($sell_line->product_id);
            if (!$product || $product->enable_stock != 1) {
                continue;
            }
            
            // Decrease stock from original transaction's location
            $this->productUtil->updateProductQuantity(
                $original_transaction->location_id,
                $sell_line->product_id,
                $sell_line->variation_id,
                -$item['quantity'],
                0,
                null,
                false
            );
            
            // Increase stock for new transaction's location
            $this->productUtil->updateProductQuantity(
                $split_transaction->location_id,
                $sell_line->product_id,
                $sell_line->variation_id,
                $item['quantity'],
                0,
                null,
                false
            );
        }
    }

    /**
     * Copy EIS data to split transaction
     */
    private function copyEisData($original_transaction, $split_transaction, $split_items)
    {
        $original_eis = $original_transaction->eisSale;
        if (!$original_eis) return;
        
        // Create new EIS record for split transaction
        $new_eis = $original_eis->replicate();
        $new_eis->transaction_id = $split_transaction->id;
        $new_eis->status = 'pending';
        $new_eis->created_at = \Carbon::now();
        $new_eis->updated_at = \Carbon::now();
        $new_eis->save();
        
        // Update original EIS status if needed
        $remaining_lines = TransactionSellLine::where('transaction_id', $original_transaction->id)
                            ->whereNull('parent_sell_line_id')
                            ->count();
        
        if ($remaining_lines == 0) {
            $original_eis->status = 'partially_split';
            $original_eis->save();
        }
    }

    //Clear all bills
    public function clearAllBills(){
        $business_id = request()->session()->get('user.business_id');
        $user_id = auth()->user()->id;

        try {
            DB::beginTransaction();
            
            // Get all unpaid invoices
            $unpaidInvoices = Transaction::where('business_id', $business_id)
                                        ->where('type', 'sell')
                                        ->where(function($query) {
                                            $query->where('payment_status', 'due')
                                                ->orWhere('payment_status', 'partial')
                                                ->orWhereNull('payment_status');
                                        })
                                        ->where('status', 'final')
                                        ->with(['payment_lines'])
                                        ->get();
            
            // Check if there are any unpaid invoices
            if ($unpaidInvoices->isEmpty()) {
                DB::rollBack();
                
                // Return JSON response for AJAX or redirect with message
                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'msg' => __('lang_v1.no_unpaid_bills_found')
                    ]);
                }
                
                return redirect()->back()
                    ->with('status', [
                        'success' => false,
                        'msg' => __('lang_v1.no_unpaid_bills_found')
                    ]);
            }
            
            $total_cleared = 0;
            $total_amount = 0;
            $cleared_invoices = [];
            
            foreach ($unpaidInvoices as $invoice) {
                $due_amount = $invoice->final_total - $this->transactionUtil->getTotalPaid($invoice->id);
                
                if ($due_amount > 0) {
                    // Create a payment entry for the due amount
                    $payment_data = [
                        'transaction_id' => $invoice->id,
                        'business_id' => $business_id,
                        'amount' => $due_amount,
                        'method' => 'cash',
                        'paid_on' => \Carbon::now(),
                        'created_by' => $user_id,
                        'payment_for' => $invoice->contact_id,
                        'note' => __('lang_v1.bulk_payment_cleared')
                    ];
                    
                    // Wrap payment creation in split-aware checkout context
                    // This ensures payment can be created on transactions with split history
                    \App\Services\Sales\SavedSaleWriteGuard::checkout(DB::connection(), $invoice->id, function () use ($payment_data) {
                        \App\TransactionPayment::create($payment_data);
                    });
                    
                    // Update payment status
                    $this->transactionUtil->updatePaymentStatus($invoice->id);
                    SaleSubmissionRule::initiate($invoice);
                    
                    $total_cleared++;
                    $total_amount += $due_amount;
                    $cleared_invoices[] = $invoice->invoice_no;
                }
            }
            
            DB::commit();

            // Check if any invoices were actually cleared
            if ($total_cleared == 0) {
                $msg = __('lang_v1.no_unpaid_bills_found');
                $success = false;
            } else {
                $msg = __('lang_v1.bills_cleared_successfully', [
                    'count' => $total_cleared, 
                    'amount' => $this->transactionUtil->num_f($total_amount, true)
                ]);
                $success = true;
            }
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => $success,
                    'msg' => $msg,
                    'cleared_invoices' => $cleared_invoices ?? [],
                    'total_cleared' => $total_cleared ?? 0,
                    'total_amount' => $this->transactionUtil->num_f($total_amount ?? 0, true)
                ]);
            }
            
            return redirect()->back()->with('status', [
                'success' => $success,
                'msg' => $msg
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            
            $output = [
                'success' => false,
                'msg' => trans('messages.something_went_wrong')
            ];
            
            if (request()->ajax()) {
                return response()->json($output);
            }
            
            return redirect()->back()->with(['status' => $output]);
        }
    }

    public function checkUnpaidBills()
    {
        $business_id = request()->session()->get('user.business_id');
        
        $unpaidInvoices = Transaction::where('business_id', $business_id)
                        ->where('type', 'sell')
                        ->where(function($query) {
                            $query->where('payment_status', 'due')
                                  ->orWhere('payment_status', 'partial')
                                  ->orWhereNull('payment_status');
                        })
                        ->where('status', 'final')
                        ->with(['payment_lines'])
                        ->get();
        
        $total_due = 0;
        foreach ($unpaidInvoices as $invoice) {
            $due_amount = $invoice->final_total - $invoice->payment_lines->sum('amount');
            if ($due_amount > 0) {
                $total_due += $due_amount;
            }
        }
    
        return response()->json([
            'count' => $unpaidInvoices->count(),
            'has_unpaid' => $unpaidInvoices->count() > 0,
            'total_due' => $this->transactionUtil->num_f($total_due, true)
        ]);
    }

    public function checkUnsubmittedBills()
    {
        $business_id = request()->session()->get('user.business_id');

        if (!$business_id) {
            return response()->json([
                'success' => false,
                'error' => 'Business ID not found in session'
            ], 400);
        }

        $count = Transaction::with('eisSale')
            ->where('business_id', $business_id)
            ->whereIn('location_id', $this->eisPermittedLocationIds((int) $business_id))
            ->where('type', 'sell')
            ->where('status', 'final')
            ->where('payment_status', 'paid')
            ->whereDoesntHave('eisSale', function($query) {
                $query->where('status', 'submitted');
            })
            ->count();

        return response()->json([
            'count' => $count,
            'has_unsubmitted' => $count > 0
        ]);
    }

    /**
     * Check EIS health before submitting bills
     */
    public function eisSubmitAllBills(Request $request, DirectSaleSubmissionService $submission)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            
            if (!$business_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Business ID not found in session'
                ], 400);
            }

            $transactionIds = Transaction::where('business_id', $business_id)
                ->whereIn('location_id', $this->eisPermittedLocationIds((int) $business_id))
                ->where('type', 'sell')
                ->where('status', 'final')
                ->where('payment_status', 'paid')
                ->whereDoesntHave('eisSale', fn ($query) => $query->where('status', 'submitted'))
                ->orderBy('transaction_date')
                ->pluck('id');

            $submitted = 0;
            $failed = [];
            foreach ($transactionIds as $transactionId) {
                $transaction = Transaction::where('business_id', $business_id)->find($transactionId);
                if (!$transaction) {
                    continue;
                }
                $result = $submission->submit($transaction);
                if ($result['status'] === 'submitted') {
                    $submitted++;
                } elseif ($result['status'] !== 'skipped') {
                    $failed[] = [
                        'transaction_id' => $transaction->id,
                        'invoice_no' => $transaction->invoice_no,
                        'error' => $result['message'] ?: 'EIS did not confirm submission.',
                    ];
                }
            }

            Log::info('Direct EIS bulk sale submission completed', [
                'business_id' => $business_id,
                'user_id' => auth()->id(),
                'requested' => $transactionIds->count(),
                'submitted' => $submitted,
                'failed' => count($failed),
            ]);

            return response()->json([
                'success' => $failed === [],
                'message' => $failed === []
                    ? "Finished direct EIS submission: {$submitted} bills submitted."
                    : "Finished direct EIS submission: {$submitted} submitted, ".count($failed).' failed.',
                'submitted' => $submitted,
                'failed' => $failed,
            ], $failed === [] ? 200 : 207);

        } catch (\Exception $e) {
            Log::error('Failed to submit bills', [
                'business_id' => $business_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to submit bills: ' . $e->getMessage()
            ], 500);
        }
    }

    private function eisPermittedLocationIds(int $businessId): array
    {
        $permitted = auth()->user()->permitted_locations();
        return BusinessLocation::where('business_id', $businessId)
            ->when($permitted !== 'all', fn ($query) => $query->whereIn('id', $permitted))
            ->pluck('id')->all();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('direct_sell.update') && ! auth()->user()->can('so.update')) {
            abort(403, 'Unauthorized action.');
        }

        //Check if the transaction can be edited or not.
        $edit_days = request()->session()->get('business.transaction_edit_days');
        if (! $this->transactionUtil->canBeEdited($id, $edit_days)) {
            return back()
                ->with('status', ['success' => 0,
                    'msg' => __('messages.transaction_edit_not_allowed', ['days' => $edit_days]), ]);
        }

        //Check if return exist then not allowed
        if ($this->transactionUtil->isReturnExist($id)) {
            return back()->with('status', ['success' => 0,
                'msg' => __('lang_v1.return_exist')]);
        }

        $business_id = request()->session()->get('user.business_id');

        $moduleUtil = new ModuleUtil();

        if (! $moduleUtil->isSubscribed($business_id)) {
            return $moduleUtil->expiredResponse();
        }

        $business_details = $this->businessUtil->getDetails($business_id);
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $transaction = Transaction::where('business_id', $business_id)
                            ->with(['price_group', 'types_of_service', 'media', 'media.uploaded_by_user'])
                            ->whereIn('type', ['sell', 'sales_order'])
                            ->findorfail($id);

        // If ZATCA module is installed and this transaction is successfully synced, prevent edit
       
        if ($moduleUtil->isModuleInstalled('ZatcaIntegrationKsa')) {
            if (!empty($transaction) && $transaction->zatca_status === 'success') {
                return back()->with('status', ['success' => 0,
                    'msg' => __('lang_v1.invoice_synced_to_zatca_cannot_be_edited')]);
            }
        }

        if ($transaction->type == 'sales_order' && ! auth()->user()->can('so.update')) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = $transaction->location_id;
        $location_printer_type = BusinessLocation::find($location_id)->receipt_printer_type;

        $sell_details = TransactionSellLine::join(
                            'products AS p',
                            'transaction_sell_lines.product_id',
                            '=',
                            'p.id'
                        )
                        ->join(
                            'variations AS variations',
                            'transaction_sell_lines.variation_id',
                            '=',
                            'variations.id'
                        )
                        ->join(
                            'product_variations AS pv',
                            'variations.product_variation_id',
                            '=',
                            'pv.id'
                        )
                        ->leftjoin('variation_location_details AS vld', function ($join) use ($location_id) {
                            $join->on('variations.id', '=', 'vld.variation_id')
                                ->where('vld.location_id', '=', $location_id);
                        })
                        ->leftjoin('units', 'units.id', '=', 'p.unit_id')
                        ->leftjoin('units as u', 'p.secondary_unit_id', '=', 'u.id')
                        ->where('transaction_sell_lines.transaction_id', $id)
                        ->with(['warranties', 'so_line'])
                        ->select(
                            DB::raw("IF(pv.is_dummy = 0, CONCAT(p.name, ' (', pv.name, ':',variations.name, ')'), p.name) AS product_name"),
                            'p.id as product_id',
                            'p.image as product_image',
                            'p.enable_stock',
                            'p.name as product_actual_name',
                            'p.type as product_type',
                            'pv.name as product_variation_name',
                            'pv.is_dummy as is_dummy',
                            'variations.name as variation_name',
                            'variations.sub_sku',
                            'p.barcode_type',
                            'p.enable_sr_no',
                            'variations.id as variation_id',
                            'units.short_name as unit',
                            'units.allow_decimal as unit_allow_decimal',
                            'u.short_name as second_unit',
                            'transaction_sell_lines.secondary_unit_quantity',
                            'transaction_sell_lines.tax_id as tax_id',
                            'transaction_sell_lines.item_tax as item_tax',
                            'transaction_sell_lines.unit_price as default_sell_price',
                            'transaction_sell_lines.unit_price_inc_tax as sell_price_inc_tax',
                            'transaction_sell_lines.unit_price_before_discount as unit_price_before_discount',
                            'transaction_sell_lines.id as transaction_sell_lines_id',
                            'transaction_sell_lines.id',
                            'transaction_sell_lines.quantity as quantity_ordered',
                            'transaction_sell_lines.sell_line_note as sell_line_note',
                            'transaction_sell_lines.parent_sell_line_id',
                            'transaction_sell_lines.lot_no_line_id',
                            'transaction_sell_lines.line_discount_type',
                            'transaction_sell_lines.line_discount_amount',
                            'transaction_sell_lines.res_service_staff_id',
                            'units.id as unit_id',
                            'transaction_sell_lines.sub_unit_id',
                            'transaction_sell_lines.so_line_id',
                            DB::raw('vld.qty_available + transaction_sell_lines.quantity AS qty_available')
                        )
                        ->get();

        if (! empty($sell_details)) {
            foreach ($sell_details as $key => $value) {

                $variation = Variation::with('media')->findOrFail($value->variation_id);
                $sell_details[$key]->media = $variation->media;

                //If modifier or combo sell line then unset
                if (! empty($sell_details[$key]->parent_sell_line_id)) {
                    unset($sell_details[$key]);
                } else {
                    if ($transaction->status != 'final') {
                        $actual_qty_avlbl = $value->qty_available - $value->quantity_ordered;
                        $sell_details[$key]->qty_available = $actual_qty_avlbl;
                        $value->qty_available = $actual_qty_avlbl;
                    }

                    $sell_details[$key]->formatted_qty_available = $this->productUtil->num_f($value->qty_available, false, null, true);
                    $lot_numbers = [];
                    if (request()->session()->get('business.enable_lot_number') == 1) {
                        $lot_number_obj = $this->transactionUtil->getLotNumbersFromVariation($value->variation_id, $business_id, $location_id);
                        foreach ($lot_number_obj as $lot_number) {
                            //If lot number is selected added ordered quantity to lot quantity available
                            if ($value->lot_no_line_id == $lot_number->purchase_line_id) {
                                $lot_number->qty_available += $value->quantity_ordered;
                            }

                            $lot_number->qty_formated = $this->transactionUtil->num_f($lot_number->qty_available);
                            $lot_numbers[] = $lot_number;
                        }
                    }
                    $sell_details[$key]->lot_numbers = $lot_numbers;

                    if (! empty($value->sub_unit_id)) {
                        $value = $this->productUtil->changeSellLineUnit($business_id, $value);
                        $sell_details[$key] = $value;
                    }

                    if ($this->transactionUtil->isModuleEnabled('modifiers')) {
                        //Add modifier details to sel line details
                        $sell_line_modifiers = TransactionSellLine::where('parent_sell_line_id', $sell_details[$key]->transaction_sell_lines_id)
                            ->where('children_type', 'modifier')
                            ->get();
                        $modifiers_ids = [];
                        if (count($sell_line_modifiers) > 0) {
                            $sell_details[$key]->modifiers = $sell_line_modifiers;
                            foreach ($sell_line_modifiers as $sell_line_modifier) {
                                $modifiers_ids[] = $sell_line_modifier->variation_id;
                            }
                        }
                        $sell_details[$key]->modifiers_ids = $modifiers_ids;

                        //add product modifier sets for edit
                        $this_product = Product::find($sell_details[$key]->product_id);
                        if (count($this_product->modifier_sets) > 0) {
                            $sell_details[$key]->product_ms = $this_product->modifier_sets;
                        }
                    }

                    //Get details of combo items
                    if ($sell_details[$key]->product_type == 'combo') {
                        $sell_line_combos = TransactionSellLine::where('parent_sell_line_id', $sell_details[$key]->transaction_sell_lines_id)
                            ->where('children_type', 'combo')
                            ->get()
                            ->toArray();
                        if (! empty($sell_line_combos)) {
                            $sell_details[$key]->combo_products = $sell_line_combos;
                        }

                        //calculate quantity available if combo product
                        $combo_variations = [];
                        foreach ($sell_line_combos as $combo_line) {
                            $combo_variations[] = [
                                'variation_id' => $combo_line['variation_id'],
                                'quantity' => $combo_line['quantity'] / $sell_details[$key]->quantity_ordered,
                                'unit_id' => null,
                            ];
                        }
                        $sell_details[$key]->qty_available =
                        $this->productUtil->calculateComboQuantity($location_id, $combo_variations);

                        if ($transaction->status == 'final') {
                            $sell_details[$key]->qty_available = $sell_details[$key]->qty_available + $sell_details[$key]->quantity_ordered;
                        }

                        $sell_details[$key]->formatted_qty_available = $this->productUtil->num_f($sell_details[$key]->qty_available, false, null, true);
                    }
                }
            }
        }

        $commsn_agnt_setting = $business_details->sales_cmsn_agnt;
        $commission_agent = [];
        if ($commsn_agnt_setting == 'user') {
            $commission_agent = User::forDropdown($business_id);
        } elseif ($commsn_agnt_setting == 'cmsn_agnt') {
            $commission_agent = User::saleCommissionAgentsDropdown($business_id);
        }

        $types = [];
        if (auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }
        $customer_groups = CustomerGroup::forDropdown($business_id);

        $transaction->transaction_date = $this->transactionUtil->format_date($transaction->transaction_date, true);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $waiters = [];
        if ($this->productUtil->isModuleEnabled('service_staff') && ! empty($pos_settings['inline_service_staff'])) {
            $waiters = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $invoice_schemes = [];
        $default_invoice_schemes = null;

        if ($transaction->status == 'draft') {
            $invoice_schemes = InvoiceScheme::forDropdown($business_id);
            $default_invoice_schemes = InvoiceScheme::getDefault($business_id);
        }

        $redeem_details = [];
        if (request()->session()->get('business.enable_rp') == 1) {
            $redeem_details = $this->transactionUtil->getRewardRedeemDetails($business_id, $transaction->contact_id);

            $redeem_details['points'] += $transaction->rp_redeemed;
            $redeem_details['points'] -= $transaction->rp_earned;
        }

        $edit_discount = auth()->user()->can('edit_product_discount_from_sale_screen');
        $edit_price = auth()->user()->can('edit_product_price_from_sale_screen');

        //Accounts
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $common_settings = session()->get('business.common_settings');
        $is_warranty_enabled = ! empty($common_settings['enable_product_warranty']) ? true : false;
        $warranties = $is_warranty_enabled ? Warranty::forDropdown($business_id) : [];

        $statuses = Transaction::sell_statuses();

        $is_order_request_enabled = false;
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        if ($is_crm) {
            $crm_settings = Business::where('id', auth()->user()->business_id)
                                ->value('crm_settings');
            $crm_settings = ! empty($crm_settings) ? json_decode($crm_settings, true) : [];

            if (! empty($crm_settings['enable_order_request'])) {
                $is_order_request_enabled = true;
            }
        }

        $sales_orders = [];
        if (! empty($pos_settings['enable_sales_order']) || $is_order_request_enabled) {
            $sales_orders = Transaction::where('business_id', $business_id)
                                ->where('type', 'sales_order')
                                ->where('contact_id', $transaction->contact_id)
                                ->where(function ($q) use ($transaction) {
                                    $q->where('status', '!=', 'completed');

                                    if (! empty($transaction->sales_order_ids)) {
                                        $q->orWhereIn('id', $transaction->sales_order_ids);
                                    }
                                })
                                ->pluck('invoice_no', 'id');
        }

        $payment_types = $this->transactionUtil->payment_types($transaction->location_id, false, $business_id);

        $payment_lines = $this->transactionUtil->getPaymentDetails($id);
        //If no payment lines found then add dummy payment line.
        if (empty($payment_lines)) {
            $payment_lines[] = $this->dummyPaymentLine;
        }

        $change_return = $this->dummyPaymentLine;

        $customer_due = $this->transactionUtil->getContactDue($transaction->contact_id, $transaction->business_id);

        $customer_due = $customer_due != 0 ? $this->transactionUtil->num_f($customer_due, true) : '';

        //Added check because $users is of no use if enable_contact_assign if false
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        return view('sell.edit')
            ->with(compact('business_details', 'taxes', 'sell_details', 'transaction', 'commission_agent', 'types', 'customer_groups', 'pos_settings', 'waiters', 'invoice_schemes', 'default_invoice_schemes', 'redeem_details', 'edit_discount', 'edit_price', 'shipping_statuses', 'warranties', 'statuses', 'sales_orders', 'payment_types', 'accounts', 'payment_lines', 'change_return', 'is_order_request_enabled', 'customer_due', 'users'));
    }

    /**
     * Display a listing sell drafts.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDrafts()
    {
        if (! auth()->user()->can('draft.view_all') && ! auth()->user()->can('draft.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        $sales_representative = User::forDropdown($business_id, false, false, true);

        return view('sale_pos.draft')
            ->with(compact('business_locations', 'customers', 'sales_representative'));
    }

    /**
     * Display a listing sell quotations.
     *
     * @return \Illuminate\Http\Response
     */
    public function getQuotations()
    {
        if (! auth()->user()->can('quotation.view_all') && ! auth()->user()->can('quotation.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        $sales_representative = User::forDropdown($business_id, false, false, true);

        return view('sale_pos.quotations')
                ->with(compact('business_locations', 'customers', 'sales_representative'));
    }

    /**
     * Send the datatable response for draft or quotations.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDraftDatables()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $is_quotation = request()->input('is_quotation', 0);

            $is_woocommerce = $this->moduleUtil->isModuleInstalled('Woocommerce');

            $sells = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
                ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
                ->join(
                    'business_locations AS bl',
                    'transactions.location_id',
                    '=',
                    'bl.id'
                )
                ->leftJoin('transaction_sell_lines as tsl', function ($join) {
                    $join->on('transactions.id', '=', 'tsl.transaction_id')
                        ->whereNull('tsl.parent_sell_line_id');
                })
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'sell')
                ->where('transactions.status', 'draft')
                ->select(
                    'transactions.id',
                    'transaction_date',
                    'invoice_no',
                    'contacts.name',
                    'contacts.mobile',
                    'contacts.supplier_business_name',
                    'bl.name as business_location',
                    'is_direct_sale',
                    'sub_status',
                    DB::raw('COUNT( DISTINCT tsl.id) as total_items'),
                    DB::raw('SUM(tsl.quantity) as total_quantity'),
                    DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as added_by"),
                    'transactions.is_export'
                );

            if ($is_quotation == 1) {
                $sells->where('transactions.sub_status', 'quotation');

                if (! auth()->user()->can('quotation.view_all') && auth()->user()->can('quotation.view_own')) {
                    $sells->where('transactions.created_by', request()->session()->get('user.id'));
                }
            } else {
                if (! auth()->user()->can('draft.view_all') && auth()->user()->can('draft.view_own')) {
                    $sells->where('transactions.created_by', request()->session()->get('user.id'));
                }
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $sells->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $sells->whereDate('transaction_date', '>=', $start)
                            ->whereDate('transaction_date', '<=', $end);
            }

            if (request()->has('location_id')) {
                $location_id = request()->get('location_id');
                if (! empty($location_id)) {
                    $sells->where('transactions.location_id', $location_id);
                }
            }

            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (! empty($created_by)) {
                    $sells->where('transactions.created_by', $created_by);
                }
            }

            if (! empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $sells->where('contacts.id', $customer_id);
            }

            if ($is_woocommerce) {
                $sells->addSelect('transactions.woocommerce_order_id');
            }

            $sells->groupBy('transactions.id');

            return Datatables::of($sells)
                 ->addColumn(
                    'action', function ($row) {
                        $html = '<div class="btn-group">
                                <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max dropdown-toggle" 
                                    data-toggle="dropdown" aria-expanded="false">'.
                                    __('messages.actions').
                                    '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                    </span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right" role="menu">
                                    <li>
                                    <a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal">
                                        <i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'
                                    </a>
                                    </li>';

                        if (auth()->user()->can('draft.update') || auth()->user()->can('quotation.update')) {
                            if ($row->is_direct_sale == 1) {
                                $html .= '<li>
                                            <a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]).'">
                                                <i class="fas fa-edit"></i>'.__('messages.edit').'
                                            </a>
                                        </li>';
                            } else {
                                $html .= '<li>
                                            <a target="_blank" href="'.action([\App\Http\Controllers\SellPosController::class, 'edit'], [$row->id]).'">
                                                <i class="fas fa-edit"></i>'.__('messages.edit').'
                                            </a>
                                        </li>';
                            }
                        }

                        $html .= '<li>
                                    <a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'"><i class="fas fa-print" aria-hidden="true"></i>'.__('messages.print').'</a>
                                </li>';

                        if (config('constants.enable_download_pdf')) {
                            $sub_status = $row->sub_status == 'proforma' ? 'proforma' : '';
                            $html .= '<li>
                                        <a href="'.route('quotation.downloadPdf', ['id' => $row->id, 'sub_status' => $sub_status]).'" target="_blank">
                                            <i class="fas fa-print" aria-hidden="true"></i>'.__('lang_v1.download_pdf').'
                                        </a>
                                    </li>';
                        }

                        if ((auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access')) && config('constants.enable_convert_draft_to_invoice')) {
                            $html .= '<li>
                                        <a href="'.action([\App\Http\Controllers\SellPosController::class, 'convertToInvoice'], [$row->id]).'" class="convert-draft"><i class="fas fa-sync-alt"></i>'.__('lang_v1.convert_to_invoice').'</a>
                                    </li>';
                        }

                        if ($row->sub_status != 'proforma') {
                            $html .= '<li>
                                        <a href="'.action([\App\Http\Controllers\SellPosController::class, 'convertToProforma'], [$row->id]).'" class="convert-to-proforma"><i class="fas fa-sync-alt"></i>'.__('lang_v1.convert_to_proforma').'</a>
                                    </li>';
                        }

                        if (auth()->user()->can('draft.delete') || auth()->user()->can('quotation.delete')) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\SellPosController::class, 'destroy'], [$row->id]).'" class="delete-sale"><i class="fas fa-trash"></i>'.__('messages.delete').'</a>
                                </li>';
                        }

                        if ($row->sub_status == 'quotation') {
                            $html .= '<li>
                                        <a href="'.action([\App\Http\Controllers\SellPosController::class, 'copyQuotation'],[$row->id]).'" 
                                        class="copy_quotation"><i class="fas fa-copy"></i>'.
                                        __("lang_v1.copy_quotation").'</a>
                                    </li>
                                    <li>
                                        <a href="#" data-href="'.action("\App\Http\Controllers\NotificationController@getTemplate", ["transaction_id" => $row->id,"template_for" => "new_quotation"]).'" class="btn-modal" data-container=".view_modal"><i class="fa fa-envelope" aria-hidden="true"></i>' . __("lang_v1.new_quotation_notification") . '
                                        </a>
                                    </li>';

                            $html .= '<li>
                                        <a href="'.action("\App\Http\Controllers\SellPosController@showInvoiceUrl", [$row->id]).'" class="view_invoice_url"><i class="fas fa-eye"></i>'.__("lang_v1.view_quote_url").'</a>
                                    </li>';
                        }

                        $html .= '</ul></div>';

                        return $html;
                    })
                ->removeColumn('id')
                ->editColumn('invoice_no', function ($row) {
                    $invoice_no = $row->invoice_no;
                    if (! empty($row->woocommerce_order_id)) {
                        $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="'.__('lang_v1.synced_from_woocommerce').'"></i>';
                    }

                    if ($row->sub_status == 'proforma') {
                        $invoice_no .= '<br><span class="label bg-gray">'.__('lang_v1.proforma_invoice').'</span>';
                    }

                    if (! empty($row->is_export)) {
                        $invoice_no .= '</br><small class="label label-default no-print" title="'.__('lang_v1.export').'">'.__('lang_v1.export').'</small>';
                    }

                    return $invoice_no;
                })
                ->editColumn('transaction_date', '{{@format_date($transaction_date)}}')
                ->editColumn('total_items', '{{@format_quantity($total_items)}}')
                ->editColumn('total_quantity', '{{@format_quantity($total_quantity)}}')
                ->addColumn('conatct_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br>@endif {{$name}}')
                ->filterColumn('conatct_name', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('contacts.name', 'like', "%{$keyword}%")
                        ->orWhere('contacts.supplier_business_name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('added_by', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->setRowAttr([
                    'data-href' => function ($row) {
                        if (auth()->user()->can('sell.view')) {
                            return  action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]);
                        } else {
                            return '';
                        }
                    }, ])
                ->rawColumns(['action', 'invoice_no', 'transaction_date', 'conatct_name'])
                ->make(true);
        }
    }

    /**
     * Creates copy of the requested sale.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function duplicateSell($id)
    {
        if (! auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $transaction = Transaction::where('business_id', $business_id)
                            ->where('type', 'sell')
                            ->findorfail($id);
            $duplicate_transaction_data = [];
            foreach ($transaction->toArray() as $key => $value) {
                if (! in_array($key, ['id', 'created_at', 'updated_at'])) {
                    $duplicate_transaction_data[$key] = $value;
                }
            }
            $duplicate_transaction_data['status'] = 'draft';
            $duplicate_transaction_data['payment_status'] = null;
            $duplicate_transaction_data['transaction_date'] = \Carbon::now();
            $duplicate_transaction_data['created_by'] = $user_id;
            $duplicate_transaction_data['invoice_token'] = null;

            DB::beginTransaction();
            $duplicate_transaction_data['invoice_no'] = $this->transactionUtil->getInvoiceNumber($business_id, 'draft', $duplicate_transaction_data['location_id']);

            //Create duplicate transaction
            $duplicate_transaction = Transaction::create($duplicate_transaction_data);

            //Create duplicate transaction sell lines
            $duplicate_sell_lines_data = [];

            foreach ($transaction->sell_lines as $sell_line) {
                $new_sell_line = [];
                foreach ($sell_line->toArray() as $key => $value) {
                    if (! in_array($key, ['id', 'transaction_id', 'created_at', 'updated_at', 'lot_no_line_id'])) {
                        $new_sell_line[$key] = $value;
                    }
                }

                $duplicate_sell_lines_data[] = $new_sell_line;
            }

            $duplicate_transaction->sell_lines()->createMany($duplicate_sell_lines_data);

            DB::commit();

            $output = ['success' => 0,
                'msg' => trans('lang_v1.duplicate_sell_created_successfully'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        if (! empty($duplicate_transaction)) {
            if ($duplicate_transaction->is_direct_sale == 1) {
                return redirect()->action([\App\Http\Controllers\SellController::class, 'edit'], [$duplicate_transaction->id])->with(['status', $output]);
            } else {
                return redirect()->action([\App\Http\Controllers\SellPosController::class, 'edit'], [$duplicate_transaction->id])->with(['status', $output]);
            }
        } else {
            abort(404, 'Not Found.');
        }
    }

    /**
     * Shows modal to edit shipping details.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editShipping($id)
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $transaction = Transaction::where('business_id', $business_id)
                                ->with(['media', 'media.uploaded_by_user'])
                                ->findorfail($id);

        $users = User::forDropdown($business_id, false, false, false);

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $activities = Activity::forSubject($transaction)
           ->with(['causer', 'subject'])
           ->where('activity_log.description', 'shipping_edited')
           ->latest()
           ->get();

        return view('sell.partials.edit_shipping')
               ->with(compact('transaction', 'shipping_statuses', 'activities', 'users'));
    }

    /**
     * Update shipping.
     *
     * @param  Request  $request, int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateShipping(Request $request, $id)
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only([
                'shipping_details', 'shipping_address',
                'shipping_status', 'delivered_to', 'delivery_person', 'shipping_custom_field_1', 'shipping_custom_field_2', 'shipping_custom_field_3', 'shipping_custom_field_4', 'shipping_custom_field_5',
            ]);


            $business_id = $request->session()->get('user.business_id');

            $transaction = Transaction::where('business_id', $business_id)
                                ->findOrFail($id);

            $transaction_before = $transaction->replicate();

            $transaction->update($input);

            $activity_property = ['update_note' => $request->input('shipping_note', '')];
            $this->transactionUtil->activityLog($transaction, 'shipping_edited', $transaction_before, $activity_property);

            $output = ['success' => 1,
                'msg' => trans('lang_v1.updated_success'),
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
     * Display list of shipments.
     *
     * @return \Illuminate\Http\Response
     */
    public function shipments()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        $sales_representative = User::forDropdown($business_id, false, false, true);

        $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');

        //Service staff filter
        $service_staffs = null;
        if ($this->productUtil->isModuleEnabled('service_staff')) {
            $service_staffs = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $delevery_person = User::forDropdown($business_id, false, false, true);

        return view('sell.shipments')->with(compact('shipping_statuses'))
                ->with(compact('business_locations', 'customers', 'sales_representative', 'is_service_staff_enabled', 'service_staffs', 'delevery_person'));
    }

    public function viewMedia($model_id)
    {
        if (request()->ajax()) {
            $model_type = request()->input('model_type');
            $business_id = request()->session()->get('user.business_id');

            $query = Media::where('business_id', $business_id)
                        ->where('model_id', $model_id)
                        ->where('model_type', $model_type);

            $title = __('lang_v1.attachments');
            if (! empty(request()->input('model_media_type'))) {
                $query->where('model_media_type', request()->input('model_media_type'));
                $title = __('lang_v1.shipping_documents');
            }

            $medias = $query->get();

            return view('sell.view_media')->with(compact('medias', 'title'));
        }
    }

    public function resetMapping()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        Artisan::call('pos:mapPurchaseSell');

        echo 'Mapping reset success';
        exit;
    }

    /**
     * Apply all filters for Sell list & count queries.
     *
     * $for_count=true omits HAVING that depends on selected aliases.
     */
    protected function applySellListFilters($query, $business_id, $sale_type, $for_count = false)
    {
        // Exclude project invoices from sell list
        if ($sale_type == 'sell') {
            $query->where(function ($q) {
                $q->where('transactions.sub_type', '!=', 'project_invoice')
                  ->orWhereNull('transactions.sub_type');
            });
        }

        // Location permissions
        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $query->whereIn('transactions.location_id', $permitted_locations);
        }

        // Created by filter
        if (request()->has('created_by')) {
            $created_by = request()->get('created_by');
            if (! empty($created_by)) {
                $query->where('transactions.created_by', $created_by);
            }
        }

        // Ownership / commission permissions
        if (! auth()->user()->can('direct_sell.view')) {
            $query->where(function ($q) {
                if (auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                    $q->where('transactions.created_by', request()->session()->get('user.id'));
                }

                if (auth()->user()->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                    $q->orWhere('transactions.commission_agent', request()->session()->get('user.id'));
                }
            });
        }

        // Shipment filters
        $only_shipments = request()->only_shipments == 'true' ? true : false;
        if ($only_shipments) {
            $query->whereNotNull('transactions.shipping_status');

            if (auth()->user()->hasAnyPermission(['access_pending_shipments_only'])) {
                $query->where('transactions.shipping_status', '!=', 'delivered');
            }
        }

        // Payment status visibility restrictions
        $is_admin = $this->businessUtil->is_admin(auth()->user());
        if (! $is_admin && ! $only_shipments && $sale_type != 'sales_order') {
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
                    $query->OverDue();
                }
            } else {
                if (auth()->user()->can('view_overdue_sells_only')) {
                    $query->where(function ($q) use ($payment_status_arr) {
                        $q->whereIn('transactions.payment_status', $payment_status_arr)
                          ->orWhere(function ($qr) {
                              $qr->OverDue();
                          });
                    });
                } else {
                    $query->whereIn('transactions.payment_status', $payment_status_arr);
                }
            }
        }

        // Explicit payment status filter
        if (! empty(request()->input('payment_status')) && request()->input('payment_status') != 'overdue') {
            $query->where('transactions.payment_status', request()->input('payment_status'));
        } elseif (request()->input('payment_status') == 'overdue') {
            $query->whereIn('transactions.payment_status', ['due', 'partial'])
                  ->whereNotNull('transactions.pay_term_number')
                  ->whereNotNull('transactions.pay_term_type')
                  ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
        }

        // Location filter
        if (request()->has('location_id')) {
            $location_id = request()->get('location_id');
            if (! empty($location_id)) {
                $query->where('transactions.location_id', $location_id);
            }
        }

        // Rewards-only filter
        if (!empty(request()->input('rewards_only')) && request()->input('rewards_only') == true) {
            $query->where(function ($q) {
                $q->whereNotNull('transactions.rp_earned')
                  ->orWhere('transactions.rp_redeemed', '>', 0);
            });
        }

        // Customer filter
        if (! empty(request()->customer_id)) {
            $query->where('contacts.id', request()->customer_id);
        }

        // Date range
        if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date . ' 00:00:00';
                $end = request()->end_date . ' 23:59:59';
                $query->where('transactions.transaction_date', '>=', $start)
                ->where('transactions.transaction_date', '<=', $end);
        }

        // Direct sale flag
        if (request()->has('is_direct_sale')) {
            $is_direct_sale = request()->is_direct_sale;
            if ($is_direct_sale == 0) {
                $query->where('transactions.is_direct_sale', 0);
                $query->whereNull('transactions.sub_type');
            }
        }

        // Commission agent
        if (request()->has('commission_agent')) {
            $commission_agent = request()->get('commission_agent');
            if (! empty($commission_agent)) {
                $query->where('transactions.commission_agent', $commission_agent);
            }
        }

        // Source
        if (! empty(request()->input('source'))) {
            if (request()->input('source') == 'woocommerce') {
                $query->whereNotNull('transactions.woocommerce_order_id');
            } else {
                $query->where('transactions.source', request()->input('source'));
            }
        }

        // CRM order request
        if ($this->moduleUtil->isModuleInstalled('Crm') && request()->has('crm_is_order_request')) {
            $query->where('transactions.crm_is_order_request', 1);
        }

        // Subscriptions only
        if (request()->only_subscriptions) {
            $query->where(function ($q) {
                $q->whereNotNull('transactions.recur_parent_id')
                  ->orWhere('transactions.is_recurring', 1);
            });
        }

        // Service staff report / waiter
        if (! empty(request()->list_for) && request()->list_for == 'service_staff_report') {
            $query->whereNotNull('transactions.res_waiter_id');
        }
        if (! empty(request()->res_waiter_id)) {
            $query->where('transactions.res_waiter_id', request()->res_waiter_id);
        }

        // Sub type / created_by / status
        if (! empty(request()->input('sub_type'))) {
            $query->where('transactions.sub_type', request()->input('sub_type'));
        }
        if (! empty(request()->input('created_by'))) {
            $query->where('transactions.created_by', request()->input('created_by'));
        }
        if (! empty(request()->input('status'))) {
            $query->where('transactions.status', request()->input('status'));
        }

        $voidFilter = request()->input('eis_void_status');
        if (in_array($voidFilter, ['void', 'not_void'], true)) {
            if (!DB::getSchemaBuilder()->hasTable('eis_sales') ||
                !DB::getSchemaBuilder()->hasColumn('eis_sales', 'void_status')) {
                if ($voidFilter === 'void') {
                    $query->whereRaw('1 = 0');
                }
            } elseif ($voidFilter === 'void') {
                $query->whereExists(function ($subquery) use ($business_id) {
                    $subquery->select(DB::raw(1))->from('eis_sales as void_sales')
                        ->whereColumn('void_sales.transaction_id', 'transactions.id')
                        ->where('void_sales.business_id', $business_id)
                        ->where('void_sales.void_status', 'void');
                });
            } else {
                $query->whereNotExists(function ($subquery) use ($business_id) {
                    $subquery->select(DB::raw(1))->from('eis_sales as void_sales')
                        ->whereColumn('void_sales.transaction_id', 'transactions.id')
                        ->where('void_sales.business_id', $business_id)
                        ->where('void_sales.void_status', 'void');
                });
            }
        }

        // Commission agent filters
        if (! empty(request()->input('sales_cmsn_agnt'))) {
            $query->where('transactions.commission_agent', request()->input('sales_cmsn_agnt'));
        }
        if (! empty(request()->input('service_staffs'))) {
            $query->where('transactions.res_waiter_id', request()->input('service_staffs'));
        }

        // Pending shipments only
        $only_pending_shipments = request()->only_pending_shipments == 'true' ? true : false;
        if ($only_pending_shipments) {
            $query->where('transactions.shipping_status', '!=', 'delivered')
                  ->whereNotNull('transactions.shipping_status');
        }

        // Shipping status filter
        if (! empty(request()->input('shipping_status'))) {
            $query->where('transactions.shipping_status', request()->input('shipping_status'));
        }

        // Dashboard sales order
        if (! empty(request()->input('for_dashboard_sales_order'))) {
            if ($for_count) {
                $query->whereIn('transactions.status', ['partial', 'ordered']);
            } else {
                $query->whereIn('transactions.status', ['partial', 'ordered'])
                      ->orHavingRaw('so_qty_remaining > 0');
            }
        }

        // Sales order view restrictions
        if ($sale_type == 'sales_order') {
            if (! auth()->user()->can('so.view_all') && auth()->user()->can('so.view_own')) {
                $query->where('transactions.created_by', request()->session()->get('user.id'));
            }
        }

        // Delivery person
        if (! empty(request()->input('delivery_person'))) {
            $query->where('transactions.delivery_person', request()->input('delivery_person'));
        }

        // ZATCA specific
        $is_zatca = !empty(request()->input('is_zatca')) ? request()->input('is_zatca') : 0;
        if ($is_zatca) {
            if (! empty(request()->input('zatca_status'))) {
                if (request()->input('zatca_status') == 'pending') {
                    $query->whereNull('transactions.zatca_status');
                } else {
                    $query->where('transactions.zatca_status', request()->input('zatca_status'));
                }
            }

            $query->whereNotNull('bl.zatca_sync_from_datetime');
            $query->whereRaw('transactions.transaction_date >= bl.zatca_sync_from_datetime');
        }

        // Payment method filter
        if (!empty(request()->input('payment_method'))) {
            $query->whereHas('payment_lines', function ($q) {
                $q->where('method', request()->input('payment_method'));
            });
        }

        return $query;
    }

    /**
     * Checks if invoice number exists
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkInvoiceNumber(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $invoice_no = $request->input('invoice_no');
        $transaction_id = $request->input('transaction_id');

        $query = Transaction::where('business_id', $business_id)
                        ->where('invoice_no', $invoice_no);
        
        if (!empty($transaction_id)) {
            $query->where('id', '!=', $transaction_id);
        }
        
        $count = $query->count();
        
        if ($count == 0) {
            echo 'true';
            exit;
        } else {
            echo 'false';
            exit;
        }
    }
}
