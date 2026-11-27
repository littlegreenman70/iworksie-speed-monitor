# Progress

## v1.0.0 (first release candidate)

Done:
- [x] Plugin built on `iworksie/tools-core` 1.1.2 (Strauss-prefixed, shared iWorks Tools menu)
- [x] Settings: pages (max 10, own site only), devices, daily/weekly schedule, alerts, retention, API key
- [x] PSI client + parser (score, lab metrics, CrUX field metrics with page/origin scope, top 3 fixes)
- [x] History table, CSV export, delete history, retention pruning
- [x] Cron queue (one test per tick, retry once on 429/5xx, lock), digest alert email
- [x] Overview / History / Settings screens, sparkline trend, plain-English fixes
- [x] uninstall.php removes table, options, transients, cron events (multisite aware)
- [x] PHPCS (WPCS + PHPCompatibilityWP 7.4+) clean, PHPStan level 6 clean
- [x] 37 unit tests, 28 integration tests
- [x] Verified locally: unit + integration on PHP 7.4, 8.1, 8.3, 8.4 against WordPress 6.5.7 and 7.1.5
- [x] Built zip lints on PHP 7.4; Plugin Check: no errors
- [x] Browser walkthrough on WP 6.5.7/PHP 7.4 and WP 7.1.5/PHP 8.4 (fake PSI responses): no JS errors, no PHP notices from the plugin

Before wordpress.org submission:
- [ ] Run against the live Google API with a real key on iworks.ie (only faked responses were possible in the build sandbox)
- [ ] Real screenshots for `.wordpress-org/` (screenshot-1..4, taken on a real site, not fixtures)
- [ ] Banner (772x250, 1544x500) and icon (128, 256), same as Health Check
