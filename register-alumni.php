<?php
function alumni_register_form_shortcode() {
    ob_start();

    global $wpdb;
    $db = new wpdb('root', '', 'coenect', 'localhost'); // adjust credentials

    $success_message = '';
    $error_message = '';

    if (isset($_POST['alumni_submit'])) {
        $idnumber  = sanitize_text_field($_POST['idnumber']);
        $firstname = sanitize_text_field($_POST['firstname']);
        $lastname  = sanitize_text_field($_POST['lastname']);
        $course    = intval($_POST['course']);
        $year      = sanitize_text_field($_POST['year']);
        $password  = '12345';
        
        if (!empty($idnumber) && !empty($firstname) && !empty($lastname) && !empty($course) && !empty($year)) {

            // Insert into "user" table
            $user_insert = $db->insert(
                'user',
                array(
                    'user'   => $idnumber,
                    'course_id' => $course,
                    'year'      => $year,
                    'password'  => $password
                ),
                array('%s', '%d', '%s', '%s')
            );

            // Insert into "alumni" table
            $alumni_insert = $db->insert(
                'alumni',
                array(
                    'user_id'   => $idnumber,
                    'firstname' => $firstname,
                    'lastname'  => $lastname,
                    'course_id' => $course,
                    'year'      => $year
                ),
                array('%s', '%s', '%s', '%d', '%s')
            );

            if ($user_insert && $alumni_insert) {
                $success_message = "Registration successful!";
            } else {
                $error_message = "Error inserting data. Please check database connection or table structure.";
            }

        } else {
            $error_message = "All fields are required.";
        }
    }

    ?>

    <div class="alumni-registration-form" style="max-width:500px; margin:auto;">
        <h3>Alumni Registration</h3>

        <?php if (!empty($success_message)): ?>
            <div style="color: green; font-weight: bold;"><?php echo esc_html($success_message); ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div style="color: red; font-weight: bold;"><?php echo esc_html($error_message); ?></div>
        <?php endif; ?>

        <form method="post">
            <p>
                <label for="idnumber">ID Number:</label><br>
                <input type="text" name="idnumber" required>
            </p>

            <p>
                <label for="firstname">First Name:</label><br>
                <input type="text" name="firstname" required>
            </p>

            <p>
                <label for="lastname">Last Name:</label><br>
                <input type="text" name="lastname" required>
            </p>

            <p>
                <label for="course">Course:</label><br>
                <select name="course" required>
                    <option value="">-- Select Course --</option>
                    <option value="1">BSIT</option>
                    <option value="2">BSCS</option>
                    <option value="3">BSECE</option>
                    <option value="4">BSCE</option>
                    <option value="5">BSEE</option>
                    <option value="6">BSME</option>
                </select>
            </p>

            <p>
                <label for="year">Year:</label><br>
                <input type="text" name="year" required>
            </p>

            <p>
                <input type="submit" name="alumni_submit" value="Register">
            </p>
        </form>
    </div>

    <?php

    return ob_get_clean();
}
add_shortcode('alumni_register_form', 'alumni_register_form_shortcode');
?>


