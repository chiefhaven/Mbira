# Saved unpaid split and version-aware cash checkout

## Optional protected migration route

POST `/connector/api/admin/migrate-saved-split` is under `auth:api` and
`throttle:2,1`. It requires the system `superadmin` permission (a business
Admin role is insufficient), and is disabled by default. Temporarily set
`CONNECTOR_ALLOW_SPLIT_MIGRATION_ROUTE=true` on the deployed server and refresh
its configuration/route caches through normal deployment procedures.
Send JSON `{"confirm":"create_saved_sale_split_operations"}` with a valid
system-superadmin bearer token. No other parameters are accepted.

Only the fixed split-ledger migration is run; arbitrary commands, paths,
connections and other pending migrations are not exposed. A MySQL advisory
lock serializes invocations of this endpoint. It does not coordinate unrelated
CLI deployers, so do not run concurrent manual migrations. Successful operations
are audit logged. Disable the environment switch and refresh configuration
after use. Migration failures may leave DDL applied and require operator review.
This route does not repair non-InnoDB tables, enable BCMath, or certify concurrency.
No migration has been executed as part of adding the route.

## Current implementation (supersedes draft-only notes below)

Ordinary completely unpaid draft AND final sales can be split. Final-sale
purchase mappings are partitioned without changing stock or purchase consumption.
Any payment row, partial/paid status, returns, fiscal records or unsupported
linked/accounting state prevents splitting. Historical totals must reconcile.
The conservative exclusions for discounts, charges, rewards, combos and other
complex metadata described below still apply.

The desktop Orders panel now provides `Split saved bill` and `Cash / Retry saved
bill` actions. Cash checkout uses dedicated GET `sell/{id}/checkout-preview` and
POST `sell/{id}/checkout` endpoints, not legacy edit or store. The POST accepts
only operation_id UUID, expected_version, method `cash`, and a four-place
decimal-string amount equal to the exact saved total. No partial cash or change
is supported. Authorization, business settings and the saved location's enabled
tenders are revalidated server-side. Unsupported cash accounts, rewards, expiry,
overselling and accounting configurations fail closed.

Checkout returns data containing operation_id, source_id and bill with paid status.
It preserves saved lines/prices and final allocations; draft checkout allocates
stock within the transaction. EIS initiation is scheduled after commit. Legacy
writes stay fenced. The standard web Add Payment modal supports the existing
enabled tender methods and partial payments for split participants. It uses a
server-issued operation UUID, locks and rereads the sale, and atomically records
the payment and operation before returning. Replaying the same operation and
payload does not duplicate the payment; reusing its UUID with changed data fails.
Payment status is the only sale-header field that this scoped path can update.
Normal account, advance-balance, cash-denomination and after-commit EIS behavior
is retained. The desktop still uses its dedicated exact-cash checkout and stores
encrypted pending requests before sending; operation UUIDs must never be regenerated
for uncertain retries.

Validation: 1460 standalone in-memory split/checkout checks, 87 standalone
add-payment checks and 14 fake-backend desktop checks passed, plus 2115 permission
and 25 ownership regressions. The purchase allocation SQL projects computed
availability as a string for exact decimal math. No live migration, business
writes or production checkout were run. PHP 8.4 emits existing
dependency/TransactionUtil deprecations, separate from feature errors.

DEPLOYMENT GATE: the ledger migration still requires reviewed deployment. Local
MySQL/InnoDB concurrent-writer tests have NOT been run. Synthetic tests are not
production concurrency certification. Third-party/raw SQL writers require review.
Do not enable this on business data until those deployment checks are completed.

The following sections document the original conservative draft-only stage;
references to draft-only support, no checkout and hard-coded final exclusion are
historical and superseded by the current implementation above.

## Deployment

The migration `2026_10_06_000001_create_saved_sale_split_operations_table` is provided
but **has not been run**. An operator must review and deploy that migration with
normal backup/change-control procedures. No automatic schema creation is performed.
Missing tables, BCMath, unsupported drivers, or any non-InnoDB required core table
produce HTTP 503 `split_storage_unavailable`. Production requires MySQL/InnoDB;
SQLite is accepted only for a standalone `:memory:` synthetic connection.

The operation table is the durable ledger AND the legacy-write fence. **Do not
delete/truncate it or roll back its migration after using the feature.** Doing so
would remove replay history and permit stale legacy edits of previously split bills.

This backend implementation does not change the desktop UI or automatically route
the old web split screen to the new API.

## HTTP contract

Both endpoints are in the existing authenticated connector/timezone route group.
Authorization requires `sell.create` **and** `sell.update`, or the verified
business-scoped `Admin#<business_id>` role. Non-admins must own the source and have
effective `access_all_locations` or `location.<location_id>` permission. Source,
location, customer, creator, products, variations, base units and line taxes are
tenant-checked. Admin override never changes the original creator.

### GET /connector/api/sell/{id}/split-preview

HTTP 200 response: `{ "data": { "eligible": boolean, "reason": null | {
"code": string, "message": string }, "supported_statuses": ["draft"],
"expected_version": SHA256, "source": Bill } }`.

Unauthorized/not-found/storage failures use the error envelope below, not an
eligibility response. Malformed persisted decimals also fail closed.

The fingerprint includes the full raw header, saved lines, payment rows, mappings,
referenced purchase rows, postings, fiscal/retry records, warranties, incoming
transaction links and child lines. It is not merely `updated_at`. Preview locks and
reads inside a short transaction but does not reserve the bill for later use.

### POST /connector/api/sell/{id}/split

Request accepts **only**:

```json
{
  "operation_id": "d1628731-d83b-4481-acf7-11d733ef31f8",
  "expected_version": "<64 lowercase hexadecimal characters from preview>",
  "lines": [{ "id": 123, "quantity": "1.5000" }]
}
```

`lines` assigns quantities to bill B; bill A keeps all remaining quantities.
IDs must be unique saved source sell-line IDs. Quantities must be positive decimal
**strings**, at most four fractional places, and within the saved quantity and
base-unit fractional policy. No prices, payments, customer, owner, location, status
or stock/allocation fields may be supplied. Both partitions must retain positive
quantities. Maximum 1,000 selected lines.

HTTP 200 response: `{ "data": { "operation_id": UUID, "source_id": integer,
"result_id": integer, "original_total": decimal-string, "bill_a": Bill,
"bill_b": Bill, "line_allocations": [{ "source_line_id": integer,
"bill_b_line_id": integer, "quantity": decimal-string }],
"legacy_writes_blocked": true } }`.
`legacy_writes_blocked` describes Bill A, which remains protected as the source
partition. Bill B is a separate invoice and follows the normal sale, payment, and
fiscal workflows.

`Bill` has: `id`, `invoice_no`, `status`, `payment_status`, `total_before_tax`,
`tax_amount`, `final_total`, `version`, and `lines`. Each line contains saved `id`,
`product_id`, `variation_id`, `quantity`, `unit_price_before_discount`, `unit_price`,
`unit_price_inc_tax`, `item_tax`, `tax_id`, `sell_line_note`, `children_type`.
Monetary/quantity fields above are four-place decimal strings. Nullable identifiers
and optional notes remain nullable. `version` is the resulting graph fingerprint.

Bill A keeps its invoice/token. Bill B uses `SPLIT-<fresh UUID>` and a fresh invoice
token, preserves source status/creator/date/customer and explicitly allowlisted
metadata. No invoice/reference counters are read or incremented.

Same tenant + operation UUID + actor + canonical payload returns the **exact stored
response**, including IDs/versions, without splitting again. Reordering quantities
or equivalent decimal spellings does not change the canonical payload. Reuse by
another actor or with another source/version/quantity payload is HTTP 409
`operation_collision`. Replays recheck authorization/ownership/location. Different
tenants have independent UUID scopes. Never reuse an operation UUID for a new split.

Error envelope: `{ "error": { "code": string, "message": string } }`.
403: `split_forbidden`; 404: `split_not_found`; 422: invalid request/quantity/unit,
empty partition, or nonconserving rounding; 409: stale fingerprint, operation
collision/incomplete ledger or unsupported saved state; 503: storage unavailable.
Unexpected DB errors roll back and are handled by the application's exception
handler; they do not constitute success. Retry deadlocks/transport uncertainty with
the SAME UUID and payload, not a newly generated operation.

## Supported and rejected cases

Supported: ordinary completely unpaid **draft -> draft + draft**, including
suspended drafts, root single/variable product lines, base-unit fractional
quantities, and historic per-unit inline taxes. Nullable draft payment status or
`due` is accepted only with zero payment rows. Current catalog prices/tax rates are
never used to reprice saved lines.

Rejected: every final/paid/partial sale; any payment row (including zero/change/
advance/parent allocations); account/cash-register postings; any returns or returned
quantities; EIS/retry records; incoming/outgoing transaction links; combos/modifiers/
children; warranties; subunits/secondary quantities/lots; sales orders, external/
recurring/module state; rewards; header tax, header/line discounts and fixed header
charges, packing, shipping, extra expenses or rounding adjustments; unexplained
historic pricing/totals; non-base exchange rates; expired edit windows. Unknown
nonempty header/line columns fail closed rather than being cloned. Inactive normal
discount/packing/manufacturing/recurrence configuration defaults are not copied.

Whole lines retain their ID when moved to B. Partial lines get a new B ID and the
source keeps the exact remainder. Header totals are the sum of saved tax-inclusive
line price * quantity, calculated with BCMath at eight intermediate decimal places
and rounded half-up to four places. Source persisted totals must reconcile exactly.
If A+B cannot conserve the source total at four places, reject rather than adding
an unexplained residual charge. No stock quantities or purchase consumption change.
A draft containing ANY existing purchase mapping is rejected. Final mapping
partitioning is deliberately not exposed in this delivery.

## Concurrency protection and limitations

The service locks the source header first, then lines, mappings, referenced purchase
rows and payments in deterministic ID order, revalidates the fingerprint, writes
both partitions and the durable operation response in one transaction. Unique
tenant/operation UUID prevents competing operations from both committing the same
key. Locks use current reads, including operation lookups after a wait, rather than
depending on an earlier repeatable-read snapshot.

`SavedSaleWriteGuard` is shared by header/line/payment model saving/deleting hooks
and common transaction/line/payment/delete/return/mapping/totals helpers. Connector
updates lock before formatting. Web update/payment/finalization/old split paths
lock and reread before mutation. Connector shipping and restaurant bulk updates
also call the guard. EIS model saving rejects draft/split tracking before touching
MyISAM. Public gateway payment refuses drafts before contacting a gateway.

**Bill A, the source remainder, remains protected against legacy sale mutations**,
even if a caller supplies a fresh version. Legacy payloads can contain stale
quantities/line IDs and are not a safe edit/split protocol. Legacy edit, finalize,
delete, return and shipping stay blocked for Bill A; payment uses the guarded
split-payment workflow. Bill B is a separate invoice and is no longer treated as
a split participant for normal sale edits, payments, or fiscal tracking. Restaurant
bills remain subject to their audited cashier workflow. The new split API may
partition either bill again with a new preview/version and UUID.

Once the ledger migration is installed, an existing unsplit draft writer must hold
an explicit DB transaction before the shared guard/model mutation. Previously
unguarded draft metadata writes may now fail closed and need a proper transaction
wrapper. The feature does not silently open/commit a transaction around one model
event while leaving its surrounding graph or side effects unprotected.

**Not certified:** arbitrary query-builder/direct SQL, third-party module writers,
raw fiscal retry inserts, noncooperating external processes, MySQL deadlock/isolation
behavior and concurrent payment/return/final writers. Model events do not intercept
bulk SQL. Audit and coordinate those writers before enabling final splitting or
split-aware checkout; no expected-version bypass is provided. A disposable MySQL/
InnoDB concurrency suite is still required. Do not characterize SQLite tests as
proof of cross-process production serializability. Final support remains hard-coded
off, not a configuration switch.

## Isolated validation

Run the standalone `tests/Standalone/saved_sale_split_checks.php` directly with PHP,
not artisan or the application's PHPUnit bootstrap. It loads only Composer and
standalone Illuminate Capsule, creates fresh SQLite `:memory:` schemas/fixtures,
and uses synthetic failure triggers. The metadata engine-verifier test attaches
another SQLite **in-memory** metadata schema; it is not a MySQL integration test.

Covers historical inline-tax prices, whole/partial/fractional partitions, quantity/
amount conservation, no stock/posting/payment/EIS mutation, permissions/ownership/
tenant/location, strict eligibility, decimal/unit/request validation, rounding
rejection, stale graphs, durable retries/collisions, stale Eloquent writer fences,
fiscal draft fences, missing storage/non-InnoDB rejection and late-failure rollback.
The supplied migration's up/down and unique constraint are exercised ONLY on the
synthetic in-memory schema; routes and thin controller envelopes are also checked
without HTTP dispatch. No application/kernel/.env bootstrap, live requests, real
migrations, business DB writes, EIS business events or integrations are used.
Lint all changed PHP files separately.