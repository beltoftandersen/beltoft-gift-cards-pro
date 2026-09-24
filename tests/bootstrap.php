<?php
/**
 * Minimal test harness. Run test files with `tests/run.sh`.
 * Each test file: require_once __DIR__ . '/bootstrap.php'; then assertions.
 */

if ( ! defined( 'WP_CLI' ) ) {
	echo "Run via wp eval-file inside the wp_app container.\n";
	exit( 1 );
}

// Never send real email from tests. This site delivers through SES, so every WooCommerce
// order email or gift card email triggered by a test would reach a real mailbox.
$GLOBALS['bgcwp_test_mail_blocked'] = 0;
// Mailer plugins (e.g. the SES mailer) hook pre_wp_mail themselves and may ignore an earlier
// short-circuit, so detach every existing handler before installing the block.
remove_all_filters( 'pre_wp_mail' );
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	$GLOBALS['bgcwp_test_mail_blocked']++;
	$GLOBALS['bgcwp_test_last_mail'] = $atts;
	return true;
}, 0, 2 );
add_filter( 'woocommerce_defer_transactional_emails', '__return_false', 999 );

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
