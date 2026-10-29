# EIS Terminal Activation and Configuration Fixes

## All StockOperations endpoints

Settings -> MRA EIS -> **EIS warehouse / production / catalog** opens `/eis/stock/tools`. Together with the existing **EIS outbound stock operations** screen, this covers all 11 operations tagged `StockOperations` in the [official Swagger](https://dev-eis-api.mra.mw/swagger/v1/swagger.json), including the two `/raw-material` paths:

| Method / path (under `/api/v1`) | Surface / behavior |
| --- | --- |
| POST `/stock/transfer-inventory` | Existing site-to-site outbox; new reviewed warehouse-to-site transfer pins the destination to the selected terminal's assigned site. No deprecated site-to-warehouse operation. |
| GET `/stock/warehouse-inventory` | Taxpayer warehouse stock, `page` >= 1 and `pageSize` 1-200 (template 50). |
| GET `/raw-material/get-raw-material` | Taxpayer raw-material warehouse stock with the same pagination. |
| POST `/stock/submit-informal-purchase` | Existing goods-receiving outbox; requires approval, not an immediate local purchase. Receiving date is serialized as ISO-8601 date-time. |
| POST `/raw-material/submit-conversion` | New reviewed raw-material consumption / finished-production request. No local manufacturing movement. |
| POST `/stock/submit-adjustment` | Existing reason-backed Increase/Decrease outbox. |
| POST `/stock/getStockAdjustmentReasons` | Existing live reason lookup used by local/outbound stock adjustments. |
| POST `/stock/get-suppliers` | New supplier lookup exposes documented EIS supplier IDs for the informal-receiving form. |
| POST `/stock/add-product` | New reviewed master-product creation with zero-quantity EIS warehouse inventory. |
| GET `/stock/get-hs-codes` | New HS-code lookup for product creation. |
| GET `/stock/get-units-of-measure` | New unit lookup for product creation / production. |

New tools require `business_settings.access` and **all-location access** because warehouse, production and catalog results are taxpayer-wide. Mutations additionally require `stock_transfer.create`, `stock_adjustment.create` or `product.create`, respectively. Authentication always uses the selected tenant-owned terminal token; remote TIN/site/endpoint overrides are not accepted. Lookups follow their exact HTTP methods, send no invented request body, validate envelope/pagination/item shape and do not follow redirects.

Warehouse transfers accept 1-100 unique barcodes with positive quantity and optional nonnegative price. Conversion requires a genuine unique `productionBatchId`, ISO-8601 `productionDate` with timezone, 1-100 unique raw materials and 1-100 unique finished products. Raw materials require `productId`, `productName`, `availableQuantity`, `usedQuantity`; quantities are at least 0.01 and usage cannot exceed availability. Finished items require `barcode`, `productDescription`, `unitOfMeasure`, quantity >= 0.01, and optional expiry on/after production. Finite quantities/prices are capped at 1 billion as a console safety limit, not an asserted API limit. Product creation requires `hsCode`, `name`, `description`, `uom`; optional barcode has at least four characters. Use the lookup values for HS code/uom; omit barcode to let EIS assign it.

### Reviewed EIS-only mutations

Apply the additive `eis_stock_tool_requests` InnoDB migration first. **Prepare -> review -> explicit confirm/send** stores endpoint/TIN/site/device/location, immutable payload, actors, timestamps and result. Preparation sends nothing. One request key survives repeat clicks and lost responses; changed input cannot replace an already prepared request. Start a different request only for a different genuine operation. Production batch IDs and product identities cannot be prepared twice under another request key. Warehouse transfers have no documented EIS idempotency token: deliberately starting another request for the same goods can duplicate the remote movement, so recover the original record through history instead.

The send claim commits before HTTP and cannot be wrapped in an outer transaction. Only the claiming caller sends; no retries or redirects. Endpoint/terminal ownership and the assigned location's site are rechecked. Unknown/review outcomes block later taxpayer tool submissions, initial-inventory uploads, new stock-outbox requests and site-stock reconciliation. An already queued outbox request remains queued with an explicit blocking reason until the tool outcome is verified; dispatch it afterward. Conversely, queued/unresolved outbox requests and unfinished/uncertain initial-inventory batches block new tool sends. These claims share the business lock to prevent overlapping sends. Accepted product creation requires a matching documented product result, not just a success envelope. Uncertain conversion/transfer responses are not inferred from local quantities.

History supports **record verified outcome** only after independent MRA verification, with a reference/outcome of at least 20 characters, verifying actor and timestamp. It never resends, changes the original result or approves anything at EIS. A request still marked sending cannot be reset this way. Verified requests remain non-retryable. Submitted/rejected/prepared requests do not require uncertain-outcome verification.

These tools deliberately do **not** create local purchases, manufacturing records, transfers, products or stock quantities. After confirmed movements, compare/reconcile each affected site; import the catalog separately after product creation. Warehouse/raw-material retrieval never substitutes warehouse quantities for site inventory. Existing outbound flows and their permissions/audits remain unchanged.

```powershell
php -d error_reporting=24575 tests\Standalone\eis_stock_tools_checks.php
php -d error_reporting=24575 tests\Standalone\eis_stock_outbound_checks.php
php -d error_reporting=24575 tests\Standalone\eis_stock_reconciliation_checks.php
```

Validated with 115 new stock-tools checks, 35 existing outbound checks, 395 cumulative reconciliation checks and 119 Utilities checks (SQLite / fake HTTP). Related PHP and JavaScript syntax, Blade compilation and authenticated web routes pass. The additive migration is applied; its record, all 23 audit columns and InnoDB storage are verified. There were zero stock-tool requests after deployment verification; no live EIS mutation was sent.

## Sales submission, retrieval and linked credit/debit notes

Settings -> MRA EIS -> **EIS Sales** provides the Sales endpoints in the [official Swagger](https://dev-eis-api.mra.mw/swagger/v1/swagger.json).

| Operation (POST) | Integration |
| --- | --- |
| `/sales/submit-sales-transaction` | Existing POS fiscal submission, immutable terminal routing, persisted invoice identity and signed retries. No second raw-submission form is introduced. Host-only and `/api/v1` endpoint configurations are normalized; redirects are disabled. |
| `/sales/last-submitted-online-transaction` | Selected terminal token, no body; displays a verified, locally linked invoice. |
| `/sales/last-submitted-offline-transaction` | Selected terminal token, no body; retrieval does not resend or alter local fiscal state. |
| `/sales/get-invoice-by-number` | Exact `{invoiceNumber}` request; local receipt aliases resolve to the original fiscal invoice. Accepted adjustment invoice numbers are also supported. |
| `/sales/process-credit-debit-note` | A saved POS return creates a linked, immutable invoice-adjustment outbox for review and explicit send. No arbitrary JSON fiscal adjustment is accepted. |

The console requires `business_settings.access`, the authenticated business and permitted locations. Sending notes additionally requires full `access_sell_return` permission. Retrieval verifies the response site/TIN, requested invoice number and local terminal ownership; a mismatching or unlinked upstream invoice is withheld. Locally pending/failed tracked invoice numbers can be retrieved for investigation without resending or silently changing their fiscal status. Receipt cancellation and void retrieval remain available under **EIS Utilities**, where Swagger tags them.

When an online sale submission fails, the application generates the EIS offline signature and validation URL from the exact submitted invoice payload, then persists both with the sale's QR data. The failed online request remains unchanged; subsequent retries reuse the stored signature and validation URL with the same EIS invoice identity instead of generating a new signature. Submission jobs invoked within an open database transaction defer EIS work until commit. If EIS reports that an invoice number already exists, the sale stays failed and is removed from blind automatic retries; verify the invoice using the read-only lookup and reconcile only after confirming the exact terminal, invoice lines, total and VAT.

### POS return/accounting integration

Both the web and Connector POS return paths call the shared `TransactionUtil::addSellReturn`. For fiscal sales, the original sale must already be submitted with exact terminal/location routing. The user must have return permission and location access. Saving a return and its EIS request occurs in the same InnoDB transaction as the existing customer return/refund ledger, stock increase and purchase-allocation release. Preparation makes **no HTTP request**. The return response directs the operator to EIS Sales for review.

The request represents the **remaining updated invoice**, not a second sale or a negative stock adjustment. Product quantities, prices and VAT are mapped against the saved original fiscal payload, and VAT/taxable amounts are summed by the documented `rateId`. The first return produces a credit note. After acceptance, reducing/reversing the return through the same POS form produces a debit note against the latest accepted note invoice; the return-credit balance and local returned stock decrease through the normal ledger. Refund payments exceeding the new return balance must be reversed first. Stock already resold cannot be removed by reducing the return. Multi-supply allocation reversal uses the actually returned quantity, not the entire purchase allocation.

Full returns retain original line identities with zero remaining quantities/totals. Supported automatic mapping requires unchanged product identities/prices/taxes and an exact match between fiscal line totals and the POS refund. Levied/service/modifier invoices, invoice-level discounts/charges that prevent exact matching, inconsistent historical VAT and unlinked historical returns fail explicitly and roll back the local return. There is no guessed tax allocation or silent fallback for unsupported invoices.

Apply `2026_10_24_030000_create_eis_sale_adjustments` before returning fiscal sales. The additive audit table stores the original sale, return, actor, terminal/endpoint identities, exact payload, local snapshot, result and submitting actor/time. **Review -> confirm/send** atomically claims a prepared record and commits the claim before HTTP. The response must identify the expected original invoice and note direction with matching totals before acceptance. Sending never repeats local accounting or stock changes. Accepted requests are idempotent on replay; sending/unknown/rejected/needs-review outcomes cannot be resent and block further return edits and stock reconciliation. Verify uncertain outcomes with MRA; no blind reset is provided.

Fiscal returns cannot be deleted, and sales with linked adjustments cannot be deleted. Use the return-reduction/debit workflow instead. A sale with a note chain cannot also void its original receipt. Existing void requests similarly block return preparation. No price-only surcharge/debit-note ledger was introduced.

Validation uses actual POS return, quantity and purchase-allocation methods with SQLite and fake HTTP only:

```powershell
php -d error_reporting=24575 tests\Standalone\eis_sales_checks.php
php -d error_reporting=24575 tests\Standalone\eis_submission_retry_checks.php
php -d error_reporting=24575 tests\Standalone\eis_stock_reconciliation_checks.php
```

## Configuration: latest Global, Terminal and Taxpayer groups

Settings -> MRA EIS provides **View configurations**, **Latest config** and **Request new token** per permitted terminal. These implement both operations tagged `Configuration` in the [official Swagger](https://dev-eis-api.mra.mw/swagger/v1/swagger.json):

| Operation | Behavior |
| --- | --- |
| POST `/api/v1/configuration/get-latest-configs` | Authenticated, no request body. Retrieves all three groups together and updates the existing terminal-scoped configuration projections. |
| POST `/api/v1/configuration/request-new-terminal-token` | Authenticated, no request body. Explicit confirmation; one audited request, no automatic retry. Recognized credentials are stored server-side. |

**View configurations** displays saved Global, Terminal and Taxpayer groups, their independent version numbers, selected terminal/location and last sync time. Global tax rates, terminal site/contact/address/offline limits and taxpayer VAT/tax-office/activated rates/levies are included. The viewer allowlists documented fields, including nested objects; credentials and activation fields are never returned. **Latest config** refreshes all three groups before displaying them; the existing complete-payload comparison detects field changes even when EIS does not increment versions. Ownership, historical routing, local enablement and designated defaults are preserved.

Both host-only and `/api/v1` endpoint configurations work. Read-only configuration retrieval retries connection/server failures at most three times; rejected, redirected, malformed or incomplete responses fail explicitly and are not persisted. Requests do not follow redirects, and error logs exclude API bodies and credentials.

Apply the additive `eis_terminal_token_requests` migration before using **Request new token**. This InnoDB audit table records actor, business, terminal, saved location, endpoint, status and timestamps without storing token response bodies. The send claim commits before HTTP. Concurrent/pending or uncertain requests block another token request, and terminal ownership/credentials are checked again before atomic persistence to the setting and terminal configuration. A network loss, persistence failure or unsupported response remains **unknown**, requiring MRA verification; there is no blind reset/retry.

Swagger declares token-response `data` only as an object, without naming its properties. The implementation recognizes the integration's existing `jwtToken` and optional `secretKey` fields; it deliberately does not guess other shapes or expose them in the browser. A JWT-only response preserves the existing secret. This operation is separate from the legacy onboarding **Renew tokens** credentials-regeneration action. Do not use another rotation action to work around an uncertain result.

Validation uses isolated SQLite and fake HTTP, never a live token rotation:

```powershell
php -d error_reporting=24575 tests\Standalone\eis_configuration_checks.php
php -d error_reporting=24575 tests\Standalone\eis_terminal_isolation_checks.php
```

## All Swagger Utilities operations

Settings -> MRA EIS -> **EIS Utilities** opens `/eis/utilities`. This administrative console implements every operation tagged `Utilities` in the [official Swagger](https://dev-eis-api.mra.mw/swagger/v1/swagger.json), including the two tagged operations under `/sales`. Select a permitted terminal, select an operation, and fill the JSON template. Invalid/unknown fields fail validation before HTTP. Lookups show the full documented response envelope; a negative business validation (e.g. certificate `isValid=false`) remains visible rather than being treated as network success for a sale. Existing POS buyer/certificate validation retains its stricter business-validity checks.

The sales and POS transaction lists show an EIS void-status column and filter. For a submitted EIS invoice, **Sync EIS void status** retrieves its remote receipt status and synchronizes it with the uniquely matching local fiscal sale. Only the exact case-sensitive value `approvalStatus: "Approved"` marks the separate EIS void marker; the local transaction status and payment history are unchanged. There is no dedicated void-receipts panel. Restricted lookup results remain limited to the selected terminal and invoice ownership.

| Operation (POST) | User fields / behavior |
| --- | --- |
| `/utilities/validate-vat5-certificate` | `projectNumber`, `certificateNumber`, numeric `quantity` |
| `/utilities/get-terminal-blocking-message` | `{}`; remote `terminalId` supplied from selected terminal |
| `/utilities/check-terminal-unblock-status` | `{}`; remote `terminalId` supplied; does not locally enable a terminal |
| `/utilities/check-tin-authorization-requirement` | Buyer `tin`; shows existence/authorization requirement |
| `/utilities/validate-authorization-code` | `authorizationCode`; shows validity and returned details |
| `/utilities/ping` | `{}`; no request body sent; displays server date |
| `/utilities/product-status` | `productId`; own taxpayer TIN supplied; mapping/tax/stock details |
| `/sales/cancel-receipt` | `receiptNumber`, `reason`, optional canonical base64 `supportingDocuments` (5 MiB decoded maximum) |
| `/sales/get-void-receipts` | Optional `status`, `invoiceNumber`, ISO-8601 `startDate`/`endDate`; `page` defaults 1, `pageSize` defaults 20/max 200 |
| `/utilities/get-terminal-site-products` | `{}`; exact selected-site `{tin, siteId}` supplied, no pagination |
| `/utilities/taxpayer-initial-inventory-upload` | `isLastBatch`, list of 1-50 `products`; own TIN supplied |

Initial inventory items require `barCode`, `productName`, `productDescription`, `unitPrice`, `quantityInStock`, `costPrice`, `sellingPrice`; optional `reorderLevel` and `overQuantityStockLevel`. Numeric values must be finite and within the console's 1-billion safety limit; required price/stock values cannot be negative. Duplicate barcodes within a batch or previously accepted batches are rejected. This is **initial taxpayer inventory**, not ongoing purchase receiving or a site-stock adjustment; do not upload dummy repair inventory or use it to resolve ordinary stock differences.

Access requires `business_settings.access` and permitted locations, including administrators. Product status and initial inventory are taxpayer-wide and require all-location access; initial inventory additionally requires `stock_adjustment.create`. Cancellation requires `sell.delete` and a locally submitted fiscal receipt matching the selected terminal/location/business. Restricted users may list voids only with an owned submitted invoice filter; an upstream response containing other invoices is withheld. Historical request access also checks the saved location, even after a terminal moves. Remote identity overrides are rejected; no user-provided token or arbitrary endpoint is accepted.

Cancellation and initial upload use **prepare -> review -> confirm/send**. Preparation never contacts EIS. Additive `eis_utility_requests` records retain the preparing and submitting actors, business/location/terminal, API endpoint, taxpayer/site/device identity, immutable payload, result and timestamps. Review displays these saved identities, not credentials. A duplicate receipt request reuses its original record even when the reason or receipt/invoice alias changes. Only the caller that atomically claims `prepared` can send; the claim commits before HTTP and cannot run inside an outer database transaction. Sending/accepted/rejected/unknown/review outcomes cannot be resent. No automatic HTTP retries or automatic local sale, stock or terminal-state changes occur. Void acceptance is a request for approval, not permission to reverse a local fiscal sale. Successful void-receipt lookups update only the separate EIS marker; a later non-approved response cannot downgrade a previously approved marker.

For phased inventory, send each batch once and explicitly mark the final batch. Staged or uncertain uploads block authoritative site-stock reconciliation for the same business, taxpayer and endpoint; accepted finalization clears only that taxpayer's staged blocker. Unknown/sending/partial/skipped/unmapped results require MRA verification before further batches. There is no documented Utilities API for safely retrying/resetting uncertain staging; this console deliberately provides no blind reset or retry. After a final batch is accepted, another initial upload for that taxpayer/endpoint is blocked. A lost browser response can be recovered through **Recent reviewed requests -> Review** without resending.

Run migrations before opening Utilities or reconciling stock. Existing POS buyer validation and stock retrieval share the Utilities transport; both host-only and `/api/v1` endpoint configurations work. No live EIS mutation is needed for validation:

```powershell
php -d error_reporting=24575 tests\Standalone\eis_utilities_checks.php
php -d error_reporting=24575 tests\Standalone\eis_pos_validation_checks.php
php -d error_reporting=24575 tests\Standalone\eis_stock_reconciliation_checks.php
```

## EIS-authoritative site stock reconciliation

Settings -> MRA EIS -> **Compare / reconcile stock** opens `/eis/stock`. Viewing/comparing requires `business_settings.access` or `stock_adjustment.view`; applying also requires `stock_adjustment.create`. Every operation enforces the authenticated business and permitted location, including administrators.

The [official Swagger contract](https://dev-eis-api.mra.mw/swagger/v1/swagger.json) specifies POST `/utilities/get-terminal-site-products` with exactly `{tin, siteId}`. This is site inventory, not `/stock/warehouse-inventory`. No undocumented pagination fields are sent by reconciliation. Only business-mapped, stocked, single-variation products at the selected terminal's assigned site are reconciled. Import the catalog first; missing EIS products are **not** assumed to have zero stock.

Comparison saves a five-minute snapshot. Applying fetches EIS again and rejects changed remote quantities, terminal/site ownership, local stock or purchase allocations. Finish outstanding fiscal sales (including credit/part-paid final sales) and stock submissions first. Negative stock, duplicate stock rows, ambiguous mappings and quantity/purchase-ledger disagreement require explicit repair rather than silent overwrites.

Differences create immutable `source=eis_reconciliation` audit movements: increases add received opening-stock purchase supply; decreases create stock adjustments and purchase allocations respecting FIFO/LIFO. Existing opening supply is never rewritten. Quantity, allocation and audit writes commit atomically; replaying an applied snapshot does not duplicate movements. Reconciliation does **not** emit an outbound EIS adjustment. Opening-stock editing and adjustment deletion cannot rewrite these reconciliation records.

Apply the EIS migrations before using this screen. All affected stock-ledger tables must use InnoDB; unsupported/nontransactional storage fails explicitly. No scheduled or blind quantity overwrite is enabled. Existing product catalog pulls retain their one-time opening-stock initialization behavior.

### Outbound adjustments, transfers and informal receiving

**EIS outbound stock operations** opens `/eis/stock/outbound`. The standard local stock-adjustment form now requires a live EIS reason at terminal-owned locations; saving creates a per-item `Decrease` outbox in the same database transaction. The normal/abnormal accounting classification remains separate from EIS Increase/Decrease. Unmapped items are recorded as explicit unsent rejections. The expired-stock shortcut is blocked at EIS sites; use the reason-backed form.

The dedicated outbound screen supports Increase/Decrease, site-to-site transfers (both sites must be permitted), and informal goods receiving using the documented EIS supplier ID. These dedicated forms change EIS only; after success, reconcile affected local locations. Do not create a duplicate ordinary local transfer/purchase for the same operation. Informal purchases require EIS approval; accepted submissions do not mean stock is available yet.

Run `php artisan queue:work --queue=eis-stock-operations` using the deployment's queue supervisor. The outbox pins immutable payloads to the original site/terminal and records per-line results. Only never-started `queued` operations can be dispatched again. No HTTP retry is used: timeouts, worker failures and inconclusive responses remain `unknown`; partial submissions are never replayed. Reconciliation blocks queued, submitting, partial, unknown and awaiting-approval operations at either transfer site. Fully rejected operations are settled; subsequent reconciliation may reverse their local quantity effect through a new audited movement, never by deleting history. Any local adjustment linked to an outbox record remains immutable, including fully rejected records.

For final outcomes not available through the documented API, an administrator with `business_settings.access` can **Record verification; do not resend** after checking EIS directly. Required notes retain the final result/reference, actor and timestamp; this changes the header to `verified`, preserves original item results, and permits authoritative stock reconciliation. Never verify a pending approval or an in-flight operation. A worker stuck in `submitting` must first be investigated/stopped and its job failure processed; it cannot be manually marked complete while it may still be sending requests.

Validation uses SQLite/fake HTTP and never sends live EIS stock writes:

```powershell
php -d error_reporting=24575 tests\Standalone\eis_stock_reconciliation_checks.php
php -d error_reporting=24575 tests\Standalone\eis_stock_outbound_checks.php
```

## Settings → MRA EIS terminal management

### Environment and registered POS product

```dotenv
EIS_ENV=development
EIS_BASE_URL=<EIS API base URL for the selected environment>
EIS_PRODUCT_ID=null
EIS_PRODUCT_VERSION=1.0.0
```

Use `EIS_ENV=production` and the MRA-registered `EIS_PRODUCT_ID` for production. Missing product IDs block production activation before HTTP; browser input cannot override the configured ID/version. In development, a null product ID uses the application name so the required `environment.pos.productID` remains nonempty. `dev` and the legacy `sandbox` mode are development aliases; other modes are rejected. Mode does not rewrite `EIS_BASE_URL`: configure the matching endpoint explicitly.

The [EIS Swagger schema](https://dev-eis-api.mra.mw/swagger/index.html) requires nonempty `productID` and `productVersion` in the POS environment. Clear/rebuild Laravel's configuration cache and restart long-running queue workers after changing these environment values.

Activation normalizes operating-system names before contacting EIS. Browser platform aliases such as `Win32`/`MacIntel` are not sent as OS names; the settings form lets the server identify the OS from the request user agent. Unrecognized operating systems are rejected locally with an actionable error.

The settings tab lists terminals at the signed-in user's permitted locations, including restricted administrators. Multiple terminals may belong to a location; **Set default** designates the location's terminal for sales. Terminal IDs below are local `eis_settings.id` values, not EIS device IDs or configuration IDs.

**Get latest config** compares the complete EIS configuration payload, not only version numbers. Site names, terminal labels, trading/contact/address details, remote activity, offline limits, tax configuration and other global/taxpayer fields refresh even when EIS versions stay unchanged. Explicit cleared fields are detected too. The stored response retains all API fields; existing normalized projections refresh, including the assigned location's name/mobile/email. Local site/terminal ownership, manual enablement, invoice/payment defaults and historical transactions remain unchanged. A changed site/terminal/TIN identity is rejected for explicit activation/reconciliation rather than silently reassigned.

All management routes require a session-authenticated account with `business_settings.access`; POST requests require the normal web CSRF token. Business identity comes from the account, never from a client-supplied business ID. Locations and terminals must belong to that business and be permitted for that user (`all` is honored).

| Method | Path | Route name | Input |
| --- | --- | --- | --- |
| GET | `/eis/terminals` | `eis.terminals.index` | None |
| POST | `/eis/terminals/activate` | `eis.terminals.activate` | Required `terminal_activation_code`; optional `environment` object. Location comes from EIS. |
| POST | `/eis/terminals/{terminalId}/latest-config` | `eis.terminals.latest-config` | None |
| POST | `/eis/terminals/{terminalId}/deactivate` | `eis.terminals.deactivate` | Optional `reason` |
| POST | `/eis/terminals/{terminalId}/renew-tokens` | `eis.terminals.renew-tokens` | None; uses this terminal's stored JWT |
| POST | `/eis/terminals/{terminalId}/default` | `eis.terminals.default` | None; updates this terminal's own location |

Listing returns `{success: true, data: {locations: [...], terminals: [...]}}`. Terminal entries contain `id`, `location_id`, `location_name`, `terminal_label`, `device_id`, `is_active`, and `is_default`. Location entries contain `id`, `name`, and `default_eis_setting_id`. Unassigned legacy terminals are excluded until reconciled. Management responses never include JWTs or secret keys.

Mutations return `{success, message, msg}`; service failures use HTTP 422. Normal authentication, authorization, validation, and missing-record responses apply. Status is active only when both the local setting's `status` and terminal configuration's `is_active` are true. Default designation requires both flags to be active; inactive terminals cannot be selected as a new default. The old singleton `eis.web.terminal.*` web routes were replaced; API routes and their authentication remain separate and unchanged.

Isolated regression checks (SQLite memory database; no application boot or live EIS calls):

```powershell
php -d error_reporting=8191 tests\Standalone\eis_terminal_management_checks.php
```

## Location-owned terminal schema and rollout

The current design supersedes the historical singleton behavior described below:

- A business owns many locations. A location owns many terminals. Activation uses EIS `terminalSite.siteId` and `siteName` to match an existing location in that business, or creates one with default invoice/layout/payment settings and a location permission. A manually selected location is not required.
- Reactivating a terminal assigned to another site moves that same terminal and configuration to the API-assigned location. Cross-business terminal theft is rejected. The old location's default is cleared if it pointed to the moved terminal; choose a default for the destination explicitly.
- Historical sales, retry records, and stock are not moved or rewritten. Retries for a terminal that has moved away from the sale's original site fail explicitly for reconciliation rather than submitting with another site's identity.
- `eis_settings.id` is the terminal identity. Settings store `location_id`; configurations, sales, and retry records store both `location_id` and `eis_setting_id`. A terminal has one configuration.
- `business_locations.default_eis_setting_id` explicitly chooses the terminal for new sales. Activation does not silently replace a different terminal or switch the default. Missing/inactive defaults block submission; choose **Set default** after activation.
- Existing fiscal sales remain pinned to their recorded terminal, even after the location default changes. Unreconciled historical sales fail explicitly rather than being reassigned.
- Local deactivation disables both the terminal and its settings. Configuration refreshes cannot silently re-enable it; reactivation or an explicit toggle is required.
- In `EIS_ENV=development` (aliases `dev` and `sandbox`), a configured, locally enabled terminal remains operational even when EIS reports `isActiveTerminal=false`. The remote value is preserved for diagnostics. Terminal lists, default selection, status, and sale submission use the same policy. Production still requires the remote active flag; local deactivation and missing configuration block use in every environment.
- `eis_sales.eis_invoice_number` retains the generated invoice identity even when the confirmed receipt overwrites `fiscal_invoice_number`. Its components follow the [EIS invoice-number generation guide](https://dev-eis-api.mra.mw/docs/invoice_number_generation.htm): taxpayer ID, terminal position, Julian date, and transaction count. The Julian date and count are based on the sale's transaction date. Pending and failed allocations count too; saved request payloads recover older identities when the dedicated column is empty, and retries reuse the original number.
- Each terminal starts each new invoice date at sequence 1, encoded as `B` (then `C`, `D`, etc.). First submissions for historical sales use the sale's date and that date's terminal count plus one; retries keep their already allocated invoice identity and do not consume another date's sequence.
- Terminal allocation and sale submission use atomic cache locks. Use a shared lock-capable cache across all web/queue servers (file cache is suitable only when processes share the same cache filesystem). Do not use the array/null cache driver in production.
- Product identities/catalog mappings remain business-wide; opening-stock initialization and stock quantities are location-specific. All users, including administrators, see quantities only at permitted tenant locations. A product pull must not delete another location's stock or shared catalog.
- New locations reuse default invoice schemes/layouts and payment settings. Configuration reconciliation updates the API-assigned location and preserves its existing invoice/address choices. Duplicate locations with the same site ID are rejected for reconciliation. Activation requires access to both the terminal's previous location and the API-assigned location; creating a new API site requires `access_all_locations`.
- Bulk submission from the sales screen is restricted to permitted locations. Each job resolves its own sale's location and terminal; no arbitrary business-wide token is used. The global badge shows **Multiple terminals** instead of claiming one selected terminal represents all locations.

### Desktop Connector tills

Named tills are separate from EIS terminals and cash-register sessions. `connector_tills` links a business/location/name to an EIS setting. An administrator with `business_settings.access` can create or update a named till from MbiraPOS Connector settings, then select it for that desktop installation (encrypted for the current Windows user, retained across cashier sign-ins). Updating an existing name at the same location changes that shared till's terminal, including for other devices selecting the same till.

- `GET /connector/api/tills` lists active tills and terminal IDs at the authenticated user's permitted locations; it never returns EIS credentials.
- `POST /connector/api/tills` accepts `name`, `location_id`, and `eis_setting_id`. Business identity comes from authentication. Settings permission, location permission and terminal ownership are checked.
- MbiraPOS sends `X-POS-Till: <id>` on operational Connector requests. The API validates tenant, active till, location access and sale/query location. An invalid/unpermitted till is rejected rather than silently replaced. Older clients without the header retain location-default routing.
- Retail creation/update, saved-bill checkout and restaurant creation/clearing persist `transactions.connector_till_id` within the sale/payment transaction. Split children retain till provenance. Workspace location is pinned to the saved till; changing the device assignment requires desktop admin settings.
- New fiscal submissions use the till's operational terminal. When that terminal is unavailable (including site reassignment), the same location's operational default is used and a notice is logged. If neither is usable, fiscal submission is blocked. Offline-limit checks use the same till selection.
- Once EIS sale/retry tracking exists, its recorded terminal takes precedence over till/default changes. An unavailable recorded terminal blocks retry instead of changing fiscal identity.

Deploy migration `Modules/Connector/Database/Migrations/2026_10_23_200000_create_connector_tills.php` before using desktop till settings. No existing locations, terminal defaults or historical sales are reassigned by this migration.

### Migration

`2026_10_23_211000_add_transaction_fiscal_receipt_projection.php` restores missing `transactions.is_fiscalized`, `fiscal_invoice_number`, and `receipt_number` columns without altering existing columns. Receipt identifiers are strings. Its rollback deliberately retains compatibility columns. A retry of an already-submitted sale repairs the local transaction receipt from tenant-scoped submitted EIS tracking without resubmitting to EIS. If the queue failed after acceptance, verify the saved response/tracking first; do not reset submitted tracking to pending. Apply this migration on each affected deployment/database before retrying the failed job.

Apply `Modules/EIS/Database/Migrations/2026_10_25_140000_add_eis_void_receipt_sync.php` before using transaction-list void synchronization. It adds the EIS approval marker, last-sync timestamp and remote response payload to `eis_sales`; it does not alter transaction status or payment records. The migration intentionally retains synchronized approval history on rollback.

`EIS_BASE_URL` in `.env`, accessed through Laravel's `config('eis.base_url')`, is the runtime endpoint source for EIS clients, including credential refresh and signed GET requests. The database column is retained for legacy schema compatibility, not as a per-terminal endpoint override. After changing `.env`, rebuild or clear Laravel's configuration cache as appropriate for the deployment.

Activation supports schemas with or without the legacy `eis_settings.base_url` column. It populates that field only when present (for older schemas where it is required); the endpoint is always taken from configuration.

The follow-up `2026_10_23_171800_restore_eis_settings_base_url.php` restores the original `eis_settings.base_url` column on installations where it is missing. Empty endpoints are populated from `eis.base_url`; existing terminal endpoints are preserved. Its rollback intentionally retains this original-schema column.

Back up the database and run the normal deployment migration process during a maintenance window:

```powershell
php artisan module:migrate EIS
```

The new migration is `2026_10_23_140000_scope_eis_to_location_terminals.php`. It adds ownership columns without deleting legacy records, backfills only exact site/terminal/invoice matches, and designates a default only where a location has exactly one mapped terminal. On MySQL it converts the affected business/location and fiscal ownership tables to InnoDB so activation and credential updates can roll back atomically. Engine conversion may rebuild/lock large tables; storage engines are not reverted on rollback.

Review `eis_migration_issues` after migration. It records the source table, source ID, business ID, and reason for every unresolved mapping. Reconcile those records against verified historical terminal/site identities; do not assign them to an arbitrary first location. Unmapped terminals remain excluded from management until reconciled. A rollback is rejected once a business has multiple mapped terminals.

No deployment migration or real EIS activation is performed by the standalone checks:

```powershell
php -d error_reporting=24575 tests\Standalone\eis_location_terminal_checks.php
php -d error_reporting=24575 tests\Standalone\eis_terminal_isolation_checks.php
php -d error_reporting=24575 tests\Standalone\eis_terminal_management_checks.php
php -d error_reporting=24575 tests\Standalone\eis_submission_retry_checks.php
php -d error_reporting=24575 tests\Standalone\eis_pos_validation_checks.php
```

Legacy API terminal/configuration/tax-rate actions accept `terminal_id` (local settings ID); token-driven actions validate token ownership. Omitted selection works only for an unambiguous single-terminal business and still enforces location permissions. Activation resolves the location from the API site rather than requiring `location_id`.

## Historical fixes (ownership behavior superseded above)
Fixed three critical issues in the Jazz POS EIS integration system related to terminal activation, configuration syncing, and settings display.

---

## Issues Fixed

### 1. Foreign Key Constraint Violations During Terminal Activation
**Issue**: Terminal activation failed with `SQLSTATE[23000]` foreign key constraint violation when attempting to insert business location.

**Root Cause**: 
- Missing `invoice_scheme_id` and `invoice_layout_id` in the INSERT statement
- Missing required fields: `state`, `city`, `zip_code` 
- Hardcoded `sale_invoice_scheme_id => 1` that didn't exist

**Solution** (Modules/EIS/Services/Configuration/ConfigurationSyncService.php):
- Auto-create default invoice scheme if missing (lines 1200-1210)
- Auto-create default invoice layout if missing (lines 1211-1220)
- Provide all required location fields with sensible defaults (lines 1226-1233)
- Result: All foreign key constraints now satisfied

### 2. Missing Branch Information in EIS Settings Display
**Issue**: EIS settings page showed `Branch: N/A` despite being requested to display business location name.

**Root Cause**: TerminalActivationController.details() endpoint wasn't returning business location information

**Solution** (Modules/EIS/Http/Controllers/API/TerminalActivationController.php):
- Added business location lookup in details() method (around line 740)
- Added `'branch' => $businessLocation?->name ?? null` to response
- Frontend now receives and displays the correct branch name

### 3. Get Latest Config Using Job Queue Instead of Direct API Call
**Issue**: `fetchLatestConfig()` was using `SyncEISConfigurationJob::dispatchNow()` which processed ALL businesses through the job queue instead of making a direct API request for the current business.

**Root Cause**: 
- getLatestConfig() method had no businessId parameter
- Was processing all configured businesses unnecessarily
- Added latency due to job queue processing

**Solution** (Updated two files):

#### a. Modules/EIS/Services/Terminal/EisTerminalActivationService.php (lines 931-985)
Changed `getLatestConfig()` method to:
- Accept `int $businessId` parameter
- Retrieve EIS settings for the specific business
- Call `ConfigurationSyncService::sync()` directly with that business's JWT token
- Update sync status for that business
- Create/update business location
- Return structured response with version information

#### b. Modules/EIS/Http/Controllers/API/TerminalActivationController.php (lines 299-323)
Updated `fetchLatestConfig()` controller method to:
- Extract `business_id` from request
- Pass it to `getLatestConfig($businessId)` service method
- Simplified response handling (removed job queue-specific logic)
- Consistent error response format

**Benefits**:
- Direct API call (no job queue overhead)
- Immediate response time
- Only syncs configurations for the requested business
- Consistent with other single-business operations

### 4. Terminal Status Preservation During Config Sync
**Issue**: Terminal status was being reset when `isActiveTerminal` field was missing from EIS API response.

**Root Cause**: The sync method defaulted `is_active = false` when the field was absent

**Solution** (Modules/EIS/Services/Configuration/ConfigurationSyncService.php, lines 393-417):
- Updated conditional logic: only update `is_active` if `isActiveTerminal` is explicitly provided
- Preserves existing terminal status when field is absent from response
- Safe default of `false` for newly created terminals
- Result: Active terminals remain active across configuration syncs

---

## Technical Details

### API Response Structure
- The EIS API endpoint `/configuration/get-latest-configs` returns optional fields
- `isActiveTerminal` is optional per ConfigurationValidator
- Previously: EISConfigurationResponse defaulted missing values to `false`, causing status reset
- Now: Conditional logic respects API contract while preserving existing state

### Business Location Creation
- Static method `ConfigurationSyncService::businessLocation($businessId)` called after successful sync
- Uses data from EIS configuration response (terminalSite, terminalConfig)
- Creates location with auto-generated default invoice scheme and layout if needed

### Database Schema Relationships
- `business_locations` requires foreign key to `invoice_schemes`
- `invoice_schemes` must be created before location can be inserted
- Both `invoice_scheme_id` and `invoice_layout_id` required on new locations

---

## Files Modified

1. **Modules/EIS/Services/Configuration/ConfigurationSyncService.php**
   - Fixed businessLocation() method (lines 1190-1237)
   - Fixed syncTerminalConfiguration() method (lines 393-417)

2. **Modules/EIS/Http/Controllers/API/TerminalActivationController.php**
   - Updated details() method to include branch information (line ~740)
   - Updated fetchLatestConfig() method to use direct API call (lines 299-323)

3. **Modules/EIS/Services/Terminal/EisTerminalActivationService.php**
   - Refactored getLatestConfig() to accept businessId and call API directly (lines 931-985)

---

## Testing Recommendations

1. **Terminal Activation Test**
   - Activate a new terminal for business ID 2 with activation code
   - Verify: No foreign key errors
   - Verify: business_locations record created with all required fields
   - Verify: Terminal status is correct

2. **Branch Display Test**
   - Navigate to EIS settings page (settings_mra_eis)
   - Verify: Branch field shows business location name, not "N/A"
   - Verify: Field updates after terminal activation

3. **Get Latest Config Test**
   - Send POST request to `/api/v1/terminal/get-latest-config` with `{"business_id": 2}`
   - Verify: Direct API response (no job queue processing)
   - Verify: Response includes version information
   - Verify: Sync status updated in database
   - Verify: Only requested business is synced (check logs)

4. **Terminal Status Preservation Test**
   - Activate and activate a terminal (is_active = true)
   - Call "get latest config" (API response lacks isActiveTerminal)
   - Verify: Terminal remains is_active = true in database

---

## Backward Compatibility

- All changes are backward compatible
- No database migrations required
- Existing API responses still valid
- Job queue (SyncEISConfigurationJob) still available for bulk syncing

---

## Related Documentation

- EIS API Response: Modules/EIS/Services/Configuration/EISConfigurationResponse.php
- Configuration Validation: Modules/EIS/Services/Configuration/ConfigurationValidator.php
- Terminal Configuration Model: Modules/EIS/Models/EisTerminalConfiguration.php
- Invoice Scheme Model: App/InvoiceScheme.php
