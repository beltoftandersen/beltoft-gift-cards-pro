<?php

namespace BgcwPro\Pdf;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the bundled dompdf library on demand.
 */
class Loader {

	/**
	 * Require the dompdf autoloader once.
	 *
	 * @return bool True when \Dompdf\Dompdf is available.
	 */
	public static function load(): bool {
		static $loaded = null;

		if ( null !== $loaded ) {
			return $loaded;
		}

		if ( class_exists( '\Dompdf\Dompdf' ) ) {
			$loaded = true;
			return true;
		}

		$file = BGCW_PRO_PATH . 'lib/dompdf/autoload.inc.php';
		if ( ! file_exists( $file ) ) {
			$loaded = false;
			return false;
		}

		require_once $file;
		$loaded = class_exists( '\Dompdf\Dompdf' );

		return $loaded;
	}
}
