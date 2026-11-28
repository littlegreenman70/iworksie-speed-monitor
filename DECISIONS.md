# Decisions

1. **Name "Speed Monitor", not "PageSpeed Monitor".** "PageSpeed" is a Google product name; the readme says "uses Google PageSpeed Insights" instead (build plan §2).
2. **Calls Google directly from the site.** No relay service, so the privacy story is simple: only the page address (and the user's own API key) goes to Google. Documented in readme "External services" as wordpress.org requires.
3. **Nothing is sent until the user acts.** Scheduled tests are off by default; the first test is a "Test now" click. Avoids "phoning home" concerns in plugin review.
4. **API key optional but recommended.** Keyless PSI requests are heavily rate-limited, especially on shared hosting IPs. 429 responses get a plain message pointing to Settings. A `IWIESP_PSI_API_KEY` constant is supported for people who keep secrets in wp-config.php. The saved key is never printed back (masked placeholder) and is redacted from any stored error.
5. **One test per request, everywhere.** Manual runs are one AJAX request per URL+device; scheduled runs queue every URL+device and process one per cron tick (30 s apart). A PSI call takes 15–60 s, so batching would hit PHP/proxy timeouts, which is a known complaint about competitors.
6. **Retry once.** Rate limit (429), 5xx and network errors get one retry 5 minutes later. Other failures (bad key, page not reachable) are stored as failed tests and shown, not retried.
7. **Custom table, small rows.** History is many rows over time, so a table (not options). Only the score, 5 lab metrics, 5 field metrics, field scope/category and the top 3 fixes are stored; the full Lighthouse JSON (hundreds of KB) is never stored. Default retention 365 days, pruned at the start of each scheduled run. The table is created on activation and lazily on version change (covers multisite network activation).
8. **Field vs lab always labelled.** Each device column has a "Real visitors / Field data" block (CrUX p75, says whether it is this page or whole-site fallback, or that there is not enough data) and a separate "Lab test / Simulated" block. The performance score is labelled "(lab)".
9. **Alerts: drop of N points (default 10) or crossing below a floor (default 50).** Lab scores wobble ±5, so the default drop is 10. Crossing the floor alerts once, not every run while low. Only scheduled runs alert; one digest email per run.
10. **Top fixes deduplicated by topic.** Lighthouse 12+ reports both classic audits and newer "insights" for the same problem. Audit IDs map to ~20 topics with plain-English why/fix and who usually fixes it (theme/plugin, hosting, content). Unknown audits fall back to Lighthouse's own title/description. The "iWorks can fix them" link only shows when a theme/plugin topic is in the top fixes.
11. **Only this site's own pages, max 10.** Stops the AJAX endpoint being used to test arbitrary sites and keeps API usage sane. The manual-test endpoint only accepts URLs already saved in settings.
12. **Core Checks registry not used.** The build plan listed Speed Monitor as a Checks user, but speed results are a time series per URL, not pass/fail checks, so the plugin uses only core's menu, page shell, UI helpers and assets. A Site Health bridge can be added later if wanted.
13. **Capability `manage_options`** (per site, also on multisite where each site has its own table and settings).
14. **Migrated sites.** Saved URLs are not rewritten if the site's domain changes; a search-replace during migration updates them, and pages on a foreign host are rejected when settings are next saved.
15. **Prefix is `iwiesp` / `IWIESP_`** (sp = Speed Monitor; `iwiesm` belongs to SMTP).

### 2026-11-28 plurals / final pass
- Plural forms for finished-with-failures JS string via `wp.i18n._n` / singular+plural localize; `wp-i18n` dependency + `wp_set_script_translations`.

