<?php
/**
 * Core protection and data engine for Bot Traffic Shield.
 *
 * @package BotTrafficShield
 * @version 1.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BTSLD_Core {

    /**
     * Singleton instance.
     *
     * @var BTSLD_Core|null
     */
    private static $_instance = null;

    /**
     * Main Instance.
     *
     * @return BTSLD_Core
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
        add_action( 'init', array( $this, 'block_ai_crawlers' ), 1 );
        add_filter( 'robots_txt', array( $this, 'add_rules_to_robots_txt' ), 99, 2 );
    }

    /**
     * Registered AI bot catalog with metadata for granular toggle control.
     *
     * @return array
     */
    public static function get_registered_ai_bots() {
        return array(
            'gptbot' => array(
                'label'       => 'GPTBot (OpenAI)',
                'provider'    => 'OpenAI',
                'description' => 'Crawler used to train OpenAI ChatGPT models and AI products.',
                'agents'      => array( 'GPTBot' ),
                'default'     => 1,
            ),
            'chatgpt_user' => array(
                'label'       => 'ChatGPT-User (OpenAI)',
                'provider'    => 'OpenAI',
                'description' => 'Used by ChatGPT web browsing when users ask questions in real-time.',
                'agents'      => array( 'ChatGPT-User' ),
                'default'     => 1,
            ),
            'claudebot' => array(
                'label'       => 'ClaudeBot & Anthropic AI',
                'provider'    => 'Anthropic',
                'description' => 'Crawlers used by Anthropic Claude LLM and data pipelines.',
                'agents'      => array( 'ClaudeBot', 'anthropic-ai', 'Claude-Web' ),
                'default'     => 1,
            ),
            'google_extended' => array(
                'label'       => 'Google-Extended (Gemini & Vertex AI)',
                'provider'    => 'Google',
                'description' => 'Allows Google to scrape content for training Gemini and AI models.',
                'agents'      => array( 'Google-Extended' ),
                'default'     => 1,
            ),
            'perplexity' => array(
                'label'       => 'PerplexityBot',
                'provider'    => 'Perplexity AI',
                'description' => 'Real-time scraper for Perplexity conversational search engine.',
                'agents'      => array( 'PerplexityBot' ),
                'default'     => 1,
            ),
            'bytespider' => array(
                'label'       => 'Bytespider (ByteDance / TikTok)',
                'provider'    => 'ByteDance',
                'description' => 'Aggressive crawler used for ByteDance AI and search models.',
                'agents'      => array( 'Bytespider' ),
                'default'     => 1,
            ),
            'meta_ai' => array(
                'label'       => 'Meta AI (FacebookBot & Meta-ExternalAgent)',
                'provider'    => 'Meta',
                'description' => 'Used by Meta to train Llama models and index AI features.',
                'agents'      => array( 'FacebookBot', 'Meta-ExternalAgent' ),
                'default'     => 1,
            ),
            'applebot_extended' => array(
                'label'       => 'Applebot-Extended (Apple Intelligence)',
                'provider'    => 'Apple',
                'description' => 'Used by Apple to train foundation models for Apple Intelligence.',
                'agents'      => array( 'Applebot-Extended' ),
                'default'     => 1,
            ),
            'ccbot' => array(
                'label'       => 'CCBot (Common Crawl)',
                'provider'    => 'Common Crawl',
                'description' => 'Open-source web crawl dataset heavily used to train almost all LLMs.',
                'agents'      => array( 'CCBot' ),
                'default'     => 1,
            ),
            'cohere' => array(
                'label'       => 'Cohere AI',
                'provider'    => 'Cohere',
                'description' => 'Web crawler used for Cohere enterprise language models.',
                'agents'      => array( 'cohere-ai' ),
                'default'     => 1,
            ),
            'amazonbot' => array(
                'label'       => 'Amazonbot',
                'provider'    => 'Amazon',
                'description' => 'Scraper used for Alexa services and Amazon LLM training.',
                'agents'      => array( 'Amazonbot' ),
                'default'     => 1,
            ),
            'diffbot' => array(
                'label'       => 'Diffbot',
                'provider'    => 'Diffbot',
                'description' => 'Automated data scraper used for machine learning knowledge graphs.',
                'agents'      => array( 'Diffbot' ),
                'default'     => 1,
            ),
            'omgilibot' => array(
                'label'       => 'Omgilibot (Webhose.io)',
                'provider'    => 'Webhose',
                'description' => 'Commercial web scraper providing raw website data for AI feeds.',
                'agents'      => array( 'Omgilibot' ),
                'default'     => 1,
            ),
            'youbot' => array(
                'label'       => 'YouBot (You.com)',
                'provider'    => 'You.com',
                'description' => 'Crawler for You.com generative search engine.',
                'agents'      => array( 'YouBot' ),
                'default'     => 1,
            ),
            'timpibot' => array(
                'label'       => 'Timpibot',
                'provider'    => 'Timpi',
                'description' => 'Decentralized AI web scraper and indexer.',
                'agents'      => array( 'Timpibot' ),
                'default'     => 1,
            ),
        );
    }

    /**
     * Search Engine Whitelist — Legitimate crawlers that must NEVER be blocked.
     *
     * This protects your SEO rankings by ensuring that standard search engine
     * indexing bots are always allowed through, even if a custom user-agent
     * pattern or overly broad rule would otherwise match them.
     *
     * Developers can modify this list via the 'btsld_search_engine_whitelist' filter.
     *
     * @return array Associative array of token => label.
     */
    public static function get_search_engine_whitelist() {
        $whitelist = array(
            // Google
            'Googlebot'          => 'Google Search',
            'Googlebot-Image'    => 'Google Images',
            'Googlebot-Video'    => 'Google Video',
            'Googlebot-News'     => 'Google News',
            'Storebot-Google'    => 'Google Store',
            'Google-InspectionTool' => 'Google Search Console',
            'GoogleOther'        => 'Google Other Services',

            // Microsoft / Bing
            'bingbot'            => 'Bing Search',
            'msnbot'             => 'MSN Search',
            'BingPreview'        => 'Bing Preview',
            'adidxbot'           => 'Bing Ads',

            // Yahoo
            'Slurp'              => 'Yahoo Search',

            // DuckDuckGo
            'DuckDuckBot'        => 'DuckDuckGo Search',
            'DuckDuckGo-Favicons-Bot' => 'DuckDuckGo Favicons',

            // Baidu
            'Baiduspider'        => 'Baidu Search',
            'Baiduspider-image'  => 'Baidu Images',

            // Yandex
            'YandexBot'          => 'Yandex Search',
            'YandexImages'       => 'Yandex Images',
            'YandexMobileBot'    => 'Yandex Mobile',

            // Other Major Search Engines
            'Sogou'              => 'Sogou Search',
            'Exabot'             => 'Exalead Search',
            'ia_archiver'        => 'Internet Archive',

            // Social Media Indexers (important for link previews & SEO)
            'Twitterbot'         => 'Twitter / X Card Crawler',
            'LinkedInBot'        => 'LinkedIn Preview',
            'Pinterestbot'       => 'Pinterest Crawler',
            'facebookexternalhit' => 'Facebook Link Preview',
            'WhatsApp'           => 'WhatsApp Link Preview',
            'Slackbot'           => 'Slack Link Preview',
            'Discordbot'         => 'Discord Link Preview',
            'TelegramBot'        => 'Telegram Link Preview',

            // Apple (standard search indexing, NOT Applebot-Extended AI training)
            'Applebot'           => 'Apple Search (Siri & Spotlight)',

            // SEO & Monitoring Tools (commonly used by site owners)
            'AhrefsBot'          => 'Ahrefs SEO Crawler',
            'SemrushBot'         => 'Semrush SEO Crawler',
            'MJ12bot'            => 'Majestic SEO Crawler',
            'DotBot'             => 'Moz SEO Crawler',
            'rogerbot'           => 'Moz Rogerbot',
            'Screaming Frog'     => 'Screaming Frog SEO Spider',
            'GTmetrix'           => 'GTmetrix Performance',
            'Pingdom'            => 'Pingdom Uptime Monitor',
            'UptimeRobot'        => 'UptimeRobot Monitor',
        );

        /**
         * Filter the search engine whitelist.
         *
         * @param array $whitelist Associative array of user-agent token => label.
         */
        return apply_filters( 'btsld_search_engine_whitelist', $whitelist );
    }

    /**
     * Check if the current request is from a whitelisted search engine.
     *
     * @param string $user_agent The incoming User-Agent string.
     * @return string|false The matched whitelist label if whitelisted, false otherwise.
     */
    private function is_whitelisted_search_engine( $user_agent ) {
        if ( empty( $user_agent ) ) {
            return false;
        }

        $whitelist = self::get_search_engine_whitelist();

        foreach ( $whitelist as $token => $label ) {
            if ( stripos( $user_agent, $token ) !== false ) {
                return $label;
            }
        }

        return false;
    }

    /**
     * Activation hook to set default options and run migrations.
     */
    public static function on_activate() {
        $settings = get_option( 'btsld_settings', array() );

        $defaults = array(
            'enabled'            => '1',
            'custom_user_agents' => '',
            'log_blocked_bots'   => '1',
            'ai_toggles'         => array(),
        );

        foreach ( self::get_registered_ai_bots() as $bot_key => $bot_info ) {
            $defaults['ai_toggles'][ $bot_key ] = '1';
        }

        if ( ! empty( $settings ) && is_array( $settings ) ) {
            if ( ! isset( $settings['ai_toggles'] ) ) {
                $settings['ai_toggles'] = $defaults['ai_toggles'];
            } else {
                $settings['ai_toggles'] = wp_parse_args( $settings['ai_toggles'], $defaults['ai_toggles'] );
            }
            $final_settings = wp_parse_args( $settings, $defaults );
        } else {
            $final_settings = $defaults;
        }

        update_option( 'btsld_settings', $final_settings );

        if ( false === get_option( 'btsld_blocked_log' ) ) {
            update_option( 'btsld_blocked_log', array() );
        }
        if ( false === get_option( 'btsld_blocked_count' ) ) {
            update_option( 'btsld_blocked_count', 0 );
        }
        if ( false === get_option( 'btsld_stats_daily' ) ) {
            update_option( 'btsld_stats_daily', array() );
        }
    }

    /**
     * Get list of User Agents currently active for blocking based on user settings.
     *
     * @return array List of user agent string tokens.
     */
    public function get_active_bot_tokens() {
        $settings       = get_option( 'btsld_settings', array() );
        $all_bots       = self::get_registered_ai_bots();
        $active_toggles = isset( $settings['ai_toggles'] ) && is_array( $settings['ai_toggles'] )
            ? $settings['ai_toggles']
            : array();

        $active_tokens = array();

        foreach ( $all_bots as $bot_key => $bot_data ) {
            $is_enabled = isset( $active_toggles[ $bot_key ] ) ? ( '1' === (string) $active_toggles[ $bot_key ] ) : true;
            if ( $is_enabled && ! empty( $bot_data['agents'] ) ) {
                foreach ( $bot_data['agents'] as $agent ) {
                    $active_tokens[] = $agent;
                }
            }
        }

        $custom_bots_raw = isset( $settings['custom_user_agents'] ) ? $settings['custom_user_agents'] : '';
        if ( ! empty( $custom_bots_raw ) ) {
            $custom_bots = array_filter( array_map( 'trim', explode( "\n", $custom_bots_raw ) ) );
            $active_tokens = array_merge( $active_tokens, $custom_bots );
        }

        return array_unique( array_values( array_filter( $active_tokens ) ) );
    }

    /**
     * Intercept request and block matching bots.
     *
     * SEO SAFETY: Whitelisted search engines are checked FIRST and always
     * allowed through, even if a custom pattern would otherwise match them.
     */
    public function block_ai_crawlers() {
        $settings = get_option( 'btsld_settings', array() );

        // Master switch check
        if ( ! isset( $settings['enabled'] ) || '1' !== (string) $settings['enabled'] ) {
            return;
        }

        // User agent check
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        if ( empty( $user_agent ) ) {
            return;
        }

        // ============================================================
        // SEO SAFETY CHECK — Whitelisted search engines ALWAYS pass.
        // This runs BEFORE any blocking logic to protect your rankings.
        // ============================================================
        $whitelisted_as = $this->is_whitelisted_search_engine( $user_agent );
        if ( false !== $whitelisted_as ) {
            // This is a legitimate search engine or social media indexer.
            // Allow the request through immediately, regardless of block rules.
            return;
        }

        $bots_to_block = $this->get_active_bot_tokens();

        foreach ( $bots_to_block as $bot_identifier ) {
            if ( stripos( $user_agent, $bot_identifier ) !== false ) {
                if ( ! isset( $settings['log_blocked_bots'] ) || '1' === (string) $settings['log_blocked_bots'] ) {
                    $this->log_blocked_request( $bot_identifier, $user_agent );
                }

                status_header( 403 );
                nocache_headers();

                wp_die(
                    '<h1>403 Forbidden</h1>' .
                    '<p>' . esc_html__( 'Access denied by Bot Traffic Shield. AI crawlers and unauthorized automated scrapers are prohibited from indexing this website.', 'bot-traffic-shield' ) . '</p>',
                    esc_html__( 'Access Denied', 'bot-traffic-shield' ),
                    array( 'response' => 403 )
                );
            }
        }
    }

    /**
     * Get validated client IP address.
     *
     * @return string
     */
    private function get_client_ip() {
        $ip = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
        if ( $ip ) {
            return $ip;
        }

        $xff_sanitized = filter_input( INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        if ( $xff_sanitized ) {
            $parts = array_map( 'trim', explode( ',', $xff_sanitized ) );
            foreach ( $parts as $candidate ) {
                if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                    return $candidate;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Log the blocked request and update aggregate time-series data for chart rendering.
     *
     * @param string $bot_identifier Matched bot rule.
     * @param string $full_user_agent Full User-Agent string.
     */
    private function log_blocked_request( $bot_identifier, $full_user_agent ) {
        $timestamp = time();
        $today     = gmdate( 'Y-m-d', $timestamp );

        // 1. Increment total lifetime blocked count
        $count = (int) get_option( 'btsld_blocked_count', 0 );
        update_option( 'btsld_blocked_count', $count + 1 );

        // 2. Update daily time-series statistics for charts
        $daily_stats = get_option( 'btsld_stats_daily', array() );
        if ( ! is_array( $daily_stats ) ) {
            $daily_stats = array();
        }

        if ( ! isset( $daily_stats[ $today ] ) ) {
            $daily_stats[ $today ] = array(
                'total'  => 0,
                'by_bot' => array(),
            );
        }

        $daily_stats[ $today ]['total']++;

        $bot_clean = sanitize_text_field( $bot_identifier );
        if ( ! isset( $daily_stats[ $today ]['by_bot'][ $bot_clean ] ) ) {
            $daily_stats[ $today ]['by_bot'][ $bot_clean ] = 0;
        }
        $daily_stats[ $today ]['by_bot'][ $bot_clean ]++;

        if ( count( $daily_stats ) > 60 ) {
            ksort( $daily_stats );
            $daily_stats = array_slice( $daily_stats, -60, null, true );
        }

        update_option( 'btsld_stats_daily', $daily_stats, false );

        // 3. Update detailed recent event log (last 150 entries)
        $log = get_option( 'btsld_blocked_log', array() );
        if ( ! is_array( $log ) ) {
            $log = array();
        }

        $log_entry = array(
            'time'       => $timestamp,
            'bot'        => $bot_clean,
            'user_agent' => sanitize_text_field( $full_user_agent ),
            'ip'         => $this->get_client_ip(),
        );

        array_unshift( $log, $log_entry );

        if ( count( $log ) > 150 ) {
            $log = array_slice( $log, 0, 150 );
        }

        update_option( 'btsld_blocked_log', $log, false );
    }

    /**
     * Get chart data formatted for frontend charting libraries.
     *
     * @param int $days Number of days to retrieve (default 7).
     * @return array Formatted chart dataset.
     */
    public static function get_chart_data( $days = 7 ) {
        $daily_stats = get_option( 'btsld_stats_daily', array() );
        if ( ! is_array( $daily_stats ) ) {
            $daily_stats = array();
        }

        $labels      = array();
        $series_data = array();
        $bot_totals  = array();

        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $date_key = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $labels[] = gmdate( 'M j', strtotime( $date_key ) );

            $day_count = 0;
            if ( isset( $daily_stats[ $date_key ] ) ) {
                $day_count = (int) $daily_stats[ $date_key ]['total'];

                if ( ! empty( $daily_stats[ $date_key ]['by_bot'] ) ) {
                    foreach ( $daily_stats[ $date_key ]['by_bot'] as $b_name => $b_count ) {
                        if ( ! isset( $bot_totals[ $b_name ] ) ) {
                            $bot_totals[ $b_name ] = 0;
                        }
                        $bot_totals[ $b_name ] += $b_count;
                    }
                }
            }
            $series_data[] = $day_count;
        }

        arsort( $bot_totals );

        return array(
            'labels'     => $labels,
            'series'     => $series_data,
            'bot_totals' => array_slice( $bot_totals, 0, 8, true ),
        );
    }

    /**
     * Add robots.txt rules for blocked bots AND explicit Allow rules for
     * whitelisted search engines to guarantee SEO safety.
     *
     * @param string $output Existing robots.txt contents.
     * @param bool   $public Whether site is public.
     * @return string
     */
    public function add_rules_to_robots_txt( $output, $public ) {
        if ( ! $public ) {
            return $output;
        }

        $settings = get_option( 'btsld_settings', array() );
        if ( ! isset( $settings['enabled'] ) || '1' !== (string) $settings['enabled'] ) {
            return $output;
        }

        $rules = "\n# --- Start Bot Traffic Shield v" . BTSLD_VERSION . " Rules ---\n";

        // 1. Explicitly ALLOW all whitelisted search engines first.
        //    This ensures they are never accidentally blocked by broad Disallow rules.
        $whitelist = self::get_search_engine_whitelist();
        if ( ! empty( $whitelist ) ) {
            $rules .= "\n# Whitelisted Search Engines (Always Allowed)\n";
            $added_tokens = array();
            foreach ( $whitelist as $token => $label ) {
                // Avoid duplicate entries for similar tokens (e.g., Googlebot variants)
                $base_token = strtok( $token, '-' );
                if ( in_array( $base_token, $added_tokens, true ) && 'Googlebot' !== $token ) {
                    continue;
                }
                $rules .= "User-agent: " . esc_attr( $token ) . "\n";
                $rules .= "Allow: /\n";
                $added_tokens[] = $base_token;
            }
        }

        // 2. Disallow AI crawlers and scrapers.
        $bots_to_block = $this->get_active_bot_tokens();
        if ( ! empty( $bots_to_block ) ) {
            $rules .= "\n# Blocked AI Crawlers & Scrapers\n";
            foreach ( $bots_to_block as $bot ) {
                $rules .= "User-agent: " . esc_attr( $bot ) . "\n";
                $rules .= "Disallow: /\n";
            }
        }

        $rules .= "\n# --- End Bot Traffic Shield Rules ---\n";

        return $output . $rules;
    }
}