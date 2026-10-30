## About Ultimate POS

Ultimate POS is a POS application by [Ultimate Fosters](http://ultimatefosters.com), a brand of [The Web Fosters](http://thewebfosters.com).

## Installation & Documentation
You will find installation guide and documentation in the downloaded zip file.
Also, For complete updated documentation of the ultimate pos please visit online [documentation guide](http://ultimatefosters.com/ultimate-pos/).

### CMS homepage assets

After restoring or deploying an installation with the CMS module, run
`php artisan module:publish Cms` to publish its CSS, JavaScript, and images.
The generated assets under `public/modules/cms` are not tracked in Git.
When published copies are missing, `/modules/cms/...` falls back to the module's
tracked source files through an explicit six-file allowlist. No other module
files can be served by that route. CSS/JS URLs include a source-content hash
to invalidate browser caches when those files change. Publishing remains
recommended so the web server can serve assets directly without PHP.
Run `php tests/Standalone/cms_asset_checks.php` to test the fallback independently
of published assets.

The CMS homepage uses the MbiraERP royal-blue, navy, and gold palette in
`Modules/Cms/Resources/assets/css/homepage.css`. Its styles are scoped to
`.cms-home` so other CMS pages retain their existing theme. After changing
CMS assets, run the publishing command again; no Node build is required.

Pricing, login, registration, and contact page styling is shared in `public/brand-public.css`
and scoped to `.mbira-public`. Contact also reuses the CMS homepage theme.
Package, authentication, and contact form endpoints remain unchanged.
The public repair-status lookup also uses the shared light layout, navigation,
footer, and constrained Branding logo. Its search options and results remain
unchanged; failed requests show inline feedback and re-enable the search button.

Home, pricing, contact, and login share `layouts.partials.public-navigation`:
the brand stays left and links stay right, with a right-aligned mobile menu.
The header displays the Superadmin Branding logo without a separate app name,
falling back to the default logo only when no branding logo is configured.
The public header has no language selector; the default application locale is English.
CMS navigation, page/blog links, and signup buttons use root-relative URLs.
CMS button settings are application destinations: existing absolute links are
normalized on read and new saves persist relative paths, including removal of
the legacy `/public` prefix. Query strings and fragments are preserved, and
subdirectory deployments use the current request base. Social media, email,
and telephone links retain their external destinations.
Run `php tests/Standalone/cms_relative_links_checks.php` for isolated coverage.
Public pricing cards keep prices and signup actions visible while plan
features open in a scrollable modal; subscription screens retain their full feature lists.
These pages also use `layouts.partials.public-footer`, including navigation,
configured social links, copyright, and any custom branding footer text.
Registration retains its existing three-step wizard, validation, package selection,
and registration-enabled setting. Styling does not enable public registration.
Registration and backend business, location, and Superadmin settings support
only Malawi, Malawian Kwacha (MWK), and the Africa/Blantyre time zone.
Server-side validation rejects other values. Registration uses +265 phone-number
hints; specific address and phone details remain user-entered. Existing records
are not migrated; saving these settings explicitly applies the Malawi-only options.

### Self-registration verification

Before the business wizard, self-registration requires a verified email address
or Malawi phone number. Configure **Superadmin Settings > Registration
verification**: email uses the application's **Email (SMTP)** settings, and SMS
reuses the **Business Settings > SMS** fields (Nexmo, Twilio, or a custom HTTP
gateway). Both channels start disabled until configured. Registration SMS
credentials are encrypted in the system settings and never copied to tenant
businesses. Log, array, and fallback mail transports cannot deliver registration
codes. Custom SMS gateways must return an HTTP error when they reject a message;
Nexmo response status is also checked.

Codes contain 6 digits, expire after 5 minutes, and allow at most 5 guesses.
Resends wait 60 seconds; delivery is limited to 5 requests per contact per hour
and 10 per IP per hour. Failed delivery attempts also count toward those limits.
Verification has an additional IP limit of 30 attempts per 10 minutes.
OTP hashes and single-use proofs use the server cache; use a persistent cache
with atomic lock support (file or Redis), shared across nodes if load balanced.
Do not use the array/null cache driver in production.

A verified contact is locked into the wizard and checked again on submission.
Verification expires after 30 minutes and is consumed after a successful
registration. Package selection survives the verification step. Changing a
contact requires a new code. This flow does not change `ALLOW_REGISTRATION`,
and Superadmin-created businesses do not require a self-registration OTP.
The legacy `/register` URL redirects to this flow; its generic registration
POST is disabled so it cannot bypass verification.

Run `php -d error_reporting=22527 tests/Standalone/registration_otp_checks.php`
for isolated verification, expiry, retry, delivery-failure, and gate checks;
the test sends no real emails or SMS.

## Backend profile menu

The backend navbar profile menu shows the user's uploaded profile photo, or
local initials when no photo is set, alongside their name. It links to My Profile,
Dashboard, and Sign Out, and closes on Escape or an outside click.

## Service staff location permissions

Service staff lookups use existing `web` permissions for `location.<id>` or
`access_all_locations`. Missing permission definitions are logged without
interrupting the lookup or granting access. If both definitions are missing,
no staff are returned. Restore missing definitions and explicitly assign the
intended access through location/user administration; the lookup does not
create permissions or change assignments.

Run `php tests/Standalone/service_staff_permission_checks.php` for isolated
coverage of missing definitions, guard/tenant isolation and direct/role grants.

## Security Vulnerabilities

If you discover a security vulnerability within ultimate POS, please send an e-mail to support at thewebfosters@gmail.com. All security vulnerabilities will be promptly addressed.

## License

The Ultimate POS software is licensed under the [Codecanyon license](https://codecanyon.net/licenses/standard).
