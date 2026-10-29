<?php

namespace Modules\Accounting\Services;

use App\AccountTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Entities\JournalEntry;
use Modules\Accounting\Entities\PaymentDetail;

class AccountingService
{
    public function updateChartAccounts($input, $type, $subtype = null)
    {
        //Get the amount depending on whether a debit or credit is being made
        $amount = $input['debit'] > 0 ? $input['debit'] : $input['credit'];

        $account_transaction_data = [
            'amount' => $amount,
            'account_id' => $input['account_id'],
            'type' => $type,
            'sub_type' => $subtype,
            'operation_date' => $input['journal_date'],
            'created_by' => Auth::id(),
            'note' => $input['description']
        ];

        $account_transaction = AccountTransaction::createAccountTransaction($account_transaction_data);

        $account_transaction->save();
    }

    public function createJournalEntry(Request $request)
    {
        try {
            DB::beginTransaction();

            $transaction_date = date('Y-m-d', strtotime($request->transaction_date));
            $transaction_number = get_uniqid();

            $payment_detail = new PaymentDetail();
            $payment_detail->created_by_id = Auth::id();
            $payment_detail->payment_type_id = $request->payment_type_id;
            $payment_detail->transaction_type = 'journal_manual_entry';
            $payment_detail->save();

            //debit account
            $journal_entry = new JournalEntry();
            $journal_entry->created_by_id = Auth::id();
            $journal_entry->payment_detail_id = $payment_detail->id;
            $journal_entry->transaction_number = $transaction_number;
            $journal_entry->location_id = $request->location_id;
            $journal_entry->currency_id = $request->currency_id;
            $journal_entry->chart_of_account_id = $request->debit;
            $journal_entry->transaction_type = 'manual_entry';
            $journal_entry->date = $transaction_date;
            $date = explode('-', $transaction_date);
            $journal_entry->month = $date[1];
            $journal_entry->year = $date[0];
            $journal_entry->debit = $request->final_total;
            $journal_entry->manual_entry = 1;
            $journal_entry->notes = $request->additional_notes;
            $journal_entry->save();

            //credit account
            $journal_entry = new JournalEntry();
            $journal_entry->created_by_id = Auth::id();
            $journal_entry->transaction_number = $transaction_number;
            $journal_entry->payment_detail_id = $payment_detail->id;
            $journal_entry->location_id = $request->location_id;
            $journal_entry->currency_id = $request->currency_id;
            $journal_entry->chart_of_account_id = $request->credit;
            $journal_entry->transaction_type = 'manual_entry';
            $journal_entry->date = $transaction_date;
            $date = explode('-', $transaction_date);
            $journal_entry->month = $date[1];
            $journal_entry->year = $date[0];
            $journal_entry->credit = $request->final_total;
            $journal_entry->manual_entry = 1;
            $journal_entry->notes = $request->additional_notes;
            $journal_entry->save();

            activity()
                ->on($journal_entry)
                ->withProperties(['id' => $journal_entry->id])
                ->log('Create Journal Entry');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return (new FlashService())->onException($e)->redirectBackWithInput();
        }
    }
}
