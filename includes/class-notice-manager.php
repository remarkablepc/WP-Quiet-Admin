<?php
/**
 * WP Quiet Admin Notice Manager
 *
 * Intercepts, buffers, parses, filters, and manages WordPress admin notices.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Quiet_Admin_Notice_Manager {

	/**
	 * Option key for stored notices.
	 */
	const NOTICES_STORAGE_KEY = 'wp_quiet_admin_stored_notices';

	/**
	 * Option key for user audit log.
	 */
	const AUDIT_STORAGE_KEY = 'wp_quiet_admin_audit_log';

	/**
	 * Settings instance.
	 *
	 * @var WP_Quiet_Admin_Settings
	 */
	protected $settings;

	/**
	 * Classifier instance.
	 *
	 * @var WP_Quiet_Admin_Classifier
	 */
	protected $classifier;

	/**
	 * Active notices on current page load.
	 *
	 * @var array
	 */
	protected $current_page_notices = array();

	/**
	 * Buffer level tracking.
	 *
	 * @var int
	 */
	protected $buffer_level = 0;

	/**
	 * Constructor.
	 *
	 * @param WP_Quiet_Admin_Settings   $settings
	 * @param WP_Quiet_Admin_Classifier $classifier
	 */
	public function __construct( WP_Quiet_Admin_Settings $settings, WP_Quiet_Admin_Classifier $classifier ) {
		$this->settings   = $settings;
		$this->classifier = $classifier;

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Hook into all WordPress admin notice actions with early start and late end
		$hooks = array(
			'admin_notices',
			'all_admin_notices',
			'user_admin_notices',
			'network_admin_notices',
		);

		foreach ( $hooks as $hook ) {
			add_action( $hook, array( $this, 'start_buffer' ), -999999 );
			add_action( $hook, array( $this, 'end_buffer' ), 999999 );
		}

		// AJAX handlers for interacting with notices
		add_action( 'wp_ajax_wp_quiet_admin_dismiss', array( $this, 'ajax_dismiss_notice' ) );
		add_action( 'wp_ajax_wp_quiet_admin_dismiss_tab', array( $this, 'ajax_dismiss_tab' ) );
		add_action( 'wp_ajax_wp_quiet_admin_undo_dismiss', array( $this, 'ajax_undo_dismiss' ) );
		add_action( 'wp_ajax_wp_quiet_admin_restore_notice', array( $this, 'ajax_restore_notice' ) );
		add_action( 'wp_ajax_wp_quiet_admin_snooze', array( $this, 'ajax_snooze_notice' ) );
		add_action( 'wp_ajax_wp_quiet_admin_unsnooze', array( $this, 'ajax_unsnooze_notice' ) );
		add_action( 'wp_ajax_wp_quiet_admin_purge_history', array( $this, 'ajax_purge_history' ) );
		add_action( 'wp_ajax_wp_quiet_admin_clear_all', array( $this, 'ajax_clear_all_notices' ) );
		add_action( 'wp_ajax_wp_quiet_admin_bulk_dismiss', array( $this, 'ajax_bulk_dismiss' ) );
		add_action( 'wp_ajax_wp_quiet_admin_bulk_snooze', array( $this, 'ajax_bulk_snooze' ) );
		add_action( 'wp_ajax_wp_quiet_admin_wake_all', array( $this, 'ajax_wake_all' ) );
		add_action( 'wp_ajax_wp_quiet_admin_toggle_plugin', array( $this, 'ajax_toggle_plugin' ) );
	}

	/**
	 * Start output buffering for notice actions.
	 */
	public function start_buffer() {
		ob_start();
		$this->buffer_level++;
	}

	/**
	 * End output buffering, capture, parse, filter, and conditionally output.
	 */
	public function end_buffer() {
		if ( $this->buffer_level <= 0 ) {
			return;
		}

		$raw_html = ob_get_clean();
		$this->buffer_level--;

		if ( empty( trim( $raw_html ) ) ) {
			return;
		}

		// Process and filter the captured HTML
		$this->process_captured_html( $raw_html );
	}

	/**
	 * Process raw HTML captured from notice hooks.
	 *
	 * @param string $raw_html
	 */
	protected function process_captured_html( $raw_html ) {
		// Extract individual notice blocks
		$notice_blocks = $this->extract_notice_elements( $raw_html );

		$stored_notices    = $this->get_stored_notices();
		$passthrough_html  = '';
		$screen            = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$current_screen_id = ( $screen && isset( $screen->id ) ) ? $screen->id : '';
		$is_hub_page       = ( false !== strpos( $current_screen_id, 'wp-quiet-admin' ) );

		$installed_plugins = $this->settings->get_installed_plugins();

		foreach ( $notice_blocks as $html ) {
			if ( empty( trim( $html ) ) ) {
				continue;
			}

			// Always pass through inline script and style tags so plugin functionality isn't broken
			$trim_lower = strtolower( ltrim( $html ) );
			if ( 0 === strpos( $trim_lower, '<script' ) || 0 === strpos( $trim_lower, '<style' ) ) {
				if ( ! $is_hub_page ) {
					$passthrough_html .= $html;
				}
				continue;
			}

			// Detect source plugin
			$source = $this->detect_plugin_source( $html, $installed_plugins );

			// Classify priority
			$classification = $this->classifier->classify( $html, $source );

			// Check Blacklist
			if ( ! empty( $source['slug'] ) && $this->settings->is_blacklisted( $source['slug'] ) ) {
				// Completely muted & dropped
				continue;
			}

			// Unique ID based on normalized text content
			$text_hash = md5( trim( wp_strip_all_tags( $html ) ) );
			$notice_id = 'qa_' . substr( $text_hash, 0, 16 );

			$existing_snooze = isset( $stored_notices[ $notice_id ]['snoozed_until'] ) ? (int) $stored_notices[ $notice_id ]['snoozed_until'] : 0;
			if ( $existing_snooze > 0 && time() >= $existing_snooze ) {
				$existing_snooze = 0; // Snooze expired, awaken notice
			}
			$is_snoozed = ( $existing_snooze > 0 && time() < $existing_snooze );

			$notice_data = array(
				'id'            => $notice_id,
				'priority'      => $classification['priority'],
				'label'         => $classification['label'],
				'badge'         => $classification['badge'],
				'tags'          => $classification['tags'],
				'summary'       => $classification['summary'],
				'source_slug'   => $source['slug'],
				'source_name'   => $source['name'],
				'is_core'       => $source['is_core'],
				'html'          => $html,
				'timestamp'     => time(),
				'page_url'      => esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ),
				'dismissed'     => false,
				'snoozed_until' => $existing_snooze,
			);

			// Save to current page notices
			$this->current_page_notices[ $notice_id ] = $notice_data;

			// Add or update in persistent storage if not already dismissed
			if ( ! isset( $stored_notices[ $notice_id ] ) || ! $stored_notices[ $notice_id ]['dismissed'] ) {
				$stored_notices[ $notice_id ] = $notice_data;
			}

			// Determine if this notice should pass through to the screen
			$should_passthrough = false;

			if ( ! $is_hub_page && ! $is_snoozed ) {
				// 1. Live pass-through plugin?
				if ( ! empty( $source['slug'] ) && $this->settings->is_whitelisted( $source['slug'] ) ) {
					$should_passthrough = true;
				}
				// 2. High priority (Red/Critical) and setting enabled?
				elseif ( WP_Quiet_Admin_Classifier::PRIORITY_HIGH === $classification['priority'] && $this->settings->get( 'passthrough_high', true ) ) {
					$should_passthrough = true;
				}
				// 3. WordPress Core notice and setting enabled?
				elseif ( $source['is_core'] && $this->settings->get( 'passthrough_core', true ) ) {
					$should_passthrough = true;
				}
			}

			if ( $should_passthrough ) {
				$passthrough_html .= $html;
			}
		}

		// Update persistent cache
		$this->save_stored_notices( $stored_notices );

		// Print any passed-through notices back into the screen
		if ( ! empty( $passthrough_html ) ) {
			echo $passthrough_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Extract individual HTML notice blocks.
	 *
	 * @param string $html
	 * @return array
	 */
	protected function extract_notice_elements( $html ) {
		$elements = array();

		if ( class_exists( 'DOMDocument' ) ) {
			$libxml_previous_state = libxml_use_internal_errors( true );
			$dom                   = new DOMDocument( '1.0', 'UTF-8' );

			// Wrap in utf-8 header and container
			$encoded_html = '<?xml encoding="UTF-8"><div>' . $html . '</div>';
			$dom->loadHTML( $encoded_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
			libxml_clear_errors();
			libxml_use_internal_errors( $libxml_previous_state );

			$container = $dom->getElementsByTagName( 'div' )->item( 0 );
			if ( $container && $container->hasChildNodes() ) {
				foreach ( $container->childNodes as $node ) {
					if ( XML_ELEMENT_NODE === $node->nodeType ) {
						$elements[] = $dom->saveHTML( $node );
					}
				}
			}
		}

		// Fallback regex if DOMDocument failed or returned empty
		if ( empty( $elements ) ) {
			// Split by standard notice container div patterns
			$parts = preg_split( '/(?=<div[^>]*class=["\'][^"\']*(?:notice|update-nag|updated|error)[^"\']*["\'])/i', $html );
			foreach ( $parts as $part ) {
				if ( ! empty( trim( $part ) ) ) {
					$elements[] = $part;
				}
			}
		}

		return ! empty( $elements ) ? $elements : array( $html );
	}

	/**
	 * Detect which plugin produced a notice HTML.
	 *
	 * @param string $html
	 * @param array  $installed_plugins
	 * @return array
	 */
	protected function detect_plugin_source( $html, $installed_plugins ) {
		$lower_html = strtolower( $html );

		// Check if it's WordPress Core
		if ( false !== strpos( $lower_html, 'update-core.php' ) || 
		     false !== strpos( $lower_html, 'update-nag' ) ||
		     false !== strpos( $lower_html, 'new version of wordpress' ) ||
		     false !== strpos( $lower_html, 'wordpress version' ) ||
		     false !== strpos( $lower_html, 'welcome to wordpress' ) ||
		     false !== strpos( $lower_html, 'site-health' ) ||
		     ( false !== strpos( $lower_html, 'wordpress' ) && ( false !== strpos( $lower_html, 'update' ) || false !== strpos( $lower_html, 'security' ) || false !== strpos( $lower_html, 'database update' ) ) ) ) {
			return array(
				'slug'    => 'wordpress-core',
				'name'    => 'WordPress Core',
				'is_core' => true,
			);
		}

		// Search for installed plugin slugs or names in classes, IDs, links, and text
		foreach ( $installed_plugins as $slug => $data ) {
			$clean_slug = strtolower( $slug );
			$clean_name = strtolower( $data['name'] );

			// Check for slug in HTML classes, IDs, or URLs
			if ( false !== strpos( $lower_html, $clean_slug ) ) {
				return array(
					'slug'    => $slug,
					'name'    => $data['name'],
					'is_core' => false,
				);
			}

			// Check for plugin name in text if distinctive (> 4 chars)
			if ( strlen( $clean_name ) > 4 && false !== strpos( $lower_html, $clean_name ) ) {
				return array(
					'slug'    => $slug,
					'name'    => $data['name'],
					'is_core' => false,
				);
			}
		}

		return array(
			'slug'    => '',
			'name'    => __( 'Unknown Plugin / System', 'wp-quiet-admin' ),
			'is_core' => false,
		);
	}

	/**
	 * Get stored notices from options table.
	 *
	 * @return array
	 */
	public function get_stored_notices() {
		$notices = get_option( self::NOTICES_STORAGE_KEY, array() );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		// Periodic cleanup of expired notices
		$retention_days = (int) $this->settings->get( 'retention_days', 7 );
		$cutoff         = time() - ( $retention_days * DAY_IN_SECONDS );
		$cleaned        = false;

		foreach ( $notices as $id => $notice ) {
			// Auto-awaken expired snoozes
			if ( ! empty( $notice['snoozed_until'] ) && time() >= (int) $notice['snoozed_until'] ) {
				$notices[ $id ]['snoozed_until'] = 0;
				$cleaned = true;
			}

			// SAFETY REASSURANCE: Never auto-purge active high-priority (critical) or core alerts.
			// Critical security warnings, fatal errors, and core updates must remain active until addressed or explicitly dismissed.
			$is_critical = ( isset( $notice['priority'] ) && 'high' === $notice['priority'] );
			$is_core     = ! empty( $notice['is_core'] ) || ( isset( $notice['source_slug'] ) && 'wordpress-core' === $notice['source_slug'] );
			$is_active   = empty( $notice['dismissed'] );
			$is_snoozed  = ! empty( $notice['snoozed_until'] ) && ( time() < (int) $notice['snoozed_until'] );

			if ( ( ( $is_critical || $is_core ) && $is_active ) || $is_snoozed ) {
				continue;
			}

			if ( isset( $notice['timestamp'] ) && $notice['timestamp'] < $cutoff ) {
				unset( $notices[ $id ] );
				$cleaned = true;
			}
		}

		if ( $cleaned ) {
			$this->save_stored_notices( $notices );
		}

		return $notices;
	}

	/**
	 * Save stored notices to options table.
	 *
	 * @param array $notices
	 * @return bool
	 */
	public function save_stored_notices( $notices ) {
		return update_option( self::NOTICES_STORAGE_KEY, $notices, false );
	}

	/**
	 * Log a user action to the audit trail.
	 *
	 * Uses a capped rolling array in wp_options to guarantee zero database table bloat.
	 *
	 * @param string $action  Action key (e.g. 'dismiss', 'bulk_dismiss', 'snooze', etc.).
	 * @param array  $details Associated contextual details.
	 * @return bool
	 */
	public function log_audit( $action, $details = array() ) {
		$current_user = wp_get_current_user();
		$user_id      = ( $current_user && $current_user->exists() ) ? $current_user->ID : 0;
		$user_login   = ( $current_user && $current_user->exists() ) ? $current_user->user_login : 'system';
		$user_name    = ( $current_user && $current_user->exists() ) ? $current_user->display_name : 'System';

		$entry = array(
			'id'         => wp_generate_uuid4(),
			'timestamp'  => time(),
			'user_id'    => $user_id,
			'user_login' => $user_login,
			'user_name'  => $user_name,
			'action'     => sanitize_key( $action ),
			'details'    => $details,
			'ip'         => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
		);

		$log = get_option( self::AUDIT_STORAGE_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift( $log, $entry );

		// Cap rolling array at 150 items to keep option size tiny
		if ( count( $log ) > 150 ) {
			$log = array_slice( $log, 0, 150 );
		}

		// Prune entries older than retention days
		$retention_days = (int) $this->settings->get( 'audit_retention_days', 30 );
		$cutoff         = time() - ( $retention_days * DAY_IN_SECONDS );
		$log            = array_values( array_filter( $log, function( $item ) use ( $cutoff ) {
			return isset( $item['timestamp'] ) && $item['timestamp'] >= $cutoff;
		} ) );

		return update_option( self::AUDIT_STORAGE_KEY, $log, false );
	}

	/**
	 * Get the audit trail log.
	 *
	 * @return array
	 */
	public function get_audit_log() {
		$log = get_option( self::AUDIT_STORAGE_KEY, array() );
		if ( ! is_array( $log ) ) {
			return array();
		}

		$retention_days = (int) $this->settings->get( 'audit_retention_days', 30 );
		$cutoff         = time() - ( $retention_days * DAY_IN_SECONDS );
		$count_before   = count( $log );

		$log = array_values( array_filter( $log, function( $item ) use ( $cutoff ) {
			return isset( $item['timestamp'] ) && $item['timestamp'] >= $cutoff;
		} ) );

		if ( count( $log ) !== $count_before ) {
			update_option( self::AUDIT_STORAGE_KEY, $log, false );
		}

		return $log;
	}

	/**
	 * Clear the entire audit trail log.
	 *
	 * @return bool
	 */
	public function clear_audit_log() {
		return update_option( self::AUDIT_STORAGE_KEY, array(), false );
	}

	/**
	 * Get count of active (non-dismissed) notices grouped by priority and source.
	 *
	 * @return array
	 */
	public function get_notice_counts() {
		$notices = $this->get_stored_notices();
		$counts  = array(
			'total'       => 0,
			'high'        => 0,
			'medium'      => 0,
			'low'         => 0,
			'other'       => 0,
			'wordpress'   => 0,
			'wp_high'     => 0,
			'wp_medium'   => 0,
			'wp_low'      => 0,
			'wp_other'    => 0,
			'plugin_high' => 0,
			'dismissed'   => 0,
			'snoozed'     => 0,
		);

		foreach ( $notices as $notice ) {
			if ( ! empty( $notice['dismissed'] ) ) {
				$counts['dismissed']++;
				continue;
			}

			$is_snoozed = ! empty( $notice['snoozed_until'] ) && ( time() < (int) $notice['snoozed_until'] );
			if ( $is_snoozed ) {
				$counts['snoozed']++;
				continue;
			}

			$priority = $notice['priority'] ?? 'other';
			if ( ! isset( $counts[ $priority ] ) ) {
				$counts[ $priority ] = 0;
			}
			$counts[ $priority ]++;
			$counts['total']++;

			$is_core = ! empty( $notice['is_core'] ) || ( isset( $notice['source_slug'] ) && 'wordpress-core' === $notice['source_slug'] );
			if ( $is_core ) {
				$counts['wordpress']++;
				if ( 'high' === $priority ) {
					$counts['wp_high']++;
				} elseif ( 'medium' === $priority ) {
					$counts['wp_medium']++;
				} elseif ( 'low' === $priority ) {
					$counts['wp_low']++;
				} else {
					$counts['wp_other']++;
				}
			} else {
				if ( 'high' === $priority ) {
					$counts['plugin_high']++;
				}
			}
		}

		return $counts;
	}

	/**
	 * AJAX Handler: Dismiss a specific notice.
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( 'wp_quiet_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$notice_id = sanitize_key( $_POST['notice_id'] ?? '' );
		if ( empty( $notice_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid Notice ID' ), 400 );
		}

		$notices = $this->get_stored_notices();
		if ( isset( $notices[ $notice_id ] ) ) {
			$notices[ $notice_id ]['dismissed'] = true;
			$this->save_stored_notices( $notices );

			$source_name = ! empty( $notices[ $notice_id ]['source_name'] ) ? $notices[ $notice_id ]['source_name'] : ( $notices[ $notice_id ]['source_slug'] ?? 'Notice' );
			$this->log_audit( 'dismiss', array(
				'notice_id'   => $notice_id,
				'source_name' => $source_name,
				'summary'     => sprintf( __( 'Dismissed notice from %s', 'wp-quiet-admin' ), $source_name ),
			) );
		}

		wp_send_json_success( array(
			'message' => __( 'Notice dismissed.', 'wp-quiet-admin' ),
			'counts'  => $this->get_notice_counts(),
		) );
	}

	/**
	 * AJAX Handler: Dismiss all notices in a specific tab.
	 */
	public function ajax_dismiss_tab() {
		check_ajax_referer( 'wp_quiet_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$tab = sanitize_key( $_POST['tab'] ?? '' );
		if ( empty( $tab ) ) {
			wp_send_json_error( array( 'message' => 'Invalid Tab' ), 400 );
		}

		$notices       = $this->get_stored_notices();
		$dismissed_ids = array();

		foreach ( $notices as $id => $notice ) {
			if ( ! empty( $notice['dismissed'] ) ) {
				continue;
			}
			$is_snoozed = ! empty( $notice['snoozed_until'] ) && ( time() < (int) $notice['snoozed_until'] );
			if ( $is_snoozed ) {
				continue;
			}

			$is_core  = ! empty( $notice['is_core'] ) || ( isset( $notice['source_slug'] ) && 'wordpress-core' === $notice['source_slug'] );
			$priority = $notice['priority'] ?? 'other';
			$should_dismiss = false;

			if ( 'wordpress' === $tab ) {
				if ( $is_core ) {
					$should_dismiss = true;
				}
			} elseif ( 'high' === $tab ) {
				if ( ! $is_core && 'high' === $priority ) {
					$should_dismiss = true;
				}
			} elseif ( 'medium' === $tab ) {
				if ( ! $is_core && 'medium' === $priority ) {
					$should_dismiss = true;
				}
			} elseif ( 'muted' === $tab || 'promos' === $tab ) {
				if ( ! $is_core && in_array( $priority, array( 'low', 'other' ), true ) ) {
					$should_dismiss = true;
				}
			}

			if ( $should_dismiss ) {
				$notices[ $id ]['dismissed'] = true;
				$dismissed_ids[]             = $id;
			}
		}

		if ( ! empty( $dismissed_ids ) ) {
			$this->save_stored_notices( $notices );
			$this->log_audit( 'dismiss_tab', array(
				'tab'     => $tab,
				'count'   => count( $dismissed_ids ),
				'summary' => sprintf( __( 'Dismissed %d notice(s) from "%s" tab', 'wp-quiet-admin' ), count( $dismissed_ids ), ucfirst( $tab ) ),
			) );
		}

		wp_send_json_success( array(
			'message'       => __( 'All notices in this tab dismissed.', 'wp-quiet-admin' ),
			'counts'        => $this->get_notice_counts(),
			'dismissed_ids' => $dismissed_ids,
			'tab'           => $tab,
		) );
	}

	/**
	 * AJAX Handler: Undo notice dismissal (supports single notice or batch IDs).
	 */
	public function ajax_undo_dismiss() {
		check_ajax_referer( 'wp_quiet_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$notices = $this->get_stored_notices();
		$updated = false;

		// Batch restore
		if ( ! empty( $_POST['notice_ids'] ) && is_array( $_POST['notice_ids'] ) ) {
			$ids = array_map( 'sanitize_key', $_POST['notice_ids'] );
			foreach ( $ids as $nid ) {
				if ( isset( $notices[ $nid ] ) ) {
					$notices[ $nid ]['dismissed'] = false;
					$updated = true;
				}
			}

			if ( $updated ) {
				$this->save_stored_notices( $notices );
				$this->log_audit( 'restore', array(
					'count'   => count( $ids ),
					'summary' => sprintf( __( 'Restored %d notice(s) from archive', 'wp-quiet-admin' ), count( $ids ) ),
				) );
				wp_send_json_success( array(
					'message' => __( 'Notices restored.', 'wp-quiet-admin' ),
					'counts'  => $this->get_notice_counts(),
				) );
			}
		}

		// Single notice restore
		$notice_id = sanitize_key( $_POST['notice_id'] ?? '' );
		if ( ! empty( $notice_id ) && isset( $notices[ $notice_id ] ) ) {
			$notices[ $notice_id ]['dismissed'] = false;
			$this->save_stored_notices( $notices );

			$source_name = ! empty( $notices[ $notice_id ]['source_name'] ) ? $notices[ $notice_id ]['source_name'] : ( $notices[ $notice_id ]['source_slug'] ?? 'Notice' );
			$this->log_audit( 'restore', array(
				'notice_id' => $notice_id,
				'summary'   => sprintf( __( 'Restored notice from %s from archive', 'wp-quiet-admin' ), $source_name ),
			) );

			wp_send_json_success( array(
				'message' => __( 'Notice restored.', 'wp-quiet-admin' ),
				'counts'  => $this->get_notice_counts(),
				'notice'  => $notices[ $notice_id ],
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Notice not found in storage.', 'wp-quiet-admin' ) ), 404 );
	}

	/**
	 * AJAX Handler: Restore a notice from dismissed history.
	 */
	public function ajax_restore_notice() {
		$this->ajax_undo_dismiss();
	}

	/**
	 * AJAX Handler: Purge dismissed history archive.
	 */
	public function ajax_purge_history() {
		check_ajax_referer( 'wp_quiet_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		if ( ! $this->settings->current_user_can_purge() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied. Purging notices is restricted to primary administrators.', 'wp-quiet-admin' ) ), 403 );
		}

		$notices      = $this->get_stored_notices();
		$purged_count = 0;
		foreach ( $notices as $id => $notice ) {
			if ( ! empty( $notice['dismissed'] ) ) {
				unset( $notices[ $id ] );
				$purged_count++;
			}
		}
		$this->save_stored_notices( $notices );

		$this->log_audit( 'purge_archive', array(
			'count'   => $purged_count,
			'summary' => sprintf( __( 'Permanently purged %d dismissed notice(s) from Archive', 'wp-quiet-admin' ), $purged_count ),
		) );

		wp_send_json_success( array(
			'message' => __( 'Dismissed archive cleared.', 'wp-quiet-admin' ),
			'counts'  => $this->get_notice_counts(),
		) );
	}

	/**
	 * AJAX Handler: Clear all active notices by safely moving them to the archive.
	 */
	public function ajax_clear_all_notices() {
		check_ajax_referer( 'wp_quiet_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$notices   = $this->get_stored_notices();
		$dismissed = 0;

		foreach ( $notices as $id => $notice ) {
			if ( empty( $notice['dismissed'] ) ) {
				$notices[ $id ]['dismissed']     = true;
				$notices[ $id ]['snoozed_until'] = 0;
				$dismissed++;
			}
		}

		if ( $dismissed > 0 ) {
			$this->save_stored_notices( $notices );
		}

		$this->log_audit( 'clear_all', array(
			'count'   => $dismissed,
			'summary' => sprintf( __( 'Cleared %d active notice(s) and moved them to Archive', 'wp-quiet-admin' ), $dismissed ),
		) );

		wp_send_json_success( array(
			'message' => sprintf( __( '%d notice(s) cleared and moved to Archive.', 'wp-quiet-admin' ), $dismissed ),
			'counts'  => $this->get_notice_counts(),
		) );
	}

	/**
	 * AJAX Handler: Whitelist or Blacklist a plugin directly from notice card.
	 */
	public function ajax_toggle_plugin() {
		check_ajax_referer( 'wp_quiet_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$slug   = sanitize_text_field( $_POST['plugin_slug'] ?? '' );
		$action = sanitize_key( $_POST['plugin_action'] ?? '' );

		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'Missing plugin identifier' ), 400 );
		}

		if ( 'whitelist' === $action || 'passthrough' === $action ) {
			$this->settings->add_to_whitelist( $slug );
			$msg = sprintf( __( 'Added %s to Show on Admin Screens.', 'wp-quiet-admin' ), $slug );
			$this->log_audit( 'rule_change', array(
				'slug'    => $slug,
				'rule'    => 'show_in_admin',
				'summary' => sprintf( __( 'Enabled "Show in Admin" for %s', 'wp-quiet-admin' ), $slug ),
			) );
		} elseif ( 'blacklist' === $action ) {
			$this->settings->add_to_blacklist( $slug );
			$notices = $this->get_stored_notices();
			foreach ( $notices as $id => $notice ) {
				if ( isset( $notice['source_slug'] ) && $notice['source_slug'] === $slug ) {
					unset( $notices[ $id ] );
				}
			}
			$this->save_stored_notices( $notices );
			$msg = sprintf( __( 'Added %s to Blacklist. Notices will be permanently muted.', 'wp-quiet-admin' ), $slug );
			$this->log_audit( 'rule_change', array(
				'slug'    => $slug,
				'rule'    => 'mute_everywhere',
				'summary' => sprintf( __( 'Enabled "Mute Everywhere" for %s', 'wp-quiet-admin' ), $slug ),
			) );
		} else {
			$this->settings->reset_plugin_status( $slug );
			$msg = sprintf( __( 'Reset filters for %s.', 'wp-quiet-admin' ), $slug );
			$this->log_audit( 'rule_change', array(
				'slug'    => $slug,
				'rule'    => 'default_hub',
				'summary' => sprintf( __( 'Reset rule to Hub (Default) for %s', 'wp-quiet-admin' ), $slug ),
			) );
		}

		wp_send_json_success( array(
			'message' => $msg,
			'slug'    => $slug,
			'action'  => $action,
			'counts'  => $this->get_notice_counts(),
		) );
	}

	/**
	 * AJAX Handler: Snooze a notice.
	 */
	public function ajax_snooze_notice() {
		check_ajax_referer( 'wp_quiet_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$notice_id = sanitize_key( $_POST['notice_id'] ?? '' );
		$duration  = sanitize_text_field( $_POST['duration'] ?? '1_day' );
		$custom    = sanitize_text_field( $_POST['custom_date'] ?? '' );

		if ( empty( $notice_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid Notice ID' ), 400 );
		}

		$until_timestamp = time() + DAY_IN_SECONDS;
		$until_label     = __( 'Tomorrow', 'wp-quiet-admin' );

		if ( '1_week' === $duration ) {
			$until_timestamp = time() + ( 7 * DAY_IN_SECONDS );
			$until_label     = __( '1 Week', 'wp-quiet-admin' );
		} elseif ( '1_month' === $duration ) {
			$until_timestamp = time() + ( 30 * DAY_IN_SECONDS );
			$until_label     = __( '1 Month', 'wp-quiet-admin' );
		} elseif ( 'custom' === $duration && ! empty( $custom ) ) {
			$parsed = strtotime( $custom . ' 23:59:59' );
			if ( $parsed && $parsed > time() ) {
				$until_timestamp = $parsed;
				$until_label     = date_i18n( get_option( 'date_format' ), $parsed );
			}
		}

		$notices = $this->get_stored_notices();
		if ( isset( $notices[ $notice_id ] ) ) {
			$notices[ $notice_id ]['snoozed_until'] = $until_timestamp;
			$notices[ $notice_id ]['dismissed']     = false;
			$this->save_stored_notices( $notices );

			$source_name = ! empty( $notices[ $notice_id ]['source_name'] ) ? $notices[ $notice_id ]['source_name'] : ( $notices[ $notice_id ]['source_slug'] ?? 'Notice' );
			$this->log_audit( 'snooze', array(
				'notice_id'   => $notice_id,
				'duration'    => $duration,
				'until_label' => $until_label,
				'summary'     => sprintf( __( 'Snoozed notice from %s until %s', 'wp-quiet-admin' ), $source_name, $until_label ),
			) );

			wp_send_json_success( array(
				'message'       => sprintf( __( 'Notice snoozed until %s.', 'wp-quiet-admin' ), $until_label ),
				'until_label'   => $until_label,
				'snoozed_until' => $until_timestamp,
				'counts'        => $this->get_notice_counts(),
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Notice not found in storage.', 'wp-quiet-admin' ) ), 404 );
	}

	/**
	 * AJAX Handler: Unsnooze a notice and return to active view.
	 */
	public function ajax_unsnooze_notice() {
		check_ajax_referer( 'wp_quiet_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$notice_id = sanitize_key( $_POST['notice_id'] ?? '' );
		if ( empty( $notice_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid Notice ID' ), 400 );
		}

		$notices = $this->get_stored_notices();
		if ( isset( $notices[ $notice_id ] ) ) {
			$notices[ $notice_id ]['snoozed_until'] = 0;
			$notices[ $notice_id ]['dismissed']     = false;
			$this->save_stored_notices( $notices );

			$source_name = ! empty( $notices[ $notice_id ]['source_name'] ) ? $notices[ $notice_id ]['source_name'] : ( $notices[ $notice_id ]['source_slug'] ?? 'Notice' );
			$this->log_audit( 'unsnooze', array(
				'notice_id' => $notice_id,
				'summary'   => sprintf( __( 'Woke up snoozed notice from %s', 'wp-quiet-admin' ), $source_name ),
			) );

			wp_send_json_success( array(
				'message' => __( 'Notice restored to active view.', 'wp-quiet-admin' ),
				'counts'  => $this->get_notice_counts(),
				'notice'  => $notices[ $notice_id ],
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Notice not found in storage.', 'wp-quiet-admin' ) ), 404 );
	}

	/**
	 * AJAX Handler: Bulk dismiss selected notices.
	 */
	public function ajax_bulk_dismiss() {
		check_ajax_referer( 'wp_quiet_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$ids = isset( $_POST['notice_ids'] ) && is_array( $_POST['notice_ids'] ) 
			? array_map( 'sanitize_key', $_POST['notice_ids'] ) 
			: array();

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => 'No notices selected' ), 400 );
		}

		$notices       = $this->get_stored_notices();
		$dismissed_ids = array();

		foreach ( $ids as $id ) {
			if ( isset( $notices[ $id ] ) ) {
				$notices[ $id ]['dismissed']     = true;
				$notices[ $id ]['snoozed_until'] = 0;
				$dismissed_ids[]                 = $id;
			}
		}

		if ( ! empty( $dismissed_ids ) ) {
			$this->save_stored_notices( $notices );
			$this->log_audit( 'bulk_dismiss', array(
				'count'   => count( $dismissed_ids ),
				'summary' => sprintf( __( 'Bulk dismissed %d notice(s)', 'wp-quiet-admin' ), count( $dismissed_ids ) ),
			) );
		}

		wp_send_json_success( array(
			'message'       => sprintf( __( '%d notice(s) dismissed.', 'wp-quiet-admin' ), count( $dismissed_ids ) ),
			'counts'        => $this->get_notice_counts(),
			'dismissed_ids' => $dismissed_ids,
		) );
	}

	/**
	 * AJAX Handler: Bulk snooze selected notices.
	 */
	public function ajax_bulk_snooze() {
		check_ajax_referer( 'wp_quiet_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$ids = isset( $_POST['notice_ids'] ) && is_array( $_POST['notice_ids'] ) 
			? array_map( 'sanitize_key', $_POST['notice_ids'] ) 
			: array();

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => 'No notices selected' ), 400 );
		}

		$duration = sanitize_text_field( $_POST['duration'] ?? '1_day' );
		$custom   = sanitize_text_field( $_POST['custom_date'] ?? '' );

		$until_timestamp = time() + DAY_IN_SECONDS;
		$until_label     = __( 'Tomorrow', 'wp-quiet-admin' );

		if ( '1_week' === $duration ) {
			$until_timestamp = time() + ( 7 * DAY_IN_SECONDS );
			$until_label     = __( '1 Week', 'wp-quiet-admin' );
		} elseif ( '1_month' === $duration ) {
			$until_timestamp = time() + ( 30 * DAY_IN_SECONDS );
			$until_label     = __( '1 Month', 'wp-quiet-admin' );
		} elseif ( 'custom' === $duration && ! empty( $custom ) ) {
			$parsed = strtotime( $custom . ' 23:59:59' );
			if ( $parsed && $parsed > time() ) {
				$until_timestamp = $parsed;
				$until_label     = date_i18n( get_option( 'date_format' ), $parsed );
			}
		}

		$notices     = $this->get_stored_notices();
		$snoozed_ids = array();

		foreach ( $ids as $id ) {
			if ( isset( $notices[ $id ] ) ) {
				$notices[ $id ]['snoozed_until'] = $until_timestamp;
				$notices[ $id ]['dismissed']     = false;
				$snoozed_ids[]                   = $id;
			}
		}

		if ( ! empty( $snoozed_ids ) ) {
			$this->save_stored_notices( $notices );
			$this->log_audit( 'bulk_snooze', array(
				'count'       => count( $snoozed_ids ),
				'duration'    => $duration,
				'until_label' => $until_label,
				'summary'     => sprintf( __( 'Bulk snoozed %d notice(s) until %s', 'wp-quiet-admin' ), count( $snoozed_ids ), $until_label ),
			) );
		}

		wp_send_json_success( array(
			'message'       => sprintf( __( '%d notice(s) snoozed until %s.', 'wp-quiet-admin' ), count( $snoozed_ids ), $until_label ),
			'until_label'   => $until_label,
			'snoozed_until' => $until_timestamp,
			'counts'        => $this->get_notice_counts(),
			'snoozed_ids'   => $snoozed_ids,
		) );
	}

	/**
	 * AJAX Handler: Wake up all snoozed notices immediately.
	 */
	public function ajax_wake_all() {
		check_ajax_referer( 'wp_quiet_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$notices   = $this->get_stored_notices();
		$woken_ids = array();

		foreach ( $notices as $id => $notice ) {
			if ( ! empty( $notice['snoozed_until'] ) ) {
				$notices[ $id ]['snoozed_until'] = 0;
				$woken_ids[]                     = $id;
			}
		}

		if ( ! empty( $woken_ids ) ) {
			$this->save_stored_notices( $notices );
			$this->log_audit( 'wake_all', array(
				'count'   => count( $woken_ids ),
				'summary' => sprintf( __( 'Woke up all %d snoozed notice(s)', 'wp-quiet-admin' ), count( $woken_ids ) ),
			) );
		}

		wp_send_json_success( array(
			'message'   => sprintf( __( '%d snoozed notice(s) awakened.', 'wp-quiet-admin' ), count( $woken_ids ) ),
			'counts'    => $this->get_notice_counts(),
			'woken_ids' => $woken_ids,
		) );
	}
}
