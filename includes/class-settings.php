<?php
/**
 * WP Quiet Admin Settings Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Quiet_Admin_Settings {

	/**
	 * Option key stored in wp_options.
	 */
	const OPTION_KEY = 'wp_quiet_admin_settings';

	/**
	 * Settings cache
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook settings registration if needed
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Default settings
	 *
	 * @return array
	 */
	public function get_defaults() {
		return array(
			'passthrough_high'            => true,  // Allow Red (High Priority) notices to appear directly on admin pages
			'passthrough_core'            => true,  // Allow WordPress Core update nags to appear directly
			'show_admin_bar'              => true,  // Show quiet count badge in top WP Admin Bar
			'whitelisted_plugins'         => array(), // Plugins whose notices are always allowed on regular pages
			'blacklisted_plugins'         => array(), // Plugins whose notices are completely hidden everywhere
			'retention_days'              => 7,     // Keep captured notices for 7 days
			'audit_retention_days'        => 30,    // Keep audit trail log for 30 days
			'restrict_purge_super_admin'  => false, // Restrict permanent purge to Super Admin / Chief Administrator
		);
	}

	/**
	 * Get all settings
	 *
	 * @return array
	 */
	public function get_all() {
		if ( null === $this->settings ) {
			$saved          = get_option( self::OPTION_KEY, array() );
			$this->settings = wp_parse_args( $saved, $this->get_defaults() );
		}
		return $this->settings;
	}

	/**
	 * Get a single setting value
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$all = $this->get_all();
		if ( isset( $all[ $key ] ) ) {
			return $all[ $key ];
		}
		return ( null !== $default ) ? $default : ( $this->get_defaults()[ $key ] ?? null );
	}

	/**
	 * Save all settings
	 *
	 * @param array $new_settings Settings array.
	 * @return bool
	 */
	public function update_all( $new_settings ) {
		$sanitized = array(
			'passthrough_high'           => ! empty( $new_settings['passthrough_high'] ),
			'passthrough_core'           => ! empty( $new_settings['passthrough_core'] ),
			'show_admin_bar'             => ! empty( $new_settings['show_admin_bar'] ),
			'whitelisted_plugins'        => ! empty( $new_settings['whitelisted_plugins'] ) && is_array( $new_settings['whitelisted_plugins'] )
				? array_values( array_map( 'sanitize_text_field', $new_settings['whitelisted_plugins'] ) )
				: array(),
			'blacklisted_plugins'        => ! empty( $new_settings['blacklisted_plugins'] ) && is_array( $new_settings['blacklisted_plugins'] )
				? array_values( array_map( 'sanitize_text_field', $new_settings['blacklisted_plugins'] ) )
				: array(),
			'retention_days'             => isset( $new_settings['retention_days'] ) ? absint( $new_settings['retention_days'] ) : 7,
			'audit_retention_days'       => isset( $new_settings['audit_retention_days'] ) ? absint( $new_settings['audit_retention_days'] ) : 30,
			'restrict_purge_super_admin' => ! empty( $new_settings['restrict_purge_super_admin'] ),
		);

		$this->settings = $sanitized;
		return update_option( self::OPTION_KEY, $sanitized );
	}

	/**
	 * Check if current user has permission to permanently purge notices.
	 *
	 * On multisite networks, requires super admin privileges.
	 * On single-site, if restricted, requires user deletion capability (chief admin).
	 *
	 * @return bool
	 */
	public function current_user_can_purge() {
		if ( is_multisite() ) {
			return is_super_admin();
		}

		if ( $this->get( 'restrict_purge_super_admin', false ) ) {
			return current_user_can( 'delete_users' );
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if a plugin slug is allowed to show on live admin screens.
	 *
	 * @param string $slug Plugin slug or file.
	 * @return bool
	 */
	public function is_passthrough( $slug ) {
		return $this->is_whitelisted( $slug );
	}

	/**
	 * Check if a plugin slug is whitelisted.
	 *
	 * @param string $slug Plugin slug or file.
	 * @return bool
	 */
	public function is_whitelisted( $slug ) {
		if ( empty( $slug ) ) {
			return false;
		}
		$whitelisted = (array) $this->get( 'whitelisted_plugins', array() );
		return in_array( $slug, $whitelisted, true );
	}

	/**
	 * Check if a plugin slug is blacklisted.
	 *
	 * @param string $slug Plugin slug or file.
	 * @return bool
	 */
	public function is_blacklisted( $slug ) {
		if ( empty( $slug ) ) {
			return false;
		}
		$blacklisted = (array) $this->get( 'blacklisted_plugins', array() );
		return in_array( $slug, $blacklisted, true );
	}

	/**
	 * Add a plugin to live screen pass-through.
	 *
	 * @param string $slug Plugin slug or file.
	 * @return bool
	 */
	public function add_to_passthrough( $slug ) {
		return $this->add_to_whitelist( $slug );
	}

	/**
	 * Add a plugin to whitelist.
	 *
	 * @param string $slug Plugin slug or file.
	 * @return bool
	 */
	public function add_to_whitelist( $slug ) {
		$slug = sanitize_text_field( $slug );
		if ( empty( $slug ) ) {
			return false;
		}

		$settings = $this->get_all();
		
		// Remove from blacklist if present
		$settings['blacklisted_plugins'] = array_diff( (array) $settings['blacklisted_plugins'], array( $slug ) );

		if ( ! in_array( $slug, $settings['whitelisted_plugins'], true ) ) {
			$settings['whitelisted_plugins'][] = $slug;
		}

		return $this->update_all( $settings );
	}

	/**
	 * Add a plugin to blacklist.
	 *
	 * @param string $slug Plugin slug or file.
	 * @return bool
	 */
	public function add_to_blacklist( $slug ) {
		$slug = sanitize_text_field( $slug );
		if ( empty( $slug ) ) {
			return false;
		}

		$settings = $this->get_all();

		// Remove from whitelist if present
		$settings['whitelisted_plugins'] = array_diff( (array) $settings['whitelisted_plugins'], array( $slug ) );

		if ( ! in_array( $slug, $settings['blacklisted_plugins'], true ) ) {
			$settings['blacklisted_plugins'][] = $slug;
		}

		return $this->update_all( $settings );
	}

	/**
	 * Remove a plugin from both whitelist and blacklist.
	 *
	 * @param string $slug Plugin slug.
	 * @return bool
	 */
	public function reset_plugin_status( $slug ) {
		$slug = sanitize_text_field( $slug );
		$settings = $this->get_all();
		$settings['whitelisted_plugins'] = array_diff( (array) $settings['whitelisted_plugins'], array( $slug ) );
		$settings['blacklisted_plugins'] = array_diff( (array) $settings['blacklisted_plugins'], array( $slug ) );
		return $this->update_all( $settings );
	}

	/**
	 * Register settings for WP Options API.
	 */
	public function register_settings() {
		register_setting( 'wp_quiet_admin_group', self::OPTION_KEY );
	}

	/**
	 * Get list of all installed plugins on the site.
	 *
	 * @return array
	 */
	public function get_installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$active      = (array) get_option( 'active_plugins', array() );
		$formatted   = array();

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$slug = dirname( $plugin_file );
			if ( '.' === $slug || empty( $slug ) ) {
				$slug = sanitize_title( $plugin_data['Name'] );
			}

			$formatted[ $slug ] = array(
				'file'        => $plugin_file,
				'slug'        => $slug,
				'name'        => $plugin_data['Name'],
				'version'     => $plugin_data['Version'],
				'author'      => $plugin_data['Author'],
				'is_active'   => function_exists( 'is_plugin_active' ) ? is_plugin_active( $plugin_file ) : in_array( $plugin_file, $active, true ),
				'whitelisted' => $this->is_whitelisted( $slug ),
				'passthrough' => $this->is_whitelisted( $slug ),
				'blacklisted' => $this->is_blacklisted( $slug ),
			);
		}

		uasort( $formatted, function( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		});

		return $formatted;
	}
}
