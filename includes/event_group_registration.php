<?php 

// Event Registration Pipeline 


add_action( 'gform_loaded', function () {

add_action( 'template_redirect', 'auto_add_event_product_to_cart' );

function auto_add_event_product_to_cart() {

    // Only run on frontend
    if ( is_admin() || ! function_exists( 'WC' ) ) {
        return;
    }

    // Check if eventID exists in URL
    if ( empty( $_GET['eventID'] ) ) {
        return;
    }

    $product_id = absint( $_GET['eventID'] );

    if ( ! $product_id ) {
        return;
    }

    // Ensure cart is loaded
    if ( ! WC()->cart ) {
        return;
    }

    // 🔍 Check if this product already exists in cart
    foreach ( WC()->cart->get_cart() as $cart_item ) {

        if ( (int) $cart_item['product_id'] === $product_id ) {
            // ✅ Product already in cart → DO NOTHING
            return;
        }
    }

    // ✅ Add product to cart (only if not present)
    WC()->cart->add_to_cart( $product_id, 1 );
}



}, 5 );




add_action( 'wp_ajax_update_event_product_quantity', 'update_event_product_quantity' );
add_action( 'wp_ajax_nopriv_update_event_product_quantity', 'update_event_product_quantity' );

function update_event_product_quantity() {

    if ( ! isset($_POST['product_id'], $_POST['quantity']) ) {
        wp_send_json_error('Missing data');
    }

    $product_id = absint($_POST['product_id']);
    $quantity   = absint($_POST['quantity']);

    if ( ! WC()->cart ) {
        wp_send_json_error('Cart not available');
    }

    $found = false;

    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

        // ✅ Only match SAME product ID (your requirement)
        if ( (int) $cart_item['product_id'] === $product_id ) {

            // 🔥 Set exact quantity (not increment)
            WC()->cart->set_quantity( $cart_item_key, $quantity, true );

            $found = true;
            break;
        }
    }

    // Optional: if product not found, add it
    if ( ! $found && $quantity > 0 ) {
        WC()->cart->add_to_cart( $product_id, $quantity );
    }

    wp_send_json_success([
        'product_id' => $product_id,
        'quantity'   => $quantity
    ]);
}



function gf_handle_event_group_registration( $entry, $action ) {

    add_filter( 'gform_confirmation', function( $confirmation ) use ( $entry ) {

        $entry_id = (int) rgar( $entry, 'id' );

        $confirmation .= '
        <style>
        #gf-event-pipeline {
            margin: 30px auto;
            text-align: center;
            font-family: Arial, sans-serif;
        }
        #gf-event-spinner {
            width: 30px;
            height: 30px;
            margin-bottom: 10px;
        }
        #gf-event-status {
            font-size: 16px;
            margin-top: 10px;
        }
        #gf-event-error {
            color: #b00020;
            font-weight: bold;
            display: none;
        }
        #gf-event-success {
            color: #1a7f37;
            font-weight: bold;
            display: none;
        }
        </style>

        <div id="gf-event-pipeline">
            <img id="gf-event-spinner" src="' . esc_url( includes_url( 'images/spinner.gif' ) ) . '" />
            <div id="gf-event-status">Processing Event Registration…</div>
            <div id="gf-event-error"></div>
            <div id="gf-event-success"></div>
        </div>

        <script>
        (function($){

            var entryId = ' . $entry_id . ';
            var ajaxUrl = "' . admin_url( 'admin-ajax.php' ) . '";

            var spinner = document.getElementById("gf-event-spinner");
            var status  = document.getElementById("gf-event-status");
            var errorEl = document.getElementById("gf-event-error");
            var okEl    = document.getElementById("gf-event-success");

            // ✅ Step messages
            var STEP_MESSAGES = {
                init: "Initializing Registration…",
                create_sf_company: "Registering Your Organization…",
                create_main_account: "Creating Your Account…",
                create_event_members: "Processing Participants…",
                create_order: "Creating Order…",
                finalize: "Finalizing Registration…"
            };

            function setStatus(step) {
                if (STEP_MESSAGES[step]) {
                    status.textContent = STEP_MESSAGES[step];
                }
            }

            function fail(message) {
                spinner.style.display = "none";
                status.style.display = "none";
                errorEl.textContent = message || "Something went wrong.";
                errorEl.style.display = "block";
            }

            function success(context) {
                spinner.style.display = "none";
                status.style.display = "none";

                
                 okEl.innerHTML = `
        <div class="thanks_message">
            Event Registration Successfull
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

                okEl.style.display = "block";

                console.log("Pipeline finished:", context);
            }

            function runStep(step, context) {

                console.log("Running step:", step, context);

                setStatus(step); // ✅ update UI

                $.ajax({
                    url: ajaxUrl,
                    method: "POST",
                    data: {
                        action: "gf_pipeline_run_step_event_ispa",
                        pipeline: "event_group",
                        step: step,
                        context: JSON.stringify(context || {})
                    },
                    success: function(res) {

                        console.log("Response:", res);

                        if (!res.success) {
                            fail(res.data?.error || "Pipeline failed");
                            return;
                        }

                        if (res.data && res.data.next) {
                            runStep(res.data.next, res.data.context || {});
                        } else {
                            success(res.data.context || {});
                        }
                    },
                    error: function(xhr) {
                        console.error("AJAX ERROR:", xhr.responseText);
                        fail("AJAX request failed");
                    }
                });
            }

            // 🔒 Prevent duplicate execution (refresh / back button)
            var guardKey = "gf_event_pipeline_ran_" + entryId;
            if (sessionStorage.getItem(guardKey) === "1") {
                return;
            }
            sessionStorage.setItem(guardKey, "1");

            console.log("Starting Event Pipeline");

            runStep("init", { entry_id: entryId });

        })(jQuery);
        </script>';

        return $confirmation;
    });
}


add_action( 'wp_ajax_gf_pipeline_run_step_event_ispa', 'gf_pipeline_run_step_event_ispa' );
add_action( 'wp_ajax_nopriv_gf_pipeline_run_step_event_ispa', 'gf_pipeline_run_step_event_ispa' );

function gf_pipeline_run_step_event_ispa() {

    $pipeline = sanitize_text_field($_POST['pipeline'] ?? '');
    $step     = sanitize_text_field($_POST['step'] ?? '');
    $context  = json_decode(stripslashes($_POST['context'] ?? '{}'), true);

    if ($pipeline !== 'event_group') {
        wp_send_json_error(['error' => 'Invalid pipeline']);
    }

    switch ($step) {

        /* ---------------- INIT ---------------- */

case 'init':

    if ( empty( $context['entry_id'] ) ) {
        wp_send_json_error(['error' => 'Missing entry_id']);
    }

    $entry = GFAPI::get_entry( (int) $context['entry_id'] );
    $form  = GFAPI::get_form( rgar($entry, 'form_id') );

    // 🔍 Get Organization ID from hidden field
    $org_id = '';

    foreach ( $form['fields'] as $field ) {

        $label = $field->adminLabel ?: $field->label;

        if ( strpos( strtolower($label), 'organization id' ) !== false ) {
            $org_id = rgar($entry, (string)$field->id);
            break;
        }
    }

    // ✅ CASE: Org exists → skip company creation
    if ( ! empty($org_id) ) {

        $context['sf_company_account_id'] = $org_id;

        wp_send_json_success([
            'next' => 'create_main_account',
            'context' => $context
        ]);
    }

    // ✅ DEFAULT FLOW (unchanged)
    wp_send_json_success([
        'next' => 'create_sf_company',
        'context' => $context
    ]);




        //----------------Creating Company Account--------------------------

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
    $context['company_name'] = $company_name;
    // Uncomment to send company Account to SF
    $sf_account_id = gf_create_company_account_event_ispa( $company_name);




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








        /* ---------------- MAIN ACCOUNT ---------------- */
 



case 'create_main_account':
// Here Main Account Need to be created from Outside 


    if ( empty( $context['entry_id'] ) ) {wp_send_json_error([ 'error' => 'Missing entry_id' ]);}

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




            update_user_meta( $user_id, 'organization',   $context['sf_company_account_id'] );
            update_user_meta( $user_id, 'organisation_name',    $context['company_name'] );
            update_user_meta( $user_id, 'account_type',     'main' );
            $context['user_id']  = (int) $user_id;
            $context['customer'] = $customer;

    $obj    = new SF_108Connector_AdminSettings();
    $result = $obj->sf_export_user_sync(array($user_id), 'account',true);    
    $context['main_sf_account_id'] = $result;
    wp_send_json_success([
        'step'    => 'create_main_account',
        'next'    => 'create_members',
        'context' => $context,
        'message' => 'Main account resolved'
    ]);








        /* ---------------- MEMBERS ---------------- */
        case 'create_members':

            $entry = GFAPI::get_entry((int)$context['entry_id']);
            $members = gf_extract_event_group_members($entry);
            $created = [];
            foreach ($members as $member) {

                if (empty($member['email'])) continue;

                $result = gf_pipeline_get_or_create_event_member_ispa($member);
                if (is_wp_error($result)) continue;

                $created[] = [
                    'wp_user_id' => $result['wp_user_id'],
                    'sf_id'      => $result['sf_object_id']
                ];
            }
            $context['members'] = $created;
            
            wp_send_json_success([
                'next' => 'create_affiliations',
                'context' => $context
            ]);





// Create Affiliations 


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



        $member_id = get_user_meta( $member['wp_user_id'], 'sf_object_id', true );
            $record = [ 'attributes' => [ 'type' => 'IS_Affiliation__c' ],
            'IS_Parent_Account__c'    => $company_sf_id,
            'IS_Account__c'     => $member_id,
            'RecordTypeId' => '012au000000WQfKAAW',
            ];



       $records[] = $record;    
    
    //Loop Ends here Gotta add main account as well   

    }


    //Adding Main Record Here

    $records[] = [
    'attributes' => [ 'type' => 'IS_Affiliation__c' ],
    'IS_Parent_Account__c' => $company_sf_id,
    'IS_Account__c' => $main_sf_id, 
    'RecordTypeId' => '012au000000WQfKAAW',
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

    if ( ! is_user_logged_in() ) {
    $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]),'POST');
    }
    wp_send_json_success([
        'step'    => 'create_affiliations',
        'next'    => 'sync_order',
        'context' => $context,
        'message' => count( $records ) . ' affiliation(s) submitted',
    ]);






// Creating and Synching Order






    
        case 'sync_order':

            $user_id = $context['user_id'];

            $cart_items = WC()->cart->get_cart();
            $first_item = reset($cart_items);

            $product = $first_item['data'];
            $context['product_sf_id'] = get_post_meta($product->get_id(),'sf_object_id',true);
            $order = wc_create_order([
                'customer_id' => $user_id
            ]);

            $qty = max(1, count($context['members']) + 1);

            $order->add_product($product, $qty);

            $order->update_status('completed');
            $order->calculate_totals();
            $membership_start = $current_year . '-07-01';
            $membership_end = ($current_year + 1). '-06-30';
            $product_name = $product->get_name();
            $order->update_meta_data( 'membership_start_date', $membership_start );
            $order->update_meta_data( 'membership_end_date', $membership_end );
            $order->update_meta_data( 'membership_type', $product_name );
            $order->update_meta_data('sf_account_id', $context['main_sf_account_id']);
            $order->update_meta_data( 'sf_organization_id', $context['sf_company_account_id'] );
            $order->save();
            $obj    = new SF_108Connector_AdminSettings();
            $result = $obj->sf_export_post_sync(array($order->get_id()), 'shop_order',true);
            $context['order_id'] = $order->get_id();
            $context['order_sf_id'] = $result;

            sf108_create_form_submission($context['user_id'],$context['order_id'],'012au000000eRvxAAE',$result , $context['sf_company_account_id'] );

            wp_send_json_success([
                'next' => 'create_registrations',
                'context' => $context
            ]);




    /* ---------------- 🔥 REGISTRATIONS (REPLACES AFFILIATIONS) ---------------- */


        case 'create_registrations':
    
            $records = [];
          
            $order_sf = get_post_meta($context['order_id'],'sf_object_id',true);
            dbg($order_sf);

            $query = "SELECT IS_Event__c FROM IS_Product__c WHERE Id = '".$context['product_sf_id']."'";
            $responses = SF_APIConnector::getSQueryObject( $query, 1 );
            $context['event_sf_id'] = $responses->IS_Event__c;


            $event_sf_id = $context['event_sf_id'] ?? '';

            foreach ($context['members'] as $member) {

                if (empty($member['sf_id'])) continue;

                $records[] = [
                    'attributes' => ['type' => 'IS_Registration__c'],
                    'IS_Account__c' => $member['sf_id'],
                    'IS_Event__c'   => $event_sf_id,
                    'IS_Order__c' => $order_sf,
                ];
            }

            // 🔥 include main user too
            $records[] = [
                'attributes' => ['type' => 'IS_Registration__c'],
                'IS_Account__c' => $context['main_sf_account_id'],
                'IS_Event__c'   => $event_sf_id,
                'IS_Order__c' => $order_sf,
            ];

            if (!empty($records)) {
              $record = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records]), 'POST');

            }

            wp_send_json_success([
                'next' => 'finalize',
                'context' => $context
            ]);

        /* ---------------- ORDER ---------------- */









        /* ---------------- FINAL ---------------- */
        case 'finalize':


            $order_html = fetch_sf_order_details( $context['order_id'] );
            $context['order_html'] = $order_html;
         
        wp_set_current_user($context['user_id']);
            wp_set_auth_cookie($context['user_id']);

            wp_send_json_success([
                'next' => null,
                'context' => $context
            ]);

        default:
            wp_send_json_error(['error' => 'Invalid step']);
    }
}


function gf_extract_event_group_members( $entry ) {

    $form = GFAPI::get_form((int)$entry['form_id']);

    foreach ($form['fields'] as $field) {

        if ($field->type !== 'events_group') continue;

        $raw = rgar($entry, (string)$field->id);

        if (empty($raw)) return [];

        $members = json_decode($raw, true);

        if (!is_array($members)) return [];

        // ✅ Filter only valid emails
        return array_values(array_filter($members, function($m){
            return !empty($m['email']);
        }));
    }

    return [];
}




function gf_pipeline_get_or_create_event_member_ispa( array $member ) {

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
    ];

    $records = [];
    $records[] = ['attributes' => [ 'type' => 'Account' ],] + $payload;

    $response = SF_APIConnector::postCURLObject(json_encode(['allOrNone' => false,'records'   => $records,]),'POST');

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

    return [
        'wp_user_id'   => (int) $user_id,
        'sf_object_id' => $sf_account_id,
        'created'      => true,
    ];
}


/*

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

*/
    


function gf_create_company_account_event_ispa($company_name){




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
        'IS_Status__c'                => 'Pending Activation',
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


function gf_is_user_registered_for_event($user_id, $event_id){

    // Get Salesforce Account ID from WP user
    $sf_id = get_user_meta($user_id, 'sf_object_id', true);

    if (empty($sf_id) || empty($event_id)) {
        return false;
    }

    // Build query
    $query = "SELECT Id 
              FROM IS_Registration__c 
              WHERE IS_Account__c = '".$sf_id."' 
              AND IS_Event__c = '".$event_id."' 
              AND RecordTypeId = '012au000000WRLGAA4'
              LIMIT 1";

    $response = SF_APIConnector::getSQueryObject($query, 1);

    // If record found → user is registered
    if (!empty($response) && is_array($response)) {
        return true;
    }

    return false;
}