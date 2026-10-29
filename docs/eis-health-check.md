# EIS connectivity status

The header and sale HTTP clients share `EisHealthService`. Its authenticated
probe uses `POST /api/v1/utilities/ping`, accepting either a host-only EIS base
URL or one ending in `/api/v1`. A successful response must contain the EIS
success envelope and `data.serverDate`; HTML/login responses, HTTP errors and
connection failures are not considered healthy.

Healthy results are cached for 60 seconds; failed checks for 15 seconds. The
cache is business-specific and includes a hashed host/token identity, so token
rotation or host changes cause an immediate recheck without storing the token.
The header does not add another cache. Old eight-hour health entries are ignored
automatically. Reload the page after recovery to refresh the badge.

Connection timeout is three seconds and total probe timeout is five seconds,
without retries or redirects. Failure logs contain the business ID and exception
class only. `clearCache()` removes current and legacy health/header entries for
one business, without flushing unrelated application caches.

Run `tests/Standalone/eis_health_checks.php` for isolated cache/HTTP regressions.
It does not bootstrap the application, use saved credentials or contact live EIS.