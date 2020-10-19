<?php
namespace Texteller;
defined( 'ABSPATH' ) || exit;

class Cron_Events
{
	public static function init()
	{
		if (wp_doing_ajax()) return;
		//self::schedule_sync_event();
		//add_action( 'tlr_sync_data', [ __CLASS__, 'cron_data_sync' ] );
		//add_action( 'update_option_tlr_sync_period', [ __CLASS__, 'sync_period_updated' ], 10, 1 );
	}

	public static function sync_period_updated($new_value)
	{
		wp_clear_scheduled_hook( 'tlr_sync_data' );
		if ( !wp_next_scheduled( 'tlr_sync_data' ) ) {
			wp_schedule_event( time(), $new_value.'mins', 'tlr_sync_data' );
		}
		return $new_value;

	}

	private static function schedule_sync_event()
	{
		$period = get_option('tlr_sync_period');
		$period = $period ? $period : 10;

		$recurrence['period'] = $period * 60; //get seconds
		$recurrence['title'] = $period . 'mins';


		add_filter('cron_schedules', function ($schedules) use ($recurrence) {
			return self::set_cron_recurrence($schedules, $recurrence);
		});

		if ( !wp_next_scheduled( 'tlr_sync_data' ) ) {
			wp_schedule_event( time(), $recurrence['title'], 'tlr_sync_data' );
		}
	}

	private static function set_cron_recurrence($schedules, $recurrence)
	{
		$title = $recurrence['title'];
		if( !isset($schedules[$title]) ) {
			$schedules[$title] = array(
				'display' => "Every {$recurrence['title']}",
				'interval' => $recurrence['period']
			);
		}
		return $schedules;
	}

	public static function cron_data_sync ()
	{
		$last_cron_date = get_option('tlr_last_cron_time');
		if ( !$last_cron_date ) {
			$last_cron_date = "2019-01-01 00:00:00";
		}

		$now = current_time('Y-m-d H:i:s');
		$now = str_replace(' ', 'T', $now);

		//getting new received messages
		// note: credit would be updated in the Webservice class initialization.
		self::update_inbox($last_cron_date, $now);
		update_option('tlr_last_cron_time', $now);
	}

	private static function update_inbox($dateFrom, $dateTo)
	{
		$webservice = new Gateway_Manager();
		$messages = $webservice->receive($dateFrom, $dateTo);

		if (is_object($messages)) {

			$messages = isset($messages->MessagesBL) ? $messages->MessagesBL : [];

			if (is_array($messages) && !empty($messages)) {

				foreach($messages as $message) {
					$date = $message->SendDate;

					//$date = Prepare_Text::jalali_date($date, "yyyy-MM-dd HH:mm:ss", 'en');

					$message_array = [
						'category'      => 'inbox',
						'recipient'     => $message->Receiver,
						'date'          => $date,
						'status'        => 'received',
						'gateway'       => 'tlr_dedicated',
						'content'       => $message->Body,
						'gateway_data'  => [ 'sender' => $message->Sender, 'tlr_id' => $message->MsgID ],
					];

					$sms = new Message( $message_array );
					$sms->save();
				}
			} elseif (is_object($messages)) {
				$date = $messages->SendDate;
				//$date = Prepare_Text::jalali_date($date, "yyyy-MM-dd HH:mm:ss", 'en');

				$message_array = [
					'category'      => 'inbox',
					'recipient'     => $messages->Receiver,
					'date'          => $date,
					'status'        => 'received',
					'gateway'       => 'tlr_dedicated',
					'content'       => $messages->Body,
					'gateway_data'  => [ 'sender' => $messages->Sender, 'tlr_id' => $messages->MsgID ],
				];

				$sms = new Message( $message_array );
				$sms->save();
			}
		}
	}
}

Cron_Events::init();