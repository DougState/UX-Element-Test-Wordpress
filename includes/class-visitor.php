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
	 * Even when forwarding headers are enabled, they are only honored
	 * when the immediate connection (REMOTE_ADDR) is itself a trusted
	 * proxy listed in `elementtest_trusted_proxy_cidrs`. A request that
	 * reaches admin-ajax.php directly — bypassing the proxy — therefore
	 * cannot spoof its IP via these headers; it falls back to REMOTE_ADDR.
	 *
	 * Sites behind Cloudflare, nginx, or another reverse proxy should
	 * add a filter that returns the headers they trust, e.g.:
	 *
	 *   add_filter( 'elementtest_trusted_proxy_headers', function () {
	 *       return array( 'HTTP_CF_CONNECTING_IP' );
	 *   } );
	 *
	 * ...and, if the proxy connects from a public IP, the CIDR(s) it
	 * connects from:
	 *
	 *   add_filter( 'elementtest_trusted_proxy_cidrs', function () {
	 *       return array( '203.0.113.0/24' );
	 *   } );
	 *
	 * @since  2.2.0
	 * @return string Sanitised IP address string.
	 */
	public static function get_visitor_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		$remote = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';

		$trusted_headers = (array) apply_filters( 'elementtest_trusted_proxy_headers', array() );

		// Only honor forwarded headers when the direct connection is a
		// trusted proxy. Otherwise the headers are attacker-controlled.
		if ( ! empty( $trusted_headers ) && '' !== $remote && self::is_trusted_proxy( $remote ) ) {
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
		}

		return '' !== $remote ? $remote : '0.0.0.0';
	}

	/**
	 * Determine whether an IP belongs to a trusted reverse proxy.
	 *
	 * The set of trusted-proxy CIDRs is supplied by the
	 * `elementtest_trusted_proxy_cidrs` filter, which the plugin wires
	 * to the admin "Reverse Proxy / CDN" setting (Cloudflare ranges,
	 * private/loopback ranges for nginx, or a custom list).
	 *
	 * @since  2.5.4
	 * @param  string $ip Validated IP address (IPv4 or IPv6).
	 * @return bool   True when $ip falls inside a trusted-proxy CIDR.
	 */
	private static function is_trusted_proxy( $ip ) {
		$cidrs = (array) apply_filters( 'elementtest_trusted_proxy_cidrs', array() );

		foreach ( $cidrs as $cidr ) {
			if ( is_string( $cidr ) && '' !== $cidr && self::ip_in_cidr( $ip, $cidr ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Test whether an IP address falls within a CIDR range.
	 *
	 * Works for both IPv4 and IPv6 by comparing the binary
	 * representations under a prefix-length mask. A bare IP (no "/")
	 * is treated as a single-host range. Mismatched address families
	 * (e.g. an IPv4 IP against an IPv6 range) never match.
	 *
	 * @since  2.5.4
	 * @param  string $ip   Validated IP address.
	 * @param  string $cidr CIDR notation (e.g. "10.0.0.0/8") or a bare IP.
	 * @return bool
	 */
	private static function ip_in_cidr( $ip, $cidr ) {
		if ( strpos( $cidr, '/' ) === false ) {
			$cidr .= ( strpos( $cidr, ':' ) !== false ) ? '/128' : '/32';
		}

		list( $subnet, $bits ) = explode( '/', $cidr, 2 );
		$bits = (int) $bits;

		$ip_bin     = @inet_pton( $ip );
		$subnet_bin = @inet_pton( $subnet );

		if ( false === $ip_bin || false === $subnet_bin ) {
			return false;
		}

		// Normalize IPv4-mapped IPv6 addresses (::ffff:x.x.x.x) to plain IPv4.
		$mapped_prefix = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
		if ( 16 === strlen( $ip_bin ) && substr( $ip_bin, 0, 12 ) === $mapped_prefix ) {
			$ip_bin = substr( $ip_bin, 12 );
		}
		if ( 16 === strlen( $subnet_bin ) && substr( $subnet_bin, 0, 12 ) === $mapped_prefix ) {
			$subnet_bin = substr( $subnet_bin, 12 );
			$bits       = max( 0, $bits - 96 );
		}

		// Reject cross-family comparisons (after mapped-address normalization).
		if ( strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
			return false;
		}

		$max_bits = strlen( $ip_bin ) * 8;
		if ( $bits < 0 || $bits > $max_bits ) {
			return false;
		}

		$whole_bytes = intdiv( $bits, 8 );
		$rem_bits    = $bits % 8;

		if ( $whole_bytes > 0 && substr( $ip_bin, 0, $whole_bytes ) !== substr( $subnet_bin, 0, $whole_bytes ) ) {
			return false;
		}

		if ( $rem_bits > 0 ) {
			$mask = chr( ( 0xff << ( 8 - $rem_bits ) ) & 0xff );
			if ( ( $ip_bin[ $whole_bytes ] & $mask ) !== ( $subnet_bin[ $whole_bytes ] & $mask ) ) {
				return false;
			}
		}

		return true;
	}
}
