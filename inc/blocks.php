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



	endif;
});
