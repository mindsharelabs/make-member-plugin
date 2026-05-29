<?php

/**
 * Sponsorship Tiers block template.
 *
 * @param array       $block The block settings and attributes.
 * @param string      $content The block inner HTML (empty).
 * @param bool        $is_preview True during AJAX preview.
 * @param int|string  $post_id The post ID this block is saved to.
 */

$id = 'make-sponsorship-tiers-' . $block['id'];
if ( ! empty( $block['anchor'] ) ) {
	$id = $block['anchor'];
}

$class_name = 'make-sponsorship-tiers';
if ( ! empty( $block['className'] ) ) {
	$class_name .= ' ' . $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_name .= ' align' . $block['align'];
}

$settings = get_field( 'make_sponsorship_tiers' );
$tiers    = isset( $settings['tiers'] ) && is_array( $settings['tiers'] ) ? $settings['tiers'] : array();

if ( empty( $tiers ) ) {
	if ( $is_preview ) {
		echo '<div class="' . esc_attr( $class_name ) . '" id="' . esc_attr( $id ) . '">';
		echo '<p>' . esc_html__( 'Add sponsorship tiers to preview them here.', 'mindshare' ) . '</p>';
		echo '</div>';
	}

	return;
}

echo '<div class="' . esc_attr( $class_name ) . '" id="' . esc_attr( $id ) . '">';
echo '<div class="row gx-5 gy-5">';

foreach ( $tiers as $tier ) {
	$name        = isset( $tier['name'] ) ? $tier['name'] : '';
	$level       = isset( $tier['level'] ) ? $tier['level'] : '';
	$content     = isset( $tier['content'] ) ? $tier['content'] : '';
	$benefits    = isset( $tier['benefits'] ) && is_array( $tier['benefits'] ) ? $tier['benefits'] : array();
	$is_featured = ! empty( $tier['featured'] );
	$list_items  = '';

	foreach ( $benefits as $benefit ) {
		$benefit_text = isset( $benefit['benefit'] ) ? trim( $benefit['benefit'] ) : '';

		if ( '' === $benefit_text ) {
			continue;
		}

		$list_items .= '<li class="list-group-item">' . esc_html( $benefit_text ) . '</li>';
	}

		echo '<div class="col-12 col-md-6 col-lg-4 make-sponsorship-tier-col' . ( $is_featured ? ' is-featured' : '' ) . '">';
	echo '<div class="card h-100 shadow-sm make-sponsorship-tier-card' . ( $is_featured ? ' is-featured' : '' ) . '">';

	if ( $level ) {
		echo '<div class="make-sponsorship-tier-amount">' . esc_html( $level ) . '</div>';
	}

	echo '<div class="make-sponsorship-tier-accent"></div>';
	echo '<div class="card-body">';

	if ( $name ) {
		echo '<h3 class="h4 card-title">' . esc_html( $name ) . '</h3>';
	}

	if ( $content ) {
		echo '<div class="card-text make-sponsorship-tier-content">' . wp_kses_post( $content ) . '</div>';
	}

	echo '</div>';

	if ( '' !== $list_items ) {
		echo '<ul class="list-group list-group-flush make-sponsorship-tier-benefits">';
		echo $list_items;
		echo '</ul>';
	}

	echo '</div>';
	echo '</div>';
}

echo '</div>';
echo '</div>';
