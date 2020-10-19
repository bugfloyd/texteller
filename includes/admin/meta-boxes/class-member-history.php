<?php

namespace Texteller\Admin\Meta_Boxes;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

class Member_History
{

	/**
	 * @param TLR\Registration_Module $register_member
	 */
	public static function render( TLR\Registration_Module $register_member )
	{
		$member_id = $register_member->get_member_field_value('id');
        if ( empty($member_id) ) {
            return;
        }

		// Query
		$args = [
			'object_type'   =>  'message',
			'member_ids'    =>  [ $member_id ]
		];
		$tlr_query = new TLR\Object_Query( $args );
		$messages_count = $tlr_query::get_messages_count( $member_id );

		if ( $messages_count > 0 ) {
			$messages = $tlr_query->get_messages( 10 );

			/**
			 * This hook is documented in Messages_List_table class
             *
             * @see \Texteller\Admin\Message_List_Table::set_triggers()
			 */
			$triggers = TLR\get_notification_triggers();
			?>
            <div class='tlr-panel-wrap texteller'>
                <div class="member-history-column member-history">
                    <table>
                        <colgroup><col><col><col><col></colgroup>
                        <tr>
                            <th><?= __( 'Trigger', 'texteller' ); ?></th>
                            <th><?= __( 'Content', 'texteller' ); ?></th>
                            <th><?= __( 'Gateway', 'texteller' ); ?></th>
                            <th><?= __( 'Interface', 'texteller' ); ?></th>
                            <th><?= __( 'Status', 'texteller' ); ?></th>
                            <th><?= __( 'Date', 'texteller' ); ?></th>
                        </tr>

						<?php
						foreach ( (array) $messages as $message ) {
							?>
                            <tr>
                                <td>
		                            <?= isset( $triggers[$message['message_trigger']] ) ?
                                        $triggers[$message['message_trigger']] : $message['message_trigger']; ?>
                                </td>
                                <td class="message-content"><?= $message['message_content']; ?></td>
                                <td><?= $message['message_gateway']; ?></td>
                                <td><?= $message['message_interface']; ?></td>
                                <td><?= $message['message_status']; ?></td>
                                <td><?= $message['message_date']; ?></td>

                            </tr>
							<?php
						}
						?>
                    </table>
                </div>
            </div>
			<?php
            if ( $messages_count > 0 ) {
                $displayed_count = $messages_count > 10 ? 10 : $messages_count;
                ?>
                <div class="table-description">
                    <p>
                        <?php
                        $messages_table_url = admin_url( 'admin.php?page=tlr-messages' );
                        $url = esc_url( add_query_arg( 'member_id', $member_id , $messages_table_url ) );
                        echo sprintf(
                                __( 'Viewing %d of %d messages.', 'texteller'),
                                $displayed_count,
                                $messages_count
                             );

                        if ( $messages_count > 10 ) {
                            echo ' | ' . sprintf(
		                            "<a href='$url'>%s</a>",
		                            __( 'View all, in messages table', 'texteller' )
	                            );
                        }
                        ?>
                    </p>
                </div>
                <?php
            }
        }

	}
}