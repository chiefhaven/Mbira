# Saved bills: overselling and receipt reconciliation

## Policy

- No business setting is enabled or rewritten. The authoritative backend setting
  is `business.pos_settings.allow_overselling`, with the same nonempty semantics
  used by the canonical `TransactionUtil::mapPurchaseSell` allocator.
- An already-final saved bill may contain positive exact-decimal allocations to
  an explicit integer/string `purchase_line_id = 0` only while its business allows
  overselling. Null, blank, floats, booleans, malformed references, missing real
  purchases, incomplete coverage, nonpositive allocations, and active mapping
  metadata remain invalid. Other references must be positive canonical integer IDs.
- Both partial and whole-line final splits conserve allocations by purchase ID,
  including the sentinel. They do not consume purchases or change available stock.
  Final checkout likewise preserves the exact existing mappings/consumption.
- Draft checkout uses the canonical stock and purchase allocator utilities. Real
  purchase supply is used first; only the shortage gets a zero sentinel. Under
  overselling, location stock may become negative; a missing stock row starts at
  zero, never at invented positive supply. Duplicate stock rows still fail closed.
  The canonical stock utility's exact-decimal branch is restricted to the internal
  saved checkout scope. Disabling overselling blocks insufficient stock/supply.
- Completely unpaid eligibility, permission/ownership/location checks, edit
  windows, graph versions, durable tenant/actor UUID replay, exact single cash
  amount, and existing fiscal/account/reward/expiry limitations are unchanged.

## Receipt reconciliation

`ProductUtil::adjustStockOverSelling` is still the canonical entry used by purchase,
opening-stock, transfer-receipt and manufacturing receipt paths. With split storage
present it requires the caller's enclosing transaction. The internal reconciler:

1. Verifies transactional storage and the received receipt's tenant/location/type.
2. Uses current locking discovery reads for matching final sale sentinel debts;
   stale MVCC discovery must not lose bill B after a concurrent split.
3. Locks sale graphs, mappings and receipt supply; checks exact positive coverage,
   inert allocation metadata and real purchase capacity/consumption.
4. Replaces only sentinel debt with the receipt allocation, retaining an exact
   sentinel residual when the supply is partial. Increments purchase consumption
   by precisely the new receipt-backed allocations. Available stock was already
   added by the receipt path and is NOT changed again here.
5. Verifies per-line coverage, per-purchase ledger deltas, exact planned allocation
   writes, unchanged real allocations/unrelated purchase consumption, receipt
   supply/header, sale quantities/prices/headers, payments and fiscal graphs.

Only the guard-owned implementation receives a short-lived exact-model saving
capability. There is no general legacy-write bypass, arbitrary caller callback,
header/line/payment/fiscal authority, delete authority or lasting scope. Cancelled
or redirected observer writes fail closed. Savepoints roll back partial work even
when an enclosing caller catches an error. Historical debt remains reconcilable
after the overselling setting is disabled; this does not authorize new overselling.
Non-sale debts retain the legacy path, scoped to the receipt business, after
refreshing purchase balances. Pre-migration behavior has no split participants.

The legacy receipt-edit bulk repair is also fenced before decrementing consumption
or deleting/reassigning protected split allocations. Reducing, deleting or
unreceiving consumed split supply requires a separate audited correction workflow;
the new reconciliation capability does not authorize it.

## Isolated verification

Run `php tests/Standalone/saved_sale_split_checks.php` directly, not Artisan or the
application PHPUnit bootstrap. Composer autoload plus bare Capsule/Container,
SQLite `:memory:` only, synthetic session/dispatcher, null logger, local after-commit
observer. No application/kernel/environment bootstrap, persistent/live database,
network, real fiscal submission, settings mutation, or live migrations.
The existing migration-schema test invokes the migration only against its own
synthetic in-memory database. No deployment migration is performed.

The current real `EisSale::checkOfflineLimits` API reads `eis_offline_limits` in
addition to the legacy terminal fields. The harness models that table and supplies
the global logging alias locally. Production `EisSale` is not modified. Removing
the synthetic table deliberately exercises its real `CHECK_ERROR` response, which
the saved checkout adapter continues to reject (never fail-open).

Coverage includes sentinel-only/mixed mappings, partial/whole splits and exact
retries, reference types, malformed quantities/metadata, missing/incomplete
coverage, real supply validation, enabled/disabled flag cases, fractional negative
stock and missing rows, mixed canonical allocation, final no-stock-change checkout,
all four receipt types, partial/complete/repeated receipts across split bills,
multiple same-variant receipt lines, paid fiscal graph protection, scope attacks,
observer cancellation/redirection/deletion, rollback/trigger corruption,
cross-business/location/product guards, stale versions, legacy bulk repair fences,
normal unsplit/transfer debt, and fiscal check failures.

Verified: **1,427 isolated assertions**. PHP lint for all seven changed PHP files.
Existing unrelated PHP 8.x deprecation notices remain outside this change.

## Boundaries

- No invoice/allocation records (including invoice 442) are repaired or rewritten
  by this implementation work. Eligibility is evaluated against existing data.
- No desktop/UI/error-contract changes, global flag enablement, deployment,
  persistent DB changes, live migration, or real EIS request was performed.
- SQLite verifies algorithmic invariants and rollback, not real MySQL/InnoDB
  concurrency, lock scheduling, storage-engine deployment, or full controller
  integration. Receipt controllers may already hold receipt locks: conflicting
  lock orders can deadlock, and the whole receipt must roll back and be retried;
  reconciliation never swallows a deadlock or skips a debt to force success.
- Core graph/stock storage must pass the existing InnoDB verification. Unsupported
  graphs, ambiguous stock, paid/partial split requests, fiscal-linked splitting,
  expiry, rewards, linked cash accounts and arbitrary corrections remain blocked.
- Existing non-sale legacy arithmetic remains unchanged; the new exact-decimal
  capability applies only to ordinary saved sale stock/debt paths.