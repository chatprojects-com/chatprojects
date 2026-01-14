<?php
/**
 * Settings Page Template
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'chatprojects'));
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('chatprojects_settings');
        do_settings_sections('chatprojects-settings');
        submit_button();
        ?>
    </form>
    
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2><?php esc_html_e('Quick Start Guide', 'chatprojects'); ?></h2>
        <ol>
            <li><?php esc_html_e('Enter your OpenAI API key above and save settings', 'chatprojects'); ?></li>
            <li><?php esc_html_e('Visit https://yourdomain.com/projects/ - if you see a 404 page not found, go to Settings > Permalinks in WordPress admin and click Save Changes', 'chatprojects'); ?></li>
            <li><?php esc_html_e('Create a new project from the Projects menu', 'chatprojects'); ?></li>
            <li><?php esc_html_e('Upload files to the project\'s Vector Store', 'chatprojects'); ?></li>
            <li><?php esc_html_e('Start chatting with your AI assistant!', 'chatprojects'); ?></li>
        </ol>

        <h3><?php esc_html_e('Need Help?', 'chatprojects'); ?></h3>
        <p>
            <?php
            printf(
                /* translators: %s: Documentation URL */
                esc_html__('Visit our %s for detailed guides and tutorials.', 'chatprojects'),
                '<a href="https://chatprojects.com" target="_blank">' . esc_html__('documentation', 'chatprojects') . '</a>'
            );
            ?>
        </p>
    </div>

    <!-- Features Overview -->
    <div style="background: #f3f4f6; padding: 1.5rem; border-radius: 8px; margin-top: 20px; max-width: 600px;">
        <h2 style="margin: 0 0 1rem 0; font-size: 1.1rem;"><?php esc_html_e('Available Features', 'chatprojects'); ?></h2>
        <div style="font-family: monospace; font-size: 0.875rem; line-height: 1.8; color: #374151;">
            AI Chat (Free)<br>
            Vector Stores (Free)<br>
            Custom Instructions (Free)<br>
            Supported File Types (Free)<br>
            Transcription (Pro)<br>
            Prompt Library (Pro)<br>
            Custom Branding (Pro)<br>
            Live Web Search (Pro)<br>
            Embeddable Chatbot (Pro)<br>
            Custom PWA (Pro)<br>
            Image Studio (Pro)<br>
            Advanced Sharing (Pro)<br>
            More File Types (Pro)<br>
            Cloud Import and Sync (Pro)
        </div>
        <p style="margin: 1rem 0 0 0; font-size: 0.875rem; color: #6b7280;">
            <?php esc_html_e('For more information and to purchase Pro, visit', 'chatprojects'); ?> <a href="https://chatprojects.com" target="_blank" style="color: #4f46e5;">chatprojects.com</a>
        </p>
    </div>
</div>

