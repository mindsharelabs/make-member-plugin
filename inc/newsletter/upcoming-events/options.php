<?php
/* @var $fields NewsletterFields */
?>

<?php if ($context['type'] == 'automated') { ?>

    <?php
    $fields->select('automated', __('If there are no new events...', 'newsletter'), ['' => 'Show the message below', '1' => 'Do not send the newsletter', '2' => 'Remove the block'],
            ['description' => 'Works only on automatic newsletter creation'])
    ?>
    <?php $fields->text('automated_no_events', 'No events text') ?>

<?php } ?>

<div class="tnp-field-row">
    <div class="tnp-field-col-2">
        <?php $fields->select_number('max', 'Max events', 1, 20) ?>
    </div>
    <div class="tnp-field-col-2">
        <?php $fields->select('order', 'Order by time', ['' => 'from far to near', '1' => 'from near to far']) ?>
    </div>
    <div style="clear: both"></div>
</div>

<div class="tnp-field-row">
    <div class="tnp-field-col-3">
        <?php $fields->yesno('show_location', __('Location', 'newsletter')) ?>
    </div>
    <div class="tnp-field-col-3">
        <?php $fields->yesno('show_date', __('Date', 'newsletter')) ?>
    </div>
    <div class="tnp-field-col-3">
        <?php $fields->yesno('show_time', __('Time', 'newsletter')) ?>
    </div>
    <div style="clear: both"></div>
</div>

<div class="tnp-field-row">
    <div class="tnp-field-col-2">
        <?php $fields->yesno('image', __('Image', 'newsletter')) ?>
    </div>
    <div class="tnp-field-col-2">
        <?php $fields->number('excerpt_length', __('Excerpt characters', 'newsletter'), ['min' => 0]); ?>
    </div>
    <div style="clear: both"></div>
</div>

<?php $fields->language(); ?>

<?php $fields->terms('tribe_events_cat') ?>

<?php
$fields->select('tickets', __('Tickets', 'newsletter'),
        [
            'tickets' => __('Show only events with tickets', 'newsletter'),
            'all' => __('Show all events', 'newsletter'),
])
?>


<?php $fields->font('title_font', __('Title font', 'newsletter'), ['family_default' => true, 'size_default' => true, 'weight_default' => true]) ?>
<?php $fields->font('font', __('Excerpt font', 'newsletter'), ['family_default' => true, 'size_default' => true, 'weight_default' => true]) ?>
<?php $fields->button('button', __('Button', 'newsletter'), ['url' => false, 'family_default' => true, 'size_default' => true, 'weight_default' => true]) ?>

<?php $fields->block_commons() ?>