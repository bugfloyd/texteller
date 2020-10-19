<?php
namespace Texteller;
defined( 'ABSPATH' ) || exit;

class Member_Group
{
	/**
	 * Hook in methods.
	 */
	public static function init()
	{
		add_action( 'init', [self::class, 'register_member_group_taxonomy'], 10 );
		add_action( 'admin_footer-edit-tags.php', [self::class, 'change_fields_description'] );
	}

	/**
	 * Register `member_group` taxonomy
	 * This should be done after registering CPT
	 * `default_member_group` is inserted in tlr-registration module
	 *
	 * @access public
	 * @wp-hook init
	 * @return void
	 */
	public static function register_member_group_taxonomy()
	{
		//todo: add meta_box_cb & meta_box_sanitize_cb to args
		// todo: add usser role and related caps and use them here
		$tax_args = [
			'description'           =>  'گروه های کاربری',
			'public'                =>  true,
			'show_ui'               =>  true,
			'publicly_queryable'    =>  false,
			'show_tagcloud'         =>  false,
			'show_in_quick_edit'    =>  false,
			'hierarchical'          =>  true,
			'show_in_menu'          =>  false,  //it was done manually in admin menu
			'show_in_nav_menus'     =>  false,
			'rewrite'               =>  false,
			'update_count_callback' =>  [self::class, 'update_member_tax_term_count'],
			'meta_box_cb'           =>  [self::class, 'member_group_meta_box'],
			'capabilities'          =>  [
				'manage_terms'  =>  'manage_categories',
				'edit_terms'    =>  'manage_categories',
				'delete_terms'  =>  'manage_categories',
				'assign_terms'  =>  'edit_posts'
			],
			'labels'                        =>  [
				'name'                      =>  __('Member Groups', 'texteller'),
				'singular_name'             =>  __('Member Group', 'texteller'),
				'all_items'                 =>  __('All member groups', 'texteller'),
				'edit_item'                 =>  __('Edit member group', 'texteller'),
				'view_item'                 =>  __('View member group', 'texteller'),
				'update_item'               =>  __('Update member group', 'texteller'),
				'add_new_item'              =>  __('Add a new member group', 'texteller'),
				'new_item_name'             =>  __('Member group name', 'texteller'),
				'parent_item'               =>  __('Parent member group', 'texteller'),
				'parent_item_colon'         =>  __('Parent member group:', 'texteller'),
				'search_items'              => __('Search member groups', 'texteller'),
				'popular_items'             =>  __('popular member groups', 'texteller'),
				'separate_items_with_commas'=>  __('Separate member groups with commas', 'texteller'),
				'add_or_remove_items'       =>  __('Add or remove member groups', 'texteller'),
				'choose_from_most_used'     =>  __('Choose from most used groups', 'texteller'),
				'not_found'                 =>  __('No member groups found', 'texteller'),
				'back_to_items'             =>  __('Back to member groups', 'texteller'),

			]
		];
		register_taxonomy( 'member_group', 'TLR_Member', $tax_args );

		// Add term-meta to display member-groups based on it's value, on the site front
		register_meta(
		        'term',
                'tlr_is_public',
                [
                        'type'              =>  'integer',
                        'description'       =>  'If member group should be displayed publicly for the site users.',
                        'single'            =>  true,
                        'sanitize_callback' =>  [self::class, 'sanitize_member_group_public_meta']
                ]
        );
		add_action( 'member_group_add_form_fields', [self::class, 'add_member_group_meta'] );
		add_action( 'member_group_edit_form_fields', [self::class, 'edit_member_group_meta'] );
		add_action( 'edit_member_group', [self::class, 'save_member_group_meta'] );
		add_action( 'create_member_group', [self::class, 'save_member_group_meta'] );

		add_filter( 'manage_edit-member_group_columns', [self::class, 'taxonomy_member_column' ] );
		add_action( 'manage_member_group_custom_column', [self::class, 'taxonomy_member_column_values' ], 10, 3 );
		add_filter( 'parent_file', [self::class, 'taxonomy_set_current_menu'] );
	}

	public static function sanitize_member_group_public_meta( $value )
    {
	    return $value ? 1 : 0;
    }

    public static function add_member_group_meta()
    {
	    wp_nonce_field( 'tlr_is_member_group_public', 'tlr_check' ); ?>
        <div class="form-field term-meta-text-wrap" style="display: flex">
            <input type="checkbox" name="term_is_public" id="term-is-public" value="1" style="margin: auto 10px;">
            <label for="term-is-public"><?php esc_attr_e( "Display this member group on frontend member registration forms", 'texteller' ); ?></label>
        </div>
	    <?php
    }

    public static function edit_member_group_meta( $term )
    {
	    $value = (int) get_term_meta( $term->term_id, 'tlr_is_public', true );
	    ?>

        <tr class="form-field term-meta-text-wrap">
            <th scope="row">
                <span><?php _e( "Public member group", 'texteller' ); ?></span>
            </th>
            <td>
			    <?php wp_nonce_field( 'tlr_is_member_group_public', 'tlr_check' ); ?>
                <input type="checkbox" name="term_is_public" id="term-is-public" value="1" style="margin: auto 10px;"<?php checked( 1, $value, true ); ?>>
                <label for="term-is-public"><?php _e( "Should this member group displayed for the users on the site's front?", 'texteller' ); ?></label>
            </td>
        </tr>
	    <?php
    }

    public static function save_member_group_meta( $term_id )
    {
	    if ( ! isset( $_POST['tlr_check'] ) || ! wp_verify_nonce( $_POST['tlr_check'], 'tlr_is_member_group_public' ) ) {
	        return;
	    }

	    $old_value  = (int) get_term_meta( $term_id, 'tlr_is_public', true );
	    $new_value = isset( $_POST['term_is_public'] ) ? (int) ( $_POST['term_is_public'] ) : 0;

	    if ( $old_value && 0 === $new_value ) {
		    delete_term_meta( $term_id, 'tlr_is_public' );
	    } else if ( $old_value !== $new_value ) {
		    update_term_meta( $term_id, 'tlr_is_public', $new_value );
	    }
    }


	/**
	 * @param Registration_Module $register_member
	 */
	public static function member_group_meta_box( $register_member )
	{

	    //todo : read posted data

		$tax_name = 'member_group';
		$taxonomy = get_taxonomy( $tax_name );
		?>
		<div id="taxonomy-<?php echo $tax_name; ?>" class="categorydiv">
			<ul id="<?php echo $tax_name; ?>-tabs" class="category-tabs">
				<li class="tabs"><a href="#<?php echo $tax_name; ?>-all"><?php echo $taxonomy->labels->all_items; ?></a></li>
				<li class="hide-if-no-js"><a href="#<?php echo $tax_name; ?>-pop"><?php echo esc_html( $taxonomy->labels->most_used ); ?></a></li>
			</ul>

			<div id="<?php echo $tax_name; ?>-pop" class="tabs-panel" style="display: none;">
				<ul id="<?php echo $tax_name; ?>checklist-pop" class="categorychecklist form-no-clear" >
					<?php $popular_ids = wp_popular_terms_checklist( $tax_name ); ?>
				</ul>
			</div>

			<div id="<?php echo $tax_name; ?>-all" class="tabs-panel">
				<?php
				$name = ( $tax_name == 'category' ) ? 'post_category' : 'tax_input[' . $tax_name . ']';
				echo "<input type='hidden' name='{$name}[]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
				?>
				<ul id="<?php echo $tax_name; ?>checklist" data-wp-lists="list:<?php echo $tax_name; ?>" class="categorychecklist form-no-clear">
					<?php
					wp_terms_checklist(
						$register_member->get_member_field_value('id'),
						array(
							'taxonomy'     => $tax_name,
							'popular_cats' => $popular_ids,
						)
					);
					?>
				</ul>
			</div>
		</div>
		<?php
	}

	public static function taxonomy_set_current_menu( $parent_file )
	{
		global $current_screen;

		if ( 'member_group' === $current_screen->taxonomy ) {
			$parent_file = 'texteller';
		}

		return $parent_file;
	}

	public static function update_member_tax_term_count( array $terms )
	{
		global $wpdb;

		$tlr_members = $wpdb->prefix . 'tlr_members';

		foreach ( (array) $terms as $term ) {
			$count = 0;
			$count += (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $tlr_members WHERE ID IN (SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d ) AND ( $tlr_members.status = 'registered' OR $tlr_members.status = 'verified' )", $term
			) );
			// todo: count for parents
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
		}

		clean_term_cache( $terms, 'member_group', false );
	}

	/**
	 * Correct the column names for taxonomy edit page
	 * Need to replace "Posts" with "Members"
	 *
	 * @param array $columns
	 *
	 * @return array $columns
	 */
	public static function taxonomy_member_column($columns)
    {
		unset($columns['posts']);
		$columns['members']	= __('Members');
		$columns['is_public'] = __( 'Is public', 'texteller' );

		return $columns;
	}

	/**
	 * Set values for custom columns in member taxonomies
	 *
	 * @param string $display
	 * @param string $column
	 * @param int $term_id
	 *
	 * @return void
	 */
	public static function taxonomy_member_column_values( $display, $column, $term_id )
	{
		if( 'members' === $column ) {
			$term	= get_term($term_id, $_REQUEST['taxonomy']);
			$url = esc_url( add_query_arg( ['member_group'=> $term_id], admin_url('admin.php?page=tlr-members' ) ) );
			echo "<a href='$url'>" . $term->count . "</a>";
		}
		if ( 'is_public' === $column ) {
			$value  = (int) get_term_meta( $term_id,'tlr_is_public', true );
			$value = $value ? __('Yes') : __('No');
			echo sprintf( '<span>%s</div>', esc_attr( $value ) );
		}
	}

	public static function change_fields_description()
	{
		$screen = get_current_screen();
		if ($screen && $screen->id === 'edit-member_group' && $screen->taxonomy === 'member_group') {
		    $note = '<b>' . esc_html__('Note:', 'texteller') . ' </b>'. esc_html__('Value assigned to this slug will be used as the value attribute for member groups field, on the member registration forms.','texteller') . ' ' . esc_html__('Only English letters, digits, underscores, and hyphens are allowed.','texteller');
		    $note2 = esc_html__( "This is only for admins' reference.", 'texteller' );
			echo "<script>jQuery(document).ready(function($)
		        {
		            $('.term-slug-wrap p').html('". $note . "');
		            $('.term-description-wrap p').html('" . $note2 . "');
		        });</script>";
		}
	}
}