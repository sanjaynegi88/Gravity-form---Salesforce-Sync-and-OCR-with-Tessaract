<?php 

// Setup Transients to reduce the load for API calls over checkout



add_action( 'shutdown', 'gf_process_sf_order_resolution_queue' );
function gf_process_sf_order_resolution_queue() {

    // global lock
    if ( get_transient( 'sf_resolution_lock' ) ) {
        return;
    }
    set_transient( 'sf_resolution_lock', 1, 60 );

    global $wpdb;

    $rows = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'sf_order_resolution_%'");

    if ( empty( $rows ) ) {
        delete_transient( 'sf_resolution_lock' );
        return;
    }

    foreach ( $rows as $row ) {

        $data = maybe_unserialize( $row->option_value );
  
        $order_id = absint( $data['order_id'] ?? 0 );

        if ( ! $order_id ) {
            delete_option( $row->option_name );
            continue;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            delete_option( $row->option_name );
            continue;
        }

        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            continue; // retry later
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            continue;
        }

        $email = $user->user_email;
        if ( ! $email ) {
            continue;
        }

        $sf_id = get_user_meta($user_id,'sf_object_id',true);

        if ( empty( $sf_id )) {
            // retry up to 5 times
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;

            if ( $data['attempts'] >= 5 ) {
                delete_option( $row->option_name ); // give up
            } else {
                update_option( $row->option_name, $data, 'no' );
            }
            continue;
        }



        // ✅ APPLY META
    //    update_user_meta( $user_id, 'sf_account_id', $sf_id );

$order = wc_get_order( $order_id );

if ( $order ) {
    $order->update_meta_data( 'sf_account_id', $sf_id );
    $order->save();
    $obj    = new SF_108Connector_AdminSettings();
    $result = $obj->sf_export_post_sync(array($order->get_id()), 'shop_order',true);
   


    gf_update_membership_dates($sf_id,$user_id);

}

        update_post_meta( $order_id, 'sf_account_id', $sf_id );
        update_post_meta( $order_id, '_sf_resolved', 1 );

        // ✅ SAFE re-fire user_register (ONCE)
        if ( ! get_user_meta( $user_id, '_sf_user_register_rerun', true ) ) {
            update_user_meta( $user_id, '_sf_user_register_rerun', 1 );
            do_action( 'user_register', $user_id );
        }

        // cleanup
        delete_option( $row->option_name );
    }

    delete_transient( 'sf_resolution_lock' );
}

