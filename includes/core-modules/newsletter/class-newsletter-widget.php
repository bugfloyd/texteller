<?php

namespace Texteller\Core_Modules\Newsletter;

defined( 'ABSPATH' ) || exit;

class Newsletter_Widget extends \WP_Widget {

	function __construct()
	{
		parent::__construct(
			'TLR_Newsletter_Form',
			__('Text Newsletter', 'texteller'),
			[ 'description' => __( 'Registration form for Texteller text newsletter', 'texteller' ) ]
		);
	}

	public function widget( $args, $instance )
	{
		// before and after widget arguments are defined by themes
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) )
			echo $args['before_title'] . $instance['title'] . $args['after_title'];

		echo Registration::shortcode_generator();

		echo $args['after_widget'];
	}


	public function form( $instance )
	{
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'Text Newsletter', 'texteller' );
		}
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance )
	{
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		return $instance;
	}
}