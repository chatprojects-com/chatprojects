<?php
/**
 * Project Shell Template
 *
 * Main layout for project frontend
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

call_user_func(function () {
    $project_id = get_the_ID();
    $project = get_post($project_id);

    // Check access
    if (!ChatProjects\Access::can_access_project($project_id)) {
        wp_die(esc_html__('You do not have permission to access this project.', 'chatprojects'));
    }

    // Get project metadata (no assistant_id needed with Responses API)
    $vector_store_id = get_post_meta($project_id, '_cp_vector_store_id', true);
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($project->post_title); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
</head>

<body class="chatprojects-project">
    <!-- Mobile Header -->
    <header class="vp-mobile-header">
        <button id="vp-mobile-menu-toggle" class="vp-mobile-header-btn" aria-label="<?php esc_attr_e('Toggle menu', 'chatprojects'); ?>">
            <svg class="w-5 h-5" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <div class="vp-mobile-header-logo-wrapper">
            <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="vp-mobile-header-logo">
                <span class="vp-logo-vector">Chat</span><span class="vp-logo-projects">Projects</span>
            </a>
        </div>
    </header>

    <!-- Mobile Overlay -->
    <div id="vp-sidebar-overlay" class="vp-sidebar-overlay"></div>

    <div id="vp-project-wrapper" class="vp-wrapper" data-project-id="<?php echo esc_attr($project_id); ?>">
        
        <!-- Sidebar -->
        <aside id="vp-sidebar" class="vp-sidebar">
            <!-- Sidebar Header: ChatProjects Branding -->
            <div class="vp-sidebar-header" style="background: #ffffff; padding: 1rem; border-bottom: 1px solid #e5e7eb;">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                    <!-- Left: Logo -->
                    <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="vp-logo">
                        <span class="vp-logo-vector">Chat</span><span class="vp-logo-projects">Projects</span>
                    </a>
                    <!-- Right: Navigation Icons -->
                    <div class="vp-nav-icons">
                        <!-- Projects (Grid) -->
                        <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="vp-nav-icon active" title="<?php esc_attr_e('Projects', 'chatprojects'); ?>">
                            <svg class="w-5 h-5" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                            </svg>
                        </a>
                        <!-- Chat -->
                        <a href="<?php echo esc_url(home_url('/pro-chat/')); ?>" class="vp-nav-icon" title="<?php esc_attr_e('Chat', 'chatprojects'); ?>">
                            <svg class="w-5 h-5" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </a>
                        <!-- Compare (Pro Only - Disabled) -->
                        <span class="vp-nav-icon disabled vp-pro-badge-nav" title="<?php esc_attr_e('Compare - Pro feature', 'chatprojects'); ?>">
                            <svg class="w-5 h-5" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                            </svg>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Project Switcher Dropdown -->
            <div id="vp-project-switcher-dropdown" class="vp-project-switcher-dropdown vp-hidden">
                <div class="vp-switcher-header">
                    <h3><?php esc_html_e('Switch Project', 'chatprojects'); ?></h3>
                </div>
                <div class="vp-switcher-search">
                    <input type="text" id="vp-project-search" placeholder="<?php esc_html_e('Search projects...', 'chatprojects'); ?>" class="vp-input">
                </div>
                <div class="vp-switcher-list" id="vp-switcher-projects">
                    <?php
                    $user_projects = get_posts(array(
                        'post_type' => 'chatpr_project',
                        'author' => get_current_user_id(),
                        'posts_per_page' => -1,
                        'orderby' => 'modified',
                        'order' => 'DESC',
                        'post_status' => 'publish'
                    ));
                    
                    foreach ($user_projects as $user_project) :
                        $is_current = ($user_project->ID == $project_id);
                        $project_categories = wp_get_post_terms($user_project->ID, 'chatpr_project_category');
                    ?>
                        <a href="<?php echo esc_url( get_permalink( $user_project->ID ) ); ?>"
                           class="vp-switcher-item <?php echo $is_current ? 'active' : ''; ?>"
                           data-project-id="<?php echo esc_attr($user_project->ID); ?>">
                            <div class="vp-switcher-item-icon">
                                <span class="dashicons dashicons-portfolio"></span>
                            </div>
                            <div class="vp-switcher-item-content">
                                <div class="vp-switcher-item-title"><?php echo esc_html($user_project->post_title); ?></div>
                                <div class="vp-switcher-item-meta">
                                    <?php if (!empty($project_categories) && !is_wp_error($project_categories)) : ?>
                                        <span class="vp-category-badge"><?php echo esc_html($project_categories[0]->name); ?></span>
                                    <?php else : ?>
                                        <span class="vp-uncategorized"><?php esc_html_e('Uncategorized', 'chatprojects'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($is_current) : ?>
                                <span class="vp-switcher-item-check">
                                    <span class="dashicons dashicons-yes"></span>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="vp-switcher-footer">
                    <button id="vp-view-all-projects-btn" class="vp-btn-secondary vp-btn-block">
                        <span class="dashicons dashicons-admin-multisite"></span>
                        <?php esc_html_e('View All Projects', 'chatprojects'); ?>
                    </button>
                    <button id="vp-new-project-btn" class="vp-btn-primary vp-btn-block">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('New Project', 'chatprojects'); ?>
                    </button>

                    <!-- Divider -->
                    <div style="border-top: 1px solid #ddd; margin: 12px 0;"></div>

                    <!-- Chat Button -->
                    <a href="<?php echo esc_url(home_url('/pro-chat/')); ?>" class="vp-btn-secondary vp-btn-block" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <span class="dashicons dashicons-format-chat"></span>
                        <?php esc_html_e('Chat', 'chatprojects'); ?>
                    </a>
                </div>
            </div>

            <nav class="vp-sidebar-nav vp-sidebar-nav-compact">
                <a href="#chat" class="vp-nav-item active" data-tab="chat">
                    <span class="dashicons dashicons-portfolio"></span>
                    <span>Project Assistant</span>
                </a>
                <a href="#files" class="vp-nav-item" data-tab="files">
                    <span class="dashicons dashicons-media-document"></span>
                    <span>Files</span>
                </a>
                <!-- Transcriber (Pro Only - Disabled) -->
                <span class="vp-nav-item disabled vp-pro-badge" title="<?php esc_attr_e('Transcriber (Pro)', 'chatprojects'); ?>">
                    <span class="dashicons dashicons-microphone"></span>
                    <span><?php esc_html_e('Transcriber', 'chatprojects'); ?></span>
                </span>
                <!-- Prompts (Pro Only - Disabled) -->
                <span class="vp-nav-item disabled vp-pro-badge" title="<?php esc_attr_e('Prompts (Pro)', 'chatprojects'); ?>">
                    <span class="dashicons dashicons-editor-code"></span>
                    <span><?php esc_html_e('Prompts', 'chatprojects'); ?></span>
                </span>
            </nav>

            <!-- New Chat Button -->
            <div class="vp-sidebar-new-chat" style="padding: 0 1rem;">
                <button id="vp-new-chat-btn-sidebar" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span><?php esc_html_e('New Chat', 'chatprojects'); ?></span>
                </button>
            </div>

            <!-- Chat History List -->
            <div class="vp-sidebar-chat-history">
                <div id="vp-chat-list-sidebar" class="vp-chat-list-sidebar">
                    <div class="vp-chat-list-loading">
                        <span class="dashicons dashicons-update spinning"></span>
                        <span><?php esc_html_e('Loading...', 'chatprojects'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Context Menu for Chat Actions -->
            <div id="vp-chat-context-menu-sidebar" class="vp-context-menu" style="display: none;">
                <button class="vp-context-menu-item" data-action="rename">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e('Rename', 'chatprojects'); ?>
                </button>
                <button class="vp-context-menu-item vp-context-menu-danger" data-action="delete">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Delete', 'chatprojects'); ?>
                </button>
            </div>

            <!-- Delete Confirmation Modal -->
            <div id="vp-delete-modal-sidebar" class="vp-modal" style="display: none !important;">
                <div class="vp-modal-overlay"></div>
                <div class="vp-modal-content">
                    <h3><?php esc_html_e('Delete Chat?', 'chatprojects'); ?></h3>
                    <p><?php esc_html_e('This will delete this chat and all its messages. This action cannot be undone.', 'chatprojects'); ?></p>
                    <div class="vp-modal-actions">
                        <button id="vp-cancel-delete-sidebar" class="vp-btn-secondary"><?php esc_html_e('Cancel', 'chatprojects'); ?></button>
                        <button id="vp-confirm-delete-sidebar" class="vp-btn-danger"><?php esc_html_e('Delete', 'chatprojects'); ?></button>
                    </div>
                </div>
            </div>

            <div class="vp-sidebar-footer" style="padding: 1rem 1.25rem; border-top: 1px solid #e5e7eb;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <!-- Theme Toggle -->
                    <button
                        id="vp-theme-toggle"
                        class="vp-nav-icon"
                        title="<?php esc_html_e('Toggle theme', 'chatprojects'); ?>"
                    >
                        <svg class="w-5 h-5" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <!-- Settings -->
                        <a href="<?php echo esc_url(home_url('/settings/')); ?>" class="vp-nav-icon" title="<?php esc_html_e('Settings', 'chatprojects'); ?>">
                            <svg class="w-5 h-5" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </a>
                        <!-- Logout -->
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="vp-nav-icon" title="<?php esc_html_e('Logout', 'chatprojects'); ?>">
                            <svg class="w-5 h-5" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main id="vp-main-content" class="vp-main-content">
            
            <!-- Chat Tab -->
            <div id="vp-tab-chat" class="vp-tab-content active">
                <?php include CHATPROJECTS_PLUGIN_DIR . 'public/templates/chat-interface.php'; ?>
            </div>

            <!-- Files Tab -->
            <div id="vp-tab-files" class="vp-tab-content">
                <?php include CHATPROJECTS_PLUGIN_DIR . 'public/templates/file-manager-ui.php'; ?>
            </div>

        </main>
    
    <!-- All Projects Modal -->
    <div id="vp-all-projects-modal" class="vp-modal vp-hidden">
        <div class="vp-modal-overlay"></div>
        <div class="vp-modal-dialog vp-projects-modal-dialog">
            <div class="vp-modal-header">
                <h2><?php esc_html_e('All Projects', 'chatprojects'); ?></h2>
                <button id="vp-close-projects-modal" class="vp-modal-close" aria-label="<?php esc_attr_e('Close', 'chatprojects'); ?>" style="width: 36px !important; height: 36px !important; min-width: 36px !important; background: #fff !important; border: 1px solid #ddd !important; border-radius: 8px !important; display: flex !important; align-items: center !important; justify-content: center !important; padding: 0 !important; cursor: pointer !important; font-size: 24px !important; line-height: 1 !important; color: #374151 !important;">
                    ×
                </button>
            </div>
            
            <div class="vp-modal-search">
                <input 
                    type="text" 
                    id="vp-modal-project-search" 
                    class="vp-input" 
                    placeholder="<?php esc_attr_e('Search projects...', 'chatprojects'); ?>"
                    autocomplete="off"
                >
            </div>
            
            <div class="vp-modal-body">
                <div id="vp-modal-projects-grid" class="vp-projects-grid">
                    <?php
                    // Get all user projects for the modal
                    $all_user_projects = get_posts(array(
                        'post_type' => 'chatpr_project',
                        'author' => get_current_user_id(),
                        'posts_per_page' => -1,
                        'orderby' => 'modified',
                        'order' => 'DESC',
                        'post_status' => 'publish'
                    ));
                    
                    if (!empty($all_user_projects)) :
                        foreach ($all_user_projects as $modal_project) :
                            $is_current = ($modal_project->ID == $project_id);
                            $project_categories = wp_get_post_terms($modal_project->ID, 'chatpr_project_category');
                            $last_modified = human_time_diff(strtotime($modal_project->post_modified), current_time('timestamp'));
                        ?>
                            <div class="vp-project-card <?php echo $is_current ? 'vp-project-card-active' : ''; ?>"
                                 data-project-id="<?php echo esc_attr($modal_project->ID); ?>"
                                 data-project-name="<?php echo esc_attr(strtolower($modal_project->post_title)); ?>">
                                <a href="<?php echo esc_url( get_permalink( $modal_project->ID ) ); ?>" class="vp-project-card-link">
                                    <div class="vp-project-card-header">
                                        <div class="vp-project-card-icon">
                                            <span class="dashicons dashicons-portfolio"></span>
                                        </div>
                                        <?php if ($is_current) : ?>
                                            <span class="vp-project-card-badge"><?php esc_html_e('Current', 'chatprojects'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="vp-project-card-body">
                                        <h3 class="vp-project-card-title"><?php echo esc_html($modal_project->post_title); ?></h3>
                                        <div class="vp-project-card-meta">
                                            <?php if (!empty($project_categories) && !is_wp_error($project_categories)) : ?>
                                                <span class="vp-project-card-category">
                                                    <span class="dashicons dashicons-category"></span>
                                                    <?php echo esc_html($project_categories[0]->name); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="vp-project-card-updated">
                                                <span class="dashicons dashicons-clock"></span>
                                                <?php
                                                // translators: %s is a human-readable time difference (e.g., "2 hours", "3 days")
                                                printf( esc_html__('Modified %s ago', 'chatprojects'), esc_html($last_modified) );
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                                <button class="vp-project-card-delete"
                                        data-project-id="<?php echo esc_attr($modal_project->ID); ?>"
                                        data-project-name="<?php echo esc_attr($modal_project->post_title); ?>"
                                        title="<?php esc_attr_e('Delete project', 'chatprojects'); ?>"
                                        aria-label="<?php esc_attr_e('Delete project', 'chatprojects'); ?>"
                                        style="position: absolute !important; top: 8px !important; right: 8px !important; width: 28px !important; height: 28px !important; min-width: 28px !important; min-height: 28px !important; padding: 5px !important; background: #ffffff !important; border: 1px solid #d1d5db !important; border-radius: 6px !important; display: flex !important; align-items: center !important; justify-content: center !important; cursor: pointer !important; z-index: 999999 !important; transition: all 0.2s !important; pointer-events: auto !important;">
                                    <svg width="14" height="16" viewBox="0 0 14 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="pointer-events: none;">
                                        <path d="M1 4H13M5 1H9M5 7V13M9 7V13" stroke="#6b7280" stroke-width="1.5" stroke-linecap="round"/>
                                        <path d="M2 4L3 14C3 14.5 3.5 15 4 15H10C10.5 15 11 14.5 11 14L12 4H2Z" stroke="#6b7280" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="vp-projects-empty">
                            <span class="dashicons dashicons-portfolio"></span>
                            <p><?php esc_html_e('No projects found', 'chatprojects'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div id="vp-modal-no-results" class="vp-projects-empty vp-hidden">
                    <span class="dashicons dashicons-search"></span>
                    <p><?php esc_html_e('No projects match your search', 'chatprojects'); ?></p>
                </div>
            </div>
        </div>
    
    <!-- Delete Project Confirmation Modal -->
    <div id="vp-delete-project-modal" class="vp-modal vp-hidden" style="display: none;">
        <div class="vp-modal-overlay"></div>
        <div class="vp-modal-dialog vp-delete-modal-dialog">
            <div class="vp-modal-header">
                <h2><?php esc_html_e('Delete Project', 'chatprojects'); ?></h2>
                <button id="vp-close-delete-modal" class="vp-modal-close" aria-label="<?php esc_attr_e('Close', 'chatprojects'); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>

            <div class="vp-modal-body vp-delete-modal-body">
                <div class="vp-delete-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <h3><?php esc_html_e('Are you sure?', 'chatprojects'); ?></h3>
                    <p id="vp-delete-project-message"><?php esc_html_e('This will permanently delete the project and all associated data including:', 'chatprojects'); ?></p>
                    <ul class="vp-delete-list">
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('All chat messages and history', 'chatprojects'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Vector Store and uploaded files', 'chatprojects'); ?></li>
                        <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('All project settings', 'chatprojects'); ?></li>
                    </ul>
                    <p class="vp-delete-final-warning"><?php esc_html_e('This action cannot be undone.', 'chatprojects'); ?></p>
                </div>

                <div class="vp-modal-actions">
                    <button id="vp-cancel-delete-project" class="vp-btn-secondary">
                        <?php esc_html_e('Cancel', 'chatprojects'); ?>
                    </button>
                    <button id="vp-confirm-delete-project" class="vp-btn-danger">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Delete Project', 'chatprojects'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>

    <?php
    // Project shell event handlers - using wp_print_inline_script_tag for WordPress compliance
    $delete_msg_template = esc_js(__('This will permanently delete', 'chatprojects'));
    $deleting_text = esc_js(__('Deleting...', 'chatprojects'));
    $delete_project_text = esc_js(__('Delete Project', 'chatprojects'));
    $error_delete = esc_js(__('Failed to delete project', 'chatprojects'));
    $error_general = esc_js(__('An error occurred. Please try again.', 'chatprojects'));

    $shell_script = "jQuery(document).ready(function($) {
        // Mobile menu toggle
        $('#vp-mobile-menu-toggle').on('click', function() {
            $('#vp-sidebar').toggleClass('open');
            $('#vp-sidebar-overlay').toggleClass('open');
        });

        // Sidebar overlay click
        $('#vp-sidebar-overlay').on('click', function() {
            $('#vp-sidebar').removeClass('open');
            $(this).removeClass('open');
        });

        // Theme toggle
        $('#vp-theme-toggle').on('click', function() {
            window.VPTheme?.toggle();
        });

        // Delete project card buttons (event delegation)
        $(document).on('click', '.vp-project-card-delete', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var projectId = $(this).data('project-id');
            var projectName = $(this).data('project-name');
            $('#vp-delete-project-message').html('{$delete_msg_template} <strong>\"' + projectName + '\"</strong> and all associated data including:');
            $('#vp-delete-project-modal').removeClass('vp-hidden').data('project-id', projectId).data('project-name', projectName);
        });

        // Close delete modal
        $('#vp-close-delete-modal, #vp-cancel-delete-project').on('click', function() {
            $('#vp-delete-project-modal').addClass('vp-hidden').removeData('project-id').removeData('project-name');
        });

        // Confirm delete project
        $('#vp-confirm-delete-project').on('click', function() {
            var modal = $('#vp-delete-project-modal');
            var projectId = modal.data('project-id');
            if (!projectId) return;
            var btn = $(this);
            btn.prop('disabled', true).html('<span class=\"dashicons dashicons-update\"></span> {$deleting_text}');
            $.ajax({
                url: chatprAjax.ajaxUrl,
                type: 'POST',
                data: { action: 'chatpr_delete_project', nonce: chatprAjax.nonce, project_id: projectId },
                success: function(response) {
                    if (response.success) {
                        $('.vp-project-card[data-project-id=\"' + projectId + '\"]').fadeOut(300, function() {
                            $(this).remove();
                            var currentProjectId = $('#vp-project-wrapper').data('project-id');
                            if (projectId == currentProjectId) {
                                window.location.href = '/chatpr_project/';
                            } else {
                                modal.addClass('vp-hidden').removeData('project-id').removeData('project-name');
                                btn.prop('disabled', false).html('<span class=\"dashicons dashicons-trash\"></span> {$delete_project_text}');
                            }
                        });
                    } else {
                        var errorMsg = '{$error_delete}';
                        if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        }
                        alert(errorMsg);
                        btn.prop('disabled', false).html('<span class=\"dashicons dashicons-trash\"></span> {$delete_project_text}');
                    }
                },
                error: function() {
                    alert('{$error_general}');
                    btn.prop('disabled', false).html('<span class=\"dashicons dashicons-trash\"></span> {$delete_project_text}');
                }
            });
        });
    });";
    wp_print_inline_script_tag($shell_script, array('id' => 'chatprojects-project-shell'));
    ?>

    <?php wp_footer(); ?>
</body>
</html>
<?php
});
