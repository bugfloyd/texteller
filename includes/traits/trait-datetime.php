<?php

namespace Texteller\Traits;
use Texteller as TLR;

defined( 'ABSPATH' ) || exit;

trait DateTime {

	/**
	 * @var \IntlDateFormatter $datetime_formatter
	 */
	private static $datetime_formatter = null;
	private static $GMTDateTimeZone = null;

	protected static function init_datetime_formatter()
	{
		if ( !  class_exists('\IntlCalendar' ) ) {
			return;
		}

		$locale = get_locale();
		$blogDateTimeZone = wp_timezone();
		$calendar_type = get_option( 'tlr_calendar_type', 'gregorian' );

		$calendar = \IntlCalendar::createInstance(
			$blogDateTimeZone,
			"$locale@calendar=$calendar_type"
		);

		self::$datetime_formatter = \IntlDateFormatter::create(
			$locale,
			\IntlDateFormatter::MEDIUM,
			\IntlDateFormatter::SHORT,
			$blogDateTimeZone,
			$calendar
		);
		self::$GMTDateTimeZone = new \DateTimeZone('GMT');
	}

	protected static function format_datetime( $DateTime ) : string
	{
		if ( empty(self::$datetime_formatter) || empty(self::$GMTDateTimeZone) ) {
			return is_a($DateTime, 'DateTime') ? $DateTime->format('Y-m-d H:i:s') : $DateTime;
		}

		if ( ! is_a($DateTime, '\DateTime') ) {
			try {
				$DateTime = new \DateTime($DateTime, self::$GMTDateTimeZone);
			} catch (\Exception $e) {
				TLR\tlr_write_log($e->getMessage());
				return $DateTime;
			}
		}
		$converted_datetime = self::$datetime_formatter->format($DateTime);
		return $converted_datetime ? $converted_datetime : $DateTime;
	}
}