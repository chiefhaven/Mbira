## About Ultimate POS

Ultimate POS is a POS application by [Ultimate Fosters](http://ultimatefosters.com), a brand of [The Web Fosters](http://thewebfosters.com).

## Installation & Documentation
You will find installation guide and documentation in the downloaded zip file.
Also, For complete updated documentation of the ultimate pos please visit online [documentation guide](http://ultimatefosters.com/ultimate-pos/).

### CMS homepage assets

After restoring or deploying an installation with the CMS module, run
`php artisan module:publish Cms` to publish its CSS, JavaScript, and images.
The generated assets under `public/modules/cms` are not tracked in Git.
If the homepage is unstyled and requests to `/modules/cms/css/cms.css` return
404, run this command and refresh the browser.

The CMS homepage uses the MbiraERP royal-blue, navy, and gold palette in
`Modules/Cms/Resources/assets/css/homepage.css`. Its styles are scoped to
`.cms-home` so other CMS pages retain their existing theme. After changing
CMS assets, run the publishing command again; no Node build is required.

Pricing, login, and contact page styling is shared in `public/brand-public.css`
and scoped to `.mbira-public`. Contact also reuses the CMS homepage theme.
Package, authentication, and contact form endpoints remain unchanged.

Home, pricing, contact, and login share `layouts.partials.public-navigation`:
the brand stays left and links stay right, with a right-aligned mobile menu.
Public pricing cards keep prices and signup actions visible while plan
features open in a scrollable modal; subscription screens retain their full feature lists.
All four pages use `layouts.partials.public-footer`, including navigation,
configured social links, copyright, and any custom branding footer text.

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
