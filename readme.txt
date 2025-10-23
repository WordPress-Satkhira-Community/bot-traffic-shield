=== Bot Traffic Shield - Block Bad Bots and Stop Ai Bots Crawlers ===
Contributors: wpdelower,monarchwp23
Tags: Bad Bots, block bots, fail2ban, Stop Bots, AI Spider
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful and user-friendly plugin to block AI crawlers and malicious data scraper bots, protecting your content and server resources.

== Description ==

In the age of AI, your valuable website content is a prime target for data crawlers from large tech companies. **Bot Traffic Shield** is your first line of defense. This lightweight yet powerful plugin identifies and blocks a wide range of AI bots and data scrapers before they can access and harvest your content.

Protect your intellectual property, reduce server load, and take back control of who can access your site.

**Key Features:**

*   **Real-time Blocking:** Actively blocks bots by their User-Agent on every page request.
*   **robots.txt Integration:** Automatically adds `Disallow` rules to your virtual `robots.txt` file as a polite deterrent.
*   **Comprehensive Blocklist:** Comes with a built-in, curated list of common AI crawlers (like ChatGPT-User, Google-Extended, Bytespider, Applebot) that is regularly updated.
*   **Customizable:** Easily add your own custom User-Agent strings to the blocklist.
*   **Logging & Statistics:** Keep an eye on who you're blocking with a simple, clean log and a running count of blocked requests.
*   **Modern & Clean UI:** A beautiful and intuitive settings page with toggle switches makes configuration a breeze.
*   **Lightweight & Performant:** Designed to have a minimal impact on your site's performance.

Stop letting AI companies train their models on your hard work. Install Bot Traffic Shield and protect your content today!

== Installation ==

1.  Upload the `bot-traffic-shield` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in your WordPress dashboard.
3.  Go to **Settings > Bot Traffic Shield** to configure the plugin. By default, blocking is enabled upon activation.

== Frequently Asked Questions ==

= Will this affect my SEO or normal Googlebot? =

No. This plugin specifically targets AI data crawlers and other known bad bots. It does **not** block standard search engine crawlers like Googlebot or Bingbot, so your SEO will not be negatively impacted.

= Which bots does it block by default? =

It blocks a curated list including ChatGPT-User, Google-Extended, GPTBot, CCBot, Bytespider, Amazonbot, Applebot, and many others. You can see the full default list on the 'Default Blocklist' tab in the plugin settings.

= How do I add a bot to the blocklist? =

Go to the plugin's settings page, and in the 'Custom User Agents to Block' box, add the User-Agent identifier you wish to block. Add one per line.

= What's the difference between User-Agent blocking and robots.txt? =

The `robots.txt` file is a polite request that well-behaved bots follow. The User-Agent blocking is a hard block that forcibly prevents access, which is necessary for malicious or disobedient bots. This plugin uses both methods for maximum protection.

== Screenshots ==

1. The main settings tab with modern toggle switches.
2. The block log and statistics tab, showing recently blocked bots.
3. The default blocklist tab, showing the curated list of User-Agents blocked by default.

== Changelog ==

= 1.0.1 (23 October,2025) =
* Bug Fix
* Performance Update

= 1.0.0 =
*   Initial release.
