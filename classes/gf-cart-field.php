<?php

if ( ! class_exists( 'GF_Field' ) ) {
    return;
}

class GF_Field_GF_Cart extends GF_Field {

    public $type  = 'gf_cart';
    public $label = 'GF Cart';

    public function get_form_editor_field_title() {
        return esc_html__( 'GF Cart', 'idealams' );
    }

    public function get_form_editor_button() {
        return [
            'group' => 'advanced_fields',
            'text'  => esc_html__( 'GF Cart', 'idealams' ),
        ];
    }

    public function get_form_editor_field_settings() {
        return [
            'label_setting',
            'description_setting',
            'css_class_setting',
            'conditional_logic_field_setting',
            'auto_hide_setting',
        ];
    }

    /**
     * Frontend display of WooCommerce cart
     */
    public function get_field_input( $form, $value = '', $entry = null ) {

        // Form editor preview
        if ( GFCommon::is_form_editor() ) {
            return '<div class="ginput_container">WooCommerce Cart Preview</div>';
        }

       // WC()->cart->empty_cart( true );

/*        
if ( function_exists( 'WC' ) && ( ! empty( $_POST['event_product_id'] ) || ! empty( $_POST['wpID'] ) ) ) {


    if ( WC()->cart === null ) {
        wc_load_cart();
    }

    $product_id = absint( $_POST['event_product_id'] ?? $_POST['wpID'] ?? 0 );
    $found      = false;

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( (int) $cart_item['product_id'] === $product_id ) {
            $found = true;
            break;
        }
    }

    if ( ! $found ) {
        WC()->cart->add_to_cart( $product_id );
    }


add_action( 'woocommerce_cart_calculate_fees', function () {

    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    if ( ! WC()->cart ) {
        return;
    }

    $subtotal = WC()->cart->get_subtotal();

    if ( $subtotal <= 0 ) {
        return;
    }

    $tax = round( $subtotal * 0.13, 2 );

    // Prevent duplicate fee
    foreach ( WC()->cart->get_fees() as $fee ) {
        if ( $fee->name === 'Tax (13%)' ) {
            return;
        }
    }

    //WC()->cart->add_fee( 'Tax (13%)', $tax, false );
}, 20 );

// Force totals recalculation
WC()->cart->calculate_totals();



}

*/


if ( function_exists( 'WC' ) ) {

    if ( WC()->cart === null ) {
        wc_load_cart();
    }

    $product_id = absint( $_POST['event_product_id'] ?? $_POST['wpID'] ?? 0 );

    if ( $product_id ) {

        WC()->cart->empty_cart( true );

        // Add product
        WC()->cart->add_to_cart( $product_id );

        // Add tax fee
        add_action( 'woocommerce_cart_calculate_fees', function () {

            if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
            if ( ! WC()->cart ) return;

            $subtotal = WC()->cart->get_subtotal();
            if ( $subtotal <= 0 ) return;

            $tax = round( $subtotal * 0.13, 2 );

            foreach ( WC()->cart->get_fees() as $fee ) {
                if ( $fee->name === 'Tax (13%)' ) return;
            }

          //  WC()->cart->add_fee( 'Tax (13%)', $tax, false );

        }, 20 );

        WC()->cart->calculate_totals();
    }


}




if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
    return '<div class="gf-cart-empty">Your cart is empty.</div>';
}
WC()->cart->calculate_totals();

        ob_start();
        ?>
        <div class="gf-cart-wrapper sae">
            <table class="gf-cart-table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Products</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( WC()->cart->get_cart() as $cart_item ) :
                        $product = $cart_item['data'];
                        if ( ! $product || ! $product->exists() ) {
                            continue;
                        }
                        
                        ?>
                        <tr>
                            <td><?php echo esc_html( $product->get_name() ); ?></td>
                            <td><?php echo esc_html( $cart_item['quantity'] ); ?></td>
                            <td><?php echo wc_price( $product->get_price() ); ?></td>
                            <td><?php echo wc_price( $cart_item['line_subtotal'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            
<tfoot class="footer_fee">

<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : 

    $discount = WC()->cart->get_coupon_discount_amount( $code );

    if ( $discount <= 0 ) {
        continue; // 🚫 skip zero discount
    }

?>
    <tr class="cart-discount coupon-<?php echo esc_attr( $code ); ?>">
        <td colspan="3" style="text-align:left;">
            <?php echo esc_html( wc_cart_totals_coupon_label( $coupon ) ); ?>
        </td>
        <td>
            -<?php echo wc_price( $discount ); ?>
        </td>
    </tr>
<?php endforeach; ?>

    <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
        <tr>
            <td colspan="3" style="text-align:left;">
                <?php echo esc_html( $fee->name ); ?>
            </td>
            <td>
                <?php echo wc_price( $fee->amount ); ?>
            </td>
        </tr>
    <?php endforeach; ?>

    <tr>
        <th colspan="3" style="text-align:right;">Cart Total</th>
        <th><?php echo wc_price( WC()->cart->get_total( 'raw' ) ); ?></th>
    </tr>

</tfoot>


            </table>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Nothing stored in entry
     */
    public function get_value_save_entry( $value, $form, $input_name, $lead_id, $entry ) {
        return '';
    }

    public function is_conditional_logic_supported() {
        return true;
    }
}
