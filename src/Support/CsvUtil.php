<?php

namespace BgcwPro\Support;

defined( 'ABSPATH' ) || exit;

class CsvUtil {

	/**
	 * Prefix potentially dangerous spreadsheet formulas to prevent CSV injection.
	 *
	 * @param mixed $value CSV cell value.
	 * @return string
	 */
	public static function escape_cell( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return $value;
		}

		$trimmed = ltrim( $value );
		if ( '' === $trimmed ) {
			return $value;
		}

		$first = $trimmed[0];
		if ( in_array( $first, [ '=', '+', '-', '@' ], true ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
