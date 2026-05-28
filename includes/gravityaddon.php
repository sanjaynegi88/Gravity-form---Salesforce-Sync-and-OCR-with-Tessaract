sdfdsfsdf
<?php
// Load all forms if Gravity Forms is available
$forms = class_exists('GFAPI') ? GFAPI::get_forms(true) : [];

// Salesforce objects (not used anymore for dropdown)
$sfobjects = array('Account', 'Product', 'Order', 'Order Line Item');

// Salesforce field definitions
$sffields = array(
    'Name (IS_Name)', 'Email (IS_Email)', 'Phone (IS_Phone)',
    'Order (IS_Order)', 'Street Address (IS_Street_address)',
    'Street Address 2 (IS_Street_address_2)', 'State (IS_State)',
    'City (IS_City)', 'Zip Code (IS_Zip)', 'Short Description (IS_short_description)',
    'Long Description (IS_long_description)', 'Price (IS_price)', 'Account Type (IS_Account_type)'
);

// Default selections
$selected_sf_object = isset($_POST['selected_sf_object']) ? $_POST['selected_sf_object'] : '';
$selected_form_id = isset($_POST['selected_form']) ? $_POST['selected_form'] : '';
$show_additional_fields = !empty($selected_sf_object) && !empty($selected_form_id);

// Parse field values
function parse_field_value($field_label) {
    preg_match('/\((.*?)\)/', $field_label, $matches);
    return isset($matches[1]) ? $matches[1] : $field_label;
}

// Fetch fields of selected Gravity Form
$gravity_form_fields = [];
if ($show_additional_fields && class_exists('GFAPI')) {
    $form_obj = GFAPI::get_form($selected_form_id);
    $form_title = $form_obj['title'] ?? 'Unknown Form';
    if (!empty($form_obj['fields'])) {
        foreach ($form_obj['fields'] as $field) {
            if (!empty($field->label)) {
                $gravity_form_fields[] = array(
                    'label' => $field->label,
                    'id'    => $field->id
                );
            }
        }
    }
}
?>
<div class="curtain">
<div id="edit_container_global"></div>
</div>
<form style="padding:50px 0;" method="post" action="">

   <label for="selected_form">Choose a Form:&nbsp;&nbsp;</label>
    <select id="selected_form" name="selected_form">
        <option value="">-- Select --</option>
        <?php if (!empty($forms)) : ?>
            <?php foreach ($forms as $form) : ?>
                <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($selected_form_id, $form['id']); ?>>
                    <?php echo esc_html($form['title']); ?>
                </option>
            <?php endforeach; ?>
        <?php else : ?>
            <option disabled>No Gravity Forms found</option>
        <?php endif; ?>
    </select>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<label>Sync on Action</label>&nbsp;&nbsp;<input type="checkbox" checked>Insert/Delete&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" checked>Delete
    &nbsp;&nbsp;<input type="button" class="button button-primary button-select-form" value="Save Mapping">
</form>

 <div id="additional-fields" class="gf_addon_step2">

 <div class="addon_fields"></div>
 </div>


<div id="additional_forms"></div>





