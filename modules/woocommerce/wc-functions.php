<?php
if ( ! function_exists( 'wc_create_new_customer' ) ) {

	/**
	 * Create a new customer.
	 *
	 * @see \wc_create_new_customer()
	 *
	 * @param  string $email    Customer email.
	 * @param  string $username Customer username.
	 * @param  string $password Customer password.
	 * @param  array  $args     List of arguments to pass to `wp_insert_user()`.
	 *
	 * @return int|WP_Error Returns WP_Error on failure, Int (user ID) on success.
	 */
	function wc_create_new_customer( $email, $username = '', $password = '', $args = array() )
	{
		$form_fields = get_option('tlr_wc_registration_form_fields');
		$enabled_email = isset( $form_fields['email']['enabled'] ) && 'yes' === $form_fields['email']['enabled'];
		$required_email = isset($form_fields['email']['required']) && 1 === $form_fields['email']['required'];

		// If Email field is enabled
		if ( $enabled_email ) {

			// If Email is a required field and the value is empty or not an email address
			if ( $required_email ) {
				if ( empty( $email ) || !is_email( $email ) ) {
					return new WP_Error( 'registration-error-invalid-email', __( 'Please provide a valid email address.', 'woocommerce' ) );
				}
			}

			// If email is an optional field
			else {
				if ( !empty( $email ) && ! is_email( $email ) ) {
					return new WP_Error( 'registration-error-invalid-email', __( 'Please provide a valid email address.', 'woocommerce' ) );
				}
			}
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( 'registration-error-email-exists', apply_filters( 'woocommerce_registration_error_email_exists', __( 'An account is already registered with your email address. <a href="#" class="showlogin">Please log in.</a>', 'woocommerce' ), $email ) );
		}

		$registration_module = \Texteller\Registration_Module::get_instance();

		if ( 'yes' === get_option( 'woocommerce_registration_generate_username', 'yes' ) && empty( $username ) ) {

			$username_generator = get_option('tlr_wc_registration_username_generator', 'wc_default');

			if ( 'wc_default' === $username_generator ) {
				$email = $registration_module->get_member_field_value('email' );

				$args['first_name'] = !empty( $_POST['billing_first_name'] ) ? $_POST['billing_first_name'] : null;
				$args['last_name'] = !empty( $_POST['billing_last_name'] ) ? $_POST['billing_last_name'] : null;

				if ( !$email && empty($args['first_name']) && empty($args['last_name']) ) {
					$username = $registration_module->generate_username( 'rand_numbers' );
				} else {
					$username = wc_create_new_customer_username( $email, $args );
					$username = sanitize_user( $username );
				}
			} else {
				$username = $registration_module->generate_username( $username_generator, 'tlr_wc_registration_username_generator' );
			}
		}

		if ( empty( $username ) || ! validate_username( $username ) ) {
			return new WP_Error( 'registration-error-invalid-username', __( 'Please enter a valid account username.', 'woocommerce' ) );
		}

		if ( username_exists( $username ) ) {
			return new WP_Error( 'registration-error-username-exists', __( 'An account is already registered with that username. Please choose another.', 'woocommerce' ) );
		}

		// Handle password creation.
		$password_generated = false;

		if ( 'yes' === get_option( 'woocommerce_registration_generate_password' ) && empty( $password ) ) {
			$password           = wp_generate_password();
			$password_generated = true;
		}

		if ( empty( $password ) ) {
			return new WP_Error( 'registration-error-missing-password', __( 'Please enter an account password.', 'woocommerce' ) );
		}

		// Use WP_Error to handle registration errors.
		$errors = new WP_Error();

		do_action( 'woocommerce_register_post', $username, $email, $errors );

		$errors = apply_filters( 'woocommerce_registration_errors', $errors, $username, $email );

		if ( $errors->get_error_code() ) {
			return $errors;
		}

		$new_customer_data = apply_filters(
			'woocommerce_new_customer_data',
			array_merge(
				$args,
				array(
					'user_login' => $username,
					'user_pass'  => $password,
					'user_email' => $email,
					'role'       => 'customer',
				)
			)
		);

		$customer_id = wp_insert_user( $new_customer_data );

		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}

		do_action( 'woocommerce_created_customer', $customer_id, $new_customer_data, $password_generated );

		return $customer_id;
	}
}