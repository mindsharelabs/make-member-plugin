<?php
/**
 * Gravity Form Entries
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   bool $is_preview True during AJAX preview.
 * @param   (int|string) $post_id The post ID this block is saved to.
 */

// Create id attribute allowing for custom "anchor" value.
$id = 'make-gravity-form-entries-' . $block['id'];
if( !empty($block['anchor']) ) {
    $id = $block['anchor'];
}

// Create class attribute allowing for custom "className" and "align" values.
$className = 'make-gravity-form-entries';
if( !empty($block['className']) ) {
    $className .= ' ' . $block['className'];
}
if( !empty($block['align']) ) {
    $className .= ' align' . $block['align'];
}

$gravity_form_entries = get_field('gravity_form_entries');
$form_id = (!empty($gravity_form_entries['form_id']) ? absint($gravity_form_entries['form_id']) : 0);
$visible_fields = (!empty($gravity_form_entries['visible_fields']) && is_array($gravity_form_entries['visible_fields']) ? array_values($gravity_form_entries['visible_fields']) : array());

if(!class_exists('GFAPI') || !$form_id || empty($visible_fields)) :
    if($is_preview) :
        echo '<p>Select a Gravity Form and at least one field to display entries.</p>';
    endif;
    return;
endif;

$form = GFAPI::get_form($form_id);
if(!$form) :
    if($is_preview) :
        echo '<p>The selected Gravity Form could not be loaded.</p>';
    endif;
    return;
endif;

$field_choices = (function_exists('make_gf_entries_get_field_choices') ? make_gf_entries_get_field_choices($form_id) : array());
$field_labels = array();

foreach($visible_fields as $field_key) :
    $field_key = (string) $field_key;
    if(isset($field_choices[$field_key])) :
        $field_labels[$field_key] = $field_choices[$field_key];
    endif;
endforeach;

if(empty($field_labels)) :
    if($is_preview) :
        echo '<p>No matching Gravity Forms fields are available for display.</p>';
    endif;
    return;
endif;

$entries = GFAPI::get_entries(
    $form_id,
    array(
        'status' => 'active',
    )
);

if(empty($entries)) :
    if($is_preview) :
        echo '<p>No entries were found for the selected Gravity Form.</p>';
    endif;
    return;
endif;

$field_keys = array_keys($field_labels);
$signature_base_url = '';
$upload_dir = wp_upload_dir();

if(!empty($upload_dir['baseurl'])) :
    $signature_base_url = wp_parse_url($upload_dir['baseurl'], PHP_URL_PATH);
    if($signature_base_url) :
        $signature_base_url = untrailingslashit($signature_base_url) . '/gravity_forms/signatures/';
    endif;
endif;
?>
<div id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($className); ?>">
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <?php foreach($field_labels as $label) : ?>
                            <th scope="col" class="fw-semibold text-nowrap"><?php echo esc_html($label); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($entries as $entry) : ?>
                        <tr>
                            <?php foreach($field_keys as $field_key) : ?>
                                <?php
                                $value = '';
                                $text = '';
                                $image = '';
                                $text_values = array();
                                $field_key_parts = explode('.', (string) $field_key);
                                $base_field_id = absint($field_key_parts[0]);
                                $is_sub_input = (count($field_key_parts) > 1);
                                $gf_field = ($base_field_id ? GFAPI::get_field($form, $base_field_id) : false);

                                if($gf_field && !$is_sub_input && $gf_field->type === 'signature') :
                                    $value = rgar($entry, $field_key);
                                elseif($gf_field && method_exists($gf_field, 'get_value_export')) :
                                    $value = $gf_field->get_value_export($entry, $field_key, true, false);
                                else :
                                    $value = rgar($entry, $field_key);
                                endif;

                                if(is_array($value)) :
                                    foreach($value as $item) :
                                        if(is_array($item)) :
                                            foreach($item as $sub_item) :
                                                $sub_item = trim((string) $sub_item);
                                                if($sub_item !== '') :
                                                    $text_values[] = $sub_item;
                                                endif;
                                            endforeach;
                                        else :
                                            $item = trim((string) $item);
                                            if($item !== '') :
                                                $text_values[] = $item;
                                            endif;
                                        endif;
                                    endforeach;
                                    $text = implode(', ', $text_values);
                                else :
                                    $value = trim((string) $value);

                                    if($value !== '') :
                                        if($gf_field && !$is_sub_input && $gf_field->type === 'signature' && $signature_base_url && preg_match('/\.png$/i', $value)) :
                                            $image = $signature_base_url . rawurlencode(basename($value));
                                        elseif(preg_match('/\.(png|jpe?g|gif|webp|svg)(?:\?.*)?$/i', $value)) :
                                            $image = $value;
                                        else :
                                            $text = $value;
                                        endif;
                                    endif;
                                endif;
                                ?>
                                <td>
                                    <?php if($image) : ?>
                                        <img src="<?php echo esc_url($image); ?>" class="img-fluid" style="max-width: 320px; height: auto;" loading="lazy">
                                    <?php elseif($text) : ?>
                                        <?php echo nl2br(esc_html($text)); ?>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
