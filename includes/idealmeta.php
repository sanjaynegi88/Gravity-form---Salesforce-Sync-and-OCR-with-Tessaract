<?php



add_action('show_user_profile', 'user_events_field');
add_action('edit_user_profile', 'user_events_field');

function user_events_field($user) {
    $events = get_user_meta($user->ID, 'user_events', true);
    if (!is_array($events)) {
        $events = [];
    }
    ?>
    <h3>User Events</h3>
    <table class="form-table">
        <tr>
            <th><label>Events</label></th>
            <td>
                <div id="events-wrapper">
                    <?php foreach ($events as $event): ?>
                        <div class="event-item" style="margin-bottom:8px;">
                            <input type="text" name="user_events[]" value="<?php echo esc_attr($event); ?>" style="width:300px;">
                            <button type="button" class="button remove-event">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="button" id="add-event">Add Event</button>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {

                        document.getElementById('add-event').addEventListener('click', function () {
                            const wrapper = document.getElementById('events-wrapper');

                            const div = document.createElement('div');
                            div.classList.add('event-item');
                            div.style.marginBottom = '8px';

                            div.innerHTML = `
                                <input type="text" name="user_events[]" style="width:300px;">
                                <button type="button" class="button remove-event">Remove</button>
                            `;

                            wrapper.appendChild(div);
                        });

                        document.addEventListener('click', function (e) {
                            if (e.target.classList.contains('remove-event')) {
                                e.target.parentElement.remove();
                            }
                        });

                    });
                </script>

                <p class="description">Add multiple events. Stored as an array.</p>
            </td>
        </tr>
    </table>
    <?php
}



add_action('personal_options_update', 'save_user_events');
add_action('edit_user_profile_update', 'save_user_events');

function save_user_events($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    if (isset($_POST['user_events'])) {
        $events = array_map('sanitize_text_field', $_POST['user_events']);
        $events = array_filter($events);
        $events = array_values($events);

        update_user_meta($user_id, 'user_events', $events);
    } else {
        delete_user_meta($user_id, 'user_events');
    }
}



add_action('show_user_profile', 'user_picture_field');
add_action('edit_user_profile', 'user_picture_field');

function user_picture_field($user) {
    $image = get_user_meta($user->ID, 'user_picture', true);
    ?>
    <h3>User Picture</h3>
    <table class="form-table">
        <tr>
            <th><label>User Image</label></th>
            <td>

                <div id="user-picture-preview" style="margin-bottom:10px;">
                    <?php if (!empty($image)): ?>
                        <img src="<?php echo esc_attr($image); ?>" style="max-width:150px; display:block;">
                    <?php endif; ?>
                </div>

                <input type="file" id="user_picture_input" accept="image/*">

                <input type="hidden" name="user_picture" id="user_picture_hidden" value="<?php echo esc_attr($image); ?>">

                <br><br>

                <button type="button" class="button" id="remove-picture">Remove Image</button>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const fileInput = document.getElementById('user_picture_input');
    const preview = document.getElementById('user-picture-preview');
    const hidden = document.getElementById('user_picture_hidden');

    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        const reader = new FileReader();

        reader.onload = function (e) {
            const img = new Image();
            img.src = e.target.result;

            img.onload = function () {

                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                let width = img.width;
                let height = img.height;

                // 🔹 Resize logic (max width 300px)
                if (width > 300) {
                    height = height * (300 / width);
                    width = 300;
                }

                canvas.width = width;
                canvas.height = height;

                ctx.drawImage(img, 0, 0, width, height);

                // 🔹 Compression loop (target ~10–15KB)
                let quality = 0.7;
                let base64 = canvas.toDataURL('image/jpeg', quality);

                function getSize(base64) {
                    return Math.round((base64.length * 3) / 4 / 1024); // KB
                }

                let size = getSize(base64);

                while (size > 15 && quality > 0.1) {
                    quality -= 0.05;
                    base64 = canvas.toDataURL('image/jpeg', quality);
                    size = getSize(base64);
                }

                console.log('Final image size:', size + 'KB');

                // Save to hidden field
                hidden.value = base64;

                // Show preview
                preview.innerHTML = `<img src="${base64}" style="max-width:150px;">`;
            };
        };

        reader.readAsDataURL(file);
    });

    document.getElementById('remove-picture').addEventListener('click', function () {
        hidden.value = '';
        preview.innerHTML = '';
        fileInput.value = '';
    });

});
</script>

                <p class="description">
                    Upload image. Stored as base64 string (not in media library).
                </p>

            </td>
        </tr>
    </table>
    <?php
}

add_action('personal_options_update', 'save_user_picture');
add_action('edit_user_profile_update', 'save_user_picture');

function save_user_picture($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    if (isset($_POST['user_picture'])) {
        $image = $_POST['user_picture'];

        // Optional: validate it's base64 image
        if (strpos($image, 'data:image') === 0) {
            update_user_meta($user_id, 'user_picture', $image);
        } else {
            delete_user_meta($user_id, 'user_picture');
        }
    }
}