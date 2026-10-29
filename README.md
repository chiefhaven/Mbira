## About Ultimate POS

Ultimate POS is a POS application by [Ultimate Fosters](http://ultimatefosters.com), a brand of [The Web Fosters](http://thewebfosters.com).

## Installation & Documentation
You will find installation guide and documentation in the downloaded zip file.
Also, For complete updated documentation of the ultimate pos please visit online [documentation guide](http://ultimatefosters.com/ultimate-pos/).

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
