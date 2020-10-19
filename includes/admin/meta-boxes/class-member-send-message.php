<?php

namespace Texteller\Admin\Meta_Boxes;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

class Member_Send_Message
{
	/**
	 * @param TLR\Registration_Module $register_member
	 */
	public static function render( TLR\Registration_Module $register_member )
	{
	    $member = $register_member->get_member();
		if ( empty( $member->get_mobile() ) || $member->is_canceled() ) {
		    return;
		}
		?>
		<div class="send-message">
			<div class="message-content-wrap">
				<label for="message-content"><?= __( 'Message Content', 'texteller' ) ?></label>
                <textarea id="message-content" class="tlr-count"></textarea>
                <label for="message-gateway"><?= __( 'Gateway', 'texteller' ) ?></label>
                <select id="message-gateway" style="width:100%;">
                    <?php
                    $gateways = (array) get_option( 'tlr_active_gateways', [] );
                    foreach ( $gateways as $gateway ) {
                        ?>
                        <option value="<?= $gateway; ?>"><?= ucfirst( $gateway ); ?></option>
                        <?php
                    }
                    ?>
                </select>
                <button id="send-message" name="send-message" style="margin-top:10px;width:100%;" disabled><?= __( 'Send Message', 'texteller'); ?></button>
				<span class="send-result"></span>
			</div>
		</div>
		<?php
	}
}
