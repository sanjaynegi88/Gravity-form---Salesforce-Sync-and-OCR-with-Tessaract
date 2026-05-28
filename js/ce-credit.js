jQuery(document).ready(function ($) {

    var ajaxUrl = CECreditData.ajaxUrl;

    // Target CE Credit field by GF semantic class
    var $ceField = $('.gfield--type-cecredit');

    if (!$ceField.length) {
        return;
    }

    // Find the hidden input that GF will actually submit
    var $hiddenInput = $ceField.find('input[type="hidden"]');

    if (!$hiddenInput.length) {
        console.warn('Hidden input for CE Credit not found');
        return;
    }

    // Prevent duplicate select creation
    if ($ceField.data('select-added')) {
        return;
    }
    $ceField.data('select-added', true);

    // Append select UI
    var $select = $('<select class="select_enrolled_exams"><option value="">Select Course</option></select>');
    $ceField.append($select);

    // Fetch courses via AJAX
    $.ajax({
        url: ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'handle_sf_account',
            nonce: CECreditData.nonce
        },
        success: function (response) {

            if (!response.success || !response.data.courses) {
                $select.append('<option value="">No courses found</option>');
                return;
            }

            response.data.courses.forEach(function (course) {
                $select.append(
                    '<option value="' + course.id + '">' + course.name + '</option>'
                );
            });

        },
        error: function (xhr) {
            console.error('AJAX error:', xhr.responseText);
            $select.empty().append('<option value="">Error loading courses</option>');
        }
    });


    $select.on('change', function () {
        var selectedValue = $(this).val() || '';
        $hiddenInput.val(selectedValue);
    });

});























