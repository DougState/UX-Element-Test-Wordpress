<?php
/**
 * Standalone regression test for assignment proof lifetime.
 *
 * Run with:
 *
 *   php tests/test-assignment-proof-ttl.php
 *
 * Exit code 0 = all assertions passed; 1 = failure.
 *
 * @package ElementTestPro
 */

// --- Minimal WordPress shims --------------------------------------------

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['__et_settings'] = array();
$GLOBALS['__et_filter']   = null;

function get_option( $name, $default = false ) {
	if ( 'elementtest_settings' === $name ) {
		return $GLOBALS['__et_settings'];
	}

	return $default;
}

function absint( $maybeint ) {
	return abs( intval( $maybeint ) );
}

function apply_filters( $hook, $value ) {
	if ( 'elementtest_assignment_token_ttl' === $hook && null !== $GLOBALS['__et_filter'] ) {
		return call_user_func( $GLOBALS['__et_filter'], $value );
	}

	return $value;
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

function assignment_token_ttl( array $settings, $filter = null ) {
	$GLOBALS['__et_settings'] = $settings;
	$GLOBALS['__et_filter']   = $filter;

	$reflection = new ReflectionClass( 'ElementTest_Ajax_Handler' );
	$handler    = $reflection->newInstanceWithoutConstructor();
	$method     = $reflection->getMethod( 'get_assignment_token_ttl' );
	$method->setAccessible( true );

	return $method->invoke( $handler );
}

echo "Assignment proof TTL\n";

check(
	'default setting matches 30-day assignment cookie',
	30 * DAY_IN_SECONDS,
	assignment_token_ttl( array() )
);

check(
	'configured cookie_days controls proof lifetime',
	45 * DAY_IN_SECONDS,
	assignment_token_ttl( array( 'cookie_days' => 45 ) )
);

check(
	'zero-day setting falls back to 30 days',
	30 * DAY_IN_SECONDS,
	assignment_token_ttl( array( 'cookie_days' => 0 ) )
);

check(
	'cookie_days is capped to the settings maximum',
	365 * DAY_IN_SECONDS,
	assignment_token_ttl( array( 'cookie_days' => 999 ) )
);

check(
	'positive filter override is preserved',
	2 * DAY_IN_SECONDS,
	assignment_token_ttl(
		array( 'cookie_days' => 30 ),
		function( $default_ttl ) {
			return 2 * DAY_IN_SECONDS;
		}
	)
);

check(
	'non-positive filter override falls back to assignment window',
	30 * DAY_IN_SECONDS,
	assignment_token_ttl(
		array( 'cookie_days' => 30 ),
		function( $default_ttl ) {
			return 0;
		}
	)
);

echo "\n{$count} checks, {$failures} failure(s)\n";
exit( $failures > 0 ? 1 : 0 );
