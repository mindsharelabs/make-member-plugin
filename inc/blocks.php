<?php
/**
* Register custom Gutenberg blocks category
*
*/
add_filter('block_categories_all', function ($categories, $post) {
	return array_merge(
		$categories,
		array(
			array(
				'slug' 	=> 'make-blocks',
				'title' => __('MAKE Santa Fe Blocks', 'mindshare'),
				// 'icon' 	=> file_get_contents(CCA_ABSPATH . 'inc/img/logo-sm.svg'),
			),

		)
	);
}, 10, 2);




add_action('acf/init', function () {

	if( function_exists('acf_register_block_type')) :


		acf_register_block_type(array(
			'name'              => 'make-member-sign-in',
			'title'             => __('Make Member Sign In'),
			'description'       => __('A block that allows a user to sign in.'),
			'render_template'   => MAKESF_ABSPATH . '/inc/templates/make-member-sign-in.php',
			'category'          => 'make-blocks',
			// 'icon'              => file_get_contents(MAKESF_URL . 'inc/img/logo-sm.svg'),
			'keywords'          => array( 'make', 'sign', 'member', 'mind', 'Mindshare'),
			'align'             => 'full',
			'mode'            	=> 'edit',
			'multiple'          => false,
			'supports'					=> array(
				'align' => false,
			),
			'enqueue_assets' => function(){
				// We're just registering it here and then with the action get_footer we'll enqueue it.
				wp_register_style( 'make-block-styles', MAKESF_URL . 'css/style.css', array(),  MAKESF_PLUGIN_VERSION);
				add_action( 'get_footer', function () {wp_enqueue_style('make-block-styles');});

				wp_register_script('list-min-js', MAKESF_URL . 'assets/js/list.min.js', array('jquery'), MAKESF_PLUGIN_VERSION, false);
				wp_enqueue_script('list-min-js');

				wp_register_script('make-sign-in-scripts', MAKESF_URL . 'assets/js/make-member-sign-in.js', array('jquery', 'list-min-js'), MAKESF_PLUGIN_VERSION, true);
				wp_enqueue_script('make-sign-in-scripts');
				wp_localize_script( 'make-sign-in-scripts', 'makeMember', array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'postID' => get_the_id(),
					'data' => array()
				));


			})
		);


		acf_register_block_type(array(
			'name'              => 'make-badge-list',
			'title'             => __('Badge List'),
			'description'       => __('A block that displays all the Badges in a card-list format.'),
			'render_template'   => MAKESF_ABSPATH . '/inc/templates/make-badge-list.php',
			'category'          => 'make-blocks',
			// 'icon'              => file_get_contents(MAKESF_URL . 'inc/img/logo-sm.svg'),
			'keywords'          => array( 'badge', 'list', 'diciplines', 'make', 'mind', 'Mindshare'),
			'align'             => 'full',
			'mode'            	=> 'edit',
			'multiple'          => false,
			'supports'					=> array(
				'align' => false,
			),
			'enqueue_assets' => function(){
				// We're just registering it here and then with the action get_footer we'll enqueue it.
				wp_register_style( 'make-block-styles', MAKESF_URL . 'css/style.css', array(),  MAKESF_PLUGIN_VERSION);
				add_action( 'get_footer', function () {wp_enqueue_style('make-block-styles');});


			})
		);



	endif;
});







add_action( 'acf/include_fields', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'key' => 'group_658da62ab6c8a',
		'title' => 'Block: Badge List',
		'fields' => array(
			array(
				'key' => 'field_658da62b7a6d1',
				'label' => 'Make Badge List',
				'name' => 'make_badge_list',
				'aria-label' => '',
				'type' => 'group',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'layout' => 'block',
				'sub_fields' => array(
					array(
						'key' => 'field_658da63e7a6d2',
						'label' => 'Badges',
						'name' => 'badges',
						'aria-label' => '',
						'type' => 'relationship',
						'instructions' => 'If nothing is selected all Badges will display.',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'post_type' => array(
							0 => 'certs',
						),
						'post_status' => '',
						'taxonomy' => '',
						'filters' => array(
							0 => 'search',
						),
						'return_format' => 'id',
						'min' => '',
						'max' => '',
						'elements' => '',
						'bidirectional' => 0,
						'bidirectional_target' => array(
						),
					),
				),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'block',
					'operator' => '==',
					'value' => 'acf/make-badge-list',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	) );
} );

