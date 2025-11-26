=== Bot Traffic Shield - Block Bad Bots and Stop AI Bots Crawlers ===
Contributors: wpdelower,monarchwp23
Donate link: https://monarchwp.com/
Tags: Bad Bots, block bots, fail2ban, Stop Bots, AI Spider
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful and user-friendly plugin to block AI crawlers and malicious data scraper bots, protecting your content and server resources.

== Description ==

In the age of AI, your valuable website content is a prime target for data crawlers from large tech companies. **Bot Traffic Shield** is your first line of defense against content theft and unauthorized scraping.

This lightweight yet powerful plugin identifies and blocks a wide range of AI bots and data scrapers before they can access and harvest your content, protecting your intellectual property while reducing unnecessary server load.

### 🛡️ Why You Need Bot Traffic Shield

*   **Protect Your Content** - Stop AI companies from training their models on your hard work
*   **Reduce Server Load** - Block unwanted traffic that wastes your bandwidth and resources
*   **SEO-Safe Blocking** - Only blocks harmful bots; legitimate search engines like Google and Bing remain unaffected
*   **Take Control** - Decide who can and cannot access your valuable content

### ✨ Key Features

**Real-Time Bot Blocking**
*   Actively blocks bots by their User-Agent on every page request
*   Immediate protection with zero configuration needed

**Comprehensive Default Blocklist**
*   Pre-configured list of 20+ known AI crawlers and scrapers
*   Includes ChatGPT-User, Google-Extended, GPTBot, CCBot, Bytespider, Amazonbot, Applebot, and more
*   Regularly updated with new bot signatures

**Advanced Logging & Analytics**
*   Track every blocked bot attempt with detailed logs
*   View bot name, IP address, user agent, and timestamp
*   **Pagination system** - Browse through logs easily (20 entries per page)
*   Running statistics showing total blocked requests

**CSV Export Capability**
*   Export your block logs to CSV format
*   Filter exports by date range (7 days, 30 days, or all time)
*   Perfect for analysis, reporting, or compliance

**robots.txt Integration**
*   Automatically adds `Disallow` rules to your virtual robots.txt
*   Provides an additional layer of protection for well-behaved bots

**Fully Customizable**
*   Add your own custom User-Agent strings to block
*   Simple textarea interface - one bot per line
*   Enable/disable logging with a single toggle
*   Master on/off switch for all blocking features

**Modern, Intuitive Interface**
*   Beautiful, clean admin UI with tabbed navigation
*   Modern toggle switches and card-based design
*   Mobile-responsive admin panel
*   No learning curve - start protecting immediately

**Lightweight & Performance-Optimized**
*   Minimal impact on site speed
*   Efficient code that runs before page load
*   No external API calls or database queries on frontend

### 🎯 Who Is This Plugin For?

*   **Content Creators** - Protect your articles, tutorials, and creative work
*   **Bloggers** - Keep your unique content from being scraped
*   **News Sites** - Prevent unauthorized content aggregation
*   **E-commerce** - Protect product descriptions and pricing data
*   **Any WordPress Site** - That values their content and server resources

### 🚀 How It Works

1. Install and activate the plugin
2. Bot Traffic Shield immediately starts blocking known bad bots
3. Monitor blocked attempts in the logs
4. Add custom bots to block as needed
5. Export logs for analysis or record-keeping

**No complicated setup. No API keys. No subscriptions.**

### 🔒 Privacy & Security

*   All data stays on your server
*   No external services or third-party dependencies
*   GDPR compliant - you control all logged data
*   Logs can be cleared at any time by disabling logging

### 📊 Perfect For

✅ Reducing bandwidth costs  
✅ Protecting original content  
✅ Improving server performance  
✅ Maintaining competitive advantage  
✅ Preventing AI training on your data  

Stop letting AI companies profit from your hard work. Install Bot Traffic Shield and take back control of your content today!

== Installation ==

### Automatic Installation

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins > Add New**
3. Search for "Bot Traffic Shield"
4. Click **Install Now** and then **Activate**
5. Go to **Settings > Bot Traffic Shield** to configure (optional)

### Manual Installation

1. Download the plugin zip file
2. Upload the `bot-traffic-shield` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress
4. Navigate to **Settings > Bot Traffic Shield** to configure

### Post-Installation

*   Blocking is **enabled by default** upon activation
*   Logging is **enabled by default** to track blocked bots
*   Visit the settings page to customize your blocklist
*   Check the **Block Log & Stats** tab to see blocked bots in real-time

== Frequently Asked Questions ==

= Will this affect my SEO or normal search engines? =

**No.** Bot Traffic Shield specifically targets AI data crawlers and malicious scrapers. It does **not** block legitimate search engine crawlers like Googlebot, Bingbot, or other SEO-friendly bots. Your search rankings will remain completely unaffected.

= Which bots does it block by default? =

The plugin includes a comprehensive blocklist of 20+ known AI crawlers and scrapers, including:

*   ChatGPT-User
*   GPTBot
*   Google-Extended
*   CCBot
*   Bytespider
*   Amazonbot
*   Applebot
*   Claude-Web
*   Anthropic-AI
*   And many more...

You can view the complete default list in the **Default Blocklist** tab in settings.

= How do I add a custom bot to the blocklist? =

1. Go to **Settings > Bot Traffic Shield**
2. Scroll to **Custom User Agents to Block**
3. Enter the User-Agent string (one per line)
4. Click **Save Settings**

Example: If you want to block "BadBot/1.0", simply add that line to the textarea.

= How do I export my block logs? =

1. Go to **Settings > Bot Traffic Shield**
2. Click on the **Block Log & Stats** tab
3. Scroll to the **Export Logs (CSV)** section
4. Choose your date range (7 days, 30 days, or all time)
5. Click **Export CSV**

The CSV file will download automatically with all blocked bot details.

= Can I see which bots have been blocked? =

**Yes!** The **Block Log & Stats** tab shows:
*   Date and time of each block
*   Bot name
*   Full User-Agent string
*   IP address
*   Total count of blocked requests

Logs are paginated for easy browsing (20 entries per page).

= What's the difference between User-Agent blocking and robots.txt? =

*   **robots.txt** - A polite request that well-behaved bots follow (but can be ignored)
*   **User-Agent blocking** - A hard block that forcibly denies access

This plugin uses **both methods** for maximum protection. The robots.txt is for compliant bots, while User-Agent blocking stops aggressive or malicious bots that ignore robots.txt.

= Does this plugin slow down my website? =

**No.** Bot Traffic Shield is designed to be extremely lightweight:
*   Runs early in the WordPress load process
*   Minimal database queries
*   No external API calls
*   Negligible performance impact

In fact, by blocking unwanted bots, you'll likely see **improved** server performance.

= Can I temporarily disable blocking? =

**Yes.** Simply toggle the **Enable Bot Blocking** switch to OFF in the settings. You can re-enable it at any time without losing your custom configuration.

= Will I lose my logs if I disable logging? =

**Yes.** Disabling logging will clear all existing logs and stop recording new blocks. If you want to keep your logs, export them to CSV before disabling.

= Is this plugin compatible with caching plugins? =

**Yes.** Bot Traffic Shield works at a very early stage of WordPress, before most caching plugins, ensuring bots are blocked regardless of cache status.

= Can I use this with other security plugins? =

**Yes.** Bot Traffic Shield focuses specifically on bot blocking and works seamlessly alongside other security plugins like Wordfence, Sucuri, or iThemes Security.

== Screenshots ==

1. Modern settings interface with toggle switches and custom bot configuration
2. Block log and statistics dashboard showing paginated bot blocking history
3. Default blocklist showing all pre-configured AI crawlers and scrapers
4. CSV export feature with flexible date range options
5. Real-time blocking statistics and detailed log entries

== Changelog ==

= 1.0.4 (2025-11-26) =
* **New:** Clear Log
* **Improved:** Modern, redesigned admin interface

= 1.0.3 (2025-11-05) =
* **New:** Pagination system for block logs (20 entries per page)
* **New:** CSV export with date range filtering (7 days, 30 days, all time)
* **Improved:** Modern, redesigned admin interface
* **Improved:** Better mobile responsiveness
* **Enhanced:** Code optimization and performance improvements
* **Fixed:** WordPress coding standards compliance
* **Fixed:** Proper escaping and sanitization throughout

= 1.0.2 (2025-10-25) =
* New: Modern admin interface design
* New: Enhanced logging capabilities
* Improved: Overall performance optimizations
* Fixed: Various bug fixes

= 1.0.1 (2025-10-23) =
* Fixed: Bug fixes
* Improved: Performance updates

= 1.0.0 (2025-10-15) =
* Initial release
* Real-time bot blocking
* robots.txt integration
* Default blocklist of 20+ bots
* Logging and statistics
* Custom User-Agent blocking

== Upgrade Notice ==

= 1.0.4 =
Major update! Clear log button added. Recommended for all users.

= 1.0.3 =
Major update! New pagination system for easier log browsing and CSV export feature for data analysis. Enhanced admin interface and improved performance. Recommended for all users.

= 1.0.2 =
Improved interface and performance. Recommended update for all users.

= 1.0.0 =
Initial release of Bot Traffic Shield.

== Privacy Policy ==

Bot Traffic Shield logs the following information when a bot is blocked (if logging is enabled):
*   User-Agent string
*   IP address
*   Request timestamp
*   Requested URL

All data is stored locally in your WordPress database. No information is sent to external servers. You can disable logging or clear logs at any time from the plugin settings.

== Support ==

For support, feature requests, or bug reports:
*   Visit our website: [https://monarchwp.com/](https://monarchwp.com/)
*   Email: info@monarchwp.com

== Credits ==

Developed by [MonarchWP](https://monarchwp.com/)
