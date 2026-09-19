<?php
/**
 * Minimal test harness. Run test files with `tests/run.sh`.
 * Each test file: require_once __DIR__ . '/bootstrap.php'; then assertions.
 */

if ( ! defined( 'WP_CLI' ) ) {
	echo "Run via wp eval-file inside the wp_app container.\n";
	exit( 1 );
}

$GLOBALS['bgcwp_test_failures'] = 0;
$GLOBALS['bgcwp_test_passes']   = 0;
$GLOBALS['bgcwp_test_cleanups'] = [];

function bgcwp_assert( $cond, $msg ) {
	if ( $cond ) {
		$GLOBALS['bgcwp_test_passes']++;
		echo "  ok   - {$msg}\n";
	} else {
		$GLOBALS['bgcwp_test_failures']++;
		echo "  FAIL - {$msg}\n";
	}
}

function bgcwp_assert_eq( $expected, $actual, $msg ) {
	$ok = ( $expected === $actual );
	if ( ! $ok ) {
		$msg .= ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')';
	}
	bgcwp_assert( $ok, $msg );
}

function bgcwp_test_admin_id() {
	$users = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
	return $users ? (int) $users[0] : 0;
}

/**
 * Register a cleanup callback that runs on shutdown, even after a recorded
 * FAIL. Prefer this over a test file's own register_shutdown_function() so
 * cleanup order is predictable and a failure in one callback cannot skip the
 * others.
 *
 * @param callable $fn Cleanup callback, called with no arguments.
 */
function bgcwp_test_register_cleanup( callable $fn ) {
	$GLOBALS['bgcwp_test_cleanups'][] = $fn;
}

register_shutdown_function(
	function () {
		foreach ( $GLOBALS['bgcwp_test_cleanups'] as $cleanup ) {
			try {
				$cleanup();
			} catch ( \Throwable $e ) {
				echo '  cleanup error - ' . $e->getMessage() . "\n";
			}
		}

		$f = $GLOBALS['bgcwp_test_failures'];
		$p = $GLOBALS['bgcwp_test_passes'];
		echo $f ? "RESULT: {$f} failed, {$p} passed\n" : "RESULT: all {$p} passed\n";
		if ( $f ) {
			exit( 1 );
		}
	}
);
