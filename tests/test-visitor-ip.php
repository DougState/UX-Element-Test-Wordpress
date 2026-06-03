<?php
/**
 * Standalone regression test for trusted-proxy IP resolution (issue #52).
 *
 * This repo has no PHPUnit harness, so this test stubs the handful of
 * WordPress functions ElementTest_Visitor depends on and drives
 * get_visitor_ip() directly. Run with:
 *
 *   php tests/test-visitor-ip.php
 *
 * Exit code 0 = all assertions passed; 1 = failure.
 *
 * @package ElementTestPro
 */

// --- Minimal WordPress shims --------------------------------------------

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['__et_trusted_headers'] = array();
$GLOBALS['__et_trusted_cidrs']   = array();

function apply_filters( $hook, $value ) {
	if ( 'elementtest_trusted_proxy_headers' === $hook ) {
		return $GLOBALS['__et_trusted_headers'];
	}
	if ( 'elementtest_trusted_proxy_cidrs' === $hook ) {
		return $GLOBALS['__et_trusted_cidrs'];
	}
	return $value;
}

function sanitize_text_field( $str ) {
	$str = (string) $str;
	$str = preg_replace( '/[\r\n\t]+/', ' ', $str );
	return trim( $str );
}

function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function is_user_logged_in() {
	return false;
}

require __DIR__ . '/../includes/class-visitor.php';

// --- Tiny assertion helpers ---------------------------------------------

$failures = 0;
$count    = 0;

function check( $label, $expected, $actual ) {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  PASS: {$label}\n";
	} else {
		$failures++;
		echo "  FAIL: {$label}\n";
		echo "        expected: " . var_export( $expected, true ) . "\n";
		echo "        actual:   " . var_export( $actual, true ) . "\n";
	}
}

/**
 * Drive get_visitor_ip() under a given environment.
 *
 * @param array $server  $_SERVER overrides.
 * @param array $headers Trusted forwarding headers.
 * @param array $cidrs   Trusted proxy CIDRs.
 * @return string
 */
function resolve_ip( array $server, array $headers, array $cidrs ) {
	$_SERVER = array();
	foreach ( $server as $k => $v ) {
		$_SERVER[ $k ] = $v;
	}
	$GLOBALS['__et_trusted_headers'] = $headers;
	$GLOBALS['__et_trusted_cidrs']   = $cidrs;

	return ElementTest_Visitor::get_visitor_ip();
}

echo "Trusted-proxy IP resolution (issue #52)\n";

// 1. The core attack: untrusted direct client spoofs X-Forwarded-For.
//    REMOTE_ADDR is a public attacker IP, NOT in any trusted CIDR -> header ignored.
check(
	'spoofed XFF ignored when REMOTE_ADDR is not a trusted proxy',
	'203.0.113.7',
	resolve_ip(
		array( 'REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8' ),
		array( 'HTTP_X_FORWARDED_FOR' ),
		array( '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16' )
	)
);

// 2. Two different spoofed headers from the same untrusted client resolve to
//    the SAME IP (REMOTE_ADDR) -> dedupe/rate-limit can no longer be bypassed.
$first  = resolve_ip(
	array( 'REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8' ),
	array( 'HTTP_X_FORWARDED_FOR' ),
	array( '10.0.0.0/8' )
);
$second = resolve_ip(
	array( 'REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_FOR' => '1.1.1.1' ),
	array( 'HTTP_X_FORWARDED_FOR' ),
	array( '10.0.0.0/8' )
);
check( 'spoofed header rotation no longer changes resolved IP', true, $first === $second );

// 3. Legitimate proxy: REMOTE_ADDR IS in a trusted CIDR -> header honored.
check(
	'forwarded header honored when REMOTE_ADDR is a trusted proxy (IPv4)',
	'8.8.8.8',
	resolve_ip(
		array( 'REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8' ),
		array( 'HTTP_X_FORWARDED_FOR' ),
		array( '10.0.0.0/8' )
	)
);

// 4. XFF chain: left-most (original client) is taken when proxy is trusted.
check(
	'left-most XFF entry used for trusted proxy',
	'8.8.8.8',
	resolve_ip(
		array( 'REMOTE_ADDR' => '192.168.1.1', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 192.168.1.1' ),
		array( 'HTTP_X_FORWARDED_FOR' ),
		array( '192.168.0.0/16' )
	)
);

// 5. No trusted CIDRs configured (e.g. 'custom' preset with no filter) ->
//    header ignored even though the header list is non-empty.
check(
	'header ignored when no trusted CIDRs are configured',
	'198.51.100.9',
	resolve_ip(
		array( 'REMOTE_ADDR' => '198.51.100.9', 'HTTP_X_CUSTOM_IP' => '8.8.8.8' ),
		array( 'HTTP_X_CUSTOM_IP' ),
		array()
	)
);

// 6. IPv6 proxy in a trusted IPv6 CIDR honors the header.
check(
	'forwarded header honored for trusted IPv6 proxy',
	'8.8.4.4',
	resolve_ip(
		array( 'REMOTE_ADDR' => '2606:4700:0:0:0:0:0:1', 'HTTP_CF_CONNECTING_IP' => '8.8.4.4' ),
		array( 'HTTP_CF_CONNECTING_IP' ),
		array( '2606:4700::/32' )
	)
);

// 7. Cross-family safety: IPv4 REMOTE_ADDR must not match an IPv6 CIDR.
check(
	'IPv4 client does not match an IPv6 trusted CIDR',
	'203.0.113.7',
	resolve_ip(
		array( 'REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8' ),
		array( 'HTTP_X_FORWARDED_FOR' ),
		array( '2606:4700::/32' )
	)
);

// 8. Malformed REMOTE_ADDR falls back to 0.0.0.0 (no fatal, no header trust).
check(
	'malformed REMOTE_ADDR falls back to 0.0.0.0',
	'0.0.0.0',
	resolve_ip(
		array( 'REMOTE_ADDR' => 'not-an-ip', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8' ),
		array( 'HTTP_X_FORWARDED_FOR' ),
		array( '10.0.0.0/8' )
	)
);

// 9. Bare-IP (no slash) trusted entry works as a single host.
check(
	'bare-IP trusted entry matches exactly',
	'8.8.8.8',
	resolve_ip(
		array( 'REMOTE_ADDR' => '198.51.100.50', 'HTTP_X_REAL_IP' => '8.8.8.8' ),
		array( 'HTTP_X_REAL_IP' ),
		array( '198.51.100.50' )
	)
);

// 10. Boundary: an IP just outside the /24 is rejected.
check(
	'IP outside trusted /24 is not treated as a proxy',
	'203.0.114.1',
	resolve_ip(
		array( 'REMOTE_ADDR' => '203.0.114.1', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8' ),
		array( 'HTTP_X_FORWARDED_FOR' ),
		array( '203.0.113.0/24' )
	)
);

echo "\n{$count} checks, {$failures} failure(s)\n";
exit( $failures > 0 ? 1 : 0 );
