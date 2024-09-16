<?php
$cells = [];
$total_width = 600 - $options['block_padding_left'] - $options['block_padding_right'];
$column_width = $total_width / 2 - 20;

$title_style = TNP_Composer::get_style($options, 'title', $composer, 'title', ['scale' => .8]);
?>
<style>
    .event {
        margin-bottom: 25px;
    }

    .image {
        padding-bottom: 20px;
    }

    .title,  {
        font-family: 'Courier New', Courier, monospace;
        font-weight: bold;
        font-size: 22px;
        padding-bottom: 20px;
        text-align: center;
        color: #111111;
    }
    .title-link {
        font-family: 'Courier New', Courier, monospace;
        font-weight: bold;
        font-size: 22px;
        padding-bottom: 20px;
        text-align: center;
        color: #111111;
    }

    .time {
        color: #444444;
        padding-bottom: 15px;
    }
    .date {
        font-size: 14px;
        padding-top: 15px;
        font-family: 'Courier New', Courier, monospace;
        color: #444444;
        padding-bottom: 15px;
        font-weight: bold;
        text-align: center;
    }

    .location {
        font-size: 14px;
        line-height: normal!important;
        padding-bottom: 20px;
        font-weight: bold;
        text-align: center;
    }
    .name {
        line-height: normal!important;
        font-weight: bold;
    }
    .town {
        font-size: 14px;
        font-family: 'Montserrat', sans-serif;
        line-height: normal!important;
        font-weight: bold;
    }
    .address {
        font-size: 14px;
        font-family: 'Montserrat', sans-serif;
        line-height: normal!important;
        font-weight: bold;
    }
    .excerpt {
        font-size: 14px;
        font-family: 'Montserrat', sans-serif;
        line-height: 1.2em!important;
        padding-bottom:20px;
        text-align: center;
    }

</style>

<?php foreach ($items as $item) { 
    mapi_write_log($item);
    if ($item->image) {
        $item->image->set_width($column_width);
    }
    ob_start();
    ?>

    <div inline-class="event">
        <?php if ($item->image) { ?>
            <div inline-class="image">
                <?php echo TNP_Composer::image($item->image) ?>
            </div>
        <?php } ?>


        <div inline-class="title">
            <a inline-class="title-link" href="<?php echo esc_attr($item->url) ?>" target="_blank"><?php echo esc_html($item->title) ?></a>
        </div>

        <?php if ($item->show_date) { ?>
            <div inline-class="date">
                <?php echo esc_html($item->date) ?>
            </div>

            <?php if ($item->show_time && !$item->all_day) { ?>
                <div inline-class="time"><?php echo esc_html($item->time) ?></div>
            <?php } ?>
        <?php } ?>

        <?php if ($item->location) { ?>
            <div inline-class="location">
                <span inline-class="name"><?php echo esc_html($item->location->name) ?></span>
                <br>
                <span inline-class="town"><?php echo esc_html($item->location->city) ?></span> -
                <span inline-class="address"><?php echo esc_html($item->location->address) ?></span>
            </div>
        <?php } ?>

        <div inline-class="excerpt">
            <?php echo $item->excerpt; ?>
        </div>

        <div inline-class="button">
            <?php 
            echo TNP_Composer::button($button_options, 'button', $composer);
            
            ?>
        </div>
    </div>
    <?php
    $cells[] = ob_get_clean();
    ?>
<?php } ?>

<?php echo TNP_Composer::grid($cells, ['width' => $total_width, 'responsive' => true, 'padding' => 10]) ?>
