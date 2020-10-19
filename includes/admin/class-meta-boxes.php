<?php
namespace Texteller;
defined( 'ABSPATH' ) || exit;



class Meta_Boxes
{
    private static $notice_codes;

	public static function init( string $edit_page )
	{



		/*add_action( 'init', function(){
			if ( !isset($_GET['tlr_notices']) ) {
				return;
			}
			self::$notice_codes = $_GET['tlr_notices'];
		});
		add_action( 'admin_notices', [self::class, 'admin_notice'], 10, 2 );*/


	}

	public static function admin_notice()
    {
        if ( empty( self::$notice_codes ) ) {
            return;
        }

        $messages = [
            101 =>  __('Please enter a valid mobile number', 'texteller'),
            102 =>  __('This mobile number is linked to an existing member', 'texteller'),
            103 =>  __('Please enter a valid email address', 'texteller'),
            104 =>  __('The selected user is already linked to another member', 'texteller'),
            105 =>  __('An error occurred while trying to link the user to this member. Please try again', 'texteller'),
        ];

	    $error_codes = self::$notice_codes;
	    $error_codes = explode(',', $error_codes);

        foreach ($error_codes as $error_code) {
            $message = isset($messages[$error_code]) ? $messages[$error_code] : '';
	        echo $message ? "<div class='notice notice-error is-dismissible'><p>$message</p></div>" : '';
        }

	    self::$notice_codes = '';
	}

	/*public static function save_member( $member )
	{
		//$errors = Meta_Box_Member_Data::save( $member, $member_update, $errors );
		//$errors = Meta_Box_Member_Actions::save( $member, $member_update, $errors );  todo

        add_filter( 'redirect_post_location', function( string  $location, int $postID ) use ( $post_id, $errors) {
            if ( $postID != $post_id ) return $location;
	        if ( !empty($errors) ) {
		        $error_codes = implode(',', $errors );
		        $redirect = add_query_arg( 'tlr_notices', $error_codes, $location );
		        return $redirect;
	        } else {
		        $redirect = remove_query_arg( 'tlr_notices', $location );
		        return $redirect;
            }
        }, 10, 2 );
	}*/
}