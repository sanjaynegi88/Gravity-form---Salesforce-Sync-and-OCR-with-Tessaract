<?php

//ISPA Corporate Membership Pipeline


function gf_handle_corporate_membership_payment_ispa( $entry, $action ) {

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
                action: "gf_pipeline_run_step_ispa",
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


    console.log("Starting Pipeline");
    runStep("init", { entry_id: entryId });

})(jQuery);
</script>';

        return $confirmation;
    });
}



















// Creating Main Account 


function gf_pipeline_get_or_create_main_account_ispa( array $customer ) {

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

    // No Need for Payload Just Create Account and Sync
    /*
    $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]),'POST');
    // Main Account Created
    if ( empty( $response[0]->id ) ) {
        return new WP_Error(
            'sf_create_failed',
            'Failed to create Salesforce account'
        );
    }
    */

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





















// Corporate Ajax Endpoint for ISPA

add_action( 'wp_ajax_gf_pipeline_run_step_ispa', 'gf_pipeline_run_step_ispa' );
add_action( 'wp_ajax_nopriv_gf_pipeline_run_step_ispa', 'gf_pipeline_run_step_ispa' );

function gf_pipeline_run_step_ispa() {


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

    if ( empty( $context['entry_id'] ) ) {
        wp_send_json_error(['error' => 'Missing entry_id']);
    }

    if ( is_user_logged_in() ) {

        $user_id = get_current_user_id();

        $membership_end = get_user_meta($user_id, 'membership_end_date', true);
        $sf_account_id  = get_user_meta($user_id, 'sf_object_id', true);
        $company_id     = get_user_meta($user_id, 'organization', true);

        if ( !empty($membership_end) && strtotime($membership_end) > time() && !empty($sf_account_id) ) {

            // ✅ Context injection
            $context['user_id'] = $user_id;
            $context['main_sf_account_id'] = $sf_account_id;
            $context['sf_company_account_id'] = $company_id;
            $context['company_name'] = get_user_meta($user_id, 'organisation_name', true);

            // ✅ Update membership locally
            $current_year = (int) current_time('Y');
            $membership_start = $current_year . '-07-01';
            $membership_end   = ($current_year + 1). '-06-30';
            $start_date       = current_time('Y-m-d');
            $end_date   = date('Y-m-d', strtotime($start_date . ' +365 days'));
            update_user_meta($user_id, 'membership_start_date', $start_date);
            update_user_meta($user_id, 'membership_end_date', $end_date);
            update_user_meta($user_id, 'membership_status', 'Pending Activation');

            // ✅ Sync user to SF
            $obj = new SF_108Connector_AdminSettings();
            $obj->sf_export_user_sync([$user_id], 'account', true);

            // ✅ Update company in SF
            if ( !empty($company_id) ) {

                $account_update = []; // 🔥 IMPORTANT

                $account_update[] = [
                    'attributes' => [ 'type' => 'Account' ],
                    'Id' => $company_id,
                    'IS_Membership_Start_Date__c' => $start_date,
                    'IS_Membership_End_Date__c'   => $end_date,
                    'IS_Status__c'                => 'Active'
                ];

                $payload = [
                    'allOrNone' => false,
                    'records'   => $account_update,
                ];

                SF_APIConnector::postCURLObject(json_encode($payload), 'PATCH');
            }

            // ✅ Exit early (VERY IMPORTANT)
            wp_send_json_success([
                'step'    => 'init',
                'next'    => 'create_members',
                'context' => $context,
                'message' => 'Existing member detected → skipping company + main account'
            ]);
        }
    }

    // ✅ Default flow
    wp_send_json_success([
        'step'    => 'init',
        'next'    => 'create_sf_company',
        'context' => $context,
        'message' => 'Corporate pipeline initialized'
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
    $sales_volume = gf_get_sales_volume_from_entry( $form, $entry );

    if ( empty( $company_name ) ) {
        wp_send_json_error([
            'error' => 'Company name not found'
        ]);
    }
    $context['company_name'] = $company_name;
    // Uncomment to send company Account to SF
    $sf_account_id = gf_create_company_account_ispa( $company_name,$sales_volume );




    if ( empty( $sf_account_id ) ) {
        wp_send_json_error(['error' => 'Failed to create Salesforce company']);
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
// Here Main Account Need to be created from Outside 


    if ( empty( $context['entry_id'] ) ) {
        wp_send_json_error([ 'error' => 'Missing entry_id' ]);
    }

 

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

    // Running Upto Here
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

            // SAFE membership handling
            $is_renewal = ! empty( get_user_meta( $user_id, 'membership_end_date', true ) );
            $dates = gf_resolve_membership_dates( $user_id, $is_renewal );
            $current_year = (int) current_time('Y');
            $membership_start = $current_year . '-07-01';
            $membership_end = ($current_year + 1). '-06-30';
            update_user_meta( $user_id, 'membership_start_date', $membership_start );
            update_user_meta( $user_id, 'membership_end_date',   $membership_end );
            update_user_meta( $user_id, 'membership_type',       $membership_type );
            update_user_meta( $user_id, 'membership_status',     'Pending Activation' );
            update_user_meta( $user_id, 'organization',   $context['sf_company_account_id'] );
            update_user_meta( $user_id, 'organisation_name',    $context['company_name'] );
            update_user_meta( $user_id, 'account_type',     'main' );
            update_user_meta( $user_id, 'join_date',  current_time( 'Y-m-d' ) );
            update_user_meta( $user_id, 'joined_date',  current_time( 'Y-m-d' ) );
            $context['user_id']  = (int) $user_id;
            $context['customer'] = $customer;
            $context['membership_type'] = $membership_type;
            $context['membership_end_date'] = $dates['end'];
            $obj    = new SF_108Connector_AdminSettings();
            $result = $obj->sf_export_user_sync(array($user_id), 'account',true);    
            $context['main_sf_account_id'] = $result;
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
    $members = gf_extract_all_corporate_members_from_widget_ispa( $entry );
    dbg($members);
    if ( is_wp_error( $members ) ) {
        wp_send_json_error([ 'error' => $members->get_error_message() ]);
    }


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




        if ( empty( $member['email'] ) ) {
            continue;
        }
   
        // Here it registers Members 
        $result = gf_pipeline_get_or_create_wp_member_ispa( $member , $context['sf_company_account_id']);
        
        dbg($result);

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


    foreach ( $members as $member ) {



        $member_id = get_user_meta( get_user_by( 'email', $member['email'] )->ID, 'sf_object_id', true );

         if ( empty($member_id ) ) {
            continue;
        }

        if($member['roles']['billing'] == 1){
        $record = [
            'attributes' => [ 'type' => 'IS_Affiliation__c' ],
            'IS_Parent_Account__c'    => $company_sf_id,
            'IS_Account__c'     => $member_id,
            'RecordTypeId' => '012au000000WQfKAAW',
            'IS_Status__c' => 'Active',
            'IS_Billing_Contact__c' => true

        ];
        $context['billing_account_id'] = $member['wp_user_id'];
        }elseif($member['roles']['owner'] == 1){
            $record = [
            'attributes' => [ 'type' => 'IS_Affiliation__c' ],
            'IS_Parent_Account__c'    => $company_sf_id,
            'IS_Account__c'     => $member_id,
            'IS_Status__c' => 'Active',
            'RecordTypeId' => '012au000000WQfKAAW',
            'IS_Owner__c' => true

        ];

        }else{
            $record = [
            'attributes' => [ 'type' => 'IS_Affiliation__c' ],
            'IS_Parent_Account__c'    => $company_sf_id,
            'IS_Account__c'     => $member_id,
            'IS_Status__c' => 'Active',
            'RecordTypeId' => '012au000000WQfKAAW',
        ];

        }

       $records[] = $record;    
    
    //Loop Ends here Gotta add main account as well   

    }

    //Adding Main Record Here

    $records[] = [
    'attributes' => [ 'type' => 'IS_Affiliation__c' ],
    'IS_Parent_Account__c' => $company_sf_id,
    'IS_Account__c' => $main_sf_id, 
    'RecordTypeId' => '012au000000WQfKAAW',
    'IS_Status__c' => 'Active',
    'IS_Primary_Contact__c' => true // optional
    ];



    if ( empty( $records ) ) {
        wp_send_json_success([
            'step'    => 'create_affiliations',
            'next'    => 'sync_order',
            'context' => $context,
            'message' => 'No affiliations to create',
        ]);
    }

    dbg($records);

    $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]),'POST');

    wp_send_json_success([
        'step'    => 'create_affiliations',
        'next'    => 'sync_order',
        'context' => $context,
        'message' => count( $records ) . ' affiliation(s) submitted',
    ]);




case 'sync_order':



    if (empty( $context['entry_id'] ) || empty( $context['user_id'] )) {

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

    $main_wp_user_id = (int) $context['user_id'];

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


  


      $cart_items = WC()->cart->get_cart();

        if ( ! empty( $cart_items ) ) {

            $first_item = reset( $cart_items ); // ✅ safe, does NOT modify cart
            $product    = $first_item['data'];
                $price = 0;

                if (isset($first_item['ispa_custom_price'])) {

                    $price = (float) $first_item['ispa_custom_price'];

                } else {

                    $price = (float) $product->get_price();
                }
            if ( $product ) {
                $membership_type = $product->get_name();
            }
        }

    // Use FIRST product only (corporate rule)


    $product_id = (int) $product->get_id();
    $quantity   = 1; // forced to 1 as requested
    $fees = WC()->cart->get_fees();

    if ( ! $product_id ) {
        wp_send_json_error([
            'error' => 'Invalid product resolved from Gravity Forms'
        ]);
    }


    $order = wc_create_order([
        'customer_id' => $main_wp_user_id,
    ]);



    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        wp_send_json_error([
            'error' => 'Invalid WooCommerce product'
        ]);
    }

    $current_year = (int) current_time('Y');

    $membership_start =
        $current_year . '-07-01';

    $membership_end =
        ($current_year + 1)
        . '-06-30';

    $product_name =
        $product->get_name();
    $item = new WC_Order_Item_Product();
    $item->set_product($product);
    $item->set_quantity($quantity);
    $item->set_subtotal($price);
    $item->set_total($price);
    $order->add_item($item);
    $order->update_meta_data(
        'membership_start_date',
        $membership_start
    );
    $order->update_meta_data(
        'membership_end_date',
        $membership_end
    );
    $order->update_meta_data(
        'membership_type',
        $product_name
    );
    $order->calculate_totals(false);
    $txn_id =
    rgar($entry,'transaction_id');
    if ($txn_id) {
        $order->set_payment_method(
            'stripe'
        );
        $order->payment_complete(
            $txn_id
        );
        $order->update_status(
            'completed',
            'Corporate membership payment completed'
        );
    }
    $company_sf_id = $context['sf_company_account_id'];
    $order->update_meta_data( 'sf_account_id', $sf_account_id );
    $order->update_meta_data( 'sf_organization_id', $company_sf_id );
    $revenue = floatval( WC()->session->get('ispa_revenue', 0) );
    $order->update_meta_data( 'annual_revenue', $revenue );
    $order->save();
    $obj    = new SF_108Connector_AdminSettings();
    $result = $obj->sf_export_post_sync(array($order->get_id()), 'shop_order',true);


    $context['order_sf_id'] = $result;







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
    $order_sf_id = get_post_meta($context['order_id'],'sf_object_id',true);    
    $billing_account_id = get_user_meta( $context['billing_account_id'], 'sf_object_id', true );
    $order_date = current_time( 'Y-m-d' );
    $revenue = floatval( WC()->session->get('ispa_revenue') );
    // $payload = [
    //     // 'Id'   => $order_sf_id,
    //     // 'IS_Order_Status__c' => 'Completed',
    //     'IS_Account__c' => $context['sf_company_account_id'],
    //     'IS_Billing_Contact__c' => $billing_account_id,
    //     'IS_Order_Date__c' => $order_date,
    //     'IS_Sales_Volume__c' => $revenue,
       
    // ];
    
    // $records[] = ['attributes' => [ 'type' => 'IS_Order__c' ],] + $payload;
    // $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]), 'PATCH' );
    $payload = [
        'IS_Account__c' => $context['sf_company_account_id'],
        'IS_Billing_Contact__c' => $billing_account_id,
        'IS_Order_Date__c' => $order_date,
        'IS_Sales_Volume__c' => $revenue,
    ];

    $response = SF_APIConnector::postCURLObject(
        json_encode($payload),
        'PATCH',
        "sobjects/IS_Order__c/{$order_sf_id}"
    );
        

        $order_html = fetch_sf_order_details( $order_id );
        $context['order_html'] = $order_html;
        
        $order = wc_get_order( $order_id );

        if ( $order ) {
        $order_total = $order->get_total(); // numeric value
        }

        $obj    = new SF_108Connector_AdminSettings();
        $result = $obj->sf_export_post_sync(array($order_id), 'shop_order',true);

        do_action(
                'gf_corp_order_created',
                $order_id,
                $context['entry_id'],
                $context['user_id'],
                GFAPI::get_entry( (int) $context['entry_id'] )
        );

    // Adding Transaction if it does not exists

        $query = "SELECT Id FROM IS_Transaction__c WHERE IS_Order__c = '$result->id'";
        $response = SF_APIConnector::getSQueryObject( $query, 1 );


        
    $payload = [
        'id' => $response->id,
        'IS_Order__c' => $order_sf_id,
        'IS_Amount__c' => $order_total
    ];





    $records[] = ['attributes' => [ 'type' => 'IS_Transaction__c' ],] + $payload;

    if(empty($response)){    

    $response = SF_APIConnector::postCURLObject( json_encode(['allOrNone' => false,'records'   => $records,]),'POST');

    }

    // Creating Form Submission

    // sf108_create_form_submission( $context['user_id'],$order_id,'012au000000aNnkAAE',$result->id ,  $context['sf_company_account_id'] );


    wp_set_current_user( $context['user_id'] );
    wp_set_auth_cookie( $context['user_id'] );
    

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




function gf_extract_all_corporate_members_from_widget_ispa( array $entry ) {


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

        // ✅ FILTER: remove members with empty email
        $members = array_values( array_filter( $members, function( $member ) {
            return ! empty( $member['email'] );
        }));

        return $members;
    }

    return [];
}















function gf_pipeline_get_or_create_wp_member_ispa( array $member , $sf_c_account_id ) {

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
    $end_date   = date('Y-m-d', strtotime( $start_date . ' +365 days' ));

    $membership_type = 'Individual by Organization'; // fallback

    if ( function_exists('WC') && WC()->cart ) {

        $cart_items = WC()->cart->get_cart();

        if ( ! empty( $cart_items ) ) {

            $first_item = reset( $cart_items ); // safe
            $product    = $first_item['data'];
            
            if ( $product ) {
                $membership_type = $product->get_name();
            }
        }
    }

    $payload = [
        'FirstName'   => $member['first_name'] ?? '',
        'LastName'    => $member['last_name'],
        'PersonEmail' => $email,
        'IS_Membership_Start_Date__c' => $start_date,
        'IS_Membership_End_Date__c'   => $end_date,
        'IS_Membership_Type__c'       => $membership_type,
        'Phone'                       => $member['phone'],
        'IS_Status__c'               => 'Pending Activation',
        'IS_Primary_Affiliation__c'  => $sf_c_account_id,
        'IS_Joined_Date__c'          => current_time( 'Y-m-d' )
    ];

    $records = [];
    $records[] = [
        'attributes' => [ 'type' => 'Account' ],
    ] + $payload;

    $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]),'POST');
    dbg($response);
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

    $existing = get_user_by( 'email', $email );

    if ( $existing ) {
        $user_id = $existing->ID;
    } else {


        $base_username = sanitize_user( current( explode( '@', $email ) ) );

        if ( empty( $base_username ) ) {
            $base_username = 'user';
        }

        $username = $base_username;
        $counter  = 2;

        while ( username_exists( $username ) ) {
            $username = $base_username . $counter;
            $counter++;
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass'  => wp_generate_password( 12, true ),
            'first_name' => $member['first_name'] ?? '',
            'last_name'  => $member['last_name'] ?? '',
            'role'       => 'subscriber',
        ]);
    }

    if ( is_wp_error( $user_id ) ) {
        return $user_id;
    }

    update_user_meta( $user_id, 'sf_object_id', $sf_account_id );
    update_user_meta( $user_id, 'sf_account_id', $sf_account_id );


    if ( ! empty( $member['phone'] ) ) {
    update_user_meta( $user_id, 'billing_phone', sanitize_text_field($member['phone']) );
    }

    return [
        'wp_user_id'   => (int) $user_id,
        'sf_object_id' => $sf_account_id,
        'created'      => true,
    ];
}















function gf_pipeline_get_or_create_wp_member_billing_ispa( array $member, $entry ) {

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
    'IS_Status__c'                => 'Pending Activation',
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














function gf_pipeline_get_or_create_member_account_ispa( array $member ) {

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
        'IS_Status__c' => 'Pending Activation'
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









function gf_extract_corporate_main_member_from_widget_ispa( $entry ) {

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










function gf_create_company_account_ispa($company_name,$sales_volume){

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
        'IS_Status__c'                => 'Pending Activation',
        'IS_Sales_Volume__c'          => $sales_volume,
        'IS_Joined_Date__c'           => current_time( 'Y-m-d' )

    ];

    $account_update[] = ['attributes' => [ 'type' => 'Account' ],] + $payload;

    if ( ! empty( $account_update ) ) {

    $payload = [
        'allOrNone' => false,
        'records'   => $account_update,
    ];

    $response = SF_APIConnector::postCURLObject(json_encode( $payload ), 'POST');



        return $response[0]->id;    
    }
}