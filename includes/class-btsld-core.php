<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BTSLD_Core {

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'block_ai_crawlers' ), 1 );
        add_filter( 'robots_txt', array( $this, 'add_rules_to_robots_txt' ), 99, 2 );
    }

    /**
     * Runs on plugin activation.
     */
    public static function on_activate() {
        if ( ! get_option( 'btsld_settings' ) ) {
            $default_settings = array(
                'enabled'            => '1',
                'custom_user_agents' => '',
                'log_blocked_bots'   => '1',
            );
            add_option( 'btsld_settings', $default_settings );
            add_option( 'btsld_blocked_log', array() );
            add_option( 'btsld_blocked_count', 0 );
        }
    }
    
    public function get_default_bot_list() {
        return array(
            'ChatGPT-User', 'Google-Extended', 'GPTBot', 'CCBot', 'anthropic-ai',
            'ClaudeBot', 'cohere-ai', 'Bytespider', 'Amazonbot', 'Applebot',
            'PerplexityBot', 'Omgilibot', 'Diffbot', 'FacebookBot'
        );
    }

    private function get_full_bot_list() {
        $settings = get_option( 'btsld_settings', array() );
        $default_bots = $this->get_default_bot_list();
        $custom_bots_raw = isset( $settings['custom_user_agents'] ) ? $settings['custom_user_agents'] : '';
        $custom_bots = array_filter( array_map( 'trim', explode( "\n", $custom_bots_raw ) ) );
        return array_unique( array_merge( $default_bots, $custom_bots ) );
    }

    public function block_ai_crawlers() {
        $settings = get_option( 'btsld_settings' );
        if ( ! isset( $settings['enabled'] ) || '1' !== $settings['enabled'] ) {
            return;
        }

        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        if ( empty( $user_agent ) ) {
            return;
        }

        $bots_to_block = $this->get_full_bot_list();

        foreach ( $bots_to_block as $bot_identifier ) {
            if ( stripos( $user_agent, $bot_identifier ) !== false ) {
                if ( isset( $settings['log_blocked_bots'] ) && '1' === $settings['log_blocked_bots'] ) {
                    $this->log_blocked_request( $bot_identifier, $user_agent );
                }
                header( 'HTTP/1.1 403 Forbidden' );
                die( 'Access denied by Bot Traffic Shield. AI crawlers and data scrapers are not permitted.' );
            }
        }
    }

    /**
     * Get a validated client IP without directly using $_SERVER,
     * to satisfy code sniffers and keep it robust.
     */
    private function get_client_ip() {
        // Try REMOTE_ADDR first
        $ip = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
        if ( $ip ) {
            return $ip;
        }

        // Fallback: check X-Forwarded-For (may contain a list)
        $xff = filter_input( INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', FILTER_UNSAFE_RAW );
        if ( $xff ) {
            $parts = array_map( 'trim', explode( ',', $xff ) );
            foreach ( $parts as $candidate ) {
                if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                    return $candidate;
                }
            }
        }

        // Nothing valid found
        return '';
    }
    
    private function log_blocked_request( $bot_identifier, $full_user_agent ) {
        // Increment total count.
        $count = (int) get_option( 'btsld_blocked_count', 0 );
        update_option( 'btsld_blocked_count', $count + 1 );
        
        // Get the existing log.
        $log = get_option( 'btsld_blocked_log', array() );

        // Get validated client IP via helper (no direct $_SERVER usage here).
        $ip_address = $this->get_client_ip();
        
        // Sanitize the user agent and bot identifier string for storage.
        $sanitized_user_agent = sanitize_text_field( $full_user_agent );
        $sanitized_bot_id     = sanitize_text_field( $bot_identifier );

        $log_entry = array(
            'time'       => current_time( 'timestamp' ),
            'bot'        => $sanitized_bot_id,
            'user_agent' => $sanitized_user_agent,
            'ip'         => $ip_address,
        );

        // Add the new entry to the beginning of the array.
        array_unshift( $log, $log_entry );

        // Keep the log from getting too big (e.g., max 100 entries).
        if ( count( $log ) > 100 ) {
            $log = array_slice( $log, 0, 100 );
        }

        update_option( 'btsld_blocked_log', $log );
    }

    public function add_rules_to_robots_txt( $output, $public ) {
        if ( ! $public ) {
            return $output;
        }
        $settings = get_option( 'btsld_settings' );
        if ( ! isset( $settings['enabled'] ) || '1' !== $settings['enabled'] ) {
            return $output;
        }

        $bots_to_block = $this->get_full_bot_list();
        $rules = "\n# Rules added by Bot Traffic Shield\n";

        foreach ( $bots_to_block as $bot ) {
            $rules .= "User-agent: " . esc_html( $bot ) . "\n";
            $rules .= "Disallow: /\n";
        }
        return $output . $rules;
    }
}