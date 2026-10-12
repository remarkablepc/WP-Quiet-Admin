<?php
/**
 * Plugin Name:       WP Quiet Admin
 * Plugin URI:        https://wordpress.org/plugins/wp-quiet-admin/
 * Description:       Intelligently silences noisy admin notices and groups them by priority into a clean, dedicated notification hub with snooze and pass-through controls.
 * Version:           1.0.10
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            RemarkablePC
 * Author URI:        https://remarkablepc.com/
 * Text Domain:       wp-quiet-admin
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Copyright (c) 2026 RemarkablePC / David Hewitt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Prevent duplicate activation collision if another copy or folder of WP Quiet Admin is already active.
if ( defined( 'WP_QUIET_ADMIN_VERSION' ) || class_exists( 'WP_Quiet_Admin', false ) || function_exists( 'wp_quiet_admin' ) ) {
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'WP Quiet Admin:', 'wp-quiet-admin' ) . '</strong> ' . esc_html__( 'Another copy or folder of WP Quiet Admin is already active. Please deactivate the existing version before activating this one.', 'wp-quiet-admin' ) . '</p></div>';
	} );
	return;
}

// Define Plugin Constants
defined( 'WP_QUIET_ADMIN_VERSION' ) || define( 'WP_QUIET_ADMIN_VERSION', '1.0.10' );
defined( 'WP_QUIET_ADMIN_FILE' ) || define( 'WP_QUIET_ADMIN_FILE', __FILE__ );
defined( 'WP_QUIET_ADMIN_DIR' ) || define( 'WP_QUIET_ADMIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'WP_QUIET_ADMIN_URL' ) || define( 'WP_QUIET_ADMIN_URL', plugin_dir_url( __FILE__ ) );
defined( 'WP_QUIET_ADMIN_BASENAME' ) || define( 'WP_QUIET_ADMIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoload / Include required classes
require_once WP_QUIET_ADMIN_DIR . 'includes/class-classifier.php';
require_once WP_QUIET_ADMIN_DIR . 'includes/class-settings.php';
require_once WP_QUIET_ADMIN_DIR . 'includes/class-notice-manager.php';
require_once WP_QUIET_ADMIN_DIR . 'includes/class-admin-bar.php';
require_once WP_QUIET_ADMIN_DIR . 'includes/class-admin-page.php';

if ( ! class_exists( 'WP_Quiet_Admin', false ) ) {
/**
 * Main Plugin Bootstrap Class
 */
final class WP_Quiet_Admin {

	/**
	 * Single instance of the class.
	 *
	 * @var WP_Quiet_Admin|null
	 */
	private static $instance = null;

	/**
	 * Settings instance.
	 *
	 * @var WP_Quiet_Admin_Settings
	 */
	public $settings;

	/**
	 * Classifier instance.
	 *
	 * @var WP_Quiet_Admin_Classifier
	 */
	public $classifier;

	/**
	 * Notice Manager instance.
	 *
	 * @var WP_Quiet_Admin_Notice_Manager
	 */
	public $notice_manager;

	/**
	 * Admin Bar instance.
	 *
	 * @var WP_Quiet_Admin_Admin_Bar
	 */
	public $admin_bar;

	/**
	 * Admin Page instance.
	 *
	 * @var WP_Quiet_Admin_Admin_Page
	 */
	public $admin_page;

	/**
	 * Main WP_Quiet_Admin Instance.
	 *
	 * @return WP_Quiet_Admin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Initialize plugin components.
	 */
	private function init_components() {
		$this->settings       = new WP_Quiet_Admin_Settings();
		$this->classifier     = new WP_Quiet_Admin_Classifier();
		$this->notice_manager = new WP_Quiet_Admin_Notice_Manager( $this->settings, $this->classifier );
		$this->admin_bar      = new WP_Quiet_Admin_Admin_Bar( $this->settings, $this->notice_manager );
		$this->admin_page     = new WP_Quiet_Admin_Admin_Page( $this->settings, $this->notice_manager );
	}

	/**
	 * Register general hooks.
	 */
	private function init_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_filter( 'plugin_action_links_' . WP_QUIET_ADMIN_BASENAME, array( $this, 'add_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Enqueue styles on frontend when admin bar is active.
	 */
	public function enqueue_frontend_assets() {
		if ( is_admin_bar_showing() && current_user_can( 'manage_options' ) ) {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style(
				'wp-quiet-admin-global',
				WP_QUIET_ADMIN_URL . 'assets/css/admin.css',
				array( 'dashicons' ),
				WP_QUIET_ADMIN_VERSION
			);
		}
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		// Enqueue dashicons dependency and general styles for admin bar indicator across all admin screens
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'wp-quiet-admin-global',
			WP_QUIET_ADMIN_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			WP_QUIET_ADMIN_VERSION
		);

		// Enqueue scripts
		wp_enqueue_script(
			'wp-quiet-admin-js',
			WP_QUIET_ADMIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WP_QUIET_ADMIN_VERSION,
			true
		);

		wp_localize_script(
			'wp-quiet-admin-js',
			'wpQuietAdminData',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wp_quiet_admin_nonce' ),
				'isHubPage' => ( 'dashboard_page_wp-quiet-admin' === $hook_suffix ),
				'strings'   => array(
					'dismissed'        => __( 'Notice dismissed.', 'wp-quiet-admin' ),
					'snoozed'          => __( 'Notice snoozed.', 'wp-quiet-admin' ),
					'unsnoozed'        => __( 'Notice restored to active view.', 'wp-quiet-admin' ),
					'passthroughAdded' => __( 'Plugin set to Show on Admin Screens.', 'wp-quiet-admin' ),
					'blacklistAdded'   => __( 'Plugin muted everywhere.', 'wp-quiet-admin' ),
					'confirmClear'     => __( 'Are you sure you want to clear all stored notices?', 'wp-quiet-admin' ),
				),
			)
		);
	}

	/**
	 * Add Settings and View Notices links to Plugins page.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public function add_action_links( $links ) {
		$custom_links = array(
			'<a href="' . esc_url( admin_url( 'index.php?page=wp-quiet-admin' ) ) . '">' . __( 'Notice Hub', 'wp-quiet-admin' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'index.php?page=wp-quiet-admin&tab=settings' ) ) . '">' . __( 'Settings', 'wp-quiet-admin' ) . '</a>',
		);
		return array_merge( $custom_links, $links );
	}

	/**
	 * Add custom metadata links below the plugin description on Plugins page.
	 *
	 * @param string[] $links An array of the plugin's metadata, including the version, author, and author URI.
	 * @param string   $file  Path to the plugin file relative to the plugins directory.
	 * @return string[]
	 */
	public function add_plugin_row_meta( $links, $file ) {
		if ( WP_QUIET_ADMIN_BASENAME === $file ) {
			$sponsor_link = '<a href="' . esc_url( 'https://github.com/sponsors/remarkablepc?utm_source=wp-quiet-admin&utm_medium=plugin-row-meta' ) . '" target="_blank" rel="noopener noreferrer" style="color: #ea4aaa; font-weight: 600;"><span class="dashicons dashicons-heart" style="font-size: 14px; width: 14px; height: 14px; vertical-align: -2px; margin-right: 2px;"></span>' . esc_html__( 'Sponsor on GitHub', 'wp-quiet-admin' ) . '</a>';
			$paypal_link  = '<a href="' . esc_url( 'https://www.paypal.com/ncp/payment/EKH76RTYHH24S' ) . '" target="_blank" rel="noopener noreferrer" style="color: #0070ba; font-weight: 600;"><span class="dashicons dashicons-money-alt" style="font-size: 14px; width: 14px; height: 14px; vertical-align: -2px; margin-right: 2px;"></span>' . esc_html__( 'Donate via PayPal / Venmo', 'wp-quiet-admin' ) . '</a>';

			$links[] = $sponsor_link;
			$links[] = $paypal_link;
		}
		return $links;
	}
}
}

if ( ! function_exists( 'wp_quiet_admin' ) ) {
/**
 * Initialize WP Quiet Admin.
 */
function wp_quiet_admin() {
	return WP_Quiet_Admin::instance();
}
}

// Start the plugin
add_action( 'plugins_loaded', 'wp_quiet_admin' );
