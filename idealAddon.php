<?php
/**
 * Plugin Name: idealAMS Gravity Forms Addon (Requires Gravity Forms)
 * Description: A custom plugin that requires Gravity Forms and (optionally) WooCommerce + Stripe.
 * Version:     1.3.0
 * Author:      108 Ideaspace
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// membership registration 012bZ000000OEJZQA4

define( 'SF_108GFADDON_DIR', plugin_dir_path( __FILE__ ) );
define( 'SF_108GFADDON_URL', plugin_dir_url( __FILE__ ) );
define( 'GF_WC_DEBUG_LOG',   WP_CONTENT_DIR . '/gf-wc-debug.log' );

if ( ! defined( 'SF_108GFADDON_MENU_SLUG' ) ) {
define( 'SF_108GFADDON_MENU_SLUG', '108-salesforce-gf-addon' );
}

include_once('classes/gf-extended-widgets.php');
include_once('classes/gf-cart-field.php');
include_once('classes/gf-product.php');
include_once('classes/gf-product-calculator.php');
include_once('includes/transients.php');
include_once('includes/course_registration.php');
include_once('includes/exam_registration.php');
include_once('includes/event_group_registration.php');
include_once('includes/idealmeta.php');
include_once('includes/corporate-ispa.php');


add_filter( 'gform_field_content', function( $content, $field ) {

    if ( $field->isRequired ) {
        // Remove (Required)
        $content = str_replace('(Required)', '', $content);

        // Add * inside label
        $content = preg_replace(
            '/(<label[^>]*>)(.*?)(<\/label>)/',
            '$1$2 <span class="gfield_required">*</span>$3',
            $content
        );
    }

    return $content;

}, 10, 2 );

add_action( 'wp_head', 'gfaddon_define_ajaxurl' );
add_action( 'admin_head', 'gfaddon_define_ajaxurl' );

function gfaddon_define_ajaxurl() {
?>
<script>
window.ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
</script>
<?php
}

// Debug Function
function dbg($msg){
    $file = WP_CONTENT_DIR . '/gf-debug.txt';
    if (is_array($msg) || is_object($msg)) {
        $msg = print_r($msg, true); // <-- return output as string
    }
    file_put_contents($file, "[".date("Y-m-d H:i:s")."] ".$msg.PHP_EOL, FILE_APPEND);
}

function dbg_alt($msg){
    $file = WP_CONTENT_DIR . '/gf-debug-alt.txt';
    if (is_array($msg) || is_object($msg)) {
        $msg = print_r($msg, true); // <-- return output as string
    }
    file_put_contents($file, "[".date("Y-m-d H:i:s")."] ".$msg.PHP_EOL, FILE_APPEND);
}



global $log_file;
$log_file = WP_CONTENT_DIR . '/sf_api_order_log.txt';

add_action( 'admin_init', function () {
include_once ABSPATH . 'wp-admin/includes/plugin.php';
$needs_gf = ! is_plugin_active( 'gravityforms/gravityforms.php' );
if ( $needs_gf ) {
  deactivate_plugins( plugin_basename( __FILE__ ) );
  add_action( 'admin_notices', function () {
  echo '<div class="notice notice-error"><p><strong>idealAMS Gravity Form Addon</strong> requires <strong>Gravity Forms</strong> to be installed and activated.</p></div>';
  } );
 return;
 }
});

add_action( 'plugins_loaded', function () {
    if ( class_exists( 'SF_APIConnector' ) && is_callable( [ 'SF_APIConnector', 'instance' ] ) ) {
        SF_APIConnector::instance();
    }
}, 20 );

add_action( 'admin_enqueue_scripts', function( $hook ) {
    // Only enqueue Select2 on our page to avoid bloat
    if ( isset($_GET['page']) && $_GET['page'] === SF_108GFADDON_MENU_SLUG ) {
        $loaded_select2 = false;
        if ( wp_style_is( 'select2', 'registered' ) ) {
            wp_enqueue_style( 'select2' );
            wp_enqueue_script( 'select2' );
            $loaded_select2 = true;
        } elseif ( wp_script_is( 'selectWoo', 'registered' ) ) {
            wp_enqueue_script( 'selectWoo' );
            wp_enqueue_style( 'select2' );
            $loaded_select2 = true;
        }

        if ( ! $loaded_select2 ) {
            wp_register_style( 'idealams_select2_css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0-rc.0' );
            wp_register_script( 'idealams_select2_js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', [ 'jquery' ], '4.1.0-rc.0', true );
            wp_enqueue_style( 'idealams_select2_css' );
            wp_enqueue_script( 'idealams_select2_js' );
        }

        wp_add_inline_script( $loaded_select2 ? 'select2' : 'idealams_select2_js', "
            jQuery(function($){
                $('.select_2').select2({
                    placeholder: 'Select options',
                    width: 'resolve'
                });
            });
        " );

        wp_enqueue_style( 'idealams-admin', SF_108GFADDON_URL . 'css/admin-style.css', [], '1.0' );
        wp_enqueue_script( 'idealams-mapping', SF_108GFADDON_URL . 'js/sf-mapping.js', [ 'jquery' ], '1.0', true );
        wp_localize_script( 'idealams-mapping', 'sf_mapping_ajax', [ 'ajax_url' => admin_url( 'admin-ajax.php' ) ] );

        wp_enqueue_script( 'jquery-ui-core' );
        wp_enqueue_script( 'jquery-ui-widget' );
        wp_enqueue_script( 'jquery-ui-selectmenu' );
        wp_enqueue_script( 'jquery-ui-selectable' );
        wp_enqueue_style( 'jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css', [], '1.13.2' );
    }
});


if ( ! function_exists( 'fetch_sf_object_fields_by_object_name' ) ) {
    function fetch_sf_object_fields_by_object_name( $sf_object ) {
        if ( ! class_exists( 'SF_APIConnector' ) ) return [];
        $sf_fields = SF_APIConnector::getSfObjectFields( $sf_object );
        if ( is_string( $sf_fields ) ) {
            $sf_fields = json_decode( $sf_fields );
        }
        if ( ! is_object( $sf_fields ) || empty( $sf_fields->fields ) ) return [];
        $sfOptFields = [];
        foreach ( (array) $sf_fields->fields as $fielddata ) {
            if ( ! empty( $fielddata->updateable ) ) {
                $sfOptFields[ $fielddata->name ] = $fielddata->label . ' (' . $fielddata->type . ')';
            }
        }
        return $sfOptFields;
    }
}


global $sffields;
$sffields = [
    'IS_Name'              => 'Name',
    'IS_Email'             => 'Email',
    'IS_Phone'             => 'Phone',
    'IS_Order'             => 'Order',
    'IS_Street_address'    => 'Street Address',
    'IS_Street_address_2'  => 'Street Address 2',
    'IS_State'             => 'State',
    'IS_City'              => 'City',
    'IS_Zip'               => 'Zip Code',
    'IS_short_description' => 'Short Description',
    'IS_long_description'  => 'Long Description',
    'IS_price'             => 'Price',
    'IS_Account_type'      => 'Account Type'
];


add_action( 'admin_menu', function () {
    add_menu_page(
        '108 Salesforce Gravity Form Addon',
        '108 Salesforce Gravity Form Addon',
        'manage_options',
        SF_108GFADDON_MENU_SLUG,
        function () {
            echo '<div class="wrap gf_addon_wrap"><h1>108 Salesforce Gravity Form Addon</h1>';
            if ( class_exists( 'GFAPI' ) ) {
                $forms = GFAPI::get_forms( true );
                if ( ! empty( $forms ) ) {
                    require_once SF_108GFADDON_DIR . 'includes/gravityaddon.php';
                } else {
                    echo '<p>No published Gravity Forms found.</p>';
                }
            } else {
                echo '<p>Gravity Forms is not active.</p>';
            }
            echo '</div>';
        },
        'dashicons-admin-generic',
        13
    );
} );


add_action( 'wp_ajax_add_sf_gf_mapping', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied' ] );
    }
    $sffield       = sanitize_text_field( is_array( $_POST['ssffield'] ?? '' ) ? implode( '', $_POST['ssffield'] ) : ( $_POST['ssffield'] ?? '' ) );
    $gfield        = sanitize_text_field( is_array( $_POST['sgfield'] ?? '' ) ? implode( '', $_POST['sgfield'] ) : ( $_POST['sgfield'] ?? '' ) );
    $form_id       = sanitize_text_field( is_array( $_POST['sform_id'] ?? '' ) ? implode( '', $_POST['sform_id'] ) : ( $_POST['sform_id'] ?? '' ) );
    $sf_object     = sanitize_text_field( is_array( $_POST['ssf_object'] ?? '' ) ? implode( '', $_POST['ssf_object'] ) : ( $_POST['ssf_object'] ?? '' ) );
    $sf_object_type= sanitize_text_field( is_array( $_POST['ssf_object_type'] ?? '' ) ? implode( '', $_POST['ssf_object_type'] ) : ( $_POST['ssf_object_type'] ?? '' ) );
    $object_type   = sanitize_text_field( is_array( $_POST['sobject_type'] ?? '' ) ? implode( '', $_POST['sobject_type'] ) : ( $_POST['sobject_type'] ?? 'common' ) );
    $sf_to_wp      = $_POST['sf_to_wp'] ?? '';
    $wp_to_sf      = $_POST['wp_to_sf'] ?? '';
    $sf_object = str_replace( ' ', '_', $sf_object );

    if ( empty( $sffield ) || empty( $gfield ) || empty( $form_id ) || empty( $sf_object ) ) {
        wp_send_json_error( [ 'message' => 'Missing required data.' ] );
    }

    $option_key = "gfid_{$form_id}_sfobject_{$sf_object}_{$sf_object_type}";
    $default_data = [
        'form_id'     => $form_id,
        'sf_object'   => $sf_object,
        'object_type' => $sf_object_type,
        'order'       => 1,
        'mappings'    => [],
    ];

    $existing = get_option( $option_key, $default_data );
    if ( ! is_array( $existing ) || ! isset( $existing['mappings'] ) ) {
        $existing = $default_data;
    }

    foreach ( $existing['mappings'] as $map ) {
        if ( $map['sffield'] === $sffield && $map['gfield'] === $gfield ) {
            wp_send_json_error( [ 'message' => 'Mapping already exists.' ] );
        }
    }

    $existing['mappings'][] = [
        'sffield'  => $sffield,
        'gfield'   => $gfield,
        'sf_to_wp' => $sf_to_wp,
        'wp_to_sf' => $wp_to_sf
    ];

    update_option( $option_key, $existing );
    wp_send_json_success( [ 'message' => 'Mapping saved successfully.', 'option_key' => $option_key ] );
} );


add_action( 'admin_menu', function () {


    add_submenu_page(
        SF_108GFADDON_MENU_SLUG,
        'Form Mapping',
        'Form Mapping',
        'manage_options',
        '108-gf-addon-form-mapping',
        'sf_108gfaddon_render_form_mapping_page'
    );

        add_submenu_page(
        SF_108GFADDON_MENU_SLUG,
        'Field Mapping',
        'Field Mapping',
        'manage_options',
        '108-gf-addon-field-mapping',
        'sf_108gfaddon_render_field_mapping_page'
    );

    add_submenu_page(
        SF_108GFADDON_MENU_SLUG,          // Parent slug
        'Addon Logs',                     // Page title
        'Logs',                           // Menu title
        'manage_options',                // Capability
        '108-gf-addon-logs',              // Menu slug
        'sf_108gfaddon_render_logs_page'  // Callback
    );



});




function sf_108gfaddon_render_field_mapping_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    /* ------------------------------------
     * HANDLE SAVE
     * ------------------------------------ */
    if ( isset( $_POST['gf_field_mapping_save'] ) ) {

        check_admin_referer( 'gf_field_mapping_save' );

        $raw = (array) ( $_POST['membership_field_mapping'] ?? [] );

        $clean = [
            'membership_renewal_id'      => sanitize_text_field( $raw['membership_renewal_id'] ?? '' ),
            'membership_registration_id' => sanitize_text_field( $raw['membership_registration_id'] ?? '' ),
        ];

        update_option( 'gf_membership_field_mapping', $clean, false );

        echo '<div class="updated notice"><p>Field Mapping saved.</p></div>';
    }

    /* ------------------------------------
     * LOAD SAVED VALUES
     * ------------------------------------ */
    $field_mapping = get_option( 'gf_membership_field_mapping', [] );

    $membership_renewal_id      = $field_mapping['membership_renewal_id'] ?? '';
    $membership_registration_id = $field_mapping['membership_registration_id'] ?? '';

    ?>
    <div class="wrap">
        <h1>Field Mapping</h1>

        <form method="post">
            <?php wp_nonce_field( 'gf_field_mapping_save' ); ?>

            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row">
                        <label for="membership_renewal_id">
                            Membership Renewal ID
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="membership_renewal_id"
                            name="membership_field_mapping[membership_renewal_id]"
                            value="<?php echo esc_attr( $membership_renewal_id ); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                            Identifier used to detect membership renewals.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="membership_registration_id">
                            Membership Registration ID
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="membership_registration_id"
                            name="membership_field_mapping[membership_registration_id]"
                            value="<?php echo esc_attr( $membership_registration_id ); ?>"
                            class="regular-text"
                        />
                        <p class="description">
                            Identifier used to detect new memberships.
                        </p>
                    </td>
                </tr>

            </table>

            <p class="submit">
                <button type="submit" name="gf_field_mapping_save" class="button button-primary">
                    Save Field Mapping
                </button>
            </p>
        </form>
    </div>
    <?php
}






function sf_108gfaddon_render_logs_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $log_file = WP_CONTENT_DIR . '/gf-debug.txt';

    // Handle clear log action
    if ( isset( $_POST['clear_gf_log'] ) && check_admin_referer( 'clear_gf_log_action' ) ) {
        if ( file_exists( $log_file ) && is_writable( $log_file ) ) {
            file_put_contents( $log_file, '' );
            echo '<div class="notice notice-success is-dismissible"><p>Log file cleared successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Log file not writable.</p></div>';
        }
    }

    echo '<div class="wrap">';
    echo '<h1>Gravity Forms Addon Logs</h1>';

    if ( file_exists( $log_file ) && is_readable( $log_file ) ) {

        $log_content = file_get_contents( $log_file );

        echo '<div style="
            background:#111;
            color:#0f0;
            padding:15px;
            max-height:600px;
            overflow:auto;
            font-family: monospace;
            white-space: pre-wrap;
            border:1px solid #444;
            margin-bottom:15px;
        ">';

        echo esc_html( $log_content ?: 'Log file is empty.' );

        echo '</div>';

    } else {
        echo '<p><strong>Log file not found or not readable.</strong></p>';
        echo '<code>' . esc_html( $log_file ) . '</code>';
    }

    // Clear Log Button
    echo '<form method="post">';
    wp_nonce_field( 'clear_gf_log_action' );
    echo '<input type="submit" name="clear_gf_log" class="button button-danger" value="Clear Log"
        onclick="return confirm(\'Are you sure you want to clear the log file?\');">';
    echo '</form>';

    echo '</div>';
}






function sf_108gfaddon_render_form_mapping_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( ! class_exists( 'GFAPI' ) ) {
        echo '<div class="wrap"><p>Gravity Forms is not active.</p></div>';
        return;
    }

    $option_key = 'gf_Member_Event_form_mapping';

    // Default structure
    $mapping = get_option( $option_key, [
        'membership_registration' => '',
        'event_registration'      => '',
        'combined_registration'   => '',
        'course_registration'   => '',
        'ce_request'   => '',
        'membership_renewal' => '',
        'complaints_form' => '',
        'individual_membership_ispa' => '',
        'corporate_membership_ispa' => '',
        'corporate_event_ispa' => ''

    ] );

    // Handle save
    if ( isset( $_POST['save_form_mapping'] ) && check_admin_referer( 'save_gf_form_mapping' ) ) {

        $mapping = [
            'membership_registration' => absint( $_POST['membership_registration'] ?? 0 ),
            'membership_registration_group' => absint( $_POST['membership_registration_group'] ?? 0 ),
            'event_registration'      => absint( $_POST['event_registration'] ?? 0 ),
            'combined_registration'   => absint( $_POST['combined_registration'] ?? 0 ),
            'course_registration'   => absint( $_POST['course_registration'] ?? 0 ),
            'exam_registration'   => absint( $_POST['exam_registration'] ?? 0 ),
            'ce_request'   => absint( $_POST['ce_request'] ?? 0 ),
            'membership_renewal'   => absint( $_POST['membership_renewal'] ?? 0 ),
            'complaints_form'   => absint( $_POST['complaints_form'] ?? 0 ),
            'individual_membership_ispa' => absint( $_POST['individual_membership_ispa'] ?? 0 ),
            'corporate_membership_ispa' => absint( $_POST['corporate_membership_ispa'] ?? 0 ),
             'corporate_event_ispa' => absint( $_POST['corporate_event_ispa'] ?? 0 ),
        ];

        update_option( $option_key, $mapping );

        echo '<div class="notice notice-success is-dismissible"><p>Form mapping saved successfully.</p></div>';
    }

    $forms = GFAPI::get_forms( true );

    echo '<div class="wrap">';
    echo '<h1>Form Mapping</h1>';

    echo '<form method="post">';
    wp_nonce_field( 'save_gf_form_mapping' );

    echo '<table class="form-table" role="presentation">';

    sf_render_gf_dropdown_row(
        'Membership Registration',
        'membership_registration',
        $forms,
        $mapping['membership_registration']
    );

    sf_render_gf_dropdown_row(
        'Group Membership Registration',
        'membership_registration_group',
        $forms,
        $mapping['membership_registration_group']
    );

    sf_render_gf_dropdown_row(
        'Event Registration',
        'event_registration',
        $forms,
        $mapping['event_registration']
    );

    sf_render_gf_dropdown_row(
        'Combined Registration',
        'combined_registration',
        $forms,
        $mapping['combined_registration']
    );

        sf_render_gf_dropdown_row(
        'Course Registration',
        'course_registration',
        $forms,
        $mapping['course_registration']
    );

        sf_render_gf_dropdown_row(
        'Exam Registration',
        'exam_registration',
        $forms,
        $mapping['exam_registration']
    );


        sf_render_gf_dropdown_row(
        'CE Credit Request',
        'ce_request',
        $forms,
        $mapping['ce_request']
    );

        sf_render_gf_dropdown_row(
        'Membership Renewal',
        'membership_renewal',
        $forms,
        $mapping['membership_renewal']
    );

            sf_render_gf_dropdown_row(
        'Complaints',
        'complaints_form',
        $forms,
        $mapping['complaints_form']
    );

    sf_render_gf_dropdown_row(
    'Individual Memberships (ISPA)',
    'individual_membership_ispa',
    $forms,
    $mapping['individual_membership_ispa']
    );

    sf_render_gf_dropdown_row(
    'Corporate Memberships (ISPA)',
    'corporate_membership_ispa',
    $forms,
    $mapping['corporate_membership_ispa']
    );

     sf_render_gf_dropdown_row(
    'Corporate Event Group Registration',
    'corporate_event_ispa',
    $forms,
    $mapping['corporate_event_ispa']
    );

    echo '</table>';

    submit_button( 'Save Mapping', 'primary', 'save_form_mapping' );

    echo '</form>';
    echo '</div>';
}


function sf_render_gf_dropdown_row( $label, $name, $forms, $selected ) {

    echo '<tr>';
    echo '<th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th>';
    echo '<td>';
    echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '">';
    echo '<option value="">— Select Form —</option>';

    foreach ( $forms as $form ) {
        printf(
            '<option value="%d" %s>%s</option>',
            absint( $form['id'] ),
            selected( $selected, $form['id'], false ),
            esc_html( $form['title'] )
        );
    }

    echo '</select>';
    echo '</td>';
    echo '</tr>';
}





add_action( 'wp_ajax_sf_select_object', function () {
    $SfObjects = get_option( 'sf_object_mapping_data' );
    if ( is_serialized( $SfObjects ) ) $SfObjects = unserialize( $SfObjects );

    $form_id = absint( $_POST['formID'] ?? 0 );
    ?>
    <form action="" method="post" class="sf_object_select_form">
        <input type="hidden" class="gf_selected_formID" value="<?php echo esc_attr( $form_id ); ?>">
        <div class="sf_object_select">
            <label>Salesforce Object</label>
            <select id="selected_sf_object" name="gf_mapping_data_sf" class="gf_mapping_data_sf">
                <option value="">Please select...</option>
                <?php if ( is_array( $SfObjects ) ) :
                    foreach ( $SfObjects as $key => $value ) : ?>
                        <option value="<?php echo esc_attr( $value['sffield'] ); ?>"><?php echo esc_html( $value['sffield'] ); ?></option>
                    <?php endforeach; endif; ?>
            </select>
        </div>

        <div class="sf_object_type">
            <label>Salesforce Object Type</label>
            <select id="selected_salesforce_object_type" name="gf_mapping_data_sf_type" class="gf_mapping_data_sf_type">
                <option value="">Please select...</option>
                <option>Personel</option>
                <option>Business</option>
                <option>Common</option>
            </select>
        </div>
        <div class="field_mapping_selectable"></div>
    </form>
    <?php
    wp_die();
} );

add_action( 'wp_ajax_fetch_gformsbyid_forms', function () {
    global $wpdb;

    $form_id = isset( $_POST['formID'] ) ? intval( $_POST['formID'] ) : 0;
    if ( ! $form_id ) wp_die( 'Invalid form ID' );

    $pattern = 'gfid_' . $form_id . '_sfobject_%';
    $results = $wpdb->get_results( $wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern
    ) );

    $sffields = fetch_sf_object_fields_by_object_name( $_POST['objectType'] ?? '' );

    $form_obj = GFAPI::get_form( $form_id );
    $form_title  = $form_obj['title'] ?? 'Unknown Form';
    $form_fields = [];

    foreach ( $form_obj['fields'] as $field ) {
        if ( ! empty( $field->inputs ) && is_array( $field->inputs ) ) {
            foreach ( $field->inputs as $input ) {
                $label = trim( $field->label . ' - ' . $input['label'] );
                $form_fields[ (string) $input['id'] ] = $label;
            }
        } else {
            $form_fields[ (string) $field->id ] = $field->label;
        }
    }

    echo '<div class="gf_sf_field_map_container">';
    echo '<div class="common_container"><label>Gravity Form Fields</label><select id="gf_field_select" class="select_2" multiple="multiple">';
    foreach ( $form_fields as $gf_id => $gf_label ) {
        echo '<option value="' . esc_attr( $gf_id ) . '">' . esc_html( $gf_label ) .' ('.$gf_id.')</option>';
    }
    echo '</select></div>';

    echo '<div class="common_container"><label>Salesforce Fields</label><select id="sf_field_select" class="" multiple="multiple">';
    foreach ( $sffields as $sf_key => $sf_label ) {
        echo '<option value="' . esc_attr( $sf_key ) . '">' . esc_html( $sf_label ) . '</option>';
    }
    echo '</select></div></div>';

    echo '<label>Sync Direction:</label>&nbsp;&nbsp;&nbsp;<input id="wp_to_sf" type="checkbox" checked>WP to SF &nbsp;&nbsp;<input id="sf_to_wp" type="checkbox" checked>SF to WP';
    echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="button" class="button button-primary" id="add_mapped_value" value="Add/Update">';

    wp_die();
} );

add_action( 'wp_ajax_fetch_and_append_gforms', function () {
    global $wpdb;

    $form_id = isset( $_POST['formID'] ) ? intval( $_POST['formID'] ) : 0;
    if ( ! $form_id ) wp_die( 'Invalid form ID' );

    $pattern = 'gfid_' . $form_id . '_sfobject_%';
    $results = $wpdb->get_results( $wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern
    ) );

    $form_obj   = GFAPI::get_form( $form_id );
    $form_title = $form_obj['title'] ?? 'Unknown Form';
    $form_fields = [];

    foreach ( $form_obj['fields'] as $field ) {
        if ( ! empty( $field->inputs ) ) {
            foreach ( $field->inputs as $input ) {
                $form_fields[ (string) $input['id'] ] = $field->label . ' - ' . $input['label'];
            }
        } else {
            $form_fields[ (string) $field->id ] = $field->label;
        }
    }

    foreach ( $results as $row ) {
        $key   = $row->option_name;
        $value = maybe_unserialize( $row->option_value );

        if ( ! is_array( $value ) || empty( $value['mappings'] ) || ! isset( $value['sf_object'] ) ) continue;

        $sf_object  = $value['sf_object'];
        $object_type= $value['object_type'] ?? 'common';
        $mappings   = $value['mappings'];

        echo "<div class='form_container'>";
        echo '<h3>Mapped Fields for: <strong>' . esc_html( $form_title ) . '</strong> (Salesforce Object: <strong>' . esc_html( $sf_object ) . '</strong>)</h3>';

        echo '<div class="header_title">
                <div class="header_container_common">SF Object ' . esc_html( $sf_object ) . '</div>
                <div class="header_container_common">Object Type ' . esc_html( $object_type ) . '</div>
                <div class="header_container_common">Enable <input type="checkbox" checked /></div>
                <div class="header_container_common"><a optionid="' . esc_attr( $key ) . '" class="delete_mapping" href="#">Delete</a></div>
                <div class="header_container_common">Sync Order : 1</div>
              </div>';

        echo '<table class="widefat striped" style="margin-bottom:30px;">
                <thead><tr>
                    <th>Gravity field</th>
                    <th>Salesforce field</th>
                    <th>Active/InActive</th>
                    <th>Action</th>
                    <th>Sync Direction</th>
                </tr></thead><tbody>';

        foreach ( $mappings as $i => $map ) {
            $gfield_id = $map['gfield'];
            $sffield   = $map['sffield'];
            $m1 = ( $map['wp_to_sf'] === 'on' ) ? 'WP to SF' : '';
            $m2 = ( $map['sf_to_wp'] === 'on' ) ? 'SF to WP' : '';

            $gf_label = $form_fields[ $gfield_id ] ?? 'Unknown Field (' . esc_html( $gfield_id ) . ')';

            echo '<tr>';
            echo '<td>' . esc_html( $gf_label ) .' ('.$gfield_id.')</td>';
            echo '<td>' . esc_html( $sffield ) . '</td>';
            echo '<td><input type="checkbox" checked disabled></td>';
            echo '<td><a href="#" gf-form="' . esc_attr( $form_id ) . '" gf-field="' . esc_attr( $gfield_id ) . '" sf-field="' . esc_attr( $sffield ) . '"
                        data-option="' . esc_attr( $key ) . '" data-option-sfwp="' . esc_attr( $map['sf_to_wp'] ) . '" data-option-wpsf="' . esc_attr( $map['wp_to_sf'] ) . '" class="edit-mapping">Edit</a> |
                      <a href="#" class="delete-mapping-single" sf-field="' . esc_attr( $sffield ) . '" gf-field="' . esc_attr( $gfield_id ) . '" data-option="' . esc_attr( $key ) . '" data-index="' . esc_attr( $i ) . '">Delete</a></td>';
            echo '<td>' . esc_html( $m1 ) . ' | ' . esc_html( $m2 ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    wp_die();
} );

add_action( 'wp_ajax_delete_mapping_action', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
    $wpoption = sanitize_text_field( $_POST['wpoption'] ?? '' );
    if ( $wpoption ) delete_option( $wpoption );
    wp_die();
} );

add_action( 'wp_ajax_delete_mapping_action_single', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $wpoption = sanitize_text_field( $_POST['wpoption'] ?? '' );
    $sf       = sanitize_text_field( $_POST['sf'] ?? '' );
    $gf       = sanitize_text_field( $_POST['gf'] ?? '' );

    $option = get_option( $wpoption );
    if ( isset( $option['mappings'] ) && is_array( $option['mappings'] ) ) {
        foreach ( $option['mappings'] as $index => $mapping ) {
            if ( isset( $mapping['sffield'], $mapping['gfield'] ) && $mapping['sffield'] === $sf && $mapping['gfield'] === $gf ) {
                unset( $option['mappings'][ $index ] );
            }
        }
        $option['mappings'] = array_values( $option['mappings'] );
        update_option( $wpoption, $option );
    }
    wp_die();
} );

add_action( 'wp_ajax_edit_mapping_action_single', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    global $sffields;

    $wpoption = sanitize_text_field( $_POST['wpoption'] ?? '' );
    $sf       = sanitize_text_field( $_POST['sf'] ?? '' );
    $gf       = sanitize_text_field( $_POST['gf'] ?? '' );
    $gff      = sanitize_text_field( $_POST['gff'] ?? '' );
    $sfwp     = sanitize_text_field( $_POST['sfwp'] ?? '' );
    $wpsf     = sanitize_text_field( $_POST['wpsf'] ?? '' );

    $form_obj = GFAPI::get_form( (int) $gf );
    $form_fields = [];
    foreach ( $form_obj['fields'] as $field ) {
        $form_fields[ $field->id ] = $field->label;
    }

    echo '<div class="gf_sf_field_map_container">';
    echo '<div class="common_container"><label>Gravity Form Fields</label><select id="gf_field_select" multiple="multiple">';
    foreach ( $form_fields as $gf_id => $gf_label ) {
        $sel = ( (string) $gf_id === (string) $gff ) ? ' selected' : '';
        echo '<option value="' . esc_attr( $gf_id ) . '"' . $sel . '>' . esc_html( $gf_label ) . '</option>';
    }
    echo '</select></div>';

    echo '<div class="common_container"><label>Salesforce Fields</label><select id="sf_field_select" multiple="multiple">';
    foreach ( $sffields as $sf_key => $sf_label ) {
        $sel = ( (string) $sf_key === (string) $sf ) ? ' selected' : '';
        echo '<option value="' . esc_attr( $sf_key ) . '"' . $sel . '>' . esc_html( $sf_label ) . '</option>';
    }
    echo '</select></div></div>';

    echo esc_html( $wpsf ) . ' ' . esc_html( $sfwp );
    echo '<label>Sync Direction:</label>&nbsp;&nbsp;&nbsp;<input id="wp_to_sf" type="checkbox" ' . ( $wpsf === 'on' ? 'checked' : '' ) . '>WP to SF 
          &nbsp;&nbsp;<input id="sf_to_wp" type="checkbox" ' . ( $sfwp === 'on' ? 'checked' : '' ) . '>SF to WP';

    echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input wpoption="' . esc_attr( $wpoption ) . '" type="button" class="button button-primary" id="update_mapped_value" value="Update">';
    echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="button" class="button button-secondary" id="cancel_curtain" value="Cancel">';

    wp_die();
} );

add_action( 'wp_ajax_edit_mapping_action_single_update', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $wpoption     = sanitize_text_field( $_POST['wpoption'] ?? '' );
    $sf           = ( is_array( $_POST['sf'] ?? '' ) ? ( $_POST['sf'][0] ?? '' ) : sanitize_text_field( $_POST['sf'] ?? '' ) );
    $gff          = ( is_array( $_POST['gf'] ?? '' ) ? ( $_POST['gf'][0] ?? '' ) : sanitize_text_field( $_POST['gf'] ?? '' ) );
    $old_gf_value = ( is_array( $_POST['ogf'] ?? '' ) ? ( $_POST['ogf'][0] ?? '' ) : sanitize_text_field( $_POST['ogf'] ?? '' ) );
    $old_sf_value = ( is_array( $_POST['osf'] ?? '' ) ? ( $_POST['osf'][0] ?? '' ) : sanitize_text_field( $_POST['osf'] ?? '' ) );
    $wpsf         = sanitize_text_field( $_POST['wpsf'] ?? '' );
    $sfwp         = sanitize_text_field( $_POST['sfwp'] ?? '' );

    $existing = get_option( $wpoption );
    if ( ! is_array( $existing ) || ! isset( $existing['mappings'] ) || ! is_array( $existing['mappings'] ) ) {
        wp_send_json_error( [ 'message' => 'Invalid option format.' ] );
    }

    foreach ( $existing['mappings'] as &$map ) {
        if ( isset( $map['sffield'] ) && is_array( $map['sffield'] ) && count( $map['sffield'] ) === 1 ) {
            $map['sffield'] = $map['sffield'][0];
        }
    }
    unset( $map );

    foreach ( $existing['mappings'] as $map ) {
        if ( isset( $map['sffield'], $map['gfield'] ) && $map['sffield'] === $sf && $map['gfield'] === $gff
            && !( $map['sffield'] === $old_sf_value && $map['gfield'] === $old_gf_value ) ) {
            wp_send_json_error( [ 'message' => 'Mapping already exists.' ] );
        }
    }

    $found = false;
    foreach ( $existing['mappings'] as &$map ) {
        if ( isset( $map['sffield'], $map['gfield'] ) && $map['sffield'] === $old_sf_value && $map['gfield'] === $old_gf_value ) {
            $map['sffield']  = $sf;
            $map['gfield']   = $gff;
            $map['sf_to_wp'] = $sfwp;
            $map['wp_to_sf'] = $wpsf;
            $found = true;
            break;
        }
    }
    unset( $map );

    if ( ! $found ) {
        wp_send_json_error( [ 'message' => 'Original mapping not found.' ] );
    }

    update_option( $wpoption, $existing );
    wp_send_json_success( [ 'message' => 'Mapping updated successfully.', 'option_key' => $wpoption ] );
} );



add_action( 'gform_loaded', function () {
    if ( class_exists( 'GF_Fields' ) && class_exists( 'GF_Field_WCProduct_Custom' ) ) {
        try {
            GF_Fields::register( new GF_Field_WCProduct_Custom() );
        } catch ( \Exception $e ) {
            error_log( 'gf-wc: field registration skipped - ' . $e->getMessage() );
        }
    }
}, 5 );


add_action( 'gform_loaded', function () {
    if ( class_exists( 'GF_Fields' ) && class_exists( 'GF_Field_WCProduct_Calculator' ) ) {
        try {
            GF_Fields::register( new GF_Field_WCProduct_Calculator() );
        } catch ( \Exception $e ) {
            error_log( 'gf-wc: field registration skipped - ' . $e->getMessage() );
        }
    }
}, 5 );


add_action( 'gform_field_standard_settings', function( $position, $form_id ) {
    if ( $position !== 50 ) return;
    ?>
    <li class="wc-product-setting field_setting" id="wc-product-setting-li">
        <label for="wc_product_setting"><?php esc_html_e( 'WooCommerce product', 'gf-wc-product' ); ?></label>
        <select id="wc_product_setting" style="width:100%;" onchange="SetFieldProperty('wc_product_id', this.value);">
            <option value=""><?php esc_html_e( '-- Loading products --', 'gf-wc-product' ); ?></option>
        </select>
        <p class="description"><?php esc_html_e( 'Choose the WooCommerce product to display. Only the product ID will be submitted and used for payment calculation.', 'gf-wc-product' ); ?></p>
    </li>
    <?php
}, 10, 2 );


add_action( 'gform_field_standard_settings', function( $position ) {
    if ( $position !== 50 ) return;
    ?>
    <!-- Reference Field -->
    <li class="event-ref-field-setting field_setting">
        <label for="event_ref_field">Reference Field</label>
        <select id="event_ref_field" style="width:100%;"></select>
        <p class="description">Select another field from this form.</p>
    </li>

    <!-- WooCommerce Product -->
    <li class="event-wc-product-setting field_setting">
        <label for="wc_product_setting">WooCommerce Product</label>
        <select id="wc_product_calculator_setting" style="width:100%;"></select>
        <p class="description">Select a WooCommerce product.</p>
    </li>
    <?php
});


add_filter( 'gform_editor_js_settings', function( $settings ) {

    if ( ! isset( $settings['field'] ) ) {
        $settings['field'] = [];
    }

    $settings['field']['event_ref_field'] = '';
    $settings['field']['wc_product_id']   = '';

    return $settings;
});


add_filter( 'gform_editor_js_settings', function( $settings ) {
    if ( ! is_array( $settings ) ) $settings = [];
    if ( ! isset( $settings['field'] ) || ! is_array( $settings['field'] ) ) $settings['field'] = [];
    if ( ! isset( $settings['field']['wc_product_id'] ) ) $settings['field']['wc_product_id'] = '';
    return $settings;
} );


add_action( 'wp_enqueue_scripts', 'myplugin_enqueue' );
function myplugin_enqueue() {
    wp_localize_script(
        'myplugin-script',
        'MyPluginAjax',
        array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('myplugin_nonce'),
        )
    );
}

add_action( 'admin_print_footer_scripts', function () {
    if ( is_admin() ) { ?>
    <script>
    (function($){
        var fallbackSettingsHtml =
            '<li class="wc-product-setting field_setting" id="wc-product-setting-li-fallback">' +
            '<label for="wc_product_setting">WooCommerce product</label>' +
            '<select id="wc_product_setting" style="width:100%;"><option value="">-- Loading products --</option></select>' +
            '<p class="description">Choose the WooCommerce product to display.</p>' +
            '</li>';

        function getVisibleSettingsContainer() {
            var selectors = ['#field-settings .panel-body','.gform_editor_container .field_settings .panel-body','.gform_editor .field_settings','#gform_field_settings .panel-body','.field-settings'];
            for (var i=0;i<selectors.length;i++) {
                var $c = $(selectors[i]);
                if ($c && $c.length && $c.is(':visible')) return $c;
            }
            var $any = $('.field_settings:visible, #field-settings:visible').first();
            if ($any && $any.length) return $any;
            return null;
        }

        function ensureSettingsMarkupPresent() {
            var $serverLi = $('#wc-product-setting-li');
            if ($serverLi && $serverLi.length) { $serverLi.show(); return $('#wc_product_setting'); }
            if ($('#wc-product-setting-li-fallback').length) { return $('#wc_product_setting'); }
            var $container = getVisibleSettingsContainer();
            if ($container) {
                var $list = $container.find('ul, .settings-list').first();
                if ($list && $list.length) { $list.append(fallbackSettingsHtml); }
                else { $container.append('<ul class="settings-list"></ul>'); $container.find('ul.settings-list').append(fallbackSettingsHtml); }
                return $('#wc_product_setting');
            }
            return null;
        }

        function populateProductsInto($select, selectedId) {
            if (!$select || $select.length === 0) return;
            $select.html('<option value="">-- Loading products --</option>');
            $.ajax({
                url: ajaxurl, method: 'GET', dataType: 'json', data: { action: 'gf_wc_get_products' },
                success: function(response) {
                    var data = null;
                    if (response && response.success && $.isArray(response.data)) data = response.data;
                    else if ($.isArray(response)) data = response;
                    else { console.error('gf-wc: unexpected product payload', response); $select.html('<option value="">-- No products --</option>'); return; }
                    $select.empty().append('<option value="">-- Select product --</option>');
                    $.each(data, function(i,p){
                        if (!p || typeof p.id === 'undefined') return;
                        var sel = (selectedId && selectedId == p.id) ? ' selected' : '';
                        $select.append('<option value="'+p.id+'"'+sel+'>' + (p.title || ('#' + p.id)) + '</option>');
                    });
                },
                error: function(){ console.error('gf-wc: products AJAX error', arguments); $select.html('<option value="">-- Error loading --</option>'); }
            });
        }

        $(document).on('gform_load_field_settings', function(event, field){
            try {
                if (!field || field.type !== 'wc_product_custom') return;
                var $select = $('#wc_product_setting');
                if (!$select || $select.length === 0) $select = ensureSettingsMarkupPresent(); else $('#wc-product-setting-li').show();

                if ($select && $select.length) {
                    var saved = field['wc_product_id'] || '';
                    populateProductsInto($select, saved);
                    $select.off('change.gf_wc').on('change.gf_wc', function(){
                        var v = $(this).val();
                        if (typeof SetFieldProperty === 'function') SetFieldProperty('wc_product_id', v); else field['wc_product_id'] = v;
                        if (v) {
                            $.ajax({ url: ajaxurl, dataType:'json', data:{ action:'gf_wc_get_product_price', product_id:v }, success:function(resp){
                                if (resp && resp.success && resp.data && typeof resp.data.price !== 'undefined') {
                                    var price = parseFloat(resp.data.price).toFixed(2);
                                    $('#field_' + field.id + ' .field_label').text((field.label || 'Product') + ' — ' + price);
                                }
                            }});
                        }
                    });
                }
            } catch (err) { console.error('gf-wc: error in settings handler:', err); }
        });

        $(document).on('gform_field_added', function(event, field){
            try { if (field && field.type === 'wc_product_custom') setTimeout(function(){ $(document).trigger('gform_load_field_settings', [field]); }, 200); } catch (e) {}
        });

        $(function(){
            var $initial = $('#wc_product_setting:visible').first();
            if ($initial && $initial.length) populateProductsInto($initial, '');
        });
    })(jQuery);
    </script>
<?php }} );

/* AJAX: product listing & price */
add_action( 'wp_ajax_gf_wc_get_products', function () {
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'forbidden', 403 );
    if ( ! class_exists( 'WC_Product' ) ) wp_send_json_error( 'woocommerce not active' );

    try { $products = wc_get_products( [ 'limit' => 200, 'status' => 'publish' ] ); }
    catch ( \Exception $e ) { wp_send_json_error( 'wc error: ' . $e->getMessage() ); }

    $out = [];
    foreach ( (array) $products as $p ) {
        if ( ! $p ) continue;
        $out[] = [ 'id' => $p->get_id(), 'title' => wp_strip_all_tags( $p->get_name() . ' (ID:' . $p->get_id() . ')' ) ];
    }
    wp_send_json_success( $out );
} );
add_action( 'wp_ajax_nopriv_gf_wc_get_product_price', 'gf_wc_get_product_price' );
add_action( 'wp_ajax_gf_wc_get_product_price', 'gf_wc_get_product_price' );
function gf_wc_get_product_price() {
    $pid = isset( $_REQUEST['product_id'] ) ? intval( $_REQUEST['product_id'] ) : 0;
    if ( ! $pid || ! class_exists( 'WC_Product' ) ) wp_send_json_error( [ 'price' => 0 ] );
    $p = wc_get_product( $pid );
    if ( ! $p ) wp_send_json_error( [ 'price' => 0 ] );
    $price = $p->get_price();
    wp_send_json_success( [ 'price' => (float) $price ] );
}



add_filter( 'gform_product_info', function( $product_info, $form, $entry ) {


    $wc_fields = [];

    foreach ( $form['fields'] as $f ) {
        if (
            isset( $f->type )
            && in_array( $f->type, [ 'wc_product_custom', 'wc_product_calculator' ], true )
        ) {
            $wc_fields[] = $f;
        }
    }

    if ( empty( $wc_fields ) ) {
        return $product_info;
    }

    $products = [];

    foreach ( $wc_fields as $f ) {

        $field_id   = (int) $f->id;
        $posted_key = 'input_' . $field_id;
        $pid        = 0;

        /* -------------------------------
         * Resolve product ID
         * ------------------------------- */
        $posted_val = rgpost( $posted_key );
        if ( ! empty( $posted_val ) ) {
            $pid = (int) $posted_val;
        }

        if ( ! $pid && ! empty( $entry ) && isset( $entry[ $field_id ] ) ) {
            $pid = (int) rgar( $entry, (string) $field_id );
        }

        if ( ! $pid && ! empty( $f->wc_product_id ) ) {
            $has_cond = ! empty( $f->conditionalLogic ) || ! empty( $f->conditional_logic );
            if ( ! $has_cond ) {
                $pid = (int) $f->wc_product_id;
            }
        }

        if ( ! $pid || ! function_exists( 'wc_get_product' ) ) {
            continue;
        }

        $product = wc_get_product( $pid );
        if ( ! $product ) {
            continue;
        }

        /* -------------------------------
         * Resolve price
         * ------------------------------- */
        $price = 0;

        if ( $f->type === 'wc_product_custom' ) {

            $price = $product->get_price();

        } elseif ( $f->type === 'wc_product_calculator' ) {

            $price_key = 'input_price_' . $field_id;
            $posted_price = rgpost( $price_key );

            if ( $posted_price !== null && $posted_price !== '' ) {
                $price = (float) $posted_price;
            } elseif ( ! empty( $entry ) && isset( $entry[ $price_key ] ) ) {
                $price = (float) rgar( $entry, $price_key );
            }
        }

        if ( $price === '' || $price === null ) {
            $price = 0;
        }

        /* -------------------------------
         * Resolve QUANTITY  ✅ NEW
         * ------------------------------- */
        $qty = 1;

        $qty_key = 'input_qty_' . $field_id;
        $posted_qty = rgpost( $qty_key );

        if ( $posted_qty !== null && $posted_qty !== '' ) {
            $qty = max( 1, (int) $posted_qty );
        }
        elseif ( ! empty( $entry ) && isset( $entry[ $qty_key ] ) ) {
            $qty = max( 1, (int) rgar( $entry, $qty_key ) );
        }



        /* -------------------------------
 * Checkbox reference → quantity
 * ------------------------------- */
if ( $f->type === 'wc_product_calculator' && ! empty( $f->event_ref_field ) ) {

    $ref_field_id = (int) $f->event_ref_field;
    $checkbox_count = 0;

    // 1️⃣ Count POSTED checkbox values
    foreach ( $_POST as $key => $val ) {
        if ( strpos( $key, 'input_' . $ref_field_id . '.' ) === 0 && $val !== '' ) {
            $checkbox_count++;
        }
    }

    // 2️⃣ Fallback: count entry values (resume/edit)
    if ( $checkbox_count === 0 && ! empty( $entry ) ) {
        foreach ( $entry as $key => $val ) {
            if ( strpos( (string) $key, $ref_field_id . '.' ) === 0 && $val !== '' ) {
                $checkbox_count++;
            }
        }
    }

    // 3️⃣ If checkboxes selected → override quantity
    if ( $checkbox_count > 0 ) {
        $qty = $checkbox_count;
    }
}

        /* -------------------------------
         * Inject into GF pricing engine
         * ------------------------------- */
        $products[] = [
            'id'       => $pid,
            'name'     => $product->get_name(),
            'price'    => (float) $price,
            'quantity' => $qty, // ✅ quantity now works
            'options'  => [],
        ];
    }

    if ( ! empty( $products ) ) {
        $product_info['products'] = $products;
        $product_info['options']  = [];
        $product_info['shipping'] = [];
    }

    return $product_info;

}, 10, 3 );



/* Update frontend Total (optional; requires a Total field on form) */



add_action( 'gform_enqueue_scripts', function( $form ) {

    $wc_field_ids = [];
    foreach ( $form['fields'] as $f ) {
        if ( isset( $f->type ) && $f->type === 'wc_product_custom' ) {
            $wc_field_ids[] = intval( $f->id );
        }
    }
    if ( empty( $wc_field_ids ) ) return;

    wp_enqueue_script( 'jquery' );
    ?>
    <script>
    (function($){

        $('.gf-wc-product-qty').on('change input', function () {
         $(this).trigger('change');
        });


           $(document).on('gform_post_render', function (event, formId) {

        $('.gf-wc-calculator').each(function () {

            const $calculator = $(this);
            const calcFieldId = $calculator.data('calculator-field');
            const refFieldId  = $calculator.data('ref-field');

            if (!refFieldId) return;

            // Checkbox inputs for THIS calculator only
            const selector = 'input[name^="input_' + refFieldId + '."]';

            // Prevent double-binding
            $(document).off('change.gfCalcQty' + calcFieldId, selector);

            // Bind checkbox change
            $(document).on('change.gfCalcQty' + calcFieldId, selector, function () {

                let count = 0;

                $(selector + ':checked').each(function () {
                    count++;
                });

                // Update quantity input (if present)
                const $qtyInput = $('input[name="input_qty_' + calcFieldId + '"]');
                if ($qtyInput.length && count > 0) {
                    $qtyInput.val(count);
                }

                // 🔥 THIS tells Gravity Forms to recalc totals
                if (window.gform && gform.doAction) {
                    gform.doAction('gform_input_change', formId, calcFieldId);
                }

            });

        });

    });


        var wcFieldIds = <?php echo wp_json_encode($wc_field_ids); ?>;

        function fetchPrice(pid) {
            return $.ajax({
                url: ajaxurl,
                method: 'GET',
                dataType: 'json',
                data: { action: 'gf_wc_get_product_price', product_id: pid }
            }).then(function(resp){
                if (resp && resp.success && resp.data && typeof resp.data.price !== 'undefined') {
                    return parseFloat(resp.data.price) || 0;
                }
                return 0;
            }, function() { return 0; });
        }

        // Determine if a field is visible (active) in the form UI
        function isFieldActive(fid) {
            var $input = $('input[name="input_' + fid + '"]');
            if ( !$input || !$input.length ) return false;
            var $gfield = $input.closest('.gfield');
            return $gfield.length ? $gfield.is(':visible') : $input.is(':visible');
        }

        // Optional: read quantity field if present (naming: input_{fid}_qty)
        function getQuantityForField(fid) {
            var $q = $('input[name="input_' + fid + '_qty"]');
            if ( $q.length ) {
                var v = parseFloat($q.val()) || 0;
                return v > 0 ? v : 1;
            }
            return 1;
        }

        function updateTotalDisplay(total) {
            // Update GF total UI placeholders used in many themes/plugins
            $('.ginput_total, .gform_total').text(parseFloat(total || 0).toFixed(2));
        }

        function recalcAll() {
            var promises = [];
            var activeFids = [];

            wcFieldIds.forEach(function(fid){
                if ( isFieldActive(fid) ) {
                    // read posted product id from input value (hidden input or select)
                    var pid = $('input[name="input_' + fid + '"]').val() || $('select[name="input_' + fid + '"]').val();
                    if ( pid ) {
                        activeFids.push({ fid: fid, pid: pid });
                        promises.push(fetchPrice(pid));
                    }
                }
            });

            if ( promises.length === 0 ) {
                updateTotalDisplay(0);
                return;
            }

            $.when.apply($, promises).then(function(){
                var results = arguments;
                // If only one promise, jQuery returns single value instead of array of arrays
                var prices = [];
                if ( promises.length === 1 ) {
                    prices.push( results[0] );
                } else {
                    // results is array-like of [price, textStatus, jqXHR] for each promise
                    for ( var i = 0; i < results.length; i++ ) {
                        // each result entry may be [price] or price directly depending on jQuery version
                        var p = Array.isArray(results[i]) ? results[i][0] : results[i];
                        prices.push( parseFloat(p) || 0 );
                    }
                }

                // compute total with quantities if any
                var total = 0;
                for ( var j = 0; j < activeFids.length; j++ ) {
                    var qty = getQuantityForField(activeFids[j].fid);
                    var price = prices[j] || 0;
                    total += price * qty;
                }
                updateTotalDisplay(total);
            });
        }

        // Recalc when GF finishes rendering a form
        $(document).on('gform_post_render', function(){
            recalcAll();
        });

        // Recalc after conditional logic runs (GF fires this event)
        $(document).on('gform_post_conditional_logic', function(){
            recalcAll();
        });

        // Recalc when inputs change (useful if you provide quantity inputs or other controls)
        $(document).on('change keyup', '.gform_wrapper input, .gform_wrapper select', function(){
            // small debounce to avoid rapid repeated calls
            if ( window.__gf_wc_recalc_timeout ) clearTimeout(window.__gf_wc_recalc_timeout);
            window.__gf_wc_recalc_timeout = setTimeout(function(){ recalcAll(); }, 120);
        });

        // Initial run on DOM ready (covers some themes that don't trigger GF events)
        $(function(){ recalcAll(); });
    })(jQuery);
    </script>
    <?php
}, 10, 2 );



// After Completion Create Account and Order 



function sf_fetch_charge_id($entry,$action){

    $stripe_addon = gf_stripe();
    if ( ! $stripe_addon ) {
        return;
    }

    // Include Stripe PHP library
    $stripe_addon->include_stripe_api();

    try {
        $secret_key = $stripe_addon->get_secret_api_key();
        \Stripe\Stripe::setApiKey( $secret_key );
    } catch ( \Exception $e ) {
        error_log( 'GF Stripe: could not set API key: ' . $e->getMessage() );
        return;
    }

    // These are what we want to extract
    $payment_intent_id = '';
    $charge_id         = '';
    $payment_method_id = ''; 
    try {

        if ( strpos( $action['transaction_id'], 'pi_' ) === 0 ) {
            // Gravity Forms gave us a Payment Intent ID
            $intent            = \Stripe\PaymentIntent::retrieve( $action['transaction_id'] );
            $payment_intent_id = $intent->id;
            $payment_method_id = $intent->payment_method;

            if ( ! empty( $intent->charges->data[0]->id ) ) {
                $charge_id = $intent->charges->data[0]->id;
            }

        } elseif ( strpos( $action['transaction_id'], 'ch_' ) === 0 ) {
            // Gravity Forms gave us a Charge ID
            $charge            = \Stripe\Charge::retrieve( $action['transaction_id'] );
            $charge_id         = $charge->id;
            $payment_intent_id = $charge->payment_intent;
            $payment_method_id = $charge->payment_method;

        } else {
            // Some other format (subscription id, etc.) – you can log and inspect if needed
            error_log( 'GF Stripe: unknown transaction id format: ' . $gf_txn_id );
        }

    } catch ( \Exception $e ) {
        error_log( 'GF Stripe: error looking up Stripe object: ' . $e->getMessage() );
    }


return $charge_id;

// End Fetching Charge ID


}









add_action( 'gform_post_payment_completed', function( $entry, $action ) {



    ob_start();
    dbg("Payment Complete Hook");
    global $wpdb;

    $entry_id   = (int) rgar( $entry, 'id' );
    $form_id    = rgar( $entry, 'form_id' );
    $form       = GFAPI::get_form( $form_id );
    $like_pattern = 'gfid_' . $form_id . '_sfobject_%';


    if ( ! gf_form_has_payment( $form ) ) {
        return;
    }



    if ( gform_get_meta( $entry_id, '_payment_processed' ) ) {
        return;
    }
    gform_update_meta( $entry_id, '_payment_processed', 1 );
    $map = get_option( 'gf_Member_Event_form_mapping', [] );
    $individual_form = (int) ( $map['membership_registration'] ?? 0 );
    $corporate_form  = (int) ( $map['membership_registration_group'] ?? 0 );
    $event_registration = (int) ( $map['event_registration'] ?? 0 );
    $course_registration = (int) ( $map['course_registration'] ?? 0 );
    $exam_registration = (int) ( $map['exam_registration'] ?? 0 );
    $ce_request = (int) ( $map['ce_request'] ?? 0 );
    $membership_renewal = (int) ( $map['membership_renewal'] ?? 0 );
    $individual_ispa = (int) ( $map['individual_membership_ispa'] ?? 0 );
    $corporate_ispa = (int) ( $map['corporate_membership_ispa'] ?? 0 );
    $corporate_event_ispa = (int) ( $map['corporate_event_ispa'] ?? 0 );
    

    $charge_id = sf_fetch_charge_id($entry,$action);



    if ( $form_id == $corporate_event_ispa ) {
        gf_handle_event_group_registration( $entry, $action );
        return;

    }


    

    // Currently Expanding for Memberships will create classes later
    if ( $form_id == $corporate_form ) {
        gf_handle_corporate_membership_payment( $entry, $action );
        return;

    }


        if ( $form_id == $corporate_ispa ) {
        gf_handle_corporate_membership_payment_ispa( $entry, $action );
        return;

    }                    


    if ( $form_id == $individual_form ) {
        gf_handle_individual_membership_payment( $entry, $action );
        return;

    }

    if($form_id == $event_registration){

    if ( gform_get_meta( $entry_id, '_event_payment_processed' ) ) {
        return;
    }

    gform_update_meta( $entry_id, '_event_payment_processed', 1 );

    gf_handle_event_product_payment( $entry, $action );

    return;
        
    }
    

   if($form_id == $course_registration){
    gf_handle_course_registration_payment( $entry, $action );
    return;
        
    }

       if($form_id == $exam_registration){
    gf_handle_exam_registration_payment( $entry, $action );
    return;
        
    }

    if($form_id == $ce_request){
    gf_handle_ce_request_payment( $entry, $action );
    return;
        
    }

        if($form_id == $membership_renewal){
    gf_handle_membership_renewal_payment( $entry, $action );
    return;
        
    }

    if ( $form_id == $individual_ispa ) {
    gf_handle_individual_membership_ispa_payment($entry,$action);
    }


// Fetching Charge ID as Requested

    $stripe_addon = gf_stripe();
    if ( ! $stripe_addon ) {
        return;
    }

    // Include Stripe PHP library
    $stripe_addon->include_stripe_api();

    try {
        $secret_key = $stripe_addon->get_secret_api_key();
        \Stripe\Stripe::setApiKey( $secret_key );
    } catch ( \Exception $e ) {
        error_log( 'GF Stripe: could not set API key: ' . $e->getMessage() );
        return;
    }

    // These are what we want to extract
    $payment_intent_id = '';
    $charge_id         = '';
    $payment_method_id = ''; 
    try {

        if ( strpos( $action['transaction_id'], 'pi_' ) === 0 ) {
            // Gravity Forms gave us a Payment Intent ID
            $intent            = \Stripe\PaymentIntent::retrieve( $action['transaction_id'] );
            $payment_intent_id = $intent->id;
            $payment_method_id = $intent->payment_method;

            if ( ! empty( $intent->charges->data[0]->id ) ) {
                $charge_id = $intent->charges->data[0]->id;
            }

        } elseif ( strpos( $action['transaction_id'], 'ch_' ) === 0 ) {
            // Gravity Forms gave us a Charge ID
            $charge            = \Stripe\Charge::retrieve( $action['transaction_id'] );
            $charge_id         = $charge->id;
            $payment_intent_id = $charge->payment_intent;
            $payment_method_id = $charge->payment_method;

        } else {
            // Some other format (subscription id, etc.) – you can log and inspect if needed
            error_log( 'GF Stripe: unknown transaction id format: ' . $gf_txn_id );
        }

    } catch ( \Exception $e ) {
        error_log( 'GF Stripe: error looking up Stripe object: ' . $e->getMessage() );
    }



// End Fetching Charge ID


    $options = $wpdb->get_results(
    $wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",$like_pattern), ARRAY_A );

    $has_sf_account_field = (bool) array_filter(
    $form['fields'],
    fn($f) => isset($f->type) && $f->type === 'sf_account_id'
    );


    $payload = [];
    $order_created = false;
    foreach ( $options as $option ) {

        $sfMappings = maybe_unserialize( $option['option_value'] );

        if ( preg_match( '/sfobject_(.*?)_Common/', $option['option_name'], $matches ) ) {
        $mappingName = $matches[1];
        }

        foreach ( $sfMappings['mappings'] as $map ) {

                $sf = $map['sffield'];
                $gf = $map['gfield'];
                //
                $field_type = gf_get_field_type( $form, $gf );
        
                // Grab value
                $value = rgar( $entry, $gf ); // safer than $entry[$gf]

                // Skip if empty (null, "", 0-length)
                if ( $value === null || $value === '' ) {
                    continue;
                }
            if($field_type=="checkbox"){
            $payload[$sf] = 'true';
            }else{
            $payload[ $sf ] = $value;
            }    

                

            } // end foreach $sfMappings['mappings'] as $map




        // Account Type Mapping - Will Create Special Cases (maybe switch statement ?)
        if ( $mappingName == 'Account') {


            $account_update = [];
            $account_update[] = ['attributes' => [ 'type' => $mappingName ],] + $payload;

            

            if ( $account_update ) {
                
                $payload  = [ 'records' => $account_update ];
                $response = SF_APIConnector::postCURLObject( json_encode( $payload ), 'POST' );
            } 

            if ( ! empty( $response ) && isset( $response[0]->id ) ) {

                $sf_id = $response[0]->id;
                $universal_ser = get_option( 'sf_field_mapping_data_user' );
                $gf_ser        = get_option( 'gfid_' . $form_id . '_sfobject_Account_Common' );
                $maps          = build_woo_to_gf_mapping( $universal_ser, $gf_ser );
                $guser_id      = create_user_fromgf_registration( $entry, $maps );
                update_user_meta( $guser_id, 'sf_object_id', $sf_id );
                update_user_meta( $guser_id, 'sf_account_id', $sf_id );

            } 

            do_action( 'user_register', $guser_id );

            // Generate Order and Post it to SF Custom
            $product_ids = gfwc_collect_product_ids( $form, $entry );
            $quantity = gfwc_collect_ref_quantity($form,$entry);
            if(empty($quantity)){
                $quantity = 1;
            }
            // Order Creation Task
            if(!$order_created){
                  dbg("Order 7");
            $order = wc_create_order( [ 'customer_id' => $guser_id ] );

            foreach ( $product_ids as $pid ) {
                if ( $p = wc_get_product( $pid ) ) {
                // Here we have to fetch the Quantity Based on The Selection   
                // $pid is the product id and $p is the product object 
                    $order->add_product( $p, $quantity );
                }
            } // end foreach $product_ids

            $user = get_userdata( $guser_id );
            $order->set_billing_email( $user->user_email );
            if ( $first_name ) $order->set_billing_first_name( $user->first_name );
            if ( $last_name )  $order->set_billing_last_name( $user->last_name );

            $txn_id = $charge_id;
            $order->set_payment_method( 'Credit Card' );
            $order->set_payment_method_title( 'Credit Card' );
            $order->calculate_totals();
            $order->update_meta_data( 'sf_account_id', $sf_id );
            $order->update_meta_data( '_wc_order_date', date('Y-m-d') ); 
            $order->update_meta_data('sf_date_created',current_time( 'Y-m-d' ));
            $order->calculate_taxes();
            $order->calculate_totals();
            $order->save();

            if ( $txn_id ) {
                $order->payment_complete( $txn_id );
                $order->update_status( 'completed', 'Gravity Forms marked entry Paid.' );
            } // end if $txn_id
            // Fetching User From Salesforce (not needed right now)
            $query = "SELECT Id FROM Account WHERE PersonEmail = '".$user->user_email."'";
            $responses = SF_APIConnector::getSQueryObject( $query, 10 );

            $order_update[] = [
                'attributes'            => [ 'type' => 'IS_Order__c' ],
                'IS_Wordpress_Id__c'    => $order->get_id(),
                'IS_Account__c'        => $sf_id,
                'IS_Payment_Method__c' => 'Credit Card',
                'Mapping_Name' => $mappingName,
            ];      


            update_post_meta( $order->get_id(), 'sf_account_id',$sf_id);
            do_action( 'woocommerce_thankyou', $order->get_id() );
            $obj    = new SF_108Connector_AdminSettings();
            $result = $obj->sf_export_post_sync(array($order->get_id()), 'shop_order',true);
            // Order Successfully Created for Account Type 
            $order_created = true;
            }
        

        }else{
           // Anything Other then Account
 
            $guser_id = get_current_user_id();
        

            // Order Creation Task
            if(!$order_created){
              $product_ids = gfwc_collect_product_ids( $form, $entry );
              $quantity = gfwc_collect_ref_quantity($form,$entry);
              if(empty($quantity)){
              $quantity = 1;
              }
  dbg("Order 71");
              $order = wc_create_order( [ 'customer_id' => get_current_user_id() ] );

              foreach ( $product_ids as $pid ) {
                if ( $p = wc_get_product( $pid ) ) {
                    $order->add_product( $p, $quantity ); 
                }
               } 

              $user = get_userdata( $guser_id );
              $sf_id = get_user_meta( $guser_id, 'sf_object_id',true);
             $order->set_billing_email( $user->user_email );
              if ( $first_name ) $order->set_billing_first_name( $user->first_name );
              if ( $last_name )  $order->set_billing_last_name( $user->last_name );

              $txn_id = $charge_id;
              $order->set_payment_method( 'Credit Card' );
              $order->set_payment_method_title( 'Credit Card' );

            
              $order->update_meta_data( 'sf_account_id', $sf_id ); 
              $order->update_meta_data( '_wc_order_date', date('Y-m-d') ); 
              $order->update_meta_data('sf_date_created',current_time( 'Y-m-d' ));
              $order->calculate_taxes();
              $order->calculate_totals();
              $order->save();
               if ( $txn_id ) {
                $order->payment_complete( $txn_id );
                $order->update_status( 'completed', 'Gravity Forms marked entry Paid.' );
              } 
             do_action( 'woocommerce_thankyou', $order->get_id() );


             $query     = "SELECT Id FROM Account WHERE PersonEmail = '".$user->user_email."'";
             $responses = SF_APIConnector::getSQueryObject( $query, 10 );
               $order_update[] = [
                'attributes'            => [ 'type' => 'IS_Order__c' ],
                'IS_Wordpress_Id__c'    => $order->get_id(),
                'IS_Account__c'        => $responses[0]->Id,
                'IS_Payment_Method__c' => 'Credit Card',
                'Mapping_Name' => $mappingName,
                'Object_ID_FS' => $order->get_id()
             ];
             $order_created = true;
            }

         

        }

        if ( $order_update ) {
            if($mappingName=="IS_Form_Submission__c"){
            $payload['IS_Order__c'] = get_post_meta($order->get_id(),'sf_object_id',true);
            }
         //  $payload = $payload['records'][0];
           $account_update = [];
            $account_update[] = ['attributes' => [ 'type' => $mappingName ],] + $payload;
            if ( $account_update ) {
                $payload  = [ 'records' => $account_update ];
                $response = SF_APIConnector::postCURLObject( json_encode( $payload ), 'POST' );
                do_action('gfForm_Submission_Upload',$response + $entry);

            } 
        
                  // end account type mapping branch for when $mappingName == 'Account' || 'IS_Order__c'
        } else {

            foreach ( $sfMappings['mappings'] as $map ) {

                $sf = $map['sffield'];
                $gf = $map['gfield'];
                $payload[ $sf ] = $entry[ $gf ] ?? null;

            } 

            $account_update = [];
            $account_update[] = [
                'attributes' => [ 'type' => $mappingName ],
            ] + $payload;

            if ( $account_update ) {
                $payload  = [ 'records' => $account_update ];
                // Getting Order Before Posting 
            //    $order_object = $order->get_meta( 'sf_object_id', true );
                // Trying to fetch Order Object ID

             //   $response = SF_APIConnector::postCURLObject( json_encode( $payload ), 'POST' );

                // This Response is appearing blank (have to see why ?)
            } // end if $account_update

        } // end else (for if $order_update)

        // End Account Type foreach loop for this $option
    } // end foreach $options as $option
                       
    return ob_get_clean();

}, 10, 2 );



if ( ! function_exists( 'gfwc_collect_product_ids' ) ) {

function gfwc_collect_product_ids( $form, $entry ) : array {

    $ids = [];

    if ( empty( $form['fields'] ) ) {
        return $ids;
    }

    foreach ( $form['fields'] as $f ) {


        if ( isset( $f->type ) && $f->type === 'wc_product_custom' ) {

            $fid        = (int) $f->id;
            $posted_key = 'input_' . $fid;
            $pid        = 0;

            // Submitted value
            $posted_val = rgpost( $posted_key );
            if ( ! empty( $posted_val ) ) {
                $pid = intval( $posted_val );
            }

            // Fallback to default WC product if no conditional logic
            if ( ! $pid && ! empty( $f->wc_product_id ) ) {
                $has_cond = ! empty( $f->conditionalLogic ) || ! empty( $f->conditional_logic );
                if ( ! $has_cond ) {
                    $pid = intval( $f->wc_product_id );
                }
            }

            if ( $pid > 0 ) {
                $product = wc_get_product( $pid );
                if ( $product && floatval( $product->get_price() ) > 0 ) {
                    $ids[] = $pid;
                }
            }
        }

        if ( isset( $f->type ) && $f->type === 'wc_product_calculator' ) {


            if ( empty( $f->wc_product_id ) ) {
                continue;
            }

            // Respect conditional logic (same rule as custom)
            $has_cond = ! empty( $f->conditionalLogic ) || ! empty( $f->conditional_logic );
            if ( $has_cond ) {
                // If field is conditional, only include if it was actually submitted
                $posted_key = 'input_' . (int) $f->id;
                if ( empty( rgpost( $posted_key ) ) ) {
                    continue;
                }
            }

            $pid = intval( $f->wc_product_id );
            if ( $pid <= 0 ) {
                continue;
            }

            $product = wc_get_product( $pid );
            if ( ! $product || floatval( $product->get_price() ) <= 0 ) {
                continue;
            }

            $ids[] = $pid;
        }

        /* ==========================================
         * EVENTS DETAILS (cart-based)
         * ========================================== */
        if ( isset( $f->type ) && $f->type === 'events_details' ) {

            if ( function_exists( 'WC' ) && WC()->cart ) {

                foreach ( WC()->cart->get_cart() as $cart_item ) {

                    $pid = intval( $cart_item['product_id'] ?? 0 );
                    if ( ! $pid ) continue;

                    $product = wc_get_product( $pid );
                    if ( ! $product ) continue;

                    if ( floatval( $product->get_price() ) <= 0 ) continue;

                    $ids[] = $pid;
                }
            }
        }
    }

    return array_values( array_unique( $ids ) );
}



}





if ( ! function_exists( 'gfwc_collect_ref_quantity' ) ) {

function gfwc_collect_ref_quantity( $form, $entry ) : array {

    $quantities = [];

    if ( empty( $form['fields'] ) ) {
        return $quantities;
    }

    foreach ( $form['fields'] as $field ) {

        // Only product calculator widgets
        if ( empty( $field->type ) || $field->type !== 'wc_product_calculator' ) {
            continue;
        }

        // Calculator must have a reference field
        if ( empty( $field->event_ref_field ) ) {
            continue;
        }

        $ref_field_id = (string) $field->event_ref_field;
        $checked_count = 0;

        // Count checked checkbox inputs (e.g. 5.1, 5.2, 5.3)
        foreach ( $entry as $key => $value ) {
            if ( strpos( (string) $key, $ref_field_id . '.' ) === 0 && ! empty( $value ) ) {
                $checked_count++;
            }
        }

        // Quantity rule
        $qty = $checked_count > 0 ? $checked_count : 1;

        // Keyed by calculator field ID
        $quantities[ (int) $field->id ] = $qty;
    }

    return $quantities;
}



}












function build_woo_to_gf_mapping( $universal_ser, $gf_ser ) {

    // Unserialize
    $universal = maybe_unserialize( $universal_ser );
    $gf        = maybe_unserialize( $gf_ser );
    $gf_maps   = is_array($gf) && isset($gf['mappings']) ? $gf['mappings'] : [];

    // If missing/invalid, return empty
    if ( ! is_array($universal) || ! is_array($gf_maps) ) {
        return [];
    }

    // Priority scoring helper
    $priority = function( $woofield ) {
        if ( strpos($woofield, 'billing_') === 0 ) return 3;
        if ( strpos($woofield, 'shipping_') === 0 ) return 2;
        return 1;
    };

    $sf_to_woo     = [];
    $sf_to_woo_pri = [];

    foreach ( $universal as $row ) {
        if ( ! is_array($row) ) continue;

        $sf  = $row['sffield']  ?? null;
        $woo = $row['woofield'] ?? null;
        $act = $row['isactive'] ?? 'A';

        if ( ! $sf || ! $woo || $act !== 'A' ) continue;

        $p = $priority( $woo );

        if ( ! isset($sf_to_woo[$sf]) || $p > ($sf_to_woo_pri[$sf] ?? 0) ) {
            $sf_to_woo[$sf]     = $woo;
            $sf_to_woo_pri[$sf] = $p;
        }
    }

    $woo_to_gf = [];

    foreach ( $gf_maps as $m ) {
        if ( ! is_array($m) ) continue;

        $sf = $m['sffield'] ?? null;
        $gf = isset($m['gfield']) ? (string)$m['gfield'] : null;

        if ( ! $sf || $gf === null ) continue;

        if ( isset($sf_to_woo[$sf]) ) {
            $woo_to_gf[ $sf_to_woo[$sf] ] = $gf;
        }
    }

    return $woo_to_gf;
}


function create_user_fromgf_registration($entry,$maps){

$form = GFAPI::get_form( $entry['form_id'] );
$user_email = rgar( $entry, $maps['user_email'] );
$user_id    = email_exists( $user_email );
$key = array_search(
    'username',
    array_column($form['fields'], 'type')
);
if ($key !== false) {
    $field = $form['fields'][$key];
    $username = rgar( $entry, $field->id );
}else{
$username = sanitize_user( current( explode( '@', $user_email ) ) );
}



if ( ! $user_id ) {
    $password = '';
  foreach ( $form['fields'] as $field ) {
        $value = rgar( $entry, $field->id );
        switch ( $field->type ) {
        case 'password':
        $password = $value;
        break;
        }
    }

    if ( empty( $password ) ) {
        $password = wp_generate_password( 12, true );
    }
    // Adding User for Registration

            


if ( is_user_logged_in() ) {
    $user_id = get_current_user_id();
} else {
    $existing = get_user_by( 'email', $user_email );

    if ( $existing ) {
        $user_id = $existing->ID;
    } else {
        $user_id = wp_insert_user([
            'user_login' => $user_email,
            'user_email' => $user_email,
            'user_pass'  => $password,
            'role'       => 'customer'
        ]);
    }
}









}

wp_set_current_user( $user_id );
wp_set_auth_cookie( $user_id );



foreach ( $maps as $woo_key => $gfield_id ) {

    $val = rgar( $entry, (string) $gfield_id );

    if ( $val !== '' && $val !== null ) {
        update_user_meta( $user_id, $woo_key, $val );
    }
}


if ( isset( $maps['billing_first_name'] ) ) {
    update_user_meta( $user_id, 'first_name', rgar( $entry, $maps['billing_first_name'] ) );
}
if ( isset( $maps['billing_last_name'] ) ) {
    update_user_meta( $user_id, 'last_name', rgar( $entry, $maps['billing_last_name'] ) );
}



return $user_id;

}


function gf_process_order_resync_queue() {

    if ( get_transient('process_order_lock') ) return;
    set_transient('process_order_lock', 1, 60);
    global $wpdb;
    $options = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'order_process_queue_%'"
    );

    if ( empty($options) ) {
        delete_transient('process_order_lock');
        return;
    }


  $records   = [];
    $index_map = []; 
    foreach ( $options as $option ) {
        $data = maybe_unserialize( $option->option_value );

        // Flatten common shapes: [[record]] -> [record] -> record
        while ( is_array($data) && count($data) === 1 && isset($data[0]) ) {
            $data = $data[0];
        }
        if ( isset($data[0]) && is_array($data[0]) && isset($data[0]['attributes']) ) {
            $data = $data[0];
        }
        if ( ! is_array($data) ) {
            continue; 
        }

        $wp_id   = $data['IS_Wordpress_Id__c'] ?? null;
        $account = $data['IS_Account__c']      ?? $data['IS_Billing_Account__c'] ?? null;
        $mappingName = $data['Mapping_Name'] ?? null;
        $mappingObjectID = $data['Object_ID_FS'] ?? null;

        if ( empty($wp_id) || empty($account) ) {
            continue; // skip incomplete
        }
        $cdate = (new DateTime())->format('Y-m-d');


        // Resending Order to Confirm Sync
        $records[] = [
            'attributes'            => [ 'type' => 'IS_Order__c' ],
            'IS_Wordpress_Id__c'    => $wp_id,
            'IS_Billing_Account__c' => $account,
            'IS_Payment_Method__c'  => 'Credit Card',
            'IS_Order_Date__c'      => $cdate,
        ];

        $index_map[] = [
            'option_name' => $option->option_name,
            'wp_id'       => $wp_id,
            'account'     => $account,
        ];
    }

    if ( empty($records) ) {
        delete_transient('process_order_lock');
        return;
    }

    // Send batch to Salesforce
  //  $payload  = [ 'records' => $records ];
  //  $response = SF_APIConnector::postCURLObject( json_encode($payload), 'POST' );
    // Update WP meta + delete processed options (assumes response order matches request order)
    if(!empty($mappingName) && $mappingName == "IS_Form_Submission__c"){
        // This is Form Submission $mappingObjectID
        $mappingObjectID = get_post_meta( $mappingObjectID, 'sf_object_id', true );
          $records[] = [
            'attributes'            => [ 'type' => 'IS_Form_Submission__c' ],
            'Id' => $mappingObjectID,
            'IS_Order__c' => $response
        ];
        $payload  = [ 'records' => $records ];
        $response = SF_APIConnector::postCURLObject( json_encode($payload), 'PATCH' );

    }



    if ( is_array($response) ) {
        foreach ( $response as $i => $res ) {
            if ( ! isset($index_map[$i]) ) continue;

            $wp_id   = $index_map[$i]['wp_id'];
            $account = $index_map[$i]['account'];

            if ( ! empty($res->id) ) {
                update_post_meta( $wp_id, 'sf_account_id', $account );
                update_post_meta( $wp_id, 'sf_object_id',  $res->id );
                delete_option( $index_map[$i]['option_name'] );
            }
    
    try {
    $obj    = new SF_108Connector_AdminSettings();
    $result = $obj->sf_export_post_sync([$wp_id], 'shop_order',true);
    // Result Contains Order ID as String 

    } catch (\Throwable $e) {
    }

}
  }

    // Release lock
    delete_transient('process_order_lock');

}


add_filter( 'cron_schedules', function ( $schedules ) {
    $schedules['every_minute'] = [
        'interval' => 60,
        'display'  => 'Every Minute',
    ];
    return $schedules;
});

if ( ! wp_next_scheduled( 'gf_process_order_resync_event' ) ) {
    wp_schedule_event( time() + 60, 'every_minute', 'gf_process_order_resync_event' );
}

add_action( 'gf_process_order_resync_event', 'gf_process_order_resync_queue' );


add_action( 'gform_after_submission', 'gf_after_submission_sf_mapping', 10, 2 );
function gf_after_submission_sf_mapping( $entry, $form ) {


    global $wpdb;

     gform_update_meta( $entry_id, '_payment_processed', 1 );

    $map = get_option( 'gf_Member_Event_form_mapping', [] );
    $event_registration = (int) ( $map['event_registration'] ?? 0 );

    $form_id = $form['id'];
    $has_wc_product_custom = false;


    $ce_request = (int) ( $map['ce_request'] ?? 0 );
    $complaint =  (int) ( $map['complaints_form'] ?? 0 );       

    if ( $form_id == $ce_request ) {
    gf_handle_ce_request_form( $entry, $action );
    return;

    
    }

if( $form_id == $event_registration ) {
return;

}


    if( $form_id == $complaint ) {
    if ( function_exists( 'gf_handle_complaint_form' ) ) {
    gf_handle_complaint_form( $entry, $action );
    }
    return;

    }

    if ( gf_form_has_payment( $form ) ) {
        return;
    }

    foreach ( $form['fields'] as $field ) {

        $field_type = method_exists( $field, 'get_input_type' )
            ? $field->get_input_type()
            : $field->type;

        if ( $field_type === 'wc_product_custom' ) {
            $has_wc_product_custom = true;
            break;
        }
    } 

  
    if ( $has_wc_product_custom ) {
        return;
    } 

    $like_pattern = 'gfid_' . $form_id . '_sfobject_%';
    $options = $wpdb->get_results(
        $wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $like_pattern),
        ARRAY_A
    );

    $payload = [];

    foreach ( $options as $opt ) {
        if ( strpos( $opt['option_name'], 'Account' ) !== false ) {
            $type = 'Account';
        }
    } 

    $sfMappings = maybe_unserialize($options[0]['option_value']);
    $mappingName = $sfMappings['sf_object'];
    foreach ( $sfMappings['mappings'] as $map ) {

        $sf = trim($map['sffield'] ?? '');
        $gf = trim($map['gfield'] ?? '');
        $field_type = gf_get_field_type( $form, $gf );
        if ($sf === '' || $gf === '') continue;
        if (empty($entry[$gf])) continue;
        if($field_type=="checkbox"){
        $payload[$sf] = 'true';
        }else{
        $payload[$sf] = $entry[$gf];
        }    
    } // end foreach mappings


  
    

        $email_value    = sanitize_email( (string) ($payload['PersonEmail'] ?? '') );
        $first_name = $payload['FirstName'] ?? '';
        $last_name  = $payload['LastName'] ?? '';
    if ($type == "Account") {


        $mappingName = 'Account';


        // Insert To Salesforce First

          if ( empty($entry['transaction_id']) ) {

            //    $payload['IS_Woo_Customer_Id__c'] = $user_id;

            $account_update[] = [
                'attributes' => [ 'type' => $mappingName ],
            ] + $payload;

            if ( $account_update ) {

                $payload = [ 'records' => $account_update ];

                if ( empty($entry['transaction_id']) ) {

                    $response = SF_APIConnector::postCURLObject(json_encode($payload), $action);


                } // end inner if transaction empty



            } // end if account_update exists

          } 

        // -------------------------------------------------
        // Get password + username from form fields
        // -------------------------------------------------
        foreach ( $form['fields'] as $field ) {

            if ( $field->get_input_type() === 'password' ) {
                $password_value = rgar( $entry, $field->id );

            } else if ( $field->get_input_type() === 'username' ) {
                $username_value = rgar( $entry, $field->id );
            }

        } // end foreach finding username/password


        $username_value = sanitize_user( (string) ($username_value ?? ''), true );

        if($username_value == ""){
            $username_value = $response[0]->id;
        }
        if ( empty($password_value) ) {
            $password_value = wp_generate_password(14, true, true);
        }



        // -------------------------------------------------
        // Create / Update WordPress User
        // -------------------------------------------------
        $user = get_user_by('login', $username_value);

        if ( $user ) {
            $user_id = $user->ID;
            $action = "PATCH";
            $payload['Id'] = get_user_meta($user_id, 'sf_object_id');

        } else {
            $action = "POST";
        } // end if WP user exists

        if (!$user_id) {

            if ( is_user_logged_in() ) {
    $user_id = get_current_user_id();
} else {
    $existing = get_user_by( 'email', $user_email );

    if ( $existing ) {
        $user_id = $existing->ID;
    } else {
        

            $user_id = wp_insert_user([
                'user_login' => $username_value,
                'user_pass'  => $password_value,
                'user_email' => $email_value,
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'role'       => 'subscriber',
            ]);


    }
}





                     update_user_meta($user_id, 'sf_object_id', $response[0]->id);
                     update_user_meta($user_id, 'sf_account_id', $response[0]->id);
                     do_action('user_register', $user_id);

                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);




        } // end if create new WP user


        // -------------------------------------------------
        // SALESFORCE CREATE/PATCH (Account)
        // -------------------------------------------------



    }else{
        // if normal submission

  

            $account_update = [];
            $account_update[] = ['attributes' => [ 'type' => $mappingName ],] + $payload;
            if ( $account_update ) {
                $payload  = [ 'records' => $account_update ];
                $response = SF_APIConnector::postCURLObject( json_encode( $payload ), 'POST' );

                 do_action('gfForm_Submission_Upload',$response + $entry);
            } 

           


    }
} // end function





function gf_form_has_payment( $form ) {
    foreach ( $form['fields'] as $field ) {
        if ( isset( $field->type ) && in_array( $field->type, [
            'product',
            'total',
            'payment',
            'stripe_creditcard',
            'paypal'
        ], true ) ) {
            return true;
        }
    }
    return false;
}



function gf_get_field_type( $form, $gfield ) {

    $main_id = (int) strtok( (string) $gfield, '.' );
    foreach ( $form['fields'] as $field ) {
        if ( (int) $field->id === $main_id ) {
            if ( method_exists( $field, 'get_input_type' ) ) {
                return $field->get_input_type();   
            }
            if ( isset( $field->type ) ) {
                return $field->type;
            }
            return null;
        }
    }
    return null;
}









add_action( 'gform_loaded', function() {
    if ( ! class_exists( 'GFForms' ) ) {
        return;
    }

    $sf = new GF_Reusable_Custom_Field();
    $sf->type  = 'sf_account_id';
    $sf->label = 'SF Account ID';
    $sf->field_type = 'hidden';
    GF_Fields::register( $sf );


    $fs = new GF_Reusable_Custom_Field();
    $fs->type  = 'form_submission';
    $fs->label = 'Form Submission Enabled';
    $fs->field_type = 'hidden';
    GF_Fields::register( $fs );


    $ce = new GF_Reusable_Custom_Field();
    $ce->type  = 'cecredit';
    $ce->label = 'Course Enrolled';
    $ce->field_type = 'cecredit';
    GF_Fields::register( $ce );


} );



add_action( 'gform_editor_js', function() {
wp_enqueue_script( 'jquery' );    
?>
<script>
(function($){

    /* -----------------------------------------
     * Ensure GF editor globals exist
     * ----------------------------------------- */
    if (typeof window.fieldSettings === 'undefined') {
        window.fieldSettings = {};
    }

    /* -----------------------------------------
     * Attach BOTH settings to the field
     * ----------------------------------------- */
    fieldSettings['wc_product_calculator'] =
        (fieldSettings['wc_product_calculator'] || '') +
        ', .event-ref-field-setting, .event-wc-product-setting';

    /* -----------------------------------------
     * Populate settings when field is selected
     * ----------------------------------------- */
    $(document).on('gform_load_field_settings', function(e, field, form){
        
        if (!field || field.type !== 'wc_product_calculator') return;

        /* ===============================
         * 1. Reference Field Dropdown
         * =============================== */
        var $ref = $('#event_ref_field');

        if ($ref.length && form && Array.isArray(form.fields)) {
     
            var selectedRef = field.event_ref_field || '';

            $ref.empty().append('<option value="">— Select Field —</option>');

            form.fields.forEach(function(f){
                if (String(f.id) === String(field.id)) return;
                if (f.type === 'html' || f.type === 'section') return;

                var sel = String(f.id) === String(selectedRef) ? ' selected' : '';

                $ref.append(
                    '<option value="'+f.id+'"'+sel+'>' +
                    (f.label || ('Field #' + f.id)) +
                    ' (ID: ' + f.id + ')' +
                    '</option>'
                );
            });

            $ref.off('change.ref').on('change.ref', function(){
                SetFieldProperty('event_ref_field', this.value);
            });
        }


        var $wc = $('#wc_product_calculator_setting');
        if (!$wc.length) return;

        var selectedProduct = field.wc_product_id || '';

        $wc.html('<option value="">— Loading products —</option>');

        $.ajax({
            url: ajaxurl,
            dataType: 'json',
            data: { action: 'gf_wc_get_products' },
            success: function(resp){

                if (!resp || !resp.success || !Array.isArray(resp.data)) {
                    $wc.html('<option value="">— No products found —</option>');
                    return;
                }

                $wc.empty().append('<option value="">— Select Product —</option>');
                console.log(resp)
                resp.data.forEach(function(p){
                    var sel = String(p.id) === String(selectedProduct) ? ' selected' : '';
                    $wc.append(
                        '<option value="'+p.id+'"'+sel+'>' +
                        p.title + ' (ID: ' + p.id + ')' +
                        '</option>'
                    );
                });
            }
        });

        $wc.off('change.wc').on('change.wc', function(){
            SetFieldProperty('wc_product_id', this.value);
        });

    });

})(jQuery);
</script>
<?php
});







add_action( 'gform_editor_js', function() {
wp_enqueue_script( 'jquery' );    
    ?>
    <script type="text/javascript">
    (function($){
        if ( typeof fieldSettings === 'undefined' ) {
            window.fieldSettings = {};
        }
        function appendFieldSetting(key, setting) {
            if ( typeof fieldSettings[key] !== 'undefined' && fieldSettings[key] ) {
                fieldSettings[key] = fieldSettings[key] + ', ' + setting;
            } else {
                fieldSettings[key] = setting;
            }
        }
        appendFieldSetting('sf_account_id', 'auto_hide_setting');
        appendFieldSetting('total_posts', 'auto_hide_setting');

        $(document).on('gform_load_field_settings', function(event, field, form){
            if (!field || !field.type) return;
            if ( field.type === 'sf_account_id' || field.type === 'total_posts' ) {
                var $checkbox = $('#field_auto_hide');
                if ( $checkbox.length ) {
                    $checkbox.prop('checked', !!field.auto_hide);
                }
            }
        });
    })(jQuery);
    </script>
    <?php
} );



add_action( 'gform_editor_js', function() {
wp_enqueue_script( 'jquery' );    
?>
<script>
(function($){

    fieldSettings['wc_product_calculator'] =
        (fieldSettings['wc_product_calculator'] || '') +
        ', .event-ref-field-setting';

    // Populate & sync value
    $(document).on('gform_load_field_settings', function(e, field, form){

        if (!field || field.type !== 'wc_product_calculator') return;

        var $select = $('#event_ref_field');
        if (!$select.length || !form || !form.fields) return;

        var selected = field.event_ref_field || '';
        $select.empty();

        $select.append('<option value="">— Select Field —</option>');

        form.fields.forEach(function(f){

            // Skip self
            if (f.id === field.id) return;

            // Skip unsupported field types if needed
            if (f.type === 'html' || f.type === 'section') return;

            var label = f.label || ('Field #' + f.id);
            var val   = f.id;

            var sel = (String(val) === String(selected)) ? ' selected' : '';
            $select.append(
                '<option value="' + val + '"' + sel + '>' +
                label + ' (ID: ' + val + ')' +
                '</option>'
            );
        });

        // Save when changed
        $select.off('change.ref').on('change.ref', function(){
            SetFieldProperty('event_ref_field', this.value);
        });

    });

})(jQuery);
</script>
<?php
});




add_filter( 'gform_validation', function( $validation_result ) {
    $form  = $validation_result['form'];
    $form_id = rgar( $form, 'id' );
    $has_wc = false;
    $has_cc = false;
    foreach ( $form['fields'] as $field ) {
        if ( isset( $field->type ) && $field->type === 'wc_product_custom' ) $has_wc = true;
        if ( isset( $field->type ) && $field->type === 'creditcard' ) $has_cc = true;
        if ( method_exists( $field, 'get_input_type' ) && $field->get_input_type() === 'creditcard' ) $has_cc = true;
        if ( $has_wc && $has_cc ) break;
    }

    // If the form doesn't have our custom products OR doesn't appear to accept card payments,
    // skip this validation entirely so other forms are unaffected.
    if ( ! $has_wc || ! $has_cc ) {
        return $validation_result;
    }

    $active_product_ids = [];
    foreach ( $form['fields'] as $f ) {

        if ( isset( $f->type ) && $f->type === 'wc_product_custom' ) {
            $fid = (int) $f->id;
            $posted_key = 'input_' . $fid;
            $posted_val = rgpost( $posted_key );
            if ( ! empty( $posted_val ) ) {
                $active_product_ids[] = intval( $posted_val );
            }
        }

        if ( isset( $f->type ) && $f->type === 'wc_product_calculator' ) {
            $fid = (int) $f->id;
            $posted_key = 'input_' . $fid;
            $posted_val = rgpost( $posted_key );
            if ( ! empty( $posted_val ) ) {
                $active_product_ids[] = intval( $posted_val );
            }
        }


    }

    $active_product_ids = array_values( array_unique( $active_product_ids ) );

    // 2) If no active products, check whether payment/credit card data was submitted.
    if ( empty( $active_product_ids ) ) {

        // Find a credit card field if present
        $cc_field_index = null;
        $cc_field_id    = null;
        foreach ( $form['fields'] as $idx => $field ) {
            if ( isset( $field->type ) && $field->type === 'creditcard' ) {
                $cc_field_index = $idx;
                $cc_field_id    = (int) $field->id;
                break;
            }
            if ( method_exists( $field, 'get_input_type' ) && $field->get_input_type() === 'creditcard' ) {
                $cc_field_index = $idx;
                $cc_field_id    = (int) $field->id;
                break;
            }
        }

        // Detect whether payment data was entered (best-effort)
        $payment_submitted = false;
        if ( $cc_field_id ) {
            $cc_posted = rgpost( (string) $cc_field_id );
            if ( ! empty( $cc_posted ) ) {
                $payment_submitted = true;
            } else {
                $maybe = rgpost( 'input_' . $cc_field_id );
                if ( ! empty( $maybe ) ) $payment_submitted = true;
            }
        } else {
            // fallback check for any likely payment POST fields (rare)
            foreach ( $_POST as $k => $v ) {
                if ( stripos( $k, 'card' ) !== false || stripos( $k, 'payment' ) !== false || stripos( $k, 'stripe' ) !== false || stripos( $k, 'credit' ) !== false ) {
                    if ( ! empty( $v ) ) { $payment_submitted = true; break; }
                }
            }
        }

        // If payment info exists but there are no active products, fail validation.
        if ( $payment_submitted ) {
            $validation_result['is_valid'] = false;
            $validation_result['form']['failed_validation'] = true;

            $message = 'Cannot process payment: no active product selected. Please choose a product before entering payment details.';

            if ( $cc_field_index !== null ) {
                $validation_result['form']['fields'][ $cc_field_index ]->failed_validation = true;
                $validation_result['form']['fields'][ $cc_field_index ]->validation_message = $message;
            } else {
                if ( isset( $validation_result['form']['fields'][0] ) ) {
                    $validation_result['form']['fields'][0 ]->failed_validation = true;
                    $validation_result['form']['fields'][0 ]->validation_message = $message;
                }
            }
        }
    }

    return $validation_result;
}, 10 );



add_action( 'gform_loaded', function () {

    if ( class_exists( 'GF_Fields' ) && class_exists( 'GF_Field_GF_Cart' ) ) {
        GF_Fields::register( new GF_Field_GF_Cart() );
    }

}, 5 );

add_action(
    'woocommerce_before_calculate_totals',
    function($cart){

        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {

            if (isset($cart_item['ispa_custom_price'])) {

                $cart_item['data']->set_price(
                    (float) $cart_item['ispa_custom_price']
                );
            }
        }

    },
    999
);

add_filter(
    'woocommerce_get_cart_item_from_session',
    function($cart_item, $values) {

        if (isset($values['ispa_custom_price'])) {

            $cart_item['ispa_custom_price'] = $values['ispa_custom_price'];
        }

        return $cart_item;

    },
    99,
    2
);


add_filter( 'gform_product_info', function( $product_info, $form, $entry ) {

    // Check if this form contains GF Cart field
    $has_gf_cart = false;
    foreach ( $form['fields'] as $field ) {
        if ( isset( $field->type ) && $field->type === 'gf_cart' ) {
            $has_gf_cart = true;
            break;
        }
    }

    if ( ! $has_gf_cart ) {
        return $product_info;
    }

    // WooCommerce must be available
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return $product_info;
    }

    $cart_items = WC()->cart->get_cart();
    if ( empty( $cart_items ) ) {
        return $product_info;
    }

    $products = [];

    foreach ( $cart_items as $cart_item ) {

        $product = $cart_item['data'] ?? null;
        if ( ! $product || ! $product instanceof WC_Product ) {
            continue;
        }

        // ✅ ONLY CHANGE: add 13% to price
        $price = round( (float) $product->get_price() * 1.13, 2 );
        $qty   = (int) ( $cart_item['quantity'] ?? 1 );

        if ( $price <= 0 || $qty <= 0 ) {
            continue;
        }

        $products[] = [
            'id'       => $product->get_id(),
            'name'     => $product->get_name(),
            'price'    => $price,
            'quantity' => $qty,
            'options'  => [],
        ];
    }

    if ( empty( $products ) ) {
        return $product_info;
    }

    // Override GF pricing with cart items
    $product_info['products'] = $products;

    // No extra options / shipping
    if ( isset( $product_info['options'] ) ) {
        $product_info['options'] = [];
    }
    if ( isset( $product_info['shipping'] ) ) {
        $product_info['shipping'] = [];
    }

    return $product_info;

}, 20, 3 );

add_filter('gform_product_info', function($product_info, $form, $entry) {

    // Only for form 17
    if ((int)$form['id'] !== 17) {
        return $product_info;
    }

    $revenue = get_gf_field_value_by_css_class(
        $form,
        'annual_revenue'
    );
    $membership_type = get_gf_field_value_by_css_class(
        $form,
        'member_type'
    );
    $revenue = floatval(
        str_replace(',', '', $revenue)
    );

    $price = ispa_calculate_membership_fee($revenue , $membership_type);

    $product_info['products'] = [
        'Membership Fee' => [
            'name'     => 'Membership Fee',
            'price'    => $price,
            'quantity' => 1
        ]
    ];

    $product_info['total'] = $price;

    return $product_info;

}, 99 , 3);

function get_gf_field_value_by_css_class($form, $css_class) {

    foreach ($form['fields'] as $field) {

        if (strpos($field->cssClass, $css_class) !== false) {

            return rgpost('input_' . $field->id);
        }
    }

    return '';
}

add_filter( 'gform_calculation_result', function( $result, $formula, $field, $form, $entry ) {

    // Only act if GF Cart field exists in this form
    $has_gf_cart = false;
    foreach ( $form['fields'] as $f ) {
        if ( isset( $f->type ) && $f->type === 'gf_cart' ) {
            $has_gf_cart = true;
            break;
        }
    }

    if ( ! $has_gf_cart ) {
        return $result;
    }

    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return $result;
    }

    $cart_total = (float) WC()->cart->get_total( 'edit' );

    // Gravity Forms expects a numeric string
    return number_format( $cart_total, 2, '.', '' );

}, 10, 5 );









add_filter( 'gform_validation', function( $validation_result ) {

    $form = $validation_result['form'];

    $has_gf_cart = false;
    foreach ( $form['fields'] as $f ) {
        if ( $f->type === 'gf_cart' ) {
            $has_gf_cart = true;
            break;
        }
    }

    if ( ! $has_gf_cart ) {
        return $validation_result;
    }

    if ( ! WC()->cart || WC()->cart->is_empty() ) {
        $validation_result['is_valid'] = false;
        $validation_result['form']['failed_validation'] = true;

        foreach ( $validation_result['form']['fields'] as &$field ) {
            if ( $field->type === 'creditcard' ) {
                $field->failed_validation = true;
                $field->validation_message = 'Your cart is empty. Please add items before paying.';
                break;
            }
        }
    }

    return $validation_result;

}, 10 );


function gf_cart_product_info( $product_info, $form, $entry ) {

    // Check if GF Cart field exists
    $has_gf_cart = false;
    foreach ( $form['fields'] as $field ) {
        if ( isset( $field->type ) && $field->type === 'gf_cart' ) {
            $has_gf_cart = true;
            break;
        }
    }

    if ( ! $has_gf_cart ) {
        return $product_info;
    }

    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return $product_info;
    }

    $cart_items = WC()->cart->get_cart();
    if ( empty( $cart_items ) ) {
        return $product_info;
    }

    $products = [];

    foreach ( $cart_items as $cart_item ) {

        $product = $cart_item['data'] ?? null;
        if ( ! $product || ! $product instanceof WC_Product ) {
            continue;
        }

        $price = (float) $product->get_price();
        $qty   = (int) ( $cart_item['quantity'] ?? 1 );

        if ( $price <= 0 || $qty <= 0 ) {
            continue;
        }

        $products[] = [
            'id'       => $product->get_id(),
            'name'     => $product->get_name(),
            'price'    => $price,
            'quantity' => $qty,
            'options'  => [],
        ];
    }

    if ( empty( $products ) ) {
        return $product_info;
    }

    $product_info['products'] = $products;
    $product_info['options']  = [];
    $product_info['shipping'] = [];

    return $product_info;
}


add_filter( 'gform_stripe_payment_intent_args', function( $args, $feed, $entry, $form ) {

    foreach ( $form['fields'] as $field ) {
        if ( isset( $field->type ) && $field->type === 'gf_cart' ) {

            if ( function_exists( 'WC' ) && WC()->cart ) {

                // Get raw numeric cart total (NO currency symbols)
                $total = WC()->cart->get_total( 'edit' );

                // Strip anything non-numeric just in case
                $total = (float) preg_replace( '/[^0-9.]/', '', $total );

                // Stripe REQUIRES integer cents
                $args['amount'] = (int) round( $total * 100 );

                // Hard-set currency to avoid mismatch
                $args['currency'] = strtolower( get_woocommerce_currency() );

                // Safety check
                if ( $args['amount'] <= 0 ) {

                }
            }

            break;
        }
    }

    return $args;

}, 999, 4 );


// Membership Addon 

function gf_handle_individual_membership_payment( $entry, $action ) {

    add_filter( 'gform_confirmation', function ( $confirmation ) use ( $entry ) {

    $entry_id = (int) rgar( $entry, 'id' );

    $confirmation .= '
<style>
#gf-ind-pipeline {
    margin: 30px auto;
    text-align: center;
}
#gf-ind-status {
    margin-top: 10px;
    font-size: 15px;
}
</style>

<div id="gf-ind-pipeline">
    <img id="gf-ind-spinner"
         src="' . esc_url( includes_url( 'images/spinner.gif' ) ) . '" />
    <div id="gf-ind-status">Initializing membership…</div>
</div>

<script>
(function(){

if (window.gfPipelineRunning) {
    console.log("Pipeline already running, skipping...");
    return;
}
    window.gfPipelineRunning = true;

    var entryId = ' . $entry_id . ';
    var ajaxUrl = "' . esc_url( admin_url( 'admin-ajax.php' ) ) . '";

    var STEP_MESSAGES = {
    init: "Initializing membership…",
    resolve_user: "Creating your account…",
    sync_salesforce: "Creating Membership",
    create_order: "Finalizing your order…",
    finalize: "Creating Membership Request"
};


    function whenReady(fn) {
        if (document.readyState === "complete" || document.readyState === "interactive") {
            fn();
        } else {
            document.addEventListener("DOMContentLoaded", fn);
        }
    }

    function whenjQuery(fn) {
        if (window.jQuery) {
            fn(window.jQuery);
        } else {
            setTimeout(function(){ whenjQuery(fn); }, 50);
        }
    }

    whenReady(function(){
        whenjQuery(function($){

            var status = document.getElementById("gf-ind-status");

            function setStatus(text){
                if (status) status.textContent = text;
            }

function runStep(step, context){

    if (STEP_MESSAGES[step]) {
        setStatus(STEP_MESSAGES[step]);
    } else {
        setStatus("Processing…");
    }

    fetch(ajaxUrl, {
        method: "POST",
        headers: {"Content-Type":"application/x-www-form-urlencoded"},
        body: new URLSearchParams({
            action: "gf_individualuser_pipeline_run_step",
            step: step,
            context: JSON.stringify(context || {})
        })
    })
    .then(function(res){ return res.json(); })
    .then(function(json){

        if (!json.success) {
            setStatus(json.error || "Something went wrong.");
            return;
        }

        if (json.data && json.data.next) {
            runStep(json.data.next, json.data.context || {});
        } else {

    var ctx = json.data.context || {};

    setStatus("Membership Successfully Created");

    var container = document.getElementById("gf-ind-pipeline");

    container.innerHTML = `
        <div class="thanks_message">
            Membership Successfully Created
        </div>

        <div class="sf108-order-html">
            ${ctx.order_html || "<p>Order details unavailable.</p>"}
        </div>

        <div style="margin-top: 60px;clear: both;float: left;">
            <a href="/dashboard" class="visit_dashboard">
                Visit Dashboard
            </a>
        </div>
    `;
}
    })
    .catch(function(){
        setStatus("Unexpected error occurred.");
    });
}


            runStep("init", { entry_id: entryId });

        });
    });

})();
</script>';

        return $confirmation;
    });
}




add_action( 'wp_ajax_gf_individualuser_pipeline_run_step', 'gf_individualuser_pipeline_run_step' );
add_action( 'wp_ajax_nopriv_gf_individualuser_pipeline_run_step', 'gf_individualuser_pipeline_run_step' );



function gf_individualuser_pipeline_run_step() {

    $step    = sanitize_text_field( $_POST['step'] ?? '' );
    $context = json_decode( stripslashes( $_POST['context'] ?? '{}' ), true );

    switch ( $step ) {

        case 'init':

            if ( empty( $context['entry_id'] ) ) {
                wp_send_json_error([ 'error' => 'Missing entry_id' ]);
            }

            wp_send_json_success([
                'step'    => 'init',
                'next'    => 'resolve_user',
                'context' => $context,
            ]);

        case 'resolve_user':

            $entry = GFAPI::get_entry( (int) $context['entry_id'] );
            if ( is_wp_error( $entry ) ) {
                wp_send_json_error([ 'error' => 'Invalid entry' ]);
            }

            $form = GFAPI::get_form( rgar( $entry, 'form_id' ) );
            $customer = gf_extract_basic_customer_data( $entry, $form );

            if ( empty( $customer['email'] ) ) {
                wp_send_json_error([ 'error' => 'Email missing' ]);
            }

            $user_id = gf_get_or_create_wp_user(
                $customer['email'],
                $customer['first_name'],
                $customer['last_name'],
                'subscriber'
            );

    if ( $user_id && ! is_wp_error( $user_id ) ) {

    // Billing name
    update_user_meta( $user_id, 'billing_first_name', $customer['first_name'] );
    update_user_meta( $user_id, 'billing_last_name',  $customer['last_name'] );

    // Billing phone
    if ( ! empty( $customer['phone'] ) ) {
        update_user_meta( $user_id, 'billing_phone', $customer['phone'] );
    }

    // Billing address
    if ( ! empty( $customer['address']['address_1'] ) ) {
        update_user_meta( $user_id, 'billing_address_1', $customer['address']['address_1'] );
        update_user_meta( $user_id, 'billing_address_2', $customer['address']['address_2'] );
        update_user_meta( $user_id, 'billing_city',      $customer['address']['city'] );
        update_user_meta( $user_id, 'billing_state',     $customer['address']['state'] );
        update_user_meta( $user_id, 'billing_postcode',  $customer['address']['postcode'] );
        update_user_meta( $user_id, 'billing_country',   $customer['address']['country'] );
    }
}

            if ( is_wp_error( $user_id ) ) {
                wp_send_json_error([ 'error' => $user_id->get_error_message() ]);
            }

            // SAFE membership handling
            $is_renewal = ! empty( get_user_meta( $user_id, 'membership_end_date', true ) );
            $dates = gf_resolve_membership_dates( $user_id, $is_renewal );

            update_user_meta( $user_id, 'membership_start_date', $dates['start'] );
            update_user_meta( $user_id, 'membership_end_date',   $dates['end'] );
            update_user_meta( $user_id, 'membership_type',       'Individual Membership' );
            update_user_meta( $user_id, 'membership_status',     'Active' );

            $context['user_id']  = (int) $user_id;
            $context['customer'] = $customer;

            wp_send_json_success([
                'step'    => 'resolve_user',
                'next'    => 'sync_salesforce',
                'context' => $context,
            ]);


        case 'sync_salesforce':

            $user_id = (int) ( $context['user_id'] ?? 0 );
            if ( ! $user_id ) {
                wp_send_json_error([ 'error' => 'Missing user_id' ]);
            }

            $obj = new SF_108Connector_AdminSettings();
            $obj->sf_export_user_sync( [ $user_id ], true );

            $sf_id = get_user_meta( $user_id, 'sf_object_id', true );
            if ( ! $sf_id ) {
                wp_send_json_error([ 'error' => 'Salesforce sync failed' ]);
            }

            $context['sf_account_id'] = $sf_id;

            wp_send_json_success([
                'step'    => 'sync_salesforce',
                'next'    => 'create_order',
                'context' => $context,
            ]);

        case 'create_order':
        $entry_id = (int) $context['entry_id'];
            if (gform_get_meta($entry_id, '_order_created')) {
               wp_send_json_success([
               'next' => null,
               'context' => $context
               ]);
        }

            $entry = GFAPI::get_entry( (int) $context['entry_id'] );
            if ( is_wp_error( $entry ) ) {
                wp_send_json_error([ 'error' => 'Invalid entry' ]);
            }

            $form = GFAPI::get_form( rgar( $entry, 'form_id' ) );
            $products = GFCommon::get_product_fields( $form, $entry );

            if ( empty( $products['products'] ) ) {
                wp_send_json_error([ 'error' => 'No product found' ]);
            }

            $product_data = reset( $products['products'] );
            $product = wc_get_product( (int) $product_data['id'] );

            if ( ! $product ) {
                wp_send_json_error([ 'error' => 'Invalid product' ]);
            }
  
            $order = wc_create_order([
                'customer_id' => (int) $context['user_id'],
            ]);

            $order->add_product( $product, 1 );
            $order->calculate_totals();
            $txn_id = rgar( $entry, 'transaction_id' );
            if ( $txn_id ) {
                $order->payment_complete( $txn_id );
                $order->update_status( 'completed' );
            }

            $user_id = (int) $context['user_id'];

$billing_fields = [
    'first_name' => get_user_meta( $user_id, 'billing_first_name', true ),
    'last_name'  => get_user_meta( $user_id, 'billing_last_name', true ),
    'company'    => get_user_meta( $user_id, 'billing_company', true ),
    'address_1'  => get_user_meta( $user_id, 'billing_address_1', true ),
    'address_2'  => get_user_meta( $user_id, 'billing_address_2', true ),
    'city'       => get_user_meta( $user_id, 'billing_city', true ),
    'state'      => get_user_meta( $user_id, 'billing_state', true ),
    'postcode'   => get_user_meta( $user_id, 'billing_postcode', true ),
    'country'    => get_user_meta( $user_id, 'billing_country', true ),
    'email'      => get_user_meta( $user_id, 'billing_email', true ),
    'phone'      => get_user_meta( $user_id, 'billing_phone', true ),
];

$order->set_address( $billing_fields, 'billing' );
            $start_date = current_time('Y-m-d');
            $end_date =  date('Y-m-d',strtotime( $start_date . ' +365 days' ));
            $order->update_meta_data( 'sf_account_id', $context['sf_account_id'] );
            $order->update_meta_data( 'sf_date_created', current_time( 'Y-m-d' ) );
             $membership_start = $current_year . '-07-01';
            $membership_end = ( $current_year + 1 ) . '-06-30';
            $product_name = $product->get_name();
            $order->update_meta_data('membership_start_date', $membership_start);
            $order->update_meta_data('membership_end_date', $membership_end );

           
            $order->save();

            $context['order_id'] = $order->get_id();
            gform_update_meta($entry_id, '_order_created', 1);
            $obj    = new SF_108Connector_AdminSettings();
            $result = $obj->sf_export_post_sync(array($order->get_id()), 'shop_order',true);


            $context['order_id'] = $order->get_id();

            wp_send_json_success([
                'step'    => 'create_order',
                'next'    => 'finalize',
                'context' => $context,
            ]);

        case 'finalize':

            // Create Form Submission As Well Fetch User Details and Order Details
            // $context['order_id']
            // $context['user_id']

            $order_id = (int) $context['order_id'];

            $order_html = fetch_sf_order_details( $order_id );

            $context['order_html'] = $order_html;

            $obj    = new SF_108Connector_AdminSettings();
            $result = $obj->sf_export_post_sync(array($order_id), 'shop_order',true);

            sf108_create_form_submission($context['user_id'],$context['order_id'],'012au000000aNnkAAE');

            wp_set_current_user( (int) $context['user_id'] );
            wp_set_auth_cookie( (int) $context['user_id'] );

            wp_send_json_success([
                'step'    => 'finalize',
                'next'    => null,
                'context' => $context,
            ]);

        default:
            wp_send_json_error([ 'error' => 'Invalid step' ]);
    }
}









function gf_get_or_create_wp_user( $email, $first_name = '', $last_name = '', $role = 'subscriber' ) {

    $email = sanitize_email( $email );
    if ( ! $email ) {
        return new WP_Error( 'invalid_email', 'Invalid email address' );
    }

    // 1️⃣ Check existing user
    $user = get_user_by( 'email', $email );
    if ( $user ) {
        return (int) $user->ID;
    }

    // 2️⃣ Validate role
    if ( ! get_role( $role ) ) {
        $role = 'subscriber';
    }

    // 3️⃣ Generate unique username
    $base_username = sanitize_user( current( explode( '@', $email ) ) );
    $username = $base_username;
    $i = 1;

    while ( username_exists( $username ) ) {
        $username = $base_username . '_' . $i;
        $i++;
    }

    // 4️⃣ Create user
    $user_id = wp_insert_user( [
        'user_login'   => $username,
        'user_email'   => $email,
        'user_pass'    => wp_generate_password( 12, true ),
        'first_name'   => sanitize_text_field( $first_name ),
        'last_name'    => sanitize_text_field( $last_name ),
        'display_name' => trim($first_name . ' ' . $last_name),
        'role'         => $role,
    ] );



if ( is_wp_error( $user_id ) ) {

    if ( $user_id->get_error_code() === 'existing_user_login' 
      || $user_id->get_error_code() === 'existing_user_email' ) {

        // Another request just created it → fetch it again
        $user = get_user_by( 'email', $email );

        if ( $user ) {
            return (int) $user->ID;
        }
    }

    return $user_id;
}

    return (int) $user_id;
}




function gf_handle_membership_renewal_payment( $entry, $action ) {



    $form = GFAPI::get_form( rgar( $entry, 'form_id' ) );

    $customer = gf_extract_basic_customer_data( $entry, $form );
    if ( empty( $customer['email'] ) ) {
        return;
    }

    $user_id = gf_get_or_create_customer( $customer,false );
    if ( ! $user_id ) {
        return;
    }

    $product_ids = gfwc_collect_product_ids( $form, $entry );
    if ( empty( $product_ids ) ) {
        return;
    }

    $txn_id = rgar( $action, 'transaction_id' );

    foreach ( $product_ids as $pid ) {

        $order_id = gf_create_wc_order_for_customer($user_id,$customer,$pid,1,$txn_id);
   
    }

}





function gf_get_custom_members_field_id( $form ) {

    foreach ( $form['fields'] as $field ) {

        // Replace 'corporate_members' with your actual custom field type
        if ( isset( $field->type ) && $field->type === 'corporate_members' ) {
            return (string) $field->id;
        }
    }

    return null;
}



function gf_get_company_name_from_entry( $form, $entry ) {

    foreach ( $form['fields'] as $field ) {

        if ( empty( $field->label ) ) {
            continue;
        }

        if ( strtolower( trim( $field->label ) ) === 'company name' || strtolower( trim( $field->label ) ) === 'organization name' ) {
            return rgar( $entry, (string) $field->id );
        }
    }

    return '';
}



function gf_get_sales_volume_from_entry( $form, $entry ) {

    foreach ( $form['fields'] as $field ) {

        if ( empty( $field->label ) ) {
            continue;
        }

        if ( strtolower( trim( $field->label ) ) === 'sales volume' ) {
            return rgar( $entry, (string) $field->id );
        }
    }

    return '';
}



function gf_get_or_create_user_from_submission( $email, $first_name, $last_name ) {

    // 1️⃣ If already logged in → use that user
    if ( is_user_logged_in() ) {
        return get_current_user_id();
    }

    // 2️⃣ Email already exists → use that user
    $existing_user = get_user_by( 'email', $email );
    if ( $existing_user ) {
        return $existing_user->ID;
    }

    // 3️⃣ Create new user
    $password = wp_generate_password( 12, true );



    if ( is_user_logged_in() ) {
    $user_id = get_current_user_id();
} else {
    $existing = get_user_by( 'email', $user_email );

    if ( $existing ) {
        $user_id = $existing->ID;
    } else {

        $user_id = wp_insert_user( [
        'user_login' => $email,
        'user_email' => $email,
        'user_pass'  => $password,
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'role'       => 'customer',
    ] );
            
    }
}






    if ( is_wp_error( $user_id ) ) {
        return 0;
    }

    // 4️⃣ Auto-login new user
    wp_set_current_user( $user_id );
    wp_set_auth_cookie( $user_id );

    return $user_id;
}



function gf_create_parent_affiliation($company,$user_id_sf){

  //  IS_Affiliation__c

}






function gf_handle_corporate_membership_payment( $entry, $action ) {

    add_filter( 'gform_confirmation', function( $confirmation ) use ( $entry ) {
        wp_enqueue_script('jquery');
        $entry_id = (int) rgar( $entry, 'id' );

        $confirmation .= '
<style>
#gf-corp-pipeline {
    margin: 30px auto;
    text-align: center;
    font-family: Arial, sans-serif;
}
#gf-corp-spinner {
    width: 30px;
    height: 30px;
    margin: 0 auto 15px;
}
#gf-corp-status {
    font-size: 15px;
    margin-top: 10px;
}
#gf-corp-error {
    color: #b00020;
    font-weight: bold;
    display: none;
}
#gf-corp-success {
    color: #1a7f37;
    font-weight: bold;
    display: none;
}
a.visit_dashboard{
display: block;
float:none !important;

    text-decoration: none;
    padding: 10px 30px;
    border: 2px solid #cbcbcb;
    border-radius: 10px;
    width: 300px;
    margin: 20px auto;
    box-shadow: 0 5px 7px -6px #000;
}    

.wc-item-meta{
display:none;
}
.order-again a{
float:none !important;
margin:10px !important;
}
#wc-thankyou-wrap{
text-align:left !important;
}


</style>

<div id="gf-corp-pipeline">
    <img id="gf-corp-spinner"
         src="' . esc_url( includes_url( 'images/spinner.gif' ) ) . '"
         alt="Processing…">
    <div id="gf-corp-status">Initializing corporate membership…</div>
    <div id="wc-thankyou-wrap"></div>
    <div id="gf-corp-error"></div>
    <div id="gf-corp-success"></div>
</div>

<script>
(function ($) {

    var entryId = ' . $entry_id . ';
    var ajaxUrl = "' . admin_url( 'admin-ajax.php' ) . '";

    var spinner = document.getElementById("gf-corp-spinner");
    var status  = document.getElementById("gf-corp-status");
    var errorEl = document.getElementById("gf-corp-error");
    var okEl    = document.getElementById("gf-corp-success");
    var thankyouWrap = document.getElementById("wc-thankyou-wrap");

    // 🔹 Static step messages (frontend-controlled)
    var STEP_MESSAGES = {
        init: "Initializing Your Order…",
        create_sf_company: "Creating Company Account…",
        create_main_account: "Processing Main Account…",
        create_members: "Creating Corporate Members…",
        create_affiliations: "Adding Members to Company…",
        sync_order: "Finalizing Order…"
    };

    function setStatus(step) {

        var STEP_MESSAGES = {
        init: "Initializing Your Order…",
        create_sf_company: "Creating Company Account…",
        create_main_account: "Processing Main Account…",
        create_members: "Creating Corporate Members…",
        create_affiliations: "Adding Members to Company…",
        sync_order: "Finalizing Order…"
    };
        console.log(STEP_MESSAGES[step]);
        $("#gf-corp-status").text(STEP_MESSAGES[step]);
        
        $("#gf-corp-error").remove();

    }

    function fail(message) {
        spinner.style.display = "none";
        status.style.display = "none";
        errorEl.textContent = message || "Something went wrong. Please contact support.";
        errorEl.style.display = "block";
    }

function success(context) {

    spinner.style.display = "none";
    status.style.display = "none";

    setStatus("Membership Successfully Created");

    // Hide Gravity Forms confirmation
    $(".gform_confirmation_message").hide();

    okEl.style.display = "block";

    // Safety check
    if (!context) {
        okEl.innerHTML = "<p>Something went wrong.</p>";
        return;
    }

    // Build output
    okEl.innerHTML = `
        <div class="thanks_message">
            Membership Successfully Created
        </div>

        <div class="sf108-order-html">
            ${context.order_html || "<p>Order details unavailable.</p>"}
        </div>

        <div style="margin-top:60px;text-align:center;float:left;">
            <a href="/dashboard" class="visit_dashboard">
                Visit Dashboard
            </a>
        </div>
     `;
}


    function runStep(step, context) {

        setStatus(step);
        console.log(step);
        fetch(ajaxUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "gf_pipeline_run_step",
                pipeline: "corporate_membership",
                step: step,
                context: JSON.stringify(context || {})
            })
        })
        .then(function (res) {
            return res.text().then(function (text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Invalid JSON response:", text);
                    throw new Error("Invalid server response");
                }
            });
        })
        .then(function (json) {

            if (!json.success) {
                fail(json.error || "Pipeline step failed.");
                return;
            }

            if (json.data && json.data.next) {
                runStep(json.data.next, json.data.context || {});
            } else {
                success(json.data.context || {});
            }
        })
        .catch(function (err) {
            fail(err.message || "AJAX error occurred.");
        });
    }


    var guardKey = "gf_pipeline_corp_init_ran_" + entryId;
    if (sessionStorage.getItem(guardKey) === "1") {
        return;
    }
    sessionStorage.setItem(guardKey, "1");

    // 🚀 Start pipeline
    console.log("Starting Pipeline");
    runStep("init", { entry_id: entryId });

})(jQuery);
</script>';

        return $confirmation;
    });
}















function gf_pipeline_get_or_create_main_account( array $customer ) {


    $email = sanitize_email( $customer['email'] ?? '' );
    if ( ! $email ) {
        return new WP_Error( 'missing_email', 'Customer email is required' );
    }

    $user = get_user_by( 'email', $email );
    if ( $user ) {

        return [
            'wp_user_id'   => (int) $user->ID,
            'sf_object_id' => get_user_meta( $user->ID, 'sf_object_id', true ),
            'created'      => false,
        ];
    }

    // -----------------------------------------
    // 3. Create Salesforce Person Account (ONE call)
    // -----------------------------------------

    $start_date = current_time( 'Y-m-d' );
    $end_date = date('Y-m-d',strtotime( $start_date . ' +365 days' ));



    $payload = [
        'FirstName'   => $customer['first_name'] ?? '',
        'LastName'    => $customer['last_name'] ?? '',
        'PersonEmail' => $email,
        'IS_Membership_Start_Date__c' => $start_date,
        'IS_Membership_End_Date__c'   => $end_date,
        'IS_Membership_Type__c' => 'Corporate Membership',
        'IS_Status__c' => 'Active'
    ];

    $records[] = [
        'attributes' => [ 'type' => 'Account' ],
    ] + $payload;

    $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]),'POST');
    // Main Account Created
    if ( empty( $response[0]->id ) ) {
        return new WP_Error(
            'sf_create_failed',
            'Failed to create Salesforce account'
        );
    }

    $sf_account_id = $response[0]->id;

    // -----------------------------------------
    // 4. Create WordPress user WITH sf_object_id
    // -----------------------------------------
    $username = sanitize_user( current( explode( '@', $email ) ) );
    if ( username_exists( $username ) ) {
        $username .= '_' . wp_generate_password( 4, false );
    }

    if ( is_user_logged_in() ) {
    $user_id = get_current_user_id();
} else {
    $existing = get_user_by( 'email', $email );

    if ( $existing ) {
        $user_id = $existing->ID;
    } else {

        $user_id = wp_insert_user([
        'user_login' => $username,
        'user_email' => $email,
        'user_pass'  => wp_generate_password( 12, true ),
        'first_name' => $customer['first_name'] ?? '',
        'last_name'  => $customer['last_name'] ?? '',
        'role'       => 'subscriber',
    ]);


    }
}




    if ( is_wp_error( $user_id ) ) {
        return $user_id;
    }

    update_user_meta( $user_id, 'sf_object_id', $sf_account_id );
    update_user_meta( $user_id, 'sf_account_id', $sf_account_id );

    return [
        'wp_user_id'   => (int) $user_id,
        'sf_object_id' => $sf_account_id,
        'created'      => true,
    ];
}





// Corporate Ajax Endpoint 

add_action( 'wp_ajax_gf_pipeline_run_step', 'gf_pipeline_run_step' );
add_action( 'wp_ajax_nopriv_gf_pipeline_run_step', 'gf_pipeline_run_step' );

function gf_pipeline_run_step() {


    $pipeline = sanitize_text_field( $_POST['pipeline'] ?? '' );
    $step     = sanitize_text_field( $_POST['step'] ?? '' );
    $context  = json_decode( stripslashes( $_POST['context'] ?? '{}' ), true );

    if ( $pipeline !== 'corporate_membership' ) {
        wp_send_json_error(['error' => 'Invalid pipeline']);
    }

    switch ( $step ) {

        /* -------------------------------------------------
         * STEP 1: INIT
         * ------------------------------------------------- */
        case 'init':

            // SF_TODO: Nothing to do here (validation-only step)

            wp_send_json_success([
                'pipeline' => $pipeline,
                'step'     => 'init',
                'next'     => 'create_sf_company',
                'context'  => $context,
                'message'  => 'Corporate pipeline initialized'
            ]);

        /* -------------------------------------------------
         * STEP 2: CREATE SALESFORCE COMPANY (STUB)
         * ------------------------------------------------- */



case 'create_sf_company':

    if ( empty( $context['entry_id'] ) ) {
        wp_send_json_error([
            'error' => 'Missing entry_id in context'
        ]);
    }

    $entry_id = (int) $context['entry_id'];
    $entry    = GFAPI::get_entry( $entry_id );

    if ( is_wp_error( $entry ) ) {
        wp_send_json_error([
            'error' => 'Unable to load Gravity Forms entry'
        ]);
    }

    // 🔑 LOAD FORM (REQUIRED)
    $form = GFAPI::get_form( rgar( $entry, 'form_id' ) );

    if ( empty( $form ) ) {
        wp_send_json_error([
            'error' => 'Unable to load Gravity Forms form'
        ]);
    }

    // ✅ CORRECT helper usage
    $company_name = gf_get_company_name_from_entry( $form, $entry );


    if ( empty( $company_name ) ) {
        wp_send_json_error([
            'error' => 'Company name not found'
        ]);
    }

    // ✅ EXISTING SF helper
    $sf_account_id = gf_create_company_account( $company_name );
    //$sf_account_id = '001au00000I25VzAAJ';


    if ( empty( $sf_account_id ) ) {
        wp_send_json_error([
            'error' => 'Failed to create Salesforce company'
        ]);
    }

    // Store in pipeline context
    $context['sf_company_account_id'] = $sf_account_id;

    wp_send_json_success([
        'step'    => 'create_sf_company',
        'next'    => 'create_main_account',
        'context' => $context,
        'message' => 'Company account created in Salesforce'
    ]);







case 'create_main_account':

    if ( empty( $context['entry_id'] ) ) {
        wp_send_json_error([ 'error' => 'Missing entry_id' ]);
    }

    $entry = GFAPI::get_entry( (int) $context['entry_id'] );
    if ( is_wp_error( $entry ) ) {
        wp_send_json_error([ 'error' => 'Invalid entry' ]);
    }


$customer = gf_extract_corporate_main_member_from_widget( $entry );

if ( is_wp_error( $customer ) ) {
    wp_send_json_error([
        'error' => $customer->get_error_message()
    ]);
}

$result = gf_pipeline_get_or_create_main_account( $customer );


        $records[] = [
            'attributes' => [ 'type' => 'IS_Affiliation__c' ],
            'IS_Parent_Account__c'    => $context['sf_company_account_id'],
            'IS_Account__c'     => $result['sf_object_id'],
            'RecordTypeId' => '012au000000WQfKAAW',
            'IS_Primary_Contact__c' => true

        ];

       $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]),'POST');

  

    if ( is_wp_error( $result ) ) {
        wp_send_json_error([
            'error' => $result->get_error_message()
        ]);
    }

    $context['main_wp_user_id']    = $result['wp_user_id'];
    $context['main_sf_account_id'] = $result['sf_object_id'];

    wp_send_json_success([
        'step'    => 'create_main_account',
        'next'    => 'create_members',
        'context' => $context,
        'message' => 'Main account resolved'
    ]);















case 'create_members':

    if ( empty( $context['entry_id'] ) ) {
        wp_send_json_error([ 'error' => 'Missing entry_id' ]);
    }

    $entry = GFAPI::get_entry( (int) $context['entry_id'] );
    if ( is_wp_error( $entry ) ) {
        wp_send_json_error([ 'error' => 'Invalid entry' ]);
    }

    // Extract ALL members from widget
    $members = gf_extract_all_corporate_members_from_widget( $entry );

    if ( is_wp_error( $members ) ) {
        wp_send_json_error([ 'error' => $members->get_error_message() ]);
    }

    // 0 → N members allowed
    if ( empty( $members ) || ! is_array( $members ) ) {
        $context['members'] = [];
        wp_send_json_success([
            'step'    => 'create_members',
            'next'    => 'create_affiliations',
            'context' => $context,
            'message' => 'No additional corporate members to create',
        ]);
    }

    $created_members = [];

    foreach ( $members as $member ) {


        if ( ! empty( $member['main'] ) ) {
            continue;
        }


        if ( empty( $member['email'] ) ) {
            continue;
        }
        // Here it registers Members 
        if($member['billing']== 'true' ){
        $result = gf_pipeline_get_or_create_wp_member_billing( $member,$entry );
         
        }else{
        $result = gf_pipeline_get_or_create_wp_member( $member );
        }
        // Skip only this member on failure
        if ( is_wp_error($result) ) continue;

        $user_id = $result['wp_user_id'];
        $sf_id   = $result['sf_object_id'];

        $created_members[] = [
            'wp_user_id' => (int) $user_id,
            'email'      => sanitize_email( $member['email'] ),
            'roles'      => [
                'owner'   => ! empty( $member['owner'] ),
                'billing' => ! empty( $member['billing'] ),
            ],
        ];
    }

    // Always succeed, even if empty
    $context['members'] = $created_members;

    wp_send_json_success([
        'step'    => 'create_members',
        'next'    => 'create_affiliations',
        'context' => $context,
        'message' => sprintf(
            '%d corporate member(s) processed',
            count( $created_members )
        ),
    ]);




        
        




case 'create_affiliations':

    if (
        empty( $context['sf_company_account_id'] ) ||
        empty( $context['main_sf_account_id'] )
    ) {
        wp_send_json_error([
            'error' => 'Missing company or main account Salesforce ID'
        ]);
    }

    $company_sf_id = $context['sf_company_account_id'];
    $main_sf_id    = $context['main_sf_account_id'];
    $members       = $context['members'] ?? [];
    
    $records = [];

    // -----------------------------------------
    // 1️⃣ Affiliation: MAIN account
    // -----------------------------------------

/*
    $records[] = [
        'attributes' => [ 'type' => 'IS_Affiliation__c' ],
        'IS_Parent_Account__c' => $company_sf_id,
        'IS_Account__c'     => $member['sf_object_id'],
    ];
*/
    // -----------------------------------------
    // 2️⃣ Affiliations: OTHER members
    // -----------------------------------------

    $records = [];

    foreach ( $members as $member ) {


        $member_id = get_user_meta( get_user_by( 'email', $member['email'] )->ID, 'sf_object_id', true );

         if ( empty($member_id ) ) {
            continue;
        }

        if($member['billing']=='true'){
        $record = [
            'attributes' => [ 'type' => 'IS_Affiliation__c' ],
            'IS_Parent_Account__c'    => $company_sf_id,
            'IS_Account__c'     => $member_id,
            'RecordTypeId' => '012au000000WQfKAAW',
            'IS_Billing_Contact__c' => true

        ];

        }elseif($member['owner']=='true'){
            $record = [
            'attributes' => [ 'type' => 'IS_Affiliation__c' ],
            'IS_Parent_Account__c'    => $company_sf_id,
            'IS_Account__c'     => $member_id,
            'RecordTypeId' => '012au000000WQfKAAW',
            'IS_Owner__c' => true

        ];

        }else{
              $record = [
            'attributes' => [ 'type' => 'IS_Affiliation__c' ],
            'IS_Parent_Account__c'    => $company_sf_id,
            'IS_Account__c'     => $member_id,
            'RecordTypeId' => '012au000000WQfKAAW',
        ];

        }

            if ( ! empty( $member['owner'] ) && $member['owner'] === true ) {
             $record['IS_Owner__c'] = true;
            }

            if ( ! empty( $member['billing'] ) && $member['billing'] === true ) {
            $record['IS_Billing_Contact__c'] = 'true'; 
            }


       $records[] = $record;     

    }


    if ( empty( $records ) ) {
        wp_send_json_success([
            'step'    => 'create_affiliations',
            'next'    => 'sync_order',
            'context' => $context,
            'message' => 'No affiliations to create',
        ]);
    }

    $response = SF_APIConnector::postCURLObject(
        json_encode([
            'allOrNone' => false,
            'records'   => $records,
        ]),
        'POST'
    );

    wp_send_json_success([
        'step'    => 'create_affiliations',
        'next'    => 'sync_order',
        'context' => $context,
        'message' => count( $records ) . ' affiliation(s) submitted',
    ]);




case 'sync_order':



    if (
        empty( $context['entry_id'] ) ||
        empty( $context['main_wp_user_id'] )
    ) {

        wp_send_json_error([
            'error' => 'Missing required context for order sync'
        ]);
    }

    $entry = GFAPI::get_entry( (int) $context['entry_id'] );
    if ( is_wp_error( $entry ) ) {

        wp_send_json_error([
            'error' => 'Unable to load Gravity Forms entry'
        ]);
    }

    $form = GFAPI::get_form( rgar( $entry, 'form_id' ) );
    if ( empty( $form ) ) {

        wp_send_json_error([
            'error' => 'Unable to load Gravity Forms form'
        ]);
    }

    $main_wp_user_id = (int) $context['main_wp_user_id'];

    $sf_account_id = get_user_meta( $main_wp_user_id, 'sf_object_id', true );
    if ( empty( $sf_account_id ) ) {

        wp_send_json_error([
            'error' => 'Salesforce Account ID missing on main user'
        ]);
    }

    $existing_order_id = get_option( 'gf_corp_order_for_entry_' . $context['entry_id'] );
    if ( $existing_order_id ) {


        wp_send_json_success([
            'step'    => 'sync_order',
            'context' => array_merge( $context, [
                'order_id' => (int) $existing_order_id,
            ]),
            'message' => 'Order already created for this entry',
        ]);
    }


    $product_info = GFCommon::get_product_fields( $form, $entry );

    if (
        empty( $product_info['products'] ) ||
        ! is_array( $product_info['products'] )
    ) {

        wp_send_json_error([
            'error' => 'No products resolved from Gravity Forms product data'
        ]);
    }

    // Use FIRST product only (corporate rule)
    $product_data = reset( $product_info['products'] );

    $product_id = (int) rgar( $product_data, 'id' );
    $quantity   = 1; // forced to 1 as requested

    if ( ! $product_id ) {
        wp_send_json_error([
            'error' => 'Invalid product resolved from Gravity Forms'
        ]);
    }
  dbg("Order 86");

    $order = wc_create_order([
        'customer_id' => $main_wp_user_id,
    ]);



    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        wp_send_json_error([
            'error' => 'Invalid WooCommerce product'
        ]);
    }

  
    $order->add_product( $product, 1 );

    $txn_id = rgar( $entry, 'transaction_id' );

    if ( $txn_id ) {


        $order->set_payment_method( 'Credit Card' );
        $order->payment_complete( $txn_id );
        $order->update_status( 'completed', 'Corporate membership payment completed' );
    } else {

        $order->update_status( 'completed', 'Corporate membership (no txn id)' );
    }

    $order->calculate_taxes();
    $order->calculate_totals();

    $order->update_meta_data( 'sf_account_id', $sf_account_id );
    $order->update_meta_data('sf_date_created',current_time( 'Y-m-d' ));
    $order->save();




    update_option(
        'gf_corp_order_for_entry_' . $context['entry_id'],
        $order->get_id(),
        false
    );


    wp_send_json_success([
        'step'    => 'sync_order',
        'next'    => 'finalizing_order',
        'context' => array_merge( $context, [
        'order_id' => $order->get_id(),
        ]),
        'message' => 'Order created and synced using GFCommon product resolution',
    ]);




    case 'finalizing_order':
        $order_id = $context['order_id'];
        // Synching Order to Salesforce
        
        $order_html = fetch_sf_order_details( $order_id );
        $context['order_html'] = $order_html;
        
        $order = wc_get_order( $order_id );

        if ( $order ) {
        $order_total = $order->get_total(); // numeric value
        }

        $obj    = new SF_108Connector_AdminSettings();
        $result = $obj->sf_export_post_sync(array($order_id), 'shop_order',true);


        $query = "SELECT Id FROM IS_Transaction__c WHERE IS_Order__c = '$result->id'";
        $response = SF_APIConnector::getSQueryObject( $query, 1 );
        //print_r($response->id); Transaction ID

        
    $payload = [
        'id' => $response->id,
        'IS_Order__c' => $result->id,
        'IS_Amount__c' => $order_total
    ];

    $records[] = [
        'attributes' => [ 'type' => 'IS_Transaction__c' ],
    ] + $payload;

    $response = SF_APIConnector::postCURLObject( json_encode(['allOrNone' => false,'records'   => $records,]),'POST');


    wp_set_current_user( $context['main_wp_user_id'] );
    wp_set_auth_cookie( $context['main_wp_user_id'] );
    

wp_send_json_success([
    'step'    => 'finalizing_order',
    'next'    => null,
    'context' => array_merge( $context, ['order_id' => (int) $order_id,]),
    'message' => 'Order finalized and synced',
]);











        default:
            wp_send_json_error([
                'error' => 'Invalid pipeline step'
            ]);
    }
}




function gf_extract_all_corporate_members_from_widget( array $entry ) {

    $form = GFAPI::get_form( (int) $entry['form_id'] );
    if ( is_wp_error( $form ) || empty( $form['fields'] ) ) {
        return new WP_Error( 'invalid_form', 'Unable to load form' );
    }

    foreach ( $form['fields'] as $field ) {

        if ( empty( $field->type ) || $field->type !== 'corporate_members' ) {
            continue;
        }

        $raw = rgar( $entry, (string) $field->id );
        if ( empty( $raw ) ) {
            return [];
        }

        $members = json_decode( $raw, true );
        if ( ! is_array( $members ) ) {
            return new WP_Error( 'invalid_members', 'Invalid members JSON' );
        }

        return $members;
    }

    return [];
}




function gf_pipeline_get_or_create_wp_member( array $member ) {

    $email = sanitize_email( $member['email'] ?? '' );
    if ( ! $email ) {
        return new WP_Error( 'missing_email', 'Member email is required' );
    }

    /* -------------------------------------------------
     * 1. Check WordPress first
     * ------------------------------------------------- */
    $user = get_user_by( 'email', $email );
    if ( $user ) {
        return [
            'wp_user_id'   => (int) $user->ID,
            'sf_object_id' => get_user_meta( $user->ID, 'sf_object_id', true ),
            'created'      => false,
        ];
    }



    $start_date = current_time( 'Y-m-d' );
    $end_date = date('Y-m-d',strtotime( $start_date . ' +365 days' ));


    $payload = [
        'FirstName'   => $member['first_name'] ?? '',
        'LastName'    => $member['last_name'],
        'PersonEmail' => $email,
        'IS_Membership_Start_Date__c' => $start_date,
        'IS_Membership_End_Date__c'   => $end_date,
        'IS_Membership_Type__c' => 'Corporate Membership',
        'IS_Status__c' => 'Active'
    ];





    $records[] = [
        'attributes' => [ 'type' => 'Account' ],
    ] + $payload;

    $response = SF_APIConnector::postCURLObject(
        json_encode([
            'allOrNone' => false,
            'records'   => $records,
        ]),
        'POST'
    );

    // Normalize SF response
    $sf_account_id = '';
    if ( is_array( $response ) && isset( $response[0]->id ) ) {
        $sf_account_id = $response[0]->id;
    } elseif ( is_object( $response ) && isset( $response->id ) ) {
        $sf_account_id = $response->id;
    }

    if ( ! $sf_account_id ) {
        return new WP_Error(
            'sf_create_failed',
            'Failed to create Salesforce member account'
        );
    }

    /* -------------------------------------------------
     * 3. Create WordPress user
     * ------------------------------------------------- */
    $username = sanitize_user( current( explode( '@', $email ) ) );
    if ( username_exists( $username ) ) {
        $username .= '_' . wp_generate_password( 4, false );
    }

    if ( is_user_logged_in() ) {
    $user_id = get_current_user_id();
    } else {
    $existing = get_user_by( 'email', $email );

    if ( $existing ) {
        $user_id = $existing->ID;
    } else {
    $user_id = wp_insert_user([
        'user_login' => $username,
        'user_email' => $email,
        'user_pass'  => wp_generate_password( 12, true ),
        'first_name' => $member['first_name'] ?? '',
        'last_name'  => $member['last_name'] ?? '',
        'role'       => 'subscriber',
    ]);

    }
}





    if ( is_wp_error( $user_id ) ) {
        return $user_id;
    }

    update_user_meta( $user_id, 'sf_object_id', $sf_account_id );
    update_user_meta( $user_id, 'sf_account_id', $sf_account_id );

    return [
        'wp_user_id'   => (int) $user_id,
        'sf_object_id' => $sf_account_id,
        'created'      => true,
    ];
}



function gf_pipeline_get_or_create_wp_member_billing( array $member, $entry ) {

    $email = sanitize_email( $member['email'] ?? '' );
    if ( ! $email ) {
        return new WP_Error( 'missing_email', 'Member email is required' );
    }

    /* -------------------------------------------------
     * 1. Check WordPress first
     * ------------------------------------------------- */
    $user = get_user_by( 'email', $email );
    if ( $user ) {
        return [
            'wp_user_id'   => (int) $user->ID,
            'sf_object_id' => get_user_meta( $user->ID, 'sf_object_id', true ),
            'created'      => false,
        ];
    }

    /* -------------------------------------------------
     * 2. Create Salesforce Person Account
     * ------------------------------------------------- */
    $start_date = current_time( 'Y-m-d' );
    $end_date   = date( 'Y-m-d', strtotime( $start_date . ' +365 days' ) );


$sf_address = gf_pipeline_extract_address_for_sf( $entry );


$payload = [
    'FirstName'   => $member['first_name'] ?? '',
    'LastName'    => $member['last_name'] ?? '',
    'PersonEmail' => $email,
    'IS_Membership_Start_Date__c' => $start_date,
    'IS_Membership_End_Date__c'   => $end_date,
    'IS_Membership_Type__c'       => 'Corporate Membership',
    'IS_Status__c'                => 'Active',
];


foreach ( $sf_address as $key => $val ) {
    if ( $val !== '' ) {
        $payload[ $key ] = $val;
    }
}


$records = [
    [
        'attributes' => [ 'type' => 'Account' ],
    ] + $payload
];


$response = SF_APIConnector::postCURLObject(
    json_encode([
        'allOrNone' => false,
        'records'   => $records,
    ]),
    'POST'
);



    $sf_account_id = is_array( $response ) && isset( $response[0]->id )
        ? $response[0]->id
        : '';

    if ( ! $sf_account_id ) {
        return new WP_Error( 'sf_create_failed', 'Failed to create Salesforce member account' );
    }

    /* -------------------------------------------------
     * 3. Create WordPress user
     * ------------------------------------------------- */
    $username = sanitize_user( current( explode( '@', $email ) ) );
    if ( username_exists( $username ) ) {
        $username .= '_' . wp_generate_password( 4, false );
    }


        if ( is_user_logged_in() ) {
    $user_id = get_current_user_id();
} else {
    $existing = get_user_by( 'email', $email );

    if ( $existing ) {
        $user_id = $existing->ID;
    } else {
    $user_id = wp_insert_user([
        'user_login' => $username,
        'user_email' => $email,
        'user_pass'  => wp_generate_password( 12, true ),
        'first_name' => $member['first_name'] ?? '',
        'last_name'  => $member['last_name'] ?? '',
        'role'       => 'subscriber',
    ]);
    }
}






    if ( is_wp_error( $user_id ) ) {
        return $user_id;
    }

    /* -------------------------------------------------
     * 4. Store Salesforce + billing address
     * ------------------------------------------------- */
    update_user_meta( $user_id, 'sf_object_id', $sf_account_id );
    update_user_meta( $user_id, 'sf_account_id', $sf_account_id );

    $billing = gf_pipeline_extract_billing_address_from_entry( $entry );
    foreach ( $billing as $key => $val ) {
        if ( $val !== '' ) {
            update_user_meta( $user_id, $key, $val );
        }
    }

    return [
        'wp_user_id'   => (int) $user_id,
        'sf_object_id' => $sf_account_id,
        'created'      => true,
    ];
}










function gf_pipeline_extract_address_for_sf( $entry ) {

    $form = GFAPI::get_form( rgar( $entry, 'form_id' ) );
    if ( ! $form || empty( $form['fields'] ) ) {
        return [];
    }

    foreach ( $form['fields'] as $field ) {
        if ( $field->get_input_type() === 'address' ) {
            $fid = (string) $field->id;

            $street1 = rgar( $entry, $fid . '.1' );
            $street2 = rgar( $entry, $fid . '.2' );

            return [
                'BillingStreet'     => trim( $street1 . "\n" . $street2 ),
                'BillingCity'       => rgar( $entry, $fid . '.3' ),
                'BillingState'      => rgar( $entry, $fid . '.4' ),
                'BillingPostalCode' => rgar( $entry, $fid . '.5' ),
                'BillingCountry'    => rgar( $entry, $fid . '.6' ),
            ];
        }
    }

    return [];
}






function gf_pipeline_extract_billing_address_from_entry( $entry ) {

    $form = GFAPI::get_form( rgar( $entry, 'form_id' ) );
    if ( ! $form || empty( $form['fields'] ) ) {
        return [];
    }

    foreach ( $form['fields'] as $field ) {
        if ( $field->get_input_type() === 'address' ) {
            $fid = (string) $field->id;

            return [
                'billing_address_1' => rgar( $entry, $fid . '.1' ),
                'billing_address_2' => rgar( $entry, $fid . '.2' ),
                'billing_city'      => rgar( $entry, $fid . '.3' ),
                'billing_state'     => rgar( $entry, $fid . '.4' ),
                'billing_postcode'  => rgar( $entry, $fid . '.5' ),
                'billing_country'   => rgar( $entry, $fid . '.6' ),
            ];
        }
    }

    return [];
}



function gf_pipeline_get_or_create_member_account( array $member ) {

    $email = sanitize_email( $member['email'] ?? '' );
    if ( ! $email ) {
        return new WP_Error( 'missing_email', 'Member email is required' );
    }

    // -----------------------------------------
    // 1. WordPress-first check
    // -----------------------------------------
    $user = get_user_by( 'email', $email );
    if ( $user ) {

        $sf_id = get_user_meta( $user->ID, 'sf_object_id', true );

        if ( $sf_id ) {
            return [
                'wp_user_id'   => (int) $user->ID,
                'sf_object_id' => $sf_id,
                'created'      => false,
            ];
        }
        // If WP user exists but SF ID missing, fall through and create SF
    }

    // -----------------------------------------
    // 2. Create Salesforce Person Account
    // -----------------------------------------
    $last_name = trim( $member['last_name'] ?? '' );
    if ( $last_name === '' ) {
        $last_name = 'Member';
    }

    $start_date = current_time( 'Y-m-d' );
    $end_date = date('Y-m-d',strtotime( $start_date . ' +365 days' ));


    $payload = [
        'FirstName'   => $member['first_name'] ?? '',
        'LastName'    => $last_name,
        'PersonEmail' => $email,
        'IS_Membership_Start_Date__c' => $start_date,
        'IS_Membership_End_Date__c'   => $end_date,
        'IS_Membership_Type__c' => 'Corporate Membership',
        'IS_Status__c' => 'Active'
    ];

    $records = [[
        'attributes' => [ 'type' => 'Account' ],
    ] + $payload];

    $response = SF_APIConnector::postCURLObject(
        json_encode([
            'allOrNone' => false,
            'records'   => $records,
        ]),
        'POST'
    );

    if ( empty( $response[0]->id ) ) {
        $msg = $response[0]->errors[0]->message ?? 'Failed to create Salesforce member account';
        return new WP_Error( 'sf_create_failed', $msg );
    }

    $sf_account_id = $response[0]->id;

    // -----------------------------------------
    // 3. Create or update WordPress user
    // -----------------------------------------
    if ( ! $user ) {

        $username = sanitize_user( current( explode( '@', $email ) ) );
        if ( username_exists( $username ) ) {
            $username .= '_' . wp_generate_password( 4, false );
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => wp_generate_password( 12, true ),
            'first_name' => $member['first_name'] ?? '',
            'last_name'  => $last_name,
            'role'       => 'subscriber',
        ]);

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

    } else {
        $user_id = (int) $user->ID;
    }

    update_user_meta( $user_id, 'sf_object_id', $sf_account_id );
    update_user_meta( $user_id, 'sf_account_id', $sf_account_id );

    return [
        'wp_user_id'   => (int) $user_id,
        'sf_object_id' => $sf_account_id,
        'created'      => true,
    ];
}




function gf_extract_corporate_main_member_from_widget( $entry ) {

    if ( empty( $entry['form_id'] ) ) {
        return new WP_Error(
            'missing_form_id',
            'Entry does not contain form_id'
        );
    }

    $form = GFAPI::get_form( (int) $entry['form_id'] );

    if ( is_wp_error( $form ) || empty( $form['fields'] ) ) {
        return new WP_Error(
            'invalid_form',
            'Unable to load Gravity Forms form'
        );
    }

    foreach ( $form['fields'] as $field ) {

        if ( empty( $field->type ) || $field->type !== 'corporate_members' ) {
            continue;
        }

        $raw = rgar( $entry, (string) $field->id );

        if ( empty( $raw ) ) {
            return new WP_Error(
                'members_missing',
                'Corporate members data is missing'
            );
        }

        $members = json_decode( $raw, true );

        if ( ! is_array( $members ) ) {
            return new WP_Error(
                'members_invalid',
                'Corporate members JSON is invalid'
            );
        }

        foreach ( $members as $member ) {

            if ( ! empty( $member['main'] ) ) {

                if ( empty( $member['email'] ) ) {
                    return new WP_Error(
                        'missing_email',
                        'Main member email is missing'
                    );
                }

                return [
                    'first_name' => sanitize_text_field( $member['first_name'] ?? '' ),
                    'last_name'  => sanitize_text_field( $member['last_name'] ?? '' ),
                    'email'      => sanitize_email( $member['email'] ),
                    'phone'      => sanitize_text_field( $member['phone'] ?? '' ),
                ];
            }
        }

        return new WP_Error(
            'main_not_found',
            'No main member marked true'
        );
    }

    return new WP_Error(
        'widget_not_found',
        'Corporate members field not found in form'
    );
}







function gf_get_or_create_wp_user_only( $member, $company = false, $address = [] ) {

    $email = sanitize_email( $member['email'] ?? '' );
    if ( ! $email ) {
        return false;
    }

    /* ------------------------------------
     * Get or create user
     * ------------------------------------ */
    $user = get_user_by( 'email', $email );

    if ( $user ) {
        $user_id = $user->ID;
    } else {

        $user_id = wp_insert_user( [
            'user_login' => $email,
            'user_email' => $email,
            'first_name' => $member['first_name'] ?? '',
            'last_name'  => $member['last_name'] ?? '',
            'user_pass'  => wp_generate_password(),
            'role'       => 'subscriber',
        ] );

        if ( is_wp_error( $user_id ) ) {
            return false;
        }
    }

    /* ------------------------------------
     * Save billing address (if provided)
     * ------------------------------------ */
    if ( ! empty( $address ) && is_array( $address ) ) {

        update_user_meta( $user_id, 'billing_first_name', $member['first_name'] ?? '' );
        update_user_meta( $user_id, 'billing_last_name',  $member['last_name'] ?? '' );
        update_user_meta( $user_id, 'billing_email',      $email );

        update_user_meta( $user_id, 'billing_address_1',  $address['address_1'] ?? '' );
        update_user_meta( $user_id, 'billing_address_2',  $address['address_2'] ?? '' );
        update_user_meta( $user_id, 'billing_city',       $address['city'] ?? '' );
        update_user_meta( $user_id, 'billing_state',      $address['state'] ?? '' );
        update_user_meta( $user_id, 'billing_postcode',   $address['postcode'] ?? '' );
        update_user_meta( $user_id, 'billing_country',    $address['country'] ?? '' );
    }

    return (int) $user_id;
}



function gf_create_wc_order_only( $user_id, $product_id, $txn_id ) {
  dbg("Order 31");
    $order = wc_create_order( [ 'customer_id' => $user_id ] );
    if ( is_wp_error( $order ) ) {
        return false;
    }

    $order->add_product( wc_get_product( $product_id ), 1 );
    $order->set_payment_method( 'Credit Card' );
    $order->update_meta_data('sf_date_created',current_time( 'Y-m-d' ));
    $order->payment_complete( $txn_id );
    $order->calculate_taxes();
    $order->calculate_totals();
    
    $order->save();

    return $order->get_id();
}








if ( ! wp_next_scheduled( 'sf_corporate_sync_cron' ) ) {
    wp_schedule_event( time() + 60, 'minute', 'sf_corporate_sync_cron' );
}



function gf_extract_basic_customer_data( $entry, $form ) {

    $data = [
        'email'      => '',
        'first_name' => '',
        'last_name'  => '',
        'phone'      => '',
        'address'    => [
            'address_1' => '',
            'address_2' => '',
            'city'      => '',
            'state'     => '',
            'postcode'  => '',
            'country'   => '',
        ],
    ];

    foreach ( $form['fields'] as $field ) {

        $type = method_exists( $field, 'get_input_type' )
            ? $field->get_input_type()
            : $field->type;

        /* --------------------
         * EMAIL
         * -------------------- */
        if ( $type === 'email' && empty( $data['email'] ) ) {
            $data['email'] = sanitize_email( rgar( $entry, (string) $field->id ) );
        }

        /* --------------------
         * PHONE
         * -------------------- */
        if ( $type === 'phone' && empty( $data['phone'] ) ) {
            $data['phone'] = sanitize_text_field( rgar( $entry, (string) $field->id ) );
        }

        /* --------------------
         * NAME
         * -------------------- */
        if ( $type === 'name' && empty( $data['first_name'] ) ) {
            $data['first_name'] = sanitize_text_field( rgar( $entry, $field->id . '.3' ) );
            $data['last_name']  = sanitize_text_field( rgar( $entry, $field->id . '.6' ) );
        }

        /* --------------------
         * ADDRESS
         * -------------------- */
        if ( $type === 'address' && empty( $data['address']['address_1'] ) ) {
            $data['address'] = [
                'address_1' => sanitize_text_field( rgar( $entry, $field->id . '.1' ) ),
                'address_2' => sanitize_text_field( rgar( $entry, $field->id . '.2' ) ),
                'city'      => sanitize_text_field( rgar( $entry, $field->id . '.3' ) ),
                'state'     => sanitize_text_field( rgar( $entry, $field->id . '.4' ) ),
                'postcode'  => sanitize_text_field( rgar( $entry, $field->id . '.5' ) ),
                'country'   => sanitize_text_field( rgar( $entry, $field->id . '.6' ) ),
            ];
        }
    }

    return $data;
}







function gf_resolve_membership_dates( int $user_id, bool $is_renewal = false ) {

    $today = current_time( 'Y-m-d' );

    $existing_end = get_user_meta( $user_id, 'membership_end_date', true );

    // No existing membership → NEW
    if ( empty( $existing_end ) ) {
        return [
            'start' => $today,
            'end'   => date( 'Y-m-d', strtotime( $today . ' +365 days' ) ),
        ];
    }

    // Renewal → extend from current end
    if ( $is_renewal ) {
        return [
            'start' => $today,
            'end'   => date( 'Y-m-d', strtotime( $existing_end . ' +365 days' ) ),
        ];
    }

    // Existing active membership → DO NOT TOUCH
    return [
        'start' => get_user_meta( $user_id, 'membership_start_date', true ),
        'end'   => $existing_end,
    ];
}




function gf_get_or_create_customer( $customer,$company ) {

    $email = $customer['email'];
    if ( ! $email ) {
        return 0;
    }

    $user_id = email_exists( $email );

 
    if ( ! $user_id ) {
        $username = sanitize_user( current( explode( '@', $email ) ) );
        if ( username_exists( $username ) ) {
            $username .= '_' . wp_generate_password( 4, false );
        }
      $start_date = current_time( 'Y-m-d' );
      $end_date = date('Y-m-d',strtotime( $start_date . ' +365 days' ));


      $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => wp_generate_password( 12, true ),
            'first_name' => $customer['first_name'],
            'last_name'  => $customer['last_name'],
            'role'       => 'subscriber',
        ]);
    }else{

     // Here someone is Becoming a Member if he is not a member earlier
     

$is_renewal = ! empty( get_user_meta( $user_id, 'membership_end_date', true ) );

$dates = gf_resolve_membership_dates( $user_id, $is_renewal );

update_user_meta( $user_id, 'membership_start_date', $dates['start'] );
update_user_meta( $user_id, 'membership_end_date',   $dates['end'] );
update_user_meta( $user_id, 'membership_type',       'Individual Membership' );
update_user_meta( $user_id, 'membership_status',     'Active' );

//     $end_date = get_user_meta( $user_id, 'membership_end_date', true );
//     $dt = new DateTime( $end_date );
//$dt->modify('+365 days');

//$new_date = $dt->format('Y-m-d');
//$end_date = $new_date;




    }

if ( ! is_wp_error( $user_id ) ) {

    update_user_meta( $user_id, 'membership_start_date', $start_date );
    update_user_meta( $user_id, 'membership_end_date',   $end_date );
    update_user_meta( $user_id, 'membership_type',       'Individual Membership' );
    update_user_meta( $user_id, 'membership_status',     'Active' );

}


    if ( ! empty( $customer['address'] ) && is_array( $customer['address'] ) ) {

        $billing_map = [
            'billing_address_1' => $customer['address']['address_1'] ?? '',
            'billing_address_2' => $customer['address']['address_2'] ?? '',
            'billing_city'      => $customer['address']['city'] ?? '',
            'billing_state'     => $customer['address']['state'] ?? '',
            'billing_postcode'  => $customer['address']['postcode'] ?? '',
            'billing_country'   => $customer['address']['country'] ?? '',
            'billing_email'     => $email,
            'billing_first_name'=> $customer['first_name'] ?? '',
            'billing_last_name' => $customer['last_name'] ?? '',
        ];

        foreach ( $billing_map as $meta_key => $value ) {
            if ( ! empty( $value ) ) {
                update_user_meta( $user_id, $meta_key, $value );
            }
        }
    }




    //do_action( 'user_register', $user_id );

    $obj    = new SF_108Connector_AdminSettings();
    $result = $obj->sf_export_user_sync(array($user_id),true);

    // Here we need to make sure it has the meta value 

            $query = "SELECT id FROM Account WHERE PersonEmail = '".$email."'";
            $responses = SF_APIConnector::getSQueryObject( $query, 1 );

            if($company){
            gf_add_memberships_to_company($company,$responses->id);
            }    
            
            update_user_meta( $guser_id, 'sf_account_id', $responses->Id );
            

    return is_wp_error( $user_id ) ? 0 : (int) $user_id;
}











function gf_create_wc_order_for_customer( $user_id, $customer, $product_id, $quantity, $txn_id ) {

   dbg("Order 22");

    $order = wc_create_order([
        'customer_id' => $user_id,
    ]);

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        return 0;
    }

    $order->add_product( $product, max( 1, $quantity ) );
    $user = get_userdata( $user_id );

if ( $user ) {
    $email = $user->user_email;
    $sf_id = get_user_meta( $user_id, 'sf_object_id',true);
}

    /* --------------------
     * Billing
     * -------------------- */
    $order->set_billing_email( $customer['email'] );
    $order->set_billing_first_name( $customer['first_name'] );
    $order->set_billing_last_name( $customer['last_name'] );

    foreach ( $customer['address'] as $key => $val ) {
        if ( $val ) {
            $order->{"set_billing_{$key}"}( $val );
        }
    }

    /* --------------------
     * Payment
     * -------------------- */
    if ( $txn_id ) {
        $order->set_payment_method( 'Credit Card' );
        $order->payment_complete( $txn_id );
        $order->update_status( 'completed', 'GF payment completed.' );
    }
    $order->update_meta_data( 'sf_account_id', $sf_id );
    $order->update_meta_data('sf_date_created',current_time( 'Y-m-d' ));
    $order->calculate_taxes();
    $order->calculate_totals();
    $order->save();
    gf_queue_order_for_sf_resolution( $order->get_id() );
      $query = "SELECT Id FROM Account WHERE PersonEmail = '".$email."'";
      $responses = SF_APIConnector::getSQueryObject( $query, 1 );
    if(!empty($responses->Id)){
        $order->update_meta_data( 'sf_account_id', $sf_id );
        do_action( 'woocommerce_thankyou', $order->get_id() );
    }

   



    return $order->get_id();
}




function gf_queue_order_for_sf_resolution( $order_id ) {

    // prevent duplicates
    if ( get_post_meta( $order_id, '_sf_resolution_queued', true ) ) {
        return;
    }

    update_post_meta( $order_id, '_sf_resolution_queued', 1 );

    add_option(
        'sf_order_resolution_' . $order_id,
        [
            'order_id' => $order_id,
            'attempts' => 0,
        ],
        '',
        'no'
    );
}







function gf_update_membership_dates($sf_id, $user_id, $is_renewal = false){

$dates = gf_resolve_membership_dates( $user_id, $is_renewal );

$payload = [
    'Id' => $sf_id,
    'IS_Membership_Start_Date__c' => $dates['start'],
    'IS_Membership_End_Date__c'   => $dates['end'],
];


$account_update[] = [
    'attributes' => [ 'type' => 'Account' ],
] + $payload;

if ( ! empty( $account_update ) ) {

    $payload = [
        'allOrNone' => false,
        'records'   => $account_update,
    ];

    $response = SF_APIConnector::postCURLObject(json_encode( $payload ),'PATCH');
    update_user_meta( $user_id, 'membership_start_date', $start_date );
    update_user_meta( $user_id, 'membership_end_date', $end_date );
}


}




add_action( 'wp_footer', function () {
wp_enqueue_script( 'jquery' );    
?>

<style>

.gf-corporate-members {
    margin: 20px 0;
}

.corp-member-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 14px;
    background: #fff;
}

.corp-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 12px;
}

.corp-grid label {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 4px;
    display: block;
}

.corp-grid input {
    width: 100%;
    padding: 7px 9px;
    border-radius: 5px;
    border: 1px solid #ccc;
}

.corp-roles {
    display: flex;
    align-items: center;
    gap: 16px;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.corp-roles label {
    font-size: 13px;
}

.remove-member {
    margin-left: auto;
    background: #dc3232;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 5px;
    cursor: pointer;
}

.remove-event-member {
    margin-left: auto;
    background: #dc3232;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 5px;
    cursor: pointer;
}


.add-corp-member {
    background: #2271b1;
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    margin-top: 10px;
}

@media (max-width: 600px) {
    .corp-grid {
        grid-template-columns: 1fr;
    }
}




</style>

<script>
(function($){


const CURRENT_USER_ID = <?php echo get_current_user_id(); ?>;

document.addEventListener('change', function(e){

    if (!e.target.classList.contains('roster-checkbox')) return;

    const wrapper = e.target.closest('.gf-event-roster');

    const hidden = wrapper.parentElement.querySelector('input[type="hidden"][name^="input_"]');

    if (!hidden) return;

    let selected = [];

    wrapper.querySelectorAll('.roster-checkbox:checked').forEach(cb => {

        selected.push({
            first_name: cb.dataset.first || '',
            last_name:  cb.dataset.last || '',
            email:      cb.dataset.email || '',
            phone:      cb.dataset.phone || ''
        });

    });

    hidden.value = JSON.stringify(selected);

    // 🔥 THIS IS THE MISSING PIECE
    updateCartQuantityFromRoster($(wrapper));

});


document.addEventListener('change', function(e){

    if(e.target.id === 'choice_14_14_1'){
        const wrapper = document.querySelector('.gf-event-roster');
        if(wrapper){
            updateCartQuantityFromRoster($(wrapper));
        }
    }

});



function updateCartQuantityFromRoster($wrapper) {

    // 🔥 count selected members
    let selectedCount = $wrapper.find('.roster-checkbox:checked').length;

    // 🔥 main user always included
    let totalPeople = selectedCount + 1;
    const loggedInChecked =
        $wrapper
        .find('.roster-checkbox:checked')
        .filter(function(){

            return parseInt(
                $(this).data('user')
            ) === CURRENT_USER_ID;

        }).length > 0;

    if(loggedInChecked){
        totalPeople -= 2;
    }
    // 🔥 sponsor checkbox (your actual ID)
    const isSponsor = document.querySelector('#choice_14_14_1')?.checked || false;

    // 🔥 subtract 1 if sponsor checked
    let finalQty = totalPeople - (isSponsor ? 1 : 0);

    // 🔥 safety (never let it go below 1)
    finalQty = Math.max(1, finalQty);

    const productId = getEventProductId();
    if (!productId) return;

    $.ajax({
        url: wc_add_to_cart_params.ajax_url,
        type: 'POST',
        data: {
            action: 'update_event_product_quantity',
            product_id: productId,
            quantity: finalQty
        },
        success: function(res) {
            console.log('Cart updated:', finalQty);
            $(document.body).trigger('wc_fragment_refresh');
        }
    });
}



    function serializeMembers($wrapper) {
        const members = [];

        $wrapper.find('.corp-member-card').each(function(){
            const card = $(this);

            members.push({
                first_name: card.find('.corp-fname').val() || '',
                last_name:  card.find('.corp-lname').val() || '',
                email:      card.find('.corp-email').val() || '',
                phone:      card.find('.corp-phone').val() || '',
                owner:      card.find('.corp-owner').is(':checked'),
                main:       card.find('.corp-main').is(':checked'),
                billing:    card.find('.corp-billing').is(':checked'),
            });
        });

        $wrapper.find('input[type=hidden]').val(JSON.stringify(members));
    }

    function serializeEventMembers($wrapper) {

        const members = [];

        $wrapper.find('.corp-member-card').each(function(){
            const card = $(this);

            members.push({
                first_name: card.find('.corp-fname').val() || '',
                last_name:  card.find('.corp-lname').val() || '',
                email:      card.find('.corp-email').val() || '',
                phone:      card.find('.corp-phone').val() || '',
            });
        });

        $wrapper.find('input[type=hidden]').val(JSON.stringify(members));
    }



    function addMemberCard($wrapper, data = {}) {

        const html = `
        <div class="corp-member-card">
            <div class="corp-grid">
                <div>
                    <label>First Name</label>
                    <input type="text" class="corp-fname" value="${data.first_name || ''}">
                </div>

                <div>
                    <label>Last Name</label>
                    <input type="text" class="corp-lname" value="${data.last_name || ''}">
                </div>

                <div>
                    <label>Email</label>
                    <input type="email" class="corp-email" value="${data.email || ''}">
                </div>

                <div>
                    <label>Phone</label>
                    <input type="text" class="corp-phone" value="${data.phone || ''}">
                </div>
            </div>

            <div class="corp-roles">
                <label><input type="checkbox" class="corp-owner"> Owner</label>
                <label><input type="checkbox" class="corp-billing"> Billing</label>
                <label><input type="checkbox" class="corp-coordination"> Code Coordinator</label>
                <button type="button" class="remove-member">Remove</button>
            </div> 
        </div>`;

        const $card = $(html);

    if (data.owner) {
    $card.find('.corp-owner').prop('checked', true);
    }

    if (data.billing) {
    $card.find('.corp-billing').prop('checked', true);
    }


        $wrapper.find('.corp-members-container').append($card);

        serializeMembers($wrapper);
    }


function addEventMemberCard($wrapper, data = {}) {

    const html = `
    <div class="corp-member-card">
        <div class="corp-grid">
            <div>
                <label>First Name</label>
                <input type="text" class="corp-fname" value="${data.first_name || ''}">
            </div>

            <div>
                <label>Last Name</label>
                <input type="text" class="corp-lname" value="${data.last_name || ''}">
            </div>

            <div>
                <label>Email</label>
                <input type="email" class="corp-email" value="${data.email || ''}">
            </div>

            <div>
                <label>Phone</label>
                <input type="text" class="corp-phone" value="${data.phone || ''}">
            </div>
        </div>

        <button type="button" class="remove-event-member">Remove</button>
    </div>`;

    const $card = $(html);

    if (data.owner) {
    $card.find('.corp-owner').prop('checked', true);
    }

    if (data.billing) {
    $card.find('.corp-billing').prop('checked', true);
    }

    console.log($wrapper);
        $wrapper.find('.corp-members-container').append($card);

    serializeMembers($wrapper);
}

function getEventProductId() {
    const params = new URLSearchParams(window.location.search);
    return parseInt(params.get('eventID')) || 0;
}





function updateCartQuantity($wrapper) {

    let count = $wrapper.find('.corp-member-card').length;

    count = count + 1;
    // Minimum 1 (optional, remove if you want 0 allowed)
    //if (count < 1) count = 1;

    const productId = getEventProductId();

    if (!productId) return;

    // 🔥 AJAX call to update WooCommerce cart
    $.ajax({
        url: wc_add_to_cart_params.ajax_url,
        type: 'POST',
        data: {
            action: 'update_event_product_quantity',
            product_id: productId,
            quantity: count
        },
        success: function(res) {
            console.log('Cart updated:', res);

            // Optional: refresh fragments (mini cart etc)
            $(document.body).trigger('wc_fragment_refresh');
        }
    });
}






    // Add card
    $(document).on('click', '.add-corp-member', function(){
        addMemberCard($(this).closest('.gf-corporate-members'));
    });

$(document).on('click', '.add-event-member', function(){
    let $wrapper = $(this).closest('.gf-corporate-members');

    addEventMemberCard($wrapper);
    updateCartQuantity($wrapper);
});

$(document).on('click', '.remove-event-member', function(){
    let $wrapper = $(this).closest('.gf-corporate-members');

    $(this).closest('.corp-member-card').remove();

    serializeEventMembers($wrapper);
    updateCartQuantity($wrapper);
});

    // Remove card
    $(document).on('click', '.remove-member', function(){
        const $wrapper = $(this).closest('.gf-corporate-members');
        $(this).closest('.corp-member-card').remove();
        serializeMembers($wrapper);
    });

    // Update JSON on change
    $(document).on('input change', '.gf-corporate-members input', function(){
        serializeMembers($(this).closest('.gf-corporate-members'));
    });

    // Restore on validation error

jQuery(document).on('gform_post_render', function(){

    jQuery('.gf-corporate-members').each(function(){

        const $wrapper = jQuery(this);
        const val = $wrapper.find('input[type=hidden]').val();

        if (!val) return;

        const container = $wrapper.find('.corp-members-container');

        // ✅ IMPORTANT: clear existing cards first
        container.empty();

        try {
            JSON.parse(val).forEach(member => {
                addMemberCard($wrapper, member);
            });
        } catch(e){}

    });

});


})(jQuery);
</script>

<?php
});





function gf_create_company_account($company_name){

    $start_date = current_time( 'Y-m-d' );
    $end_date   = date('Y-m-d', strtotime( $start_date . ' +365 days' ));




    $membership_type = 'Individual by Organization'; // fallback

    if ( function_exists('WC') && WC()->cart ) {

        $cart_items = WC()->cart->get_cart();

        if ( ! empty( $cart_items ) ) {

            $first_item = reset( $cart_items ); // ✅ safe, does NOT modify cart
            $product    = $first_item['data'];

            if ( $product ) {
                $membership_type = $product->get_name();
            }
        }
    }

    $payload = [
        'Name' => $company_name,
        'IS_Membership_Start_Date__c' => $start_date,
        'IS_Membership_End_Date__c'   => $end_date,
        'IS_Membership_Type__c'       => $membership_type, 
        'IS_Status__c'                => 'Active'
    ];

    $account_update[] = [
        'attributes' => [ 'type' => 'Account' ],
    ] + $payload;

    if ( ! empty( $account_update ) ) {

        $payload = [
            'allOrNone' => false,
            'records'   => $account_update,
        ];

        $response = SF_APIConnector::postCURLObject(json_encode( $payload ), 'POST');



        return $response[0]->id;    
    }
}




function gf_add_memberships_to_company($pid,$aid){

$payload = [
    'IS_Parent_Account__c' => $pid,
    'IS_Account__c'   => $aid,
];

$account_update[] = [
    'attributes' => [ 'type' => 'IS_Affiliation__c' ],
] + $payload;

if ( ! empty( $account_update ) ) {

    $payload = [
        'allOrNone' => false,
        'records'   => $account_update,
    ];

    $response = SF_APIConnector::postCURLObject(json_encode( $payload ),'POST');

}




}



function gf_sync_member_to_salesforce($member, $company){
// Member is an array $member['email'];

    $obj    = new SF_108Connector_AdminSettings();
    $result = $obj->sf_export_user_sync(array($member),true);
    

    gf_add_memberships_to_company($company,$result);

}





add_action( 'sf_corporate_sync_cron', 'gf_process_corporate_sf_queue' );
function gf_process_corporate_sf_queue() {
    
    global $wpdb;
    $jobs = $wpdb->get_results(
        "SELECT option_name, option_value
         FROM {$wpdb->options}
         WHERE option_name LIKE 'sf_corp_sync_%'
         LIMIT 1"
    );

    
if ( ! $jobs ) {
    return; // ✅ no option found → stop safely
}

            $data = maybe_unserialize( $jobs[0]->option_value );

    foreach ( $jobs as $job ) {

        $data = maybe_unserialize( $jobs[0]->option_value );
        $order_id = absint( $data['order_id'] ?? 0 );

        if ( ! $order_id ) {
            delete_option( $jobs[0]->option_name );
            continue;
        }

        // 🔥 Salesforce work now
        $company = gf_create_company_account( $data['company_name'] );
       
        // Synching Each Member To Salesforce
       
        foreach ( $data['members'] as $member ) {
            
        if(!empty($company)){    
        gf_sync_member_to_salesforce( $member, $company );
        }
            }

       // sf_export_post_sync( [ $order_id ], 'shop_order', true );

        delete_option( $job->option_name );
    }


update_option("sf_update_order_step_".$data['order_id'],$data['order_id']);


}




add_action( 'sf_update_order_orphaned', 'sf_update_order_orphaned_action' );
function sf_update_order_orphaned_action() {

    global $wpdb;
    $jobs = $wpdb->get_results(
        "SELECT option_name, option_value
         FROM {$wpdb->options}
         WHERE option_name LIKE 'sf_update_order_step_%'
         LIMIT 1"
    );    


    if ( ! $jobs ) {
    return; // ✅ no option found → stop safely
    }

            $data = maybe_unserialize( $jobs[0]->option_value );

            // $data is order id

        $order = wc_get_order($data);
        $user_id = $order ? $order->get_user_id() : 0;
        $sf_id = get_user_meta($user_id,'sf_object_id',true);
        update_post_meta( $data, 'sf_account_id',$sf_id); 
        $obj    = new SF_108Connector_AdminSettings();
        $result = $obj->sf_export_post_sync(array($data), 'shop_order',true);
  

    delete_option( 'sf_update_order_step_'.$data );

}



function gf_form_has_cart_widget( $form ) : bool {
    if ( empty( $form['fields'] ) ) {
        return false;
    }

    foreach ( $form['fields'] as $field ) {
        if ( isset( $field->type ) && $field->type === 'gf_cart' ) {
            return true;
        }
    }

    return false;
}



function gf_handle_event_registration_payment( $entry, $action ){


     $form = GFAPI::get_form( rgar( $entry, 'form_id' ) );
     $customer = gf_extract_basic_customer_data( $entry, $form );
    if ( empty( $customer['email'] ) ) {
        return;
    }


$address = [
    'address_1' => '',
    'address_2' => '',
    'city'      => '',
    'state'     => '',
    'postcode'  => '',
    'country'   => '',
];

foreach ( $form['fields'] as $field ) {

    if ( $field->type === 'address' ) {

        $field_id = (string) $field->id;

        $address['address_1'] = rgar( $entry, $field_id . '.1' );
        $address['address_2'] = rgar( $entry, $field_id . '.2' );
        $address['city']      = rgar( $entry, $field_id . '.3' );
        $address['state']     = rgar( $entry, $field_id . '.4' );
        $address['postcode']  = rgar( $entry, $field_id . '.5' );
        $address['country']   = rgar( $entry, $field_id . '.6' );

        break; // only ONE address field
    }
}




    $user_id = gf_get_or_create_wp_user_only( $customer,false,$address );
    if ( ! $user_id ) {
        return;
    }

    $product_ids = gfwc_collect_cart_product_ids( $form, $entry );
    if ( empty( $product_ids ) ) {
        return;
    }



     $txn_id = rgar( $action, 'transaction_id' );

    foreach ( $product_ids as $pid ) {

        $order_id = gf_create_wc_order_for_customer($user_id,$customer,$pid,1,$txn_id);


    }

}




function gfwc_collect_cart_product_ids() : array {

    $ids = [];

    if ( ! function_exists( 'WC' ) ) {
        return $ids;
    }

    // Ensure cart is available (shortcodes / AJAX safe)
    if ( WC()->cart === null ) {
        wc_load_cart();
    }

    if ( ! WC()->cart ) {
        return $ids;
    }

    foreach ( WC()->cart->get_cart() as $cart_item ) {

        $pid = intval( $cart_item['product_id'] ?? 0 );
        if ( $pid <= 0 ) {
            continue;
        }

        $product = wc_get_product( $pid );
        if ( ! $product ) {
            continue;
        }

        // Match your existing behavior: ignore free products
        if ( floatval( $product->get_price() ) <= 0 ) {
            continue;
        }

        $ids[] = $pid;
    }

    return array_values( array_unique( $ids ) );
}





add_action( 'wp_ajax_render_wc_thankyou', 'render_wc_thankyou_ajax' );
add_action( 'wp_ajax_nopriv_render_wc_thankyou', 'render_wc_thankyou_ajax' );

function render_wc_thankyou_ajax() {

    $order_id = absint( $_POST['order_id'] ?? 0 );

    if ( ! $order_id ) {
        wp_send_json_error( 'Invalid order ID' );
    }

    if ( ! function_exists( 'wc_get_order' ) ) {
        wp_send_json_error( 'WooCommerce not loaded' );
    }

    ob_start();

    /**
     * EXACT WooCommerce thank you output
     */
    do_action( 'woocommerce_thankyou', $order_id );

    wp_send_json_success( ob_get_clean() );
}








add_action( 'gform_loaded', function () {

    if ( ! class_exists( 'GFForms' ) ) {
        return;
    }

    $map = get_option( 'gf_Member_Event_form_mapping', [] );
    $ce_form_id = isset( $map['ce_request'] ) ? (int) $map['ce_request'] : 0;

    if ( ! $ce_form_id ) {
        return;
    }


    add_action( "gform_enqueue_scripts_{$ce_form_id}", 'enqueue_ce_credit_js', 10, 2 );
});




function enqueue_ce_credit_js( $form, $is_ajax ) {
wp_enqueue_script( 'ce-credit-js', SF_108GFADDON_URL . 'js/ce-credit.js', [ 'jquery' ], '1.1', true );
  

    // Pass data to JS safely
    wp_localize_script(
        'ce-credit-js',
        'CECreditData',
        [
            'formId' => (int) $form['id'],
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ]
    );
}




add_filter( 'gform_pre_render', 'gf_prefill_logged_in_user' );
add_filter( 'gform_pre_validation', 'gf_prefill_logged_in_user' );
add_filter( 'gform_pre_submission_filter', 'gf_prefill_logged_in_user' );

function gf_prefill_logged_in_user( $form ) {

    if ( ! is_user_logged_in() ) {
        return $form;
    }

    $user = wp_get_current_user();
    if ( ! $user || ! $user->ID ) {
        return $form;
    }

    $billing = [
        'address_1' => get_user_meta( $user->ID, 'billing_address_1', true ),
        'address_2' => get_user_meta( $user->ID, 'billing_address_2', true ),
        'city'      => get_user_meta( $user->ID, 'billing_city', true ),
        'state'     => get_user_meta( $user->ID, 'billing_state', true ),
        'postcode'  => get_user_meta( $user->ID, 'billing_postcode', true ),
        'country'   => get_user_meta( $user->ID, 'billing_country', true ),
        'phone'     => get_user_meta( $user->ID, 'billing_phone', true ),
    ];

    /* ========================
     * 🔍 STEP 1: CHECK IF ORG FIELD EXISTS
     * ======================== */
    $org_field_exists = false;

    foreach ( $form['fields'] as $field ) {
        $label = $field->adminLabel ?: $field->label;

        if ( strpos( strtolower($label), 'organization name' ) !== false ) {
            $org_field_exists = true;
            break;
        }
    }

    /* ========================
     * 🔥 STEP 2: FETCH ORG DATA ONLY IF NEEDED
     * ======================== */
    $org_data = null;

    if ( $org_field_exists ) {
        $org_data = gf_get_user_organization_data( $user->ID );
        // Expected: ['name' => '', 'id' => '']
    }

    /* ========================
     * 🔁 MAIN LOOP
     * ======================== */
    foreach ( $form['fields'] as &$field ) {

        $type = method_exists( $field, 'get_input_type' )
            ? $field->get_input_type()
            : $field->type;

        /* EMAIL */
        if ( $type === 'email' ) {
            if ( empty( $field->defaultValue ) ) {
                $field->defaultValue = $user->user_email;
            }
            // $field->cssClass .= ' gf-lock-field';
        }

        /* PHONE */
        if ( $type === 'phone' && empty( $field->defaultValue ) ) {
            $field->defaultValue = $billing['phone'];
        }

        /* NAME */
        if ( $type === 'name' && ! empty( $field->inputs ) ) {

            // $field->cssClass .= ' gf-lock-field';

            foreach ( $field->inputs as &$input ) {

                $input_id = (string) $input['id'];

                if ( strpos( $input_id, '.3' ) !== false && empty( $input['defaultValue'] ) ) {
                    $input['defaultValue'] = $user->first_name;
                }

                if ( strpos( $input_id, '.6' ) !== false && empty( $input['defaultValue'] ) ) {
                    $input['defaultValue'] = $user->last_name;
                }
            }
        }

        /* ADDRESS */
        if ( $type === 'address' && ! empty( $field->inputs ) ) {

            foreach ( $field->inputs as &$input ) {

                $input_id = (string) $input['id'];

                if ( strpos( $input_id, '.1' ) !== false ) $input['defaultValue'] = $billing['address_1'];
                if ( strpos( $input_id, '.2' ) !== false ) $input['defaultValue'] = $billing['address_2'];
                if ( strpos( $input_id, '.3' ) !== false ) $input['defaultValue'] = $billing['city'];
                if ( strpos( $input_id, '.4' ) !== false ) $input['defaultValue'] = $billing['state'];
                if ( strpos( $input_id, '.5' ) !== false ) $input['defaultValue'] = $billing['postcode'];
                if ( strpos( $input_id, '.6' ) !== false ) $input['defaultValue'] = $billing['country'];
            }
        }

        /* ========================
         * 🏢 ORGANIZATION NAME
         * ======================== */
        if ( $org_data ) {
  
            $label = $field->adminLabel ?: $field->label;

            if ( strpos( strtolower($label), 'organization name' ) !== false ) {

                if ( empty( $field->defaultValue ) && ! empty($org_data['name']) ) {
                    $field->defaultValue = $org_data['name'];
                }

                // $field->cssClass .= ' gf-lock-field';
            }

            if ( strpos( strtolower($label), 'organization id' ) !== false ) {

                if ( empty( $field->defaultValue ) && ! empty($org_data['id']) ) {
                    $field->defaultValue = $org_data['id'];
                }
            }
           
        }
    }

    return $form;
}


add_filter( 'gform_field_content', 'gf_make_fields_readonly', 10, 5 );
function gf_make_fields_readonly( $content, $field, $value, $lead_id, $form_id ) {

    if ( ! is_user_logged_in() ) {
        return $content;
    }

    // Only lock fields we marked
    if ( strpos( $field->cssClass, 'gf-lock-field' ) === false ) {
        return $content;
    }

    // Add readonly + disable pointer interaction
    $content = str_replace(
        '<input',
        '<input readonly="readonly" tabindex="-1"',
        $content
    );

    return $content;
}







function gf_handle_ce_request_form( $entry, $action ) {

   /*     
    if ( (int) rgar( $entry, 'form_id' ) !== 9 ) {
        return;
    }
  */
    $form = GFAPI::get_form( rgar( $entry, 'form_id' ) );
    if ( empty( $form['fields'] ) ) {
        return;
    }

    $payload = [];

    foreach ( $form['fields'] as $field ) {

        $value = rgar( $entry, $field->id );
        if ( $value === '' || $value === null ) {
            continue;
        }

        // 🔹 Salesforce Account ID
        if (
            $field->type === 'sf_account_id' ||
            strpos( (string) $field->cssClass, 'sf_account_id' ) !== false
        ) {
            $payload['IS_Member__c'] = sanitize_text_field( $value );
            continue;
        }

        // 🔹 Enrolled Course
        if ( $field->type === 'cecredit' ) {
            $payload['IS_My_Course__c'] = sanitize_text_field( $value );
            continue;
        }

        // 🔹 Credits Earned
        if ( strtolower( $field->label ) === 'credits earned' ) {
            $payload['IS_Credits_Earned__c'] = floatval( $value );
            continue;
        }

        // 🔹 Description
        if ( strtolower( $field->label ) === 'description' ) {
            $payload['IS_Comment__c'] = sanitize_textarea_field( $value );
            continue;
        }

        // 🔹 Enrollment Date
        if ( $field->type === 'date' ) {
          //  $payload['IS_Enrollment_Date__c'] = sanitize_text_field( $value );
            continue;
        }
    }


    $payload['IS_Credit_Status__c'] = "Pending Approval";

    $payload['IS_Credits_Earned__c'] = sanitize_text_field( rgar( $entry, 6 ) );

    // Remove empty values (Salesforce-safe)
    $payload = array_filter( $payload, function ( $v ) {
        return $v !== '' && $v !== null;
    });

    $records[] = [
        'attributes' => [ 'type' => 'IS_CE_Credit__c' ],
    ] + $payload;



    // 🚀 Send to Salesforce (same method your plugin uses)
    $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]),'POST');


}




function sf108_create_form_submission( $user_id, $order_id, $recordType = "", $order_sf_id="" , $company_id ='' ) {

dbg("Form Submission ".$user_id." | ".$order_id);

    $order_id = absint( $order_id );
    if ( ! $user_id || ! $order_id ) {
        return;
    }


    /* ------------------------------------
     * LOAD ORDER
     * ------------------------------------ */
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    /* ------------------------------------
     * USER DATA
     * ------------------------------------ */
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return;
    }

dbg("Passed First Level");

    $first_name = $user->first_name ?: get_user_meta( $user_id, 'billing_first_name', true );
    $last_name  = $user->last_name  ?: get_user_meta( $user_id, 'billing_last_name', true );
    $email      = $user->user_email;
    $phone      = get_user_meta( $user_id, 'billing_phone', true );
    $address1 = get_user_meta( $user_id, 'billing_address_1', true );
    $address2 = get_user_meta( $user_id, 'billing_address_2', true );
    $city = get_user_meta( $user_id, 'billing_city', true );
    $postcode = get_user_meta( $user_id, 'billing_postcode', true );
    $country = get_user_meta( $user_id, 'billing_country', true );
    $state = get_user_meta( $user_id, 'billing_state', true );

    /* ------------------------------------
     * SALESFORCE IDS
     * ------------------------------------ */



    $sf_account_id = get_user_meta( $user_id, 'sf_object_id', true );
    if ( ! $sf_account_id ) {
        return; // Required
    }

 dbg($sf_account_id);   


$sf_order_id = $order->get_meta( 'sf_object_id' );

if($sf_order_id == null){

$sf_order_id = $order_sf_id;

}



dbg("Order id is ".$order_sf_id);

    /* ------------------------------------
     * PAYLOAD (FULLY DYNAMIC)
     * ------------------------------------ */
$payload = [
    'IS_First_Name__c' => sanitize_text_field( $first_name ),
    'IS_Last_Name__c'  => sanitize_text_field( $last_name ),
    'IS_Email__c'      => sanitize_email( $email ),
    'IS_Phone__c'      => sanitize_text_field( $phone ),
    'IS_Status__c'     => 'Submitted',
    'IS_Account__c'    => $company_id,
    'IS_Order__c'      => $sf_order_id,
    'Address_Line1__c' => $address1,
    'IS_Address_Line2__c' => $address2,
    'IS_City__c'            => sanitize_text_field( $city ),
    'IS_State_Province__c'  => sanitize_text_field( $state ),
    'IS_ZIP_Postal_Code__c' => sanitize_text_field( $postcode ),
    'IS_Country__c'         => sanitize_text_field( $country ),
    'RecordTypeId'     => $recordType,
];




 $records = [['attributes' => [ 'type' => 'IS_Form_Submission__c' ],] + $payload];

 $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records' => $records,]),'POST');

dbg($response);

}




add_filter( 'gform_validation', 'gf_block_if_email_exists_for_complaint_form' );
function gf_block_if_email_exists_for_complaint_form( $validation_result ) {

    // 🔹 Get mapping from wp_options
    $map = get_option( 'gf_Member_Event_form_mapping', [] );
    $complaint_form_id = (int) ( $map['complaints_form'] ?? 0 );

    if ( ! $complaint_form_id ) {
        return $validation_result;
    }

    $form = $validation_result['form'];

    // Only run on complaint form
    if ( (int) $form['id'] !== $complaint_form_id ) {
        return $validation_result;
    }

    foreach ( $form['fields'] as &$field ) {

        if ( $field->get_input_type() !== 'email' ) {
            continue;
        }

        $field_id = (string) $field->id;

        $email = rgpost( 'input_' . $field_id );
        $email = sanitize_email( $email );

        if ( empty( $email ) ) {
            continue;
        }

        // ✅ Block ONLY if email exists AND user is NOT logged in
        if ( email_exists( $email ) && ! is_user_logged_in() ) {

            $validation_result['is_valid'] = false;

            $field->failed_validation  = true;
            $field->validation_message =
                'You already have an account. Please log in.';

            break;
        }
    }

    $validation_result['form'] = $form;

    return $validation_result;
}





function fetch_sf_order_details( $order_id ) {

    // Get order
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return '';
    }

    ob_start();

    // ----------------------------------
    // Order items + totals table
    // ----------------------------------
    woocommerce_order_details_table( $order_id );

    // ----------------------------------
    // Get user from order
    // ----------------------------------
    $user_id = $order->get_user_id();

    if ( $user_id ) {

        // Fetch billing meta manually
        $address1 = get_user_meta( $user_id, 'billing_address_1', true );
        $address2 = get_user_meta( $user_id, 'billing_address_2', true );
        $city     = get_user_meta( $user_id, 'billing_city', true );
        $state    = get_user_meta( $user_id, 'billing_state', true );
        $postcode = get_user_meta( $user_id, 'billing_postcode', true );
        $country  = get_user_meta( $user_id, 'billing_country', true );

        // Only show section if at least one field exists
        if ( $address1 || $city || $postcode || $country ) :
        ?>
            <section class="sf_billing_address" style="text-align:left;">

                <h2>Billing Address</h2>

                <address>
                    <?php if ( $address1 ) echo "<strong>Address Line 1 : </strong>".esc_html( $address1 ) . '<br>'; ?>
                    <?php if ( $address2 ) echo "<strong>Address Line 2 : </strong>".( $address2 ) . '<br>'; ?>
                    <?php if ( $city )     echo "<strong>City : </strong>". esc_html( $city ) . '<br>'; ?>
                    <?php if ( $state )    echo "<strong>State : </strong>".esc_html( $state ) . '<br>'; ?>
                    <?php if ( $postcode ) echo "<strong>Zipcode : </strong>".esc_html( $postcode ) . '<br>'; ?>
                    <?php if ( $country )  echo "<strong>Country : </strong>".esc_html( $country ); ?>
                </address>

            </section>
        <?php
        endif;

    } 

    return ob_get_clean();
}




add_action('init',function(){

if(isset($_GET['ffe'])){
echo "Active";
$query = "SELECT IS_Event__c FROM IS_Product__c WHERE IS_WC_Product_ID__c = '3123'";
$responses = SF_APIConnector::getSQueryObject( $query, 1 );
print_r($responses);
}


});







// Generic Pipeline


add_action( 'wp_ajax_gf_product_pipeline_run_step', 'gf_product_pipeline_run_step' );
add_action( 'wp_ajax_nopriv_gf_product_pipeline_run_step', 'gf_product_pipeline_run_step' );


function gf_handle_product_payment( $entry, $action ) {

add_filter( 'gform_confirmation', function ( $confirmation ) use ( $entry,$action ) {

$entry_id = (int) rgar( $entry, 'id' );

$charge_id = sf_fetch_charge_id($entry,$action);

$confirmation .= '<style>
#gf-prod-pipeline{margin:30px auto;text-align:center}
#gf-prod-status{margin-top:10px;font-size:15px}
</style>

<div id="gf-prod-pipeline">
  <img src="' . esc_url( includes_url( 'images/spinner.gif' ) ) . '">
  <div id="gf-prod-status">Processing order…</div>
</div>

<script>
(function(){

var entryId = "' . $entry_id . '";
var chargeId = "'.$charge_id.'";
var ajaxUrl = "' . esc_url( admin_url( 'admin-ajax.php' ) ) . '";

var STEP_MESSAGES={
 init:"Initializing…",
 resolve_user:"Preparing account…",
 sync_salesforce:"Syncing customer…",
 create_order:"Creating order…",
 finalize:"Finalizing purchase…"
};

function runStep(step,context){

 document.getElementById("gf-prod-status").textContent =
  STEP_MESSAGES[step] || "Processing…";

 fetch(ajaxUrl,{
   method:"POST",
   headers:{"Content-Type":"application/x-www-form-urlencoded"},
   body:new URLSearchParams({
     action:"gf_product_pipeline_run_step",
     step:step,
     context:JSON.stringify(context||{})
   })
 })
 .then(r=>r.json())
 .then(json=>{

   if(!json.success){
   console.log(json.error);
     document.getElementById("gf-prod-status").textContent = json.error || "Error";
     return;
   }

   if(json.data.next){
   console.log(json.data.next);
     runStep(json.data.next,json.data.context||{});
   }else{

     var ctx=json.data.context||{};
     var box=document.getElementById("gf-prod-pipeline");

     box.innerHTML=`
      <div class="thanks_message">Purchase Completed</div>
      <div class="sf108-order-html">
        ${ctx.order_html||"<p>No order details.</p>"}
      </div>
      <div style="margin-top:40px">
        <a href="/dashboard" class="visit_dashboard">
          Visit Dashboard
        </a>
      </div>`;
   }

 });

}

runStep("init",{entry_id:entryId,chargeId:chargeId});

})();
</script>';

        return $confirmation;
    });
}




function gf_product_pipeline_run_step() {

    $step    = sanitize_text_field( $_POST['step'] ?? '' );
    $context = json_decode( stripslashes( $_POST['context'] ?? '{}' ), true );

        if ( $step === 'init' && ! empty( $context['entry_id'] ) ) {

        $entry_id = (int) $context['entry_id'];

        if ( gform_get_meta( $entry_id, '_product_pipeline_locked' ) ) {

            wp_send_json_error([
                'error' => 'Pipeline already running'
            ]);
        }

        gform_update_meta( $entry_id, '_product_pipeline_locked', time() );
    }


    switch ( $step ) {

        case 'init':
            
            wp_send_json_success([
                'next'=>'resolve_user',
                'context'=>$context
            ]);


        case 'resolve_user':

            $entry = GFAPI::get_entry( (int) $context['entry_id'] );
            if ( is_wp_error( $entry ) ) {
                wp_send_json_error([ 'error' => 'Invalid entry' ]);
            }

            $form = GFAPI::get_form( rgar( $entry, 'form_id' ) );
            $customer = gf_extract_basic_customer_data( $entry, $form );

            if ( empty( $customer['email'] ) ) {
                wp_send_json_error([ 'error' => 'Email missing' ]);
            }

            $user_id = gf_get_or_create_wp_user(
                $customer['email'],
                $customer['first_name'],
                $customer['last_name'],
                'subscriber'
            );

    if ( $user_id && ! is_wp_error( $user_id ) ) {

    // Billing name
    update_user_meta( $user_id, 'billing_first_name', $customer['first_name'] );
    update_user_meta( $user_id, 'billing_last_name',  $customer['last_name'] );

    // Billing phone
    if ( ! empty( $customer['phone'] ) ) {
        update_user_meta( $user_id, 'billing_phone', $customer['phone'] );
    }

    // Billing address
    if ( ! empty( $customer['address']['address_1'] ) ) {
        update_user_meta( $user_id, 'billing_address_1', $customer['address']['address_1'] );
        update_user_meta( $user_id, 'billing_address_2', $customer['address']['address_2'] );
        update_user_meta( $user_id, 'billing_city',      $customer['address']['city'] );
        update_user_meta( $user_id, 'billing_state',     $customer['address']['state'] );
        update_user_meta( $user_id, 'billing_postcode',  $customer['address']['postcode'] );
        update_user_meta( $user_id, 'billing_country',   $customer['address']['country'] );
    }
}

            if ( is_wp_error( $user_id ) ) {
                wp_send_json_error([ 'error' => $user_id->get_error_message() ]);
            }

            // SAFE membership handling
            $context['user_id']  = (int) $user_id;
            $context['customer'] = $customer;

            wp_send_json_success([
                'step'    => 'resolve_user',
                'next'    => 'sync_salesforce',
                'context' => $context,
            ]);



        case 'sync_salesforce':

            $obj=new SF_108Connector_AdminSettings();
            $obj->sf_export_user_sync([$context['user_id']],true);

            $context['sf_account_id']=get_user_meta(
                $context['user_id'],
                'sf_object_id',
                true
            );

            wp_send_json_success([
                'next'=>'create_order',
                'context'=>$context
            ]);

        case 'create_order':

            $entry=GFAPI::get_entry((int)$context['entry_id']);
            $form=GFAPI::get_form(rgar($entry,'form_id'));
            $products=GFCommon::get_product_fields($form,$entry);
            $p=reset($products['products']);

            $product=wc_get_product((int)$p['id']);
              dbg("Order 5");
            $order=wc_create_order([
                'customer_id'=>$context['user_id']
            ]);

            $order->add_product($product,1);
            $order->calculate_totals();

            $txn = $context['chargeId'];
            if($txn){
                $order->payment_complete($context['chargeId']);
                $order->update_status('completed');
            }

            $order->update_meta_data(
              'sf_account_id',
              $context['sf_account_id']
            );
            $order->update_meta_data('sf_date_created',current_time( 'Y-m-d' ));
            $order->save();
            $obj    = new SF_108Connector_AdminSettings();
            $result = $obj->sf_export_post_sync(array($order->get_id()), 'shop_order',true);
            $context['order_id']= $order->get_id();

            wp_send_json_success([
                'next'=>'finalize',
                'context'=>$context
            ]);

        case 'finalize':

            $context['order_html'] =
              fetch_sf_order_details((int)$context['order_id']);

            wp_set_current_user($context['user_id']);
            wp_set_auth_cookie($context['user_id']);

            wp_send_json_success([
                'next'=>null,
                'context'=>$context
            ]);
    }

    wp_send_json_error(['error'=>'Invalid step']);
}














add_filter( 'gettext', function( $translated, $original, $domain ) {

    if ( $domain === 'woocommerce' && $original === 'Tax' ) {
        return 'HST';
    }

    return $translated;

}, 20, 3 );





add_action( 'wp_ajax_gf_ispa_pipeline_run_step', 'gf_ispa_pipeline_run_step' );
add_action( 'wp_ajax_nopriv_gf_ispa_pipeline_run_step', 'gf_ispa_pipeline_run_step' );


function gf_handle_individual_membership_ispa_payment( $entry, $action ) {

    add_filter( 'gform_confirmation', function ( $confirmation ) use ( $entry ) {

        $entry_id = (int) rgar( $entry, 'id' );

        $confirmation .= '
<style>
#gf-ispa-pipeline {
    margin: 30px auto;
    text-align: center;
}
#gf-ispa-status {
    margin-top: 10px;
    font-size: 15px;
}
</style>

<div id="gf-ispa-pipeline">
    <img src="' . esc_url( includes_url( 'images/spinner.gif' ) ) . '" />
    <div id="gf-ispa-status">Initializing ISPA membership…</div>
</div>

<script>
(function(){

    var entryId = ' . $entry_id . ';
    var ajaxUrl = "' . esc_url( admin_url( 'admin-ajax.php' ) ) . '";

    var STEP_MESSAGES = {
        init: "Initializing ISPA membership…",
        resolve_user: "Creating your account…",
        sync_salesforce: "Creating ISPA Membership…",
        create_order: "Finalizing your order…",
        finalize: "Creating Membership Request…"
    };

    function whenReady(fn) {
        if (document.readyState === "complete" || document.readyState === "interactive") {
            fn();
        } else {
            document.addEventListener("DOMContentLoaded", fn);
        }
    }

    function whenjQuery(fn) {
        if (window.jQuery) {
            fn(window.jQuery);
        } else {
            setTimeout(function(){ whenjQuery(fn); }, 50);
        }
    }

    whenReady(function(){
        whenjQuery(function($){

            var status = document.getElementById("gf-ispa-status");

            function setStatus(text){
                if (status) status.textContent = text;
            }

            function runStep(step, context){

                setStatus(STEP_MESSAGES[step] || "Processing…");

                fetch(ajaxUrl, {
                    method: "POST",
                    headers: {"Content-Type":"application/x-www-form-urlencoded"},
                    body: new URLSearchParams({
                        action: "gf_ispa_pipeline_run_step",
                        step: step,
                        context: JSON.stringify(context || {})
                    })
                })
                .then(res => res.json())
                .then(json => {

                    if (!json.success) {
                        setStatus(json.error || "Something went wrong.");
                        return;
                    }

                    if (json.data && json.data.next) {
                        runStep(json.data.next, json.data.context || {});
                    } else {

                        var ctx = json.data.context || {};

                        setStatus("ISPA Membership Created");

                        document.getElementById("gf-ispa-pipeline").innerHTML = `
                            <div class="thanks_message">
                                ISPA Membership Successfully Created
                            </div>

                            <div class="sf108-order-html">
                                ${ctx.order_html || "<p>No order details</p>"}
                            </div>

                            <div style="margin-top: 40px;">
                                <a href="/dashboard" class="visit_dashboard">
                                    Visit Dashboard
                                </a>
                            </div>
                        `;
                    }
                })
                .catch(() => {
                    setStatus("Unexpected error occurred.");
                });
            }

            runStep("init", { entry_id: entryId });

        });
    });

})();
</script>';

        return $confirmation;
    });
}






function gf_ispa_pipeline_run_step() {

    $step = sanitize_text_field( $_POST['step'] ?? '' );
    $context = json_decode( stripslashes($_POST['context'] ?? '{}'), true );

    if ( empty($context['entry_id']) ) {
        wp_send_json_error([ 'error' => 'Missing entry ID' ]);
    }

    $entry = GFAPI::get_entry( (int)$context['entry_id'] );
    $form  = GFAPI::get_form( $entry['form_id'] );
    $customer = gf_extract_basic_customer_data( $entry, $form );
    switch ( $step ) {

        case 'init':
            $context['started'] = time();
            wp_send_json_success([
                'next' => 'resolve_user',
                'context' => $context
            ]);
        break;

        case 'resolve_user':

        $user_id = ispa_get_or_create_user_from_entry( $entry );


        $user = get_userdata($user_id);
        $cart_items = WC()->cart->get_cart();
        $first_product_name = '';
        if ( ! empty( $cart_items ) ) {
        $first_item = reset( $cart_items );
        $first_product_name = $first_item['data']->get_name();
        }

        $first_name = get_user_meta( $user_id, 'first_name', true );
        $last_name  = get_user_meta( $user_id, 'last_name', true );
        $email      = $user->user_email;
        $start_date = current_time( 'Y-m-d' );
        $end_date = date('Y-m-d',strtotime( $start_date . ' +365 days' ));
        $membership_type = $first_product_name;
   
// ID and email are blank here


     update_user_meta( $user_id, 'first_name', $first_name );       
     update_user_meta( $user_id, 'last_name', $last_name );       
     update_user_meta( $user_id, 'membership_status', 'Active' );
     update_user_meta( $user_id, 'membership_type', $membership_type );       
     update_user_meta( $user_id, 'membership_start_date', $start_date );
     update_user_meta( $user_id, 'membership_end_date', $end_date );


      if ( ! empty( $customer['address']['address_1'] ) ) {
        update_user_meta( $user_id, 'billing_address_1', $customer['address']['address_1'] );
        update_user_meta( $user_id, 'billing_address_2', $customer['address']['address_2'] );
        update_user_meta( $user_id, 'billing_city',      $customer['address']['city'] );
        update_user_meta( $user_id, 'billing_state',     $customer['address']['state'] );
        update_user_meta( $user_id, 'billing_postcode',  $customer['address']['postcode'] );
        update_user_meta( $user_id, 'billing_country',   $customer['address']['country'] );
    }

   
    $obj    = new SF_108Connector_AdminSettings();
    $result = $obj->sf_export_user_sync(array($user_id), 'account',true);            

    $context['sf_id'] = $result ?? '';
    $context['start_date'] = $start_date ?? '';
    $context['end_date'] = $end_date ?? '';        
    $context['membership_type'] = $membership_type ?? '';


    $context['user_id'] = $user_id;

            wp_send_json_success([
                'next' => 'create_order',
                'context' => $context
            ]);
        break;

        case 'sync_salesforce':



            $cart_items = WC()->cart->get_cart();

            $first_product_name = '';

        if ( ! empty( $cart_items ) ) {
        $first_item = reset( $cart_items );
        $first_product_name = $first_item['data']->get_name();
        }


        $user_id = $context['user_id'];
        $first_name = get_user_meta( $user_id, 'first_name', true );
        $last_name  = get_user_meta( $user_id, 'last_name', true );
        $email      = $user->user_email;
        $start_date = current_time( 'Y-m-d' );
        $end_date = date('Y-m-d',strtotime( $start_date . ' +365 days' ));
        $membership_type = $first_product_name;
   
// ID and email are blank here

    $payload = [
    'Id' => $context['sf_id'],    
    'FirstName'   => $first_name ?: '',
    'LastName'    => $last_name ?: '',
    'PersonEmail' => $email ?: '',
    'IS_Status__c' => 'Active',
    'IS_Membership_Type__c' => $membership_type,
    'IS_Membership_Start_Date__c' => $start_date,
    'IS_Membership_End_Date__c' => $end_date
    ];




            $response = SF_APIConnector::postCURLObject(json_encode([
                'records' => [[
                    'attributes' => ['type' => 'Account']
                ] + $payload]
            ]), 'POST');

            $context['sf_id'] = $response[0]->id ?? '';


            wp_send_json_success([
                'next' => 'create_order',
                'context' => $context
            ]);
        break;





case 'create_order':
  dbg("Order 6");
$order = wc_create_order([
    'customer_id' => $context['user_id']
]);

if ( WC()->cart === null ) {
    wc_load_cart();
}

$cart_items = WC()->cart->get_cart();

foreach ( $cart_items as $cart_item ) {

    $product = $cart_item['data'];
    $qty     = $cart_item['quantity'];

    if ( ! $product ) continue;

    // ✅ USE CART PRICE (not product price)
    $line_total = (float) $cart_item['line_total'];


    $adjusted_total = $line_total + $total_fee;

    $order->add_product( $product, $qty, [
        'subtotal' => $adjusted_total,
        'total'    => $adjusted_total,
    ]);
}

// Payment
$txn_id = rgar( $entry, 'transaction_id' );

if ( $txn_id ) {
    $order->set_payment_method( 'Credit Card' );
    $order->payment_complete( $txn_id );
    $order->update_status( 'completed', 'Corporate membership payment completed' );
} else {
    $order->update_status( 'completed', 'Corporate membership (no txn id)' );
}

$order->update_meta_data('sf_account_id', $context['sf_id']);
 $membership_start = $current_year . '-07-01';
$membership_end = ( $current_year + 1 ) . '-06-30';
$product_name = $product->get_name();
$order->update_meta_data('membership_start_date', $membership_start);
$order->update_meta_data('membership_end_date', $membership_end );

$order->update_meta_data('membership_type', $context['membership_type']);


//annual_revenue

$volume = '37';

    $revenue = floatval( WC()->session->get('ispa_revenue') );

    if ( $revenue >= 1000000 ) {
    $excess = $revenue - 1000000;
    $units = ceil($excess / 100000);
    // Need to Make this 37 dynamic
    $fees = $units * $volume;
    }else{
        $fees = 0;
    }

$order->update_meta_data('annual_revenue', $revenue);

$fee = new WC_Order_Item_Fee();
$fee->set_name('Revenue Adjustment'); 
$fee->set_amount($fees);        
$fee->set_total($fees);         
$order->add_item($fee);
$order->calculate_totals();
$order->update_status('completed', 'Auto completed via custom flow');   
$order->save();


$obj    = new SF_108Connector_AdminSettings();
$result = $obj->sf_export_post_sync(array($order->get_id()), 'shop_order',true);

$context['order_id'] = $order->get_id();
$context['order_html'] = '<p>Order #' . $order->get_id() . '</p>';

        wp_send_json_success([
        'next' => 'finalize',
        'context' => $context
        ]);
        break;

        case 'finalize':

            wp_send_json_success([
                'context' => $context
            ]);
        break;

        default:
            wp_send_json_error([ 'error' => 'Invalid step' ]);
    }
}






add_action( 'gform_enqueue_scripts', function( $form ) {
     
    $map = get_option( 'gf_Member_Event_form_mapping', [] );
       
    $individual_form_id = (int) ( $map['individual_membership_ispa'] ?? 0 );
    $corporate_form_id  = (int) ( $map['corporate_membership_ispa'] ?? 0 );

    $current_form_id = (int) $form['id'];
    if ( $current_form_id !== $individual_form_id && $current_form_id !== $corporate_form_id ) {
        return;
    }

    wp_enqueue_script( 'jquery' );

    wp_add_inline_script( 'jquery', "

    function initISPAMemberType(formId){

        var memberType   = jQuery('.member_type select');
        var revenueField = jQuery('.annual_revenue input');

        if (!memberType.length) return;

        memberType.off('change.ispa').on('change.ispa', function(){
            
            var productId = jQuery(this).val();
            var revenue   = revenueField.val();
            console.log('Revenue is : '+revenue);
            if (!productId) return;

            jQuery.post(ajaxurl, {
                action: 'ispa_add_product_by_id',
                product_id: productId,
                revenue: revenue
            }, function(response){

                if (response.success) {
                    jQuery(document.body).trigger('wc_fragment_refresh');
                }

            });

        });

        revenueField.off('input.ispa').on('input.ispa', function(){

            var productId = memberType.val();
            var revenue   = jQuery(this).val();

            if (!productId) return;

            jQuery.post(ajaxurl, {
                action: 'ispa_add_product_by_id',
                product_id: productId,
                revenue: revenue
            }, function(response){

                if (response.success) {
                    jQuery(document.body).trigger('wc_fragment_refresh');
                }

            });

        });

    }

    jQuery(document).on('gform_post_render', function(event, formId, currentPage){

        // ✅ Works for BOTH forms
        if (formId != {$current_form_id}) return;

        if (currentPage == 1) {
            initISPAMemberType(formId);
        }

    });

    " );

}, 10, 1 );




// add_action('wp_ajax_ispa_add_product_by_id', 'ispa_add_product_by_id');
// add_action('wp_ajax_nopriv_ispa_add_product_by_id', 'ispa_add_product_by_id');


// function ispa_add_product_by_id() {

//     if ( ! function_exists('WC') ) {
//         wp_send_json_error(['message' => 'WooCommerce not loaded']);
//     }

//     $product_id = intval($_POST['product_id'] ?? 0);
//     $revenue    = floatval($_POST['revenue'] ?? 0);

//     if ( ! $product_id ) {
//         wp_send_json_error(['message' => 'Invalid product ID']);
//     }

//     if ( WC()->cart === null ) {
//         wc_load_cart();
//     }

//     // Save revenue in session
//     WC()->session->set('ispa_revenue', $revenue);

//     WC()->cart->empty_cart();
//     WC()->cart->add_to_cart($product_id);
//     WC()->cart->calculate_totals();

//     wp_send_json_success([
//         'product_id' => $product_id
//     ]);
// }

function ispa_add_product_by_id() {

    if ( ! function_exists('WC') ) {
        wp_send_json_error(['message' => 'WooCommerce not loaded']);
    }

    $product_id = intval($_POST['product_id'] ?? 0);
    // Revenue in millions
    $revenue = floatval($_POST['revenue'] ?? 0);

    if ( ! $product_id ) {
        wp_send_json_error(['message' => 'Invalid product ID']);
    }

    if ( WC()->cart === null ) {
        wc_load_cart();
    }
    $price = ispa_calculate_membership_fee(
        $revenue,
        $product_id
    );

    // Save in session
    WC()->session->set('ispa_dynamic_price', $price);
    WC()->session->set('ispa_revenue', $revenue);

    // Empty cart and add product
    WC()->cart->empty_cart();

    WC()->cart->add_to_cart(
        $product_id,
        1,
        0,
        [],
        [
            'ispa_custom_price' => $price,
            'unique_key' => md5(microtime())
        ]
    );

    WC()->cart->calculate_totals();

    wp_send_json_success([
        'product_id' => $product_id,
        'price'      => $price
    ]);
}

add_action('wp_ajax_ispa_add_product_by_id', 'ispa_add_product_by_id');
add_action('wp_ajax_nopriv_ispa_add_product_by_id', 'ispa_add_product_by_id');

function ispa_calculate_membership_fee($revenue , $product_id ) {
    $product = wc_get_product( $product_id );
    $product_name = $product
    ? $product->get_name()
    : '';
    $fee = 0;
    $revenue = $revenue / 1000000;       

    // SERVICE PROVIDER AFFILIATE
    if (
        stripos(
            $product_name,
            'Service Provider Affiliate'
        ) !== false
    ) {

        if ( $revenue >= 50.1 ) {

            $fee = 8385;

        } elseif ( $revenue >= 25.1 ) {

            $fee = 6415;

        } elseif ( $revenue >= 15.1 ) {

            $fee = 5410;

        } elseif ( $revenue >= 5.1 ) {

            $fee = 3445;

        } else {

            $fee = 2685;
        }

        return round( $fee, 2 );
    }
    if ($revenue >= 200.1 && $revenue <= 225.0) {
        $fee = 28850 + (($revenue - 200) * 1000000 * 0.000097);
    } elseif ($revenue >= 175.1 && $revenue <= 200.0) {
        $fee = 26290 + (($revenue - 175) * 1000000 * 0.000097);
    } elseif ($revenue >= 150.1 && $revenue <= 175.0) {
        $fee = 22535 + (($revenue - 150) * 1000000 * 0.00014);
    } elseif ($revenue >= 125.1 && $revenue <= 150.0) {
        $fee = 20635 + (($revenue - 125) * 1000000 * 0.00015);
    } elseif ($revenue >= 45.1 && $revenue <= 50.0) {
        $fee = 10810;
    } elseif ($revenue >= 40.1 && $revenue <= 45.0) {
        $fee = 10540;
    } elseif ($revenue >= 35.1 && $revenue <= 40.0) {
        $fee = 10230;
    } elseif ($revenue >= 30.1 && $revenue <= 35.0) {
        $fee = 9925;
    } elseif ($revenue >= 25.1 && $revenue <= 30.0) {
        $fee = 9350;
    } elseif ($revenue >= 20.1 && $revenue <= 25.0) {
        $fee = 8730;
    } elseif ($revenue >= 15.1 && $revenue <= 20.0) {
        $fee = 7910;
    } elseif ($revenue >= 10.1 && $revenue <= 15.0) {
        $fee = 7080;
    } elseif ($revenue >= 7.6 && $revenue <= 10.0) {
        $fee = 6165;
    } elseif ($revenue >= 5.1 && $revenue <= 7.5) {
        $fee = 5250;
    } elseif ($revenue >= 2.6 && $revenue <= 5.0) {
        $fee = 4265;
    } else {
        $fee = 3270;
    }
    return round($fee, 2);
}







add_action('woocommerce_cart_calculate_fees', function($cart){

    if ( is_admin() && ! defined('DOING_AJAX') ) return;

    if ( ! WC()->session ) return;

    $revenue = floatval( WC()->session->get('ispa_revenue') );

    if ( $revenue <= 0 ) return;
    $cart = WC()->cart; 
    // 🛒 Get first cart item
    $cart_items = $cart->get_cart();
    $first_item = reset($cart_items);

    if ( ! $first_item ) return;

    $product = $first_item['data'];

    // 📦 Get meta
    $per_unit_fee = $product->get_meta('sales_volume_fee');

    if ( $per_unit_fee === '' && $product->get_parent_id() ) {
        $per_unit_fee = get_post_meta($product->get_parent_id(), 'sales_volume_fee', true);
    }

    $per_unit_fee = floatval($per_unit_fee);
      
    $fee = 0;

    // ✅ CASE 1: Meta fee exists (> 0)
    if ( $per_unit_fee > 0 ) {

        if ( $revenue <= 1000000 ) return;

        $excess = $revenue - 1000000;
        $units  = ceil($excess / 100000);

        $fee = $units * $per_unit_fee;

    } 
    // ✅ CASE 2: Meta is 0 → Apply slab logic
    else {

        $product_name = $product->get_name();

        // 🎯 Apply for BOTH products
        if ( in_array($product_name, ['Sleep Accessories', 'Affiliates']) ) {

            if ( $revenue < 1000000 ) {
                $fee = 0;
            } elseif ( $revenue <= 5000000 ) {
                $fee = 5000;
            } elseif ( $revenue <= 10000000 ) {
                $fee = 7500;
            } else {
                $fee = 15000;
            }

        } else {
            return; // no fee for other products
        }
    }

    // 🚫 If fee is 0 → don't add
    if ( $fee <= 0 ) {
        return;
    }

    // 🚫 Prevent duplicate
    foreach ($cart->get_fees() as $existing_fee) {
        if ($existing_fee->name === 'Fee based on the sales volume') {
            return;
        }
    }
     
    $cart->add_fee('Fee based on the sales volume', $fee, false);

});























function ispa_get_or_create_user_from_entry( $entry ) {

    $form = GFAPI::get_form( $entry['form_id'] );

    if ( empty( $form['fields'] ) ) {
        return 0;
    }

    $email      = '';
    $first_name = '';
    $last_name  = '';

    foreach ( $form['fields'] as $field ) {

        // ✅ Detect email field
        if ( $field->type === 'email' && empty($email) ) {

            $email = sanitize_email( rgar( $entry, $field->id ) );
        }

        // ✅ Detect name field (GF "name" field)
        if ( $field->type === 'name' ) {

            // GF stores name in sub-inputs
            // 3 = first name, 6 = last name (standard GF structure)
            $first_name = rgar( $entry, $field->id . '.3' );
            $last_name  = rgar( $entry, $field->id . '.6' );
        }
    }

    if ( empty( $email ) ) {
        return 0;
    }

    // 🔍 Check if user exists
    $user = get_user_by( 'email', $email );

    if ( $user ) {

        // Optional: update name if missing
        if ( $first_name ) {
            update_user_meta( $user->ID, 'first_name', $first_name );
        }

        if ( $last_name ) {
            update_user_meta( $user->ID, 'last_name', $last_name );
        }

        return $user->ID;
    }


    $username = sanitize_user( current( explode('@', $email) ), true );

    if ( username_exists( $username ) ) {
        $username .= '_' . time();
    }

    $password = wp_generate_password();

    $user_id = wp_create_user( $username, $password, $email );

    if ( is_wp_error( $user_id ) ) {
        return 0;
    }

    // ✅ Set user data
    wp_update_user([
        'ID'         => $user_id,
        'role'       => 'subscriber',
        'first_name' => $first_name,
        'last_name'  => $last_name
    ]);

    return $user_id;
}













// Event Registration Pipeline 

add_action( 'wp_ajax_gf_event_pipeline_run_step', 'gf_event_pipeline_run_step' );
add_action( 'wp_ajax_nopriv_gf_event_pipeline_run_step', 'gf_event_pipeline_run_step' );


function gf_handle_event_product_payment( $entry, $action ) {

$form_id = rgar($entry, 'form_id');
add_filter( 'gform_confirmation_' . $form_id, function ( $confirmation ) use ( $entry,$action ) {


$entry_id = (int) rgar( $entry, 'id' );

$charge_id = sf_fetch_charge_id($entry,$action);

$confirmation .= '<style>
#gf-prod-pipeline{margin:30px auto;text-align:center}
#gf-prod-status{margin-top:10px;font-size:15px}
</style>

<div id="gf-prod-pipeline">
  <img src="' . esc_url( includes_url( 'images/spinner.gif' ) ) . '">
  <div id="gf-prod-status">Processing order…</div>
</div>

<script>
(function(){

if (window.self !== window.top) {
    console.log("Running inside iframe → skipping pipeline");
    return;
}

if (window.gfPipelineRunning) return;
window.gfPipelineRunning = true;

var entryId = "' . $entry_id . '";
var chargeId = "'.$charge_id.'";
var ajaxUrl = "' . esc_url( admin_url( 'admin-ajax.php' ) ) . '";

var STEP_MESSAGES={
 init:"Initializing…",
 resolve_user:"Preparing account…",
 sync_salesforce:"Syncing customer…",
 create_order:"Creating order…",
 finalize:"Finalizing purchase…"
};

function runStep(step,context){

 document.getElementById("gf-prod-status").textContent =
  STEP_MESSAGES[step] || "Processing…";

 fetch(ajaxUrl,{
   method:"POST",
   headers:{"Content-Type":"application/x-www-form-urlencoded"},
   body:new URLSearchParams({
     action:"gf_event_pipeline_run_step",
     step:step,
     context:JSON.stringify(context||{})
   })
 })
 .then(r=>r.json())
 .then(json=>{

if (!json.success) {
    console.log(json);

    let errorMsg = (json.data && json.data.error) 
        ? json.data.error 
        : "Something went wrong";

    document.getElementById("gf-prod-status").textContent = errorMsg;
    return;
}

   if(json.data.next){
   console.log(json.data.next);
     runStep(json.data.next,json.data.context||{});
   }else{

     var ctx=json.data.context||{};
     var box=document.getElementById("gf-prod-pipeline");

     box.innerHTML=`
      <div class="thanks_message">Purchase Completed</div>
      <div class="sf108-order-html">
        ${ctx.order_html||"<p>No order details.</p>"}
      </div>
      <div style="margin-top:40px">
        <a href="/dashboard" class="visit_dashboard">
          Visit Dashboard
        </a>
      </div>`;
   }

 });

}

runStep("init",{entry_id:entryId,chargeId:chargeId});

})();
</script>';

        return $confirmation;
    });
}




function gf_event_pipeline_run_step() {

    $step    = sanitize_text_field( $_POST['step'] ?? '' );
    $context = json_decode( stripslashes( $_POST['context'] ?? '{}' ), true );

        if ( $step === 'init' && ! empty( $context['entry_id'] ) ) {

        $entry_id = (int) $context['entry_id'];

if ( gform_get_meta( $entry_id, '_product_pipeline_locked' ) ) {

    // Instead of error → continue pipeline
    wp_send_json_success([
        'next' => 'resolve_user',
        'context' => $context
    ]);
}

        gform_update_meta( $entry_id, '_product_pipeline_locked', time() );
    }


    switch ( $step ) {

        case 'init':
            
            wp_send_json_success([
                'next'=>'resolve_user',
                'context'=>$context
            ]);


        case 'resolve_user':

            $entry = GFAPI::get_entry( (int) $context['entry_id'] );
            if ( is_wp_error( $entry ) ) {
                wp_send_json_error([ 'error' => 'Invalid entry' ]);
            }

            $form = GFAPI::get_form( rgar( $entry, 'form_id' ) );
            $customer = gf_extract_basic_customer_data( $entry, $form );

            if ( empty( $customer['email'] ) ) {
                wp_send_json_error([ 'error' => 'Email missing' ]);
            }

            $user_id = gf_get_or_create_wp_user($customer['email'],$customer['first_name'],$customer['last_name'],'subscriber');

    if ( $user_id && ! is_wp_error( $user_id ) ) {

    // Billing name
    update_user_meta( $user_id, 'billing_first_name', $customer['first_name'] );
    update_user_meta( $user_id, 'billing_last_name',  $customer['last_name'] );

    // Billing phone
    if ( ! empty( $customer['phone'] ) ) {
        update_user_meta( $user_id, 'billing_phone', $customer['phone'] );
    }

    // Billing address
    if ( ! empty( $customer['address']['address_1'] ) ) {
        update_user_meta( $user_id, 'billing_address_1', $customer['address']['address_1'] );
        update_user_meta( $user_id, 'billing_address_2', $customer['address']['address_2'] );
        update_user_meta( $user_id, 'billing_city',      $customer['address']['city'] );
        update_user_meta( $user_id, 'billing_state',     $customer['address']['state'] );
        update_user_meta( $user_id, 'billing_postcode',  $customer['address']['postcode'] );
        update_user_meta( $user_id, 'billing_country',   $customer['address']['country'] );
    }
}

            if ( is_wp_error( $user_id ) ) {
                wp_send_json_error([ 'error' => $user_id->get_error_message() ]);
            }

            // SAFE membership handling
            $context['user_id']  = (int) $user_id;
            $context['customer'] = $customer;

            wp_send_json_success([
                'step'    => 'resolve_user',
                'next'    => 'sync_salesforce',
                'context' => $context,
            ]);



        case 'sync_salesforce':

            $obj=new SF_108Connector_AdminSettings();
            $obj->sf_export_user_sync([$context['user_id']],true);

            $context['sf_account_id']=get_user_meta(
                $context['user_id'],
                'sf_object_id',
                true
            );

            wp_send_json_success([
                'next'=>'create_order',
                'context'=>$context
            ]);

        case 'create_order':
            $entry_id = (int)$context['entry_id'];

if (gform_get_meta($entry_id, '_order_created')) {

    wp_send_json_success([
        'next'    => null,
        'context' => [
            'order_html' => '<p>Order already processed.</p>'
        ]
    ]);
}
            dbg("Creating Order");
            $entry=GFAPI::get_entry((int)$context['entry_id']);
            $form=GFAPI::get_form(rgar($entry,'form_id'));
            $products=GFCommon::get_product_fields($form,$entry);
            $p=reset($products['products']);
            
            $query = "SELECT IS_Event__c FROM IS_Product__c WHERE IS_WC_Product_ID__c = '".$p['id']."'";
            $responses = SF_APIConnector::getSQueryObject( $query, 1 );
            $context['event_sf_id'] = $responses->IS_Event__c;
            $product=wc_get_product((int)$p['id']);
            $txn = $context['chargeId'];





$orders = wc_get_orders([
    'limit' => 1,
    'meta_key' => '_transaction_id',
    'meta_value' => $txn,
]);

if (!empty($orders)) {
    $order = $orders[0]; 
} else {
    dbg("Order 8");
    $order = wc_create_order([
        'customer_id' => $context['user_id']
    ]);
}




            $order->add_product($product,1);
            $order->calculate_totals();

            
            if($txn){
             $order->payment_complete($context['chargeId']);
             $order->update_status('completed');
            }
            $membership_start = $current_year . '-07-01';
            $membership_end = ($current_year + 1). '-06-30';
            $product_name = $product->get_name();
            $order->update_meta_data( 'membership_start_date', $membership_start );
            $order->update_meta_data( 'membership_end_date', $membership_end );
            $order->update_meta_data( 'membership_type', $product_name );
            $order->update_meta_data( 'sf_organization_id',$context['sf_account_id'] );
            $order->update_meta_data('sf_account_id',$context['sf_account_id']);

            $order->update_meta_data('sf_date_created',current_time( 'Y-m-d' ));
            
            $order->save();
            
            $obj    = new SF_108Connector_AdminSettings();
            $result = $obj->sf_export_post_sync(array($order->get_id()), 'shop_order',true);
            $context['order_id']= $order->get_id();

            sf108_create_form_submission($context['user_id'],$context['order_id'],'012au000000eRvxAAE', $result , $context['sf_account_id'] );

            gform_update_meta($entry_id, '_order_created', 1);
            wp_send_json_success([
                'next'=>'finalize',
                'context'=>$context
            ]);

        case 'finalize':
            // Creating Registration
        $order_sf = get_post_meta($context['order_id'],'sf_object_id',true);
            //$context['event_sf_id']
        //order_id    
        $payload = [
        'IS_Registration_Type__c'   => 'Attendee-Member',
        'IS_Event__c'    => $context['event_sf_id'],
        'IS_Status__c' => 'Active',
        'IS_Account__c'   => $context['sf_account_id'],
        'IS_Order__c' => $order_sf,
       
    ];

    $records = [[
        'attributes' => [ 'type' => 'IS_Registration__c' ],
    ] + $payload];

    if(!empty($context['event_sf_id']) || !empty($order_sf)){
    $response = SF_APIConnector::postCURLObject(
        json_encode([
            'allOrNone' => false,
            'records'   => $records,
        ]),
        'POST'
    );     
    }


            $context['order_html'] = fetch_sf_order_details((int)$context['order_id']);
            wp_set_current_user($context['user_id']);
            wp_set_auth_cookie($context['user_id']);

            wp_send_json_success([
                'next'=>null,
                'context'=> $context
            ]);
    }

    wp_send_json_error(['error'=>'Invalid step']);
}








//add_action('wp_enqueue_scripts','add_google_addressestogravityform');

function add_google_addressestogravityform () {

    wp_enqueue_script(
        'google-places-api',
        'https://maps.googleapis.com/maps/api/js?key=AIzaSyDelYTryARYLZwugNlQXIelWlGMu_BI4lo&libraries=places',
        [],
        null,
        true
    );

    $inline_js = <<<JS
jQuery(document).ready(function ($) {

    function initAutocomplete() {

        $('input[id^="input_"][id$="_1"]').each(function () {

            var streetField = $(this);

            if (streetField.data('autocomplete-initialized')) return;

            var baseId = streetField.attr('id').replace(/_1$/, '');

            var cityField    = $('#' + baseId + '_3');
            var stateField   = $('#' + baseId + '_4');
            var zipField     = $('#' + baseId + '_5');
            var countryField = $('#' + baseId + '_6');

            var autocomplete = new google.maps.places.Autocomplete(this, {
                types: ['geocode']
                // componentRestrictions: { country: "in" } // optional
            });

            streetField.data('autocomplete-initialized', true);

            autocomplete.addListener('place_changed', function () {

                var place = autocomplete.getPlace();
                if (!place.address_components) return;

              let street = '';
let city = '';
let state = '';
let zip = '';
let country = '';

let hasStreet = false;

place.address_components.forEach(function (component) {

    var types = component.types;

    if (types.includes('street_number')) {
        street = component.long_name + ' ' + street;
        hasStreet = true;
    }

    if (types.includes('route')) {
        street += component.long_name;
        hasStreet = true;
    }

    // 🔥 Better city handling
    if (
        types.includes('locality') ||
        types.includes('sublocality') ||
        types.includes('sublocality_level_1') ||
        types.includes('administrative_area_level_2')
    ) {
        city = component.long_name;
    }

    if (types.includes('administrative_area_level_1')) {
        state = component.long_name;
    }

    if (types.includes('postal_code')) {
        zip = component.long_name;
    }

    if (types.includes('country')) {
        country = component.long_name;
    }
});

// 🔥 Fallback if street is missing
if (!hasStreet && place.formatted_address) {
    street = place.formatted_address.split(',')[0];
}

            if (street) streetField.val(street).trigger('change');
if (city) cityField.val(city).trigger('change');
if (state) stateField.val(state).trigger('change');
if (zip) zipField.val(zip).trigger('change');

if (country) {
    if (countryField.is('select')) {
        countryField.val(country).trigger('change');
    } else {
        countryField.val(country).trigger('change');
    }
}
            });

        });
    }

    initAutocomplete();

    jQuery(document).on('gform_post_render', function () {
        initAutocomplete();
    });

});
JS;

    wp_add_inline_script('google-places-api', $inline_js);
}




function gf_get_user_organization_data( $user_id ) {


    if ( empty($user_id) ) {
        return null;
    }

    /* ========================
     * 🔐 GET SF ACCOUNT ID FROM USER META
     * ======================== */
    $sf_account_id = get_user_meta( $user_id, 'sf_object_id', true );
    
    if ( empty($sf_account_id) ) {
        return null;
    }


    $query = "SELECT FIELDS(ALL) FROM IS_Affiliation__c WHERE IS_Account__c = '".$sf_account_id."'";

    $responses = SF_APIConnector::getSQueryObject($query, 1);



    $query2 = "SELECT Name FROM Account WHERE id = '".$responses->IS_Parent_Account__c."'";
    $responseName = SF_APIConnector::getSQueryObject($query2, 1);
    

    // Mock response for now
    $response = [
        (object)[
            'Name' => $responseName->Name,
            'Id'   => $responses->IS_Parent_Account__c
        ]
    ];

    if ( empty($response) ) {
        return null;
    }

    return [
        'name' => $responseName->Name ?? '',
        'id'   => $responses->IS_Parent_Account__c ?? ''
    ];
}





add_action('init',function(){


if(isset($_GET['order'])){


    $payload = [
        'Id'   => 'a0Qau00000BV8r0EAD',
        'IS_Account__c' => '001au00000KEzXyAAL',
       
    ];
    
    $records[] = ['attributes' => [ 'type' => 'IS_Order__c' ],] + $payload;
    $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]), 'PATCH' );

            print_r($response);
}



});