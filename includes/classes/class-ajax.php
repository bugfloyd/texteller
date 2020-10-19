<?php

namespace Texteller;


class Ajax {

	public static function init()
	{
		if ( ! wp_doing_ajax() ) {
			return;
		}

		// Member verification
		$verify_number = new Verify_Number();
		add_action( 'wp_ajax_tlr_verify_number', [ $verify_number, 'member_validation_listener' ] );
		add_action( 'wp_ajax_nopriv_tlr_verify_number', [ $verify_number, 'member_validation_listener' ] );

		add_action( 'wp_ajax_tlr_init_verification', [ $verify_number, 'init_verification_listener' ] );
		add_action( 'wp_ajax_nopriv_tlr_init_verification', [ $verify_number, 'init_verification_listener' ] );
	}


}