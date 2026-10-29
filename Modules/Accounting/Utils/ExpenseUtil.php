<?php

namespace Modules\Accounting\Utils;

use Modules\Accounting\Entities\BusinessLocation;
use Modules\Accounting\Entities\Contact;
use App\ExpenseCategory;
use Modules\Accounting\Entities\Transaction;
use Modules\Accounting\Entities\User;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class ExpenseUtil
{

    /**
     * Constructor
     *
     * @param TransactionUtil $transactionUtil
     * @return void
     */
    public function __construct(TransactionUtil $transactionUtil, ModuleUtil $moduleUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
    }

    public function getData()
    {
        $business_id = request()->session()->get('user.business_id');

        $categories = ExpenseCategory::where('business_id', $business_id)->pluck('name', 'id');

        $users = User::forDropdown($business_id, false, true, true);

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        $contacts = Contact::contactDropdown($business_id, false, false);

        return compact('categories', 'business_locations', 'users', 'contacts');
    }

    public function getDataTables()
    {
        $business_id = request()->session()->get('user.business_id');

        $expenses = Transaction::leftJoin('expense_categories AS ec', 'transactions.expense_category_id', '=', 'ec.id')
            ->join(
                'business_locations AS bl',
                'transactions.location_id',
                '=',
                'bl.id'
            )
            ->leftJoin('tax_rates as tr', 'transactions.tax_id', '=', 'tr.id')
            ->leftJoin('users AS U', 'transactions.expense_for', '=', 'U.id')
            ->leftJoin('users AS usr', 'transactions.created_by', '=', 'usr.id')
            ->leftJoin('contacts AS c', 'transactions.contact_id', '=', 'c.id')
            ->leftJoin(
                'transaction_payments AS TP',
                'transactions.id',
                '=',
                'TP.transaction_id'
            )
            ->where('transactions.business_id', $business_id)
            ->whereIn('transactions.type', ['expense', 'expense_refund'])
            ->select(
                'transactions.id',
                'transactions.document',
                'transaction_date',
                'ref_no',
                'ec.name as category',
                'payment_status',
                'additional_notes',
                'final_total',
                'transactions.is_recurring',
                'transactions.recur_interval',
                'transactions.recur_interval_type',
                'transactions.recur_repetitions',
                'transactions.subscription_repeat_on',
                'bl.name as location_name',
                DB::raw("CONCAT(COALESCE(U.surname, ''),' ',COALESCE(U.first_name, ''),' ',COALESCE(U.last_name,'')) as expense_for"),
                DB::raw("CONCAT(tr.name ,' (', tr.amount ,' )') as tax"),
                DB::raw('SUM(TP.amount) as amount_paid'),
                DB::raw("CONCAT(COALESCE(usr.surname, ''),' ',COALESCE(usr.first_name, ''),' ',COALESCE(usr.last_name,'')) as added_by"),
                'transactions.recur_parent_id',
                'c.name as contact_name',
                'transactions.type'
            )
            ->with(['recurring_parent'])
            ->groupBy('transactions.id');

        //Add condition for expense for,used in sales representative expense report & list of expense
        if (request()->has('expense_for')) {
            $expense_for = request()->get('expense_for');
            if (!empty($expense_for)) {
                $expenses->where('transactions.expense_for', $expense_for);
            }
        }

        if (request()->has('contact_id')) {
            $contact_id = request()->get('contact_id');
            if (!empty($contact_id)) {
                $expenses->where('transactions.contact_id', $contact_id);
            }
        }

        //Add condition for location,used in sales representative expense report & list of expense
        if (request()->has('location_id')) {
            $location_id = request()->get('location_id');
            if (!empty($location_id)) {
                $expenses->where('transactions.location_id', $location_id);
            }
        }

        //Add condition for expense category, used in list of expense,
        if (request()->has('expense_category_id')) {
            $expense_category_id = request()->get('expense_category_id');
            if (!empty($expense_category_id)) {
                $expenses->where('transactions.expense_category_id', $expense_category_id);
            }
        }

        //Add condition for start and end date filter, uses in sales representative expense report & list of expense
        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start = request()->start_date;
            $end =  request()->end_date;
            $expenses->whereDate('transaction_date', '>=', $start)
                ->whereDate('transaction_date', '<=', $end);
        }

        //Add condition for expense category, used in list of expense,
        if (request()->has('expense_category_id')) {
            $expense_category_id = request()->get('expense_category_id');
            if (!empty($expense_category_id)) {
                $expenses->where('transactions.expense_category_id', $expense_category_id);
            }
        }

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $expenses->whereIn('transactions.location_id', $permitted_locations);
        }

        //Add condition for payment status for the list of expense
        if (request()->has('payment_status')) {
            $payment_status = request()->get('payment_status');
            if (!empty($payment_status)) {
                $expenses->where('transactions.payment_status', $payment_status);
            }
        }

        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);
        if (!$is_admin && !auth()->user()->can('all_expense.access')) {
            $user_id = auth()->user()->id;
            $expenses->where(function ($query) use ($user_id) {
                $query->where('transactions.created_by', $user_id)
                    ->orWhere('transactions.expense_for', $user_id);
            });
        }

        return DataTables::of($expenses)
            ->addColumn(
                'action',
                '<div class="btn-group">
                        <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                            data-toggle="dropdown" aria-expanded="false"> @lang("messages.actions")<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                </span>
                        </button>
                    <ul class="dropdown-menu dropdown-menu-left" role="menu">
                    @if(auth()->user()->can("expense.edit"))
                        <li><a href="{{action(\'ExpenseController@edit\', [$id])}}"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</a></li>
                    @endif
                    @if($document)
                        <li><a href="{{ url(\'uploads/documents/\' . $document)}}" 
                        download=""><i class="fa fa-download" aria-hidden="true"></i> @lang("purchase.download_document")</a></li>
                        @if(isFileImage($document))
                            <li><a href="#" data-href="{{ url(\'uploads/documents/\' . $document)}}" class="view_uploaded_document"><i class="fas fa-file-image" aria-hidden="true"></i>@lang("lang_v1.view_document")</a></li>
                        @endif
                    @endif
                    @if(auth()->user()->can("expense.delete"))
                        <li>
                        <a href="#" data-href="{{action(\'ExpenseController@destroy\', [$id])}}" class="delete_expense"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</a></li>
                    @endif
                    <li class="divider"></li> 
                    @if($payment_status != "paid")
                        <li><a href="{{action("TransactionPaymentController@addPayment", [$id])}}" class="add_payment_modal"><i class="fas fa-money-bill-alt" aria-hidden="true"></i> @lang("purchase.add_payment")</a></li>
                    @endif
                    <li><a href="{{action("TransactionPaymentController@show", [$id])}}" class="view_payment_modal"><i class="fas fa-money-bill-alt" aria-hidden="true" ></i> @lang("purchase.view_payments")</a></li>
                    </ul></div>'
            )
            ->removeColumn('id')
            ->editColumn(
                'final_total',
                '<span class="display_currency final-total" data-currency_symbol="true" data-orig-value="@if($type=="expense_refund"){{-1 * $final_total}}@else{{$final_total}}@endif">@if($type=="expense_refund") - @endif @format_currency($final_total)</span>'
            )
            ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
            ->editColumn(
                'payment_status',
                '<a href="{{ action("TransactionPaymentController@show", [$id])}}" class="view_payment_modal payment-status" data-orig-value="{{$payment_status}}" data-status-name="{{__(\'lang_v1.\' . $payment_status)}}"><span class="label @payment_status($payment_status)">{{__(\'lang_v1.\' . $payment_status)}}
                        </span></a>'
            )
            ->addColumn('payment_due', function ($row) {
                $due = $row->final_total - $row->amount_paid;

                if ($row->type == 'expense_refund') {
                    $due = -1 * $due;
                }
                return '<span class="display_currency payment_due" data-currency_symbol="true" data-orig-value="' . $due . '">' . $this->transactionUtil->num_f($due, true) . '</span>';
            })
            ->addColumn('recur_details', function ($row) {
                $details = '<small>';
                if ($row->is_recurring == 1) {
                    $type = $row->recur_interval == 1 ? Str::singular(__('lang_v1.' . $row->recur_interval_type)) : __('lang_v1.' . $row->recur_interval_type);
                    $recur_interval = $row->recur_interval . $type;

                    $details .= __('lang_v1.recur_interval') . ': ' . $recur_interval;
                    if (!empty($row->recur_repetitions)) {
                        $details .= ', ' . __('lang_v1.no_of_repetitions') . ': ' . $row->recur_repetitions;
                    }
                    if ($row->recur_interval_type == 'months' && !empty($row->subscription_repeat_on)) {
                        $details .= '<br><small class="text-muted">' .
                            __('lang_v1.repeat_on') . ': ' . str_ordinal($row->subscription_repeat_on);
                    }
                } elseif (!empty($row->recur_parent_id)) {
                    $details .= __('lang_v1.recurred_from') . ': ' . $row->recurring_parent->ref_no;
                }
                $details .= '</small>';
                return $details;
            })
            ->editColumn('ref_no', function ($row) {
                $ref_no = $row->ref_no;
                if (!empty($row->is_recurring)) {
                    $ref_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="' . __('lang_v1.recurring_expense') . '"><i class="fas fa-recycle"></i></small>';
                }

                if (!empty($row->recur_parent_id)) {
                    $ref_no .= ' &nbsp;<small class="label bg-info label-round no-print" title="' . __('lang_v1.generated_recurring_expense') . '"><i class="fas fa-recycle"></i></small>';
                }

                if ($row->type == 'expense_refund') {
                    $ref_no .= ' &nbsp;<small class="label bg-gray">' . __('lang_v1.refund') . '</small>';
                }

                return $ref_no;
            })
            ->rawColumns(['final_total', 'action', 'payment_status', 'payment_due', 'ref_no', 'recur_details'])
            ->make(true);
    }
    public function getTransactionsDataTable()
    {
        $business_id = request()->session()->get('user.business_id');

        $expenses = Transaction::with('journal_entry')
            ->leftJoin('expense_categories AS ec', 'transactions.expense_category_id', '=', 'ec.id')
            ->join(
                'business_locations AS bl',
                'transactions.location_id',
                '=',
                'bl.id'
            )
            ->leftJoin('tax_rates as tr', 'transactions.tax_id', '=', 'tr.id')
            ->leftJoin('users AS U', 'transactions.expense_for', '=', 'U.id')
            ->leftJoin('users AS usr', 'transactions.created_by', '=', 'usr.id')
            ->leftJoin('contacts AS c', 'transactions.contact_id', '=', 'c.id')
            ->leftJoin(
                'transaction_payments AS TP',
                'transactions.id',
                '=',
                'TP.transaction_id'
            )
            ->where('transactions.business_id', $business_id)
            ->whereIn('transactions.type', ['expense', 'expense_refund'])
            ->select(
                'transactions.id',
                'transactions.journal_entry_id',
                'transactions.document',
                'transaction_date',
                'ref_no',
                'ec.name as category',
                'payment_status',
                'additional_notes',
                'final_total',
                'transactions.is_recurring',
                'transactions.recur_interval',
                'transactions.recur_interval_type',
                'transactions.recur_repetitions',
                'transactions.subscription_repeat_on',
                'bl.name as location_name',
                DB::raw("CONCAT(COALESCE(U.surname, ''),' ',COALESCE(U.first_name, ''),' ',COALESCE(U.last_name,'')) as expense_for"),
                DB::raw("CONCAT(tr.name ,' (', tr.amount ,' )') as tax"),
                DB::raw('SUM(TP.amount) as amount_paid'),
                DB::raw("CONCAT(COALESCE(usr.surname, ''),' ',COALESCE(usr.first_name, ''),' ',COALESCE(usr.last_name,'')) as added_by"),
                'transactions.recur_parent_id',
                'c.name as contact_name',
                'transactions.type'
            )
            ->with(['recurring_parent'])
            ->groupBy('transactions.id');

        //Add condition for expense for,used in sales representative expense report & list of expense
        if (request()->has('expense_for')) {
            $expense_for = request()->get('expense_for');
            if (!empty($expense_for)) {
                $expenses->where('transactions.expense_for', $expense_for);
            }
        }

        if (request()->has('contact_id')) {
            $contact_id = request()->get('contact_id');
            if (!empty($contact_id)) {
                $expenses->where('transactions.contact_id', $contact_id);
            }
        }

        //Add condition for location,used in sales representative expense report & list of expense
        if (request()->has('location_id')) {
            $location_id = request()->get('location_id');
            if (!empty($location_id)) {
                $expenses->where('transactions.location_id', $location_id);
            }
        }

        //Add condition for expense category, used in list of expense,
        if (request()->has('expense_category_id')) {
            $expense_category_id = request()->get('expense_category_id');
            if (!empty($expense_category_id)) {
                $expenses->where('transactions.expense_category_id', $expense_category_id);
            }
        }

        //Add condition for start and end date filter, uses in sales representative expense report & list of expense
        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start = request()->start_date;
            $end =  request()->end_date;
            $expenses->whereDate('transaction_date', '>=', $start)
                ->whereDate('transaction_date', '<=', $end);
        }

        //Add condition for expense category, used in list of expense,
        if (request()->has('expense_category_id')) {
            $expense_category_id = request()->get('expense_category_id');
            if (!empty($expense_category_id)) {
                $expenses->where('transactions.expense_category_id', $expense_category_id);
            }
        }

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $expenses->whereIn('transactions.location_id', $permitted_locations);
        }

        //Add condition for payment status for the list of expense
        if (request()->has('payment_status')) {
            $payment_status = request()->get('payment_status');
            if (!empty($payment_status)) {
                $expenses->where('transactions.payment_status', $payment_status);
            }
        }

        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);
        if (!$is_admin && !auth()->user()->can('all_expense.access')) {
            $user_id = auth()->user()->id;
            $expenses->where(function ($query) use ($user_id) {
                $query->where('transactions.created_by', $user_id)
                    ->orWhere('transactions.expense_for', $user_id);
            });
        }

        return DataTables::of($expenses)
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
                '<span class="display_currency final-total" data-currency_symbol="true" data-orig-value="@if($type=="expense_refund"){{-1 * $final_total}}@else{{$final_total}}@endif">@if($type=="expense_refund") - @endif @format_currency($final_total)</span>'
            )
            ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
            ->editColumn(
                'payment_status',
                '<a href="{{ action("TransactionPaymentController@show", [$id])}}" class="view_payment_modal payment-status" data-orig-value="{{$payment_status}}" data-status-name="{{__(\'lang_v1.\' . $payment_status)}}"><span class="label @payment_status($payment_status)">{{__(\'lang_v1.\' . $payment_status)}}
                        </span></a>'
            )
            ->addColumn('payment_due', function ($row) {
                $due = $row->final_total - $row->amount_paid;

                if ($row->type == 'expense_refund') {
                    $due = -1 * $due;
                }
                return '<span class="display_currency payment_due" data-currency_symbol="true" data-orig-value="' . $due . '">' . $this->transactionUtil->num_f($due, true) . '</span>';
            })
            ->addColumn('recur_details', function ($row) {
                $details = '<small>';
                if ($row->is_recurring == 1) {
                    $type = $row->recur_interval == 1 ? Str::singular(__('lang_v1.' . $row->recur_interval_type)) : __('lang_v1.' . $row->recur_interval_type);
                    $recur_interval = $row->recur_interval . $type;

                    $details .= __('lang_v1.recur_interval') . ': ' . $recur_interval;
                    if (!empty($row->recur_repetitions)) {
                        $details .= ', ' . __('lang_v1.no_of_repetitions') . ': ' . $row->recur_repetitions;
                    }
                    if ($row->recur_interval_type == 'months' && !empty($row->subscription_repeat_on)) {
                        $details .= '<br><small class="text-muted">' .
                            __('lang_v1.repeat_on') . ': ' . str_ordinal($row->subscription_repeat_on);
                    }
                } elseif (!empty($row->recur_parent_id)) {
                    $details .= __('lang_v1.recurred_from') . ': ' . $row->recurring_parent->ref_no;
                }
                $details .= '</small>';
                return $details;
            })
            ->editColumn('ref_no', function ($row) {
                $ref_no = $row->ref_no;
                if (!empty($row->is_recurring)) {
                    $ref_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="' . __('lang_v1.recurring_expense') . '"><i class="fas fa-recycle"></i></small>';
                }

                if (!empty($row->recur_parent_id)) {
                    $ref_no .= ' &nbsp;<small class="label bg-info label-round no-print" title="' . __('lang_v1.generated_recurring_expense') . '"><i class="fas fa-recycle"></i></small>';
                }

                if ($row->type == 'expense_refund') {
                    $ref_no .= ' &nbsp;<small class="label bg-gray">' . __('lang_v1.refund') . '</small>';
                }

                return $ref_no;
            })
            ->rawColumns(['final_total', 'mapping', 'payment_status', 'payment_due', 'ref_no', 'recur_details', 'chart_of_account'])
            ->make(true);
    }
}
