<?php
/**
 * ChatProjects Main Admin Page
 * Dashboard overview for ChatProjects plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

call_user_func(function () {
    // Get statistics
    $project_count = wp_count_posts('chatpr_project');
    $published_projects = isset($project_count->publish) ? $project_count->publish : 0;
    $api_key_configured = get_option('chatprojects_openai_key') ? true : false;
    ?>

<div class="wrap vp-main-page">
    <h1><?php esc_html_e('ChatProjects Dashboard', 'chatprojects'); ?></h1>

    <?php if (!$api_key_configured) : ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php esc_html_e('OpenAI API Key Required', 'chatprojects'); ?></strong><br>
            <?php printf(
                wp_kses(
                    /* translators: %s: Settings URL */
                    __('Please configure your OpenAI API key in <a href="%s">Settings</a> to start using ChatProjects.', 'chatprojects'),
                    array( 'a' => array( 'href' => array() ) )
                ),
                esc_url( admin_url('admin.php?page=chatprojects-settings') )
            ); ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="vp-dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin: 2rem 0;">

        <!-- Settings -->
        <div class="vp-dashboard-card" style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="width: 50px; height: 50px; background: #fef3c7; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <span class="dashicons dashicons-admin-settings" style="font-size: 24px; color: #f59e0b;"></span>
                </div>
                <div>
                    <h2 style="margin: 0; font-size: 1.25rem; color: #1e293b;"><?php esc_html_e('Settings', 'chatprojects'); ?></h2>
                    <p style="margin: 0; color: #64748b;"><?php esc_html_e('Configure plugin', 'chatprojects'); ?></p>
                </div>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=chatprojects-settings')); ?>" class="button button-primary">
                <?php esc_html_e('Configure Settings', 'chatprojects'); ?>
            </a>
        </div>

        <!-- Projects Overview -->
        <div class="vp-dashboard-card" style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="width: 50px; height: 50px; background: #dbeafe; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <span class="dashicons dashicons-portfolio" style="font-size: 24px; color: #3b82f6;"></span>
                </div>
                <div>
                    <h2 style="margin: 0; font-size: 2rem; color: #1e293b; line-height: 1;"><?php echo esc_html($published_projects); ?></h2>
                    <p style="margin: 0; color: #64748b;"><?php esc_html_e('Projects', 'chatprojects'); ?></p>
                </div>
            </div>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=chatpr_project')); ?>" class="button button-primary">
                <?php esc_html_e('View All Projects', 'chatprojects'); ?>
            </a>
        </div>

    </div>

    <!-- Quick Start Guide -->
    <div class="vp-quick-start" style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin: 2rem 0;">
        <h2 style="margin-top: 0;"><?php esc_html_e('Quick Start Guide', 'chatprojects'); ?></h2>

        <div class="vp-steps" style="display: grid; gap: 1rem;">
            <div class="vp-step" style="display: flex; gap: 1rem; padding: 1rem; background: #f9fafb; border-radius: 6px;">
                <div style="width: 30px; height: 30px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600;">1</div>
                <div>
                    <h3 style="margin: 0 0 0.5rem 0;"><?php esc_html_e('Configure OpenAI API Key', 'chatprojects'); ?></h3>
                    <p style="margin: 0; color: #64748b;">
                        <?php esc_html_e('Go to Settings and enter your OpenAI API key to enable AI features.', 'chatprojects'); ?>
                        <?php if (!$api_key_configured): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=chatprojects-settings')); ?>" style="color: #3b82f6;">
                            <?php esc_html_e('Configure Now', 'chatprojects'); ?> →
                        </a>
                        <?php else: ?>
                        <span style="color: #10b981;">✓ <?php esc_html_e('Completed', 'chatprojects'); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="vp-step" style="display: flex; gap: 1rem; padding: 1rem; background: #f9fafb; border-radius: 6px;">
                <div style="width: 30px; height: 30px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600;">2</div>
                <div>
                    <h3 style="margin: 0 0 0.5rem 0;"><?php esc_html_e('Visit the Projects Page', 'chatprojects'); ?></h3>
                    <p style="margin: 0; color: #64748b;">
                        <?php esc_html_e('Go to https://yourdomain.com/projects/ - if you see a 404 page not found, go to Settings > Permalinks and click Save Changes.', 'chatprojects'); ?>
                    </p>
                </div>
            </div>

            <div class="vp-step" style="display: flex; gap: 1rem; padding: 1rem; background: #f9fafb; border-radius: 6px;">
                <div style="width: 30px; height: 30px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600;">3</div>
                <div>
                    <h3 style="margin: 0 0 0.5rem 0;"><?php esc_html_e('Create Your First Project', 'chatprojects'); ?></h3>
                    <p style="margin: 0; color: #64748b;">
                        <?php esc_html_e('Projects are created from the frontend chat interface. Visit the frontend to create projects.', 'chatprojects'); ?>
                    </p>
                </div>
            </div>

            <div class="vp-step" style="display: flex; gap: 1rem; padding: 1rem; background: #f9fafb; border-radius: 6px;">
                <div style="width: 30px; height: 30px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600;">4</div>
                <div>
                    <h3 style="margin: 0 0 0.5rem 0;"><?php esc_html_e('Start Using Features', 'chatprojects'); ?></h3>
                    <p style="margin: 0; color: #64748b;">
                        <?php esc_html_e('Chat with AI and upload files to Vector Stores.', 'chatprojects'); ?>
                    </p>
                </div>
            </div>

            <div class="vp-step" style="display: flex; gap: 1rem; padding: 1rem; background: #f9fafb; border-radius: 6px;">
                <div style="width: 30px; height: 30px; background: #3b82f6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 600;">5</div>
                <div>
                    <h3 style="margin: 0 0 0.5rem 0;"><?php esc_html_e('User Roles & Permissions', 'chatprojects'); ?></h3>
                    <p style="margin: 0; color: #64748b;">
                        <?php esc_html_e('The following roles can access ChatProjects:', 'chatprojects'); ?>
                    </p>
                    <ul style="margin: 0.5rem 0 0 1rem; color: #64748b; padding-left: 0;">
                        <li><strong><?php esc_html_e('Administrator', 'chatprojects'); ?></strong> - <?php esc_html_e('Full access + settings management', 'chatprojects'); ?></li>
                        <li><strong><?php esc_html_e('Editor', 'chatprojects'); ?></strong> - <?php esc_html_e('Full access (no settings)', 'chatprojects'); ?></li>
                        <li><strong><?php esc_html_e('Author', 'chatprojects'); ?></strong> - <?php esc_html_e('Own projects only', 'chatprojects'); ?></li>
                        <li><strong><?php esc_html_e('Projects User', 'chatprojects'); ?></strong> - <?php esc_html_e('Frontend-only, own projects only', 'chatprojects'); ?></li>
                    </ul>
                    <p style="margin: 0.5rem 0 0 0; color: #64748b;">
                        <?php printf(
                            wp_kses(
                                /* translators: %s: URL to Users → Add New page */
                                __('Create new users with the "Projects User" role via <a href="%s">Users → Add New</a>.', 'chatprojects'),
                                array('a' => array('href' => array()))
                            ),
                            esc_url(admin_url('user-new.php'))
                        ); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php
});
