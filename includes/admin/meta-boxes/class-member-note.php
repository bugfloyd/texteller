<?php

namespace Texteller\Admin\Meta_Boxes;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

class Member_Note
{
	public static function render( TLR\Registration_Module $register_member ) {
		?>
		<label class="screen-reader-text" for="tlr_description"><?= __( 'Member Note', 'texteller'); ?></label>
        <textarea name="tlr_description" id="tlr_description"><?php echo esc_html( $register_member->get_form_data('description') ); ?></textarea>
		<p>
			<?= __("You can write some notes about this member. This note is only for your reference and won't be displayed anywhere else on the site.", 'texteller'); ?>
		</p>
		<?php
	}
}