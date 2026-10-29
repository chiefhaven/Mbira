# is_fiscalized Field - Quick Reference

## What Was Added

### Migration
**File**: `Modules/EIS/Database/Migrations/2026_10_22_141000_add_is_fiscalized_to_eis_sales.php`

Two new columns added to `eis_sales` table:
```sql
is_fiscalized BOOLEAN DEFAULT FALSE
fiscalized_at TIMESTAMP NULL
```

### Model Updates
**File**: `Modules/EIS/Models/EisSale.php`

#### Fillable Fields
Added to fillable array:
- `is_fiscalized`
- `fiscalized_at`

#### Casts
```php
'fiscalized_at' => 'datetime',
'is_fiscalized' => 'boolean',
```

#### New Methods
- `markAsFiscalized()` - Mark sale as fiscalized and set timestamp
- `isFiscalized()` - Check if sale is fiscalized
- `scopePendingFiscalization()` - Query pending sales
- `scopeFiscalized()` - Query fiscalized sales

## Quick Usage

### Mark as Fiscalized
```php
$sale = EisSale::find($id);
$sale->markAsFiscalized(); // Sets is_fiscalized=true, fiscalized_at=now()
```

### Check if Fiscalized
```php
if ($sale->isFiscalized()) {
    echo "Fiscalized";
}
```

### Query Scopes
```php
// Get pending sales
EisSale::pendingFiscalization()->get();

// Get fiscalized sales
EisSale::fiscalized()->get();
```

## Database Schema

```
eis_sales.is_fiscalized BOOLEAN DEFAULT FALSE
eis_sales.fiscalized_at TIMESTAMP NULL
```

## Status Flow

```
pending → submitted → is_fiscalized=true
  (failed) → status=failed, is_fiscalized=false
  (cancelled) → status=cancelled, is_fiscalized=false
```

## Migration

```bash
php artisan migrate
```

## Files Modified

1. `Modules/EIS/Database/Migrations/2026_10_22_141000_add_is_fiscalized_to_eis_sales.php` - NEW
2. `Modules/EIS/Models/EisSale.php` - UPDATED

## Integration Points

Update these services to call `$sale->markAsFiscalized()`:
- `Modules/EIS/Services/Sales/SaleSubmissionService.php`
- `Modules/EIS/Services/Sales/SaleResponseService.php`

Example:
```php
if ($response->isSuccess()) {
    $eisSale->markAsFiscalized(); // Add this line
}
```

## Verification

After migration:
```bash
# Check column exists
php artisan tinker
> Schema::hasColumn('eis_sales', 'is_fiscalized')
> true
```

---

**Status**: ✅ Ready to Deploy
