<?php

namespace Texteller\Core_Modules\Newsletter;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

/**
 * Class Options Texteller Newsletter Options
 * @package Texteller\Core_Modules\Newsletter
 */
final class Options implements TLR\Interfaces\Options
{
	use TLR\Traits\Options_Base;

	/**
	 * Options constructor.
	 */
	public function __construct()
	{
		$this->init_option_hooks();
	}

	/**
	 * Initializes newsletter options and CSS generator
	 * @see \Texteller\Interfaces\Options::init_option_hooks()
	 */
	public function init_option_hooks()
	{
		add_filter( 'texteller_option_pages', [self::class, 'add_option_pages'], 15 );
		add_action( 'admin_init', [$this, 'register_module_options' ] );

		new CSS_Generator();
	}


	/**
	 * @see \Texteller\Interfaces\Options::add_option_tabs()
	 *
	 * @param array $tabs
	 *
	 * @return array
	 */
	public static function add_option_pages( array $tabs ) : array
	{
		$tabs[] = [
			'slug'  =>  'tlr_newsletter',
			'title' =>  __( 'Newsletter', 'texteller' ),
		];
		return $tabs;
	}

	/**
	 * @see \Texteller\Interfaces\Options::register_module_options()
	 */
	public function register_module_options()
	{
		self::register_sections();

		$this->register_registration_fields_options();
		$this->register_design_options();
		$this->register_behaviour_options();
		$this->register_notifications_options();
	}

	/**
	 * @see \Texteller\Interfaces\Options::register_sections()
	 */
	public static function register_sections()
	{
		self::register_section([
			'id'    =>  'tlr_nl_fields',
			'title' =>  _x( 'Registration form fields', 'Module settings tab title', 'texteller' ),
			'desc'  =>  __('Manage the newsletter registration form fields', 'texteller'),
			'class' =>  'description',
			'page' =>  'tlr_newsletter'
		]);

		self::register_section([
			'id'    =>  'tlr_nl_design',
			'title' =>  _x( 'Form Design', 'texteller' ),
			'desc'  =>  __("Style the newsletter form", 'texteller'),
			'class' =>  'description',
			'page' =>  'tlr_newsletter'
		]);

		self::register_section([
			'id'    =>  'tlr_nl_behaviour',
			'title' =>  _x( 'Registration behaviour', 'Module settings tab title', 'texteller' ),
			'desc'  =>  __( "Configure the registration process", 'texteller'),
			'class' =>  'description',
			'page' =>  'tlr_newsletter'
		]);


		self::register_section([
			'id'    =>  'tlr_nl_notifications',
			'title' =>  _x( 'Registration Notifications', 'Module settings tab title', 'texteller' ),
			'desc'  =>  __('Manage newsletter registration and login related notifications', 'texteller'),
			'class' =>  'description',
			'page'  =>  'tlr_newsletter'
		]);
	}

	/**
	 * Registers options in the fields section
	 */
	private function register_registration_fields_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_nl_form_fields',
				'title'         =>  __('Form Fields', 'texteller'),
				'page'          =>  'tlr_newsletter',
				'section'       =>  'tlr_nl_fields',
				'class'         =>  'registration-form-fields',
				'field_args'    =>  [ 'default' => self::get_default_fields() ],
				'desc'          =>  __( 'Each enabled field will appear on the form, in the chosen size.', 'texteller' ) . ' '
                                    . __( 'Re-ordering fields can be done by a simple drag and drop.', 'texteller' ) . ' '
                                    . __( 'Editing field labels is also possible via clicking on the title (e.g. First Name) and typing the intended label instead.', 'texteller'),
                'helper'        =>  __( 'In order to display the newsletter registration form, one can use Wordpress widgets or insert this shortcode:', 'texteller' ) . ' <code>tlr_newsletter</code>',
				'type'          =>  'registration_fields',
				'params'        =>  [
					'size_selection' => true
				]
			],
			[
				'id'            =>  'tlr_nl_form_labels',
				'title'         =>  __('Registration Form Labels', 'texteller'),
				'page'          =>  'tlr_newsletter',
				'section'       =>  'tlr_nl_fields',
				'field_args'    =>  [ 'default' =>  [
					'form_title'        =>  'Newsletter',
					'form_description'  =>  'Subscribe to our newsletter to receive the latest news and special offers',
					'submit_label'      =>  'SUBSCRIBE NOW!'
				] ],
				'type'          =>  [self::class, 'render_form_labels_option']
			]
		];
		self::register_options($fields);
	}

	/**
	 * Default registration form fields. Defines available fields to be displayed on the registration form
	 * User may override each field option
	 *
	 * @return array Registration form fields
	 */
	public static function get_default_fields()
	{
		return [
			'first_name'    =>  [
				'enabled'   =>  'yes',
				'id'        =>  'first_name',
				'title'     =>  __( 'First name', 'texteller' ),
				'size'      =>  'half',
				'required'  =>  0
			],
			'last_name' =>  [
				'enabled'   =>  'yes',
				'id'        =>  'last_name',
				'title'     =>  __( 'Last name', 'texteller' ),
				'size'      =>  'half',
				'required'  =>  0
			],
			'mobile'    =>  [
				'enabled'   =>  'yes',
				'id'        =>  'mobile',
				'title'     =>  __( 'Mobile number', 'texteller' ),
				'size'      =>  'half',
				'required'  =>  1
			],
			'email'     =>  [
				'enabled'   =>  'yes',
				'id'        =>  'email',
				'title'     =>  __( 'Email', 'texteller' ),
				'size'      =>  'half',
				'required'  =>  0
			],
			'title'    =>  [
				'enabled'   =>  '',
				'id'        =>  'title',
				'title'     =>  __( 'Title', 'texteller' ),
				'size'      =>  'half',
				'required'  =>  0
			],
			'member_group'  =>  [
				'enabled'   =>  '',
				'id'        =>  'member_group',
				'title'     =>  __( 'Member group', 'texteller' ),
				'size'      =>  'half',
				'required'  =>  0
			]
		];
	}

	/**
	 * Registers options in the design section
	 */
	private function register_design_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_nl_form_design',
				'title'         =>  __('Form Design', 'texteller'),
				'page'          =>  'tlr_newsletter',
				'section'       =>  'tlr_nl_design',
				'class'         =>  'tlr-registration-design',
				'field_args'    =>  [
					'default'   =>  [
						'form-bg-color'             =>  '#ffffff',
						'form-border-color'         =>  '#dcdcdc',
						'form-border-width'         =>  1,
						'form-border-radius'        =>  3,
						'title-color'               =>  '#282828',
						'title-font-size'           =>  18,
						'desc-color'                =>  '#282828',
						'desc-font-size'            =>  13,
						'input-bg-color'            =>  '#f3f4f9',
						'input-focus-bg-color'      =>  '#ffffff',
						'input-border-color'        =>  '#bed1e0',
						'input-focus-border-color'  =>  '#cbe2f2',
						'input-valid-border-color'  =>  '#59b7d1',
						'input-invalid-border-color'=>  '#bb245c',
						'input-border-width'        =>  1,
						'input-border-radius'       =>  2,
						'label-color'               =>  '#000000',
						'text-color'                =>  '#000000',
						'label-font-size'           =>  13,
						'submit-bg-color'           =>  '#073d56',
						'submit-hover-bg-color'     =>  '#0e2b3a',
						'submit-border-color'       =>  '#073d56',
						'submit-hover-border-color' =>  '#0e2b3a',
						'submit-color'              =>  '#ffffff',
						'submit-hover-color'        =>  '#ededed',
						'submit-border-width'       =>  0,
						'submit-border-radius'      =>  3,
						'submit-font-size'          =>  16,
						'submit-width'              =>  100,
						'submit-padding'            =>  12,
						'results-bg-color'          =>  '#e6e6f0',
						'overlay-bg-color'          =>  'rgba(1,19,30,0.8)',
						'results-text-color'        =>  '#0d0d0d',
						'results-padding'           =>  20,
						'results-text-size'         =>  13,
						'results-border-radius'     =>  3
					]
				],
				'desc'          =>      '',
				'type'          =>   'form_design'
			],
			[
				'id'            =>  'tlr_nl_form_breakpoint',
				'title'         =>  __('Mobile Breakpoint', 'texteller' ),
				'page'          =>  'tlr_newsletter',
				'section'       =>  'tlr_nl_design',
				'field_args'    =>  [ 'default' => 768 ],
				'desc'          =>  __( 'This is the display width breakpoint for responsive version of form.', 'texteller' ),
				'type'          =>   'input',
				'params'        =>  [
					'type'      =>  'number',
					'label'     =>  'px',
					'attribs'   =>  ['class' =>'tlr-small-field']
				]
			]
		];
		self::register_options($fields);
	}

	/**
	 * Registers options in the registration behaviour section
	 */
	private function register_behaviour_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_nl_link_user',
				'title'         =>  __('Link Logged-in User', 'texteller'),
				'page'          =>  'tlr_newsletter',
				'section'       =>  'tlr_nl_behaviour',
                'field_args'    =>  [ 'default' => 'yes' ],
				'desc'          =>  __('When a logged-in user fills out registration form, the newly created member will be linked to the logged-in user account. Hence, no link will be made if the form is filled by a guest user.', 'texteller'),
                'helper'        =>  __( 'This setting will be ignored if logged-in user is already linked to another member.', 'texteller' ),
				'type'          =>   'input',
				'params'        =>  [
					'type'  =>  'checkbox',
                    'label' =>  __('Link WP logged-in user to the new member', 'texteller')
				]
			],
			[
				'id'            =>  'tlr_nl_mobile_verification',
				'title'         =>  __( 'Mobile Number Verification', 'texteller' ),
				'page'          =>  'tlr_newsletter',
				'section'       =>  'tlr_nl_behaviour',
				'desc'          =>  __( ' In order for this option to work, the verification notification should be configured via General/General Notifications section.', 'texteller' ),
				'type'          =>  'input',
				'params'        =>  [
					'type'    =>    'checkbox',
					'label'   =>    __( 'Verify mobile number by sending member a code', 'texteller' )
				]
			],
		];
		self::register_options($fields);
	}

	/**
	 * Registers options in the notifications section
	 */
	private function register_notifications_options()
	{
		$fields = [
			[
				'id'            =>  'tlr_trigger_tlr_newsletter_member_registered',
				'title'         =>  __( 'New Member Registration', 'texteller' ),
				'page'          =>  'tlr_newsletter',
				'section'       =>  'tlr_nl_notifications',
				'field_args'    =>  [ 'default' => [] ],
				'type'          =>  'notification',
				'params'        =>  [
					'label'                 =>  __( 'Send notifications when a new member is registered via the newsletter form', 'texteller' ),
					'tag_type'              =>  'member',
					'trigger_recipients'    =>  [ 'registered_member' => __( 'Registered Member', 'texteller' )]
				]
			]
		];
		self::register_options($fields);
	}

	/**
	 * @see \Texteller\Interfaces\Options::get_option_tags()
	 *
	 * @return TLR\Tags
	 */
	public static function get_option_tags() : TLR\Tags
	{
		$tags = self::get_the_tags();
		$member_tags = self::get_base_tags_array('member');
		$tags->add_tag_type_data( 'member', $member_tags );

		return $tags;
	}

	public static function render_form_labels_option()
	{
		$stored_value = get_option('tlr_nl_form_labels');
		$form_title = isset($stored_value['form_title']) ? $stored_value['form_title'] : '';
		$form_description = isset($stored_value['form_description']) ? $stored_value['form_description'] : '';
		$submit_label = isset($stored_value['submit_label']) ? $stored_value['submit_label'] : '';

		ob_start();
		?>
		<div class="tlr-sub-option-container">
			<div class="tlr-sub-option-wrap form-title-wrap">
				<div class="tlr-option-title">
					<span><?php esc_html_e('Form Title', 'texteller' ) ?></span>
				</div>
				<div class="tlr-option-fields">
					<?php
					echo TLR\Admin\Option_Renderer::input( [
						'id' => 'tlr_nl_form_labels[form_title]',
						'params'    =>  [
							'type'  =>  'text'
						]
				], $form_title )
				?>
				</div>
				<div class="option-description-wrap">
					<?php
					echo TLR\Admin\Option_Renderer::render_description( ['desc' => esc_html__('The Title is displayed at the top of the registration form.','texteller')] );
					?>
				</div>
			</div><hr class="tlr-separator">
			<div class="tlr-sub-option-wrap form-description-wrap">
				<div class="tlr-option-title">
					<span><?php esc_html_e('Form Description', 'texteller' ) ?></span>
				</div>
				<div class="tlr-option-fields">
					<?php
					echo TLR\Admin\Option_Renderer::textarea( [
						'id' => 'tlr_nl_form_labels[form_description]',
						'params'    =>  [
							'attribs'   =>  [
								'rows'  =>  4,
								'cols'  =>  20
							]
						]
					], $form_description )
					?>
				</div>
				<div class="option-description-wrap">
					<?php
					echo TLR\Admin\Option_Renderer::render_description(
						[ 'desc' => esc_html__( 'This text is displayed below the form title.', 'texteller' ) ]
					);
					?>
				</div>
			</div><hr class="tlr-separator">
			<div class="tlr-sub-option-wrap submit-label-wrap">
				<div class="tlr-option-title">
					<span><?php esc_html_e('Submit Button Label', 'texteller' ) ?></span>
				</div>
				<div class="tlr-option-fields">
					<?php
					echo TLR\Admin\Option_Renderer::input( [
						'id' => 'tlr_nl_form_labels[submit_label]',
						'params'    =>  [
							'type'  =>  'text'
						]
					], $submit_label )
					?>
				</div>
				<div class="option-description-wrap">
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}