# Accounting module ledger

The active Accounting module ledger is `accounting_accounts_transactions`, grouped
by `accounting_acc_trans_mappings` where applicable. Accounting dashboard,
chart-of-accounts, journal, and trial-balance features use this ledger.

Operational sales and purchase documents remain in `transactions`; their
receipts and payments remain in `transaction_payments`. The core
`accounts`/`account_transactions` tables are a separate cash-and-bank subledger,
not an automatic feed into the Accounting module.

The transaction mapping screen records accruals and settlements as separate,
balanced pairs:

| Source | Debit | Credit |
| --- | --- | --- |
| Sale invoice | Accounts receivable | Income |
| Sale payment | Bank/cash | Accounts receivable |
| Purchase invoice | Inventory/expense | Accounts payable |
| Purchase payment | Accounts payable | Bank/cash |

Mapping is user-initiated. Re-saving a source updates its two mapped entries;
invoice entries use `transaction_id`, and payment entries use
`transaction_payment_id`. Mapping both an invoice and its payments is expected
under accrual accounting: the invoice records revenue/cost and receivable/payable,
while the payment clears the receivable/payable. The payment must not be mapped
to the income or expense account again.

Automatic posting is opt-in per business in Accounting settings. Once enabled,
final sales and expenses, received purchases, finalized HRM payroll, and eligible
related payments are posted to the active ledger using that business's selected
accounts. Payroll expense uses gross wages (net pay plus recorded deductions);
payroll can use a dedicated expense account, falling back to the general expense
account when no payroll-specific account is configured.
payroll payments clear the payable, leaving withheld deductions in the selected
payables account. Recoverable purchase/expense tax and sale cost of goods sold
are posted separately. Changed sources create reversing entries before
replacements; repeated events are idempotent. Enabling automation does not
backfill existing transactions.

Asset accounting has a separate opt-in and separate account mappings. Link an
owned asset to a received purchase bill in Asset Management; its quantity times
unit price is reclassified from inventory to fixed assets, and the linked value
cannot exceed the bill's net total. Rented and leased assets are not capitalized.
The existing asset `depreciation` value is treated as a fixed monthly amount.
Review a proposed period using
`php artisan accounting:assets-depreciation <business_id> YYYY-MM --dry-run`;
remove `--dry-run` only after review to post. The run is idempotent and caps
cumulative depreciation at the linked asset cost. Asset depreciation is not
automatically backfilled. Assets with posted depreciation cannot be deleted or
unlinked from their received purchase bill until an asset-disposal workflow is
available, preventing removal of accounting history without a disposal entry.
Apply the source-postings migration before enabling automation; the tracker must
use a transactional table so reversals and replacement entries commit together.
To backfill eligible finalized sales, received purchases, expenses, payroll, and
their payments for a single configured business, run
`php artisan accounting:sync <business_id> --dry-run` to review the count, then
run `php artisan accounting:sync <business_id>`. The command is tenant-scoped,
chunked, and safe to rerun; drafts, orders, and return documents are not included.

The older `chart_of_accounts`/`journal_entries` schema is retained for legacy
data, but is not the active ledger for the current module routes. Do not copy or
delete legacy entries until each legacy account has an explicit mapping to an
active `accounting_accounts` record and the resulting trial balance is checked.
