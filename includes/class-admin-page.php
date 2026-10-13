<?php
/**
 * WP Quiet Admin Hub Page
 *
 * Dedicated Admin Page grouping notices into tabs with badge counters:
 * - Red: High Priority (Critical)
 * - Yellow: Medium Priority (Warnings & Updates)
 * - Low: Low Priority (Reviews & Upsells)
 * - Other: Unclassified & General
 * - Settings: Whitelist, Blacklist & Pass-through rules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Quiet_Admin_Admin_Page', false ) ) {
class WP_Quiet_Admin_Admin_Page {

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

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_save_settings' ) );
	}

	/**
	 * Register the menu item under Dashboard.
	 */
	public function register_admin_menu() {
		$counts     = $this->notice_manager->get_notice_counts();
		$menu_title = __( 'Quiet Admin', 'wp-quiet-admin' );

		if ( $counts['total'] > 0 ) {
			$low_and_other = $counts['low'] + $counts['other'];
			$pills_html    = '';
			if ( $counts['high'] > 0 ) {
				$pills_html .= '<span class="qa-side-pill pill-red" style="background-color:#d63638;color:#ffffff;display:inline-block;padding:0 6px;border-radius:9px;font-size:11px;font-weight:600;line-height:18px;height:18px;min-width:18px;text-align:center;box-sizing:border-box;" title="' . esc_attr__( 'Critical', 'wp-quiet-admin' ) . '">' . absint( $counts['high'] ) . '</span>';
			}
			if ( $counts['medium'] > 0 ) {
				$pills_html .= '<span class="qa-side-pill pill-yellow" style="background-color:#dba617;color:#1d2327;display:inline-block;padding:0 6px;border-radius:9px;font-size:11px;font-weight:600;line-height:18px;height:18px;min-width:18px;text-align:center;box-sizing:border-box;" title="' . esc_attr__( 'Warnings & Updates', 'wp-quiet-admin' ) . '">' . absint( $counts['medium'] ) . '</span>';
			}
			if ( $low_and_other > 0 ) {
				$pills_html .= '<span class="qa-side-pill pill-green" style="background-color:#00a32a;color:#ffffff;display:inline-block;padding:0 6px;border-radius:9px;font-size:11px;font-weight:600;line-height:18px;height:18px;min-width:18px;text-align:center;box-sizing:border-box;" title="' . esc_attr__( 'Reviews & Promos', 'wp-quiet-admin' ) . '">' . absint( $low_and_other ) . '</span>';
			}
			$menu_title .= ' <span class="qa-sidebar-badge-group" style="display:inline-flex;gap:4px;align-items:center;margin-left:6px;vertical-align:middle;">' . $pills_html . '</span>';
		}

		add_dashboard_page(
			__( 'WP Quiet Admin Hub', 'wp-quiet-admin' ),
			$menu_title,
			'manage_options',
			'wp-quiet-admin',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle settings POST save.
	 */
	public function handle_save_settings() {
		// 1. Export settings to JSON
		if ( isset( $_POST['wp_quiet_admin_export_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_quiet_admin_export_nonce'] ) ), 'wp_quiet_admin_export_settings' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wp-quiet-admin' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'wp-quiet-admin' ) );
			}

			$export_data = array(
				'generator'   => 'WP Quiet Admin',
				'version'     => defined( 'WP_QUIET_ADMIN_VERSION' ) ? WP_QUIET_ADMIN_VERSION : '1.0.10',
				'exported_at' => current_time( 'mysql' ),
				'site_url'    => get_site_url(),
				'settings'    => $this->settings->get_all(),
			);

			$filename = 'wp-quiet-admin-settings-' . gmdate( 'Y-m-d' ) . '.json';
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Expires: 0' );
			header( 'Pragma: no-cache' );
			echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			exit;
		}

		// 2. Import settings from JSON
		if ( isset( $_POST['wp_quiet_admin_import_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_quiet_admin_import_nonce'] ) ), 'wp_quiet_admin_import_settings' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wp-quiet-admin' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'wp-quiet-admin' ) );
			}

			if ( empty( $_FILES['import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'wp-quiet-admin', 'tab' => 'settings', 'qa_error' => 'no_file' ), admin_url( 'index.php' ) ) );
				exit;
			}

			$raw_content = file_get_contents( $_FILES['import_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$decoded     = json_decode( $raw_content, true );

			if ( ! is_array( $decoded ) ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'wp-quiet-admin', 'tab' => 'settings', 'qa_error' => 'invalid_json' ), admin_url( 'index.php' ) ) );
				exit;
			}

			$settings_payload = isset( $decoded['settings'] ) && is_array( $decoded['settings'] ) ? $decoded['settings'] : $decoded;

			// Validate presence of at least one known setting key
			$valid_keys = array( 'passthrough_high', 'passthrough_core', 'show_admin_bar', 'whitelisted_plugins', 'blacklisted_plugins', 'retention_days' );
			$has_valid  = false;
			foreach ( $valid_keys as $vk ) {
				if ( isset( $settings_payload[ $vk ] ) ) {
					$has_valid = true;
					break;
				}
			}

			if ( ! $has_valid ) {
				wp_safe_redirect( add_query_arg( array( 'page' => 'wp-quiet-admin', 'tab' => 'settings', 'qa_error' => 'invalid_schema' ), admin_url( 'index.php' ) ) );
				exit;
			}

			$current = $this->settings->get_all();
			$merged  = wp_parse_args( $settings_payload, $current );
			$this->settings->update_all( $merged );

			$whitelist_count = count( (array) ( $merged['whitelisted_plugins'] ?? array() ) );
			$blacklist_count = count( (array) ( $merged['blacklisted_plugins'] ?? array() ) );

			$this->notice_manager->log_audit( 'import_settings', array(
				'summary' => sprintf( __( 'Imported settings: %d Show in Admin rules, %d Mute Everywhere rules.', 'wp-quiet-admin' ), $whitelist_count, $blacklist_count ),
			) );

			wp_safe_redirect( add_query_arg( array( 'page' => 'wp-quiet-admin', 'tab' => 'settings', 'imported' => '1' ), admin_url( 'index.php' ) ) );
			exit;
		}

		// 3. Clear audit trail
		if ( isset( $_POST['wp_quiet_admin_clear_audit_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_quiet_admin_clear_audit_nonce'] ) ), 'wp_quiet_admin_clear_audit_action' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wp-quiet-admin' ) );
			}
			if ( ! $this->settings->current_user_can_purge() ) {
				wp_die( esc_html__( 'Unauthorized. Clearing audit trail requires chief administrator privileges.', 'wp-quiet-admin' ) );
			}

			$this->notice_manager->clear_audit_log();

			wp_safe_redirect( add_query_arg( array( 'page' => 'wp-quiet-admin', 'tab' => 'settings', 'audit_cleared' => '1' ), admin_url( 'index.php' ) ) );
			exit;
		}

		// 4. Standard settings form save
		if ( ! isset( $_POST['wp_quiet_admin_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_quiet_admin_settings_nonce'] ) ), 'wp_quiet_admin_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-quiet-admin' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'wp-quiet-admin' ) );
		}

		$whitelisted = array();
		$blacklisted = array();

		if ( ! empty( $_POST['plugin_rules'] ) && is_array( $_POST['plugin_rules'] ) ) {
			foreach ( $_POST['plugin_rules'] as $slug => $rule ) {
				$slug = sanitize_text_field( $slug );
				if ( 'whitelist' === $rule ) {
					$whitelisted[] = $slug;
				} elseif ( 'blacklist' === $rule ) {
					$blacklisted[] = $slug;
				}
			}
		}

		$new_settings = array(
			'passthrough_high'           => ! empty( $_POST['passthrough_high'] ),
			'passthrough_core'           => ! empty( $_POST['passthrough_core'] ),
			'show_admin_bar'             => ! empty( $_POST['show_admin_bar'] ),
			'whitelisted_plugins'        => $whitelisted,
			'blacklisted_plugins'        => $blacklisted,
			'retention_days'             => isset( $_POST['retention_days'] ) ? absint( $_POST['retention_days'] ) : 7,
			'audit_retention_days'       => isset( $_POST['audit_retention_days'] ) ? absint( $_POST['audit_retention_days'] ) : 30,
			'restrict_purge_super_admin' => ! empty( $_POST['restrict_purge_super_admin'] ),
		);

		$this->settings->update_all( $new_settings );

		$this->notice_manager->log_audit( 'update_settings', array(
			'summary' => __( 'Updated Quiet Admin rules and urgency settings.', 'wp-quiet-admin' ),
		) );

		wp_safe_redirect( add_query_arg( array( 'page' => 'wp-quiet-admin', 'tab' => 'settings', 'saved' => '1' ), admin_url( 'index.php' ) ) );
		exit;
	}

	/**
	 * Render the main page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-quiet-admin' ) );
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'wordpress';
		if ( 'low' === $current_tab || 'other' === $current_tab || 'all' === $current_tab || 'promos' === $current_tab ) {
			$current_tab = ( 'all' === $current_tab ) ? 'wordpress' : 'muted';
		}
		$valid_tabs  = array( 'wordpress', 'high', 'medium', 'muted', 'snoozed', 'history', 'settings' );
		if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
			$current_tab = 'wordpress';
		}

		$counts       = $this->notice_manager->get_notice_counts();
		$all_notices  = $this->notice_manager->get_stored_notices();
		$is_saved     = ! empty( $_GET['saved'] );
		$can_purge    = $this->settings->current_user_can_purge();

		// Filter notices by current tab
		$tab_notices = array();
		if ( 'settings' !== $current_tab ) {
			foreach ( $all_notices as $id => $notice ) {
				$is_dismissed = ! empty( $notice['dismissed'] );
				$is_snoozed   = ! empty( $notice['snoozed_until'] ) && ( time() < (int) $notice['snoozed_until'] );
				$is_core      = ! empty( $notice['is_core'] ) || ( isset( $notice['source_slug'] ) && 'wordpress-core' === $notice['source_slug'] );

				// Tab: Snoozed (shows active sleeping notices)
				if ( 'snoozed' === $current_tab ) {
					if ( $is_snoozed ) {
						$tab_notices[ $id ] = $notice;
					}
					continue;
				}

				// Tab: History / Archive (shows dismissed notices only)
				if ( 'history' === $current_tab ) {
					if ( $is_dismissed && ! $is_snoozed ) {
						$tab_notices[ $id ] = $notice;
					}
					continue;
				}

				// All other active priority tabs show non-dismissed and non-snoozed notices
				if ( $is_dismissed || $is_snoozed ) {
					continue;
				}

				if ( 'wordpress' === $current_tab ) {
					if ( $is_core ) {
						$tab_notices[ $id ] = $notice;
					}
				} elseif ( 'muted' === $current_tab ) {
					// Combined Reviews & Promos tab: includes low (upsells/reviews) and other (general info)
					if ( ! $is_core && in_array( $notice['priority'] ?? 'other', array( 'low', 'other' ), true ) ) {
						$tab_notices[ $id ] = $notice;
					}
				} else {
					// Specific priority tabs (high, medium):
					if ( ( $notice['priority'] ?? 'other' ) === $current_tab ) {
						if ( ! $is_core ) {
							$tab_notices[ $id ] = $notice;
						}
					}
				}
			}

			// Sort snoozed notices by wake-up date soonest first; other tabs newest first
			if ( 'snoozed' === $current_tab ) {
				uasort( $tab_notices, function( $a, $b ) {
					return ( $a['snoozed_until'] ?? 0 ) <=> ( $b['snoozed_until'] ?? 0 );
				});
			} else {
				uasort( $tab_notices, function( $a, $b ) {
					return ( $b['timestamp'] ?? 0 ) <=> ( $a['timestamp'] ?? 0 );
				});
			}
		}

		$base_url = admin_url( 'index.php?page=wp-quiet-admin' );
		?>
		<div class="wrap wp-quiet-admin-wrap">
			<!-- Header Banner -->
			<div class="qa-header">
				<div class="qa-header-left">
					<h1>
						<span class="dashicons dashicons-bell"></span>
						<?php esc_html_e( 'WP Quiet Admin', 'wp-quiet-admin' ); ?>
					</h1>
					<p class="qa-subtitle">
						<?php esc_html_e( 'No more banner fatigue. Triage, mute, and organize all plugin notices by priority.', 'wp-quiet-admin' ); ?>
					</p>
				</div>
				<div class="qa-header-right">
					<button type="button" class="button button-secondary qa-btn-clear-all" <?php echo ( $counts['total'] === 0 ) ? 'disabled' : ''; ?> title="<?php esc_attr_e( 'Clear all active notices and move them safely to the archive', 'wp-quiet-admin' ); ?>">
						<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear All Notices', 'wp-quiet-admin' ); ?>
					</button>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $base_url ) ); ?>" class="button <?php echo ( 'settings' === $current_tab ) ? 'button-primary' : 'button-secondary'; ?>">
						<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Rules & Settings', 'wp-quiet-admin' ); ?>
					</a>
				</div>
			</div>

			<?php if ( $is_saved ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved successfully!', 'wp-quiet-admin' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $_GET['imported'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings imported successfully! Whitelist rules and blacklist filters have been updated.', 'wp-quiet-admin' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $_GET['audit_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'User action audit trail cleared successfully.', 'wp-quiet-admin' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $_GET['qa_error'] ) ) : 
				$err = sanitize_key( $_GET['qa_error'] );
				$msg = __( 'An error occurred.', 'wp-quiet-admin' );
				if ( 'no_file' === $err ) {
					$msg = __( 'Please choose a valid JSON file to import.', 'wp-quiet-admin' );
				} elseif ( 'invalid_json' === $err ) {
					$msg = __( 'The uploaded file is not a valid JSON document.', 'wp-quiet-admin' );
				} elseif ( 'invalid_schema' === $err ) {
					$msg = __( 'The JSON document does not contain recognizable WP Quiet Admin settings.', 'wp-quiet-admin' );
				}
			?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $msg ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper qa-nav-tabs">
				<!-- 1. WordPress Core (First Tab) -->
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'wordpress', $base_url ) ); ?>" class="nav-tab qa-tab-wordpress <?php echo ( 'wordpress' === $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-wordpress-alt" style="font-size: 16px; width: 16px; height: 16px; margin-right: 5px; vertical-align: middle;"></span>
					<?php esc_html_e( 'WordPress Core', 'wp-quiet-admin' ); ?>
					<span class="qa-tab-pills-group">
						<?php if ( $counts['wordpress'] === 0 ) : ?>
							<span class="qa-tab-pill pill-zero">0</span>
						<?php else : ?>
							<?php if ( $counts['wp_high'] > 0 ) : ?>
								<span class="qa-tab-pill pill-red" title="<?php printf( esc_attr__( '%d Critical', 'wp-quiet-admin' ), $counts['wp_high'] ); ?>"><?php echo absint( $counts['wp_high'] ); ?></span>
							<?php endif; ?>
							<?php if ( $counts['wp_medium'] > 0 ) : ?>
								<span class="qa-tab-pill pill-yellow" title="<?php printf( esc_attr__( '%d Updates', 'wp-quiet-admin' ), $counts['wp_medium'] ); ?>"><?php echo absint( $counts['wp_medium'] ); ?></span>
							<?php endif; ?>
							<?php $wp_blue = $counts['wp_other'] + $counts['wp_low']; ?>
							<?php if ( $wp_blue > 0 ) : ?>
								<span class="qa-tab-pill pill-blue" title="<?php printf( esc_attr__( '%d Announcements', 'wp-quiet-admin' ), $wp_blue ); ?>"><?php echo absint( $wp_blue ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
					</span>
				</a>

				<!-- 2. High Priority / Red -->
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'high', $base_url ) ); ?>" class="nav-tab qa-tab-high <?php echo ( 'high' === $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<span class="qa-color-indicator dot-red"></span>
					<?php esc_html_e( 'Critical Notices', 'wp-quiet-admin' ); ?>
					<span class="qa-tab-badge badge-high <?php echo ( $counts['high'] > 0 ) ? 'has-count' : ''; ?>"><?php echo absint( $counts['high'] ); ?></span>
				</a>

				<!-- 3. Medium Priority / Yellow -->
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'medium', $base_url ) ); ?>" class="nav-tab qa-tab-medium <?php echo ( 'medium' === $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<span class="qa-color-indicator dot-yellow"></span>
					<?php esc_html_e( 'Warnings & Updates', 'wp-quiet-admin' ); ?>
					<span class="qa-tab-badge badge-medium <?php echo ( $counts['medium'] > 0 ) ? 'has-count' : ''; ?>"><?php echo absint( $counts['medium'] ); ?></span>
				</a>

				<!-- 4. Combined Reviews & Promos / Green -->
				<?php $promos_count = $counts['low'] + $counts['other']; ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'muted', $base_url ) ); ?>" class="nav-tab qa-tab-muted qa-tab-promos <?php echo ( 'muted' === $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<span class="qa-color-indicator dot-green"></span>
					<?php esc_html_e( 'Reviews & Promos', 'wp-quiet-admin' ); ?>
					<span class="qa-tab-badge badge-green <?php echo ( $promos_count > 0 ) ? 'has-count' : ''; ?>" <?php echo ( $promos_count > 0 ) ? 'style="background-color:#00a32a !important;color:#ffffff !important;"' : ''; ?>><?php echo absint( $promos_count ); ?></span>
				</a>

				<!-- 5. Snoozed Tab -->
				<?php $snoozed_count = $counts['snoozed'] ?? 0; ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'snoozed', $base_url ) ); ?>" class="nav-tab qa-tab-snoozed <?php echo ( 'snoozed' === $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-clock" style="font-size: 16px; width: 16px; height: 16px; margin-right: 5px; vertical-align: middle;"></span>
					<?php esc_html_e( 'Snoozed', 'wp-quiet-admin' ); ?>
					<span class="qa-tab-badge badge-snoozed <?php echo ( $snoozed_count > 0 ) ? 'has-count' : ''; ?>"><?php echo absint( $snoozed_count ); ?></span>
				</a>

				<!-- 6. Archive Tab -->
				<?php $archive_total = $counts['dismissed'] ?? 0; ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" class="nav-tab qa-tab-history <?php echo ( 'history' === $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<span class="dashicons dashicons-backup" style="font-size: 16px; width: 16px; height: 16px; margin-right: 5px; vertical-align: middle;"></span>
					<?php esc_html_e( 'Archive', 'wp-quiet-admin' ); ?>
					<span class="qa-tab-badge badge-history <?php echo ( $archive_total > 0 ) ? 'has-count' : ''; ?>"><?php echo absint( $archive_total ); ?></span>
				</a>
			</nav>

			<!-- Floating Contextual Bulk Actions Bar -->
			<div class="qa-bulk-actions-bar" style="display: none;">
				<div class="qa-bulk-bar-inner">
					<div class="qa-bulk-bar-left">
						<label class="qa-bulk-select-all-label">
							<input type="checkbox" class="qa-bulk-select-all-checkbox" />
							<span class="qa-bulk-selected-text">
								<strong class="qa-bulk-count">0</strong> <?php esc_html_e( 'selected', 'wp-quiet-admin' ); ?>
							</span>
						</label>
						<button type="button" class="button-link qa-bulk-deselect-btn"><?php esc_html_e( 'Deselect All', 'wp-quiet-admin' ); ?></button>
					</div>
					<div class="qa-bulk-bar-right">
						<!-- Bulk Snooze Dropdown -->
						<div class="qa-snooze-wrap qa-bulk-snooze-wrap">
							<button type="button" class="button button-secondary qa-btn-bulk-snooze-toggle">
								<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Snooze Selected', 'wp-quiet-admin' ); ?> <span class="dashicons dashicons-arrow-down-alt2 qa-snooze-caret"></span>
							</button>
							<div class="qa-snooze-dropdown qa-bulk-snooze-dropdown">
								<a href="#" class="qa-bulk-snooze-item" data-duration="1_day">
									<span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Tomorrow (+24h)', 'wp-quiet-admin' ); ?>
								</a>
								<a href="#" class="qa-bulk-snooze-item" data-duration="1_week">
									<span class="dashicons dashicons-calendar"></span> <?php esc_html_e( '1 Week (+7d)', 'wp-quiet-admin' ); ?>
								</a>
								<a href="#" class="qa-bulk-snooze-item" data-duration="1_month">
									<span class="dashicons dashicons-backup"></span> <?php esc_html_e( '1 Month (+30d)', 'wp-quiet-admin' ); ?>
								</a>
								<div class="qa-snooze-divider"></div>
								<div class="qa-snooze-custom-panel">
									<span class="qa-snooze-custom-label"><?php esc_html_e( 'Pick a date:', 'wp-quiet-admin' ); ?></span>
									<div class="qa-snooze-custom-row">
										<input type="date" class="qa-bulk-snooze-date-picker" min="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>" />
										<button type="button" class="button button-small button-primary qa-btn-bulk-snooze-custom"><?php esc_html_e( 'Set', 'wp-quiet-admin' ); ?></button>
									</div>
								</div>
							</div>
						</div>

						<!-- Bulk Dismiss Button -->
						<button type="button" class="button button-primary qa-btn-bulk-dismiss">
							<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Dismiss Selected', 'wp-quiet-admin' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Tab Contents -->
			<div class="qa-tab-content-area">
				<?php
				if ( 'settings' === $current_tab ) {
					$this->render_settings_tab();
				} elseif ( 'wordpress' === $current_tab ) {
					$this->render_wordpress_tab( $tab_notices, $counts );
				} elseif ( 'muted' === $current_tab ) {
					$this->render_muted_tab( $tab_notices, $counts );
				} elseif ( 'snoozed' === $current_tab ) {
					$this->render_snoozed_tab( $tab_notices, $counts );
				} elseif ( 'history' === $current_tab ) {
					$this->render_history_tab( $tab_notices, $counts );
				} else {
					$this->render_notices_tab( $current_tab, $tab_notices, $counts );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Notice Cards for active priority tab.
	 *
	 * @param string $tab
	 * @param array  $notices
	 * @param array  $counts
	 */
	protected function render_notices_tab( $tab, $notices, $counts = array() ) {
		$tab_labels = array(
			'high'   => array( 'title' => __( 'Critical Notices (Red)', 'wp-quiet-admin' ), 'desc' => __( 'Fatal errors, security alerts, database issues, or broken dependencies.', 'wp-quiet-admin' ) ),
			'medium' => array( 'title' => __( 'Warnings & Updates (Yellow)', 'wp-quiet-admin' ), 'desc' => __( 'Plugin/theme updates, recommended backups, deprecated calls, and configuration warnings.', 'wp-quiet-admin' ) ),
			'low'    => array( 'title' => __( 'Promotions, Upsells & Reviews (Low)', 'wp-quiet-admin' ), 'desc' => __( '"Rate us 5 stars", "Upgrade to Pro", Black Friday deals, newsletters, and feedback surveys.', 'wp-quiet-admin' ) ),
			'other'  => array( 'title' => __( 'Other Notices (Informational)', 'wp-quiet-admin' ), 'desc' => __( 'General status notifications, success confirmations, and uncategorized notices.', 'wp-quiet-admin' ) ),
		);

		$info = $tab_labels[ $tab ] ?? $tab_labels['other'];
		$dismiss_label = ( 'high' === $tab ) 
			? __( 'Dismiss All Critical', 'wp-quiet-admin' ) 
			: __( 'Dismiss All Warnings', 'wp-quiet-admin' );
		?>
		<div class="qa-tab-header-meta">
			<div class="qa-tab-header-flex">
				<div>
					<h2>
						<?php if ( 'high' === $tab ) : ?>
							<span class="qa-color-indicator dot-red" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 6px;"></span>
						<?php elseif ( 'medium' === $tab ) : ?>
							<span class="qa-color-indicator dot-yellow" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 6px;"></span>
						<?php endif; ?>
						<?php echo esc_html( $info['title'] ); ?>
					</h2>
					<p class="description"><?php echo esc_html( $info['desc'] ); ?></p>
				</div>
				<div>
					<button type="button" class="button button-secondary qa-btn-dismiss-tab" data-tab="<?php echo esc_attr( $tab ); ?>" <?php echo empty( $notices ) ? 'disabled' : ''; ?> title="<?php esc_attr_e( 'Dismiss all notices in this tab and send them to the archive', 'wp-quiet-admin' ); ?>">
						<span class="dashicons dashicons-dismiss"></span> <?php echo esc_html( $dismiss_label ); ?>
					</button>
				</div>
			</div>
		</div>

		<?php if ( 'high' === $tab ) : 
			$is_passthrough_on = $this->settings->get( 'passthrough_high', true );
		?>
			<!-- Safety Reassurance Banner for Critical Notices -->
			<div class="qa-safety-banner <?php echo $is_passthrough_on ? 'is-active' : 'is-inactive'; ?>">
				<div class="qa-safety-icon">
					<span class="dashicons <?php echo $is_passthrough_on ? 'dashicons-shield-alt' : 'dashicons-warning'; ?>"></span>
				</div>
				<div class="qa-safety-text">
					<strong>
						<?php echo $is_passthrough_on 
							? esc_html__( 'Safety Protection: Critical Pass-Through is Active', 'wp-quiet-admin' ) 
							: esc_html__( 'Attention: Critical Pass-Through is Disabled', 'wp-quiet-admin' ); ?>
					</strong>
					<p>
						<?php echo $is_passthrough_on 
							? esc_html__( 'All high-priority fatal errors and security alerts are allowed to display directly on standard admin pages so your site operations remain safe. They are also archived and tracked here for your review.', 'wp-quiet-admin' ) 
							: esc_html__( 'Critical alerts are currently silenced on regular admin pages and only visible here in Quiet Admin. We recommend enabling Critical Pass-Through for safety.', 'wp-quiet-admin' ); ?>
					</p>
				</div>
				<div class="qa-safety-action">
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', admin_url( 'index.php?page=wp-quiet-admin' ) ) ); ?>" class="button button-secondary button-small">
						<?php esc_html_e( 'Configure Rules', 'wp-quiet-admin' ); ?>
					</a>
				</div>
			</div>

			<?php if ( ! empty( $counts['wp_high'] ) && $counts['wp_high'] > 0 ) : ?>
				<!-- Callout Banner for WordPress Core Critical Alerts -->
				<div class="qa-wp-critical-callout">
					<div class="qa-wp-callout-icon">
						<span class="dashicons dashicons-wordpress-alt"></span>
					</div>
					<div class="qa-wp-callout-text">
						<strong><?php printf( esc_html__( 'WordPress Core has %d Critical Alert(s)', 'wp-quiet-admin' ), absint( $counts['wp_high'] ) ); ?></strong>
						<p><?php esc_html_e( 'WordPress Core critical notices are organized under the WordPress Core tab with their own dedicated red priority badge.', 'wp-quiet-admin' ); ?></p>
					</div>
					<div class="qa-wp-callout-action">
						<a href="<?php echo esc_url( add_query_arg( 'tab', 'wordpress', admin_url( 'index.php?page=wp-quiet-admin' ) ) ); ?>" class="button button-primary button-small">
							<?php esc_html_e( 'View WordPress Alerts →', 'wp-quiet-admin' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( empty( $notices ) ) : ?>
			<div class="qa-empty-state">
				<div class="qa-empty-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<h3><?php esc_html_e( 'All Quiet Here!', 'wp-quiet-admin' ); ?></h3>
				<p><?php esc_html_e( 'No active notices found in this category. Enjoy the distraction-free dashboard.', 'wp-quiet-admin' ); ?></p>
			</div>
		<?php else : ?>
			<div class="qa-notices-grid">
				<?php foreach ( $notices as $id => $notice ) : ?>
					<div class="qa-notice-card qa-card-<?php echo esc_attr( $notice['priority'] ); ?>" data-notice-id="<?php echo esc_attr( $id ); ?>" data-plugin-slug="<?php echo esc_attr( $notice['source_slug'] ); ?>">
						<!-- Card Top Meta Bar -->
						<div class="qa-card-meta">
							<label class="qa-card-select-label" title="<?php esc_attr_e( 'Select notice for bulk actions', 'wp-quiet-admin' ); ?>">
								<input type="checkbox" class="qa-card-select-checkbox" value="<?php echo esc_attr( $id ); ?>" />
							</label>
							<div class="qa-card-source">
								<span class="dashicons dashicons-admin-plugins"></span>
								<strong><?php echo esc_html( $notice['source_name'] ); ?></strong>
								<?php if ( ! empty( $notice['source_slug'] ) ) : ?>
									<code class="qa-slug-pill"><?php echo esc_html( $notice['source_slug'] ); ?></code>
								<?php endif; ?>
								<?php if ( 'high' === $notice['priority'] && $this->settings->get( 'passthrough_high', true ) ) : ?>
									<span class="qa-live-status-pill" title="<?php esc_attr_e( 'This notice is actively displaying on regular admin screens', 'wp-quiet-admin' ); ?>">
										<span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Live on Admin Screens', 'wp-quiet-admin' ); ?>
									</span>
								<?php endif; ?>
							</div>
							<div class="qa-card-time">
								<span class="dashicons dashicons-clock"></span>
								<?php echo esc_html( human_time_diff( $notice['timestamp'] ?? time(), time() ) . ' ' . __( 'ago', 'wp-quiet-admin' ) ); ?>
							</div>
						</div>

						<!-- Raw Rendered Notice Body -->
						<div class="qa-card-body">
							<div class="qa-raw-notice-wrapper inline">
								<?php echo $this->prepare_notice_html_for_card( $notice['html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>

						<!-- Card Action Footer -->
						<div class="qa-card-footer">
							<div class="qa-footer-left">
								<?php if ( ! empty( $notice['source_slug'] ) && 'wordpress-core' !== $notice['source_slug'] ) : 
									$is_pt = $this->settings->is_whitelisted( $notice['source_slug'] );
								?>
									<button type="button" class="button button-small qa-btn-whitelist <?php echo $is_pt ? 'button-primary is-active' : ''; ?>" title="<?php esc_attr_e( 'Allow notices from this plugin to also appear on regular admin pages', 'wp-quiet-admin' ); ?>">
										<span class="dashicons dashicons-visibility"></span> <?php echo $is_pt ? esc_html__( 'Showing in Admin', 'wp-quiet-admin' ) : esc_html__( 'Show in Admin', 'wp-quiet-admin' ); ?>
									</button>
									<button type="button" class="button button-small qa-btn-blacklist" title="<?php esc_attr_e( 'Mute all notices from this plugin permanently everywhere', 'wp-quiet-admin' ); ?>">
										<span class="dashicons dashicons-hidden"></span> <?php esc_html_e( 'Mute Plugin', 'wp-quiet-admin' ); ?>
									</button>
								<?php endif; ?>
							</div>
							<div class="qa-footer-right">
								<!-- Snooze Button with Interactive Dropdown -->
								<div class="qa-snooze-wrap">
									<button type="button" class="button button-secondary button-small qa-btn-snooze-toggle" title="<?php esc_attr_e( 'Temporarily hide this notice until a later date', 'wp-quiet-admin' ); ?>">
										<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Snooze', 'wp-quiet-admin' ); ?> <span class="dashicons dashicons-arrow-down-alt2 qa-snooze-caret"></span>
									</button>
									<div class="qa-snooze-dropdown">
										<a href="#" class="qa-snooze-item" data-duration="1_day">
											<span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Tomorrow (+24h)', 'wp-quiet-admin' ); ?>
										</a>
										<a href="#" class="qa-snooze-item" data-duration="1_week">
											<span class="dashicons dashicons-calendar"></span> <?php esc_html_e( '1 Week (+7d)', 'wp-quiet-admin' ); ?>
										</a>
										<a href="#" class="qa-snooze-item" data-duration="1_month">
											<span class="dashicons dashicons-backup"></span> <?php esc_html_e( '1 Month (+30d)', 'wp-quiet-admin' ); ?>
										</a>
										<div class="qa-snooze-divider"></div>
										<div class="qa-snooze-custom-panel">
											<span class="qa-snooze-custom-label"><?php esc_html_e( 'Pick a date:', 'wp-quiet-admin' ); ?></span>
											<div class="qa-snooze-custom-row">
												<input type="date" class="qa-snooze-date-picker" min="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>" />
												<button type="button" class="button button-small button-primary qa-btn-snooze-custom"><?php esc_html_e( 'Set', 'wp-quiet-admin' ); ?></button>
											</div>
										</div>
									</div>
								</div>

								<button type="button" class="button button-primary button-small qa-btn-dismiss" title="<?php esc_attr_e( 'Dismiss and move this notice to Archive', 'wp-quiet-admin' ); ?>">
									<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Dismiss Notice', 'wp-quiet-admin' ); ?>
								</button>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif;
	}

	/**
	 * Render WordPress Core tab notices with internal category filters.
	 *
	 * @param array $notices
	 * @param array $counts
	 */
	protected function render_wordpress_tab( $notices, $counts ) {
		$is_core_passthrough = $this->settings->get( 'passthrough_core', true );
		?>
		<div class="qa-tab-header-meta">
			<div class="qa-tab-header-flex">
				<div>
					<h2>
						<span class="dashicons dashicons-wordpress-alt" style="font-size: 24px; width: 24px; height: 24px; vertical-align: middle; color: #2271b1; margin-right: 6px;"></span>
						<?php esc_html_e( 'WordPress Core Notices', 'wp-quiet-admin' ); ?>
					</h2>
					<p class="description">
						<?php esc_html_e( 'Official WordPress system updates, core security advisories, and administrative announcements.', 'wp-quiet-admin' ); ?>
					</p>
				</div>
				<div>
					<button type="button" class="button button-secondary qa-btn-dismiss-tab" data-tab="wordpress" <?php echo empty( $notices ) ? 'disabled' : ''; ?> title="<?php esc_attr_e( 'Dismiss all active WordPress Core notices and send them to the archive', 'wp-quiet-admin' ); ?>">
						<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Dismiss All Core Notices', 'wp-quiet-admin' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Pass-Through Status for WordPress Core -->
		<div class="qa-safety-banner <?php echo $is_core_passthrough ? 'is-active' : 'is-inactive'; ?>">
			<div class="qa-safety-icon">
				<span class="dashicons <?php echo $is_core_passthrough ? 'dashicons-shield-alt' : 'dashicons-warning'; ?>"></span>
			</div>
			<div class="qa-safety-text">
				<strong>
					<?php echo $is_core_passthrough 
						? esc_html__( 'WordPress Core Pass-Through is Active', 'wp-quiet-admin' ) 
						: esc_html__( 'WordPress Core Pass-Through is Disabled', 'wp-quiet-admin' ); ?>
				</strong>
				<p>
					<?php echo $is_core_passthrough 
						? esc_html__( 'WordPress system notifications and core update alerts display on standard admin pages so you never miss essential WordPress notices.', 'wp-quiet-admin' ) 
						: esc_html__( 'WordPress system notifications are muted on regular admin pages and only collected here in Quiet Admin.', 'wp-quiet-admin' ); ?>
				</p>
			</div>
			<div class="qa-safety-action">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', admin_url( 'index.php?page=wp-quiet-admin' ) ) ); ?>" class="button button-secondary button-small">
					<?php esc_html_e( 'Configure Rules', 'wp-quiet-admin' ); ?>
				</a>
			</div>
		</div>

		<!-- Sub-Filter Bar inside WordPress Tab -->
		<div class="qa-wp-subfilter-bar">
			<button type="button" class="qa-wp-filter-btn is-active" data-filter="all">
				<?php esc_html_e( 'All Core Notices', 'wp-quiet-admin' ); ?>
				<span class="qa-filter-count"><?php echo absint( $counts['wordpress'] ); ?></span>
			</button>
			<button type="button" class="qa-wp-filter-btn" data-filter="high">
				<span class="qa-filter-dot dot-red"></span>
				<?php esc_html_e( 'Critical Alerts', 'wp-quiet-admin' ); ?>
				<span class="qa-filter-count"><?php echo absint( $counts['wp_high'] ); ?></span>
			</button>
			<button type="button" class="qa-wp-filter-btn" data-filter="medium">
				<span class="qa-filter-dot dot-yellow"></span>
				<?php esc_html_e( 'Updates & Warnings', 'wp-quiet-admin' ); ?>
				<span class="qa-filter-count"><?php echo absint( $counts['wp_medium'] ); ?></span>
			</button>
			<button type="button" class="qa-wp-filter-btn" data-filter="other">
				<span class="qa-filter-dot dot-blue"></span>
				<?php esc_html_e( 'Announcements & Info', 'wp-quiet-admin' ); ?>
				<span class="qa-filter-count"><?php echo absint( $counts['wp_other'] + $counts['wp_low'] ); ?></span>
			</button>
		</div>

		<?php if ( empty( $notices ) ) : ?>
			<div class="qa-empty-state">
				<div class="qa-empty-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<h3><?php esc_html_e( 'No Core Notices Active', 'wp-quiet-admin' ); ?></h3>
				<p><?php esc_html_e( 'WordPress Core has no active notices or alerts at this time.', 'wp-quiet-admin' ); ?></p>
			</div>
		<?php else : ?>
			<div class="qa-notices-grid">
				<?php foreach ( $notices as $id => $notice ) : 
					$notice_priority = $notice['priority'] ?? 'other';
					$filter_group    = ( 'low' === $notice_priority ) ? 'other' : $notice_priority;
				?>
					<div class="qa-notice-card qa-card-<?php echo esc_attr( $filter_group ); ?>" data-notice-id="<?php echo esc_attr( $id ); ?>" data-plugin-slug="wordpress-core">
						<!-- Card Top Meta Bar -->
						<div class="qa-card-meta">
							<label class="qa-card-select-label" title="<?php esc_attr_e( 'Select notice for bulk actions', 'wp-quiet-admin' ); ?>">
								<input type="checkbox" class="qa-card-select-checkbox" value="<?php echo esc_attr( $id ); ?>" />
							</label>
							<div class="qa-card-source">
								<span class="dashicons dashicons-wordpress-alt" style="color: #2271b1; font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span>
								<strong><?php esc_html_e( 'WordPress Core', 'wp-quiet-admin' ); ?></strong>
								<code class="qa-slug-pill">wordpress-core</code>
								<?php if ( 'high' === $notice_priority ) : ?>
									<span class="qa-priority-pill pill-red"><?php esc_html_e( 'Critical Alert', 'wp-quiet-admin' ); ?></span>
								<?php elseif ( 'medium' === $notice_priority ) : ?>
									<span class="qa-priority-pill pill-yellow"><?php esc_html_e( 'Core Update', 'wp-quiet-admin' ); ?></span>
								<?php else : ?>
									<span class="qa-priority-pill pill-blue"><?php esc_html_e( 'Announcement', 'wp-quiet-admin' ); ?></span>
								<?php endif; ?>
								<?php if ( $is_core_passthrough ) : ?>
									<span class="qa-live-status-pill" title="<?php esc_attr_e( 'This notice is actively displaying on regular admin screens', 'wp-quiet-admin' ); ?>">
										<span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Live on Admin Screens', 'wp-quiet-admin' ); ?>
									</span>
								<?php endif; ?>
							</div>
							<div class="qa-card-time">
								<span class="dashicons dashicons-clock"></span>
								<?php echo esc_html( human_time_diff( $notice['timestamp'] ?? time(), time() ) . ' ' . __( 'ago', 'wp-quiet-admin' ) ); ?>
							</div>
						</div>

						<!-- Raw Rendered Notice Body -->
						<div class="qa-card-body">
							<div class="qa-raw-notice-wrapper inline">
								<?php echo $this->prepare_notice_html_for_card( $notice['html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>

						<!-- Card Action Footer -->
						<div class="qa-card-footer">
							<div class="qa-footer-left"></div>
							<div class="qa-footer-right">
								<!-- Snooze Button with Interactive Dropdown -->
								<div class="qa-snooze-wrap">
									<button type="button" class="button button-secondary button-small qa-btn-snooze-toggle" title="<?php esc_attr_e( 'Temporarily hide this notice until a later date', 'wp-quiet-admin' ); ?>">
										<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Snooze', 'wp-quiet-admin' ); ?> <span class="dashicons dashicons-arrow-down-alt2 qa-snooze-caret"></span>
									</button>
									<div class="qa-snooze-dropdown">
										<a href="#" class="qa-snooze-item" data-duration="1_day">
											<span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Tomorrow (+24h)', 'wp-quiet-admin' ); ?>
										</a>
										<a href="#" class="qa-snooze-item" data-duration="1_week">
											<span class="dashicons dashicons-calendar"></span> <?php esc_html_e( '1 Week (+7d)', 'wp-quiet-admin' ); ?>
										</a>
										<a href="#" class="qa-snooze-item" data-duration="1_month">
											<span class="dashicons dashicons-backup"></span> <?php esc_html_e( '1 Month (+30d)', 'wp-quiet-admin' ); ?>
										</a>
										<div class="qa-snooze-divider"></div>
										<div class="qa-snooze-custom-panel">
											<span class="qa-snooze-custom-label"><?php esc_html_e( 'Pick a date:', 'wp-quiet-admin' ); ?></span>
											<div class="qa-snooze-custom-row">
												<input type="date" class="qa-snooze-date-picker" min="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>" />
												<button type="button" class="button button-small button-primary qa-btn-snooze-custom"><?php esc_html_e( 'Set', 'wp-quiet-admin' ); ?></button>
											</div>
										</div>
									</div>
								</div>

								<button type="button" class="button button-primary button-small qa-btn-dismiss" title="<?php esc_attr_e( 'Dismiss and remove this notice', 'wp-quiet-admin' ); ?>">
									<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Dismiss Notice', 'wp-quiet-admin' ); ?>
								</button>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif;
	}

	/**
	 * Render Muted Notices Tab (Combining Promotions, Upsells, Reviews & Informational Notices).
	 *
	 * @param array $notices
	 * @param array $counts
	 */
	protected function render_muted_tab( $notices, $counts ) {
		$muted_count = $counts['low'] + $counts['other'];
		?>
		<div class="qa-tab-header-meta">
			<div class="qa-tab-header-flex">
				<div>
					<h2>
						<span class="qa-color-indicator dot-green" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 6px;"></span>
						<?php esc_html_e( 'Reviews & Promos', 'wp-quiet-admin' ); ?>
					</h2>
					<p class="description">
						<?php esc_html_e( 'Promotions, upsells, rating requests, review prompts, newsletters, and general status notices.', 'wp-quiet-admin' ); ?>
					</p>
				</div>
				<div>
					<button type="button" class="button button-secondary qa-btn-dismiss-tab" data-tab="muted" <?php echo empty( $notices ) ? 'disabled' : ''; ?> title="<?php esc_attr_e( 'Dismiss all promotional and informational notices and send them to the archive', 'wp-quiet-admin' ); ?>">
						<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Dismiss All Reviews & Promos', 'wp-quiet-admin' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Sub-Filter Bar inside Reviews & Promos Tab -->
		<div class="qa-muted-subfilter-bar">
			<button type="button" class="qa-muted-filter-btn is-active" data-filter="all">
				<?php esc_html_e( 'All Reviews & Promos', 'wp-quiet-admin' ); ?>
				<span class="qa-filter-count"><?php echo absint( $muted_count ); ?></span>
			</button>
			<button type="button" class="qa-muted-filter-btn" data-filter="low">
				<span class="qa-filter-dot dot-green"></span>
				<?php esc_html_e( 'Promotions & Upsells', 'wp-quiet-admin' ); ?>
				<span class="qa-filter-count"><?php echo absint( $counts['low'] ); ?></span>
			</button>
			<button type="button" class="qa-muted-filter-btn" data-filter="other">
				<span class="qa-filter-dot dot-green" style="background:#00a32a;opacity:0.75;"></span>
				<?php esc_html_e( 'Informational / Other', 'wp-quiet-admin' ); ?>
				<span class="qa-filter-count"><?php echo absint( $counts['other'] ); ?></span>
			</button>
		</div>

		<?php if ( empty( $notices ) ) : ?>
			<div class="qa-empty-state">
				<div class="qa-empty-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<h3><?php esc_html_e( 'All Quiet Here!', 'wp-quiet-admin' ); ?></h3>
				<p><?php esc_html_e( 'No promotional or routine notices found on your site. Enjoy your distraction-free dashboard.', 'wp-quiet-admin' ); ?></p>
			</div>
		<?php else : ?>
			<div class="qa-notices-grid">
				<?php foreach ( $notices as $id => $notice ) : 
					$priority = $notice['priority'] ?? 'other';
				?>
					<div class="qa-notice-card qa-card-<?php echo esc_attr( $priority ); ?> qa-card-muted" data-notice-id="<?php echo esc_attr( $id ); ?>" data-plugin-slug="<?php echo esc_attr( $notice['source_slug'] ); ?>" data-priority="<?php echo esc_attr( $priority ); ?>">
						<!-- Card Top Meta Bar -->
						<div class="qa-card-meta">
							<label class="qa-card-select-label" title="<?php esc_attr_e( 'Select notice for bulk actions', 'wp-quiet-admin' ); ?>">
								<input type="checkbox" class="qa-card-select-checkbox" value="<?php echo esc_attr( $id ); ?>" />
							</label>
							<div class="qa-card-source">
								<span class="dashicons dashicons-admin-plugins"></span>
								<strong><?php echo esc_html( $notice['source_name'] ); ?></strong>
								<?php if ( ! empty( $notice['source_slug'] ) ) : ?>
									<code class="qa-slug-pill"><?php echo esc_html( $notice['source_slug'] ); ?></code>
								<?php endif; ?>
								<?php if ( 'low' === $priority ) : ?>
									<span class="qa-priority-pill pill-green"><?php esc_html_e( 'Promotion / Upsell', 'wp-quiet-admin' ); ?></span>
								<?php else : ?>
									<span class="qa-priority-pill pill-green-soft"><?php esc_html_e( 'Informational', 'wp-quiet-admin' ); ?></span>
								<?php endif; ?>
							</div>
							<div class="qa-card-time">
								<span class="dashicons dashicons-clock"></span>
								<?php echo esc_html( human_time_diff( $notice['timestamp'] ?? time(), time() ) . ' ' . __( 'ago', 'wp-quiet-admin' ) ); ?>
							</div>
						</div>

						<!-- Raw Rendered Notice Body -->
						<div class="qa-card-body">
							<div class="qa-raw-notice-wrapper inline">
								<?php echo $this->prepare_notice_html_for_card( $notice['html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>

						<!-- Card Action Footer -->
						<div class="qa-card-footer">
							<div class="qa-footer-left">
								<?php if ( ! empty( $notice['source_slug'] ) ) : 
									$is_pt = $this->settings->is_whitelisted( $notice['source_slug'] );
								?>
									<button type="button" class="button button-small qa-btn-whitelist <?php echo $is_pt ? 'button-primary is-active' : ''; ?>" title="<?php esc_attr_e( 'Allow notices from this plugin to also appear on regular admin pages', 'wp-quiet-admin' ); ?>">
										<span class="dashicons dashicons-visibility"></span> <?php echo $is_pt ? esc_html__( 'Showing in Admin', 'wp-quiet-admin' ) : esc_html__( 'Show in Admin', 'wp-quiet-admin' ); ?>
									</button>
									<button type="button" class="button button-small qa-btn-blacklist" title="<?php esc_attr_e( 'Mute all notices from this plugin permanently everywhere', 'wp-quiet-admin' ); ?>">
										<span class="dashicons dashicons-hidden"></span> <?php esc_html_e( 'Mute Plugin', 'wp-quiet-admin' ); ?>
									</button>
								<?php endif; ?>
							</div>
							<div class="qa-footer-right">
								<!-- Snooze Button with Interactive Dropdown -->
								<div class="qa-snooze-wrap">
									<button type="button" class="button button-secondary button-small qa-btn-snooze-toggle" title="<?php esc_attr_e( 'Temporarily hide this notice until a later date', 'wp-quiet-admin' ); ?>">
										<span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Snooze', 'wp-quiet-admin' ); ?> <span class="dashicons dashicons-arrow-down-alt2 qa-snooze-caret"></span>
									</button>
									<div class="qa-snooze-dropdown">
										<a href="#" class="qa-snooze-item" data-duration="1_day">
											<span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Tomorrow (+24h)', 'wp-quiet-admin' ); ?>
										</a>
										<a href="#" class="qa-snooze-item" data-duration="1_week">
											<span class="dashicons dashicons-calendar"></span> <?php esc_html_e( '1 Week (+7d)', 'wp-quiet-admin' ); ?>
										</a>
										<a href="#" class="qa-snooze-item" data-duration="1_month">
											<span class="dashicons dashicons-backup"></span> <?php esc_html_e( '1 Month (+30d)', 'wp-quiet-admin' ); ?>
										</a>
										<div class="qa-snooze-divider"></div>
										<div class="qa-snooze-custom-panel">
											<span class="qa-snooze-custom-label"><?php esc_html_e( 'Pick a date:', 'wp-quiet-admin' ); ?></span>
											<div class="qa-snooze-custom-row">
												<input type="date" class="qa-snooze-date-picker" min="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>" />
												<button type="button" class="button button-small button-primary qa-btn-snooze-custom"><?php esc_html_e( 'Set', 'wp-quiet-admin' ); ?></button>
											</div>
										</div>
									</div>
								</div>

								<button type="button" class="button button-primary button-small qa-btn-dismiss" title="<?php esc_attr_e( 'Dismiss and remove this notice', 'wp-quiet-admin' ); ?>">
									<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Dismiss Notice', 'wp-quiet-admin' ); ?>
								</button>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif;
	}

	/**
	 * Render Dedicated Snoozed Notices Tab.
	 *
	 * @param array $notices
	 * @param array $counts
	 */
	protected function render_snoozed_tab( $notices, $counts ) {
		?>
		<div class="qa-tab-header-meta">
			<div class="qa-tab-header-flex">
				<div>
					<h2>
						<span class="dashicons dashicons-clock" style="font-size: 24px; width: 24px; height: 24px; vertical-align: middle; color: #8250df; margin-right: 6px;"></span>
						<?php esc_html_e( 'Snoozed Notices', 'wp-quiet-admin' ); ?>
					</h2>
					<p class="description">
						<?php esc_html_e( 'Notices temporarily sleeping on a schedule. They will automatically wake up and return to their active priority tabs.', 'wp-quiet-admin' ); ?>
					</p>
				</div>
				<div>
					<button type="button" class="button button-secondary qa-btn-wake-all" <?php echo empty( $notices ) ? 'disabled' : ''; ?> title="<?php esc_attr_e( 'Wake up all snoozed notices immediately and return them to active tabs', 'wp-quiet-admin' ); ?>">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Wake Up All', 'wp-quiet-admin' ); ?>
					</button>
				</div>
			</div>
		</div>

		<?php if ( empty( $notices ) ) : ?>
			<div class="qa-empty-state">
				<div class="qa-empty-icon">
					<span class="dashicons dashicons-clock" style="color: #8250df;"></span>
				</div>
				<h3><?php esc_html_e( 'No Snoozed Notices', 'wp-quiet-admin' ); ?></h3>
				<p><?php esc_html_e( 'You have no notices currently sleeping. Snooze any notice from active tabs to set a reminder.', 'wp-quiet-admin' ); ?></p>
			</div>
		<?php else : ?>
			<div class="qa-notices-grid">
				<?php foreach ( $notices as $id => $notice ) : 
					$priority  = $notice['priority'] ?? 'other';
					$is_core   = ! empty( $notice['is_core'] ) || ( isset( $notice['source_slug'] ) && 'wordpress-core' === $notice['source_slug'] );
					$wake_time = (int) ( $notice['snoozed_until'] ?? 0 );
					$wake_diff = human_time_diff( time(), $wake_time );
				?>
					<div class="qa-notice-card qa-card-snoozed qa-card-<?php echo esc_attr( $priority ); ?>" data-notice-id="<?php echo esc_attr( $id ); ?>" data-plugin-slug="<?php echo esc_attr( $notice['source_slug'] ?? '' ); ?>">
						<!-- Card Top Meta Bar -->
						<div class="qa-card-meta">
							<label class="qa-card-select-label" title="<?php esc_attr_e( 'Select notice for bulk actions', 'wp-quiet-admin' ); ?>">
								<input type="checkbox" class="qa-card-select-checkbox" value="<?php echo esc_attr( $id ); ?>" />
							</label>
							<div class="qa-card-source">
								<?php if ( $is_core ) : ?>
									<span class="dashicons dashicons-wordpress-alt" style="color: #2271b1; font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span>
									<strong><?php esc_html_e( 'WordPress Core', 'wp-quiet-admin' ); ?></strong>
									<code class="qa-slug-pill">wordpress-core</code>
								<?php else : ?>
									<span class="dashicons dashicons-admin-plugins"></span>
									<strong><?php echo esc_html( $notice['source_name'] ); ?></strong>
									<?php if ( ! empty( $notice['source_slug'] ) ) : ?>
										<code class="qa-slug-pill"><?php echo esc_html( $notice['source_slug'] ); ?></code>
									<?php endif; ?>
								<?php endif; ?>

								<span class="qa-snoozed-pill" style="background:#f5f3ff;color:#6b21a8;border:1px solid #ddd6fe;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;margin-left:6px;">
									<span class="dashicons dashicons-clock" style="font-size:13px;width:13px;height:13px;"></span>
									<?php printf( esc_html__( 'Wakes up in %s (%s)', 'wp-quiet-admin' ), esc_html( $wake_diff ), esc_html( date_i18n( get_option( 'date_format' ), $wake_time ) ) ); ?>
								</span>
							</div>
							<div class="qa-card-time">
								<span class="dashicons dashicons-clock"></span>
								<?php echo esc_html( human_time_diff( $notice['timestamp'] ?? time(), time() ) . ' ' . __( 'ago', 'wp-quiet-admin' ) ); ?>
							</div>
						</div>

						<!-- Raw Rendered Notice Body -->
						<div class="qa-card-body">
							<div class="qa-raw-notice-wrapper inline">
								<?php echo $this->prepare_notice_html_for_card( $notice['html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>

						<!-- Card Action Footer -->
						<div class="qa-card-footer">
							<div class="qa-footer-left"></div>
							<div class="qa-footer-right">
								<button type="button" class="button button-primary button-small qa-btn-unsnooze" title="<?php esc_attr_e( 'Wake up this notice immediately and return to active view', 'wp-quiet-admin' ); ?>">
									<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Wake Up Now', 'wp-quiet-admin' ); ?>
								</button>
								<button type="button" class="button button-secondary button-small qa-btn-dismiss" title="<?php esc_attr_e( 'Dismiss and move notice to Archive', 'wp-quiet-admin' ); ?>">
									<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Dismiss Notice', 'wp-quiet-admin' ); ?>
								</button>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif;
	}

	/**
	 * Render Dismissed History Archive Tab.
	 *
	 * @param array $notices
	 * @param array $counts
	 */
	protected function render_history_tab( $notices, $counts ) {
		$archive_counts = array(
			'all'       => count( $notices ),
			'wordpress' => 0,
			'high'      => 0,
			'medium'    => 0,
			'promos'    => 0,
		);

		foreach ( $notices as $notice ) {
			$is_core  = ! empty( $notice['is_core'] ) || ( isset( $notice['source_slug'] ) && 'wordpress-core' === $notice['source_slug'] );
			$priority = $notice['priority'] ?? 'other';

			if ( $is_core ) {
				$archive_counts['wordpress']++;
			}
			if ( 'high' === $priority ) {
				$archive_counts['high']++;
			} elseif ( 'medium' === $priority ) {
				$archive_counts['medium']++;
			} else {
				if ( ! $is_core ) {
					$archive_counts['promos']++;
				}
			}
		}
		?>
		<div class="qa-tab-header-meta">
			<div class="qa-tab-header-flex">
				<div>
					<h2>
						<span class="dashicons dashicons-backup" style="font-size: 24px; width: 24px; height: 24px; vertical-align: middle; color: #646970; margin-right: 6px;"></span>
						<?php esc_html_e( 'Archive', 'wp-quiet-admin' ); ?>
					</h2>
					<p class="description">
						<?php esc_html_e( 'Review dismissed notices or restore them to active tabs. Notices are preserved according to your retention settings.', 'wp-quiet-admin' ); ?>
					</p>
				</div>
				<div>
					<?php $can_purge = $this->settings->current_user_can_purge(); ?>
					<button type="button" class="button button-secondary qa-btn-purge-history" <?php echo ( empty( $notices ) || ! $can_purge ) ? 'disabled' : ''; ?> title="<?php echo ( ! $can_purge ) ? esc_attr__( 'Purging the archive is restricted to primary administrators.', 'wp-quiet-admin' ) : esc_attr__( 'Permanently delete all dismissed notices from the archive', 'wp-quiet-admin' ); ?>">
						<span class="dashicons <?php echo ( ! $can_purge ) ? 'dashicons-lock' : 'dashicons-trash'; ?>"></span> <?php esc_html_e( 'Purge Dismissed Archive', 'wp-quiet-admin' ); ?>
					</button>
				</div>
			</div>
		</div>

		<?php if ( ! empty( $notices ) ) : ?>
			<!-- Sub-Filter Bar inside Archive Tab -->
			<div class="qa-history-subfilter-bar">
				<button type="button" class="qa-history-filter-btn is-active" data-filter="all">
					<?php esc_html_e( 'All Dismissed', 'wp-quiet-admin' ); ?>
					<span class="qa-filter-count"><?php echo absint( $archive_counts['all'] ); ?></span>
				</button>
				<button type="button" class="qa-history-filter-btn" data-filter="wordpress">
					<span class="qa-filter-dot dot-blue"></span>
					<?php esc_html_e( 'WordPress Core', 'wp-quiet-admin' ); ?>
					<span class="qa-filter-count"><?php echo absint( $archive_counts['wordpress'] ); ?></span>
				</button>
				<button type="button" class="qa-history-filter-btn" data-filter="high">
					<span class="qa-filter-dot dot-red"></span>
					<?php esc_html_e( 'Critical Alerts', 'wp-quiet-admin' ); ?>
					<span class="qa-filter-count"><?php echo absint( $archive_counts['high'] ); ?></span>
				</button>
				<button type="button" class="qa-history-filter-btn" data-filter="medium">
					<span class="qa-filter-dot dot-yellow"></span>
					<?php esc_html_e( 'Updates & Warnings', 'wp-quiet-admin' ); ?>
					<span class="qa-filter-count"><?php echo absint( $archive_counts['medium'] ); ?></span>
				</button>
				<button type="button" class="qa-history-filter-btn" data-filter="muted">
					<span class="qa-filter-dot dot-green"></span>
					<?php esc_html_e( 'Reviews & Promos', 'wp-quiet-admin' ); ?>
					<span class="qa-filter-count"><?php echo absint( $archive_counts['promos'] ); ?></span>
				</button>
			</div>
		<?php endif; ?>

		<?php if ( empty( $notices ) ) : ?>
			<div class="qa-empty-state">
				<div class="qa-empty-icon">
					<span class="dashicons dashicons-backup"></span>
				</div>
				<h3><?php esc_html_e( 'No Dismissed Notices in Archive', 'wp-quiet-admin' ); ?></h3>
				<p><?php esc_html_e( 'When you dismiss a notice, it is preserved here so you can review or restore it at any time.', 'wp-quiet-admin' ); ?></p>
			</div>
		<?php else : ?>
			<div class="qa-notices-grid">
				<?php foreach ( $notices as $id => $notice ) : 
					$priority = $notice['priority'] ?? 'other';
					$is_core  = ! empty( $notice['is_core'] ) || ( isset( $notice['source_slug'] ) && 'wordpress-core' === $notice['source_slug'] );
					$group    = 'muted';
					if ( 'high' === $priority ) {
						$group = 'high';
					} elseif ( 'medium' === $priority ) {
						$group = 'medium';
					} elseif ( $is_core ) {
						$group = 'wordpress';
					}
				?>
					<div class="qa-notice-card qa-card-archived qa-card-<?php echo esc_attr( $group ); ?> qa-card-<?php echo esc_attr( $priority ); ?>" data-notice-id="<?php echo esc_attr( $id ); ?>" data-plugin-slug="<?php echo esc_attr( $notice['source_slug'] ?? '' ); ?>" data-priority-group="<?php echo esc_attr( $group ); ?>" data-is-core="<?php echo $is_core ? '1' : '0'; ?>">
						<!-- Card Top Meta Bar -->
						<div class="qa-card-meta">
							<label class="qa-card-select-label" title="<?php esc_attr_e( 'Select notice for bulk actions', 'wp-quiet-admin' ); ?>">
								<input type="checkbox" class="qa-card-select-checkbox" value="<?php echo esc_attr( $id ); ?>" />
							</label>
							<div class="qa-card-source">
								<?php if ( $is_core ) : ?>
									<span class="dashicons dashicons-wordpress-alt" style="color: #2271b1; font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span>
									<strong><?php esc_html_e( 'WordPress Core', 'wp-quiet-admin' ); ?></strong>
									<code class="qa-slug-pill">wordpress-core</code>
								<?php else : ?>
									<span class="dashicons dashicons-admin-plugins"></span>
									<strong><?php echo esc_html( $notice['source_name'] ); ?></strong>
									<?php if ( ! empty( $notice['source_slug'] ) ) : ?>
										<code class="qa-slug-pill"><?php echo esc_html( $notice['source_slug'] ); ?></code>
									<?php endif; ?>
								<?php endif; ?>

								<?php if ( $is_core ) : ?>
									<?php if ( 'high' === $priority ) : ?>
										<span class="qa-priority-pill pill-red"><?php esc_html_e( 'Core Critical', 'wp-quiet-admin' ); ?></span>
									<?php elseif ( 'medium' === $priority ) : ?>
										<span class="qa-priority-pill pill-yellow"><?php esc_html_e( 'Core Update', 'wp-quiet-admin' ); ?></span>
									<?php else : ?>
										<span class="qa-priority-pill pill-blue"><?php esc_html_e( 'WordPress Core', 'wp-quiet-admin' ); ?></span>
									<?php endif; ?>
								<?php elseif ( 'high' === $priority ) : ?>
									<span class="qa-priority-pill pill-red"><?php esc_html_e( 'Critical Alert', 'wp-quiet-admin' ); ?></span>
								<?php elseif ( 'medium' === $priority ) : ?>
									<span class="qa-priority-pill pill-yellow"><?php esc_html_e( 'Warning / Update', 'wp-quiet-admin' ); ?></span>
								<?php else : ?>
									<span class="qa-priority-pill pill-green"><?php esc_html_e( 'Review / Promo', 'wp-quiet-admin' ); ?></span>
								<?php endif; ?>

								<span class="qa-archived-pill">
									<span class="dashicons dashicons-yes-alt" style="font-size:12px;vertical-align:middle;margin-right:2px;"></span>
									<?php esc_html_e( 'Dismissed', 'wp-quiet-admin' ); ?>
								</span>
							</div>
							<div class="qa-card-time">
								<span class="dashicons dashicons-clock"></span>
								<?php echo esc_html( human_time_diff( $notice['timestamp'] ?? time(), time() ) . ' ' . __( 'ago', 'wp-quiet-admin' ) ); ?>
							</div>
						</div>

						<!-- Raw Rendered Notice Body -->
						<div class="qa-card-body">
							<div class="qa-raw-notice-wrapper inline">
								<?php echo $this->prepare_notice_html_for_card( $notice['html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>

						<!-- Card Action Footer -->
						<div class="qa-card-footer">
							<div class="qa-footer-left"></div>
							<div class="qa-footer-right">
								<button type="button" class="button button-primary button-small qa-btn-restore" title="<?php esc_attr_e( 'Restore notice back to active view', 'wp-quiet-admin' ); ?>">
									<span class="dashicons dashicons-undo"></span> <?php esc_html_e( 'Restore Notice', 'wp-quiet-admin' ); ?>
								</button>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif;
	}

	/**
	 * Render the Settings / Filters tab.
	 */
	protected function render_settings_tab() {
		$settings          = $this->settings->get_all();
		$installed_plugins = $this->settings->get_installed_plugins();
		$whitelisted       = (array) $settings['whitelisted_plugins'];
		$blacklisted       = (array) $settings['blacklisted_plugins'];
		$audit_log         = $this->notice_manager->get_audit_log();
		$can_purge         = $this->settings->current_user_can_purge();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'index.php?page=wp-quiet-admin&tab=settings' ) ); ?>" class="qa-settings-form">
			<?php wp_nonce_field( 'wp_quiet_admin_save_settings', 'wp_quiet_admin_settings_nonce' ); ?>

			<!-- Urgent Pass-Through & Retention Section -->
			<div class="qa-settings-section">
				<h3><?php esc_html_e( 'Urgency & Pass-Through Rules', 'wp-quiet-admin' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Control whether high-priority alerts bypass Quiet Admin and show directly at the top of regular admin pages.', 'wp-quiet-admin' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Critical (Red) Notices', 'wp-quiet-admin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="passthrough_high" value="1" <?php checked( $settings['passthrough_high'] ); ?> />
								<strong><?php esc_html_e( 'Pass-through Critical / Red Notices', 'wp-quiet-admin' ); ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( '(Recommended) Always render fatal errors, database alerts, and security warnings directly on page headers so you never miss breaking issues. They will also still be cataloged in the Critical hub.', 'wp-quiet-admin' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WordPress Core Updates', 'wp-quiet-admin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="passthrough_core" value="1" <?php checked( $settings['passthrough_core'] ); ?> />
								<strong><?php esc_html_e( 'Pass-through WordPress Core Notices', 'wp-quiet-admin' ); ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( 'Allow official WordPress update notifications and update nags to appear normally on standard pages.', 'wp-quiet-admin' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Admin Toolbar Badge', 'wp-quiet-admin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="show_admin_bar" value="1" <?php checked( $settings['show_admin_bar'] ); ?> />
								<strong><?php esc_html_e( 'Show Quiet Admin multi-pill badges in Admin Bar', 'wp-quiet-admin' ); ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( 'Displays the live color-coded pill counters (🔴 Critical, 🟡 Warnings, 🟢 Reviews & Promos) in the top toolbar.', 'wp-quiet-admin' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Notice Retention', 'wp-quiet-admin' ); ?></th>
						<td>
							<input type="number" name="retention_days" min="1" max="60" value="<?php echo esc_attr( $settings['retention_days'] ); ?>" class="small-text" /> <?php esc_html_e( 'Days', 'wp-quiet-admin' ); ?>
							<p class="description"><?php esc_html_e( 'Automatically purge non-critical promotional and routine notices after this number of days. Unresolved critical alerts and snoozed notices are permanently preserved and never auto-purged.', 'wp-quiet-admin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Audit Trail Retention', 'wp-quiet-admin' ); ?></th>
						<td>
							<input type="number" name="audit_retention_days" min="7" max="180" value="<?php echo esc_attr( $settings['audit_retention_days'] ?? 30 ); ?>" class="small-text" /> <?php esc_html_e( 'Days', 'wp-quiet-admin' ); ?>
							<p class="description"><?php esc_html_e( 'Length of time to preserve administrative user actions (dismissals, snoozes, wakes, archive purges) in the audit log.', 'wp-quiet-admin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Purge Permissions', 'wp-quiet-admin' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="restrict_purge_super_admin" value="1" <?php checked( ! empty( $settings['restrict_purge_super_admin'] ) ); ?> />
								<strong><?php esc_html_e( 'Restrict Permanent Purge to Primary Administrator / Super Admin', 'wp-quiet-admin' ); ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( 'When enabled, only Network Super Administrators (Multisite) or Chief Administrators with user deletion capability (Single Site) can permanently clear or purge the notice archive. Standard administrators can dismiss and snooze notices, but cannot erase incident archives.', 'wp-quiet-admin' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Plugin Rules Section -->
			<div class="qa-settings-section">
				<h3><?php esc_html_e( 'Plugin Notice Rules', 'wp-quiet-admin' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Set rules per plugin. "Show in Admin" plugins also display notices on standard admin pages. "Mute Everywhere" plugins are completely silenced.', 'wp-quiet-admin' ); ?>
				</p>

				<div class="qa-plugins-table-wrapper">
					<table class="wp-list-table widefat fixed striped qa-plugins-table">
						<thead>
							<tr>
								<th class="manage-column column-name"><?php esc_html_e( 'Plugin Name', 'wp-quiet-admin' ); ?></th>
								<th class="manage-column column-slug"><?php esc_html_e( 'Slug / Folder', 'wp-quiet-admin' ); ?></th>
								<th class="manage-column column-status"><?php esc_html_e( 'Status', 'wp-quiet-admin' ); ?></th>
								<th class="manage-column column-rule" style="width: 360px;"><?php esc_html_e( 'Notice Rule', 'wp-quiet-admin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $installed_plugins ) ) : ?>
								<tr><td colspan="4"><?php esc_html_e( 'No plugins detected.', 'wp-quiet-admin' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $installed_plugins as $slug => $plugin ) :
									$is_white = in_array( $slug, $whitelisted, true );
									$is_black = in_array( $slug, $blacklisted, true );
								?>
									<tr>
										<td><strong><?php echo esc_html( $plugin['name'] ); ?></strong></td>
										<td><code><?php echo esc_html( $slug ); ?></code></td>
										<td>
											<?php if ( $plugin['is_active'] ) : ?>
												<span class="qa-status-tag qa-status-active"><?php esc_html_e( 'Active', 'wp-quiet-admin' ); ?></span>
											<?php else : ?>
												<span class="qa-status-tag qa-status-inactive"><?php esc_html_e( 'Inactive', 'wp-quiet-admin' ); ?></span>
											<?php endif; ?>
										</td>
										<td>
											<div class="qa-segmented-control" data-plugin-slug="<?php echo esc_attr( $slug ); ?>">
												<label class="qa-segment <?php echo ( ! $is_white && ! $is_black ) ? 'is-active segment-normal' : ''; ?>" title="<?php esc_attr_e( 'Filter notices normally into Quiet Admin tabs', 'wp-quiet-admin' ); ?>">
													<input type="radio" name="plugin_rules[<?php echo esc_attr( $slug ); ?>]" value="normal" <?php checked( ! $is_white && ! $is_black ); ?> />
													<span><?php esc_html_e( 'Hub (Default)', 'wp-quiet-admin' ); ?></span>
												</label>
												<label class="qa-segment <?php echo $is_white ? 'is-active segment-whitelist' : ''; ?>" title="<?php esc_attr_e( 'Allow notices from this plugin to also show on regular admin screens', 'wp-quiet-admin' ); ?>">
													<input type="radio" name="plugin_rules[<?php echo esc_attr( $slug ); ?>]" value="whitelist" <?php checked( $is_white ); ?> />
													<span><?php esc_html_e( 'Show in Admin', 'wp-quiet-admin' ); ?></span>
												</label>
												<label class="qa-segment <?php echo $is_black ? 'is-active segment-blacklist' : ''; ?>" title="<?php esc_attr_e( 'Completely mute all notices from this plugin everywhere', 'wp-quiet-admin' ); ?>">
													<input type="radio" name="plugin_rules[<?php echo esc_attr( $slug ); ?>]" value="blacklist" <?php checked( $is_black ); ?> />
													<span><?php esc_html_e( 'Mute Everywhere', 'wp-quiet-admin' ); ?></span>
												</label>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary button-hero">
					<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Quiet Admin Rules', 'wp-quiet-admin' ); ?>
				</button>
			</p>
		</form>

		<!-- Section 3: User Action Audit Trail -->
		<div class="qa-settings-section qa-audit-section">
			<div class="qa-audit-header-flex">
				<div>
					<h3>
						<span class="dashicons dashicons-list-view" style="margin-right: 6px; color: #2271b1; vertical-align: middle;"></span>
						<?php esc_html_e( 'User Action Audit Trail', 'wp-quiet-admin' ); ?>
					</h3>
					<p class="description">
						<?php esc_html_e( 'Tracks which administrator dismissed, snoozed, awakened, or purged notices. Automatically pruned according to your audit retention threshold.', 'wp-quiet-admin' ); ?>
					</p>
				</div>
				<div>
					<form method="post" action="<?php echo esc_url( admin_url( 'index.php?page=wp-quiet-admin&tab=settings' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to permanently clear the audit trail?', 'wp-quiet-admin' ) ); ?>');">
						<?php wp_nonce_field( 'wp_quiet_admin_clear_audit_action', 'wp_quiet_admin_clear_audit_nonce' ); ?>
						<button type="submit" class="button button-secondary button-small qa-btn-clear-audit" <?php echo ( empty( $audit_log ) || ! $can_purge ) ? 'disabled' : ''; ?> title="<?php echo ( ! $can_purge ) ? esc_attr__( 'Clearing the audit trail requires primary administrator privileges.', 'wp-quiet-admin' ) : ''; ?>">
							<span class="dashicons <?php echo ( ! $can_purge ) ? 'dashicons-lock' : 'dashicons-trash'; ?>"></span> <?php esc_html_e( 'Clear Audit Trail', 'wp-quiet-admin' ); ?>
						</button>
					</form>
				</div>
			</div>

			<div class="qa-audit-table-wrapper">
				<?php if ( empty( $audit_log ) ) : ?>
					<div class="qa-audit-empty">
						<span class="dashicons dashicons-info-outline"></span>
						<p><?php esc_html_e( 'No actions recorded yet. User dismissals, snoozes, wakes, and archive purges will be logged here.', 'wp-quiet-admin' ); ?></p>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped qa-audit-table">
						<thead>
							<tr>
								<th class="column-time" style="width: 170px;"><?php esc_html_e( 'Date & Time', 'wp-quiet-admin' ); ?></th>
								<th class="column-user" style="width: 180px;"><?php esc_html_e( 'User', 'wp-quiet-admin' ); ?></th>
								<th class="column-action" style="width: 140px;"><?php esc_html_e( 'Action', 'wp-quiet-admin' ); ?></th>
								<th class="column-details"><?php esc_html_e( 'Event Summary', 'wp-quiet-admin' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $audit_log as $entry ) :
								$action_key   = $entry['action'] ?? 'unknown';
								$action_label = ucfirst( str_replace( '_', ' ', $action_key ) );
								$badge_class  = 'badge-info';
								if ( in_array( $action_key, array( 'dismiss', 'bulk_dismiss', 'dismiss_tab' ), true ) ) {
									$badge_class = 'badge-dismiss';
								} elseif ( in_array( $action_key, array( 'snooze', 'bulk_snooze' ), true ) ) {
									$badge_class = 'badge-snooze';
								} elseif ( in_array( $action_key, array( 'restore', 'unsnooze', 'wake_all' ), true ) ) {
									$badge_class = 'badge-wake';
								} elseif ( in_array( $action_key, array( 'purge_archive', 'clear_all' ), true ) ) {
									$badge_class = 'badge-purge';
								}
								$summary = $entry['details']['summary'] ?? ( $entry['details']['details'] ?? $action_label );
							?>
								<tr>
									<td>
										<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['timestamp'] ) ); ?></strong>
										<div class="qa-audit-ago"><?php echo esc_html( human_time_diff( $entry['timestamp'], time() ) . ' ' . __( 'ago', 'wp-quiet-admin' ) ); ?></div>
									</td>
									<td>
										<div class="qa-audit-user">
											<?php if ( ! empty( $entry['user_id'] ) ) : ?>
												<?php echo get_avatar( $entry['user_id'], 22 ); ?>
											<?php else : ?>
												<span class="dashicons dashicons-admin-users"></span>
											<?php endif; ?>
											<span class="qa-audit-username"><?php echo esc_html( $entry['user_name'] ?? $entry['user_login'] ); ?></span>
											<code class="qa-audit-login"><?php echo esc_html( $entry['user_login'] ); ?></code>
										</div>
									</td>
									<td>
										<span class="qa-audit-badge <?php echo esc_attr( $badge_class ); ?>">
											<?php echo esc_html( $action_label ); ?>
										</span>
									</td>
									<td>
										<span class="qa-audit-summary-text"><?php echo esc_html( $summary ); ?></span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<!-- Section 4: Configuration Export & Import -->
		<div class="qa-settings-section qa-portability-section">
			<h3>
				<span class="dashicons dashicons-cloud" style="margin-right: 6px; color: #2271b1; vertical-align: middle;"></span>
				<?php esc_html_e( 'Export & Import Configuration', 'wp-quiet-admin' ); ?>
			</h3>
			<p class="description">
				<?php esc_html_e( 'Back up your rules or transfer your "Show in Admin" pass-through whitelist and "Mute Everywhere" blacklist to another WordPress installation.', 'wp-quiet-admin' ); ?>
			</p>

			<div class="qa-portability-grid">
				<!-- Export Box -->
				<div class="qa-portability-box">
					<div class="qa-portability-box-inner">
						<h4>
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Export Settings', 'wp-quiet-admin' ); ?>
						</h4>
						<p>
							<?php esc_html_e( 'Download a JSON file containing all plugin display rules, pass-through preferences, and retention thresholds.', 'wp-quiet-admin' ); ?>
						</p>
						<form method="post" action="<?php echo esc_url( admin_url( 'index.php?page=wp-quiet-admin&tab=settings' ) ); ?>">
							<?php wp_nonce_field( 'wp_quiet_admin_export_settings', 'wp_quiet_admin_export_nonce' ); ?>
							<button type="submit" class="button button-secondary">
								<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Download Export (.json)', 'wp-quiet-admin' ); ?>
							</button>
						</form>
					</div>
				</div>

				<!-- Import Box -->
				<div class="qa-portability-box">
					<div class="qa-portability-box-inner">
						<h4>
							<span class="dashicons dashicons-upload"></span>
							<?php esc_html_e( 'Import Settings', 'wp-quiet-admin' ); ?>
						</h4>
						<p>
							<?php esc_html_e( 'Upload a valid WP Quiet Admin JSON settings file. Imported rules will be merged with your current settings.', 'wp-quiet-admin' ); ?>
						</p>
						<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'index.php?page=wp-quiet-admin&tab=settings' ) ); ?>">
							<?php wp_nonce_field( 'wp_quiet_admin_import_settings', 'wp_quiet_admin_import_nonce' ); ?>
							<div class="qa-import-file-row">
								<input type="file" name="import_file" accept=".json,application/json" required />
								<button type="submit" class="button button-primary">
									<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Import Settings', 'wp-quiet-admin' ); ?>
								</button>
							</div>
						</form>
					</div>
		</div>

		<!-- Section 5: Support Open Source Development -->
		<div class="qa-settings-section qa-support-section">
			<div class="qa-support-card">
				<div class="qa-support-card-left">
					<div class="qa-support-icon">
						<span class="dashicons dashicons-heart"></span>
					</div>
					<div class="qa-support-text">
						<h3><?php esc_html_e( 'Support WP Quiet Admin Development', 'wp-quiet-admin' ); ?></h3>
						<p>
							<?php esc_html_e( 'WP Quiet Admin is 100% free and open-source software by RemarkablePC. There are no artificial feature paywalls, subscription nags, or telemetry trackers. If this plugin has brought peace to your dashboard, consider supporting continuous maintenance and feature updates.', 'wp-quiet-admin' ); ?>
						</p>
					</div>
				</div>
				<div class="qa-support-actions">
					<a href="https://github.com/sponsors/remarkablepc?utm_source=wp-quiet-admin&utm_medium=plugin-settings" target="_blank" rel="noopener noreferrer" class="button qa-btn-sponsor">
						<span class="dashicons dashicons-star-filled" style="color: #ea4aaa; vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Sponsor on GitHub', 'wp-quiet-admin' ); ?>
					</a>
					<a href="https://www.paypal.com/ncp/payment/EKH76RTYHH24S" target="_blank" rel="noopener noreferrer" class="button button-primary qa-btn-paypal">
						<span class="dashicons dashicons-money-alt" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Donate via PayPal / Venmo', 'wp-quiet-admin' ); ?>
					</a>
					<a href="https://github.com/remarkablepc/WP-Quiet-Admin/discussions" target="_blank" rel="noopener noreferrer" class="button qa-btn-discuss" title="<?php esc_attr_e( 'Join community discussions on GitHub (Free open-source software - no 1-on-1 support)', 'wp-quiet-admin' ); ?>">
						<span class="dashicons dashicons-format-chat" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Community Discussions', 'wp-quiet-admin' ); ?>
					</a>
				</div>
			</div>
			<p class="description" style="margin-top: 10px; font-style: italic; font-size: 12px; color: #646970;">
				<?php esc_html_e( 'Note: WP Quiet Admin is free open-source software provided as-is without individual, 1-on-1 technical support. Feel free to connect with fellow users and share ideas in GitHub Discussions!', 'wp-quiet-admin' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Prepare notice HTML for safe rendering inside a Quiet Admin card.
	 *
	 * Appends the 'inline' class to notice divs so that WordPress Core's
	 * common.js does not detach and move them to the top of the screen (.wp-header-end).
	 *
	 * @param string $html Raw notice HTML.
	 * @return string Processed HTML.
	 */
	protected function prepare_notice_html_for_card( $html ) {
		if ( empty( $html ) ) {
			return '';
		}

		// Inject 'inline' class into any div/p/section with notice, updated, or error classes
		$html = preg_replace_callback(
			'/<(div|p|section)([^>]*\bclass=["\'])([^"\']*?)(["\'][^>]*)>/is',
			function( $matches ) {
				$tag     = $matches[1];
				$classes = $matches[3];
				if ( preg_match( '/\b(notice|updated|error)\b/i', $classes ) ) {
					if ( false === strpos( $classes, 'inline' ) ) {
						$classes .= ' inline';
					}
				}
				return '<' . $tag . $matches[2] . $classes . $matches[4] . '>';
			},
			$html
		);

		return $html;
	}
}
}
