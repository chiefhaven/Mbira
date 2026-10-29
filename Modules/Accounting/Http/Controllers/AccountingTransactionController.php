<?php

namespace Modules\Accounting\Http\Controllers;

use App\Business;
use Modules\Accounting\Services\FlashService;
use Modules\Accounting\Entities\PaymentDetail;
use Modules\Accounting\Entities\Transaction;
use Modules\Accounting\Utils\ExpenseUtil;
use Modules\Accounting\Utils\PurchaseUtil;
use Modules\Accounting\Utils\SellUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Entities\ChartOfAccount;
use Modules\Accounting\Entities\JournalEntry;

class AccountingTransactionController extends Controller
{
    public function __construct(SellUtil $sellUtil, ExpenseUtil $expenseUtil, PurchaseUtil $purchaseUtil)
    {
        $this->sellUtil = $sellUtil;
        $this->expenseUtil = $expenseUtil;
        $this->purchaseUtil = $purchaseUtil;
    }

    public function sales()
    {
        switch (request()->type) {
            case 'payment':
                if (request()->ajax()) {
                    return $this->sellUtil->getTransactionsDataTable();
                }
                $data_array = $this->sellUtil->getData();
                $data_array['chart_of_accounts'] = ChartOfAccount::where('active', 1)->orderBy('gl_code')->get();
                return view('accounting::transactions.sales.payment')->with($data_array);

            case 'invoice':
                if (request()->ajax()) {
                    return $this->sellUtil->getTransactionsDataTable();
                }
                $data_array = $this->sellUtil->getData();
                $data_array['chart_of_accounts'] = ChartOfAccount::where('active', 1)->orderBy('gl_code')->get();
                return view('accounting::transactions.sales.invoice')->with($data_array);

            default:
                abort(404);
                break;
        }
    }

    public function expenses()
    {
        if (!auth()->user()->can('all_expense.access') && !auth()->user()->can('view_own_expense')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            return $this->expenseUtil->getTransactionsDataTable();
        }

        $data_array = $this->expenseUtil->getData();
        $data_array['chart_of_accounts'] = ChartOfAccount::where('active', 1)->orderBy('gl_code')->get();

        return view('accounting::transactions.expenses')->with($data_array);
    }

    public function purchases()
    {
        switch (request()->type) {
            case 'purchase_order':
                if (!auth()->user()->can('purchase_order.view_all') && !auth()->user()->can('purchase_order.view_own')) {
                    abort(403, 'Unauthorized action.');
                }

                if (request()->ajax()) {
                    return $this->purchaseUtil->getPurchaseOrderTransactionsDataTable();
                }

                $data_array = $this->purchaseUtil->getPurchaseOrdersData();
                $data_array['chart_of_accounts'] = ChartOfAccount::where('active', 1)->orderBy('gl_code')->get();
                return view('accounting::transactions.purchases.purchase_order')->with($data_array);

            case 'purchase_payment':
                if (!auth()->user()->can('purchase.view') && !auth()->user()->can('purchase.create') && !auth()->user()->can('view_own_purchase')) {
                    abort(403, 'Unauthorized action.');
                }
                if (request()->ajax()) {
                    return $this->purchaseUtil->getPurchaseTransactionsDataTable();
                }
                $data_array = $this->purchaseUtil->getPurchaseData();
                $data_array['chart_of_accounts'] = ChartOfAccount::where('active', 1)->orderBy('gl_code')->get();
                return view('accounting::transactions.purchases.purchase_payment')->with($data_array);

            default:
                abort(404);
                break;
        }
    }

    public function map_to_chart_of_account(Request $request)
    {
        $request->validate([
            'map_type' => ['required'],
            'mapping_for' => ['required'],
            'chart_of_account_id' => ['required'],
            'transaction_id' => ['required'],
            'notes' => ['nullable'],
        ]);

        try {
            DB::beginTransaction();

            $business = Business::where('id', session('business.id'))->select('currency_id')->firstOrFail();
            $transaction = Transaction::where('id', $request->transaction_id)->select('location_id', 'final_total')->firstOrFail();
            $transaction_number = get_uniqid();
            $transaction_type = "map_{$request->mapping_for}_transaction_to_journal_entry";

            $payment_detail = new PaymentDetail();
            $payment_detail->created_by_id = Auth::id();
            $payment_detail->payment_type_id = 1; //cash
            $payment_detail->transaction_type = $transaction_type;
            $payment_detail->cheque_number = null;
            $payment_detail->receipt = null;
            $payment_detail->account_number = null;
            $payment_detail->bank_name = null;
            $payment_detail->routing_code = null;
            $payment_detail->save();

            $today = date('Y-m-d');
            $journal_entry = new JournalEntry();
            $journal_entry->created_by_id = Auth::id();
            $journal_entry->transaction_number = $transaction_number;
            $journal_entry->payment_detail_id = $payment_detail->id;
            $journal_entry->location_id = $transaction->location_id;
            $journal_entry->currency_id = $business->currency_id;
            $journal_entry->chart_of_account_id = $request->chart_of_account_id;
            $journal_entry->transaction_type = $transaction_type;
            $journal_entry->date = $today;
            $date = explode('-', $today);
            $journal_entry->month = $date[1];
            $journal_entry->year = $date[0];
            $request->map_type == 'credit' ?
                $journal_entry->credit = $transaction->final_total :
                $journal_entry->debit = $transaction->final_total;
            $journal_entry->manual_entry = 0;
            $journal_entry->notes = $request->notes;
            $journal_entry->save();

            Transaction::findOrFail($request->transaction_id)->update(['journal_entry_id' => $journal_entry->id]);

            activity()
                ->on($journal_entry)
                ->withProperties(['id' => $journal_entry->id])
                ->log("Map {$request->mapping_for} transaction to journal entry");

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return (new  FlashService())->onException($e)->redirectBackWithInput();
        }

        (new FlashService())->onSave();
        return back();
    }
}
