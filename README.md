## About MbiraERP

MbiraERP is a business management and POS application maintained by HavenPlus.

## Installation & Documentation
Use the installation guide included with your distribution for initial setup.
The sections below document MbiraERP deployment, public pages, registration,
and related configuration. Contact HavenPlus support for assistance.

### Git-pull deployments: required styles

The distributable styles in `public/css/app.css`, `public/css/vendor.css`,
`public/css/rtl.css`, and `public/css/tailwind/app.css`, plus the Bootstra p
Glyphicons and Ionicons files in `public/fonts`, must be committed and deployed
with the application. They are intentionally exempt from `.gitignore` so a
Git-pull deployment includes the styles used by login, registration, and the
backend. `public/css/init.css` remains an ignored intermediate build output.

Do not delete `public/css` during deployment. The existing Mix production build
generates vendor/RTL styles but does **not** recreate `app.css` or the distributed
Tailwind stylesheet; running `npm run production` alone is not a full restore.
Commit updated distributable styles whenever they change.

After pulling a release containing these assets, run `php artisan view:clear`
and publish CMS assets as described below. Verify `/css/tailwind/app.css`,
`/css/vendor.css`, and `/css/app.css` each return HTTP 200 with a `text/css`
content type from the production hostname, then hard-refresh the browser.
Clearing Laravel or browser caches cannot fix missing files (HTTP 404).

### Icon fonts

The compiled vendor stylesheet uses Font Awesome Free 5.15.4 solid and brand
fonts at `public/fonts/vendor/@fortawesome/fontawesome-free/webfa-*`.
These distributable fonts, regular fonts, and their license are tracked in Git.
Deploy the entire directory: CSS without its font files produces missing icons.
Shared backend/auth/guest layouts also load `public/css/icon-fonts.css` to enable
the regular (`far`) icon weight, omitted from the original compiled stylesheet.
Future Sass builds include that weight too. CMS pages retain their existing
Font Awesome CDN stylesheet.

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
The post-verification business wizard has a gold-accented workspace card,
verified-contact banner and blue step navigation. All fields in the active step
remain visible with normal page scrolling, without an inner scroll area or
height cap. Wizard actions follow the fields. Styling is scoped to public signup;
shared administrator forms are unchanged. The stylesheet is content-versioned.
Registration and backend business, location, and Superadmin settings support
only Malawi, Malawian Kwacha (MWK), and the Africa/Blantyre time zone.
Server-side validation rejects other values. Registration uses +265 phone-number
hints; specific address and phone details remain user-entered. Existing records
are not migrated; saving these settings explicitly applies the Malawi-only options.

### Public CMS SEO

CMS pages and blogs share escaped titles, descriptions, canonical URLs,
Open Graph and Twitter cards. Existing page meta descriptions take precedence;
otherwise a plain-text content excerpt is used. Featured images take precedence
over the Branding logo. Canonicals exclude query strings and use each record's
current slug, including when a blog is accessed through an older slug.

`/sitemap.xml` lists the homepage, contact page, blog index, and enabled CMS
pages/blogs with their last update times. Testimonials, disabled content and
administration/authentication URLs are excluded. Disabled custom pages return
404. `/robots.txt` is generated by Laravel with the current deployment's sitemap
URL and crawl exclusions for account/administration screens; these rules are
not access controls. Both endpoints require requests to reach Laravel.

On cPanel, deploy the deletion of the old static `public/robots.txt` too:
otherwise the web server serves that stale file instead of the dynamic route.
Use the preferred HTTPS hostname consistently and configure HTTPS/proxy handling
correctly, since canonical and sitemap URLs use Laravel's URL generator.
Keep CMS custom meta tags for verification/other additions, not duplicate
description, canonical, Open Graph or Twitter tags.
Run `php tests/Standalone/cms_seo_checks.php` for isolated SEO checks.

### Self-registration verification

#### Registration contacts

Public registration does not block visitors by IP country. No GeoLite2 database
or MaxMind PHP extension is required. After deploying removal of the country
gate, run `php artisan route:clear` and `php artisan config:clear` on cPanel to
remove cached references to the old middleware and configuration.

Email OTP remains available, but completing registration requires a valid Malawi
phone number. Optional alternate phone numbers must also be Malawi numbers.
Numbers are saved in E.164 (+265) form. Existing email addresses (case-insensitive)
and phone numbers are rejected before sending OTP, when verifying it, and on
final submission. Checks cover all user accounts (including deleted accounts),
user contact/alternate numbers, and business-location mobile/alternate numbers,
including common local and international formatting. Final public submissions
lock normalized contacts across sessions to prevent concurrent reuse; use the
shared persistent atomic-lock cache described below. Administrator forms are
not changed, and no existing contacts are migrated.

The contact verification screen uses a responsive blue-and-gold setup panel,
with distinct contact-entry, code-entry, error and unavailable states. Mobile
layouts collapse the introductory panel while retaining the form and step label.
Its stylesheet URL is content-versioned so deployments refresh the design.

Before the business wizard, self-registration requires a verified email address
or Malawi phone number. Configure **Superadmin Settings > Registration
verification**: email uses the application's **Email (SMTP)** settings, and SMS
reuses the **Business Settings > SMS** fields (Nexmo, Twilio, or a custom HTTP
gateway). Both channels start disabled until configured. Registration SMS
credentials are encrypted in the system settings and never copied to tenant
businesses. Log, array, and fallback mail transports cannot deliver registration
codes. Nexmo response status is checked. Custom SMS OTP requests reject redirects,
HTML/login responses, malformed JSON, and JSON `status: error/failed/failure/fail`
or `success: false` even when the gateway returns HTTP 200. Other custom
gateway formats must reject failed requests with HTTP errors. Acceptance by
SMTP or an SMS API does not guarantee delivery; check the provider's delivery
reports for rejected/queued messages.

Registration uses the SMS fields in **Superadmin Settings > Registration
verification**, not an individual business's SMS settings. For MbiraNotify's
`/api/http/sms/send` endpoint, configure the Other service as follows:

| Setting | Value |
| --- | --- |
| URL | `https://mbiranotify.havenplustechnologies.co.mw/api/http/sms/send` |
| Method / data type | POST / JSON |
| Send-to parameter / type | `recipient` / String |
| Message parameter | `message` |
| Header 1 | `Accept` = `application/json` |
| Header 2 | `Content-Type` = `application/json` |
| Parameter 1 | `api_token` = your gateway token |
| Parameter 2 | `sender_id` = your gateway-approved sender |
| Parameter 3 | `type` = `plain` |

Enable SMS OTP and save. `to` is not a valid recipient parameter for this
gateway. Enter credentials only in the authenticated settings form.
Never paste API tokens or verification codes into logs or
support messages. Email delivery uses the separate SMTP configuration; on cPanel
check **Track Delivery**, including rejection and deferral details.

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

## Form submission protection

Shared application, authentication, guest, CMS and Accounting layouts load
`public/js/form-submission-guard.js`. Valid native form submissions disable
submit controls until navigation; AJAX and fetch requests initiated by form
handlers restore controls on completion, including failures. Repeated clicks,
Enter submissions and programmatic native submits are blocked while pending.
Invalid/cancelled submissions and remote field validation do not lock the form.
Existing disabled states and native form payloads are preserved; returning via
browser Back restores navigation-locked controls.

Custom workflows that launch requests later (for example after an asynchronous
confirmation dialog) must retain their own busy handling. Standalone pages
outside the shared layouts must include the shared guard partial after jQuery.
Run `node tests/Standalone/form_submission_guard_checks.js` for isolated checks.

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

If you discover a security vulnerability within MbiraERP, please contact HavenPlus support. All security vulnerabilities will be promptly addressed.

## License

The MbiraERP software is licensed under the [Codecanyon license](https://codecanyon.net/licenses/standard).
