<?php

namespace Texteller\Traits;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

trait Encrypted_Options {

	public static function update_encrypted_option( $value, $old_value )
	{
		if ( empty($value) ) {
			return $value;
		}
		if ( 'TLR_STORED_TOKEN' === $value ) {
			return $old_value;
		} else {
			return TLR\tlr_encrypt( $value );
		}
	}

	private static function get_encrypted_option( $option )
	{
		return TLR\tlr_decrypt( get_option($option, '') );
	}
}