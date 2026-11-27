# iWorks.ie Speed Monitor

Free WordPress plugin: scheduled speed tests of your own pages using Google PageSpeed Insights, with history, drop alerts and plain-English fixes. Real-visitor (field) and lab numbers are always labelled.

Part of the **iWorks Tools** suite (shared menu via [`iworksie/tools-core`](https://github.com/littlegreenman70/iworks-tools-core), bundled with Strauss).

- Slug / text domain: `iworksie-speed-monitor`
- Namespace: `IWorksIE\SpeedMonitor\` (PSR-4 from `src/`)
- Prefix: `iwiesm_` / `IWIESM_`
- Requires WordPress 6.5+, PHP 7.4+

## Development

Clone next to `iworks-tools-core` (the path repository in `composer.json`), then:

```bash
composer install          # also runs Strauss -> vendor-prefixed/
composer lint             # PHPCS: WordPress standards + PHPCompatibilityWP 7.4+
composer analyse          # PHPStan level 6
composer test:unit
bash bin/install-wp-tests.sh iworks_tests_sm root '' 127.0.0.1 latest
composer test:integration # needs MySQL
composer build            # build/iworksie-speed-monitor(.zip)
```

Code must stay PHP 7.4 compatible: no PHP 8-only syntax (named arguments, `match`, union types, nullsafe, enums, readonly, constructor promotion). CI lints the built plugin on PHP 7.4 and runs the unit and integration suites on a real PHP 7.4 runtime.

## How it works

| Piece | File |
|---|---|
| Google API call (one URL + one device per request, key redacted from errors) | `src/Psi/Client.php` |
| Response → small stored row (score, lab + field metrics, top 3 fixes) | `src/Psi/Parser.php` |
| Plain-English advice per Lighthouse audit/insight topic | `src/Psi/Advice.php` |
| History table `{prefix}iwiesm_results` | `src/Storage/Results.php`, `src/Installer.php` |
| Schedule: recurring event fills a queue, single events run one test every 30 s, retries 429/5xx once | `src/Scheduler.php` |
| Drop alerts, one digest email per run | `src/Alerts.php` |
| Admin screens (Overview / History / Settings), AJAX and form handlers | `src/Admin/` |

See `DECISIONS.md` for the reasoning behind the main choices and `PROGRESS.md` for status.
