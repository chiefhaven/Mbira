<form method="post" id="undo_last_reconcile_form" action="{{ url('accounting/reconcile/undo') }}">
    @csrf 
    <input type="hidden" name="chart_of_account_id" id="chart_of_account_id"
        v-model="chart_of_account_id">
    <input type="hidden" name="last_reconcile_transaction_id" id="last_reconcile_transaction_id"
        v-model="chart_of_account.last_reconcile_transaction_id">
</form>
