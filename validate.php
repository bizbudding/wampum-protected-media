<?php

if ( ! isset( $_GET['name'] ) ) {
	die();
}
header( 'Content-Type: application/pdf' );
header( 'Content-Length: ' . filesize( $filename ) );
header( 'Content-Disposition: attachment;filename="' . $filename . '"' );
readfile( $filename );
// $file = @ fopen( $filename, 'rb' );
// if ( $file ) {
// 	fpassthru( $file );
// 	exit;
// }
