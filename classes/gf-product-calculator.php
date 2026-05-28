<?php
 /**
 * Additional Product Field for GFORM for Checkout and Paid Forms.
 */

if ( ! class_exists( 'GF_Field_WCProduct_Calculator' ) ) {
    class GF_Field_WCProduct_Calculator extends GF_Field {
        public $type = 'wc_product_calculator';

        public function get_form_editor_field_title() {
            return esc_html__( 'Product Calculator', 'gf-wc-product' );
        }

        public function get_form_editor_button() {
            return array(
                'group' => 'advanced_fields', 
                'text'  => esc_html__( 'Product Calculator', 'gf-wc-product' ),
            );
        }

        public function get_form_editor_field_settings() {
            return [
                'label_setting',
                'description_setting',
                'css_class_setting',
                'admin_label_setting',
                'rules_setting',
                'visibility_setting',
                'duplicate_setting',
                'conditional_logic_field_setting' 
            ];
        }


        public function is_conditional_logic_supported() {
            return true;
        }




public function get_field_input( $form, $value = '', $entry = null ) {

    $field_id   = (int) $this->id;
    $product_id = isset( $this->wc_product_id ) ? (int) $this->wc_product_id : 0;
    $ref_id     = $this->event_ref_field;

    // Resolve quantity (default 1)
    $qty = 1;

    if ( ! empty( $entry ) && isset( $entry[ 'input_qty_' . $field_id ] ) ) {
        $qty = max( 1, (int) rgar( $entry, 'input_qty_' . $field_id ) );
    }

    $out  = '<div class="gf-wc-product-field gf-wc-product-' . esc_attr( $field_id ) . '">';

    if ( $product_id && class_exists( 'WC_Product' ) ) {

        $product = wc_get_product( $product_id );

        if ( $product ) {

            $title = esc_html( $product->get_name() );
            $price = esc_html( $product->get_price() );

            $out .= '<div class="product_table_gfAddon">';
            $out .= '<div class="gfAddonProductTitle">' . $title . '</div>';
            $out .= '<div class="gfAddonProductPrice">$' . $price . '</div>';
            $out .= '</div>';

            // Product reference
            $out .= '<input type="hidden" name="input_' . $field_id . '" value="' . esc_attr( $product_id ) . '" />';

            // Calculator price (dynamic)
            $out .= '<input type="hidden" name="input_price_' . $field_id . '" value="' . esc_attr( $price ) . '" />';

            // Quantity input
            $out .= '
            <div class="gfAddonProductQty">
                <label for="input_qty_' . $field_id . '">Quantity</label>
                <input
                    type="number"
                    id="input_qty_' . $field_id . '"
                    name="input_qty_' . $field_id . '"
                    value="' . esc_attr( $qty ) . '"
                    min="1"
                    step="1"
                    class="gf-wc-product-qty"
                />
            </div>';

            $out  .= '<div class="gf-wc-product-field gf-wc-calculator" data-calculator-field="' . esc_attr( $this->id ) . '" data-ref-field="' . esc_attr( $ref_id ) . '">';

        } else {
            $out .= '<em>' . esc_html__( 'Selected product not found.', 'gf-wc-product' ) . '</em>';
        }

    } else {
        $out .= '<em>' . esc_html__( 'No product configured for this field.', 'gf-wc-product' ) . '</em>';
    }

    $out .= '</div>';

    return $out;
}







    }
}
