# EIS product stock synchronization

## Current catalog reconciliation (2026-10-23)

The product pull is now authoritative for the **entire business catalog**, including
manual products, as explicitly requested. After every page has been fetched and
validated, one database transaction imports/restores returned products and maps,
sets `variation_location_details.qty_available` to the exact EIS quantity at the
mapped request-site location, and soft-deletes business products with no active
map in the returned catalog. Absent maps are soft-deleted as well. Other businesses
are untouched. This is business-wide catalog membership, **not** site-only deletion:
a product absent from this site's complete catalog is retired even if it was
previously used at another location. Stock at other locations is not overwritten.

Only successful, well-formed `data` lists are accepted. `has_more` controls
pagination; an absent flag means a single/final page under the existing API contract.
HTTP errors, missing/malformed data, explicit error responses, empty nonfinal pages,
repeated pages/identifiers, invalid quantities or conflicting sites abort the pull.
No partial catalog is used for deletion. An explicitly successful empty catalog
retires all business products/maps; it is not treated as a failed response.
Rejected product quantities/sites produce an `EIS product catalog validation failed`
log entry with the product identifier, reason, page, request site, stock/site field
types and bounded values, and PHP serialization precision. Credentials and the
complete product payload are not logged. The exception distinguishes missing or
invalid/conflicting stock from an invalid/mismatched site; neither failure changes
the local catalog or stock.
Decoded floating-point quantities are encoded with canonical
`serialize_precision=-1` inside an exception-safe scope that restores the host's
setting. A host setting such as `100` no longer rejects valid quantities such as
`108977.0`; low host precision cannot round an invalid quantity into acceptance.
The transformer `stock` field and `transformed_stock` log value use the same
validated four-decimal string as the database writer (for example,
`108977.0000`). Invalid or missing stock remains null, with a
`stock_validation_reason`, rather than being replaced by a misleading zero.
Each EIS-configured ERP business represents its configured terminal. The product
list's Current stock sums only that terminal's mapped location, intersected with
the selected location and the user's location permissions. Legacy quantities at
other locations are not added to the terminal quantity or deleted. No unrelated
location is used as a fallback when the terminal is unmapped. Ordinary businesses
retain permitted-location totals; selecting a location limits the stock sum to
that location, not just the list of products.
Returning maps, products, and their single variation are restored using existing
IDs. Repeated pulls replace quantity rather than adding to it; explicit zero is
authoritative. DECIMAL(22,4) validation uses the existing strict source parser,
not float-based catalog fallbacks. Missing/negative/conflicting quantities fail
instead of silently becoming zero. Existing product stock tracking follows EIS.

This path reconciles available quantity only. It does **not** fabricate opening-stock
transactions or rewrite historical purchase/sale allocation and cost ledgers.
The former one-time initialization helper remains available separately; its
historical checks below do not describe the current product-pull behavior.

All participating tables must support transactions. Migration
`2026_10_23_040000_make_eis_product_maps_transactional` converts the legacy MyISAM
map table to InnoDB without deleting rows; rollback deliberately retains InnoDB.
Fresh EIS map tables also use InnoDB. The sync refuses nontransactional storage
before writing. Run this migration before syncing. The conversion briefly locks
the map table. No live sync is needed to validate these changes.

Current regression command:

```powershell
php tests\Standalone\eis_product_sync_checks.php
```

This uses real transformer/upserter/models with an isolated SQLite database and
synthetic multi-page responses/HTTP fakes; no live product import or API calls.

## EIS-only product editing

### Terminal isolation

An ERP business represents one terminal. Product pulls/upserts, configuration
responses, sale payloads and invoice submission use the same exact configured
site (`site_id` when present, otherwise `branch_id`). A device ID or `00` is
never substituted for a missing site. Duplicate settings records fail closed.
Configuration sync verifies the business's stored token and the returned site,
TIN and terminal ID (when supplied) before persisting or skipping unchanged
versions. Forced sync preserves the existing configuration if validation fails.
Location reconciliation requires exactly one existing location for the business
and updates its EIS site and details in place, preserving its ID and existing
transaction/stock references when a terminal is replaced. It never creates or
deletes locations. Missing or multiple locations require explicit reconciliation.

Sale submission reloads stored credentials for the transaction's business,
rejects mismatched caller settings, tracking, sale location and foreign
products/customers, and checks payload site/TIN before HTTP. Retry jobs scope
transaction reads and tracking writes to their business. Activation cannot
assign another business's terminal. Activation can replace the current business
terminal by updating its existing settings and terminal configuration records.
Health caches already include both business ID and credential/host identity.

EIS invoice sequence numbers use the current taxpayer ID, terminal position and
Julian day encoded in the persisted fiscal invoice number. The Julian day and
terminal count are based on the sale's transaction date; the next sequence is
that date's invoice count plus one. The business ID is not prepended to the
sequence. Pending and failed invoices retain their allocated numbers and retries
reuse them. Each transaction date or replacement terminal has its own count.

Internal module routes, including formerly public terminal/configuration
read endpoints, now require authenticated same-business access. Body, route,
bulk and session business targets cannot override the account's business.
Webhooks under `api/v1/webhooks/*` retain their existing access behavior by
explicit user direction. **Webhook signature verification and terminal
authentication remain deferred pending the official EIS callback contract;
this is not a claim that external webhook isolation is complete.**

Isolated terminal, configuration-response, activation and location regression:

```powershell
php tests\Standalone\eis_terminal_isolation_checks.php
php tests\Standalone\eis_submission_retry_checks.php
```

The terminal suite also runs the catalog fixture checks. No command here runs
a live EIS sync, activation, or sale submission.

Any business with an `eis_settings` row is EIS-managed, even if the integration
is inactive or its token has expired. Other businesses retain existing behavior.
Manual products (including quick-add, imports, duplicates and modifier products),
deletion/deactivation, price edits/imports, opening stock and manual stock
adjustments are forbidden for EIS-managed businesses. Normal sales/purchases
continue moving stock; purchases do not change the EIS catalog prices. Viewing
stock history does not overwrite EIS quantities using the local ledger.

The dedicated product editor permits only image, category/subcategory, brand,
permitted locations and rack/row/position, alert quantity and product custom
fields 1-20. Forged protected fields and foreign or unauthorized references are
rejected server-side. Product and variation model events also reject protected
writes from legacy writers. The EIS upserter uses a business-bound, exception-safe
trusted scope; do not disable model events to bypass this policy.

Every pull refreshes EIS-owned name, SKU, description, expiry, unit, tax, tracking
and prices while retaining approved local details. The current isolated suite
also exercises these rules, endpoint denial, local edit validation/persistence,
ordinary-business compatibility and trusted-scope cleanup.

## Historical one-time opening-stock design

The remainder documents the previous initialization design and its standalone
helper suite, not the current authoritative catalog sync contract.

## Changed sources

- `Modules/EIS/Services/Products/ProductUpsertService.php`: locks the business/product/map identity; never replaces a missing or cross-business mapped product; rejects ambiguous fallback identities/variations; refreshes catalogue metadata/prices independently of inventory; removes the absolute availability overwrite and arbitrary site fallback.
- `Modules/EIS/Services/Products/ProductTransformer.php`: preserves an explicit validated stock source and unambiguous site separately from existing catalogue fallback fields; explicit false stock tracking stays false.
- `Modules/EIS/Services/Products/EisProductClient.php` and `Modules/EIS/Services/Sync/ProductSyncService.php`: share the exact request-site selection and pass trusted, business-matched request context to the transformer.
- `Modules/EIS/Services/Products/OpeningStockInitializer.php`: testable connection-based, transactional one-time supply initializer.
- `tests/Standalone/eis_opening_stock_checks.php` and this document: isolated verification and operational boundaries.

## Invariants

The first clean, tracked, single-variation import at exactly one tenant/site location creates one received `opening_stock` transaction, one purchase line (including explicit zero), and exact availability. The transaction links `opening_stock_product_id`; purchase lines carry normal quantity-consumption counters and purchase costs. Totals/quantities use BCMath at four decimal places. Header `total_before_tax` is quantity times the stored tax-exclusive cost; `final_total` is quantity times stored tax-inclusive cost; `tax_amount` is the difference of those totals. All header amounts and unit tax differences must fit nonnegative DECIMAL(22,4). No sell allocation mappings are rewritten. Inspection of `ProductUtil::addSingleProductOpeningStock` confirms compatible exclusive/inclusive purchase-line fields, but its legacy header puts the inclusive total in both fields; that unrelated utility is not changed or copied here. Existing initialized headers remain immutable, not silently repaired by repull.

An `eis_opening_stock_v1` object in the existing map `meta` stores business/product/variation/location/EIS/site identity, quantity, transaction/purchase-line IDs and initialization time. Other map metadata is retained. All graph writes and the marker share the enclosing transaction. Import retries, changed EIS snapshots, local sales/adjustments/consumption and even deletion of the original ledger never trigger another supply or reset availability. Site drift or an inconsistent marker is blocked, not relocated. The marker is not a repair/reconciliation mechanism.

An ordinary existing catalogue product first matched without an EIS map receives a durable catalogue-only exclusion, not ownership of its stock. Unmarked legacy nonzero/negative/invalid availability, any historical purchase/sell/adjustment (including orphaned lines or a sell with sentinel allocation), duplicate/inconsistent stock rows, other stock locations and existing opening headers require manual review. Catalogue metadata can still sync when the identity is safe. Missing/conflicting/ambiguous identity cannot safely select a catalogue product and rejects the import instead of remapping it.

## Input and storage requirements

- Stock must be explicit in `quantity`, `stock` or `stockQuantity`, as a nonnegative decimal string or integer with at most four fractional digits and 18 integer digits. Multiple provided aliases must agree. At this ingestion boundary only, finite nonnegative decoded floats <=1e9 are accepted if canonical JSON encoding (`serialize_precision=-1`, preserving a zero fractional part) passes the same strict decimal validator: <=4 fractional places, no exponent, no rounding. Canonical precision is scoped to encoding and the host setting is restored afterward. Ledger `decimal()` still rejects every float; string/integer bounds remain unchanged. Absence/null, booleans, arrays, negative quantities, excess precision and exponent-encoded values are never rounded or treated as zero. This validates the decoded payload, **not raw JSON-token exactness**: a source exponent or precision already lost during decoding cannot be reconstructed. Catalogue transformation's old float fallback is never an initialization input.
- Site aliases/nested explicit site must agree and match exactly one business location. Only truly missing per-item site fields may inherit the exact site actually sent by the terminal-products request (`settings->site_id ?? settings->branch_id`). Sync verifies settings business against the target business and snapshots settings before requests. Invalid settings fail before any request/import; invalid or conflicting explicit item sites never inherit context. An empty explicit settings site does not fall back to branch. Unknown requested sites never fall back to another local location. Direct transforms without request context remain unscoped. No default/first-location fallback is allowed.
- In MySQL, every table involved in stock/history/identity locking must be InnoDB, particularly `eis_product_maps`. Initialization fails closed with a table-specific diagnosis for MyISAM/missing/other engines. Catalogue sync is not certification of atomicity for nontransactional catalogue tables. **No engine conversion or migration is included or executed.** An operator must review storage before enabling initialization on an installation with MyISAM maps.
- The helper requires an enclosing transaction and locks the business, product, map, variation, location, movement rows and availability. Business serialization coordinates this importer, including absent-map creation. Other application writers and actual concurrent MySQL behavior are not certified by SQLite tests.
- Existing uncertain stock/allocations must be reconciled deliberately with an audited opening-stock procedure, not a repeated EIS pull or deleting the marker. Diagnostic logs name the reason and require manual review. Saved-sale allocation fences are unchanged.

## Verification

Final verification on 2026-10-07: all six PHP lint checks pass; **326 isolated assertions pass twice consecutively**. Changed PHP files retain CRLF; `git diff --check` is clean. The protected `EisSale` SHA256 remains unchanged, and there are no diffs to either saved-sale fence/service.

Run directly from any directory, with PHP BCMath and PDO SQLite available:

```powershell
php -l C:\wamp64\www\jazz\Modules\EIS\Services\Products\OpeningStockInitializer.php
php -l C:\wamp64\www\jazz\Modules\EIS\Services\Products\ProductUpsertService.php
php -l C:\wamp64\www\jazz\Modules\EIS\Services\Products\ProductTransformer.php
php -l C:\wamp64\www\jazz\Modules\EIS\Services\Products\EisProductClient.php
php -l C:\wamp64\www\jazz\Modules\EIS\Services\Sync\ProductSyncService.php
php -l C:\wamp64\www\jazz\tests\Standalone\eis_opening_stock_checks.php
php -d error_reporting=8191 C:\wamp64\www\jazz\tests\Standalone\eis_opening_stock_checks.php
```

The suite uses installed Composer classes, a bare Container, synthetic config/logger, actual Eloquent models/transformer/upserter, and fresh SQLite `:memory:` databases. No Laravel application/kernel, `.env`, production database, network, migration, queue or live EIS call is used. Synthetic SQL triggers inject purchase-line and marker-write failures. A SQLite connection with synthetic engine metadata exercises MyISAM refusal; it does not connect to MySQL. Deprecations from older dependencies on PHP 8.4 are excluded by the CLI flag; other errors remain enabled.

Coverage includes the real sync/transform/import chain with synthetic settings/client and repeated pages; the actual client POST builder using an in-process HTTP fake; missing versus invalid/conflicting explicit sites; site/branch precedence, business mismatch and unknown-site refusal; bounded floats (0.0, 1.5, 12.5), NaN/infinities, large/scientific/excess-precision and encoder-rounding refusal; taxed positive/zero/fractional header and line totals, overflow and DECIMAL upper boundaries. Existing positive/zero opening graphs, exact large decimals above IEEE-754's integer boundary, repeated imports after sales/consumption, changed snapshots/prices/metadata, missing/invalid stock, nonstock, site drift/ambiguity, multiple/deleted variations, corrupt/cross-tenant maps, ordinary and legacy stock, sentinel sale allocations, intact unrelated metadata, complete rollback/retry, engine refusal and no ledger recreation after marker initialization remain covered.

The existing standalone saved-sale suite was also attempted without source changes. It stops in the externally changed `EisSale::checkOfflineLimits` on the unresolved global `Log` class. A process-local test logger removes only that logging error, but the existing code still returns `CHECK_ERROR` and the saved-sale fence correctly rejects checkout. This is an independent regression limitation, not repaired or bypassed here; `EisSale` and the saved-sale fence were not edited. The new opening-stock suite is independent of that code path.

No real production data, storage engines, migrations, concurrent row locks, HTTP response formats, live sale allocation or deployment have been exercised. The user must review these operational prerequisites before deploying. The MyISAM refusal is intentional, not a reason to weaken the stock/marker transaction requirement.