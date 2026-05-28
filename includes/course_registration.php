<?php



function gf_handle_course_registration_payment($entry, $action){

    add_filter( 'gform_confirmation', function( $confirmation ) use ( $entry ) {
        wp_enqueue_script('jquery');
        $entry_id = (int) rgar( $entry, 'id' );

        $confirmation .= '
<style>
#gf-corp-pipeline {
    max-width: 480px;
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
</style>

<div id="gf-corp-pipeline">
    <img id="gf-corp-spinner"
         src="' . esc_url( includes_url( 'images/spinner.gif' ) ) . '"
         alt="Processing…">
    <div id="gf-corp-status">Initializing corporate membership…</div>
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



    function setStatus(step) {

        var STEP_MESSAGES = {
        init: "Initializing Your Order…",
        create_main_account: "Creating Your Account…",
        create_order: "Enrolling Your Profile…",
        create_mycourse: "Adding Modules…",
        create_mymodules: "Finalizing Order…"
    };
        console.log(STEP_MESSAGES[step]);
        $("#gf-corp-status").text(STEP_MESSAGES[step]);
        
        $("#gf-corp-error").remove();
        //var status  = document.getElementById("gf-corp-status");
        //status.textContent = STEP_MESSAGES[step] || "Processing…";
    }

    function fail(message) {
        spinner.style.display = "none";
        status.style.display = "none";
        errorEl.textContent = message || "Something went wrong. Please contact support.";
        errorEl.style.display = "block";
    }

    function success() {
        spinner.style.display = "none";
        status.style.display = "none";
        okEl.textContent = "Course Registration completed successfully.";
        okEl.style.display = "block";
    }

    function runStep(step, context) {

        setStatus(step);
        console.log(step);
        fetch(ajaxUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({
                action: "gf_course_pipeline_run_step",
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
                // ✅ FINAL STEP COMPLETE (sync_order)
                success();
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






add_action( 'wp_ajax_gf_course_pipeline_run_step', 'gf_course_pipeline_run_step' );
add_action( 'wp_ajax_nopriv_gf_course_pipeline_run_step', 'gf_course_pipeline_run_step' );

function gf_course_pipeline_run_step() {

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
                'next'     => 'create_main_account',
                'context'  => $context,
                'message'  => 'Corporate pipeline initialized'
            ]);








case 'create_main_account':

    if ( empty( $context['entry_id'] ) ) {
        wp_send_json_error([ 'error' => 'Missing entry_id' ]);
    }

    $entry = GFAPI::get_entry( (int) $context['entry_id'] );
    if ( is_wp_error( $entry ) ) {
        wp_send_json_error([ 'error' => 'Invalid entry' ]);
    }

    $form = GFAPI::get_form( rgar( $entry, 'form_id' ) );
    if ( empty( $form ) ) {
        wp_send_json_error([ 'error' => 'Invalid form' ]);
    }

    // 🔹 Extract identity from entry
    $identity = gf_extract_basic_identity_from_entry( $entry, $form );

    if (
        empty( $identity['email'] ) ||
        empty( $identity['first_name'] ) ||
        empty( $identity['last_name'] )
    ) {
        wp_send_json_error([
            'error' => 'Email, first name, and last name are required'
        ]);
    }

    $email = $identity['email'];

    // 🔹 Get or create WP user
    $user = get_user_by( 'email', $email );

    if ( $user ) {
        $user_id = $user->ID;
    } else {

        $username = sanitize_user( current( explode( '@', $email ) ) );

        // Ensure unique username
        if ( username_exists( $username ) ) {
            $username .= '_' . wp_generate_password( 4, false );
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => wp_generate_password(),
            'first_name' => $identity['first_name'],
            'last_name'  => $identity['last_name'],
            'role'       => 'subscriber',
        ]);

        $payload = [
        'FirstName'   => $identity['first_name'] ?? '',
        'LastName'    => $identity['last_name'] ?? '',
        'PersonEmail' => $email,
    ];

    $records[] = [
        'attributes' => [ 'type' => 'Account' ],
    ] + $payload;

    $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]),'POST');
    update_user_meta( $user_id, 'sf_object_id', $response[0]->id );
    update_user_meta( $user_id, 'sf_account_id', $response[0]->id );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error([
                'error' => 'Failed to create WordPress user'
            ]);
        }
    }

    // 🔹 Store identity for later Salesforce steps
    $context['main_wp_user_id'] = (int) $user_id;
    $context['customer'] = $identity;
    $context['main_sf_account_id'] = $response[0]->id;

    wp_send_json_success([
        'step'    => 'create_main_account',
        'next'    => 'create_order',
        'context' => $context,
        'message' => 'Main account resolved',
    ]);









case 'create_order':

    if (empty( $context['entry_id'] )){
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
    $context['product_id'] = $product_id;
    if ( ! $product_id ) {
        wp_send_json_error([
            'error' => 'Invalid product resolved from Gravity Forms'
        ]);
    }

  dbg("Order CR");
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



    $paid_amount = (float) rgar( $entry, 'payment_amount' );

    if ( $paid_amount > 0 ) {
    $order->set_total( $paid_amount );
    }

    $order->calculate_totals();

    $order->update_meta_data( 'sf_account_id', $sf_account_id );
    $order->save();


    $obj    = new SF_108Connector_AdminSettings();
    $result = $obj->sf_export_post_sync(array($order->get_id()), 'shop_order',true);
    $context['order_sf_id'] = $result[0]->Id;

    update_option(
        'gf_corp_order_for_entry_' . $context['entry_id'],
        $order->get_id(),
        false
    );



    wp_send_json_success([
        'step'    => 'create_order',
        'next'    => 'create_mycourse',
        'context' => array_merge( $context, ['order_id' => $order->get_id(),]),
        'message' => 'Order created and synced using GFCommon product resolution',
    ]);










    case 'create_mycourse':

        // Creating My Course
        // Search for Course ID first  
        $order_id = $context['order_id'];
        $product_id = $context['product_id'];
        // Synching Order to Salesforce
 
        $usersfid = get_user_meta( $context['main_wp_user_id'], 'sf_account_id', true );;
        $product_sf_id = get_post_meta($product_id,'sf_object_id',true);

        // Fetching Post ID 

        $query = "SELECT IS_Course__c FROM IS_Product__c WHERE Id = '$product_sf_id'";
        $response = SF_APIConnector::getSQueryObject( $query, 1 );
        $course_ID = $response->IS_Course__c;
        $start_date = current_time( 'Y-m-d' );



        
    $payload = [
        'IS_Completion_Status__c' => 'Not Started',
        'IS_Course__c' => $response->IS_Course__c,
        'IS_Account__c' => $usersfid,
        'IS_Enrollment_Date__c' => $start_date,
        'IS_Order__c' => $context['order_sf_id'],
    ];





    $records[] = ['attributes' => [ 'type' => 'IS_My_Courses__c' ],] + $payload;
    $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]),'POST');
    
    $context['mycourseID'] = $response[0]->id;
    $context['courseID'] = $course_ID;    

        wp_send_json_success([
        'step'    => 'create_mycourse',
        'next'    => 'create_mymodules',
        'context' => $context,
        'message' => 'Order created and synced using GFCommon product resolution',
    ]);




    case 'create_mymodules':

    

    $fetchModules = "SELECT id FROM IS_Module__c WHERE IS_Course__c = '".$context['courseID']."'";    

    $responses = SF_APIConnector::getSQueryObject( $fetchModules, 1000 ); 
        



        foreach($responses as $response){

        $payload = [
        'IS_My_Course__c' => $context['mycourseID'],
        'IS_Module__c' => $response->Id,
        'IS_Status__c' => 'Not Started'

    ];

    $records[] = ['attributes' => [ 'type' => 'IS_My_Module__c' ],] + $payload;
    $result = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]),'POST');
    $records = [];    
    }


       wp_send_json_success([
        'step'    => 'create_mymodules',
        'next'    => null,
        'context' => $context,
        'message' => 'Order created and synced using GFCommon product resolution',
    ]);
    





        default:
            wp_send_json_error([
                'error' => 'Invalid pipeline step'
            ]);
    }
}







function gf_extract_basic_identity_from_entry( $entry, $form ) {

    $data = [
        'email'      => '',
        'first_name' => '',
        'last_name'  => '',
    ];

    foreach ( $form['fields'] as $field ) {

        // EMAIL field
        if ( $field->get_input_type() === 'email' ) {
            $data['email'] = sanitize_email(
                rgar( $entry, (string) $field->id )
            );
        }

        // NAME field (GF Name field)
        if ( $field->get_input_type() === 'name' ) {
            $data['first_name'] = sanitize_text_field(
                rgar( $entry, $field->id . '.3' )
            );
            $data['last_name'] = sanitize_text_field(
                rgar( $entry, $field->id . '.6' )
            );
        }
    }

    return $data;
}




add_action('init',function(){

    if(isset($_GET['bss'])){





    }

});