<?php
require_once __DIR__ . '/bootstrap.php';

use BgcwPro\Pdf\Loader;

bgcwp_assert_eq( true, Loader::load(), 'dompdf autoloader loads' );
bgcwp_assert( class_exists( '\Dompdf\Dompdf' ), 'Dompdf class available' );
bgcwp_assert( class_exists( '\Dompdf\Options' ), 'Dompdf Options class available' );
foreach ( [ 'Onest-Regular', 'Onest-Bold', 'Onest-ExtraBold' ] as $font ) {
	bgcwp_assert( filesize( BGCW_PRO_PATH . 'assets/fonts/' . $font . '.ttf' ) > 10000, "font bundled: {$font}" );
}
