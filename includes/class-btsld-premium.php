<?php
/**
 * Premium feature registry, admin menus, and feature gating.
 *
 * Premium menus are always registered so users can discover them, but the free
 * plugin ships no premium functionality. Feature sections are merged into a
 * few menu groups and shown as tabs. Bot Traffic Shield Pro unlocks the gate
 * via the `btsld_premium_active` filter and renders each page through the
 * `btsld_premium_page_{key}` action. Without Pro, a locked preview is shown.
 *
 * @package BotTrafficShield
 * @version 1.0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BTSLD_Premium {

    /**
     * Prefix for premium page slugs, e.g. bot-traffic-shield-rate-limiting.
     */
    const PAGE_PREFIX = 'bot-traffic-shield-';

    /**
     * Slug of the "Upgrade to Pro" overview page.
     */
    const UPGRADE_SLUG = 'bot-traffic-shield-upgrade';

    /**
     * Singleton instance.
     *
     * @var BTSLD_Premium|null
     */
    private static $_instance = null;

    /**
     * Hook suffixes of the premium admin pages.
     *
     * @var string[]
     */
    private $page_hooks = array();

    /**
     * Group whose tabbed page is being rendered, if any.
     *
     * @var string|null
     */
    private static $rendering_group = null;

    /**
     * Main Instance.
     *
     * @return BTSLD_Premium
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Priority 20 so premium items are listed after the Dashboard submenu.
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_head', array( $this, 'print_menu_badge_styles' ) );
        // Old section slugs are no longer registered, so WordPress denies them before admin_init.
        add_action( 'admin_page_access_denied', array( $this, 'redirect_legacy_section_urls' ) );
    }

    /**
     * Whether premium functionality is available (Bot Traffic Shield Pro active).
     *
     * @return bool
     */
    public static function is_active() {
        /**
         * Filters whether premium functionality is available.
         *
         * Bot Traffic Shield Pro returns true here.
         *
         * @param bool $active Default false.
         */
        return (bool) apply_filters( 'btsld_premium_active', false );
    }

    /**
     * Where "Upgrade to Pro" buttons point.
     *
     * @return string
     */
    public static function get_upgrade_url() {
        return (string) apply_filters( 'btsld_upgrade_url', 'https://wpsatkhira.com/bot-traffic-shield.html#pricing' );
    }

    /**
     * Admin URL of a premium section (its group page and tab) or of a group.
     *
     * @param string $key Section key from get_pages() or group key from get_groups().
     * @return string
     */
    public static function get_page_url( $key ) {
        $groups = self::get_groups();
        if ( isset( $groups[ $key ] ) ) {
            return admin_url( 'admin.php?page=' . self::PAGE_PREFIX . $key );
        }

        $group = self::get_section_group( $key );
        if ( null === $group ) {
            return admin_url( 'admin.php?page=' . self::PAGE_PREFIX . $key );
        }

        $url = admin_url( 'admin.php?page=' . self::PAGE_PREFIX . $group );
        return count( $groups[ $group ]['sections'] ) > 1 ? add_query_arg( 'tab', $key, $url ) : $url;
    }

    /**
     * Premium menu groups, in menu order. Each group is one submenu; its
     * sections (keys from get_pages()) are shown as tabs, merging related
     * features from the spec into one screen.
     *
     * Sections not listed in any group get a menu of their own.
     *
     * @return array
     */
    public static function get_groups() {
        $groups = array(
            'detection'    => array(
                'title'      => __( 'Bot Detection', 'bot-traffic-shield' ),
                'menu_title' => __( 'Bot Detection', 'bot-traffic-shield' ),
                'icon'       => 'dashicons-visibility',
                'tagline'    => __( 'Score every visitor, discover unknown bots, and observe before you block.', 'bot-traffic-shield' ),
                'sections'   => array( 'bot-detection', 'unknown-bots', 'why-blocked', 'learning-mode' ),
            ),
            'traffic'      => array(
                'title'      => __( 'Traffic Control', 'bot-traffic-shield' ),
                'menu_title' => __( 'Traffic Control', 'bot-traffic-shield' ),
                'icon'       => 'dashicons-performance',
                'tagline'    => __( 'Rate-limit, challenge and block automated traffic.', 'bot-traffic-shield' ),
                'sections'   => array( 'rate-limiting', 'ip-management', 'challenges' ),
            ),
            'policies'     => array(
                'title'      => __( 'Rules & Policies', 'bot-traffic-shield' ),
                'menu_title' => __( 'Rules & Policies', 'bot-traffic-shield' ),
                'icon'       => 'dashicons-filter',
                'tagline'    => __( 'Decide which crawlers may access what.', 'bot-traffic-shield' ),
                'sections'   => array( 'search-engines', 'path-rules', 'rules', 'fake-bots', 'ai-policies', 'ai-categories', 'ai-presets' ),
            ),
            'insights'     => array(
                'title'      => __( 'Analytics & Reports', 'bot-traffic-shield' ),
                'menu_title' => __( 'Analytics & Reports', 'bot-traffic-shield' ),
                'icon'       => 'dashicons-chart-area',
                'tagline'    => __( 'Who is crawling your site, what they target, and whether they follow your rules.', 'bot-traffic-shield' ),
                'sections'   => array( 'analytics', 'url-activity', 'heatmap', 'countries', 'robots-compliance', 'reports', 'bandwidth' ),
            ),
            'alerts'       => array(
                'title'      => __( 'Alerts & Anomalies', 'bot-traffic-shield' ),
                'menu_title' => __( 'Alerts', 'bot-traffic-shield' ),
                'icon'       => 'dashicons-bell',
                'tagline'    => __( 'Get notified, and catch bot traffic spikes against your baseline.', 'bot-traffic-shield' ),
                'sections'   => array( 'alerts', 'anomalies' ),
            ),
            'protection'   => array(
                'title'      => __( 'Protection & Integrations', 'bot-traffic-shield' ),
                'menu_title' => __( 'Protection', 'bot-traffic-shield' ),
                'icon'       => 'dashicons-shield',
                'tagline'    => __( 'Protect key WordPress areas and stop bots at the edge or server.', 'bot-traffic-shield' ),
                'sections'   => array( 'cloudflare', 'server-blocking', 'woocommerce', 'rest-api', 'xmlrpc', 'sitemap-feed' ),
            ),
            'intelligence' => array(
                'title'      => __( 'Bot Intelligence', 'bot-traffic-shield' ),
                'menu_title' => __( 'Bot Intelligence', 'bot-traffic-shield' ),
                'icon'       => 'dashicons-database',
                'tagline'    => __( 'New crawlers recognized without waiting for a plugin update.', 'bot-traffic-shield' ),
                'sections'   => array( 'intelligence' ),
            ),
        );

        /**
         * Filters the premium menu groups.
         *
         * @param array $groups Group definitions keyed by group key.
         */
        $groups = apply_filters( 'btsld_premium_groups', $groups );

        // Keep only existing sections, and give ungrouped sections their own menu.
        $pages   = self::get_pages();
        $grouped = array();
        foreach ( $groups as $group_key => $group ) {
            $groups[ $group_key ]['sections'] = array_values( array_intersect( (array) $group['sections'], array_keys( $pages ) ) );
            if ( ! $groups[ $group_key ]['sections'] ) {
                unset( $groups[ $group_key ] );
                continue;
            }
            $grouped = array_merge( $grouped, $groups[ $group_key ]['sections'] );
        }
        foreach ( array_diff( array_keys( $pages ), $grouped ) as $key ) {
            $groups[ $key ] = array(
                'title'      => $pages[ $key ]['title'],
                'menu_title' => $pages[ $key ]['menu_title'],
                'icon'       => $pages[ $key ]['icon'],
                'tagline'    => $pages[ $key ]['tagline'],
                'sections'   => array( $key ),
            );
        }

        return $groups;
    }

    /**
     * Group a section belongs to.
     *
     * @param string $key Section key.
     * @return string|null
     */
    public static function get_section_group( $key ) {
        foreach ( self::get_groups() as $group_key => $group ) {
            if ( in_array( $key, $group['sections'], true ) ) {
                return $group_key;
            }
        }
        return null;
    }

    /**
     * Section shown on a group page: the requested tab, or the first section.
     *
     * @param string $group_key Group key.
     * @return string
     */
    public static function get_active_section( $group_key ) {
        $groups = self::get_groups();
        if ( ! isset( $groups[ $group_key ] ) ) {
            return '';
        }
        $sections = $groups[ $group_key ]['sections'];
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

        return in_array( $tab, $sections, true ) ? $tab : $sections[0];
    }

    /**
     * Whether the current admin screen shows the given section.
     * Pro modules use this to enqueue their assets.
     *
     * @param string $key Section key.
     * @return bool
     */
    public static function is_current_section( $key ) {
        $group = self::get_section_group( $key );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        return null !== $group && self::PAGE_PREFIX . $group === $page && self::get_active_section( $group ) === $key;
    }

    /**
     * Hidden fields that keep a GET form (search, filters) on a section's tab.
     *
     * @param string $key Section key.
     */
    public static function render_page_query_fields( $key ) {
        $group = self::get_section_group( $key );
        echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_PREFIX . ( null !== $group ? $group : $key ) ) . '">';
        if ( null !== $group && $group !== $key ) {
            echo '<input type="hidden" name="tab" value="' . esc_attr( $key ) . '">';
        }
    }

    /**
     * Redirect pre-grouping section URLs (admin.php?page=bot-traffic-shield-{section})
     * to their group tab, keeping any other query arguments.
     */
    public function redirect_legacy_section_urls() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( ! isset( $_GET['page'] ) || ! is_string( $_GET['page'] ) ) {
            return;
        }
        $page = sanitize_key( wp_unslash( $_GET['page'] ) );
        if ( 0 !== strpos( $page, self::PAGE_PREFIX ) ) {
            return;
        }

        $key = substr( $page, strlen( self::PAGE_PREFIX ) );

        // Sections that were split when the menu was aligned with the spec (§2.1–§31).
        $aliases = array(
            'integrations' => 'cloudflare',
        );
        if ( isset( $aliases[ $key ] ) ) {
            $key = $aliases[ $key ];
        }

        if ( isset( self::get_groups()[ $key ] ) || ! isset( self::get_pages()[ $key ] ) ) {
            return;
        }

        $args = wp_unslash( $_GET );
        // phpcs:enable
        unset( $args['page'] );
        wp_safe_redirect( add_query_arg( urlencode_deep( $args ), self::get_page_url( $key ) ) );
        exit;
    }

    /**
     * Premium feature sections. Each section is one Pro module; sections are
     * shown as tabs of the menu group that lists them (see get_groups()).
     *
     * There is one section per feature in the spec (§2.1–§31); `spec` is its
     * section number. `menu_title` is the tab label. `roadmap` is the Premium release the
     * module is planned for; it is shown when Pro is active but the module has
     * not shipped yet.
     *
     * @return array
     */
    public static function get_pages() {
        $pages = array(
            // §2.1
            'bot-detection'     => array(
                'spec'        => '2.1',
                'title'       => __( 'Smart Bot Detection', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Smart Detection', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-visibility',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Detect bots even when they don\'t identify themselves.', 'bot-traffic-shield' ),
                'description' => __( 'Go beyond User-Agent matching. Every visitor is scored from behavioral signals and classified with a confidence level, so you can act on automated traffic that pretends to be a browser.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Bot Score from 0–100 with a classification and confidence level', 'bot-traffic-shield' ),
                    __( 'Signals: request rate, per-IP and per-subnet volume, URL diversity and request timing', 'bot-traffic-shield' ),
                    __( 'Missing browser headers (Accept, Accept-Language, Referer), cookie and session behavior', 'bot-traffic-shield' ),
                    __( 'Repeated 404s, sequential, pagination, sitemap and robots.txt crawling patterns', 'bot-traffic-shield' ),
                ),
            ),
            // §3
            'rate-limiting'     => array(
                'spec'        => '3',
                'title'       => __( 'Rate Limiting', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Rate Limiting', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-performance',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Slow down aggressive crawlers instead of choosing only allow or block.', 'bot-traffic-shield' ),
                'description' => __( 'Apply Allow, Block, Rate Limit or Challenge per bot, per category or per Bot Score, with automatic temporary blocks when limits are exceeded.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Requests per minute and per hour, burst limit and cooldown', 'bot-traffic-shield' ),
                    __( 'Separate limits for unknown and suspicious crawlers', 'bot-traffic-shield' ),
                    __( 'Temporary IP block after a threshold violation', 'bot-traffic-shield' ),
                    __( 'Score-aware rules, e.g. Bot Score > 90 and > 100 req/min → block IP for 1 hour', 'bot-traffic-shield' ),
                ),
            ),
            // §4
            'ip-management'     => array(
                'spec'        => '4',
                'title'       => __( 'Temporary / Permanent IP Blocking', 'bot-traffic-shield' ),
                'menu_title'  => __( 'IP Blocking', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-networking',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Allow, block and temporarily ban IP addresses.', 'bot-traffic-shield' ),
                'description' => __( 'Manage IP allow and block lists with expiring bans, notes, and the traffic history that led to each decision.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Allow IP, block IP, temporary or permanent block', 'bot-traffic-shield' ),
                    __( 'Block durations: 10 minutes, 1 hour, 24 hours, 7 days or permanent', 'bot-traffic-shield' ),
                    __( 'Automatic expiration with notes and reasons for every entry', 'bot-traffic-shield' ),
                    __( 'Per-IP Bot Score, request count, 404s and pages crawled, with search and filters', 'bot-traffic-shield' ),
                ),
            ),
            // §5
            'intelligence'      => array(
                'spec'        => '5',
                'title'       => __( 'Bot Intelligence Database', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Bot Intelligence', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-database',
                'roadmap'     => '1.5',
                'tagline'     => __( 'New crawlers recognized without waiting for a plugin update.', 'bot-traffic-shield' ),
                'description' => __( 'A central, regularly updated bot database with vendor, category, purpose, signatures and verification methods for every known crawler.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Bot name, vendor, category, purpose and User-Agent patterns', 'bot-traffic-shield' ),
                    __( 'Verification methods, network information, status, confidence and last update', 'bot-traffic-shield' ),
                    __( 'Signature and detection rule updates delivered between plugin releases', 'bot-traffic-shield' ),
                ),
            ),
            // §6
            'search-engines'    => array(
                'spec'        => '6',
                'title'       => __( 'Individual Search Engine Controls', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Search Engines', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-admin-site-alt3',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Allow or block each search and social crawler on its own.', 'bot-traffic-shield' ),
                'description' => __( 'Replace the single search/social whitelist with an individual Allow or Block decision for every search engine and social bot.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Googlebot, Bingbot, DuckDuckBot, YandexBot, Baiduspider and more', 'bot-traffic-shield' ),
                    __( 'Social bots such as FacebookBot, Twitterbot, LinkedIn and WhatsApp', 'bot-traffic-shield' ),
                    __( 'Type and action shown for every bot', 'bot-traffic-shield' ),
                ),
            ),
            // §7
            'path-rules'        => array(
                'spec'        => '7',
                'title'       => __( 'Path-Based Bot Rules', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Path Rules', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-admin-links',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Decide which bots may crawl which parts of your site.', 'bot-traffic-shield' ),
                'description' => __( 'Allow or block a bot or bot category per URL path, for example keeping AI crawlers out of /docs/* while allowing /blog/*.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Rules per bot or per bot category', 'bot-traffic-shield' ),
                    __( 'Allowed and blocked path lists with wildcards', 'bot-traffic-shield' ),
                    __( 'Works for AI crawlers and search engines alike', 'bot-traffic-shield' ),
                ),
            ),
            // §8
            'rules'             => array(
                'spec'        => '8',
                'title'       => __( 'Custom Bot Rule Builder', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Rule Builder', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-filter',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Combine conditions visually and choose what happens.', 'bot-traffic-shield' ),
                'description' => __( 'Build named rules such as "Bot type = Scraper AND Requests/min > 50 AND Path contains /product/ → Block".', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Conditions: bot type, Bot Score, IP/range, country, request rate, path, HTTP method, User-Agent, Referer, headers, cookies, response status and time window', 'bot-traffic-shield' ),
                    __( 'Actions: Allow, Block, Rate limit, Challenge or Log only', 'bot-traffic-shield' ),
                ),
            ),
            // §9
            'challenges'        => array(
                'spec'        => '9',
                'title'       => __( 'Challenge Suspicious Bots', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Challenges', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-lock',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Verify uncertain visitors instead of blocking them outright.', 'bot-traffic-shield' ),
                'description' => __( 'Send traffic that looks automated but isn\'t conclusive through a lightweight browser check, so real visitors get through.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'JavaScript and cookie challenges', 'bot-traffic-shield' ),
                    __( 'CAPTCHA and Cloudflare Turnstile integration', 'bot-traffic-shield' ),
                    __( 'Lightweight browser verification triggered by Bot Score', 'bot-traffic-shield' ),
                ),
            ),
            // §10
            'fake-bots'         => array(
                'spec'        => '10',
                'title'       => __( 'Fake Search Engine Bot Detection', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Fake Bot Detection', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-privacy',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Make sure "Googlebot" really comes from Google.', 'bot-traffic-shield' ),
                'description' => __( 'Any client can send a Googlebot User-Agent. Verify search engine bots with reverse and forward DNS before trusting them.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Reverse and forward DNS verification against expected hostnames', 'bot-traffic-shield' ),
                    __( 'Verified / Unverified badges in logs and analytics', 'bot-traffic-shield' ),
                    __( 'Treat fake search bots as suspicious automatically', 'bot-traffic-shield' ),
                ),
            ),
            // §11
            'analytics'         => array(
                'spec'        => '11',
                'title'       => __( 'Advanced Bot Analytics', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Analytics', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-chart-area',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Who is crawling your site, and what happened to each request.', 'bot-traffic-shield' ),
                'description' => __( 'See all traffic, not only blocked requests: humans, known bots, AI crawlers, suspicious and unknown traffic, and the outcome of every request.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Total, human, known bot, AI crawler, suspicious and unknown requests', 'bot-traffic-shield' ),
                    __( 'Blocked, allowed, rate-limited and challenged requests', 'bot-traffic-shield' ),
                    __( 'Top IPs, User-Agents, bots and Bot Scores, and requests over time', 'bot-traffic-shield' ),
                ),
            ),
            // §12
            'url-activity'      => array(
                'spec'        => '12',
                'title'       => __( 'Bot Activity by URL', 'bot-traffic-shield' ),
                'menu_title'  => __( 'By URL', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-admin-page',
                'roadmap'     => '1.0',
                'tagline'     => __( 'See what bots are actually targeting.', 'bot-traffic-shield' ),
                'description' => __( 'Rank your most crawled URLs, overall and per bot.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Most targeted URLs with request counts', 'bot-traffic-shield' ),
                    __( 'Per-bot view, e.g. which articles GPTBot reads most', 'bot-traffic-shield' ),
                ),
            ),
            // §13
            'heatmap'           => array(
                'spec'        => '13',
                'title'       => __( 'Bot Traffic Heatmap', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Heatmap', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-grid-view',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Spot automated crawling patterns over time.', 'bot-traffic-shield' ),
                'description' => __( 'Visualize bot traffic by hour, day, date and bot category to reveal scheduled and automated crawling.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Traffic by hour, day of week and date', 'bot-traffic-shield' ),
                    __( 'Breakdown by bot category', 'bot-traffic-shield' ),
                ),
            ),
            // §14
            'countries'         => array(
                'spec'        => '14',
                'title'       => __( 'Country-Based Bot Analytics', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Countries', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-admin-site',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Where your bot traffic comes from.', 'bot-traffic-shield' ),
                'description' => __( 'Bot traffic broken down by country, with optional blocking or rate limiting for selected countries.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Bot traffic share per country', 'bot-traffic-shield' ),
                    __( 'Optional block or rate limit for bots from selected countries', 'bot-traffic-shield' ),
                ),
            ),
            // §15
            'alerts'            => array(
                'spec'        => '15',
                'title'       => __( 'Notifications & Alerts', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Notifications', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-bell',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Get told when something unusual happens.', 'bot-traffic-shield' ),
                'description' => __( 'Email, webhook and chat notifications for bot traffic conditions you choose.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Email and webhook, plus Slack, Discord, Telegram and Microsoft Teams', 'bot-traffic-shield' ),
                    __( 'Conditions: bot requests per hour, requests per minute from one IP, new AI crawler, unknown bot, traffic growth, repeated blocked attempts', 'bot-traffic-shield' ),
                ),
            ),
            // §16
            'anomalies'         => array(
                'spec'        => '16',
                'title'       => __( 'Bot Traffic Spike / Anomaly Detection', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Spikes & Anomalies', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-chart-line',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Catch unusual bot traffic against your normal baseline.', 'bot-traffic-shield' ),
                'description' => __( 'Compare current bot traffic with a learned baseline and respond when it spikes.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Rolling baseline of normal bot traffic', 'bot-traffic-shield' ),
                    __( 'Spike detection with notification', 'bot-traffic-shield' ),
                    __( 'Automatic responses: temporary rate limiting, blocking or challenging suspicious traffic', 'bot-traffic-shield' ),
                ),
            ),
            // §17
            'robots-compliance' => array(
                'spec'        => '17',
                'title'       => __( 'robots.txt Compliance Monitoring', 'bot-traffic-shield' ),
                'menu_title'  => __( 'robots.txt', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-media-text',
                'roadmap'     => '1.5',
                'tagline'     => __( 'See which crawlers ignore your robots.txt.', 'bot-traffic-shield' ),
                'description' => __( 'Compare what your robots.txt declares with what each crawler actually requests, and flag violations.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Per-crawler robots.txt policy vs. observed behavior', 'bot-traffic-shield' ),
                    __( 'Compliant / Violating status for each crawler', 'bot-traffic-shield' ),
                ),
            ),
            // §18
            'reports'           => array(
                'spec'        => '18',
                'title'       => __( 'AI Crawler Reports', 'bot-traffic-shield' ),
                'menu_title'  => __( 'AI Reports', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-media-spreadsheet',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Periodic AI crawl reports for you or your clients.', 'bot-traffic-shield' ),
                'description' => __( 'Reports summarizing AI crawler activity, what was blocked and allowed, the most targeted content, and estimated bandwidth saved.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Monthly report with totals, top crawler and most targeted content', 'bot-traffic-shield' ),
                    __( 'PDF and CSV export, email delivery and date range filtering', 'bot-traffic-shield' ),
                ),
            ),
            // §19
            'bandwidth'         => array(
                'spec'        => '19',
                'title'       => __( 'Bandwidth Savings Estimation', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Bandwidth', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-download',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Estimate the bandwidth your bot rules save.', 'bot-traffic-shield' ),
                'description' => __( 'Blocked requests multiplied by your average response size, clearly labeled as an estimate.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Estimated bandwidth prevented per period', 'bot-traffic-shield' ),
                    __( 'Configurable average response size', 'bot-traffic-shield' ),
                ),
            ),
            // §20
            'ai-policies'       => array(
                'spec'        => '20',
                'title'       => __( 'AI Content Protection Policies', 'bot-traffic-shield' ),
                'menu_title'  => __( 'AI Policies', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-shield',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Decide by purpose: training, search, assistants or agents.', 'bot-traffic-shield' ),
                'description' => __( 'Replace one global "Block AI" switch with a separate decision for each AI crawler purpose.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Allow or block AI Training, AI Search, AI Assistants and AI Agents independently', 'bot-traffic-shield' ),
                ),
            ),
            // §21
            'ai-categories'     => array(
                'spec'        => '21',
                'title'       => __( 'AI Crawler Categories', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Categories', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-category',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Every crawler sorted by what it does.', 'bot-traffic-shield' ),
                'description' => __( 'Crawlers are grouped into categories from the Bot Intelligence database so policies and analytics can use them.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'AI Training, AI Search, AI Assistants, AI Agents, Traditional Search, Social, SEO Crawlers, Scrapers, Monitoring and Unknown Automated', 'bot-traffic-shield' ),
                ),
            ),
            // §22
            'ai-presets'        => array(
                'spec'        => '22',
                'title'       => __( 'AI Crawler Policy Presets', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Presets', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-admin-settings',
                'roadmap'     => '1.5',
                'tagline'     => __( 'One-click AI crawler policies.', 'bot-traffic-shield' ),
                'description' => __( 'Apply a ready-made policy for every crawler category in one click.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Strict Protection, Balanced and AI-Friendly presets', 'bot-traffic-shield' ),
                ),
            ),
            // §23
            'cloudflare'        => array(
                'spec'        => '23',
                'title'       => __( 'Cloudflare Integration', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Cloudflare', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-cloud',
                'roadmap'     => '2.0',
                'tagline'     => __( 'Stop bots at the edge, before they reach WordPress.', 'bot-traffic-shield' ),
                'description' => __( 'Push your bot rules and AI policies to Cloudflare so bad traffic is blocked or rate-limited at the edge.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Create and sync Cloudflare rules and AI crawler policies', 'bot-traffic-shield' ),
                    __( 'Block and rate-limit IPs at the edge', 'bot-traffic-shield' ),
                    __( 'Proxy status detection and Turnstile challenges', 'bot-traffic-shield' ),
                ),
            ),
            // §24
            'server-blocking'   => array(
                'spec'        => '24',
                'title'       => __( 'Server-Level Blocking', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Server Rules', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-admin-generic',
                'roadmap'     => '2.0',
                'tagline'     => __( 'Block bots in the web server instead of in WordPress.', 'bot-traffic-shield' ),
                'description' => __( 'Generate validated server rules so blocked bots never reach PHP, with backup and rollback.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Apache (.htaccess), Nginx and LiteSpeed rules', 'bot-traffic-shield' ),
                    __( 'Early blocking through a must-use plugin', 'bot-traffic-shield' ),
                    __( 'Validated rules with backup, rollback and clear warnings', 'bot-traffic-shield' ),
                ),
            ),
            // §25
            'woocommerce'       => array(
                'spec'        => '25',
                'title'       => __( 'WooCommerce Bot Protection', 'bot-traffic-shield' ),
                'menu_title'  => __( 'WooCommerce', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-cart',
                'roadmap'     => '2.0',
                'tagline'     => __( 'Protect product and pricing data from automated scraping.', 'bot-traffic-shield' ),
                'description' => __( 'Watch the store endpoints bots target most: products, cart, checkout, account and the WooCommerce APIs.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Product, price, stock and inventory scraping detection', 'bot-traffic-shield' ),
                    __( 'Checkout bots and coupon abuse', 'bot-traffic-shield' ),
                ),
            ),
            // §26
            'rest-api'          => array(
                'spec'        => '26',
                'title'       => __( 'REST API Bot Protection', 'bot-traffic-shield' ),
                'menu_title'  => __( 'REST API', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-rest-api',
                'roadmap'     => '2.0',
                'tagline'     => __( 'Know who is calling /wp-json/.', 'bot-traffic-shield' ),
                'description' => __( 'Monitor REST API clients and block unknown automated clients, with exclusions for legitimate integrations.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Browser, known bot and unknown client breakdown', 'bot-traffic-shield' ),
                    __( 'Block unknown automated clients with exclusions', 'bot-traffic-shield' ),
                ),
            ),
            // §27
            'xmlrpc'            => array(
                'spec'        => '27',
                'title'       => __( 'XML-RPC Protection', 'bot-traffic-shield' ),
                'menu_title'  => __( 'XML-RPC', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-editor-code',
                'roadmap'     => '2.0',
                'tagline'     => __( 'Control automated XML-RPC traffic.', 'bot-traffic-shield' ),
                'description' => __( 'An optional module to disable, restrict or rate-limit XML-RPC.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Disable XML-RPC or allow specific IPs', 'bot-traffic-shield' ),
                    __( 'Rate limiting, abuse detection and request logging', 'bot-traffic-shield' ),
                ),
            ),
            // §28
            'sitemap-feed'      => array(
                'spec'        => '28',
                'title'       => __( 'Sitemap / Feed Protection', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Sitemap & Feed', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-rss',
                'roadmap'     => '2.0',
                'tagline'     => __( 'Rate-limit excessive sitemap and feed crawling.', 'bot-traffic-shield' ),
                'description' => __( 'Monitor and rate-limit automated requests to sitemaps and feeds.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Monitoring for /sitemap.xml, /wp-sitemap.xml, /feed/ and /rss/', 'bot-traffic-shield' ),
                    __( 'Rate limits for excessive automated requests', 'bot-traffic-shield' ),
                ),
            ),
            // §29
            'unknown-bots'      => array(
                'spec'        => '29',
                'title'       => __( 'Unknown Bot Discovery', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Unknown Bots', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-search',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Find the crawlers that aren\'t on any blocklist.', 'bot-traffic-shield' ),
                'description' => __( 'Surface automated traffic that does not match any known signature, see how it behaves, and decide what to do with one click.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Known bots vs. unknown automated traffic at a glance', 'bot-traffic-shield' ),
                    __( 'Per-visitor requests, pages crawled, average request interval and Bot Score', 'bot-traffic-shield' ),
                    __( 'Likely classification, e.g. "Likely scraper", with one-click Block, Allow or Create Rule', 'bot-traffic-shield' ),
                ),
            ),
            // §30
            'why-blocked'       => array(
                'spec'        => '30',
                'title'       => __( '"Why Was This Blocked?" Explanation', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Why Blocked?', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-editor-help',
                'roadmap'     => '1.0',
                'tagline'     => __( 'An explainable reason for every blocked request.', 'bot-traffic-shield' ),
                'description' => __( 'Look up any blocked or challenged visitor and see the bot, reason, score and the signals behind the decision.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Reference code on the block page for support requests', 'bot-traffic-shield' ),
                    __( 'Bot, reason, Bot Score and every signal that fired', 'bot-traffic-shield' ),
                ),
            ),
            // §31
            'learning-mode'     => array(
                'spec'        => '31',
                'title'       => __( 'Safe / Learning Mode', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Learning Mode', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-welcome-learn-more',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Observe first, then apply recommended rules.', 'bot-traffic-shield' ),
                'description' => __( 'Watch your traffic for a set period without blocking anything, then review what was detected and apply the recommended rules in one click.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Observation period with no blocking', 'bot-traffic-shield' ),
                    __( 'Summary of automated requests, suspicious IPs, new crawlers and likely scrapers', 'bot-traffic-shield' ),
                    __( 'Recommended block, rate-limit and allow rules, applied with safe rollback', 'bot-traffic-shield' ),
                ),
            ),
        );

        /**
         * Filters the premium page definitions.
         *
         * @param array $pages Page definitions keyed by page key.
         */
        return apply_filters( 'btsld_premium_pages', $pages );
    }

    /**
     * Register premium submenus under the Bot Traffic Shield menu.
     */
    public function admin_menu() {
        $is_active = self::is_active();

        foreach ( self::get_groups() as $group_key => $group ) {
            $menu_title = esc_html( $group['menu_title'] );
            if ( ! $is_active ) {
                $menu_title .= ' <span class="btsld-menu-pro">' . esc_html__( 'PRO', 'bot-traffic-shield' ) . '</span>';
            }

            $this->page_hooks[ $group_key ] = add_submenu_page(
                BTSLD_Admin::MENU_SLUG,
                $group['title'],
                $menu_title,
                'manage_options',
                self::PAGE_PREFIX . $group_key,
                function () use ( $group_key ) {
                    $this->render_group_page( $group_key );
                }
            );
        }

        if ( ! $is_active ) {
            $this->page_hooks['upgrade'] = add_submenu_page(
                BTSLD_Admin::MENU_SLUG,
                __( 'Upgrade to Bot Traffic Shield Pro', 'bot-traffic-shield' ),
                '<span class="btsld-menu-upgrade">' . esc_html__( 'Upgrade to Pro', 'bot-traffic-shield' ) . '</span>',
                'manage_options',
                self::UPGRADE_SLUG,
                array( $this, 'render_upgrade_page' )
            );
        }
    }

    /**
     * Enqueue admin styles on premium pages.
     *
     * @param string $hook Page hook suffix.
     */
    public function enqueue_assets( $hook ) {
        if ( ! in_array( $hook, $this->page_hooks, true ) ) {
            return;
        }

        wp_enqueue_style(
            'btsld-admin-css',
            BTSLD_PLUGIN_URL . 'assets/css/btsld-admin.css',
            array(),
            BTSLD_VERSION
        );

        wp_enqueue_style(
            'btsld-premium-css',
            BTSLD_PLUGIN_URL . 'assets/css/btsld-premium.css',
            array( 'btsld-admin-css' ),
            BTSLD_VERSION
        );
    }

    /**
     * Style the PRO badges in the admin sidebar (needed on every admin screen).
     */
    public function print_menu_badge_styles() {
        if ( self::is_active() || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <style id="btsld-menu-badges">
            #adminmenu .btsld-menu-pro { display: inline-block; margin-left: 4px; padding: 0 5px; border-radius: 3px; background: #3b82f6; color: #fff; font-size: 9px; font-weight: 700; line-height: 16px; vertical-align: middle; }
            #adminmenu .btsld-menu-upgrade { color: #fbbf24; font-weight: 600; }
        </style>
        <?php
    }

    /**
     * Render a menu group. Groups with several sections get a shared header and
     * one tab per section; single-section groups render the section directly.
     *
     * @param string $group_key Group key.
     */
    public function render_group_page( $group_key ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $groups = self::get_groups();
        if ( ! isset( $groups[ $group_key ] ) ) {
            return;
        }
        $group  = $groups[ $group_key ];
        $active = self::get_active_section( $group_key );

        if ( 1 === count( $group['sections'] ) ) {
            $this->render_page( $active );
            return;
        }

        ?>
        <div class="wrap btsld-wrap">
            <?php
            self::render_header( $group['title'], $group['tagline'], $group['icon'] );
            $this->render_tabs( $group, $active );

            self::$rendering_group = $group_key;
            $this->render_page( $active );
            self::$rendering_group = null;
            ?>
        </div>
        <?php
    }

    /**
     * Tab navigation for a group's sections.
     *
     * @param array  $group  Group definition.
     * @param string $active Active section key.
     */
    private function render_tabs( $group, $active ) {
        $pages     = self::get_pages();
        $is_active = self::is_active();
        ?>
        <nav class="nav-tab-wrapper btsld-tabs-nav btsld-group-tabs" aria-label="<?php echo esc_attr( $group['title'] ); ?>">
            <?php foreach ( $group['sections'] as $key ) : ?>
                <?php $page = $pages[ $key ]; ?>
                <a href="<?php echo esc_url( self::get_page_url( $key ) ); ?>" class="nav-tab<?php echo $key === $active ? ' nav-tab-active' : ''; ?>"<?php echo $key === $active ? ' aria-current="page"' : ''; ?>>
                    <span class="dashicons <?php echo esc_attr( $page['icon'] ); ?>" aria-hidden="true"></span>
                    <?php echo esc_html( $page['menu_title'] ); ?>
                    <?php if ( ! $is_active ) : ?>
                        <span class="btsld-tab-pill"><?php esc_html_e( 'PRO', 'bot-traffic-shield' ); ?></span>
                    <?php elseif ( ! has_action( 'btsld_premium_page_' . $key ) ) : ?>
                        <span class="btsld-tab-pill is-soon"><?php esc_html_e( 'Soon', 'bot-traffic-shield' ); ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    /**
     * Render a premium section: Pro's module if available, otherwise a locked or
     * coming-soon screen.
     *
     * @param string $key Section key.
     */
    public function render_page( $key ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $pages = self::get_pages();
        if ( ! isset( $pages[ $key ] ) ) {
            return;
        }
        $page = $pages[ $key ];

        if ( self::is_active() && has_action( 'btsld_premium_page_' . $key ) ) {
            /**
             * Renders a premium admin page. Hooked by Bot Traffic Shield Pro.
             *
             * @param array $page Page definition from get_pages().
             */
            do_action( 'btsld_premium_page_' . $key, $page );
            return;
        }

        $in_group = null !== self::$rendering_group;
        if ( ! $in_group ) {
            echo '<div class="wrap btsld-wrap">';
        }
        self::render_header( $page['title'], $page['tagline'], $page['icon'] );
        if ( self::is_active() ) {
            $this->render_coming_soon( $page );
        } else {
            $this->render_locked( $page );
        }
        if ( ! $in_group ) {
            echo '</div>';
        }
    }

    /**
     * Shared page header, also available to Pro module pages.
     *
     * Inside a tabbed group page the group already printed the page header, so
     * a section heading is printed instead.
     *
     * @param string $title    Page title.
     * @param string $subtitle Subtitle.
     * @param string $icon     Dashicons class.
     */
    public static function render_header( $title, $subtitle, $icon = 'dashicons-shield-alt' ) {
        if ( null !== self::$rendering_group ) {
            ?>
            <div class="btsld-section-heading">
                <h2><?php echo esc_html( $title ); ?></h2>
                <?php if ( '' !== (string) $subtitle ) : ?>
                    <p><?php echo esc_html( $subtitle ); ?></p>
                <?php endif; ?>
            </div>
            <?php
            return;
        }
        ?>
        <header class="btsld-header">
            <div class="btsld-header-left">
                <div class="btsld-logo-badge">
                    <span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
                </div>
                <div>
                    <h1><?php echo esc_html( $title ); ?> <span class="btsld-pro-pill"><?php esc_html_e( 'PRO', 'bot-traffic-shield' ); ?></span></h1>
                    <p class="btsld-subtitle"><?php echo esc_html( $subtitle ); ?></p>
                </div>
            </div>
        </header>
        <hr class="wp-header-end">
        <?php
    }

    /**
     * Feature checklist.
     *
     * @param string[] $features Feature descriptions.
     */
    private function render_feature_list( $features ) {
        ?>
        <ul class="btsld-feature-list">
            <?php foreach ( $features as $feature ) : ?>
                <li><span class="dashicons dashicons-yes-alt"></span> <?php echo esc_html( $feature ); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Locked screen shown when Pro is not active.
     *
     * @param array $page Page definition.
     */
    private function render_locked( $page ) {
        ?>
        <div class="btsld-card btsld-locked-card">
            <div class="btsld-locked-hero">
                <span class="btsld-locked-icon"><span class="dashicons dashicons-lock"></span></span>
                <div>
                    <h2><?php echo esc_html( $page['title'] ); ?></h2>
                    <p class="btsld-locked-desc"><?php echo esc_html( $page['description'] ); ?></p>
                </div>
            </div>

            <?php $this->render_feature_list( $page['features'] ); ?>

            <div class="btsld-locked-cta">
                <a href="<?php echo esc_url( self::get_upgrade_url() ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Upgrade to Pro', 'bot-traffic-shield' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::UPGRADE_SLUG ) ); ?>" class="button button-secondary">
                    <?php esc_html_e( 'See all Pro features', 'bot-traffic-shield' ); ?>
                </a>
                <p class="btsld-locked-note">
                    <?php
                    printf(
                        /* translators: %s: Pro plugin name */
                        esc_html__( 'Already purchased? Install and activate %s to unlock this page.', 'bot-traffic-shield' ),
                        '<strong>' . esc_html__( 'Bot Traffic Shield Pro', 'bot-traffic-shield' ) . '</strong>'
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Shown when Pro is active but its version does not include this module yet.
     *
     * @param array $page Page definition.
     */
    private function render_coming_soon( $page ) {
        ?>
        <div class="btsld-card btsld-locked-card">
            <div class="btsld-locked-hero">
                <span class="btsld-locked-icon btsld-icon-soon"><span class="dashicons dashicons-clock"></span></span>
                <div>
                    <h2><?php echo esc_html( $page['title'] ); ?></h2>
                    <p class="btsld-locked-desc">
                        <?php
                        printf(
                            /* translators: %s: premium release version, e.g. 1.5 */
                            esc_html__( 'Included in your Pro plan. This module is planned for Pro v%s and will unlock here automatically when you update Bot Traffic Shield Pro.', 'bot-traffic-shield' ),
                            esc_html( $page['roadmap'] )
                        );
                        ?>
                    </p>
                </div>
            </div>

            <?php $this->render_feature_list( $page['features'] ); ?>
        </div>
        <?php
    }

    /**
     * "Upgrade to Pro" overview page listing every premium feature.
     */
    public function render_upgrade_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap btsld-wrap">
            <?php self::render_header( __( 'Bot Traffic Shield Pro', 'bot-traffic-shield' ), __( 'Detect. Understand. Control.', 'bot-traffic-shield' ), 'dashicons-star-filled' ); ?>

            <div class="btsld-card btsld-upgrade-hero">
                <div>
                    <h2><?php esc_html_e( 'Advanced Bot Detection, Traffic Control & AI Crawl Intelligence', 'bot-traffic-shield' ); ?></h2>
                    <p class="btsld-locked-desc"><?php esc_html_e( 'The free plugin blocks the AI bots you already know about. Pro shows you who is crawling your site and how they behave. It scores unknown bots, rate-limits aggressive crawlers, verifies real search engines, and lets you control exactly who can crawl your content.', 'bot-traffic-shield' ); ?></p>
                </div>
                <a href="<?php echo esc_url( self::get_upgrade_url() ); ?>" class="button button-primary button-hero" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Get Bot Traffic Shield Pro', 'bot-traffic-shield' ); ?>
                </a>
            </div>

            <?php $pages = self::get_pages(); ?>
            <?php foreach ( self::get_groups() as $group ) : ?>
                <h2 class="btsld-upgrade-group">
                    <span class="dashicons <?php echo esc_attr( $group['icon'] ); ?>" aria-hidden="true"></span>
                    <?php echo esc_html( $group['title'] ); ?>
                </h2>
                <div class="btsld-ai-grid">
                    <?php foreach ( $group['sections'] as $key ) : ?>
                        <?php $page = $pages[ $key ]; ?>
                        <a href="<?php echo esc_url( self::get_page_url( $key ) ); ?>" class="btsld-ai-card btsld-upgrade-feature">
                            <div class="btsld-ai-card-top">
                                <span class="btsld-upgrade-feature-icon dashicons <?php echo esc_attr( $page['icon'] ); ?>"></span>
                                <span class="btsld-pro-pill"><?php esc_html_e( 'PRO', 'bot-traffic-shield' ); ?></span>
                            </div>
                            <h3 class="btsld-ai-title"><?php echo esc_html( $page['title'] ); ?></h3>
                            <p class="btsld-ai-desc"><?php echo esc_html( $page['tagline'] ); ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
