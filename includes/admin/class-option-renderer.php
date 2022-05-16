<?php

namespace Texteller\Admin;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

class Option_Renderer {

	public static function field_callback( $args )
	{
		$method = $args['type'];
		if ( 'hidden' === $method ) {
		    return '';
        }

		$html = '<div class="tlr-option-wrap">';
        $html .= "<div class='{$args['id']}-wrap'>";

		if ( is_array($method) && class_exists($method[0]) && method_exists( $method[0], $method[1] ) ) {
			$option_class = new $method[0];
			$render_method = $method[1];
			$html .= $option_class->$render_method();
		} elseif ( is_string($method) && method_exists(self::class, $method) ) {
			$html .= self::$method( $args );
		}
		$html .= '</div>';

		$html .= self::render_helper($args);
		$html .= self::render_description($args);
		$html .= '</div>';

		echo $html;
	}

	protected function render_tlr_registration_field( $setting )
	{
		$sizes = [
			'half' =>  __('Half Row', 'texteller'),
			'full'  =>   __('Full Row', 'texteller')
		];

		$required = [
			'1'  =>   __( 'Required', 'texteller' ),
			'0'  =>   __( 'Optional', 'texteller' ),
		];
		$form_fields_order_size = get_option($setting['id']);

		$html = '<div><ul class="tlr-sortable-fields">';
		foreach($form_fields_order_size as $id => $title_size) {

			$html .= '<li class="tlr-form-field">';

			$disabled = $id == 'mobile' ? 'disabled' : '';
			$checked = isset( $form_fields_order_size[$id]['enabled'] ) ?
                checked( $form_fields_order_size[$id]['enabled'], 'yes', false) : '';

			$html .= "<input type='checkbox' name='{$setting['id']}[$id][enabled]' class='field-control' $checked value='yes' $disabled>";

			$html .= '<div class="tlr-form-field-wrapper">';

			$html .= '<span class="field-title">' . $title_size['title'] . '</span></div>';
			$html .= '<input type="hidden" name="' . $setting['id'] . '['. $id .'][id]" value="'. $id .'">';
			$html .= '<input type="hidden" name="' . $setting['id'] . '['. $id .'][title]" value="'. $title_size['title'] .'">';

			$html .= '<select name="' . $setting['id'] . '['. $id .'][size]">';
			foreach ( $sizes as $value => $title ) {
				$html .= '<option ';
				$html .= selected( $title_size['size'], $value, false); //dropdown menu "selected" attribute
				$html .= 'value="' . $value . '">';    //select option value
				$html .= $title . '</option>';
			}
			$html .= '</select>';

			$html .= "<select name='{$setting['id']}[$id][required]' $disabled>";
			foreach ( $required as $value => $title ) {
				$html .= '<option ';
				$html .= selected( $title_size['required'], $value, false); //dropdown menu "selected" attribute
				$html .= 'value="' . $value . '">';    //select option value
				$html .= $title . '</option>';
			}

			$html .= '</select></li>';
		}
		$html .= '</ul></div>';

		return $html;
	}

	/*
	private function render_checkbox_list_field ($args, $html = '')
	{
		foreach ( $args['input']['options'] as $id => $title ) {
			$html .= '<div class="form-field-wrapper"><input';    //opening input tag
			$html .= ' name="' . $args['id']. "[$id]" . '"';   //input id and name
			$html .= ' type="checkbox"' ;   //input type
			$html .= isset(get_option( $args['id'] )[$id]) ? checked( get_option( $args['id'] )[$id], 'yes', false) : ''; //checkbox 'checked' attribute
			$html .= ' value="yes">';    //checkbox value
			$html .= '<span class="">' . $title . '</span></div>';
			}
		return $html;
	}
	*/

	protected static function render_helper( $args )
	{
		$html = '<div class="tlr-helper-wrap">';
		if ( ! empty( $args['helper'] ) ) {
			$html .= '<div class="tlr-helper"><span>';
			$html .= $args['helper'];
			$html .= '</span></div>';
		}
		$html .= '</div>';
		return $html;
	}

	public static function render_description($args)
	{
		$html = '<div class="option-description-wrap">';
		if ( !empty($args['desc']) ) {
			$html .= '<p class="description">';
			$html .=  $args['desc'];
			$html .= '</p>';
        }
		$html .= '</div>';
		return $html;
	}

	public static function input( array $option, string $value = null ) : string
	{
	    $html = '';
		$checked = '';
		$type = isset($option['params']['type']) ? $option['params']['type'] : 'text';
		$attribs = isset( $option['params']['attribs'] ) ? self::generate_attribs( $option['params']['attribs'] ) : '';
		$id = esc_attr( $option['id'] );

		if ( is_null( $value ) ) {
			$value = get_option( $option['id']);
        }

		if ( 'checkbox' === $type ) {
			$checked = checked( $value, 'yes', false );
			$value = 'yes';
		} elseif ( 'password' === $type ) {
		    $value = !$value ? '' : 'TLR_STORED_TOKEN';
        } else {
			$value = esc_attr( $value ) ;
		}


		$html .= "<input name='$id' id='$id' type='$type' $checked value='$value' $attribs>";

		if ( isset($option['params']['label']) ) {
		    $label = esc_html( $option['params']['label'] );
			$html .= "<label for='$id'>" . esc_html($label) . "</label>";
		}

		return $html;
	}

	protected static function select( array $option ) : string
	{
		$html = '';
		$attribs = isset( $option['params']['attribs'] ) ? self::generate_attribs( $option['params']['attribs'] ) : '';
		$id = esc_attr( $option['id'] );

		$html .= "<select name='$id' id='$id' $attribs>";
		if( is_array($option['params']['options']) ) {
			foreach ( $option['params']['options'] as $value => $title ) {
				$selected = selected( get_option($option['id']), $value, false);
				$value = esc_attr( $value );
				$title = esc_html( $title );
				$html .= "<option value='$value' $selected>$title</option>";
			}
		}
		$html .= '</select>';

		return $html;
	}

	public static function textarea( array $option, string $value = null ) : string
	{
		$html = '';
        $attribs = isset( $option['params']['attribs'] ) ? self::generate_attribs( $option['params']['attribs'] ) : '';

		$id = esc_attr( $option['id'] );

		$html .= "<textarea name='$id' id='$id' $attribs>";
		$html .= esc_textarea( is_null($value) ? get_option($option['id']) : $value );
		$html .= '</textarea>';

		return $html;
	}

	protected static function radio( array $setting_args ) : string
	{
		$options = get_option( $setting_args['id'] );


		// Base prerequisite
		$parent_disabled = '';
		$parent_pre_logic = isset($setting_args['params']['pre_logic']) ? $setting_args['params']['pre_logic'] : 'AND';
		$parent_prerequisite = isset($setting_args['params']['pre']) ? $setting_args['params']['pre'] : [];
		if ( 'OR' === $parent_pre_logic ) {
			$parent_disabled = ' disabled';
		}
		foreach ( $parent_prerequisite as $ppre_key => $ppre_value ) {
			$parent_pre_option = get_option($ppre_key);
			if ( !$parent_pre_logic || 'AND' === $parent_pre_logic ) {
				if ( $ppre_value !== $parent_pre_option || empty($option) ) {
					$parent_disabled = ' disabled';
					break;
				}
			} elseif( 'OR' === $parent_pre_logic ) {
				if ( is_array($ppre_value) ) {
					if ( in_array( $parent_pre_option, $ppre_value) ) {
						$parent_disabled = '';
						break;
					}
				} elseif ( $ppre_value === $parent_pre_option ) {
					$parent_disabled = '';
					break;
				}
			}
		}

		ob_start();

		foreach ( $setting_args['params']['values'] as $value => $input_args ) {

		    //Option Prerequisite
			$option_prerequisite = isset($input_args['pre']) ? $input_args['pre'] : [];
			$disabled = '';
			if ( empty($parent_disabled) ) {
				foreach ( $option_prerequisite as $radio_pre_option_key => $radio_pre_option_value ) {
					$stored_pre_option = get_option( $radio_pre_option_key );
					if ( is_array($radio_pre_option_value)  ) {
						$single_option_data = reset( $radio_pre_option_value);
						if ( is_array( $single_option_data ) ) {
							foreach ( $single_option_data as $single_option_key => $single_option_value ) {
								$stored_value =
									isset( $stored_pre_option[TLR\array_key_first($radio_pre_option_value)][$single_option_key] )
										? $stored_pre_option[TLR\array_key_first($radio_pre_option_value)][$single_option_key]
										: '';
								if ( $single_option_value != $stored_value || empty($stored_value) ) {
									$disabled = ' disabled';
									break;
								}
							}
						} else {
							$stored_value =
								isset( $stored_pre_option[TLR\array_key_first($radio_pre_option_value)] )
                                    ? $stored_pre_option[TLR\array_key_first($radio_pre_option_value)] : '';
							if ( $single_option_data != $stored_value || empty($stored_value) ) {
								$disabled = ' disabled';
								break;
							}
						}
					} else {
						if ( $radio_pre_option_value != $stored_pre_option || empty($stored_pre_option) ) {
							$disabled = ' disabled';
							break;
						}
					}
				}
			} else {
				$disabled = $parent_disabled;
			}

			// Option checked
			if ( is_array($options) ) {
				$checked = checked( $value, TLR\array_key_first($options), false );
			} else {
				$checked = checked( $value, $options, false );
			}

			// Option class
			$class = isset($input_args['class']) ? " class='" . esc_attr($input_args['class']) . "'" : '';
			?>
			<div style='margin-bottom: 1.5em;'>
                <input type='radio' id='<?=esc_attr($setting_args['id'].'_'.$value )?>' name='<?=
                    esc_attr($setting_args['id'])?>' value='<?= esc_html($value)?>'<?= $class . $checked . $disabled?>>
                <label for='<?=esc_attr($setting_args['id'].'_'.$value )?>' class='tlr-radio-label'><?=
                    esc_html($input_args['label']) ?></label>
                <?php

                // Render sub-fields for this ration option
                if ( isset($input_args['select']['options'], $input_args['select']['id']) ) {
	                ?><label for='<?= esc_attr($input_args['select']['id']) ?>'> | <?=
                        esc_html($input_args['select']['title'])?>: </label>
                    <select id="<?= esc_attr($input_args['select']['id']) ?>" name="<?=
                        esc_attr($input_args['select']['id']) ?>" class="tlr-radio-options">
                    <?php
                    $nation_mobile_username_pattern = get_option($input_args['select']['id']);
                        foreach ( $input_args['select']['options'] as $sub_value => $sub_label) {
                            ?><option value="<?= esc_attr($sub_value)?>"<?= $disabled . selected($sub_value, $nation_mobile_username_pattern, false) ?>><?=
                                esc_html($sub_label)?></option><?php
                        }
	                ?></select><?php
                }
                if ( isset($input_args['input'], $input_args['input']['id']) ) {
                    ?><label for='<?= esc_attr($input_args['input']['id']) ?>'> | <?=
                    esc_html($input_args['input']['title'])?>: </label>
                    <input type="text" id="<?= esc_attr($input_args['input']['id']) ?>" name="<?= esc_attr($input_args['input']['id']) ?>" value="<?= esc_attr(get_option($input_args['input']['id'])) ?>" />
                <?php
                }
                // Render description
                if ( isset($input_args['desc']) ) {
                    ?><p style='padding: 0 10px 0 27px;font-size: 0.9em;'><?= $input_args['desc'] ?></p><?php
                }
                ?>
            </div><?php
			unset($option_prerequisite);
		}
		return ob_get_clean();
	}

	public static function variable_list( array $option, array $variables = null ) : string
	{
	    // todo: esc
		$html = '';
		$id = esc_attr( $option['id'] );
		$class = isset($option['params']['attribs']['class']) ? $option['params']['attribs']['class'] : '';
		$class = 'variable-field ' . $class;
		$variables = ! is_null($variables) ? $variables : get_option( $option['id'] );
		$html .= '';
		$add_button ='<a href="javascript:void(0);" class="add-variable" title="Add new">'
                     . esc_html__('Add new', 'texteller') . '</a>';

		$remove_button = '<a href="javascript:void(0);" class="remove-variable" title="Delete">'
                         . esc_html__( 'Delete', 'texteller' ) . '</a>';

		$html .= "<div class='$class'>";
		if ( is_array($variables) && !empty($variables[0])) {
			foreach ($variables as $key => $value) {
			    $value = esc_attr( $value );
				$html .= '<div>';
				$html .= "<input type='text' name='{$id}[]' value='" . esc_attr($value). "'>";
				$html .= ($key != 0) ? $remove_button : '';
				$html .= ($key == 0) ? $add_button : '';
				$html .= '</div>';
			}
		} else {
			$html .= '<div>';
			$html .= "<input type='text' name='{$id}[]' value=''>";
			$html .= $add_button;
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	protected static function tel_input_options( array $option )
    {
        $stored_value = get_option( $option['id'] );
	    $initial_country = isset($stored_value['initial_country']) ? $stored_value['initial_country'] : '';
	    $country_dropdown = isset($stored_value['country_dropdown']) && $stored_value['country_dropdown'] ? 'yes' : '';
        $preferred_countries = isset($stored_value['preferred_countries']) ? $stored_value['preferred_countries'] : [];

        ob_start();
        ?>
        <div class="tlr-sub-option-container">
            <div class="tlr-sub-option-wrap initial-country-wrap">
                <div class="tlr-option-title">
                    <span><?php esc_html_e('Default country', 'texteller' ) ?></span>
                </div>
                <div class="tlr-option-fields">
			        <?php
			        echo self::input( [
				        'id' => $option['id'].'[initial_country]',
				        'params'    =>  [
					        'type'  =>  'text',
					        'attribs'   =>  [
						        'class' =>  'tlr-tiny-field'
					        ]
				        ]
			        ], $initial_country )
			        ?>
                </div>
                <div class="option-description-wrap">
			        <?php
			        echo self::render_description( ['desc' => esc_html__("This is the country that will be initially selected after the form loads.", 'texteller') . ' ' . sprintf( /* translators: %s: Name of a country code standard */
					        esc_html__("Enter an %s country code or leave it empty in order to disable default country selection.", 'texteller'),
					        '<a href="https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2" target="_blank">ISO 3166-1 alpha-2</a>'
				        )] );
			        ?>
                </div>
            </div><hr class="tlr-separator">
            <div class="tlr-sub-option-wrap country-dropdown-wrap">
                <div class="tlr-option-title">
                    <span><?php esc_html_e('Country dropdown', 'texteller' ) ?></span>
                </div>
                <div class="tlr-option-fields">
			        <?php
			        echo self::input([
				        'id' => $option['id'].'[country_dropdown]',
				        'params'    =>  [
					        'type'  =>  'checkbox',
					        'label' =>  __('Enable country dropdown for mobile numbers.','texteller')
				        ]
			        ],$country_dropdown)
			        ?>
                </div>
                <div class="option-description-wrap">
			        <?php
			        echo self::render_description( ['desc' => esc_html__("Users will be able to enter a mobile number of any country, by selecting the related country code in the dropdown.", 'texteller') ] );
			        ?>
                </div>
            </div><hr class="tlr-separator">
            <div class="tlr-sub-option-wrap preferred-countries-wrap">
                <div class="tlr-option-title">
                    <span><?php esc_html_e('Preferred countries', 'texteller' ) ?></span>
                </div>
		        <?php
		        echo self::variable_list( [
			        'id' => $option['id'].'[preferred_countries]',
			        'params'    =>  [
				        'attribs' => ['class' => 'country-codes']
			        ]
		        ], $preferred_countries );
		        ?>
                <div class="option-description-wrap">
			        <?php
			        echo self::render_description( ['desc' => esc_html__("These country codes will be displayed at the top of the country dropdown list, in case it's enabled.", 'texteller') . ' ' . sprintf( /* translators: %s: Name of a country code standard */
					        esc_html__("The %s country codes will be shown in the order as entered here.", 'texteller'),
					        '<a href="https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2" target="_blank">ISO 3166-1 alpha-2</a>'
				        )] );
			        ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();

    }

	protected static function staff_selector( array $option, array $member_ids = null )
	{
		$member_ids = ! is_null($member_ids) ? $member_ids : get_option( $option['id'],[] );
		$id = esc_attr( $option['id'] );

		ob_start();
		?>
        <div class='tlr-admins-wrap'>
            <div class="tlr-member-selector-wrap">
                <label for='<?= $id; ?>_selector'><?= esc_html($option['params']['label']); ?></label>
                <select id='<?= $id; ?>_selector' multiple='multiple' class="member-selector" data-selector-tag="needsExtraField" data-selector-name="<?= $id . '[]'; ?>">
					<?php
					foreach ( (array) $member_ids as $member_id ) {
						$member = new TLR\Member( $member_id ); ?>
                        <option selected='selected' value='<?= $member->get_id(); ?>'>
                            <?= esc_html__( $member->get_name() ); ?>
                        </option>
						<?php
					}
					?>
                </select>
	            <?php
	            if ( !empty($member_ids) ) {
		            foreach ( (array) $member_ids as $member_id ) {
			            ?>
                        <input hidden name="<?= $id ?>[]" class="member-selector-data" value="<?= esc_attr($member_id); ?>">
			            <?php
		            }
	            }
	            ?>
            </div>
        </div>
		<?php
		return ob_get_clean();
	}

	protected static function registration_fields( array $option ) : string
	{
	    if ( !isset($option['field_args']['default']) ) {
	        return '';
        }

	    $defaults = $option['field_args']['default'];
	    $fields = array_keys($defaults);
		$stored_data = get_option( $option['id'], [] );
		if ( !empty($stored_data) ) {
			$fields = array_keys( array_merge( array_flip(array_keys($stored_data)), $defaults ) );
		}

		ob_start();
		?>
		<div>
            <ul class="tlr-sortable-fields">
                <?php
                // To check if we should render size selection for the fields of this option
                $has_size_field = isset( $option['params']['size_selection'] ) && $option['params']['size_selection'];

                foreach( $fields as $field_slug ) {

                    // If field data does not exist in the stored option, use the defaults
	                if ( empty($stored_data[$field_slug]) ) {
		                $stored_field_data = $defaults[$field_slug];
	                } else {
		                $stored_field_data = $stored_data[$field_slug];
                    }

	                // If we don't have a stored title, use default value
	                if ( empty($stored_field_data['title']) ) {
		                $stored_field_data['title'] = $defaults[$field_slug]['title'];
                    }

	                // If $has_size_field but we don't have a stored size, use default value
	                if ( $has_size_field && empty($stored_field_data['size']) ) {
		                $stored_field_data['size'] = $defaults[$field_slug]['size'];
	                }

	                $checked = isset( $stored_field_data['enabled'] )
                        ? checked( 'yes', $stored_field_data['enabled'], false ) : '';
	                ?><li class="tlr-form-field">
                    <input type='checkbox' name='<?= esc_attr("{$option['id']}[$field_slug][enabled]")
                    ?>' class='field-control' value='yes'<?=$checked?>><div class="tlr-form-field-wrapper"><input name='<?=
                        esc_attr( "{$option['id']}[$field_slug][title]"); ?>' value='<?=
                        esc_attr($stored_field_data['title']); ?>' class="field-title"></div>
                    <input type='hidden' name='<?= esc_attr( "{$option['id']}[$field_slug][id]" ) ?>' value='<?=
                        esc_attr($field_slug) ?>'>
                    <?php
	                if ( $has_size_field ) {
		                ?>
                        <select name='<?= esc_attr( "{$option['id']}[$field_slug][size]" ) ?>'>
			                <?php
			                $sizes = [
				                'half'  =>  __( 'Half row', 'texteller' ),
				                'full'  =>  __( 'Full row', 'texteller' )
			                ];

			                foreach ( $sizes as $size_slug => $size_title ) {
				                ?>
				                <option value='<?= esc_attr($size_slug) ?>'<?php
                                    selected( $size_slug, $stored_field_data['size'] ) ?>><?=
                                        esc_html($size_title)?></option>
                                <?php
			                }
			                ?>
                        </select>
		                <?php
	                }
	                ?>
                    <select name='<?= esc_attr( "{$option['id']}[$field_slug][required]" ) ?>'>
		                <?php
		                $required = [
			                '1'  =>  __( 'Required', 'texteller' ),
			                '0'  =>  __( 'Optional', 'texteller' )
		                ];

		                foreach ( $required as $value => $required_label ) {
			                ?>
                            <option value='<?= esc_attr($value) ?>'<?php
                                selected( $value, $stored_field_data['required'] ) ?>><?=
                                    esc_html($required_label)?></option>
			                <?php
		                }
		                ?>
                    </select>
                    </li><?php
                }
                ?>
            </ul>
        </div>
		<?php
		return ob_get_clean();
	}

	protected static function form_design( array $option ) : string
	{
		$stored_data = get_option( $option['id'] );

		ob_start();
		?>
        <table style="width:80%">
            <tr>
                <th class="tlr-sub-table-heading" colspan="4">
                    <span><?= esc_html__( 'Form', 'texteller' ); ?></span>
                </th>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'form-bg-color', $option, __( 'Background color', 'texteller' ), $stored_data
                );
				echo self::render_design_input(
				        'form-border-color', $option, __('Border color', 'texteller'), $stored_data
                );
				?>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'form-border-width', $option, __('Border width', 'texteller'), $stored_data, 'px'
                );
				echo self::render_design_input(
				        'form-border-radius', $option,  __('Border radius', 'texteller'), $stored_data, 'px'
                );
				?>
            </tr>
            <tr>
                <th class="tlr-sub-table-heading" colspan="4">
                    <span><?= __( 'Form header', 'texteller' ); ?></span>
                </th>
            </tr>
            <tr>
		        <?php
		        echo self::render_design_input(
		                'title-color', $option, __( 'Form title color', 'texteller' ), $stored_data
                );
		        echo self::render_design_input(
		                'title-font-size', $option, __('Form title font size','texteller'), $stored_data,'px'
                );
		        ?>
            </tr>
            <tr>
		        <?php
		        echo self::render_design_input(
		                'desc-color', $option, __( 'Description color', 'texteller' ), $stored_data
                );
		        echo self::render_design_input(
		                'desc-font-size', $option, __('Description font size','texteller'), $stored_data,'px'
                );
		        ?>
            </tr>
            <tr>
                <th class="tlr-sub-table-heading" colspan="4">
                    <span><?= __( 'Form input fields', 'texteller' ); ?></span>
                </th>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'input-bg-color', $option, __('Background color', 'texteller'), $stored_data
                );
				echo self::render_design_input(
				        'input-focus-bg-color', $option, __('Background color (focus)', 'texteller'), $stored_data
                );
				?>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'input-border-color', $option, __('Border color', 'texteller'), $stored_data
                );
				echo self::render_design_input(
				        'input-focus-border-color', $option, __('Border color (focus)', 'texteller'), $stored_data
                );

				?>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'input-valid-border-color', $option, __('Border color (valid)', 'texteller'), $stored_data
                );
				echo self::render_design_input(
				        'input-invalid-border-color',
                        $option,
                        __( 'Border color (invalid)', 'texteller' ),
                        $stored_data
                );
				?>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'input-border-width', $option, __('Border width', 'texteller'), $stored_data, 'px'
                );
				echo self::render_design_input(
				        'input-border-radius', $option, __('Border radius', 'texteller'), $stored_data, 'px'
                );
				?>
            </tr>
            <tr>
		        <?php
		        echo self::render_design_input(
		                'label-color', $option, __('Placeholder color (labels)', 'texteller'), $stored_data
                );
		        echo self::render_design_input(
		                'text-color', $option, __('Text color', 'texteller'), $stored_data
                );
		        ?>
            </tr>
            <tr>
		        <?php
		        echo self::render_design_input(
		                'label-font-size', $option, __('Font size', 'texteller'), $stored_data, 'px'
                );
		        ?>
            </tr>
            <tr>
                <th class="tlr-sub-table-heading" colspan="4">
                    <span><?= __( 'Submit button', 'texteller' ); ?></span>
                </th>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'submit-bg-color', $option, __('Background color', 'texteller'), $stored_data
                );
				echo self::render_design_input(
				        'submit-hover-bg-color', $option, __('Background color (hover)', 'texteller'), $stored_data
                );
				?>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'submit-border-color', $option, __('Border color', 'texteller'), $stored_data
                );
				echo self::render_design_input(
				        'submit-hover-border-color', $option, __('Border color (hover)', 'texteller'), $stored_data
                );
				?>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'submit-color', $option, __('Label color', 'texteller'), $stored_data
                );
				echo self::render_design_input(
				        'submit-hover-color', $option, __( 'Label color (hover)', 'texteller' ), $stored_data
                );
				?>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'submit-border-width', $option, __('Border width', 'texteller'), $stored_data, 'px'
                );
				echo self::render_design_input(
				        'submit-border-radius', $option, __('Border radius', 'texteller'), $stored_data, 'px'
                );
				?>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'submit-font-size', $option, __('Font size', 'texteller'), $stored_data, 'px'
                );
				echo self::render_design_input(
				        'submit-width', $option, __('Button width', 'texteller'), $stored_data, '%'
                );
				?>

            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'submit-padding', $option, __('Padding', 'texteller'), $stored_data, 'px'
                );
				?>
            </tr>
            <tr>
                <th class="tlr-sub-table-heading" colspan="4">
                    <span><?= __( 'Response layer', 'texteller' ); ?></span>
                </th>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'results-bg-color', $option, __('Background color', 'texteller'), $stored_data
                );
				echo self::render_design_input(
				        'overlay-bg-color', $option, __('Overlay color', 'texteller'), $stored_data
                );
				?>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'results-text-color', $option, __('Text color', 'texteller'), $stored_data
                );
				echo self::render_design_input(
				        'results-padding', $option, __('Padding', 'texteller'), $stored_data, 'px'
                );
				?>
            </tr>
            <tr>
				<?php
				echo self::render_design_input(
				        'results-text-size', $option, __('Text size', 'texteller'), $stored_data, 'px'
                );
				echo self::render_design_input(
				        'results-border-radius', $option, __('Border radius','texteller'), $stored_data, 'px'
                );
				?>
            </tr>
        </table>

		<?php
		return ob_get_clean();
	}

	protected static function render_design_input( $slug, $option, $label, $stored_data, $suffix = '' )
	{
		$color_picker_data = '';
		if( false !== strpos( $slug, '-color' ) ) {
			$class = 'tlr-color-field';
			$type = 'text';
			$color_picker_data = " data-default-color='{$option['field_args']['default'][$slug]}' data-alpha='true'";
		} else {
			$class = 'tlr-small-field';
			$type = 'number';
		}
		ob_start();
		?>
        <td>
            <label for='form_design[<?= esc_attr($slug) ?>]' class="input-label"><?= esc_html($label) ?></label>
        </td>
        <td>
            <input type='<?= $type ?>' id='form_design[<?= esc_attr($slug) ?>]' name='tlr_nl_form_design[<?= esc_attr($slug) ?>]'<?= $color_picker_data ?> value='<?= esc_html( $stored_data[$slug] ) ?>' class="<?= esc_attr($class) ?>"><?php
			if ( $suffix ) {
				?><span class="design-field-suffix"><?= esc_html($suffix) ?></span><?php
			}
			?>
        </td>
		<?php
		return ob_get_clean();
	}

	protected static function gateway_selector( array $option )
	{
		$gateways = $option['params']['gateways'];
		$active_gateways = (array) get_option( $option['id'] );
		ob_start();
		?>
        <div id="gateways-wrap"></div>
        <?php
		foreach ( $active_gateways as $active_gateway ) {
            ?>
            <input name="<?= esc_attr($option['id']) ?>[]" class="active-gateway" hidden="hidden" value="<?= esc_attr($active_gateway); ?>">
			<?php
        }
        ?>
        <script>
            jQuery(document).ready( function($) {
                let dataArray = [<?php
					foreach ( $gateways as $value => $item ) {
						$selected = in_array( $value, $active_gateways ) ? ', selected: true' : '';
						echo "{item: '$item', value: '$value'$selected},";
					}
					?>];
                let settings = {
                    dataArray: dataArray,
                    tabNameText: '<?= __( 'Available Gateways', 'texteller' );?>',
                    rightTabNameText: '<?= __( 'Active Gateways', 'texteller' );?>',
                    searchPlaceholderText: '<?= __( 'Search', 'texteller' );?>',
                    totalText	: '<?= __( 'Total', 'texteller' );?>',

                    callable: function (items) {

                        let wrapper = $('#gateways-wrap');
                        wrapper.parent().find('input.active-gateway').remove();
                        $.each(items, function (index, activeGateways) {
                            let hiddenInput = '<input name="tlr_active_gateways[]" class="active-gateway" value="'+activeGateways.value+'" hidden="">';
                            wrapper.parent().append(hiddenInput);
                        });
                    }
                };

                let transfer = $('#gateways-wrap').transfer(settings);
                // get selected items
                transfer.getSelectedItems();
            });
        </script>
		<?php
		return ob_get_clean();
	}

	public static function notification( $option ) : string
    {
	    $stored_option = get_option( $option['id'], [] );
	    $option_id = esc_attr( $option['id']);
	    $enabled = ! empty( $stored_option['enabled'] ) ? 'yes' : '';
	    $trigger_id = esc_attr( str_replace( ['tlr_trigger_', '_'], ['', '-'], $option['id'] ) );
	    $triggerJSName = explode('-', $trigger_id);
	    foreach ( (array) $triggerJSName  as $recipient_type => $name_part ) {
	        if ( 0 === $recipient_type ) continue;
		    $triggerJSName[$recipient_type] = ucfirst($name_part);
	    }
	    $triggerJSName = implode('',$triggerJSName);

	    $recipient_types = isset($option['params']['recipient_types'])
		    ? $option['params']['recipient_types']
		    : ['trigger', 'staff', 'members', 'numbers'];

	    ob_start();
	    ?>
        <input type='checkbox' id='<?= $trigger_id ?>' name='<?= $option_id ?>[enabled]' value='yes'<?php
        checked('yes', $enabled); ?> class="notification-trigger has-content" data-jsname="<?=$triggerJSName?>">
        <label for='<?= $trigger_id ?>'>
            <?= isset( $option['params']['label'] ) ? $option['params']['label'] : '' ?>
        </label>
        <div class="action-wrap <?= $trigger_id ?>-content">
            <div class="action-edit-wrap" data-jsname="<?=$triggerJSName?>">
                <div class="action-form">
                    <div class="recipient-type-wrap"><?php
                        if ( in_array('trigger', $recipient_types) ) :
                            ?>
                            <div>
                                <input type="radio" hidden id="<?=$trigger_id; ?>-recipients-trigger" value="trigger" class="has-content hidden-radio recipient-type-select" data-radiogroup="recipient-type">
                                <label for="<?=$trigger_id; ?>-recipients-trigger" class="recipient-type-label">
			                        <?= esc_html__( 'Trigger Recipients', 'texteller' ) ?>
                                </label>
                            </div><?php
                        endif;
	                    if ( in_array('staff', $recipient_types) ) :
		                    ?>
                            <div>
                            <input type="radio" hidden id="<?=$trigger_id; ?>-recipients-staff" value="staff" class="has-content hidden-radio recipient-type-select" data-radiogroup="recipient-type">
                            <label for="<?=$trigger_id; ?>-recipients-staff" class="recipient-type-label">
			                    <?= esc_html__( 'Site Staff', 'texteller' ) ?>
                            </label>
                            </div><?php
	                    endif;
	                    if ( in_array('members', $recipient_types) ) :
		                    ?>
                            <div>
                            <input type="radio" id="<?=$trigger_id; ?>-recipients-members" value="members" class="has-content hidden-radio recipient-type-select" data-radiogroup="recipient-type">
                            <label for="<?=$trigger_id; ?>-recipients-members" class="recipient-type-label">
			                    <?= esc_html__( 'Other Members', 'texteller' ) ?>
                            </label>
                            </div><?php
	                    endif;
	                    if ( in_array('numbers', $recipient_types) ) :
		                    ?>
                            <div>
                            <input type="radio" hidden id="<?=$trigger_id; ?>-recipients-numbers" value="numbers" class="has-content hidden-radio recipient-type-select" data-radiogroup="recipient-type">
                            <label for="<?=$trigger_id; ?>-recipients-numbers" class="recipient-type-label">
			                    <?= esc_html__( 'Custom Numbers', 'texteller' ) ?>
                            </label>
                            </div><?php
	                    endif;
	                    ?>
                    </div>
                    <div class="action-recipients-content">
                        <?php
                        foreach ( $recipient_types as $recipient_key ) {
                            ?>
                            <div class="recipient-content <?= $trigger_id ?>-recipients-<?= $recipient_key ?>-content">
                                <hr style="margin-bottom: 10px">
                                <div class="<?= $recipient_key ?>-content-wrap">
                                    <div class="recipient-type-fields-wrap">
	                                    <?php
	                                    $trigger_recipients = $option['params']['trigger_recipients'];

	                                    switch($recipient_key) {

		                                    case 'trigger':
		                                        $i = 0;
		                                        ?>
                                            <div style="margin-top:10px;">
	                                            <?php
	                                            foreach ( $trigger_recipients as $trigger_recipient_type => $label ) {
		                                            if ( 0 === $i ) {
			                                            $label_class = ' has-active-content';
			                                            $checked = ' checked="checked"';
		                                            } else {
			                                            $label_class = '';
			                                            $checked = '';
		                                            }
		                                            ?>
                                                    <div style="display:inline-block;">
                                                        <input type="radio" hidden id="trigger-recipient-type-<?=$trigger_id.'_'.$trigger_recipient_type?>" value="<?= esc_attr($trigger_recipient_type)?>" class="has-content hidden-radio recipient-type-select" data-radiogroup="trigger-recipient-type"<?= $checked ?>>
                                                        <label for="trigger-recipient-type-<?=$trigger_id.'_'.$trigger_recipient_type?>" class="trigger-recipient-type-label<?= $label_class ?>">
				                                            <?= esc_html($label) ?>
                                                        </label>
                                                    </div>
		                                            <?php
		                                            $i++;
	                                            }
	                                            ?>
                                            </div><?php
			                                    break;

		                                    case 'staff':
			                                    $stored_staff = isset($stored_option['actions']['staff']['recipients'])
                                                    ? $stored_option['actions']['staff']['recipients'] : [];
			                                    $stored_is_all = !empty($stored_option['actions']['staff']['is_all'])
                                                    ? '1' : '0';
			                                    ?>
                                                <div class="staff-option-wrap">
                                                    <input type="radio" id="<?=$trigger_id?>-recipients-staff-all"<?php
                                                    checked($stored_is_all, '1')?> name="<?=$option_id
                                                    ?>[actions][staff][is_all]" value="1" data-radiogroup="recipient-staff">
                                                    <label for="<?=$trigger_id?>-recipients-staff-all">
					                                    <?= esc_html__( 'All Active Staff',
                                                            'texteller' ) ?>
                                                    </label>
                                                </div>
                                                <div class="staff-option-wrap">
                                                    <input type="radio" id="<?=$trigger_id?>-recipients-select-staff" name="<?=
                                                    $option_id ?>[actions][staff][is_all]" value="0"<?php
                                                    checked($stored_is_all, '0')
                                                    ?> class="has-content" data-radiogroup="recipient-staff">
                                                    <label for="<?=$trigger_id?>-recipients-select-staff">
					                                    <?= esc_html__( 'Select Staff',
                                                            'texteller' ) ?>
                                                    </label>
                                                    <div class="staff-content <?=$trigger_id; ?>-recipients-select-staff-content">

                                                        <select class="staff-selector" multiple="multiple" data-selector-tag="needsExtraField" data-selector-name="<?=$option_id
                                                        ?>[actions][staff][recipients][]">
						                                    <?php
						                                    $active_staff = get_option( 'tlr_staff',[] );
						                                    if( !empty($active_staff) ) {
							                                    foreach ( $active_staff as $member_id ) {
								                                    $member = new TLR\Member($member_id);
								                                    if ( $member->get_id() == $member_id ) {
								                                        $selected = in_array($member_id, $stored_staff)
                                                                            ? ' selected="selected"' : '';
									                                    ?>
                                                                        <option value="<?=$member_id?>"<?=$selected?>>
										                                    <?= esc_html( $member->get_name() ); ?>
                                                                        </option>
									                                    <?php
								                                    }
							                                    }
						                                    } else {
							                                    ?>
                                                                <option selected="selected">
								                                    <?= esc_html__( 'No Staff found.',
                                                                        'texteller' ); ?>
                                                                </option>
							                                    <?php
						                                    }
						                                    ?>
                                                        </select>
                                                        <?php
                                                        if ( !empty($stored_staff) ) {
	                                                        foreach ( (array) $stored_staff as $member_id ) {
	                                                            if ( !in_array($member_id, $active_staff) ) {
	                                                                continue;
                                                                }
		                                                        ?>
                                                                <input hidden name="<?=$option_id?>[actions][staff][recipients][]" class="member-selector-data" value="<?= esc_attr($member_id); ?>">
		                                                        <?php
	                                                        }
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
			                                    <?php
			                                    break;

		                                    case 'members':
			                                    ?>
                                                <label for='<?= $trigger_id; ?>-recipient-select-member' hidden>
				                                    <?=esc_html__('Select members','texteller')?>
                                                </label>
                                                <div class="members-content-wrap">
                                                    <select id='<?= $trigger_id; ?>-recipient-select-member' multiple='multiple' class="member-selector" data-selector-tag="needsExtraField" data-selector-name="<?=
                                                    $option_id ?>[actions][members][recipients][]">
                                                        <?php
                                                        $stored_members = isset($stored_option['actions']['members']['recipients'])
                                                            ? $stored_option['actions']['members']['recipients'] : [];
                                                        foreach ( (array) $stored_members as $member_id ) {
	                                                        $member = new TLR\Member( $member_id );
	                                                        if ( !$member_id || $member->get_id() != $member_id ) {
	                                                            continue;
                                                            }
	                                                        ?>
                                                            <option selected='selected' value='<?= $member->get_id() ?>'>
		                                                        <?= esc_html__( $member->get_name() ); ?>
                                                            </option>
	                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <?php
                                                    if ( !empty($stored_members) ) {
	                                                    foreach ( (array) $stored_members as $member_id ) {
	                                                        $member = new TLR\Member($member_id);
	                                                        if( $member_id != $member->get_id() ) {
	                                                            continue;
                                                            }
		                                                    ?>
                                                            <input hidden name="<?=$option_id?>[actions][members][recipients][]" class="member-selector-data" value="<?= esc_attr($member_id); ?>">
		                                                    <?php
	                                                    }
                                                    }
                                                    ?>
                                                </div>
			                                    <?php
			                                    break;

		                                    case 'numbers':
			                                    $stored_numbers = !empty($stored_option['actions']['numbers']['recipients'])
                                                    ? (array) $stored_option['actions']['numbers']['recipients'] : [''];

			                                    foreach ( $stored_numbers as $key => $stored_number ) {
				                                    ?>
                                                    <div class="recipient-number">
                                                        <input type='text' value='<?= $stored_number
                                                        ?>' class="tlr-mobile" data-hidden-input-name="<?= $option_id ?>[actions][numbers][recipients][]">
                                                        <?php
                                                        if ( 0 === $key) {
	                                                        ?>
                                                            <button class="add-variable add-number" title="<?=
	                                                        esc_attr__('Add new', 'texteller') ?>">
		                                                        <?=esc_html__('Add New','texteller')?>
                                                            </button>
                                                            <?php
                                                        } else {
                                                            ?>
                                                            <button class="remove-variable remove-number" <?=
                                                            __( 'Remove','texteller' )?>>
                                                                <?= __( 'Remove','texteller' )?>
                                                            </button>
                                                            <?php
                                                        }
                                                        ?>
                                                    </div>
				                                    <?php
			                                    }
	                                    }
	                                    ?>
                                    </div>
                                    <?php
                                    // Trigger Recipients
                                    if ( 'trigger' === $recipient_key ) {
                                        $i = 0;
	                                    foreach ( $trigger_recipients as $trigger_recipient_type => $label ) {
	                                        if ( 0 === $i ) {
	                                            $style = ' style="display:block;"';
                                            } else {
	                                            $style = '';
                                            }
		                                    ?>
                                            <div class="recipient-data-container trigger-recipient-content trigger-recipient-type-<?=$trigger_id.'_'.$trigger_recipient_type?>-content"<?=$style?>>
                                                <div class="basic-fields">
                                                    <div class="action-field-wrap">
                                                        <label for="<?=$trigger_id?>-trigger-<?=$trigger_recipient_type?>-enabled-check" class="action-field-label"><?=
                                                            esc_html__('Enabled', 'texteller');?></label>
                                                        <input type="checkbox" id="<?= $trigger_id ?>-<?=$recipient_key?>-enabled-check" name="<?=
			                                            $option_id ?>[actions][trigger][recipients][<?=$trigger_recipient_type?>][enabled]" value="1"<?php
			                                            $stored_enabled_check = !empty($stored_option['actions']['trigger']['recipients'][$trigger_recipient_type]['enabled'])
				                                            ? $stored_option['actions']['trigger']['recipients'][$trigger_recipient_type]['enabled'] : '0';
			                                            checked('1',$stored_enabled_check);?>>
                                                    </div>
                                                    <div class="action-field-wrap">
                                                        <label for="<?=$trigger_id?>-trigger-<?=$trigger_recipient_type?>-save-check" class="action-field-label"><?=
                                                            esc_html__('Save sent messages', 'texteller')?></label>
                                                        <input type="checkbox" id="<?=$trigger_id?>-trigger-<?=$trigger_recipient_type?>-save-check" name="<?=
			                                            $option_id ?>[actions][trigger][recipients][<?=$trigger_recipient_type?>][save]" value="1"<?php
			                                            $stored_save_check = !empty($stored_option['actions']['trigger']['recipients'][$trigger_recipient_type]['save'])
				                                            ? $stored_option['actions']['trigger']['recipients'][$trigger_recipient_type]['save'] : '1';
			                                            checked('1', $stored_save_check);?>>
                                                    </div>
                                                    <div class="action-field-wrap">
                                                        <label for="<?=$trigger_id?>-trigger-<?=$trigger_recipient_type?>-gateway-select" class="action-field-label">
				                                            <?= esc_html__('Gateway', 'texteller');?>
                                                        </label>
                                                        <select id="<?=$trigger_id?>-trigger-<?=$trigger_recipient_type?>-gateway-select" name="<?= $option_id
			                                            ?>[actions][trigger][recipients][<?= $trigger_recipient_type ?>][gateway]" class="gateway-selector">
				                                            <?php
				                                            $stored_gateway = !empty($stored_option['actions']['trigger']['recipients'][$trigger_recipient_type]['gateway'])
					                                            ? $stored_option['actions']['trigger']['recipients'][$trigger_recipient_type]['gateway'] : '' ;
				                                            $active_gateways = (array) get_option( 'tlr_active_gateways', [] );
				                                            if ( !empty($active_gateways) && !empty($active_gateways[0]) ) {
					                                            if ( in_array( $stored_gateway, $active_gateways ) ) {
						                                            $selected_gateway = $stored_gateway;
					                                            } else {
						                                            $selected_gateway = $active_gateways[0];
					                                            }

					                                            foreach ( $active_gateways as $active_gateway ) {
						                                            ?>
                                                                    <option value="<?= $active_gateway ?>"<?php selected($selected_gateway, $active_gateway) ?>>
							                                            <?= ucfirst($active_gateway); ?>
                                                                    </option>
						                                            <?php
					                                            }
                                                            } else {
					                                            ?>
                                                                <option selected="selected">
						                                            <?php esc_html_e('Configure a gateway first.', 'texteller'); ?>
                                                                </option>
					                                            <?php
                                                            }
				                                            ?>
                                                        </select>
                                                    </div>
                                                    <div class="action-field-wrap">
                                                        <label for="<?=$trigger_id?>-trigger-<?=$trigger_recipient_type?>-interface-select" class="action-field-label">
				                                            <?= esc_html__('Interface', 'texteller');?>
                                                        </label>
                                                        <select id="<?=$trigger_id?>-trigger-<?=$trigger_recipient_type?>-interface-select" name="<?=
			                                            $option_id ?>[actions][trigger][recipients][<?= $trigger_recipient_type ?>][interface]" class="interface-selector">
				                                            <?php
                                                            if ( isset($selected_gateway) ) {
	                                                            $gateway_class = TLR\tlr_get_gateway_class($selected_gateway);
	                                                            if( $gateway_class ) {
		                                                            /** @var TLR\Interfaces\Gateway $gateway_class */
		                                                            $interfaces = $gateway_class::get_interfaces();
		                                                            $default_interface = $gateway_class::get_default_interface();
		                                                            $stored_interface = !empty($stored_option['actions']['trigger']['recipients'][$trigger_recipient_type]['interface'])
			                                                            ? $stored_option['actions']['trigger']['recipients'][$trigger_recipient_type]['interface'] : '';
		                                                            $selected_interface = in_array($stored_interface, array_keys($interfaces))
			                                                            ? $stored_interface : $default_interface;
		                                                            $content_types = $gateway_class::get_content_types();

		                                                            foreach ( $interfaces as $key => $title ) {
			                                                            $content_type = isset($content_types[$key])
				                                                            ? $content_types[$key] : 'text';
			                                                            ?><option value="<?= esc_attr($key) ?>" data-content-type="<?=
		                                                            $content_type ?>"<?php selected($selected_interface, $key)
			                                                            ?>><?= esc_html($title) ?></option><?php
		                                                            }
	                                                            }
                                                            } else {
	                                                            ?>
                                                                <option selected="selected">
		                                                            <?php esc_html_e('Configure a gateway first.', 'texteller'); ?>
                                                                </option>
	                                                            <?php
                                                            }
				                                            ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="content-labels-wrap">
                                                    <hr class="tlr-separator">
                                                    <label for="<?=$trigger_id?>-trigger-<?=$trigger_recipient_type?>-content" class="action-field-label">
			                                            <?= esc_html__('Notification Content:', 'texteller');?>
                                                    </label>
                                                </div>
                                                <div class="recipient-message-content">
		                                            <?php
		                                            foreach ( $active_gateways as $active_gateway ) {
			                                            $active_gateway = TLR\tlr_get_gateway_class($active_gateway);
			                                            if ( $active_gateway
			                                                 && method_exists( $active_gateway, 'get_gateway_options')
			                                            ) {
				                                            $stored_gateway_data = isset($stored_option['actions']['trigger']['recipients'][$trigger_recipient_type]['gateway_data'])
					                                            ? $stored_option['actions']['trigger']['recipients'][$trigger_recipient_type]['gateway_data'] : [];
				                                            $option_name = "{$option_id}[actions][trigger][recipients][$trigger_recipient_type][gateway_data]";
				                                            ?>
                                                            <div class="gateway-content">
					                                            <?php
					                                            $active_gateway::get_gateway_options($option_name, $stored_gateway_data);
					                                            ?>
                                                            </div>
				                                            <?php
			                                            }
		                                            }
		                                            ?>
                                                    <div class="text-content">
                                                        <textarea id='<?=$trigger_id?>-trigger-<?=$trigger_recipient_type?>-content' name="<?=
                                                        $option_id ?>[actions][trigger][recipients][<?=$trigger_recipient_type
                                                        ?>][content]" class="tlr-text-content tlr-count" cols="40" rows="7" data-sms-count-wrap="after"><?=
                                                            isset( $stored_option['actions']['trigger']['recipients'][$trigger_recipient_type]['content'] )
                                                                ? $stored_option['actions']['trigger']['recipients'][$trigger_recipient_type]['content'] : '';
                                                            ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
		                                    <?php
                                            $i++;
	                                    }
                                    }
                                    // Staff, Members & Numbers recipients
                                    else {
                                        ?>
                                        <div class="recipient-data-container <?= esc_attr($recipient_key);?>-recipient-content">
                                            <div class="basic-fields">
                                                <div class="action-field-wrap">
                                                    <label for="<?= $trigger_id ?>-<?=$recipient_key?>-enabled-check" class="action-field-label">
				                                        <?= esc_html__('Enabled', 'texteller');?>
                                                    </label>
                                                    <input type="checkbox" id="<?= $trigger_id ?>-<?=$recipient_key?>-enabled-check" name="<?=
			                                        $option_id ?>[actions][<?=$recipient_key?>][enabled]" value="1"<?php
			                                        $stored_enabled_check = !empty($stored_option['actions'][$recipient_key]['enabled'])
				                                        ? $stored_option['actions'][$recipient_key]['enabled'] : '0';
			                                        checked('1',$stored_enabled_check);?>>

                                                </div>
                                                <div class="action-field-wrap">
                                                    <label for="<?= $trigger_id ?>-<?=$recipient_key?>-save-check" class="action-field-label">
				                                        <?=esc_html__('Save sent messages', 'texteller')?>
                                                    </label>
                                                    <input type="checkbox" id="<?= $trigger_id ?>-<?=$recipient_key?>-save-check" name="<?=
			                                        $option_id ?>[actions][<?= $recipient_key ?>][save]" value="1"<?php
			                                        $stored_save_check = !empty($stored_option['actions'][$recipient_key]['save'])
				                                        ? $stored_option['actions'][$recipient_key]['save'] : '1';
			                                        checked('1', $stored_save_check);?>>
                                                </div>
                                                <div class="action-field-wrap">
                                                    <label for="<?= $trigger_id ?>-<?=$recipient_key?>-gateway-select" class="action-field-label">
				                                        <?= esc_html__('Gateway', 'texteller');?>
                                                    </label>
                                                    <select id="<?= $trigger_id ?>-<?=$recipient_key?>-gateway-select" name="<?= $option_id
			                                        ?>[actions][<?= $recipient_key ?>][gateway]" class="gateway-selector">
				                                        <?php
				                                        $stored_gateway = !empty($stored_option['actions'][$recipient_key]['gateway'])
					                                        ? $stored_option['actions'][$recipient_key]['gateway'] : '' ;
				                                        $active_gateways = (array) get_option( 'tlr_active_gateways', [] );

				                                        if ( !empty($active_gateways) && !empty($active_gateways[0]) ) {
					                                        if ( in_array( $stored_gateway, $active_gateways ) ) {
						                                        $selected_gateway = $stored_gateway;
					                                        } else {
						                                        $selected_gateway = $active_gateways[0];
					                                        }
					                                        foreach ( $active_gateways as $active_gateway ) {
						                                        ?>
                                                                <option value="<?= $active_gateway ?>"<?php selected($selected_gateway, $active_gateway) ?>>
							                                        <?= ucfirst($active_gateway); ?>
                                                                </option>
						                                        <?php
					                                        }
				                                        } else {
					                                        ?>
                                                            <option selected="selected">
						                                        <?php esc_html_e('Configure a gateway first.', 'texteller'); ?>
                                                            </option>
					                                        <?php
				                                        }
				                                        ?>
                                                    </select>
                                                </div>
                                                <div class="action-field-wrap">
                                                    <label for="<?=$trigger_id; ?>-<?=$recipient_key?>-interface-select" class="action-field-label">
				                                        <?= esc_html__('Interface', 'texteller');?>
                                                    </label>
                                                    <select id="<?=$trigger_id?>-<?=$recipient_key?>-interface-select" name="<?=
			                                        $option_id ?>[actions][<?= $recipient_key ?>][interface]" class="interface-selector" aria-label="Select a gateway interface to use.">
				                                        <?php
                                                        if ( isset($selected_gateway) ) {
	                                                        $gateway_class = TLR\tlr_get_gateway_class($selected_gateway);
	                                                        if( $gateway_class ) {
		                                                        /** @var TLR\Interfaces\Gateway $gateway_class */
		                                                        $interfaces = $gateway_class::get_interfaces();
		                                                        $default_interface = $gateway_class::get_default_interface();
		                                                        $stored_interface = !empty($stored_option['actions'][$recipient_key]['interface'])
			                                                        ? $stored_option['actions'][$recipient_key]['interface'] : '';
		                                                        $selected_interface = in_array($stored_interface, array_keys($interfaces))
			                                                        ? $stored_interface : $default_interface;
		                                                        $content_types = $gateway_class::get_content_types();

		                                                        foreach ( $interfaces as $key => $title ) {
			                                                        $content_type = isset($content_types[$key])
				                                                        ? $content_types[$key] : 'text';
			                                                        ?><option value="<?= esc_attr($key) ?>" data-content-type="<?=
		                                                        $content_type ?>"<?php selected($selected_interface, $key)
			                                                        ?>><?= esc_html($title) ?></option><?php
		                                                        }
	                                                        }
                                                        } else {
	                                                        ?>
                                                            <option selected="selected">
		                                                        <?php esc_html_e('Configure a gateway first.', 'texteller'); ?>
                                                            </option>
	                                                        <?php
                                                        }

				                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="content-labels-wrap">
                                                <hr class="tlr-separator">
                                                <label for="<?=$trigger_id?>-<?=$recipient_key?>-content" class="action-field-label">
			                                        <?= esc_html__('Notification Content:', 'texteller');?>
                                                </label>
                                            </div>
                                            <div class="recipient-message-content">
		                                        <?php
		                                        foreach ( (array) $active_gateways as $active_gateway ) {
			                                        $active_gateway = TLR\tlr_get_gateway_class($active_gateway);
			                                        if ( $active_gateway
			                                             && method_exists( $active_gateway, 'get_gateway_options')
			                                        ) {
				                                        $stored_gateway_data = isset($stored_option['actions'][$recipient_key]['gateway_data'])
					                                        ? $stored_option['actions'][$recipient_key]['gateway_data'] : [];
				                                        $option_name = "{$option_id}[actions][$recipient_key][gateway_data]";
				                                        ?>
                                                        <div class="gateway-content">
					                                        <?php
					                                        $active_gateway::get_gateway_options($option_name, $stored_gateway_data);
					                                        ?>
                                                        </div>
				                                        <?php
			                                        }
		                                        }
		                                        ?>
                                                <div class="text-content">
                                            <textarea id='<?=$trigger_id?>-<?=$recipient_key?>-content'
                                                      name="<?=
                                                      $option_id ?>[actions][<?= $recipient_key ?>][content]"
                                                      class="tlr-text-content tlr-count" cols="40" rows="7"
                                                      data-sms-count-wrap="after"><?=
	                                            isset( $stored_option['actions'][ $recipient_key ]['content'] )
		                                            ? $stored_option['actions'][ $recipient_key ]['content'] : '';
	                                            ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                    <div class="content-tags-wrap">
                                        <?php
                                        if ( !empty( $option['params']['tags'] ) ) {
                                            ?>
	                                        <span><?=__('Available tags', 'texteller')?></span>
                                            <hr class="tlr-separator">
	                                        <div class="message-tags">
                                                <ul style="column-count: 3;">
                                                    <?php
                                                    foreach ( $option['params']['tags'] as $tag_id => $tag_title ) {
                                                        ?>
                                                        <li>
                                                            <span><?= esc_html($tag_title) ?>:</span>
                                                            <code class="tlr-tag">{<?= esc_html($tag_id) ?>}</code>
                                                        </li>
                                                        <?php
                                                    }
                                                    ?>
                                                </ul>
                                            </div>
                                        <?php
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
	    return ob_get_clean();
    }

	private static function generate_attribs( array $attribs ) : string
	{
		$html = '';
		foreach ( $attribs as $key => $att_value ) {
			$key = esc_attr( $key );
			$att_value = esc_attr( $att_value );
			$html .= " $key='$att_value'";
		}

		return $html;
	}
}