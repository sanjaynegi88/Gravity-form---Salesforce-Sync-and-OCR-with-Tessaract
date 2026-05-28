<?php
 /**
 * Additional Product Field for GFORM for Checkout and Paid Forms.
 */

if ( ! class_exists( 'GF_Field_WCProduct_Custom' ) ) {
    class GF_Field_WCProduct_Custom extends GF_Field {
        public $type = 'wc_product_custom';

        public function get_form_editor_field_title() {
            return esc_html__( 'WooCommerce Product', 'gf-wc-product' );
        }

        public function get_form_editor_button() {
            return array(
                'group' => 'advanced_fields', 
                'text'  => esc_html__( 'WooCommerce Product', 'gf-wc-product' ),
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
            $product_id = isset( $this->wc_product_id ) ? intval( $this->wc_product_id ) : 0;
            $out  = '<div class="gf-wc-product-field gf-wc-product-' . esc_attr( $this->id ) . '">';
            if ( $product_id && class_exists( 'WC_Product' ) ) {
                $product = wc_get_product( $product_id );
                if ( $product ) {
                    $title = esc_html( $product->get_name() );
                    $out .= '<div class="product_table_gfAddon">';
                    $out .= '<div class="gfAddonProductTitle">'.$title.'</div>';
                    $out .= '<div class="gfAddonProductPrice">$'.$product->get_price().'</div>';
                    $out .= '</div>';
                    $out .= '<input type="hidden" name="input_' . intval( $this->id ) . '" value="' . esc_attr( $product_id ) . '"/>';
                    
                    $out .= '<input type="hidden" name="input_price_' . intval( $this->id ) . '" value="' . esc_attr( $product->get_price() ) . '"/>';
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
