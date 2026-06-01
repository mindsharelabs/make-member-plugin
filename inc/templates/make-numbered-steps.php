<?php

/**
 * MAKE Numbered Steps
 *
 * @param   array       $block The block settings and attributes.
 * @param   string      $content The block inner HTML (empty).
 * @param   bool        $is_preview True during AJAX preview.
 * @param   int|string  $post_id The post ID this block is saved to.
 */

$id = 'make-numbered-steps-' . $block['id'];
if ( ! empty( $block['anchor'] ) ) {
	$id = $block['anchor'];
}

$className = 'make-numbered-steps';
if ( ! empty( $block['className'] ) ) {
	$className .= ' ' . $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$className .= ' align' . $block['align'];
}

$settings = get_field( 'make_numbered_steps' );
$steps    = isset( $settings['steps'] ) && is_array( $settings['steps'] ) ? $settings['steps'] : array();
$items    = array();

foreach ( $steps as $step ) {
	$title = isset( $step['title'] ) ? trim( (string) $step['title'] ) : '';
	$body  = isset( $step['body'] ) ? trim( (string) $step['body'] ) : '';
	$tag   = isset( $step['tag'] ) ? trim( (string) $step['tag'] ) : '';

	if ( '' === $title && '' === trim( wp_strip_all_tags( $body ) ) && '' === $tag ) {
		continue;
	}

	$item_html  = '<li class="make-numbered-steps__item">';
	$item_html .= '<div class="make-numbered-steps__badge" aria-hidden="true">' . esc_html( (string) ( count( $items ) + 1 ) ) . '</div>';
	$item_html .= '<div class="make-numbered-steps__content">';

	if ( '' !== $title ) {
		$item_html .= '<h3 class="make-numbered-steps__title">' . esc_html( $title ) . '</h3>';
	}

	if ( '' !== trim( wp_strip_all_tags( $body ) ) ) {
		$item_html .= '<div class="make-numbered-steps__body">' . wp_kses_post( $body ) . '</div>';
	}

	if ( '' !== $tag ) {
		$item_html .= '<span class="make-numbered-steps__tag">' . esc_html( $tag ) . '</span>';
	}

	$item_html .= '</div>';
	$item_html .= '</li>';

	$items[] = $item_html;
}

if ( empty( $items ) ) {
	if ( $is_preview ) {
		echo '<div class="' . esc_attr( $className ) . '" id="' . esc_attr( $id ) . '">';
		echo '<p>' . esc_html__( 'Add steps to preview them here.', 'mindshare' ) . '</p>';
		echo '</div>';
	}

	return;
}

echo '<div class="' . esc_attr( $className ) . '" id="' . esc_attr( $id ) . '">';
echo '<ol class="make-numbered-steps__list">';
echo implode( '', $items );
echo '</ol>';
echo '</div>';
