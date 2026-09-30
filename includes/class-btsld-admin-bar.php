<?php
/**
 * Admin Bar Toolbar Widget for Bot Traffic Shield.
 *
 * @package BotTrafficShield
 * @version 1.0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BTSLD_Admin_Bar {

    /**
     * Singleton instance.
     *
     * @var BTSLD_Admin_Bar|null
     */
    private static $_instance = null;

    /**
     * Main Instance.
     *
     * @return BTSLD_Admin_Bar
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
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_badge' ), 100 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_bar_styles' ) );
    }

    /**
     * Get count of bots blocked today.
     * Fast O(1) lookup using daily aggregated stats with fallback.
     *
     * @return int Number of blocked requests today.
     */
    private function get_today_blocked_count() {
        $today       = gmdate( 'Y-m-d' );
        $daily_stats = get_option( 'btsld_stats_daily', array() );

        if ( is_array( $daily_stats ) && isset( $daily_stats[ $today ]['total'] ) ) {
            return (int) $daily_stats[ $today ]['total'];
        }

        // Fallback: Check in recent block log array if stats are not initialized yet
        $log = get_option( 'btsld_blocked_log', array() );
        if ( ! is_array( $log ) || empty( $log ) ) {
            return 0;
        }

        $today_start = strtotime( 'today 00:00:00' );
        $count       = 0;

        foreach ( $log as $entry ) {
            $entry_time = isset( $entry['time'] ) ? (int) $entry['time'] : 0;
            if ( $entry_time >= $today_start ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Add badge and quick links to WordPress admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar WordPress admin bar object.
     */
    public function add_admin_bar_badge( $wp_admin_bar ) {
        // Only display to users with management permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Get blocked count for today and lifetime
        $count_today = $this->get_today_blocked_count();
        $count_total = (int) get_option( 'btsld_blocked_count', 0 );

        // Settings URL base
        $settings_base = admin_url( 'admin.php?page=bot-traffic-shield' );

        // Admin bar parent node title
        $title = sprintf(
            '<span class="btsld-admin-bar-icon">🛡️</span>' .
            '<span class="btsld-admin-bar-label">%s</span>' .
            '<span class="btsld-admin-bar-count">%s</span>',
            esc_html__( 'Blocked Today:', 'bot-traffic-shield' ),
            number_format_i18n( $count_today )
        );

        // Parent Node
        $wp_admin_bar->add_node(
            array(
                'id'    => 'btsld_admin_bar',
                'title' => $title,
                'href'  => esc_url( $settings_base ),
                'meta'  => array(
                    'class' => 'btsld-admin-bar-badge',
                    'title' => esc_attr__( 'Bot Traffic Shield - View Analytics & Crawler Rules', 'bot-traffic-shield' ),
                ),
            )
        );

        // Submenu: Dashboard & Analytics
        $wp_admin_bar->add_node(
            array(
                'parent' => 'btsld_admin_bar',
                'id'     => 'btsld_nav_dashboard',
                'title'  => esc_html__( '📊 Dashboard & Charts', 'bot-traffic-shield' ),
                'href'   => esc_url( $settings_base . '#tab-dashboard' ),
            )
        );

        // Submenu: AI Crawler Rules
        $wp_admin_bar->add_node(
            array(
                'parent' => 'btsld_admin_bar',
                'id'     => 'btsld_nav_ai_rules',
                'title'  => esc_html__( '🤖 AI Crawler Rules', 'bot-traffic-shield' ),
                'href'   => esc_url( $settings_base . '#tab-ai-crawlers' ),
            )
        );

        // Submenu: Activity Log
        $wp_admin_bar->add_node(
            array(
                'parent' => 'btsld_admin_bar',
                'id'     => 'btsld_nav_logs',
                'title'  => esc_html__( '📋 Blocked Activity Log', 'bot-traffic-shield' ),
                'href'   => esc_url( $settings_base . '#tab-log' ),
            )
        );

        // Submenu: General Settings
        $wp_admin_bar->add_node(
            array(
                'parent' => 'btsld_admin_bar',
                'id'     => 'btsld_nav_settings',
                'title'  => esc_html__( '⚙️ General Settings', 'bot-traffic-shield' ),
                'href'   => esc_url( $settings_base . '#tab-settings' ),
            )
        );

        // Submenu: Lifetime Blocks
        $wp_admin_bar->add_node(
            array(
                'parent' => 'btsld_admin_bar',
                'id'     => 'btsld_total_stat',
                'title'  => sprintf(
                    /* translators: %s: total blocked count */
                    esc_html__( 'Total Blocked: %s', 'bot-traffic-shield' ),
                    '<strong>' . number_format_i18n( $count_total ) . '</strong>'
                ),
                'href'   => false,
                'meta'   => array( 'class' => 'btsld-admin-bar-total' ),
            )
        );
    }

    /**
     * Enqueue inline CSS for admin bar badge.
     */
    public function enqueue_admin_bar_styles() {
        if ( ! is_admin_bar_showing() ) {
            return;
        }

        $custom_css = "
            /* Bot Traffic Shield Toolbar Badge */
            #wpadminbar .btsld-admin-bar-badge > .ab-item {
                display: flex !important;
                align-items: center;
                gap: 7px;
                padding: 0 10px !important;
            }
            #wpadminbar .btsld-admin-bar-icon {
                font-size: 15px;
                line-height: 1;
            }
            #wpadminbar .btsld-admin-bar-label {
                font-size: 12px;
                font-weight: 500;
                opacity: 0.9;
            }
            #wpadminbar .btsld-admin-bar-count {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                color: #ffffff !important;
                font-weight: 700;
                font-size: 11px;
                line-height: 1;
                padding: 3px 7px;
                border-radius: 12px;
                min-width: 18px;
                text-align: center;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            }
            #wpadminbar .btsld-admin-bar-badge:hover .btsld-admin-bar-count {
                background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            }
            #wpadminbar .btsld-admin-bar-total .ab-item {
                cursor: default;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                margin-top: 4px;
                padding-top: 4px;
                opacity: 0.85;
                font-size: 12px;
            }
            #wpadminbar .btsld-admin-bar-total .ab-item:hover {
                color: inherit !important;
                background: transparent !important;
            }
        ";

        wp_add_inline_style( 'admin-bar', $custom_css );
    }
}