<?php
/**
 * Premium feature registry, admin menus, and feature gating.
 *
 * Premium menus are always registered so users can discover them, but the free
 * plugin ships no premium functionality. Bot Traffic Shield Pro unlocks the gate
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
        return (string) apply_filters( 'btsld_upgrade_url', 'https://wpsatkhira.com/bot-traffic-shield-pro/' );
    }

    /**
     * Admin URL of a premium page.
     *
     * @param string $key Page key from get_pages().
     * @return string
     */
    public static function get_page_url( $key ) {
        return admin_url( 'admin.php?page=' . self::PAGE_PREFIX . $key );
    }

    /**
     * Premium pages, in menu order.
     *
     * `roadmap` is the Premium release the module is planned for; it is shown
     * when Pro is active but the module has not shipped yet.
     *
     * @return array
     */
    public static function get_pages() {
        $pages = array(
            'bot-detection'     => array(
                'title'       => __( 'Smart Bot Detection', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Bot Detection', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-visibility',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Detect bots even when they don\'t identify themselves.', 'bot-traffic-shield' ),
                'description' => __( 'Go beyond User-Agent matching. Every visitor is scored from behavioral signals and classified with a confidence level, so you can act on automated traffic that pretends to be a browser.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Bot Score from 0–100 with a confidence level for every visitor', 'bot-traffic-shield' ),
                    __( 'Signals: request rate, per-IP and per-subnet volume, URL diversity and request timing', 'bot-traffic-shield' ),
                    __( 'Missing browser headers (Accept, Accept-Language, Referer), cookie and session behavior', 'bot-traffic-shield' ),
                    __( 'Repeated 404s, sequential, pagination and sitemap crawling patterns', 'bot-traffic-shield' ),
                    __( 'Classification from Human to Confirmed Bot to keep false positives low', 'bot-traffic-shield' ),
                    __( '"Why was this blocked?" explanation listing the signals behind every action', 'bot-traffic-shield' ),
                ),
            ),
            'unknown-bots'      => array(
                'title'       => __( 'Unknown Bot Discovery', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Unknown Bots', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-search',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Find the crawlers that aren\'t on any blocklist.', 'bot-traffic-shield' ),
                'description' => __( 'Surface automated traffic that does not match any known signature, see how it behaves, and decide what to do with one click.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Known bots vs. unknown automated traffic at a glance', 'bot-traffic-shield' ),
                    __( 'Per-visitor requests, pages crawled, average request interval and Bot Score', 'bot-traffic-shield' ),
                    __( 'Likely classification, e.g. "Likely scraper"', 'bot-traffic-shield' ),
                    __( 'One-click Block, Allow or Create Rule', 'bot-traffic-shield' ),
                ),
            ),
            'rate-limiting'     => array(
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
            'ip-management'     => array(
                'title'       => __( 'IP Management', 'bot-traffic-shield' ),
                'menu_title'  => __( 'IP Management', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-networking',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Allow, block and temporarily ban IP addresses.', 'bot-traffic-shield' ),
                'description' => __( 'Manage IP allow and block lists with expiring bans, notes, and the traffic history that led to each decision.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Allow IP, block IP, temporary or permanent block', 'bot-traffic-shield' ),
                    __( 'Block durations: 10 minutes, 1 hour, 24 hours, 7 days or permanent', 'bot-traffic-shield' ),
                    __( 'Automatic expiration with notes and reasons for every entry', 'bot-traffic-shield' ),
                    __( 'Per-IP Bot Score, request count, 404s and pages crawled', 'bot-traffic-shield' ),
                    __( 'Search and filter IPs', 'bot-traffic-shield' ),
                ),
            ),
            'search-engines'    => array(
                'title'       => __( 'Search Engine Controls & Verification', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Search Engines', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-admin-site-alt3',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Control every search and social crawler, and catch the fake ones.', 'bot-traffic-shield' ),
                'description' => __( 'Allow or block each search engine and social bot on its own, and verify that "Googlebot" really comes from Google before trusting it.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Individual Allow/Block for Googlebot, Bingbot, DuckDuckBot, YandexBot, Baiduspider and more', 'bot-traffic-shield' ),
                    __( 'Individual controls for social bots such as Facebook, X/Twitter, LinkedIn and WhatsApp', 'bot-traffic-shield' ),
                    __( 'Fake search bot detection with reverse and forward DNS verification', 'bot-traffic-shield' ),
                    __( 'Verified / Unverified badges in logs and analytics', 'bot-traffic-shield' ),
                ),
            ),
            'ai-policies'       => array(
                'title'       => __( 'AI Content Protection Policies', 'bot-traffic-shield' ),
                'menu_title'  => __( 'AI Policies', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-shield',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Decide by purpose: training, search, assistants or agents.', 'bot-traffic-shield' ),
                'description' => __( 'Replace one global "Block AI" switch with policies per AI crawler category, or apply a one-click preset.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Categories: AI Training, AI Search, AI Assistants, AI Agents, Scrapers, SEO, Monitoring and more', 'bot-traffic-shield' ),
                    __( 'Allow or block each category independently', 'bot-traffic-shield' ),
                    __( 'Presets: Strict Protection, Balanced and AI-Friendly', 'bot-traffic-shield' ),
                ),
            ),
            'rules'             => array(
                'title'       => __( 'Rule Builder', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Rule Builder', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-filter',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Build your own bot rules, including path-based rules.', 'bot-traffic-shield' ),
                'description' => __( 'Combine conditions visually and choose what happens. Protect specific paths from specific bots, such as keeping AI crawlers out of /docs/* while allowing /blog/*.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Path-based rules per bot or bot category', 'bot-traffic-shield' ),
                    __( 'Conditions: bot type, Bot Score, IP/range, country, request rate, path, HTTP method, User-Agent, Referer, headers, cookies, response status and time window', 'bot-traffic-shield' ),
                    __( 'Actions: Allow, Block, Rate limit, Challenge or Log only', 'bot-traffic-shield' ),
                ),
            ),
            'challenges'        => array(
                'title'       => __( 'Bot Challenges', 'bot-traffic-shield' ),
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
            'analytics'         => array(
                'title'       => __( 'Advanced Bot Analytics', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Advanced Analytics', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-chart-area',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Who is crawling your site, what they target, and when.', 'bot-traffic-shield' ),
                'description' => __( 'See all traffic, not only blocked requests: humans vs. known bots vs. AI crawlers vs. suspicious traffic, broken down by URL, country, time and Bot Score.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Human, known bot, AI crawler, suspicious and unknown requests', 'bot-traffic-shield' ),
                    __( 'Blocked, allowed, rate-limited and challenged requests', 'bot-traffic-shield' ),
                    __( 'Top IPs, User-Agents, bots and most targeted URLs, including per-bot views', 'bot-traffic-shield' ),
                    __( 'Traffic heatmaps by hour, day and bot category', 'bot-traffic-shield' ),
                    __( 'Bot traffic by country', 'bot-traffic-shield' ),
                    __( 'Estimated bandwidth saved', 'bot-traffic-shield' ),
                ),
            ),
            'robots-compliance' => array(
                'title'       => __( 'robots.txt Compliance Monitoring', 'bot-traffic-shield' ),
                'menu_title'  => __( 'robots.txt Compliance', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-media-text',
                'roadmap'     => '1.5',
                'tagline'     => __( 'See which crawlers ignore your robots.txt.', 'bot-traffic-shield' ),
                'description' => __( 'Compare what your robots.txt declares with what each crawler actually requests, and flag violations.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Per-crawler robots.txt policy vs. observed behavior', 'bot-traffic-shield' ),
                    __( 'Compliant / Violating status for each crawler', 'bot-traffic-shield' ),
                    __( 'Create a block rule from any violation', 'bot-traffic-shield' ),
                ),
            ),
            'alerts'            => array(
                'title'       => __( 'Alerts & Anomaly Detection', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Alerts', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-bell',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Get told when bot traffic spikes, and respond automatically.', 'bot-traffic-shield' ),
                'description' => __( 'Detect unusual bot traffic against your normal baseline and get notified, with optional automatic responses.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Email and webhook alerts, plus Slack, Discord, Telegram and Microsoft Teams', 'bot-traffic-shield' ),
                    __( 'Conditions: bot requests per hour, requests per minute from one IP, new AI crawler, unknown bot, traffic growth', 'bot-traffic-shield' ),
                    __( 'Traffic spike and anomaly detection', 'bot-traffic-shield' ),
                    __( 'Automatic responses: temporary rate limiting, blocking or challenging suspicious traffic', 'bot-traffic-shield' ),
                ),
            ),
            'reports'           => array(
                'title'       => __( 'AI Crawler Reports', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Reports', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-media-spreadsheet',
                'roadmap'     => '1.5',
                'tagline'     => __( 'Monthly AI crawl reports for you or your clients.', 'bot-traffic-shield' ),
                'description' => __( 'Scheduled reports summarizing AI crawler activity, what was blocked and allowed, the most targeted content, and estimated bandwidth saved.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Monthly report with totals, top crawlers and most targeted content', 'bot-traffic-shield' ),
                    __( 'PDF and CSV export', 'bot-traffic-shield' ),
                    __( 'Email delivery and date range filtering', 'bot-traffic-shield' ),
                    __( 'Estimated bandwidth savings', 'bot-traffic-shield' ),
                ),
            ),
            'learning-mode'     => array(
                'title'       => __( 'Learning Mode', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Learning Mode', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-welcome-learn-more',
                'roadmap'     => '1.0',
                'tagline'     => __( 'Observe first, then apply recommended rules.', 'bot-traffic-shield' ),
                'description' => __( 'Watch your traffic for a set period without blocking anything, then review what was detected and apply the recommended rules in one click.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Observation period with no blocking', 'bot-traffic-shield' ),
                    __( 'Summary of automated requests, suspicious IPs, new crawlers and likely scrapers', 'bot-traffic-shield' ),
                    __( 'Recommended block, rate-limit and allow rules', 'bot-traffic-shield' ),
                    __( 'Apply Recommendations with safe rollback', 'bot-traffic-shield' ),
                ),
            ),
            'protection'        => array(
                'title'       => __( 'Protection Modules', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Protection Modules', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-cart',
                'roadmap'     => '2.0',
                'tagline'     => __( 'Protect WooCommerce, the REST API, XML-RPC, sitemaps and feeds.', 'bot-traffic-shield' ),
                'description' => __( 'Targeted protection for the parts of WordPress that bots and scrapers hit hardest.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'WooCommerce: product, price, stock and inventory scraping, checkout bots and coupon abuse', 'bot-traffic-shield' ),
                    __( 'REST API: monitor /wp-json/ clients and block unknown automated clients, with exclusions', 'bot-traffic-shield' ),
                    __( 'XML-RPC: disable, allow specific IPs, rate limit and log', 'bot-traffic-shield' ),
                    __( 'Sitemap and feed: rate-limit excessive automated requests', 'bot-traffic-shield' ),
                ),
            ),
            'integrations'      => array(
                'title'       => __( 'Edge & Server Integrations', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Integrations', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-cloud',
                'roadmap'     => '2.0',
                'tagline'     => __( 'Block bots before they reach WordPress.', 'bot-traffic-shield' ),
                'description' => __( 'Push your bot rules to Cloudflare or to your web server so bad traffic is stopped at the edge instead of inside WordPress.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Cloudflare: sync rules and AI policies, block and rate-limit IPs at the edge, Turnstile challenges', 'bot-traffic-shield' ),
                    __( 'Server-level rules for Apache (.htaccess), Nginx and LiteSpeed', 'bot-traffic-shield' ),
                    __( 'Early blocking through a must-use plugin', 'bot-traffic-shield' ),
                    __( 'Validated rules with automatic backup and rollback', 'bot-traffic-shield' ),
                ),
            ),
            'intelligence'      => array(
                'title'       => __( 'Bot Intelligence', 'bot-traffic-shield' ),
                'menu_title'  => __( 'Bot Intelligence', 'bot-traffic-shield' ),
                'icon'        => 'dashicons-database',
                'roadmap'     => '1.5',
                'tagline'     => __( 'New crawlers recognized without waiting for a plugin update.', 'bot-traffic-shield' ),
                'description' => __( 'A central, regularly updated bot database with vendor, category, purpose, signatures and verification methods for every known crawler.', 'bot-traffic-shield' ),
                'features'    => array(
                    __( 'Bot name, vendor, category, purpose and User-Agent patterns', 'bot-traffic-shield' ),
                    __( 'Verification methods and network information where appropriate', 'bot-traffic-shield' ),
                    __( 'Automatic signature and detection rule updates', 'bot-traffic-shield' ),
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

        foreach ( self::get_pages() as $key => $page ) {
            $menu_title = esc_html( $page['menu_title'] );
            if ( ! $is_active ) {
                $menu_title .= ' <span class="btsld-menu-pro">' . esc_html__( 'PRO', 'bot-traffic-shield' ) . '</span>';
            }

            $this->page_hooks[ $key ] = add_submenu_page(
                BTSLD_Admin::MENU_SLUG,
                $page['title'],
                $menu_title,
                'manage_options',
                self::PAGE_PREFIX . $key,
                function () use ( $key ) {
                    $this->render_page( $key );
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
     * Render a premium page: Pro's module if available, otherwise a locked or
     * coming-soon screen.
     *
     * @param string $key Page key.
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

        ?>
        <div class="wrap btsld-wrap">
            <?php self::render_header( $page['title'], $page['tagline'], $page['icon'] ); ?>
            <?php
            if ( self::is_active() ) {
                $this->render_coming_soon( $page );
            } else {
                $this->render_locked( $page );
            }
            ?>
        </div>
        <?php
    }

    /**
     * Shared page header, also available to Pro module pages.
     *
     * @param string $title    Page title.
     * @param string $subtitle Subtitle.
     * @param string $icon     Dashicons class.
     */
    public static function render_header( $title, $subtitle, $icon = 'dashicons-shield-alt' ) {
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

            <div class="btsld-ai-grid">
                <?php foreach ( self::get_pages() as $key => $page ) : ?>
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
        </div>
        <?php
    }
}
