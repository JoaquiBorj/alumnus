<?php
/**
 * Skills Manager - Admin page to add, edit, and delete skills
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Add submenu page for Skills Manager
 */
function alumnus_skills_admin_menu() {
    add_submenu_page(
        'alumnus-add-alumni',           // Parent slug (existing Alumnus menu)
        __('Manage Skills', 'alumnus'), // Page title
        __('Skills', 'alumnus'),        // Menu title
        'manage_options',               // Capability
        'alumnus-manage-skills',        // Menu slug
        'alumnus_render_skills_page'    // Callback function
    );
}
add_action('admin_menu', 'alumnus_skills_admin_menu');

/**
 * Enqueue admin styles and scripts for Skills Manager
 */
function alumnus_skills_admin_scripts($hook) {
    if ($hook !== 'alumnus_page_alumnus-manage-skills') return;
    
    wp_add_inline_style('wp-admin', '
        .skills-manager-wrap { margin: 20px; }
        .skills-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .skills-stats { background: #fff; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px; }
        .skills-table { background: #fff; border: 1px solid #c3c4c7; }
        .skills-table th { background: #f6f7f7; padding: 12px; text-align: left; font-weight: 600; }
        .skills-table td { padding: 12px; border-top: 1px solid #c3c4c7; }
        .skills-table tr:hover { background: #f6f7f7; }
        .skill-actions { display: flex; gap: 10px; }
        .skill-actions a { text-decoration: none; }
        .skill-actions .delete { color: #b32d2e; }
        .skill-actions .delete:hover { color: #8a2424; }
        .alumnus-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .alumnus-modal-content { background-color: #fff; margin: 10% auto; padding: 0; border: 1px solid #c3c4c7; width: 90%; max-width: 500px; border-radius: 4px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .alumnus-modal-header { padding: 15px 20px; border-bottom: 1px solid #c3c4c7; display: flex; justify-content: space-between; align-items: center; }
        .alumnus-modal-body { padding: 20px; }
        .alumnus-modal-footer { padding: 15px 20px; border-top: 1px solid #c3c4c7; text-align: right; }
        .alumnus-close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 20px; }
        .alumnus-close:hover { color: #000; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input { width: 100%; padding: 8px; border: 1px solid #c3c4c7; border-radius: 3px; }
        .search-box { margin-bottom: 15px; }
        .search-box input { padding: 8px 12px; width: 300px; border: 1px solid #c3c4c7; border-radius: 3px; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a, .pagination span { padding: 5px 10px; margin: 0 2px; border: 1px solid #c3c4c7; display: inline-block; text-decoration: none; }
        .pagination .current { background: #2271b1; color: #fff; border-color: #2271b1; }
        .notice-success { background: #d7f0db; border-left: 4px solid #00a32a; padding: 12px; margin: 15px 0; }
        .notice-error { background: #fcf0f1; border-left: 4px solid #d63638; padding: 12px; margin: 15px 0; }
    ');
    
    wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            // Open Add Modal
            $("#add-skill-btn").click(function() {
                $("#add-skill-modal").show();
                $("#skill-name-input").val("");
                $("#skill-name-input").focus();
            });
            
            // Open Edit Modal
            $(".edit-skill-btn").click(function() {
                var skillId = $(this).data("id");
                var skillName = $(this).data("name");
                $("#edit-skill-id").val(skillId);
                $("#edit-skill-name").val(skillName);
                $("#edit-skill-modal").show();
            });
            
            // Close Modal
            $(".alumnus-close, .cancel-btn").click(function() {
                $(".alumnus-modal").hide();
            });
            
            // Close modal when clicking outside
            $(window).click(function(e) {
                if ($(e.target).hasClass("alumnus-modal")) {
                    $(".alumnus-modal").hide();
                }
            });
            
            // Delete Skill Confirmation
            $(".delete-skill-btn").click(function(e) {
                var skillName = $(this).data("name");
                var usageCount = $(this).data("usage");
                var message = "Are you sure you want to delete the skill: " + skillName + "?";
                if (usageCount > 0) {
                    message += "\\n\\nWarning: This skill is currently used by " + usageCount + " alumni. It will be removed from their profiles.";
                }
                return confirm(message);
            });
        });
    ');
}
add_action('admin_enqueue_scripts', 'alumnus_skills_admin_scripts');

/**
 * Handle Add Skill Form Submission
 */
function alumnus_handle_add_skill() {
    if (!isset($_POST['alumnus_add_skill_nonce']) || !wp_verify_nonce($_POST['alumnus_add_skill_nonce'], 'alumnus_add_skill_action')) {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $skill_name = isset($_POST['skill_name']) ? sanitize_text_field(trim($_POST['skill_name'])) : '';
    
    if (empty($skill_name)) {
        add_settings_error('alumnus_skills', 'empty_skill', __('Skill name cannot be empty.', 'alumnus'), 'error');
        return;
    }
    
    // Check if skill already exists
    $exists = $wpdb->get_var($wpdb->prepare("SELECT skill_id FROM skills WHERE skill = %s", $skill_name));
    
    if ($exists) {
        add_settings_error('alumnus_skills', 'duplicate_skill', __('This skill already exists.', 'alumnus'), 'error');
        return;
    }
    
    // Insert new skill
    $result = $wpdb->insert('skills', array('skill' => $skill_name), array('%s'));
    
    if ($result) {
        add_settings_error('alumnus_skills', 'skill_added', __('Skill added successfully!', 'alumnus'), 'success');
    } else {
        add_settings_error('alumnus_skills', 'skill_error', __('Error adding skill. Please try again.', 'alumnus'), 'error');
    }
}

/**
 * Handle Edit Skill Form Submission
 */
function alumnus_handle_edit_skill() {
    if (!isset($_POST['alumnus_edit_skill_nonce']) || !wp_verify_nonce($_POST['alumnus_edit_skill_nonce'], 'alumnus_edit_skill_action')) {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    $skill_id = isset($_POST['skill_id']) ? intval($_POST['skill_id']) : 0;
    $skill_name = isset($_POST['skill_name']) ? sanitize_text_field(trim($_POST['skill_name'])) : '';
    
    if ($skill_id <= 0 || empty($skill_name)) {
        add_settings_error('alumnus_skills', 'invalid_data', __('Invalid skill data.', 'alumnus'), 'error');
        return;
    }
    
    // Check if new name already exists (but not for the same skill)
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT skill_id FROM skills WHERE skill = %s AND skill_id != %d", 
        $skill_name, 
        $skill_id
    ));
    
    if ($exists) {
        add_settings_error('alumnus_skills', 'duplicate_skill', __('This skill name already exists.', 'alumnus'), 'error');
        return;
    }
    
    // Update skill
    $result = $wpdb->update(
        'skills',
        array('skill' => $skill_name),
        array('skill_id' => $skill_id),
        array('%s'),
        array('%d')
    );
    
    if ($result !== false) {
        add_settings_error('alumnus_skills', 'skill_updated', __('Skill updated successfully!', 'alumnus'), 'success');
    } else {
        add_settings_error('alumnus_skills', 'skill_error', __('Error updating skill. Please try again.', 'alumnus'), 'error');
    }
}

/**
 * Handle Delete Skill
 */
function alumnus_handle_delete_skill() {
    if (!isset($_GET['alumnus_delete_skill_nonce']) || !wp_verify_nonce($_GET['alumnus_delete_skill_nonce'], 'alumnus_delete_skill_' . $_GET['skill_id'])) {
        wp_die(__('Security check failed', 'alumnus'));
    }
    
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to perform this action', 'alumnus'));
    }
    
    $skill_id = isset($_GET['skill_id']) ? intval($_GET['skill_id']) : 0;
    
    if ($skill_id <= 0) {
        add_settings_error('alumnus_skills', 'invalid_id', __('Invalid skill ID.', 'alumnus'), 'error');
        return;
    }
    
    global $wpdb;
    
    // Delete skill (CASCADE will automatically remove from alumni_skills)
    $result = $wpdb->delete('skills', array('skill_id' => $skill_id), array('%d'));
    
    if ($result) {
        add_settings_error('alumnus_skills', 'skill_deleted', __('Skill deleted successfully!', 'alumnus'), 'success');
    } else {
        add_settings_error('alumnus_skills', 'skill_error', __('Error deleting skill. Please try again.', 'alumnus'), 'error');
    }
    
    // Redirect to remove query params
    wp_redirect(admin_url('admin.php?page=alumnus-manage-skills'));
    exit;
}

/**
 * Process form submissions
 */
function alumnus_skills_process_actions() {
    if (isset($_POST['alumnus_add_skill'])) {
        alumnus_handle_add_skill();
    }
    
    if (isset($_POST['alumnus_edit_skill'])) {
        alumnus_handle_edit_skill();
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['skill_id'])) {
        alumnus_handle_delete_skill();
    }
}
add_action('admin_init', 'alumnus_skills_process_actions');

/**
 * Render the Skills Manager admin page
 */
function alumnus_render_skills_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    global $wpdb;
    
    // Get search query
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Build query
    $where = '';
    $params = array();
    if (!empty($search)) {
        $where = "WHERE skill LIKE %s";
        $params[] = '%' . $wpdb->esc_like($search) . '%';
    }
    
    // Get total count
    $total_sql = "SELECT COUNT(*) FROM skills $where";
    $total_count = !empty($params) 
        ? $wpdb->get_var($wpdb->prepare($total_sql, $params))
        : $wpdb->get_var($total_sql);
    
    // Get skills with usage count
    $sql = "SELECT s.skill_id, s.skill, 
            COUNT(aks.user_id) as usage_count
            FROM skills s
            LEFT JOIN alumni_skills aks ON s.skill_id = aks.skill_id
            $where
            GROUP BY s.skill_id, s.skill
            ORDER BY s.skill ASC
            LIMIT %d OFFSET %d";
    
    $params[] = $per_page;
    $params[] = $offset;
    
    $skills = $wpdb->get_results($wpdb->prepare($sql, $params));
    
    $total_pages = ceil($total_count / $per_page);
    
    // Get overall statistics
    $total_skills = $wpdb->get_var("SELECT COUNT(*) FROM skills");
    $total_used = $wpdb->get_var("SELECT COUNT(DISTINCT skill_id) FROM alumni_skills");
    $total_unused = $total_skills - $total_used;
    
    ?>
    <div class="wrap skills-manager-wrap">
        <div class="skills-header">
            <h1><?php echo esc_html__('Manage Skills', 'alumnus'); ?></h1>
            <button type="button" id="add-skill-btn" class="button button-primary">
                <?php echo esc_html__('Add New Skill', 'alumnus'); ?>
            </button>
        </div>
        
        <?php settings_errors('alumnus_skills'); ?>
        
        <!-- Statistics -->
        <div class="skills-stats">
            <strong><?php echo esc_html__('Statistics:', 'alumnus'); ?></strong>
            <?php echo sprintf(
                __('Total Skills: %d | Used: %d | Unused: %d', 'alumnus'),
                $total_skills,
                $total_used,
                $total_unused
            ); ?>
        </div>
        
        <!-- Search Box -->
        <div class="search-box">
            <form method="get">
                <input type="hidden" name="page" value="alumnus-manage-skills">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr__('Search skills...', 'alumnus'); ?>">
                <button type="submit" class="button"><?php echo esc_html__('Search', 'alumnus'); ?></button>
                <?php if (!empty($search)): ?>
                    <a href="<?php echo admin_url('admin.php?page=alumnus-manage-skills'); ?>" class="button"><?php echo esc_html__('Clear', 'alumnus'); ?></a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Skills Table -->
        <?php if (empty($skills)): ?>
            <p><?php echo esc_html__('No skills found.', 'alumnus'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped skills-table">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php echo esc_html__('ID', 'alumnus'); ?></th>
                        <th><?php echo esc_html__('Skill Name', 'alumnus'); ?></th>
                        <th style="width: 120px;"><?php echo esc_html__('Used By', 'alumnus'); ?></th>
                        <th style="width: 150px;"><?php echo esc_html__('Actions', 'alumnus'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($skills as $skill): ?>
                        <tr>
                            <td><?php echo esc_html($skill->skill_id); ?></td>
                            <td><strong><?php echo esc_html($skill->skill); ?></strong></td>
                            <td><?php echo esc_html(sprintf(__('%d alumni', 'alumnus'), $skill->usage_count)); ?></td>
                            <td class="skill-actions">
                                <a href="#" class="edit-skill-btn" 
                                   data-id="<?php echo esc_attr($skill->skill_id); ?>"
                                   data-name="<?php echo esc_attr($skill->skill); ?>">
                                    <?php echo esc_html__('Edit', 'alumnus'); ?>
                                </a>
                                <a href="<?php echo wp_nonce_url(
                                    admin_url('admin.php?page=alumnus-manage-skills&action=delete&skill_id=' . $skill->skill_id),
                                    'alumnus_delete_skill_' . $skill->skill_id,
                                    'alumnus_delete_skill_nonce'
                                ); ?>" 
                                   class="delete delete-skill-btn"
                                   data-name="<?php echo esc_attr($skill->skill); ?>"
                                   data-usage="<?php echo esc_attr($skill->usage_count); ?>">
                                    <?php echo esc_html__('Delete', 'alumnus'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    $base_url = admin_url('admin.php?page=alumnus-manage-skills');
                    if (!empty($search)) {
                        $base_url .= '&s=' . urlencode($search);
                    }
                    
                    for ($i = 1; $i <= $total_pages; $i++):
                        if ($i === $current_page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $base_url . '&paged=' . $i; ?>"><?php echo $i; ?></a>
                        <?php endif;
                    endfor;
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Add Skill Modal -->
        <div id="add-skill-modal" class="alumnus-modal">
            <div class="alumnus-modal-content">
                <div class="alumnus-modal-header">
                    <h2><?php echo esc_html__('Add New Skill', 'alumnus'); ?></h2>
                    <span class="alumnus-close">&times;</span>
                </div>
                <form method="post" action="">
                    <?php wp_nonce_field('alumnus_add_skill_action', 'alumnus_add_skill_nonce'); ?>
                    <div class="alumnus-modal-body">
                        <div class="form-group">
                            <label for="skill-name-input"><?php echo esc_html__('Skill Name', 'alumnus'); ?> <span style="color: red;">*</span></label>
                            <input type="text" id="skill-name-input" name="skill_name" required maxlength="100">
                        </div>
                    </div>
                    <div class="alumnus-modal-footer">
                        <button type="button" class="button cancel-btn"><?php echo esc_html__('Cancel', 'alumnus'); ?></button>
                        <button type="submit" name="alumnus_add_skill" class="button button-primary"><?php echo esc_html__('Add Skill', 'alumnus'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Edit Skill Modal -->
        <div id="edit-skill-modal" class="alumnus-modal">
            <div class="alumnus-modal-content">
                <div class="alumnus-modal-header">
                    <h2><?php echo esc_html__('Edit Skill', 'alumnus'); ?></h2>
                    <span class="alumnus-close">&times;</span>
                </div>
                <form method="post" action="">
                    <?php wp_nonce_field('alumnus_edit_skill_action', 'alumnus_edit_skill_nonce'); ?>
                    <input type="hidden" id="edit-skill-id" name="skill_id">
                    <div class="alumnus-modal-body">
                        <div class="form-group">
                            <label for="edit-skill-name"><?php echo esc_html__('Skill Name', 'alumnus'); ?> <span style="color: red;">*</span></label>
                            <input type="text" id="edit-skill-name" name="skill_name" required maxlength="100">
                        </div>
                    </div>
                    <div class="alumnus-modal-footer">
                        <button type="button" class="button cancel-btn"><?php echo esc_html__('Cancel', 'alumnus'); ?></button>
                        <button type="submit" name="alumnus_edit_skill" class="button button-primary"><?php echo esc_html__('Save Changes', 'alumnus'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

