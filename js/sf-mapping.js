jQuery(document).ready(function ($) {

$("#selected_form").change(function (e) {
var formselected = $("#selected_form").val();
fetch_existing_forms(formselected);
if(formselected == "") {
    e.preventDefault();
    alert("Please select a form.");
}else {
     $.ajax({
            type: 'POST',
            url: sf_mapping_ajax.ajax_url,
            data: {
                action: 'sf_select_object',
                formID: formselected,
    
            },
            success: function (response) {

            //Step 2 Begins
            $("#additional-fields").html(response);
            
            
            step_2_bind_events();
         
            },
            error: function () {
                alert('An error occurred.');
            }
        });
}   
    
});



function step_2_bind_events(){

$('.select_2').select2({ placeholder: 'Select options', width: 'resolve'});
  $('.gf_mapping_data_sf_type, .gf_mapping_data_sf').on('change', function() {
    triggerAction();
  });
}




function triggerAction() {
  var val1 = $('.gf_mapping_data_sf').val();
  var val2 = $('.gf_mapping_data_sf_type').val();

  if (val1 && val2) {
    // Assuming you're using val1 or val2 as the form ID
    var formID = $("#selected_form").val(); // Or assign whichever value is correct for your context
    $.ajax({
      type: 'POST',
      url: sf_mapping_ajax.ajax_url,
      data: {
        action: 'fetch_gformsbyid_forms',
        formID: formID,
        objectType:val1
      },
      success: function(response) {
        $(".field_mapping_selectable").html(response);
        $(".field_mapping_selectable select").selectable();
   
  /*      $("#sf_field_select").select2({
  placeholder: 'Select an option',
  allowClear: true,
  width: '100%',
  minimumResultsForSearch: 0 // ensures search box always appears
});
*/
        save_mapped_valueforgf(); 
      },
      error: function(xhr, status, error) {
        console.error("AJAX Error:", error);
      }
    });
  }
}



function fetch_existing_forms(formID){


 $.ajax({
      type: 'POST',
      url: sf_mapping_ajax.ajax_url,
      data: {
        action: 'fetch_and_append_gforms',
        formID: formID
      },
      success: function(response) {
        $("#additional_forms").html(response);

        bind_click_action_delete();

      },
      error: function(xhr, status, error) {
        console.error("AJAX Error:", error);
      }
    });



}


function save_mapped_valueforgf(){

    $('#add_mapped_value').on('click', function () {
        const ssffield = $('#sf_field_select').val();
        const sgfield = $('#gf_field_select').val();
        const sform_id = $('#selected_form').val();
        const ssf_object = $('#selected_sf_object').val();
        const ssf_object_type = $('#selected_salesforce_object_type').val();
        const sf_to_wp = $('#sf_to_wp').is(':checked') ? 'on' : '';
        const wp_to_sf = $('#wp_to_sf').is(':checked') ? 'on' : '';
        if (!ssffield || !sgfield || !sform_id || !ssf_object) {
            alert('Please select both Salesforce and Gravity Form fields.');
            return;
        }

        $.ajax({
            type: 'POST',
            url: sf_mapping_ajax.ajax_url,
            data: {
                action: 'add_sf_gf_mapping',
                ssffield: ssffield,
                sgfield: sgfield,
                sform_id: sform_id,
                ssf_object: ssf_object,
                ssf_object_type: ssf_object_type,
                sf_to_wp: sf_to_wp,
                wp_to_sf: wp_to_sf
            },
            success: function (response) {
             //   alert(response.data.message);
              //  alert(response.data.message);
                fetch_existing_forms(sform_id);
            
            },
            error: function () {
                alert('An error occurred.');
            }
        });


    });   

}   


function bind_click_action_delete(){

$("a.delete_mapping").on("click", function () {
    if (!confirm("Are you sure you want to delete this mapping?")) {
        return; // User canceled the action
    }

    var wpoption = $(this).attr('optionid');

    $(this).parents(".form_container").hide();

    $.ajax({
        type: 'POST',
        url: sf_mapping_ajax.ajax_url,
        data: {
            action: 'delete_mapping_action',
            wpoption: wpoption
        },
        success: function (response) {
           // alert("Mapping Deleted");
        },
        error: function () {
            alert('An error occurred.');
        }
    });
});

$("a.delete-mapping-single").on("click",function(){
var wpoption = $(this).attr('data-option');
var sf = $(this).attr('sf-field');
var gf = $(this).attr('gf-field');
 $(this).parents("tr").hide();
        $.ajax({
            type: 'POST',
            url: sf_mapping_ajax.ajax_url,
            data: {
                action: 'delete_mapping_action_single',
                wpoption : wpoption ,
                sf : sf,
                gf : gf
            },
            success: function (response) {
               // alert("Mapping Deleted");

           
            },
            error: function () {
                alert('An error occurred.');
            }
        });



});





$("a.edit-mapping").on("click",function(){
var wpoption = $(this).attr('data-option');
var sf = $(this).attr('sf-field');
var gf = $(this).attr('gf-form');
var gff = $(this).attr('gf-field');
var sfwp = $(this).attr('data-option-sfwp');
var wpsf = $(this).attr('data-option-wpsf');

        $.ajax({
            type: 'POST',
            url: sf_mapping_ajax.ajax_url,
            data: {
                action: 'edit_mapping_action_single',
                wpoption : wpoption ,
                sf : sf,
                gf : gf,
                gff : gff,
                sfwp: sfwp,
                wpsf: wpsf
            },
            success: function (response) {
               $("#edit_container_global").html(response);
               $(".curtain").show().animate({"opacity":"1"});
               bind_update_cancel_buttons(sf,gff);

           
            },
            error: function () {
                alert('An error occurred.');
            }
        });



});

}


function bind_update_cancel_buttons(sff,gff){

  $("#cancel_curtain").on("click",function(){
    $(".curtain").animate({"opacity":"0"},function(){
      $(this).hide();
       $("#edit_container_global").html("");
    });

  });

  $("#update_mapped_value").on("click",function(){
    var wpoption = $(this).attr('wpoption'); 
    var sf = $(".curtain #sf_field_select").val();
    var gf = $(".curtain #gf_field_select").val();
    var sfwp = $("#wp_to_sf").is(':checked') ? 'on' : '';
    var wpsf = $("#sf_to_wp").is(':checked') ? 'on' : '';

            $.ajax({
            type: 'POST',
            url: sf_mapping_ajax.ajax_url,
            data: {
                action: 'edit_mapping_action_single_update',
                wpoption : wpoption ,
                gf : gf,
                sf : sf,
                osf : sff,
                ogf : gff,
                sfwp: sfwp,
                wpsf: wpsf
       
            },
          success: function (response) {
          // alert(response); 
         $(".curtain").animate({"opacity":"0"},function(){
         $(this).hide();
         $("#edit_container_global").html("");
          var formselected = $("#selected_form").val();
          fetch_existing_forms(formselected);

         });

           
            },
            error: function () {
                alert('An error occurred.');
            }
        });







  });


}














 // End Docunment Ready 

});