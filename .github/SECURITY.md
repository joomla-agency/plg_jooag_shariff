# Security policy

## Supported versions

| Version | Joomla | Support |
|---|---|---|
| 8.0.x | 5, 6 | Security fixes only. 8.0.1 is the last regular version; JooAg Shariff ends with Joomla 7. |
| 7.0.0 and older | 3, 4, 5 | None – on Joomla 5 and 6 update to 8.0.1, on Joomla 3 and 4 see below. |

## Reporting a vulnerability

Please do not open a public issue. Use **Report a vulnerability** on the Security tab of this repository; the report
stays private until a fix is out. Fixes come as a new 8.0.x version: in Joomla's update with a security level
(Joomla 5.4.10+ and 6.2+ show it), in the release notes and in a security advisory of this repository.

## Fixed in 8.0.1 (security level medium)

All versions before 8.0.1 are affected:

* **4.0.0 – 7.0.0:** the public Shariff backend `plugins/system/jooag_shariff/backend` with outdated bundled
  libraries (Guzzle 6.3.3, guzzlehttp/psr7 1.5.2, Zend Cache 2.8), reachable by anyone. Its `shariff.json` held the
  Facebook app secret, protected only by an `.htaccess` in Apache 2.2 syntax – servers that ignore `.htaccess`
  (nginx, for example) delivered it. Short tag options went into the HTML without escaping (attribute injection by
  content authors). Fixed in 8.0.0: the backend is gone, the installer deletes it, all output is escaped.
* **8.0.0:** the built-in share counter answered for any address on the site's domain, so anyone could make the
  server query the networks for made-up addresses. Fixed in 8.0.1: the page signs its address with the site's
  secret key and the counter only answers for signed addresses, within about 10 seconds per request.

If you entered a Facebook app secret in a version before 8.0.0 and your server may ignore `.htaccess` files, renew
the secret in Facebook's app dashboard.

### Joomla 3 and 4 (version 6.0.0 and older)

There is no fixed version for Joomla 3 and 4. Best: update the site to Joomla 5 and install 8.0.1. Until then:

1. In the plugin settings switch the share counter off, remove the Facebook app secret from the Facebook row of the
   service list and save.
2. Then delete the folder `plugins/system/jooag_shariff/backend`.

The buttons keep working, only the counts are gone. Saving the plugin again later writes a
`backend/shariff.json` without the secret.
