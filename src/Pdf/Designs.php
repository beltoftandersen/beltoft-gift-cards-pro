<?php

namespace BgcwPro\Pdf;

use BgcwPro\Support\Options;

defined( 'ABSPATH' ) || exit;

/**
 * The three PDF card designs and their palettes.
 */
class Designs {

	const DEFAULT_SLUG = 'classic';

	/**
	 * Built-in designs.
	 *
	 * color  - main panel color
	 * accent - secondary color (code box border, stripe, confetti)
	 * bg     - light tint used behind the code
	 * motif  - decorative treatment key used by the template
	 *
	 * @return array<string,array>
	 */
	private static function builtin(): array {
		return [
			'classic'     => [
				'name'    => __( 'Classic', 'beltoft-gift-cards-pro' ),
				'heading' => __( 'A gift for you', 'beltoft-gift-cards-pro' ),
				'color'   => '#16213E',
				'accent'  => '#C9A227',
				'bg'      => '#F3F1EC',
				'motif'   => 'frame',
			],
			'birthday'    => [
				'name'    => __( 'Birthday', 'beltoft-gift-cards-pro' ),
				'heading' => __( 'Happy birthday', 'beltoft-gift-cards-pro' ),
				'color'   => '#E4577B',
				'accent'  => '#F5C451',
				'bg'      => '#FDF0F3',
				'motif'   => 'confetti',
			],
			'celebration' => [
				'name'    => __( 'Celebration', 'beltoft-gift-cards-pro' ),
				'heading' => __( 'Congratulations', 'beltoft-gift-cards-pro' ),
				'color'   => '#C2571A',
				'accent'  => '#FFE8B0',
				'bg'      => '#FBF1E8',
				'motif'   => 'ribbon',
			],
		];
	}

	/**
	 * Designs with admin overrides applied.
	 *
	 * @return array<string,array>
	 */
	public static function get(): array {
		$designs = self::builtin();

		foreach ( $designs as $slug => &$design ) {
			$heading = Options::get( 'pdf_heading_' . $slug );
			if ( is_string( $heading ) && '' !== trim( $heading ) ) {
				$design['heading'] = $heading;
			}
			$color = Options::get( 'pdf_color_' . $slug );
			if ( is_string( $color ) && sanitize_hex_color( $color ) ) {
				$design['color'] = $color;
			}
		}
		unset( $design );

		/**
		 * Filter the available PDF card designs.
		 *
		 * @param array $designs Designs keyed by slug.
		 */
		return apply_filters( 'bgcw_pro_pdf_designs', $designs );
	}

	/**
	 * Map any slug (including removed email themes) to a valid design slug.
	 *
	 * @param mixed $slug Raw slug.
	 * @return string
	 */
	public static function normalize( $slug ): string {
		$slug    = is_string( $slug ) ? sanitize_key( $slug ) : '';
		$designs = self::get();

		if ( isset( $designs[ $slug ] ) ) {
			return $slug;
		}
		if ( isset( $designs[ self::DEFAULT_SLUG ] ) ) {
			return self::DEFAULT_SLUG;
		}

		$keys = array_keys( $designs );

		return $keys ? (string) $keys[0] : self::DEFAULT_SLUG;
	}

	/**
	 * Built-in (unfiltered, non-overridden) value for a design key, e.g. the default color.
	 *
	 * @return string Empty when unknown.
	 */
	public static function builtin_value( string $slug, string $key ): string {
		$builtin = self::builtin();

		return isset( $builtin[ $slug ][ $key ] ) ? (string) $builtin[ $slug ][ $key ] : '';
	}

	/**
	 * Current design version. Bumped whenever admin changes a PDF design setting.
	 */
	public static function version(): int {
		return max( 1, (int) Options::get( 'pdf_design_version' ) );
	}
}
