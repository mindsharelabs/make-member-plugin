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
				'icon' 	=> MAKE_LOGO,
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
			'icon'              => MAKE_LOGO,
			'keywords'          => array( 'make', 'sign', 'member', 'mind', 'Mindshare'),
			'align'             => 'full',
			'mode'            	=> 'edit',
			'multiple'          => false,
			'supports'					=> array(
				'align' => false,
			),
			'enqueue_assets' => function(){
				// Only enqueue scripts on frontend pages, not admin pages
				if (is_admin()) {
					return;
				}
				
				// We're just registering it here and then with the action get_footer we'll enqueue it.
				wp_register_style( 'make-block-styles', MAKESF_URL . 'assets/css/style.css', array(),  MAKESF_PLUGIN_VERSION);
				add_action( 'get_footer', function () {wp_enqueue_style('make-block-styles');});

				// Volunteer styles
				wp_register_style( 'make-volunteer-styles', MAKESF_URL . 'assets/css/volunteer.css', array('make-block-styles'),  MAKESF_PLUGIN_VERSION);
				add_action( 'get_footer', function () {wp_enqueue_style('make-volunteer-styles');});

				// Use unified sign-in JavaScript implementation
				wp_register_script('list-min-js', MAKESF_URL . 'assets/js/list.min.js', array('jquery'), MAKESF_PLUGIN_VERSION, false);
				add_action( 'get_footer', function () {wp_enqueue_script('list-min-js');});

				wp_register_script('make-sign-in-scripts', MAKESF_URL . 'assets/js/make-member-sign-in-unified.js', array('jquery', 'list-min-js'), MAKESF_PLUGIN_VERSION, true);
				add_action( 'get_footer', function () {wp_enqueue_script('make-sign-in-scripts');});

				// Volunteer scripts
				wp_register_script('make-volunteer-scripts', MAKESF_URL . 'assets/js/volunteer.js', array('jquery', 'make-sign-in-scripts'), MAKESF_PLUGIN_VERSION, true);
				add_action( 'get_footer', function () {wp_enqueue_script('make-volunteer-scripts');});

				// Defer script localization to footer to ensure scripts are enqueued first
				add_action( 'get_footer', function () {
					wp_localize_script( 'make-sign-in-scripts', 'makeMember', array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'postID' => get_the_id(),
						'volunteer_nonce' => wp_create_nonce('makesf_volunteer_nonce'),
						'signin_nonce' => wp_create_nonce('makesf_signin_nonce'),
						'config' => MakeSF_Config::get_js_config(),
						'data' => array()
					));
				});
			})
		);

		acf_register_block_type(array(
			'name'              => 'make-upcoming-events',
			'title'             => __('Upcoming Events'),
			'description'       => __('A block that displays upcoming events with available tickets.'),
			'render_template'   => MAKESF_ABSPATH . '/inc/templates/make-upcoming-events.php',
			'category'          => 'make-blocks',
			'icon'              => MAKE_LOGO,
			'keywords'          => array( 'make', 'events', 'upcoming', 'tickets', 'mind', 'Mindshare'),
			'align'             => 'full',
			'mode'            	=> 'edit',
			'multiple'          => false,
			'supports'			=> array(
				'align' => false,
			),
			'enqueue_assets' => function(){
				// We're just registering it here and then with the action get_footer we'll enqueue it.
				wp_register_style( 'make-block-styles', MAKESF_URL . 'assets/css/style.css', array(),  MAKESF_PLUGIN_VERSION);
				add_action( 'get_footer', function () {wp_enqueue_style('make-block-styles');});


			})
		);


		acf_register_block_type(array(
			'name'              => 'make-blog-categories',
			'title'             => __('Blog Category List'),
			'description'       => __('A block that displays a selection of blog categories.'),
			'render_template'   => MAKESF_ABSPATH . '/inc/templates/make-blog-categories.php',
			'category'          => 'make-blocks',
			'icon'              => MAKE_LOGO,
			'keywords'          => array( 'blog', 'categories', 'posts', 'mind', 'Mindshare'),
			'align'             => 'full',
			'mode'            	=> 'edit',
			'multiple'          => false,
			'supports'					=> array(
				'align' => false,
			),
			'enqueue_assets' => function(){
				// We're just registering it here and then with the action get_footer we'll enqueue it.
				wp_register_style( 'make-block-styles', MAKESF_URL . 'assets/css/style.css', array(),  MAKESF_PLUGIN_VERSION);
				add_action( 'get_footer', function () {wp_enqueue_style('make-block-styles');});


			})
		);


		acf_register_block_type(array(
			'name'              => 'make-badge-list',
			'title'             => __('Badge List'),
			'description'       => __('A block that displays all the Badges in a card-list format.'),
			'render_template'   => MAKESF_ABSPATH . '/inc/templates/make-badge-list.php',
			'category'          => 'make-blocks',
			'icon'              => MAKE_LOGO,
			'keywords'          => array( 'badge', 'list', 'diciplines', 'make', 'mind', 'Mindshare'),
			'align'             => 'full',
			'mode'            	=> 'edit',
			'multiple'          => false,
			'supports'					=> array(
				'align' => false,
			),
			'enqueue_assets' => function(){
				// We're just registering it here and then with the action get_footer we'll enqueue it.
				wp_register_style( 'make-block-styles', MAKESF_URL . 'assets/css/style.css', array(),  MAKESF_PLUGIN_VERSION);
				add_action( 'get_footer', function () {wp_enqueue_style('make-block-styles');});


			})
		);


		acf_register_block_type(array(
			'name'              => 'make-image-slider',
			'title'             => __('Image Slider'),
			'description'       => __('Simple image slider with optional arrows and dots.'),
			'render_template'   => MAKESF_ABSPATH . '/inc/templates/make-image-slider.php',
			'category'          => 'make-blocks',
			'icon'              => MAKE_LOGO,
			'keywords'          => array( 'image', 'slider', 'gallery', 'slideshow', 'make', 'mind', 'Mindshare' ),
			'post_types' 				=> array('post', 'page', 'exhibits', 'tribe_events'),
			'align'             => 'full', //The default block alignment. Available settings are “left”, “center”, “right”, “wide” and “full”. Defaults to an empty string.
			// 'align_text'        => 'left', //The default block text alignment (see supports setting for more info). Available settings are “left”, “center” and “right”.
			// 'align_content'     => 'left', //The default block content alignment (see supports setting for more info). Available settings are “top”, “center” and “bottom”. When utilising the “Matrix” control type, additional settings are available to specify all 9 positions from “top left” to “bottom right”.
			'mode'            	=> 'edit',
			'supports'					=> array(
				'align' => false,
				'align_text' => true,
				'align_content' => false,
				'full_height' => false,
				'mode' => false,
				'multiple' => false,
				'jsx' => false
			),
			'enqueue_assets' => function(){
				// We're just registering it here and then with the action get_footer we'll enqueue it.
				wp_register_style( 'make-block-styles', MAKESF_URL . 'assets/css/style.css' );
				add_action( 'get_footer', function () {wp_enqueue_style('make-block-styles');});

				wp_register_style( 'make-slick-theme', MAKESF_URL . 'assets/css/slick-theme.css' );
				add_action( 'get_footer', function () {wp_enqueue_style('make-slick-theme');});



				wp_register_script('make-slick-slider', MAKESF_URL . 'assets/js/slick.min.js', array('jquery'), MAKESF_PLUGIN_VERSION, true);
				wp_enqueue_script('make-slick-slider');

				wp_register_script('make-slider-init', MAKESF_URL . 'assets/js/image-slider-init.js', array('jquery', 'make-slick-slider'), MAKESF_PLUGIN_VERSION, true);
				wp_enqueue_script('make-slider-init');
	

				},
			)
		);



		acf_register_block_type(array(
			'name'              => 'make-instructor-bios',
			'title'             => __('Instructor Bios'),
			'description'       => __('A block that displays a bio for each instructor configured with the event.'),
			'render_template'   => MAKESF_ABSPATH . '/inc/templates/make-instructor-bios.php',
			'category'          => 'make-blocks',
			'icon'              => MAKE_LOGO,
			'keywords'          => array( 'instructor', 'bios','bio', 'make', 'mind', 'Mindshare'),
			'align'             => 'full',
			'mode'            	=> 'preview',
			'multiple'          => false,
			'supports'					=> array(
				'align' => false,
			),
			'enqueue_assets' => function(){
				// We're just registering it here and then with the action get_footer we'll enqueue it.
				wp_register_style( 'make-block-styles', MAKESF_URL . 'assets/css/style.css', array(),  MAKESF_PLUGIN_VERSION);
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
		'key' => 'group_659c46218279b',
		'title' => 'Block: Categories List',
		'fields' => array(
			array(
				'key' => 'field_659c4621c3857',
				'label' => 'Category List',
				'name' => 'make_category_list',
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
						'key' => 'field_659c4635c3858',
						'label' => 'Categories',
						'name' => 'categories',
						'aria-label' => '',
						'type' => 'taxonomy',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'taxonomy' => 'category',
						'add_term' => 0,
						'save_terms' => 0,
						'load_terms' => 0,
						'return_format' => 'id',
						'field_type' => 'checkbox',
						'bidirectional' => 0,
						'multiple' => 0,
						'allow_null' => 0,
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
					'value' => 'acf/make-blog-categories',
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
	acf_add_local_field_group( array(
		'key' => 'group_66e068f1622ea',
		'title' => 'BLOCK: Make Upcoming Events',
		'fields' => array(
			array(
				'key' => 'field_66e068f14684e',
				'label' => 'Make Upcoming Events',
				'name' => 'make_upcoming_events',
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
						'key' => 'field_66e069044684f',
						'label' => 'Event Categories',
						'name' => 'event_categories',
						'aria-label' => '',
						'type' => 'taxonomy',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'taxonomy' => 'event_category',
						'add_term' => 0,
						'save_terms' => 0,
						'load_terms' => 0,
						'return_format' => 'id',
						'field_type' => 'checkbox',
						'allow_in_bindings' => 0,
						'bidirectional' => 0,
						'multiple' => 0,
						'allow_null' => 0,
						'bidirectional_target' => array(
						),
					),
					array(
						'key' => 'field_66e06b98e9fa0',
						'label' => 'Number of Events to Display',
						'name' => 'num_events',
						'aria-label' => '',
						'type' => 'number',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => 3,
						'min' => '',
						'max' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'step' => '',
						'prepend' => '',
						'append' => '',
					),
					array(
						'key' => 'field_66e10489a0344',
						'label' => 'Button Labels',
						'name' => 'button_labels',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => 'Learn More',
						'maxlength' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
					array(
						'key' => 'field_66e1052a571c4',
						'label' => 'Show Excerpt',
						'name' => 'show_excerpt',
						'aria-label' => '',
						'type' => 'true_false',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'message' => '',
						'default_value' => 1,
						'allow_in_bindings' => 0,
						'ui_on_text' => 'Show',
						'ui_off_text' => 'Hide',
						'ui' => 1,
					),
					array(
						'key' => 'field_66e367qadfa3c4',
						'label' => 'Show Price',
						'name' => 'show_price',
						'aria-label' => '',
						'type' => 'true_false',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'message' => '',
						'default_value' => 1,
						'allow_in_bindings' => 0,
						'ui_on_text' => 'Show',
						'ui_off_text' => 'Hide',
						'ui' => 1,
					),
					array(
						'key' => 'field_66e10587e6bc1',
						'label' => 'Show Image',
						'name' => 'show_image',
						'aria-label' => '',
						'type' => 'true_false',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'message' => '',
						'default_value' => 1,
						'allow_in_bindings' => 0,
						'ui_on_text' => 'Show',
						'ui_off_text' => 'Hide',
						'ui' => 1,
					),
				),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'block',
					'operator' => '==',
					'value' => 'acf/make-upcoming-events',
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

	
	acf_add_local_field_group( array(
		'key' => 'group_61e99f9925444',
		'title' => 'Block: Image Slider',
		'fields' => array(
			array(
				'key' => 'field_61e99f9e62b36',
				'label' => 'Block: Image Slider',
				'name' => 'block_image_slider',
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
						'key' => 'field_61e99fa962b37',
						'label' => 'Gallery Title',
						'name' => 'gallery_title',
						'aria-label' => '',
						'type' => 'text',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
						'maxlength' => '',
					),
					array(
						'key' => 'field_61e99faf62b38',
						'label' => 'Gallery Description',
						'name' => 'gallery_description',
						'aria-label' => '',
						'type' => 'textarea',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'placeholder' => '',
						'maxlength' => '',
						'rows' => '',
						'new_lines' => '',
					),
					array(
						'key' => 'field_61e99fb562b39',
						'label' => 'Images',
						'name' => 'images',
						'aria-label' => '',
						'type' => 'repeater',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'collapsed' => '',
						'min' => 1,
						'max' => 10,
						'layout' => 'table',
						'button_label' => 'Add Image',
						'sub_fields' => array(
							array(
								'key' => 'field_61e99fbb62b3a',
								'label' => 'Image',
								'name' => 'image',
								'aria-label' => '',
								'type' => 'image',
								'instructions' => '',
								'required' => 0,
								'conditional_logic' => 0,
								'wrapper' => array(
									'width' => '',
									'class' => '',
									'id' => '',
								),
								'return_format' => 'array',
								'preview_size' => 'medium',
								'library' => 'all',
								'min_width' => '',
								'min_height' => '',
								'min_size' => '',
								'max_width' => '',
								'max_height' => '',
								'max_size' => '',
								'mime_types' => '',
								'parent_repeater' => 'field_61e99fb562b39',
							),
							array(
								'key' => 'field_61e99fbf62b3b',
								'label' => 'Caption',
								'name' => 'caption',
								'aria-label' => '',
								'type' => 'text',
								'instructions' => '',
								'required' => 0,
								'conditional_logic' => 0,
								'wrapper' => array(
									'width' => '',
									'class' => '',
									'id' => '',
								),
								'default_value' => '',
								'placeholder' => '',
								'prepend' => '',
								'append' => '',
								'maxlength' => '',
								'parent_repeater' => 'field_61e99fb562b39',
							),
						),
						'rows_per_page' => 20,
					),
				),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'block',
					'operator' => '==',
					'value' => 'acf/make-image-slider',
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

