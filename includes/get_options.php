<?php
global $wpdb;
$options = wp_load_alloptions();

foreach ($options as $key => $value) {
    if (strpos($key, 'gfid_') === 0 && strpos($key, '_sfobject_') !== false) {
        $mappings = maybe_unserialize($value);
        if (!is_array($mappings) || empty($mappings)) continue;

        // Extract form ID and Salesforce object
        preg_match('/gfid_(\d+)_sfobject_(.+)/', $key, $matches);
        $form_id = $matches[1];
        $sf_object_raw = $matches[2];
        $sf_object = str_replace('_', ' ', $sf_object_raw);

        // Get form title and fields
        $form_obj = GFAPI::get_form($form_id);
        $form_title = $form_obj['title'] ?? 'Unknown Form';
        $form_fields = [];
        foreach ($form_obj['fields'] as $field) {
            $form_fields[$field->id] = $field->label;
        }

        echo '<h3>Mapped Fields for: <strong>' . esc_html($form_title) . '</strong> (Salesforce Object: <strong>' . esc_html($sf_object) . '</strong>)</h3>';
        echo '<table class="widefat striped" style="margin-bottom:30px;">';
        echo '<thead>
                <tr>
                    <th>Gravity field</th>
                    <th>Salesforce field</th>
                    <th>Active/InActive</th>
                    <th>Action</th>
                </tr>
              </thead><tbody>';

        foreach ($mappings as $i => $map) {
            $gf_label = $form_fields[$map['gfield']] ?? 'Unknown Field (' . esc_html($map['gfield']) . ')';
            $sf_label = $map['sffield']; // use directly as stored

            echo '<tr>';
            echo '<td>' . esc_html($gf_label) . '</td>';
            echo '<td>' . esc_html($sf_label) . '</td>';
            echo '<td><input type="checkbox" checked disabled></td>';
            echo '<td>
                    <a href="#" class="edit-mapping">Edit</a> |
                    <a href="#" class="delete-mapping" data-option="' . esc_attr($key) . '" data-index="' . esc_attr($i) . '">Delete</a>
                  </td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}