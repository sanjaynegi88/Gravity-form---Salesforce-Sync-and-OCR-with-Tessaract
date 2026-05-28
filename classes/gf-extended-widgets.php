<?php 

/* Extending GF default Fields Class and adding custom widgets 
   Required for New Addons 
*/

class GF_Reusable_Custom_Field extends GF_Field {

    public $type  = 'custom_field';
    public $label = 'Custom Field';

    public function get_form_editor_field_title() {
        return esc_attr( $this->label );
    }

    public function get_form_editor_button() {
        return array(
            'group' => 'advanced_fields',
            'text'  => $this->label,
        );
    }

    public function get_form_editor_field_settings() {
        return array(
            'label_setting',
            'description_setting',
            'css_class_setting',
            'rules_setting',
            'conditional_logic_field_setting',
            'auto_hide_setting',
        );
    }

    protected function compute_dynamic_value() {
        $type = rgar( $this, 'type' );

        if ( $type === 'sf_account_id' ) {
            // return empty string when not logged in
            return is_user_logged_in() ? (string) get_user_meta( get_current_user_id(), 'sf_object_id', true ) : '';
        }

        if ( $type === 'total_posts' ) {
            $uid = get_current_user_id();
            return $uid ? (string) count_user_posts( $uid, 'post' ) : '0';
        }

        if ( $type === 'form_submission' ) {
            
            return '';
        }

        return '';
    }

    
public function get_field_input( $form, $value = '', $entry = null ) {

    $form_id  = absint( $form['id'] );
    $field_id = absint( $this->id );

    // Compute value (dynamic > saved > empty)
    $computed     = $this->compute_dynamic_value();
    $field_value  = $computed !== '' ? $computed : (string) $value;
    $field_value  = (string) $field_value;

    /**
     * FORM EDITOR PREVIEW
     * Show something readable so editors know what this field is
     */
    if ( GFCommon::is_form_editor() ) {

        return sprintf(
            '<div class="ginput_container ginput_container_text">
                <input type="text" disabled value="[%1$s] %2$s" class="large" />
            </div>',
            esc_html( $this->type ),
            esc_attr( $field_value !== '' ? $field_value : 'auto' )
        );
    }

    /**
     * FRONTEND / SUBMISSION
     * Pure hidden storage – nothing else
     */
    return sprintf(
        '<input type="hidden"
            name="input_%1$d"
            id="input_%2$d_%1$d"
            value="%3$s" />',
        $field_id,
        $form_id,
        esc_attr( $field_value )
    );
}


    /**
     * Save the value into the entry.
     * Prefer whatever GF passed ($value) but recompute defensively to guarantee correctness.
     */
    public function get_value_save_entry( $value, $form, $input_name, $lead_id, $entry ) {
        // If GF already provided a non-empty value, use it
        if ( $value !== '' && $value !== null ) {
            return $value;
        }

        // Otherwise compute server-side (guaranteed correct)
        return $this->compute_dynamic_value();
    }

    public function is_conditional_logic_supported() {
        return true;
    }


/*

public function get_field_content( $value, $force_frontend_label, $form ) {

    // FRONTEND ONLY (not form editor, not entry detail, not admin)
    if ( 
        ! GFCommon::is_form_editor() &&   // not form editor
        ! GFCommon::is_entry_detail() &&  // not entry detail
        ! is_admin()                      // not WP admin
    ) {

        // If the field is intended to be hidden
        if ( isset( $this->field_type ) && strtolower( $this->field_type ) === 'hidden' ) {
          
            return $this->get_field_input( $form, $value );
        }
    }

    // Default GF behavior in admin/editor
    return parent::get_field_content( $value, $force_frontend_label, $form );
}

*/








    
}





add_action( 'gform_loaded', function () {

    if ( ! class_exists( 'GF_Fields' ) ) {
        return;
    }

    class GF_Corporate_Members_Field extends GF_Field {

        public $type = 'corporate_members';

        public function is_conditional_logic_supported() {
        return true;
        }


        public function get_form_editor_field_title() {
            return 'Corporate Members';
        }

        public function get_form_editor_button() {
            return [
                'group' => 'advanced_fields',
                'text'  => 'Corporate Members',
            ];
        }

        public function get_form_editor_field_settings() {
            return [
                'label_setting',
                'description_setting',
                'css_class_setting',
                'conditional_logic_field_setting'

            ];
        }

        public function get_field_input( $form, $value = '', $entry = null ) {

            $id      = (int) $this->id;
            $form_id = (int) $form['id'];

            return '
            <div class="gf-corporate-members">
                <div class="corp-members-container">
             
                <div class="corp-member-card">
            <div class="corp-grid">
                <div>
                    <label>First Name</label>
                    <input type="text" class="corp-fname" value="">
                </div>

                <div>
                    <label>Last Name</label>
                    <input type="text" class="corp-lname" value="">
                </div>

                <div>
                    <label>Email</label>
                    <input type="email" class="corp-email" value="">
                </div>

                <div>
                    <label>Phone</label>
                    <input type="tel" class="corp-phone" />
                </div>
            </div>

            <div class="corp-roles">
                <label><input type="checkbox" class="corp-owner"> Owner</label>
                <label><input type="checkbox" class="corp-billing"> Billing</label>
                <label><input type="checkbox" class="corp-coordination"> Code Coordinator</label>
            </div>
        </div>
                
                
                </div>

                <button type="button" class="button add-corp-member">
                    + Add Member
                </button>

                <input type="hidden"  name="input_'.$id.'" id="input_'.$form_id.'_'.$id.'" value="' . esc_attr( $value ) . '"/>
            </div>';
        }
    }

    GF_Fields::register( new GF_Corporate_Members_Field() );







class GF_Events_Group_Field extends GF_Field {

    public $type = 'events_group';

    public function get_form_editor_field_title() {
        return 'Events Group';
    }

    public function get_form_editor_button() {
        return [
            'group' => 'advanced_fields',
            'text'  => 'Events Group',
        ];
    }

    public function get_form_editor_field_settings() {
        return [
            'label_setting',
            'description_setting',
            'css_class_setting',
            'conditional_logic_field_setting', // ✅ important
        ];
    }

    public function is_conditional_logic_supported() {
        return true;
    }



public function get_field_input( $form, $value = '', $entry = null ) {

    $id      = (int) $this->id;
    $form_id = (int) $form['id'];

    /* ========================
     * 🔍 CHECK USER + ORG
     * ======================== */
    $user_id = get_current_user_id();
    $sf_org  = get_user_meta($user_id, 'organization', true);

    $roster = [];

    if ( $user_id && $sf_org ) {
        $roster = gf_get_org_roster_members($sf_org);
    }

    /* ========================
     * ✅ CASE 1: SHOW ROSTER
     * ======================== */
    if ( ! empty($roster) ) {

        $html = '<div class="gf-event-roster">';
        $html .= '<h4>Select Members</h4>';

        foreach ( $roster as $member ) {

        $query = "SELECT FIELDS(ALL) FROM Account WHERE Id = '".$member['id']."'";

        $responses = SF_APIConnector::getSQueryObject($query, 1);
$full_name = $responses->Name ?? '';

$first_name = '';
$last_name  = '';

if ( $full_name ) {

    $parts = explode(' ', trim($full_name));

    $first_name = $parts[0] ?? '';
    $last_name  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
}


        $html .= '
            <div class="roster-item">
                <label>
                    <input type="checkbox" class="roster-checkbox"
                        data-first="'.$first_name.'"
                        data-last="'.$last_name.'"
                        data-email="'.$responses->PersonEmail.'"
                        data-phone="'.$responses->Phone.'"
                        data-user="'.$responses->IS_Woo_Customer_Id__c.'"
                    />
                    '.$first_name.' '.$last_name.' ('.$responses->PersonEmail.')
                </label>
            </div>';
        }

        $html .= '</div>';

    } else {

        /* ========================
         * 🔁 FALLBACK (UNCHANGED UI)
         * ======================== */

        $html = '
        <div class="gf-corporate-members">
            <div class="corp-members-container">

            <div class="corp-member-card">
                <div class="corp-grid">
                    <div>
                        <label>First Name</label>
                        <input type="text" class="corp-fname">
                    </div>

                    <div>
                        <label>Last Name</label>
                        <input type="text" class="corp-lname">
                    </div>

                    <div>
                        <label>Email</label>
                        <input type="email" class="corp-email">
                    </div>

                    <div>
                        <label>Phone</label>
                        <input type="tel" class="corp-phone">
                    </div>
                </div>
            </div>

            </div>

            <button type="button" class="button add-event-member">
                + Add Member
            </button>
        </div>';
    }

    /* ========================
     * 🔒 HIDDEN FIELD (IMPORTANT)
     * ======================== */
    $html .= '<input type="hidden"
        name="input_'.$id.'"
        id="input_'.$form_id.'_'.$id.'"
        value="'.esc_attr($value).'" />';

    return $html;
}







}

GF_Fields::register( new GF_Events_Group_Field() );





});


function gf_get_org_roster_members($org_sf_id) {

    if ( empty($org_sf_id) ) return [];

// SELECT FIELDS(ALL) FROM IS_Affiliation__c WHERE Id = ''

    // $query = "SELECT IS_Account__c FROM IS_Affiliation__c WHERE IS_Parent_Account__c = '".$org_sf_id."' AND IS_Primary_Contact__c = false";

    $query = "SELECT IS_Account__c FROM IS_Affiliation__c WHERE IS_Parent_Account__c = '".$org_sf_id."'";
    $responses = SF_APIConnector::getSQueryObject($query, 100);




    $members = [];

    foreach ($responses as $r) {
        $members[] = [
            'id' => $r->IS_Account__c ?? '',
        ];
    }

    return $members;
}