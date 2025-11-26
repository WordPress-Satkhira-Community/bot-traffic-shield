<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Bar Badge for Bot Traffic Shield
 * Shows "Blocked today: X" in the WordPress admin toolbar
 *
 * @since 1.0.4
 */
class BTSLD_Admin_Bar {

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_badge' ), 100 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_bar_styles' ) );
    }

    /**
     * Get count of bots blocked today.
     *
     * @return int Number of blocked requests today.
     */
    private function get_today_blocked_count() {
        $log = get_option( 'btsld_blocked_log', array() );
        if ( ! is_array( $log ) || empty( $log ) ) {
            return 0;
        }

        // Get start of today (midnight).
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
     * Add badge to admin bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar WordPress admin bar object.
     */
    public function add_admin_bar_badge( $wp_admin_bar ) {
        // Only show to users who can manage options.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Get blocked count for today.
        $count = $this->get_today_blocked_count();

        // Build the title with badge.
        $title = sprintf(
            '<span class="btsld-admin-bar-icon">🛡️</span><span class="btsld-admin-bar-label">%s</span><span class="btsld-admin-bar-count">%d</span>',
            esc_html__( 'Blocked Today:', 'bot-traffic-shield' ),
            (int) $count
        );

        // Add parent node.
        $wp_admin_bar->add_node(
            array(
                'id'    => 'btsld_admin_bar',
                'title' => $title,
                'href'  => esc_url( admin_url( 'options-general.php?page=bot-traffic-shield' ) ),
                'meta'  => array(
                    'class' => 'btsld-admin-bar-badge',
                    'title' => esc_attr__( 'Bot Traffic Shield - View blocked bot logs', 'bot-traffic-shield' ),
                ),
            )
        );

        // Add child node - View Logs.
        $wp_admin_bar->add_node(
            array(
                'parent' => 'btsld_admin_bar',
                'id'     => 'btsld_view_logs',
                'title'  => esc_html__( '📊 View Block Log', 'bot-traffic-shield' ),
                'href'   => esc_url( admin_url( 'options-general.php?page=bot-traffic-shield#log' ) ),
            )
        );

        // Add child node - Settings.
        $wp_admin_bar->add_node(
            array(
                'parent' => 'btsld_admin_bar',
                'id'     => 'btsld_settings',
                'title'  => esc_html__( '⚙️ Settings', 'bot-traffic-shield' ),
                'href'   => esc_url( admin_url( 'options-general.php?page=bot-traffic-shield' ) ),
            )
        );

        // Show total all-time count as well.
        $total = (int) get_option( 'btsld_blocked_count', 0 );
        $wp_admin_bar->add_node(
            array(
                'parent' => 'btsld_admin_bar',
                'id'     => 'btsld_total',
                'title'  => sprintf(
                    /* translators: %d: total blocked count */
                    esc_html__( 'Total Blocked: %s', 'bot-traffic-shield' ),
                    '<strong>' . number_format_i18n( $total ) . '</strong>'
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
        // Only load if admin bar is showing.
        if ( ! is_admin_bar_showing() ) {
            return;
        }

        $custom_css = "
            /* Bot Traffic Shield - Admin Bar Badge */
            #wpadminbar .btsld-admin-bar-badge > .ab-item {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 0 12px !important;
            }
            
            #wpadminbar .btsld-admin-bar-icon {
                font-size: 16px;
                line-height: 1;
            }
            
            #wpadminbar .btsld-admin-bar-label {
                font-size: 13px;
                opacity: 0.9;
            }
            
            #wpadminbar .btsld-admin-bar-count {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                color: #ffffff;
                font-weight: 600;
                font-size: 12px;
                padding: 2px 8px;
                border-radius: 10px;
                min-width: 20px;
                text-align: center;
                box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
            }
            
            #wpadminbar .btsld-admin-bar-badge:hover .btsld-admin-bar-count {
                background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            }
            
            #wpadminbar .btsld-admin-bar-total .ab-item {
                cursor: default;
                opacity: 0.8;
                font-size: 13px;
            }
            
            #wpadminbar .btsld-admin-bar-total .ab-item:hover {
                color: #fff;
                background: transparent;
            }
        ";

        wp_add_inline_style( 'admin-bar', $custom_css );
    }
}