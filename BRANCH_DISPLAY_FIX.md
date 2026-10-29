# Branch Display Fix - EIS Terminal Settings

## Problem
The EIS terminal settings page was showing `Branch: N/A` instead of displaying the business location name.

## Root Cause Analysis

### Issue Discovery Process
1. The frontend view (`settings_mra_eis.blade.php`) had `<td id="branch">N/A</td>` element
2. The `TerminalActivationController.details()` method was returning a `branch` field
3. However, the `/api/v1/terminal/status/{businessId}` endpoint (which was being called by JavaScript) wasn't returning the branch
4. The `status()` endpoint called `getStatus()` service method
5. The `getStatus()` method called `getTerminalDetails()` which didn't include the `branch` field

### Data Flow
```
Frontend JS: getTerminalStatus()
  ↓ (calls)
GET /api/v1/terminal/status/{businessId}
  ↓ (routes to)
TerminalActivationController.status()
  ↓ (calls)
EisTerminalActivationService.getStatus()
  ↓ (calls)
getTerminalDetails()  ← MISSING BRANCH FIELD
```

## Solution Implemented

### Backend Changes

#### 1. **EisTerminalActivationService.php**

**Modified `getTerminalDetails()` method signature:**
```php
// Before:
private function getTerminalDetails(EisTerminalConfiguration $terminal, ?object $activatedTerminal = null): array

// After:
private function getTerminalDetails(EisTerminalConfiguration $terminal, ?object $activatedTerminal = null, ?int $businessId = null): array
```

**Added branch lookup logic:**
```php
// Add business location branch if businessId is provided
if ($businessId) {
    $businessLocation = \App\BusinessLocation::where('business_id', $businessId)->first();
    $details['branch'] = $businessLocation?->name ?? null;
}
```

**Updated all method calls to pass businessId:**
- `getStatus()` → `$this->getTerminalDetails($terminal, null, $businessId)`
- `deactivate()` → Two calls updated to include businessId
- `toggle()` → Updated to include businessId
- `syncTerminal()` → Updated to include businessId
- `processActivationResponse()` → Updated to include businessId

### 2. **settings.blade.php**

**Updated `updateTerminalUI()` JavaScript function:**
```javascript
// Added line to update branch display
$('#branch').text(data.branch || 'N/A');
```

## Impact

✅ **Terminal Status Endpoint**: Now returns branch field from business location
✅ **Frontend Display**: Branch field properly populated in EIS settings
✅ **All Endpoints**: All methods returning terminal details now include branch
✅ **Backward Compatible**: All businessId parameters are optional with null defaults

## API Response Structure

The `/api/v1/terminal/status/{businessId}` endpoint now returns:
```json
{
  "success": true,
  "is_active": true,
  "is_confirmed": true,
  "data": {
    "id": 1,
    "terminal_label": "Terminal 1",
    "terminal_id": "TRM001",
    "trading_name": "Business Name",
    "branch": "Area 13",
    ...
  }
}
```

## Files Modified

1. **Modules/EIS/Services/Terminal/EisTerminalActivationService.php**
   - Added `?int $businessId = null` parameter to `getTerminalDetails()`
   - Added branch lookup logic
   - Updated 5 method calls to pass businessId

2. **resources/views/business/settings.blade.php**
   - Updated `updateTerminalUI()` to populate branch field

## Testing Checklist

- [ ] Navigate to Business → Settings → EIS Tab
- [ ] Verify Branch field shows business location name (not N/A)
- [ ] Activate a new terminal and verify Branch displays
- [ ] Call `/api/v1/terminal/status/{businessId}` and verify response includes branch
- [ ] Test on page load (automatic status fetch)
- [ ] Test after manual activation
- [ ] Test after "Get Latest Config" button click

## Related Files

- Frontend view: `resources/views/business/partials/settings_mra_eis.blade.php`
- Business model: `app/BusinessLocation.php`
- Terminal controller: `Modules/EIS/Http/Controllers/API/TerminalActivationController.php`
