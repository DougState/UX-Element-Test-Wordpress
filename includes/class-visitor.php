<?php
/**
 * Visitor identity utilities for ElementTest Pro.
 *
 * Provides server-side, deterministic visitor identification used
 * for impression/conversion deduplication. The hash is computed
 * from REMOTE_ADDR + User-Agent + AUTH_SALT so that clients cannot
 * rotate their identity to bypass dedup. Proxy forwarding headers
 * are only trusted when explicitly enabled via filter.
 *
 * @package ElementTestPro
 * @since   2.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ElementTest_Visitor
 *
 * Pure-static utility — no instance state, no singleton.
 *
 * @since 2.2.0
 */
class ElementTest_Visitor {

	/**
	 * Generate a privacy-friendly hash for the current visitor.
	 *
	 * For logged-in users the hash is derived from the user ID plus a
	 * site-specific salt.  For anonymous visitors it is derived from
	 * the IP address and User-Agent header, again salted.
	 *
	 * The result is a one-way SHA-256 hash — it cannot be reversed to
	 * recover the original IP or user ID.
	 *
	 * @since  2.2.0
	 * @return string 64-character hexadecimal SHA-256 hash.
	 */
	public static function get_user_hash() {
		$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'elementtest-default-salt';

		if ( is_user_logged_in() ) {
			$raw = 'user_' . get_current_user_id() . '_' . $salt;
		} else {
			$ip = self::get_visitor_ip();

			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
				: '';

			$raw = $ip . '|' . $user_agent . '|' . $salt;
		}

		return hash( 'sha256', $raw );
	}

	/**
	 * Retrieve the visitor's IP address.
	 *
	 * Defaults to REMOTE_ADDR only. Proxy forwarding headers
	 * (X-Forwarded-For, X-Real-IP, CF-Connecting-IP) are NOT trusted
	 * unless explicitly enabled via the `elementtest_trusted_proxy_headers`
	 * filter, because any external client can spoof them.
	 *
	 * Sites behind Cloudflare, nginx, or another reverse proxy should
	 * add a filter that returns the headers they trust, e.g.:
	 *
	 *   add_filter( 'elementtest_trusted_proxy_headers', function () {
	 *       return array( 'HTTP_CF_CONNECTING_IP' );
	 *   } );
	 *
	 * @since  2.2.0
	 * @return string Sanitised IP address string.
	 */
	public static function get_visitor_ip() {
		$trusted_headers = (array) apply_filters( 'elementtest_trusted_proxy_headers', array() );

		foreach ( $trusted_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

				if ( strpos( $value, ',' ) !== false ) {
					$value = trim( explode( ',', $value )[0] );
				}

				if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
					return $value;
				}
			}
		}

		$remote = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		if ( filter_var( $remote, FILTER_VALIDATE_IP ) ) {
			return $remote;
		}

		return '0.0.0.0';
	}
}
