<?php
/**
 * WP Quiet Admin Classifier
 *
 * Intelligently classifies admin notices into priority categories:
 * - high: Red (Critical errors, security, site breaking)
 * - medium: Yellow (Warnings, updates available, config requirements)
 * - low: Green (Upsells, review prompts, discounts, social follow)
 * - other: Grey/Blue (General info, success, unclassified)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Quiet_Admin_Classifier {

	/**
	 * Priority constants
	 */
	const PRIORITY_HIGH   = 'high';
	const PRIORITY_MEDIUM = 'medium';
	const PRIORITY_LOW    = 'low';
	const PRIORITY_OTHER  = 'other';

	/**
	 * Low priority / upsell & review triggers (case-insensitive regex)
	 *
	 * @var array
	 */
	private $low_patterns = array(
		// Review & Rating prompts
		'/\b(rate\s+us|leave\s+a\s+review|5[\s-]star|five[\s-]star|give\s+us\s+5\s+stars|review\s+us|love\s+using|happy\s+with|enjoying\s+the\s+plugin)\b/i',
		'/\b(share\s+(your\s+)?feedback|take\s+our\s+survey|satisfaction\s+survey)\b/i',
		
		// Pro / Upgrade / Purchase
		'/\b(upgrade\s+to\s+pro|get\s+pro|buy\s+pro|go\s+pro|upgrade\s+now|purchase\s+a\s+license|unlock\s+all\s+features|premium\s+version|unlock\s+pro|pro\s+features|pro\s+addon|get\s+premium)\b/i',
		
		// Sales, discounts & coupons
		'/\b(black\s+friday|cyber\s+monday|halloween\s+sale|holiday\s+sale|special\s+discount|\d+%\s+off|coupon\s+code|limited\s+time\s+offer|pricing\s+plans|summer\s+sale)\b/i',
		
		// Marketing, Newsletter, Social & Telemetry
		'/\b(opt[\s-]in|subscribe\s+to\s+our\s+newsletter|join\s+our\s+community|follow\s+us\s+on|like\s+us\s+on|join\s+our\s+facebook|telegram\s+channel|discord\s+server)\b/i',
		'/\b(affiliate\s+program|earn\s+money|refer\s+a\s+friend)\b/i',
	);

	/**
	 * High priority / Critical triggers
	 *
	 * @var array
	 */
	private $high_patterns = array(
		'/\b(fatal\s+error|critical\s+issue|critical\s+error|security\s+vulnerability|security\s+alert|data\s+breach)\b/i',
		'/\b(database\s+error|database\s+repair|failed\s+to\s+connect|unable\s+to\s+connect\s+to\s+database)\b/i',
		'/\b(incompatible\s+php|php\s+version\s+is\s+too\s+old|php\s+version\s+not\s+supported)\b/i',
		'/\b(unauthorized\s+access|permission\s+denied|license\s+has\s+expired\s+and\s+features\s+disabled)\b/i',
	);

	/**
	 * Medium priority / Warning & Update triggers
	 *
	 * @var array
	 */
	private $medium_patterns = array(
		'/\b(update\s+available|new\s+version\s+(is\s+)?available|please\s+update|compatibility\s+warning)\b/i',
		'/\b(backup\s+recommended|configuration\s+required|setup\s+is\s+incomplete|action\s+required|attention\s+needed)\b/i',
		'/\b(deprecated\s+function|deprecated\s+method|notice:\s+wp-config)\b/i',
	);

	/**
	 * Classify a notice based on its HTML content, classes, and source.
	 *
	 * @param string $html Raw notice HTML.
	 * @param array  $metadata Additional metadata (plugin slug, callback, etc.).
	 * @return array Array with 'priority', 'confidence', 'tags', and 'title'.
	 */
	public function classify( $html, $metadata = array() ) {
		$stripped_text = trim( wp_strip_all_tags( $html ) );
		$lower_html    = strtolower( $html );
		$lower_text    = strtolower( $stripped_text );

		$tags = array();

		// 1. Detect standard WordPress notice CSS classes
		$has_error_class   = ( false !== strpos( $lower_html, 'notice-error' ) || false !== strpos( $lower_html, ' error ' ) || preg_match( '/\b(notice-error|error)\b/', $lower_html ) );
		$has_warning_class = ( false !== strpos( $lower_html, 'notice-warning' ) || false !== strpos( $lower_html, 'update-nag' ) );
		$has_info_class    = ( false !== strpos( $lower_html, 'notice-info' ) );
		$has_success_class = ( false !== strpos( $lower_html, 'notice-success' ) || false !== strpos( $lower_html, 'updated' ) );

		// 2. Check for Low Priority (Marketing, Upsells, Reviews)
		$low_score = 0;
		foreach ( $this->low_patterns as $pattern ) {
			if ( preg_match( $pattern, $stripped_text, $matches ) ) {
				$low_score++;
				$tags[] = 'marketing:' . sanitize_title( $matches[0] );
			}
		}

		// 3. Check for High Priority (Security, Fatal, Critical)
		$high_score = 0;
		foreach ( $this->high_patterns as $pattern ) {
			if ( preg_match( $pattern, $stripped_text, $matches ) ) {
				$high_score++;
				$tags[] = 'critical:' . sanitize_title( $matches[0] );
			}
		}

		// 4. Check for Medium Priority (Updates, Warnings)
		$medium_score = 0;
		foreach ( $this->medium_patterns as $pattern ) {
			if ( preg_match( $pattern, $stripped_text, $matches ) ) {
				$medium_score++;
				$tags[] = 'warning:' . sanitize_title( $matches[0] );
			}
		}

		// DECISION LOGIC:
		// A. Deceitful/Aggressive marketing disguised as error/warning:
		// If it has low-priority keywords (upgrade, review, discount) and no genuine high-score security/fatal keywords,
		// it is categorized as LOW even if the author slapped `notice-error` or `notice-warning` on it.
		if ( $low_score > 0 && $high_score === 0 ) {
			return array(
				'priority' => self::PRIORITY_LOW,
				'label'    => __( 'Promotional / Review', 'wp-quiet-admin' ),
				'badge'    => 'low',
				'tags'     => $tags,
				'summary'  => $this->generate_summary( $stripped_text ),
			);
		}

		// B. Genuine High Priority:
		if ( $high_score > 0 || ( $has_error_class && $low_score === 0 ) ) {
			return array(
				'priority' => self::PRIORITY_HIGH,
				'label'    => __( 'Critical Notice', 'wp-quiet-admin' ),
				'badge'    => 'high',
				'tags'     => $tags,
				'summary'  => $this->generate_summary( $stripped_text ),
			);
		}

		// C. Genuine Medium Priority:
		if ( $medium_score > 0 || $has_warning_class ) {
			return array(
				'priority' => self::PRIORITY_MEDIUM,
				'label'    => __( 'Warning / Update', 'wp-quiet-admin' ),
				'badge'    => 'medium',
				'tags'     => $tags,
				'summary'  => $this->generate_summary( $stripped_text ),
			);
		}

		// D. Other / Unclassified
		return array(
			'priority' => self::PRIORITY_OTHER,
			'label'    => __( 'General Notice', 'wp-quiet-admin' ),
			'badge'    => 'other',
			'tags'     => $tags,
			'summary'  => $this->generate_summary( $stripped_text ),
		);
	}

	/**
	 * Generate a clean 1-2 sentence preview summary of notice text.
	 *
	 * @param string $text Plain notice text.
	 * @return string
	 */
	private function generate_summary( $text ) {
		$clean = preg_replace( '/\s+/', ' ', $text );
		if ( mb_strlen( $clean ) > 140 ) {
			return mb_substr( $clean, 0, 137 ) . '...';
		}
		return $clean;
	}
}
