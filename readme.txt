=== iWorks.ie Speed Monitor ===
Contributors: littlegreenman
Tags: pagespeed, core web vitals, performance, speed test, monitoring
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track your site's speed over time with scheduled tests, history, drop alerts and plain-English fixes. Uses Google PageSpeed Insights.

== Description ==

iWorks.ie Speed Monitor tests your most important pages on a schedule and keeps the history, so you can see when your site got slower and why. It uses Google PageSpeed Insights, called directly from your own server.

**Real visitors and lab tests, clearly labelled.** Every number says where it comes from:

* **Real visitors (field data):** how fast the page actually was for Chrome users over the last 28 days, from Google's Chrome UX Report. This is what Google uses for Core Web Vitals. The plugin tells you whether the numbers are for the page itself or the whole site, and says so plainly when there is not enough traffic for Google to report them.
* **Lab test (simulated):** one simulated visit run by Google during the test. Great for spotting changes after an update, but it moves a few points between runs.

**Features**

* Monitor up to 10 pages, on mobile, desktop, or both
* Daily or weekly scheduled tests, run one page at a time in the background so nothing times out
* Score history with trend lines and a full history table
* Email alerts when a score drops by a set number of points or falls below a level you choose (one digest email per run)
* Top 3 fixes per page in plain English: why it matters, how to fix it, and whether it is usually a theme/plugin, hosting or content job
* CSV export of your full history
* History survives plugin updates; old tests are removed after the period you choose (default one year)
* Works without an API key; add your own free Google API key for reliable testing

Everything is free. No upsells, no locked features, no account.

Need a custom plugin, or help making your site faster? iWorks builds them: https://www.iworks.ie

== External services ==

This plugin connects to the **Google PageSpeed Insights API** (`https://www.googleapis.com/pagespeedonline/v5/runPagespeed`) to measure page speed. That is the core feature of the plugin and nothing is sent until you start a test or turn on scheduled tests.

* **When:** when you click "Test now", and on the schedule you turn on in Settings.
* **What is sent:** the address of the page being tested, the device type (mobile or desktop), your site's language (so results come back in it), and your Google API key if you added one. No visitor data, user data or site content is sent.
* **Who runs it:** Google LLC. [Google APIs Terms of Service](https://developers.google.com/terms) and [Google Privacy Policy](https://policies.google.com/privacy).

The plugin does not send any data to iWorks or any other party, and does not track you.

== Installation ==

1. Upload the `iworksie-speed-monitor` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New.
2. Activate the plugin.
3. Open **iWorks Tools → Speed Monitor** and click **Test now**.
4. In the **Settings** tab, choose your pages, turn on scheduled tests and alerts, and (recommended) add a free Google API key.

== Frequently Asked Questions ==

= Do I need a Google API key? =

No, but it is recommended. Google limits tests without a key, and on shared hosting those limits are often already used up by other sites on the same server. A key is free and takes a couple of minutes to create: see [Google's instructions](https://developers.google.com/speed/docs/insights/v5/get-started). You can also define `IWIESP_PSI_API_KEY` in `wp-config.php`.

= Why are there two sets of numbers? =

"Real visitors" is what your actual Chrome visitors experienced over the last 28 days. "Lab test" is a single simulated visit Google ran just now. Real-visitor numbers are what matter for Google Search; lab numbers react immediately to changes, so they are useful for spotting a slowdown after an update.

= Why does it say there is not enough real-visitor data? =

Google only publishes real-visitor data once enough Chrome users have visited the page (or the site). Smaller sites often only have site-wide data, or none at all. The lab test still works.

= Why did my score change when I did not change anything? =

Lab scores vary a few points between runs because of network and server conditions. That is why alerts default to a drop of 10 points or more.

= Can I test a local or staging site? =

No. Google has to reach the page over the internet, so local, staging and password-protected sites cannot be tested.

= Will scheduled tests slow my site down? =

No. Tests run on Google's servers. Your site only makes one small request to Google every 30 seconds or so during a run, in the background.

= Scheduled tests are not running on time =

WordPress cron only runs when someone visits the site. On quiet sites, set up a real server cron job that calls `wp-cron.php`, or ask your host.

= Can I monitor pages on other websites? =

No. Only pages on your own site can be added.

= What is removed when I delete the plugin? =

Everything it created: the history table, its settings, your saved API key, and its scheduled events.

== Screenshots ==

1. Overview: score, trend, real-visitor and lab numbers for each page and device.
2. Top fixes in plain English.
3. History table with CSV export.
4. Settings: pages, schedule, alerts and API key.

== Changelog ==

= 1.0.0 =
* First release.
