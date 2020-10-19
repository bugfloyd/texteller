<?php

namespace Texteller\Traits;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

trait Options_Base
{
	//protected function __construct() {}

	public static function register_section($section)
	{
		add_settings_section(
			$section['id'],
			$section['title'],
			function() use ( $section ) {
				self::section_callback( $section );
			},
			$section['page']
		);
	}

	public static function section_callback( $section )
	{ ?>
		<p <?php echo $section['class'] ? 'class="' . $section['class'] .'"' : '' ?>><?= $section['desc']; ?></p> <?php
	}

	public static function register_options( $options )
	{
		foreach ( $options as $option ) {
		    self::add_object_tags($option);
			self::register_option( $option );
		}
	}

	protected static function register_option( $option )
	{
		add_settings_field(
			$option['id'],
			$option['title'],
			[TLR\Admin\Option_Renderer::class, 'field_callback'],
			$option['page'],
			$option['section'],
			$option
		);

		$field_args = isset($option['field_args']) ? $option['field_args'] : [];
		register_setting( $option['section'], $option['id'], $field_args );

		if ( isset( $option['extra_options'] ) ) {
		    foreach ( $option['extra_options'] as $extra_option ) {
			    $field_args = isset($extra_option['field_args']) ? $extra_option['field_args'] : [];
			    register_setting( $extra_option['section'], $extra_option['id'], $field_args );
		        //self::register_option($extra_option);
            }
        }
	}

	protected static function get_the_tags() : TLR\Tags
	{
		$tags = new TLR\Tags();
		self::set_global_tags($tags);
		return $tags;
	}

	protected static function set_global_tags( TLR\Tags &$tags )
	{
		$global_tags = [
			'site_url'      =>  __('site URL', 'texteller'),
			'site_title'    =>  __('site title','texteller')
		];
		$tags->add_tag_type_data( 'global', $global_tags );
	}

	public static function get_base_tags_array( string $base_tags = 'member' ) : array
	{
		$member_tags = [];
		if ( 'member' === $base_tags ) {
			$member_tags = [
				'member_first_name'     =>  __('first name', 'texteller'),
				'member_last_name'      =>  __('last name', 'texteller'),
                'member_full_name'      =>  __('full name', 'texteller'),
				'member_reg_date'       =>  __('member registration date', 'texteller'),
				'member_title'          =>  __('title', 'texteller'),
				'member_member_group'   =>  __('member group', 'texteller'),
				'member_status'         =>  __('member status', 'texteller'),
				'member_email'          =>  __('email', 'texteller'),
				'member_mobile'         =>  __('mobile', 'texteller'),
				'member_id'             =>  __('member id', 'texteller'),
				'member_username'       =>  __('username', 'texteller'),
				'member_user_id'        =>  __('user id', 'texteller'),
			];
		}

		return $member_tags;
	}

	private static function add_object_tags( &$option )
    {
        if ( isset($option['params']['tag_type']) ) {
            /** @var TLR\Tags $tags */
	        $tags = self::get_option_tags();
	        $option['params']['tags'] = $tags->get_merged_tag_types_data(
	                [ $option['params']['tag_type'], 'global' ]
            );
        }
    }
}