<?php
add_action('newsletter_register_blocks', function () {
    TNP_Composer::register_block(__DIR__ . '/upcoming-events');
});