<?php

namespace Texteller\Core_Modules\Newsletter;

defined( 'ABSPATH' ) || exit;

class CSS_Generator {

	private $dynamic_styles = [];
	private $dynamic_raw_styles = '';
	private $static = '';
	private $dynamic = '';
	private $final_css = '';

	public function __construct()
	{
		$this->init_hooks();
	}

	private function init_hooks()
	{
		add_action('update_option_tlr_nl_form_design', [ $this, 'generate_css'], 10, 3 );
		add_action('update_option_tlr_nl_form_fields', [ $this, 'generate_css'], 10, 3 );
		add_action('update_option_tlr_nl_form_breakpoint', [ $this, 'generate_css'], 10, 3 );
	}

	public function generate_css( $old_value, $options , $option_name )
	{
		$generator_method = 'generate_' . $option_name;
		if( method_exists( $this, $generator_method ) ) {
			$this->$generator_method( $options );
			$this->add_other_style_options( $option_name );
		} else {
			return;
		}

		$this->read_static_styles();
		$this->generate_dynamic_css();
		$this->generate_final_css();
		$this->write_final_css();
	}

	private function add_other_style_options( $current_option )
	{
		$options = [
			'tlr_nl_form_design', 'tlr_nl_form_fields', 'tlr_nl_form_breakpoint'
		];

		if( ! in_array( $current_option, $options ) ) {
			return;
		}

		unset( $options[ array_search($current_option, $options, true ) ] );

		foreach ( $options as $option ) {
			$generator_method = 'generate_' . $option;
			if( method_exists( $this, $generator_method ) ) {
				$option_styles = get_option( $option );
				$this->$generator_method( $option_styles );
			}
		}
	}

	private function generate_tlr_nl_form_design( $options )
	{
		$design_styles = [
			'#tlr-nl-form'  =>  [
				'background-color'  =>  $options['form-bg-color'],
				'border-width'      =>  "{$options['form-border-width']}px",
				'border-radius'     =>  "{$options['form-border-radius']}px",
				'border-color'      =>  $options['form-border-color'],
				'border-style'      =>  'solid'
			],
			'input.tlr-input, select.tlr-input'  =>  [
				'max-height'            =>  '3.2em',
				'height'                =>  '3em',
				'vertical-align'        =>  'middle',
				'display'               =>  'inline-block',
				'font-size'             =>  "{$options['label-font-size']}px",
				'border-width'          =>  "{$options['input-border-width']}px",
				'border-radius'         =>  "{$options['input-border-radius']}px",
				'border-color'          =>  $options['input-border-color'],
				'border-style'          =>  'solid',
				'background-color'      =>  $options['input-bg-color'],
				'color'                 =>  $options['text-color'],
				'background-size'       =>  '25px',
				'background-repeat'     =>  'no-repeat',
				'background-position'   =>  'left 5px top 50%',
				'padding'               =>  '5px 15px 5px 35px',
				'width'                 =>  "calc( 100% - ( 2 * {$options['input-border-width']}px ) )"
			],
			'input.tlr-input:focus, select.tlr-input:focus'  =>  [
				'border-color'      =>  "{$options['input-focus-border-color']}!important",
				'background-color'  =>  $options['input-focus-bg-color']
			],
			'input.tlr-input::placeholder, select.tlr-input::placeholder'  =>  [
				'color'  =>  $options['label-color']
			],
			'.tlr-nl-form-title'  =>  [
				'font-size'     =>  "{$options['title-font-size']}px",
				'color'         =>  $options['title-color'],
				'font-weight'   =>  'bold',
				'width'         =>  '100%'
			],
			'.tlr-nl-desc'  =>  [
				'font-size' =>  "{$options['desc-font-size']}px",
				'color'     =>  $options['desc-color'],
				'width'     =>  '100%',
			],
			'#tlr-nl-form .tlr-submit'  =>  [
				'margin'        =>  '0 auto',
				'text-align'    =>  'center',
				'display'       =>  'block',
				'padding'       =>  "{$options['submit-padding']}px",
				'width'         =>  "calc({$options['submit-width']}% - 10px)",
				'font-size'     =>  "{$options['submit-font-size']}px",
				'background-color'  =>  $options['submit-bg-color'],
				'border-style'      =>  'solid',
				'border-width'      =>  $options['submit-border-width'] ? "{$options['submit-border-width']}px" : '0',
				'border-color'      =>  $options['submit-border-color'],
				'border-radius'     =>  $options['submit-border-radius'] ? "{$options['submit-border-radius']}px" : '0',
				'color'             =>  $options['submit-color']
			],
			'.tlr-input.tlr-valid'  =>  [
				'border-color' =>  $options['input-valid-border-color']
			],
			'.tlr-input.tlr-invalid'  =>  [
				'border-color' =>  $options['input-invalid-border-color']
			],
			'#tlr-nl-form .tlr-submit:hover'  =>  [
				'background-color'  =>  $options['submit-hover-bg-color'],
				'border-color'      =>  $options['submit-hover-border-color'],
				'color'             =>  $options['submit-hover-color']
			],
			'.tlr-results-wrapper'  =>  [
				'background-color'  =>  $options['results-bg-color'],
				'padding'           =>  "{$options['results-padding']}px",
				'border-radius'     =>  "{$options['results-border-radius']}px",
			],
			'.tlr-result-text'   =>  [
				'text-align'    =>  'center',
				'display'       =>  'block',
				'margin-bottom' =>  '20px',
				'font-size'     =>  "{$options['results-text-size']}px",
				'color'         =>  $options['results-text-color'],
			],
			'.tlr-verification-result-text'  =>  [
				'font-size'     =>  "{$options['results-text-size']}px",
				'color'         =>  $options['results-text-color'],
				'display'       =>  'block'
			],
			'.tlr-overlay'  =>  [
				'background-color'  =>  $options['overlay-bg-color'],
				'border-radius'     =>  "{$options['form-border-radius']}px"
			]
		];
		$this->add_styles( $design_styles );
	}

	private function generate_tlr_nl_form_fields( $options )
	{
		foreach ($options as $id => $data) {
			$field_name = str_replace('_', '-', $id);

			if ( isset($data['size']) ) {
				if ($data['size'] == 'full') {
					$this->add_selector_style(
						".tlr-$field_name-wrap",
						'width',
						'100%'
					);
				} elseif ($data['size'] == 'half') {
					$this->add_selector_style(
						".tlr-$field_name-wrap",
						'width',
						'50%'
					);
				}
			}

			$image_name = ($id == 'first_name' || $id == 'last_name') ? 'member' : $id;
			$this->add_selector_style(
				"#tlr-nl-form .tlr-$field_name-field",
				'background-image',
				'url(' . TLR_ASSETS_URI . "/images/tlr_$image_name.png)"
			);
		}
	}

	private function generate_tlr_nl_form_breakpoint( $options )
	{
		$this->dynamic_raw_styles = "@media screen and ( max-width: {$options}px ){.tlr-input-wrap{width:calc(100% - 4px);}}";
	}

	public function add_selector_style( $selector, $property_name, $property_value )
	{
		$this->dynamic_styles[$selector][$property_name] = $property_value;
	}

	public function add_selector_styles( $selector, $styles )
	{
		$this->dynamic_styles[$selector] = $styles;
	}

	public function add_styles( array $styles_array )
	{
		foreach ( $styles_array as $selector => $styles ) {
			$this->add_selector_styles( $selector, $styles );
		}
	}

	private function generate_dynamic_css()
	{
		$dynamic_styles = $this->get_dynamic_styles();

		$css = '';
		foreach ( $dynamic_styles as $selector => $styles ) {
			$css .= $selector . '{' ;
			foreach ( $styles as $property_name => $property_value ) {
				$css .= $property_name . ':' . $property_value . ';';
			}
			$css .= '}';
		}

		$this->set_dynamic( $css );
	}

	private function read_static_styles()
	{
		$static_css_path = TLR_ASSETS_PATH . '/newsletter/tlr-newsletter-static.css';

		if ( file_exists( $static_css_path ) ) {
			$this->set_static( file_get_contents($static_css_path) );
		}
	}

	private function generate_final_css()
	{
		$static = $this->get_static();
		$dynamic = $this->get_dynamic();
		$notice = "/**  This file is auto-generated, do not edit it or your modifications would be removed when saving plugin options. **/" . "\n";
		$this->set_final_css( $notice . $static . $dynamic . $this->dynamic_raw_styles );
	}

	/**
	 *
	 * @global \WP_Filesystem_Base $wp_filesystem
	 *
	 * @return bool|\WP_Error
	 */
	private function write_final_css()
	{
		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		/** @var \WP_Filesystem_Base $wp_filesystem */
		global $wp_filesystem;

		$css = $this->get_final_css();
		$url = wp_nonce_url(
			'admin.php?page=tlr-options&tab=tlr_newsletter&section=tlr_nl_design',
			'texteller-options'
		);
		$credentials = request_filesystem_credentials( $url );
		$upload_dir = wp_upload_dir();
		$dir = trailingslashit( $upload_dir['basedir'] ) . 'texteller';

		if ( false === $credentials ) {
			return false;
		}
		if ( ! WP_Filesystem( $credentials ) ) {
			request_filesystem_credentials( $url, '', true );
			return false;
		}
		if ( ! is_object( $wp_filesystem ) ) {
			return new \WP_Error( 'fs_unavailable', __( 'Could not access filesystem.' ) );
		}
		if ( is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
			return new \WP_Error( 'fs_error', __( 'Filesystem error.' ), $wp_filesystem->errors );
		}

		// Make directories if they don't exist
		if( !$wp_filesystem->is_dir($dir) ) {
			$wp_filesystem->mkdir( $dir );
		}
		if( !$wp_filesystem->is_dir( $dir . '/newsletter' ) ) {
			$wp_filesystem->mkdir( $dir . '/newsletter' );
		}

		// Write the file
		$wp_filesystem->put_contents( $dir . '/newsletter/tlr-newsletter.css', $css, FS_CHMOD_FILE );

		return true;
	}

	/**
	 * @return array
	 */
	public function get_dynamic_styles(): array
	{
		return $this->dynamic_styles;
	}

	/**
	 * @param array $dynamic_styles
	 */
	public function set_dynamic_styles( array $dynamic_styles )
	{
		$this->dynamic_styles = $dynamic_styles;
	}

	/**
	 * @return string
	 */
	public function get_static(): string
	{
		return $this->static;
	}

	/**
	 * @param string $static
	 */
	public function set_static( string $static )
	{
		$this->static = $static;
	}

	/**
	 * @return string
	 */
	public function get_dynamic(): string
	{
		return $this->dynamic;
	}

	/**
	 * @param string $dynamic
	 */
	public function set_dynamic( string $dynamic )
	{
		$this->dynamic = $dynamic;
	}

	/**
	 * @return string
	 */
	public function get_final_css(): string {
		return $this->final_css;
	}

	/**
	 * @param string $final_css
	 */
	public function set_final_css( string $final_css )
	{
		$this->final_css = $final_css;
	}
}