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
			'name'              => 'make-skill-tree',
			'title'             => __('Make Skill Tree'),
			'description'       => __('A block that shows the Make Santa Fe Badge skill tree.'),
			'render_template'   => MAKESF_ABSPATH . '/inc/templates/make-skill-tree.php',
			'category'          => 'make-blocks',
			// 'icon'              => file_get_contents(MAKESF_URL . 'inc/img/logo-sm.svg'),
			'keywords'          => array( 'tree', 'badges', 'skill', 'mind', 'Mindshare'),
			'align'             => 'full',
			'mode'            	=> 'edit',
			'multiple'          => false,
			'supports'					=> array(
				'align' => false,
			),
			'enqueue_assets' => function(){


				$makeSkillTree = new makeSkillTree();

				// We're just registering it here and then with the action get_footer we'll enqueue it.
				wp_register_style( 'make-block-styles', MAKESF_URL . 'css/style.css', array(),  MAKESF_PLUGIN_VERSION);
				add_action( 'get_footer', function () {wp_enqueue_style('make-block-styles');});

				wp_register_script('make-d3-min', MAKESF_URL . 'assets/js/d3.min.js', array(), MAKESF_PLUGIN_VERSION, false);
				wp_enqueue_script('make-d3-min');

				wp_register_script('techTree-setup', MAKESF_URL . 'assets/js/techtreesetup.js', array('make-d3-min'), MAKESF_PLUGIN_VERSION, true);
				wp_enqueue_script('techTree-setup');
				wp_localize_script('techTree-setup', 'techTreeSettings', array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'baseurl' => get_template_directory_uri()
				));

				wp_register_script('techTree', MAKESF_URL . 'assets/js/techTree.js', array('techTree-setup'), MAKESF_PLUGIN_VERSION, true);
				wp_enqueue_script('techTree');

				wp_register_script('techTree-init', MAKESF_URL . 'assets/js/techtreeinit.js', array( 'techTree'), MAKESF_PLUGIN_VERSION, true);
				wp_enqueue_script('techTree-init');
				wp_localize_script('techTree-init', 'makeMember', array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'postID' => get_the_id(),
					'dataURL' => $makeSkillTree->get_badges_url(),
					'baseurl' => MAKESF_URL
				));


			})
		);
		



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

				wp_register_script('html5-qrcode', MAKESF_URL . 'assets/js/html5-qrcode.min.js', array('jquery'), MAKESF_PLUGIN_VERSION, false);
				wp_enqueue_script('html5-qrcode');

				wp_register_script('make-sign-in-scripts', MAKESF_URL . 'assets/js/make-member-sign-in.js', array('jquery', 'html5-qrcode'), MAKESF_PLUGIN_VERSION, true);
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
