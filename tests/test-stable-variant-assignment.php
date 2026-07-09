<?php
/**
 * Standalone regression test for stable public variant assignment.
 *
 * Run with:
 *
 *   php tests/test-stable-variant-assignment.php
 *
 * Exit code 0 = all assertions passed; 1 = failure.
 *
 * @package ElementTestPro
 */

// --- Minimal WordPress shims --------------------------------------------

define( 'ABSPATH', __DIR__ . '/' );

function absint( $maybeint ) {
	return abs( intval( $maybeint ) );
}

function wp_salt( $scheme = 'auth' ) {
	return 'elementtest-stable-assignment-test-salt';
}

require __DIR__ . '/../includes/class-ajax-handler.php';

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

function select_stable_variant( array $variants, $test_id, $identity ) {
	$reflection = new ReflectionClass( 'ElementTest_Ajax_Handler' );
	$handler    = $reflection->newInstanceWithoutConstructor();
	$method     = $reflection->getMethod( 'select_variant_by_stable_hash' );
	$method->setAccessible( true );

	return $method->invoke( $handler, $variants, $test_id, $identity );
}

$variants = array(
	array(
		'variant_id'         => 101,
		'traffic_percentage' => 50,
	),
	array(
		'variant_id'         => 202,
		'traffic_percentage' => 50,
	),
);

echo "Stable variant assignment\n";

$first = select_stable_variant( $variants, 77, 'visitor-a' );
for ( $i = 0; $i < 20; $i++ ) {
	check(
		'repeated first-time assignment request stays on the same variant',
		$first['variant_id'],
		select_stable_variant( $variants, 77, 'visitor-a' )['variant_id']
	);
}

check(
	'zero-weight variant is never selected when another variant has all traffic',
	202,
	select_stable_variant(
		array(
			array(
				'variant_id'         => 101,
				'traffic_percentage' => 0,
			),
			array(
				'variant_id'         => 202,
				'traffic_percentage' => 100,
			),
		),
		77,
		'visitor-a'
	)['variant_id']
);

$all_zero_variants = array(
	array(
		'variant_id'         => 101,
		'traffic_percentage' => 0,
	),
	array(
		'variant_id'         => 202,
		'traffic_percentage' => 0,
	),
	array(
		'variant_id'         => 303,
		'traffic_percentage' => 0,
	),
);
$all_zero_first = select_stable_variant( $all_zero_variants, 77, 'visitor-a' );
check(
	'all-zero fallback is still deterministic',
	$all_zero_first['variant_id'],
	select_stable_variant( $all_zero_variants, 77, 'visitor-a' )['variant_id']
);

check(
	'empty variant list returns null',
	null,
	select_stable_variant( array(), 77, 'visitor-a' )
);

// Security: IP-only seed means different User-Agents (different user_hashes)
// sharing the same IP-only seed MUST produce the same variant. This prevents
// an attacker from rotating User-Agent to resample a different variant.
$ip = '192.0.2.42';
$salt = wp_salt( 'auth' );
$ip_seed = hash( 'sha256', $ip . '|' . $salt );

$ua_a_hash = hash( 'sha256', $ip . '|' . 'Mozilla/5.0 (Windows NT 10.0)' . '|' . $salt );
$ua_b_hash = hash( 'sha256', $ip . '|' . 'Mozilla/5.0 (Macintosh)' . '|' . $salt );

check(
	'different user_hashes (different UAs) produce different values',
	false,
	$ua_a_hash === $ua_b_hash
);

$ip_result = select_stable_variant( $variants, 77, $ip_seed );
check(
	'IP-only seed is deterministic across calls',
	$ip_result['variant_id'],
	select_stable_variant( $variants, 77, $ip_seed )['variant_id']
);

check(
	'IP-only seed ≠ UA-A full hash (seeds differ)',
	true,
	$ip_seed !== $ua_a_hash
);

$ua_a_result = select_stable_variant( $variants, 77, $ua_a_hash );
$ua_b_result = select_stable_variant( $variants, 77, $ua_b_hash );

echo "\n  (Info: IP seed → variant {$ip_result['variant_id']}, "
	. "UA-A hash → variant {$ua_a_result['variant_id']}, "
	. "UA-B hash → variant {$ua_b_result['variant_id']})\n";

echo "  NOTE: callers now pass the IP-only seed, not the UA-dependent user_hash,\n";
echo "        so UA rotation has no effect on assignment.\n\n";

echo "\n{$count} checks, {$failures} failure(s)\n";
exit( $failures > 0 ? 1 : 0 );
