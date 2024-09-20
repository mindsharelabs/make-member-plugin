<?php
function make_tribe_events_default_blocks($template) {
    $template = [
        ['tribe/event-datetime'],
        ['core/paragraph', [
            'placeholder' => __('Add event description...', 'your-text-domain'),
        ]],
        ['acf/make-instructor-bios'],
        ['tribe/event-price'],
        ['tribe/event-organizer'],
        ['tribe/event-links']
    ];

    return $template;
}
add_filter('tribe_events_editor_default_template', 'make_tribe_events_default_blocks', 1, 1);