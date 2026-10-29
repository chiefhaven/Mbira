# EIS Sales Fiscalization Tracking - Migration & Model Updates

## Overview

Added `is_fiscalized` field to the `eis_sales` table to track which sales have been successfully fiscalized to the EIS system. This is a critical field for sales reconciliation and compliance reporting.

## What is Fiscalization?

**Fiscalization** is the process of successfully submitting a sale to the EIS (Electronic Invoicing System) and receiving confirmation that it has been registered and processed. A sale is "fiscalized" when:

1. The sale has been submitted to the EIS API
2. EIS has validated the submission
3. EIS has issued a fiscal invoice number
4. The transaction is now officially registered with MRA

## Database Schema Changes

### Migration File
`Modules/EIS/Database/Migrations/2026_10_22_141000_add_is_fiscalized_to_eis_sales.php`

### New Fields Added to `eis_sales` Table

```sql
ALTER TABLE eis_sales ADD COLUMN is_fiscalized BOOLEAN DEFAULT FALSE AFTER status;
ALTER TABLE eis_sales ADD COLUMN fiscalized_at TIMESTAMP NULL AFTER submitted_at;
```

#### Field Details

| Field | Type | Default | Purpose |
|-------|------|---------|---------|
| `is_fiscalized` | boolean | false | Flag indicating if sale has been successfully fiscalized to EIS |
| `fiscalized_at` | timestamp | null | Timestamp when sale was successfully fiscalized |

### Complete `eis_sales` Table Structure

```
id (bigint, primary key)
business_id (int)
transaction_id (bigint, foreign key)
invoice_number (varchar, nullable)
fiscal_invoice_number (varchar, nullable) ← Assigned by EIS
receipt_number (varchar, nullable)
receipt_signature (varchar, nullable)
qr_code (text, nullable)
status (enum: pending/submitted/failed/cancelled)
is_fiscalized (boolean, default false) ← NEW
request_payload (json, nullable)
response_payload (json, nullable)
error_message (text, nullable)
submitted_at (timestamp, nullable)
fiscalized_at (timestamp, nullable) ← NEW
created_at (timestamp)
updated_at (timestamp)
```

## Model Updates

### File: `Modules/EIS/Models/EisSale.php`

#### Updated Fillable Array
```php
protected $fillable = [
    'business_id',
    'transaction_id',
    'invoice_number',
    'fiscal_invoice_number',
    'receipt_number',
    'receipt_signature',
    'qr_code',
    'status',
    'request_payload',
    'response_payload',
    'error_message',
    'submitted_at',
    'is_fiscalized',        // ← NEW
    'fiscalized_at',        // ← NEW
];
```

#### Updated Casts
```php
protected $casts = [
    'request_payload' => 'array',
    'response_payload' => 'array',
    'submitted_at' => 'datetime',
    'fiscalized_at' => 'datetime',  // ← NEW
    'is_fiscalized' => 'boolean',   // ← NEW
];
```

#### New Helper Methods

```php
/**
 * Mark sale as fiscalized.
 * Sets is_fiscalized to true and records the timestamp.
 * 
 * @return bool
 */
public function markAsFiscalized(): bool
{
    return $this->update([
        'is_fiscalized' => true,
        'fiscalized_at' => now(),
    ]);
}

/**
 * Check if sale has been fiscalized.
 * 
 * @return bool
 */
public function isFiscalized(): bool
{
    return $this->is_fiscalized === true;
}

/**
 * Scope: Get sales that are pending fiscalization.
 * Returns sales that haven't been fiscalized yet and aren't failed/cancelled.
 * 
 * Usage: EisSale::pendingFiscalization()->get()
 * 
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopePendingFiscalization($query)
{
    return $query->where('is_fiscalized', false)
        ->where('status', '!=', 'failed')
        ->where('status', '!=', 'cancelled');
}

/**
 * Scope: Get sales that have been fiscalized.
 * 
 * Usage: EisSale::fiscalized()->get()
 * 
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopeFiscalized($query)
{
    return $query->where('is_fiscalized', true);
}
```

## Usage Examples

### Mark a Sale as Fiscalized

```php
// After successful EIS submission
$eisSale = EisSale::find($saleId);

if ($eisResponse->isSuccess()) {
    // Mark as fiscalized
    $eisSale->markAsFiscalized();
    
    // Or manually
    $eisSale->update([
        'is_fiscalized' => true,
        'fiscalized_at' => now(),
        'fiscal_invoice_number' => $eisResponse->getFiscalInvoiceNumber(),
        'status' => 'submitted'
    ]);
}
```

### Check if Sale is Fiscalized

```php
$eisSale = EisSale::find($saleId);

if ($eisSale->isFiscalized()) {
    echo "Sale has been fiscalized to EIS";
} else {
    echo "Sale is still pending fiscalization";
}
```

### Query Fiscalized Sales

```php
// Get all fiscalized sales
$fiscalizedSales = EisSale::fiscalized()->get();

// Get all sales pending fiscalization
$pendingSales = EisSale::pendingFiscalization()->get();

// Get pending sales for a specific business
$pendingForBusiness = EisSale::where('business_id', $businessId)
    ->pendingFiscalization()
    ->get();

// Get sales fiscalized in the last 24 hours
$recentFiscalized = EisSale::fiscalized()
    ->where('fiscalized_at', '>=', now()->subDay())
    ->get();

// Get sales by status and fiscalization
$submittedButNotFiscalized = EisSale::where('status', 'submitted')
    ->where('is_fiscalized', false)
    ->get();
```

### Sales Submission Service Integration

```php
// In SaleSubmissionService or similar
public function submitSaleToEIS(EisSale $eisSale, $payload)
{
    try {
        $response = $this->eisClient->submitSale($payload);
        
        if ($response->isSuccess()) {
            // Update sale with response data
            $eisSale->update([
                'status' => 'submitted',
                'response_payload' => $response->getData(),
                'fiscal_invoice_number' => $response->getFiscalInvoiceNumber(),
                'receipt_number' => $response->getReceiptNumber(),
                'receipt_signature' => $response->getReceiptSignature(),
            ]);
            
            // Mark as fiscalized
            $eisSale->markAsFiscalized();
            
            return ['success' => true];
        } else {
            $eisSale->update([
                'status' => 'failed',
                'error_message' => $response->getErrorMessage(),
                'response_payload' => $response->getData(),
            ]);
            
            return ['success' => false, 'message' => $response->getErrorMessage()];
        }
    } catch (\Exception $e) {
        $eisSale->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ]);
        
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
```

## Sale Status Flow

```
┌─────────────────────────────────┐
│ Sale Created                    │
│ status: pending                 │
│ is_fiscalized: false            │
│ fiscalized_at: null             │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ Submit to EIS API               │
│ POST /sales/submit              │
└──────────────┬──────────────────┘
               │
        ┌──────┴──────┐
        │             │
        ▼             ▼
   SUCCESS        FAILURE
        │             │
        ▼             ▼
┌──────────────┐  ┌──────────────┐
│ status:      │  │ status:      │
│ submitted    │  │ failed       │
│ is_fis:true  │  │ is_fis:false │
│ fis_at: NOW  │  │ fis_at: null │
└──────────────┘  └──────────────┘
```

## Reconciliation & Reporting

### Count Fiscalized Sales
```php
// Total fiscalized sales
$fiscalizedCount = EisSale::fiscalized()->count();

// Fiscalized by business
$byBusiness = EisSale::fiscalized()
    ->groupBy('business_id')
    ->selectRaw('business_id, COUNT(*) as count')
    ->pluck('count', 'business_id');

// Fiscal value by business
$valueByBusiness = EisSale::fiscalized()
    ->join('transactions', 'eis_sales.transaction_id', '=', 'transactions.id')
    ->groupBy('business_id')
    ->selectRaw('business_id, SUM(transactions.total) as total_value')
    ->pluck('total_value', 'business_id');
```

### Audit Trail
```php
// Get all events for a sale
$sale = EisSale::find($saleId);

echo "Created: " . $sale->created_at;
echo "Submitted: " . $sale->submitted_at;
echo "Fiscalized: " . $sale->fiscalized_at ?? 'Not yet';

// Timeline
$events = [
    ['type' => 'created', 'at' => $sale->created_at],
    ['type' => 'submitted', 'at' => $sale->submitted_at],
    ['type' => 'fiscalized', 'at' => $sale->fiscalized_at],
];
```

### Pending Submissions Report
```php
// Sales submitted but not yet fiscalized (may indicate API lag)
$delayedFiscalizations = EisSale::where('status', 'submitted')
    ->where('is_fiscalized', false)
    ->where('submitted_at', '<', now()->subHours(1))
    ->get();

// Alert if any are older than 1 hour
foreach ($delayedFiscalizations as $sale) {
    Log::warning('Sale submission not fiscalized after 1 hour', [
        'eis_sale_id' => $sale->id,
        'transaction_id' => $sale->transaction_id,
        'submitted_at' => $sale->submitted_at,
        'delay_hours' => $sale->submitted_at->diffInHours(now())
    ]);
}
```

## Database Indexes

Consider adding indexes for better query performance:

```php
// In migration or separate optimization migration
Schema::table('eis_sales', function (Blueprint $table) {
    $table->index('is_fiscalized');
    $table->index('fiscalized_at');
    $table->index(['is_fiscalized', 'status']);
});
```

## Migration Instructions

### Run Migration
```bash
php artisan migrate
```

### Rollback (if needed)
```bash
php artisan migrate:rollback
```

### Check Status
```bash
php artisan migrate:status
```

## Deployment Notes

### Before Deployment
- [ ] Review migration file for compatibility
- [ ] Backup database
- [ ] Test on staging environment
- [ ] Verify no active submissions are in progress

### During Deployment
- [ ] Run migration: `php artisan migrate`
- [ ] Verify fields were added: Check database schema

### After Deployment
- [ ] Verify EisSale model loads correctly
- [ ] Test markAsFiscalized() method
- [ ] Test query scopes
- [ ] Monitor logs for any errors

### Data Migration (Optional)
If you need to retroactively mark existing submissions as fiscalized:

```php
// Mark all submitted sales as fiscalized if they have a fiscal_invoice_number
EisSale::where('status', 'submitted')
    ->whereNotNull('fiscal_invoice_number')
    ->update([
        'is_fiscalized' => true,
        'fiscalized_at' => DB::raw('submitted_at')
    ]);
```

## Related Components

- **Model**: `Modules/EIS/Models/EisSale.php`
- **Migration**: `Modules/EIS/Database/Migrations/2026_10_22_141000_add_is_fiscalized_to_eis_sales.php`
- **Sales Submission**: `Modules/EIS/Services/Sales/SaleSubmissionService.php`
- **Sales Response**: `Modules/EIS/Services/Sales/SaleResponseService.php`
- **Sales Table**: `Modules/EIS\Database\Migrations\2026_07_05_000001_create_eis_sales_table.php`

## Summary

**is_fiscalized field provides:**
- ✅ Clear tracking of which sales have been fiscalized
- ✅ Timestamp of when fiscalization occurred
- ✅ Foundation for compliance reporting
- ✅ Ability to identify and retry failed submissions
- ✅ Better audit trail for reconciliation
- ✅ Query scopes for common reporting needs

**Backward Compatible:**
- ✅ Existing sales default to `is_fiscalized = false`
- ✅ No breaking changes to existing code
- ✅ Optional fields that can be adopted gradually

**Ready for Production:**
- ✅ Migration tested
- ✅ Model updated
- ✅ Helper methods provided
- ✅ Query scopes implemented
- ✅ Documentation complete

## EIS stock operations outbox

The separate `eis_stock_operations` outbox records stock adjustments, site-to-site
transfers, and informal purchases without using the sales fiscalization tables.
Its immutable request payload and per-item rows are created before the queued job
can submit anything. `eis_stock_operations.location_id` and `eis_setting_id` pin
each request to its original local location and EIS terminal; the site ID is also
saved in the request scope. Site-to-site transfers also persist
`destination_location_id`; both locations must block reconciliation while the
transfer is unsettled.

Operation statuses are `queued`, `submitting`, `submitted`, `awaiting_approval`,
`partial`, `rejected`, and `unknown`. Location reconciliation must wait while an
operation is `queued`, `submitting`, `awaiting_approval`, `partial`, or `unknown`.
`submitting` and `unknown` are potentially completed remote writes and are never
resent automatically. An EIS-accepted informal purchase remains
`awaiting_approval` because goods receiving is subject to EIS approval. Only a
confirmed `submitted` or fully `rejected` operation is settled.

Local stock adjustments at EIS-mapped sites require a reason returned by EIS and
create outbound per-line `Decrease` requests after the local transaction is
recorded. Reconciliation-sourced stock adjustments do not raise outbound
operations. The expired-purchase shortcut is blocked at EIS-mapped sites; use
the reason-backed stock adjustment form instead. Stock adjustment headers
sourced by EIS reconciliation and headers with pending, submitted, or ambiguous
outbound operations cannot be deleted or edited. Queued operations may be
explicitly dispatched again only while still `queued`; this does not retry any
operation that has started or received an EIS response.

The outbox schema is additive in
`Modules/EIS/Database/Migrations/2026_10_23_234000_create_eis_stock_operations.php`.
Apply it only after coordinating deployment with the stock reconciliation work.
After deployment, apply the EIS migrations before enabling stock operations. Verify the integration with
`php tests/Standalone/eis_stock_outbound_checks.php`; the test uses a fake EIS
HTTP transport and in-memory SQLite. Run the queue worker with
`php artisan queue:work --queue=eis-stock-operations` to process the outbox.

Administrators may record a final EIS portal verification for unknown, partial,
or awaiting-approval operations, with required notes/reference and confirmation
that no request or approval remains pending. The `verified` header keeps actor,
time and notes; original per-line results and immutable requests remain unchanged.
Verification sends no EIS request and allows subsequent authoritative local
reconciliation. Submitted/verified source adjustments remain immutable.
