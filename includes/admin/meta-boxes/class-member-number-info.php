<?php

namespace Texteller\Admin\Meta_Boxes;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

class Member_Number_Info
{
	public static function render( TLR\Registration_Module $register_member )
	{
		if ( $register_member->get_member_field_value('id') > 0 && ! empty( $register_member->get_member_field_value('mobile') ) ) {

			$number_data = TLR\tlr_get_number_info( $register_member->get_member_field_value('mobile') );
			?>
			<div class="member-number-info-wrap">
				<p>
					<label for="tlr_region_code"><?= __('Region Code', 'texteller')?></label>
					<span id="tlr_region_code" class="tlr-data-fields"><?= $number_data['region_code']; ?></span>
				</p>
				<p>
					<label for="tlr_country_code"><?= __('Country Code', 'texteller')?></label>
					<span id="tlr_country_code" class="tlr-data-fields"><?= $number_data['country_code']; ?></span>
				</p>
				<p>
					<label for="tlr_national_number"><?= __('National Number', 'texteller')?></label>
					<span id="tlr_national_number" class="tlr-data-fields tlr-ltr-field"><?= $number_data['national_number']; ?></span>
				</p>
				<p>
					<label for="tlr_number_type"><?= __('Number Type', 'texteller')?></label>
					<span id="tlr_number_type" class="tlr-data-fields"><?= $number_data['type']; ?></span>
				</p>
				<p>
					<label for="tlr_area_desc"><?= __('Area Description', 'texteller')?></label>
					<span id="tlr_area_desc" class="tlr-data-fields"><?= $number_data['area_desc']; ?></span>
				</p>
				<p>
					<label for="tlr_carrier"><?= __('Carrier', 'texteller')?></label>
					<span id="tlr_carrier" class="tlr-data-fields"><?= $number_data['carrier']; ?></span>
				</p>
				<p>
					<label for="tlr_time_zones"><?= __('Time Zones', 'texteller')?></label>
					<span id="tlr_time_zones" class="tlr-data-fields"><?= implode( ', ', $number_data['time_zones'] ); ?></span>
				</p>
			</div>
			<?php
		}
	}
}