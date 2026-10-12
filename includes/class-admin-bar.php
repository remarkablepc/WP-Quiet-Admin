<?php
/**
 * WP Quiet Admin Bar Integration
 *
 * Displays a sleek notification counter in the WordPress Admin Toolbar.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Quiet_Admin_Admin_Bar', false ) ) {
class WP_Quiet_Admin_Admin_Bar {

	/**
	 * Settings instance.
	 *
	 * @var WP_Quiet_Admin_Settings
	 */
	protected $settings;

	/**
	 * Notice Manager instance.
	 *
	 * @var WP_Quiet_Admin_Notice_Manager
	 */
	protected $notice_manager;

	/**
	 * Constructor.
	 *
	 * @param WP_Quiet_Admin_Settings       $settings
	 * @param WP_Quiet_Admin_Notice_Manager $notice_manager
	 */
	public function __construct( WP_Quiet_Admin_Settings $settings, WP_Quiet_Admin_Notice_Manager $notice_manager ) {
		$this->settings       = $settings;
		$this->notice_manager = $notice_manager;

		add_action( 'admin_bar_menu', array( $this, 'add_toolbar_node' ), 90 );
	}

	/**
	 * Add Quiet Admin node to admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function add_toolbar_node( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->settings->get( 'show_admin_bar', true ) ) {
			return;
		}

		$counts    = $this->notice_manager->get_notice_counts();
		$hub_url   = admin_url( 'index.php?page=wp-quiet-admin' );
		$has_high  = ( $counts['high'] > 0 );
		$has_notices = ( $counts['total'] > 0 );

		$icon_html  = '<span class="ab-icon dashicons dashicons-bell"></span>';
		$title_html = '<span class="ab-label">' . esc_html__( 'Quiet Admin', 'wp-quiet-admin' ) . '</span>';

		// Multi-pill color badges:
		// 🔴 Red pill: Critical notices
		// 🟡 Yellow pill: Warnings & Updates
		// 🟢 Green pill: Muted promotions & other notices
		$low_and_other = $counts['low'] + $counts['other'];
		$badges_html   = '<span class="qa-bar-badges-group">';

		if ( $counts['total'] === 0 ) {
			$badges_html .= '<span class="qa-pill qa-pill-zero">0</span>';
		} else {
			if ( $counts['high'] > 0 ) {
				$badges_html .= '<span class="qa-pill qa-pill-high" title="' . sprintf( esc_attr__( '%d Critical Notices', 'wp-quiet-admin' ), $counts['high'] ) . '">' . absint( $counts['high'] ) . '</span>';
			}
			if ( $counts['medium'] > 0 ) {
				$badges_html .= '<span class="qa-pill qa-pill-medium" title="' . sprintf( esc_attr__( '%d Warnings', 'wp-quiet-admin' ), $counts['medium'] ) . '">' . absint( $counts['medium'] ) . '</span>';
			}
			if ( $low_and_other > 0 ) {
				$badges_html .= '<span class="qa-pill qa-pill-low qa-pill-green" style="background-color:#00a32a !important;color:#ffffff !important;" title="' . sprintf( esc_attr__( '%d Muted Notices', 'wp-quiet-admin' ), $low_and_other ) . '">' . absint( $low_and_other ) . '</span>';
			}
		}
		$badges_html .= '</span>';

		// Main Toolbar Node: Count pills placed on the right of Quiet Admin
		$wp_admin_bar->add_node( array(
			'id'    => 'wp-quiet-admin',
			'title' => $icon_html . $title_html . $badges_html,
			'href'  => $hub_url,
			'meta'  => array(
				'title' => sprintf( __( 'Quiet Admin: %d active notices (%d critical, %d warnings, %d reviews/promos)', 'wp-quiet-admin' ), $counts['total'], $counts['high'], $counts['medium'], $low_and_other ),
				'class' => 'wp-quiet-admin-bar-root ' . ( $has_high ? 'has-critical-alerts' : '' ),
			),
		) );

		// Sub-item 1: WordPress Core (First in dropdown)
		$wp_count_badge = $counts['wordpress'];
		$wp_orb_class   = 'qa-orb-blue';
		if ( ! empty( $counts['wp_high'] ) && $counts['wp_high'] > 0 ) {
			$wp_orb_class = 'qa-orb-red';
		} elseif ( ! empty( $counts['wp_medium'] ) && $counts['wp_medium'] > 0 ) {
			$wp_orb_class = 'qa-orb-yellow';
		}
		$wp_admin_bar->add_node( array(
			'parent' => 'wp-quiet-admin',
			'id'     => 'wp-quiet-admin-wordpress',
			'title'  => '<span class="qa-sub-item-wrap"><span class="qa-sub-left"><span class="qa-sub-orb ' . esc_attr( $wp_orb_class ) . '"></span> ' . esc_html__( 'WordPress Core', 'wp-quiet-admin' ) . '</span><span class="qa-sub-count">' . absint( $wp_count_badge ) . '</span></span>',
			'href'   => add_query_arg( 'tab', 'wordpress', $hub_url ),
		) );

		// Sub-item 2: High Priority (Critical)
		$wp_admin_bar->add_node( array(
			'parent' => 'wp-quiet-admin',
			'id'     => 'wp-quiet-admin-high',
			'title'  => '<span class="qa-sub-item-wrap"><span class="qa-sub-left"><span class="qa-sub-orb qa-orb-red"></span> ' . esc_html__( 'Critical (High)', 'wp-quiet-admin' ) . '</span><span class="qa-sub-count">' . absint( $counts['high'] ) . '</span></span>',
			'href'   => add_query_arg( 'tab', 'high', $hub_url ),
		) );

		// Sub-item 3: Warnings & Updates
		$wp_admin_bar->add_node( array(
			'parent' => 'wp-quiet-admin',
			'id'     => 'wp-quiet-admin-medium',
			'title'  => '<span class="qa-sub-item-wrap"><span class="qa-sub-left"><span class="qa-sub-orb qa-orb-yellow"></span> ' . esc_html__( 'Warnings & Updates', 'wp-quiet-admin' ) . '</span><span class="qa-sub-count">' . absint( $counts['medium'] ) . '</span></span>',
			'href'   => add_query_arg( 'tab', 'medium', $hub_url ),
		) );

		// Sub-item 4: Reviews & Promos
		$low_and_other = $counts['low'] + $counts['other'];
		$wp_admin_bar->add_node( array(
			'parent' => 'wp-quiet-admin',
			'id'     => 'wp-quiet-admin-muted',
			'title'  => '<span class="qa-sub-item-wrap"><span class="qa-sub-left"><span class="qa-sub-orb qa-orb-green"></span> ' . esc_html__( 'Reviews & Promos', 'wp-quiet-admin' ) . '</span><span class="qa-sub-count">' . absint( $low_and_other ) . '</span></span>',
			'href'   => add_query_arg( 'tab', 'muted', $hub_url ),
		) );

		// Sub-item 5: Snoozed Notices (if any)
		$snoozed_count = $counts['snoozed'] ?? 0;
		if ( $snoozed_count > 0 ) {
			$wp_admin_bar->add_node( array(
				'parent' => 'wp-quiet-admin',
				'id'     => 'wp-quiet-admin-snoozed',
				'title'  => '<span class="qa-sub-item-wrap"><span class="qa-sub-left"><span class="qa-sub-orb qa-orb-purple"></span> ' . esc_html__( 'Snoozed', 'wp-quiet-admin' ) . '</span><span class="qa-sub-count">' . absint( $snoozed_count ) . '</span></span>',
				'href'   => add_query_arg( 'tab', 'snoozed', $hub_url ),
			) );
		}

		// Sub-item 6: Archive (Dismissed)
		$archive_count = $counts['dismissed'] ?? 0;
		if ( $archive_count > 0 ) {
			$wp_admin_bar->add_node( array(
				'parent' => 'wp-quiet-admin',
				'id'     => 'wp-quiet-admin-history',
				'title'  => '<span class="qa-sub-item-wrap"><span class="qa-sub-left"><span class="qa-sub-orb qa-orb-grey"></span> ' . esc_html__( 'Archive', 'wp-quiet-admin' ) . '</span><span class="qa-sub-count">' . absint( $archive_count ) . '</span></span>',
				'href'   => add_query_arg( 'tab', 'history', $hub_url ),
			) );
		}

		// Sub-item 7: Settings
		$wp_admin_bar->add_node( array(
			'parent' => 'wp-quiet-admin',
			'id'     => 'wp-quiet-admin-settings',
			'title'  => '<span class="qa-sub-item-wrap"><span class="qa-sub-left"><span class="qa-sub-orb qa-orb-gear"></span> ' . esc_html__( 'Rules & Settings', 'wp-quiet-admin' ) . '</span></span>',
			'href'   => add_query_arg( 'tab', 'settings', $hub_url ),
		) );
	}
}
}
