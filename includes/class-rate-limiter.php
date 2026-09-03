<?php
/**
 * Rate Limiter Class
 *
 * Provides IP-based and session-based rate limiting for public widget endpoints.
 * Builds on the existing Security::check_rate_limit() pattern but supports
 * anonymous (non-logged-in) users via IP address identification.
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate Limiter Class
 */
class Rate_Limiter {

	/**
	 * Check if an action is within rate limits.
	 *
	 * @param string $action     Action identifier (e.g. 'widget_message').
	 * @param string $identifier Unique identifier (IP address, session token, etc.).
	 * @param int    $limit      Maximum allowed attempts within the period.
	 * @param int    $period     Time period in seconds.
	 * @return bool True if allowed, false if rate limited.
	 */
	public static function check( $action, $identifier, $limit = 20, $period = 3600 ) {
		$key      = 'chatpr_rl_' . md5( $action . '_' . $identifier );
		$attempts = get_transient( $key );

		if ( false === $attempts ) {
			set_transient( $key, 1, $period );
			return true;
		}

		if ( (int) $attempts >= $limit ) {
			return false;
		}

		set_transient( $key, (int) $attempts + 1, $period );
		return true;
	}

	/**
	 * Get remaining allowed requests.
	 *
	 * @param string $action     Action identifier.
	 * @param string $identifier Unique identifier.
	 * @param int    $limit      Maximum allowed attempts.
	 * @param int    $period     Time period in seconds.
	 * @return int Remaining requests.
	 */
	public static function get_remaining( $action, $identifier, $limit = 20, $period = 3600 ) {
		$key      = 'chatpr_rl_' . md5( $action . '_' . $identifier );
		$attempts = get_transient( $key );

		if ( false === $attempts ) {
			return $limit;
		}

		return max( 0, $limit - (int) $attempts );
	}

	/**
	 * Check rate limit for widget message sending.
	 *
	 * Enforces both per-session and per-IP limits.
	 *
	 * @param string $session_token Session token.
	 * @param string $ip            Client IP address.
	 * @return bool|\WP_Error True if allowed, WP_Error if rate limited.
	 */
	public static function check_widget_message( $session_token, $ip ) {
		$msg_limit = absint( get_option( 'chatprojects_widget_rate_limit_msgs', 20 ) );
		$ip_limit  = $msg_limit * 3; // Per-IP limit is higher to allow multiple sessions.

		// Check per-session limit.
		if ( ! self::check( 'widget_msg_session', $session_token, $msg_limit, HOUR_IN_SECONDS ) ) {
			return new \WP_Error(
				'rate_limited',
				__( 'You have reached the message limit. Please try again later.', 'chatprojects' )
			);
		}

		// Check per-IP limit.
		if ( ! self::check( 'widget_msg_ip', $ip, $ip_limit, HOUR_IN_SECONDS ) ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Too many requests from your location. Please try again later.', 'chatprojects' )
			);
		}

		return true;
	}

	/**
	 * Check rate limit for widget session creation.
	 *
	 * @param string $ip Client IP address.
	 * @return bool|\WP_Error True if allowed, WP_Error if rate limited.
	 */
	public static function check_widget_session( $ip ) {
		$session_limit = absint( get_option( 'chatprojects_widget_rate_limit_sessions', 5 ) );

		if ( ! self::check( 'widget_session', $ip, $session_limit, HOUR_IN_SECONDS ) ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Too many chat sessions created. Please try again later.', 'chatprojects' )
			);
		}

		return true;
	}
}
