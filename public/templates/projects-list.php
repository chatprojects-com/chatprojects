<?php
/**
 * Modern Projects List Template
 *
 * Display all user projects with advanced features:
 * - Search and filter
 * - Grid/List view toggle
 * - Sort options
 * - Edit, Share, Delete modals
 * - Modern card design
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

call_user_func(function () {
// Check if user is logged in
if (!is_user_logged_in()) {
    include CHATPROJECTS_PLUGIN_DIR . 'public/templates/login-page.php';
    return;
}

$current_user = wp_get_current_user();
$user_id = get_current_user_id();

// Get user's own projects
$own_projects_args = array(
    'post_type' => 'chatpr_project',
    'author' => $user_id,
    'posts_per_page' => -1,
    'orderby' => 'modified',
    'order' => 'DESC',
    'post_status' => 'publish'
);

$own_projects = new WP_Query($own_projects_args);

global $wpdb;
$chats_table = esc_sql($wpdb->prefix . 'chatprojects_chats');

// Initialize Vector_Store for file counts
$vector_store = new \ChatProjects\Vector_Store();

// Combine projects
$all_projects = array();

if ($own_projects->have_posts()) {
    while ($own_projects->have_posts()) {
        $own_projects->the_post();
        $project_id = get_the_ID();

        // Get message count from chats table
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
        $message_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(message_count) FROM {$chats_table} WHERE project_id = %d",
                $project_id
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        // Get file count from post meta
        $file_count = $vector_store->get_file_count($project_id);

        // Get categories
        $categories = wp_get_post_terms($project_id, 'chatpr_project_category');

        $all_projects[] = array(
            'post' => get_post(),
            'is_owner' => true,
            'message_count' => intval($message_count),
            'file_count' => intval($file_count),
            'instructions' => get_post_meta($project_id, '_cp_instructions', true),
            'categories' => $categories
        );
    }
    wp_reset_postdata();
}

// Get all available categories
$all_categories = get_terms(array(
    'taxonomy' => 'chatpr_project_category',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC'
));

// Ensure $all_categories is an array (not WP_Error if taxonomy doesn't exist)
if (is_wp_error($all_categories) || !is_array($all_categories)) {
    $all_categories = array();
}

// Get user's theme preference
$theme_preference = get_user_meta($user_id, 'cp_theme_preference', true) ?: 'auto';

// Projects count (no limit)
$total_projects = count($all_projects);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="<?php echo esc_attr($theme_preference === 'dark' ? 'dark' : ''); ?>">
<?php
// Apply theme from localStorage immediately
// Using wp_print_inline_script_tag for WordPress guidelines compliance
$theme_init_script = "(function() {
    var theme = localStorage.getItem('cp_theme_preference') || '" . esc_js($theme_preference) . "';
    if (theme === 'dark') {
        document.documentElement.classList.add('dark');
    } else if (theme === 'auto' && window.matchMedia('(prefers-color-scheme:dark)').matches) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
})();";
wp_print_inline_script_tag($theme_init_script, array('id' => 'chatprojects-theme-init'));
?>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php esc_html_e('ChatProjects', 'chatprojects'); ?> - <?php bloginfo('name'); ?></title>
    <?php
    // Output chatprData directly in template to ensure it's always available
    $chatpr_inline_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('chatpr_ajax_nonce'),
        'current_user' => $current_user->display_name,
        'is_pro_user' => false,
    );
    // Output chatprData via wp_print_inline_script_tag for WordPress guidelines compliance
    $chatpr_data_script = 'var chatprData = ' . wp_json_encode($chatpr_inline_data) . ';';
    wp_print_inline_script_tag($chatpr_data_script, array('id' => 'chatprojects-inline-data'));

    wp_head(); // Theme init script and styles are enqueued via class-chatprojects.php
    ?>
</head>
<body class="bg-gray-100 dark:bg-dark-bg" x-data="projectsApp()">

    <!-- Mobile Header (replaces floating toggle) -->
    <header class="vp-mobile-header">
        <button @click="sidebarOpen = !sidebarOpen" class="vp-mobile-header-btn" aria-label="<?php esc_html_e('Toggle menu', 'chatprojects'); ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="vp-mobile-header-logo">
            <span class="cp-logo-chat">Chat</span><span class="cp-logo-projects">Projects</span>
        </a>
    </header>

    <!-- Mobile Overlay -->
    <div @click="sidebarOpen = false" class="vp-sidebar-overlay" :class="{ 'open': sidebarOpen }"></div>

    <div class="vp-app-layout">
        <!-- Sidebar -->
        <aside class="vp-sidebar" :class="{ 'open': sidebarOpen }">
            <!-- Sidebar Header -->
            <div class="cp-sidebar-header">
                <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="cp-logo">
                    <span class="cp-logo-chat">Chat</span><span class="cp-logo-projects">Projects</span>
                </a>
                <div class="cp-nav-icons">
                    <!-- Projects (Grid) -->
                    <a href="<?php echo esc_url(home_url('/projects/')); ?>" class="cp-nav-icon active" title="<?php esc_html_e('Projects', 'chatprojects'); ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                    </a>
                    <!-- Chat -->
                    <a href="<?php echo esc_url(home_url('/pro-chat/')); ?>" class="cp-nav-icon" title="<?php esc_html_e('Chat', 'chatprojects'); ?>">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Sidebar Content -->
            <div class="vp-sidebar-content">
                <!-- New Project Button -->
                <button
                    @click="openCreateModal()"
                    class="w-full mb-6 flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <?php esc_html_e('New Project', 'chatprojects'); ?>
                </button>

                <!-- Search -->
                <div class="vp-sidebar-section">
                    <div style="position: relative;">
                        <svg style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #9ca3af; pointer-events: none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input
                            type="text"
                            x-model="searchQuery"
                            placeholder="<?php esc_html_e('Search projects...', 'chatprojects'); ?>"
                            class="vp-sidebar-input"
                            style="padding-left: 34px;"
                            aria-label="<?php esc_html_e('Search projects', 'chatprojects'); ?>"
                        >
                    </div>
                </div>

                <!-- Filter -->
                <div class="vp-sidebar-section">
                    <div class="vp-sidebar-section-title"><?php esc_html_e('Filter', 'chatprojects'); ?></div>
                    <select x-model="filterMode" class="vp-sidebar-select" aria-label="<?php esc_html_e('Filter projects', 'chatprojects'); ?>">
                        <option value="all"><?php esc_html_e('All Projects', 'chatprojects'); ?></option>
                    </select>
                </div>

                <!-- Sort -->
                <div class="vp-sidebar-section">
                    <div class="vp-sidebar-section-title"><?php esc_html_e('Sort By', 'chatprojects'); ?></div>
                    <select x-model="sortBy" class="vp-sidebar-select" aria-label="<?php esc_html_e('Sort projects', 'chatprojects'); ?>">
                        <option value="modified"><?php esc_html_e('Last Modified', 'chatprojects'); ?></option>
                        <option value="created"><?php esc_html_e('Date Created', 'chatprojects'); ?></option>
                        <option value="title"><?php esc_html_e('Title (A-Z)', 'chatprojects'); ?></option>
                    </select>
                </div>

                <!-- View Toggle -->
                <div class="vp-sidebar-section">
                    <div class="vp-sidebar-section-title"><?php esc_html_e('View', 'chatprojects'); ?></div>
                    <div class="flex border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden" role="group" aria-label="<?php esc_html_e('View mode', 'chatprojects'); ?>">
                        <button
                            @click="viewMode = 'grid'"
                            :class="viewMode === 'grid' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300'"
                            class="flex-1 flex items-center justify-center gap-2 py-2 text-sm font-medium transition-colors"
                            title="<?php esc_html_e('Grid View', 'chatprojects'); ?>"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                            </svg>
                            <?php esc_html_e('Grid', 'chatprojects'); ?>
                        </button>
                        <button
                            @click="viewMode = 'list'"
                            :class="viewMode === 'list' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300'"
                            class="flex-1 flex items-center justify-center gap-2 py-2 text-sm font-medium border-l border-gray-200 dark:border-gray-600 transition-colors"
                            title="<?php esc_html_e('List View', 'chatprojects'); ?>"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                            <?php esc_html_e('List', 'chatprojects'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sidebar Footer -->
            <div class="vp-sidebar-footer">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <!-- Theme Toggle -->
                    <button
                        id="vp-theme-toggle"
                        class="vp-nav-icon"
                        title="<?php esc_html_e('Toggle theme', 'chatprojects'); ?>"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <!-- Settings -->
                        <a href="<?php echo esc_url(home_url('/settings/')); ?>" class="vp-nav-icon" title="<?php esc_html_e('Settings', 'chatprojects'); ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </a>
                        <!-- Logout -->
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="vp-nav-icon" title="<?php esc_html_e('Logout', 'chatprojects'); ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="vp-main-content">
            <div class="p-6 lg:p-8">
                <!-- Page Header -->
                <div class="mb-8 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <svg style="width: 24px; height: 24px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?php esc_html_e('Projects', 'chatprojects'); ?></h1>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <span x-text="filteredProjects.length"></span> <?php esc_html_e('projects in your workspace', 'chatprojects'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Projects Content -->
        <div x-show="filteredProjects.length > 0">

            <!-- Grid View -->
            <div x-show="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <template x-for="project in filteredProjects" :key="project.id">
                    <div class="bg-white dark:bg-dark-surface rounded-xl shadow-sm hover:shadow-lg transition-all duration-200 overflow-hidden border border-gray-100 dark:border-dark-border relative">
                        <!-- Card Header -->
                        <div class="border-b border-gray-100 dark:border-dark-border flex justify-between items-center px-6 py-4 hover:bg-gray-50 dark:hover:bg-dark-hover transition-colors">
                            <a :href="project.url" class="flex-1 min-w-0 cursor-pointer" style="text-decoration: none;">
                                <h3 class="font-semibold text-gray-900 dark:text-white text-base leading-tight truncate" x-text="project.title"></h3>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <template x-if="project.categories && project.categories.length > 0">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 italic" x-text="project.categories[0].name"></span>
                                    </template>
                                </div>
                            </a>
                            <!-- Actions Dropdown -->
                            <div class="relative ml-2 flex-shrink-0" x-data="{ open: false }">
                                <button
                                    @click.stop.prevent="open = !open"
                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-hover rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 p-2.5"
                                    aria-label="<?php esc_html_e('Project actions', 'chatprojects'); ?>"
                                    :aria-expanded="open ? 'true' : 'false'"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                    </svg>
                                </button>
                                <div
                                    x-show="open"
                                    @click.away="open = false"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="transform opacity-0 scale-95"
                                    x-transition:enter-end="transform opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="transform opacity-100 scale-100"
                                    x-transition:leave-end="transform opacity-0 scale-95"
                                    class="absolute right-0 mt-2 w-48 bg-white dark:bg-dark-surface rounded-lg shadow-lg border border-gray-200 dark:border-dark-border z-50 py-2"
                                >
                                    <button
                                        @click.stop="openEditModal(project); open = false"
                                        x-show="project.is_owner"
                                        class="w-full text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-dark-hover flex items-center space-x-2 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-inset first:rounded-t-lg px-4 py-2.5"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        <span><?php esc_html_e('Edit', 'chatprojects'); ?></span>
                                    </button>
                                    <!-- Divider before destructive action -->
                                    <div class="border-t border-gray-100 dark:border-dark-border my-1"></div>
                                    <button
                                        @click.stop="openDeleteModal(project); open = false"
                                        x-show="project.is_owner"
                                        class="w-full text-left text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50 hover:text-gray-800 dark:hover:text-gray-200 flex items-center space-x-2 transition-colors duration-150 focus:outline-none last:rounded-b-lg px-4 py-2.5"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        <span><?php esc_html_e('Delete', 'chatprojects'); ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="px-6 py-5">
                            <p class="text-sm line-clamp-2 mb-4 leading-relaxed" :class="project.description ? 'text-gray-600 dark:text-gray-400' : 'text-gray-400 dark:text-gray-500 italic'" x-text="project.description || '<?php esc_html_e('No description provided', 'chatprojects'); ?>'"></p>

                            <!-- Stats -->
                            <div class="flex items-center space-x-6 mb-4 text-sm">
                                <div class="flex items-center space-x-2 text-gray-600 dark:text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                                    </svg>
                                    <span class="font-medium" x-text="(project.message_count || 0) + ' ' + ((project.message_count === 1) ? '<?php esc_html_e('message', 'chatprojects'); ?>' : '<?php esc_html_e('messages', 'chatprojects'); ?>')"></span>
                                </div>
                                <div class="flex items-center space-x-2 text-gray-600 dark:text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span class="font-medium" x-text="(project.file_count || 0) + ' ' + ((project.file_count === 1) ? '<?php esc_html_e('file', 'chatprojects'); ?>' : '<?php esc_html_e('files', 'chatprojects'); ?>')"></span>
                                </div>
                            </div>

                            <!-- Meta -->
                            <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 pt-4 mt-4 border-t border-gray-100 dark:border-dark-border">
                                <span class="flex items-center">
                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span><?php esc_html_e('Last updated:', 'chatprojects'); ?> </span>
                                    <span x-text="project.modified_date"></span>
                                </span>
                                <span x-show="project.is_active" class="px-2.5 py-1 bg-green-100 dark:bg-green-900/20 text-green-600 dark:text-green-400 rounded-full text-xs font-medium">
                                    <?php esc_html_e('Active', 'chatprojects'); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="px-6 pb-5">
                            <a :href="project.url" class="flex items-center justify-center w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-lg transition-colors">
                                <?php esc_html_e('Open Project', 'chatprojects'); ?>
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </template>
            </div>

            <!-- List View -->
            <div x-show="viewMode === 'list'" class="bg-white dark:bg-dark-surface rounded-xl shadow-sm overflow-hidden border border-gray-100 dark:border-dark-border">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-dark-border">
                    <thead class="bg-gray-50 dark:bg-dark-bg">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider"><?php esc_html_e('Project', 'chatprojects'); ?></th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider"><?php esc_html_e('Owner', 'chatprojects'); ?></th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider"><?php esc_html_e('Messages', 'chatprojects'); ?></th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider"><?php esc_html_e('Files', 'chatprojects'); ?></th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider"><?php esc_html_e('Last Modified', 'chatprojects'); ?></th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider"><?php esc_html_e('Actions', 'chatprojects'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-dark-border">
                        <template x-for="project in filteredProjects" :key="project.id">
                            <tr class="hover:bg-gray-50 dark:hover:bg-dark-hover cursor-pointer" @click="window.location.href = project.url">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                            </svg>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white" x-text="project.title"></div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 italic mb-0.5">
                                                <template x-if="project.categories && project.categories.length > 0">
                                                    <span x-text="project.categories[0].name"></span>
                                                </template>
                                                <template x-if="!project.categories || project.categories.length === 0">
                                                    <span>Uncategorized</span>
                                                </template>
                                            </div>
                                            <div class="text-sm line-clamp-1" :class="project.description ? 'text-gray-600 dark:text-gray-400' : 'text-gray-400 dark:text-gray-500 italic'" x-text="project.description || '<?php esc_html_e('No description provided', 'chatprojects'); ?>'"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900 dark:text-white" x-text="project.is_owner ? '<?php esc_html_e('You', 'chatprojects'); ?>' : project.owner_name"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400" x-text="project.message_count || 0"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400" x-text="project.file_count || 0"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400" x-text="project.modified_date"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click.stop="openEditModal(project)" x-show="project.is_owner" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 mr-3"><?php esc_html_e('Edit', 'chatprojects'); ?></button>
                                    <button @click.stop="openDeleteModal(project)" x-show="project.is_owner" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"><?php esc_html_e('Delete', 'chatprojects'); ?></button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State -->
        <div x-show="filteredProjects.length === 0" class="text-center py-12">
            <div class="w-24 h-24 mx-auto mb-4 bg-gray-100 dark:bg-dark-surface rounded-full flex items-center justify-center">
                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2" x-text="searchQuery || filterMode !== 'all' ? '<?php esc_html_e('No projects found', 'chatprojects'); ?>' : '<?php esc_html_e('No Projects Yet', 'chatprojects'); ?>'"></h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6" x-text="searchQuery || filterMode !== 'all' ? '<?php esc_html_e('Try adjusting your search or filters', 'chatprojects'); ?>' : '<?php esc_html_e('Create your first project to get started with AI-powered project assistants', 'chatprojects'); ?>'"></p>
            <button @click="openCreateModal()" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <?php esc_html_e('Create Your First Project', 'chatprojects'); ?>
            </button>
        </div>
            </div>
        </main>
    </div>

    <!-- Create Project Modal -->
    <div x-show="createModal.show"
         x-cloak
         @keydown.escape.window="closeCreateModal()"
         class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div x-show="createModal.show"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="closeCreateModal()"
                 class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <!-- Modal panel -->
            <div x-show="createModal.show"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white dark:bg-dark-surface rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="create-project-title">

                <form @submit.prevent="createProject()">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-dark-border">
                        <h3 id="create-project-title" class="text-lg font-semibold text-gray-900 dark:text-white"><?php esc_html_e('Create New Project', 'chatprojects'); ?></h3>
                    </div>

                    <div class="px-6 py-4 space-y-4">
                        <!-- Project Title -->
                        <div>
                            <label for="create-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <?php esc_html_e('Project Title', 'chatprojects'); ?>
                            </label>
                            <input
                                type="text"
                                id="create-title"
                                x-model="createModal.project.title"
                                required
                                class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-dark-bg dark:text-white"
                                placeholder="<?php esc_html_e('Enter project title', 'chatprojects'); ?>"
                            >
                        </div>

                        <!-- Project Description -->
                        <div>
                            <label for="create-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <?php esc_html_e('Description', 'chatprojects'); ?>
                            </label>
                            <textarea
                                id="create-description"
                                x-model="createModal.project.description"
                                rows="4"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-dark-bg dark:text-white"
                                placeholder="<?php esc_html_e('Enter project description', 'chatprojects'); ?>"
                            ></textarea>
                        </div>

                        <!-- Assistant Instructions -->
                        <div>
                            <label for="create-instructions" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <?php esc_html_e('Assistant Instructions (Optional)', 'chatprojects'); ?>
                            </label>
                            <textarea
                                id="create-instructions"
                                x-model="createModal.project.instructions"
                                rows="5"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-dark-bg dark:text-white font-mono text-sm"
                                placeholder="<?php esc_html_e('You are a helpful AI assistant...', 'chatprojects'); ?>"
                            ></textarea>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <?php esc_html_e('Custom instructions for this project. Leave empty to use global default.', 'chatprojects'); ?>
                            </p>
                        </div>

                        <!-- Error Message -->
                        <div x-show="createModal.error" class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                            <p class="text-sm text-amber-700 dark:text-amber-400" x-text="createModal.error"></p>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 dark:bg-dark-bg border-t border-gray-200 dark:border-dark-border flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="closeCreateModal()"
                            :disabled="createModal.saving"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-dark-surface border border-gray-300 dark:border-dark-border rounded-lg hover:bg-gray-50 dark:hover:bg-dark-hover disabled:opacity-50"
                        >
                            <?php esc_html_e('Cancel', 'chatprojects'); ?>
                        </button>
                        <button
                            type="submit"
                            :disabled="createModal.saving"
                            :class="createModal.saving ? 'bg-blue-600 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                            class="px-4 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-70 flex items-center justify-center gap-2 min-w-[140px]"
                        >
                            <svg x-show="createModal.saving" class="animate-spin h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="createModal.saving ? '<?php esc_html_e('Creating...', 'chatprojects'); ?>' : '<?php esc_html_e('Create Project', 'chatprojects'); ?>'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div x-show="editModal.show"
         x-cloak
         @keydown.escape.window="editModal.show = false"
         class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div x-show="editModal.show"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="editModal.show = false"
                 class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <!-- Modal panel -->
            <div x-show="editModal.show"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white dark:bg-dark-surface rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="edit-project-title">

                <form @submit.prevent="saveProject()">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-dark-border">
                        <h3 id="edit-project-title" class="text-lg font-semibold text-gray-900 dark:text-white"><?php esc_html_e('Edit Project', 'chatprojects'); ?></h3>
                    </div>

                    <div class="px-6 py-4 space-y-4">
                        <!-- Project Title -->
                        <div>
                            <label for="edit-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <?php esc_html_e('Project Title', 'chatprojects'); ?>
                            </label>
                            <input
                                type="text"
                                id="edit-title"
                                x-model="editModal.project.title"
                                required
                                class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-dark-bg dark:text-white"
                                placeholder="<?php esc_html_e('Enter project title', 'chatprojects'); ?>"
                            >
                        </div>

                        <!-- Project Description -->
                        <div>
                            <label for="edit-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <?php esc_html_e('Description', 'chatprojects'); ?>
                            </label>
                            <textarea
                                id="edit-description"
                                x-model="editModal.project.description"
                                rows="4"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-dark-bg dark:text-white"
                                placeholder="<?php esc_html_e('Enter project description', 'chatprojects'); ?>"
                            ></textarea>
                        </div>

                        <!-- Assistant Instructions -->
                        <div>
                            <label for="edit-instructions" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <?php esc_html_e('Assistant Instructions (Optional)', 'chatprojects'); ?>
                            </label>
                            <textarea
                                id="edit-instructions"
                                x-model="editModal.project.instructions"
                                rows="5"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-dark-bg dark:text-white font-mono text-sm"
                                placeholder="<?php esc_html_e('You are a helpful AI assistant...', 'chatprojects'); ?>"
                            ></textarea>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <?php esc_html_e('Custom instructions for this project. Leave empty to use global default.', 'chatprojects'); ?>
                            </p>
                        </div>

                        <!-- Error Message -->
                        <div x-show="editModal.error" class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                            <p class="text-sm text-amber-700 dark:text-amber-400" x-text="editModal.error"></p>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 dark:bg-dark-bg border-t border-gray-200 dark:border-dark-border flex justify-end space-x-3">
                        <button
                            type="button"
                            @click="editModal.show = false"
                            :disabled="editModal.saving"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-dark-surface border border-gray-300 dark:border-dark-border rounded-lg hover:bg-gray-50 dark:hover:bg-dark-hover disabled:opacity-50"
                        >
                            <?php esc_html_e('Cancel', 'chatprojects'); ?>
                        </button>
                        <button
                            type="submit"
                            :disabled="editModal.saving"
                            :class="editModal.saving ? 'bg-blue-600 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                            class="px-4 py-2 text-sm font-medium text-white rounded-lg disabled:opacity-70 flex items-center justify-center gap-2 min-w-[140px]"
                        >
                            <svg x-show="editModal.saving" class="animate-spin h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="editModal.saving ? '<?php esc_html_e('Saving...', 'chatprojects'); ?>' : '<?php esc_html_e('Save Changes', 'chatprojects'); ?>'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="deleteModal.show"
         x-cloak
         @keydown.escape.window="deleteModal.show = false"
         class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div x-show="deleteModal.show"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="deleteModal.show = false"
                 class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <!-- Modal panel -->
            <div x-show="deleteModal.show"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white dark:bg-dark-surface rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full"
                 role="dialog"
                 aria-modal="true"
                 aria-labelledby="delete-project-title">

                <div class="px-6 py-4">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="flex-shrink-0 w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-900/20 flex items-center justify-center">
                            <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 id="delete-project-title" class="text-lg font-semibold text-gray-900 dark:text-white"><?php esc_html_e('Delete Project?', 'chatprojects'); ?></h3>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <?php esc_html_e('Are you sure you want to delete', 'chatprojects'); ?> "<span x-text="deleteModal.project.title" class="font-medium"></span>"? <?php esc_html_e('This action cannot be undone.', 'chatprojects'); ?>
                    </p>

                    <!-- Error Message -->
                    <div x-show="deleteModal.error" class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg mb-4">
                        <p class="text-sm text-amber-700 dark:text-amber-400" x-text="deleteModal.error"></p>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 dark:bg-dark-bg border-t border-gray-200 dark:border-dark-border flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="deleteModal.show = false"
                        :disabled="deleteModal.deleting"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-dark-surface border border-gray-300 dark:border-dark-border rounded-lg hover:bg-gray-50 dark:hover:bg-dark-hover disabled:opacity-50"
                    >
                        <?php esc_html_e('Cancel', 'chatprojects'); ?>
                    </button>
                    <button
                        type="button"
                        @click="confirmDelete()"
                        :disabled="deleteModal.deleting"
                        class="px-4 py-2 text-sm font-medium text-white bg-gray-600 rounded-lg hover:bg-gray-700 disabled:opacity-50 flex items-center space-x-2"
                    >
                        <svg x-show="deleteModal.deleting" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="deleteModal.deleting ? '<?php esc_html_e('Deleting...', 'chatprojects'); ?>' : '<?php esc_html_e('Delete Project', 'chatprojects'); ?>'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>

    <?php
    // Fallback: If main.js didn't load via wp_enqueue, load it directly
    $main_js_url = esc_url(CHATPROJECTS_PLUGIN_URL . 'assets/dist/js/main.js');
    $main_js_version = CHATPROJECTS_VERSION . '-' . filemtime(CHATPROJECTS_PLUGIN_DIR . 'assets/dist/js/main.js');
    $fallback_script = "(function() {
        var mainJsInDom = Array.from(document.querySelectorAll('script')).some(function(s) {
            return s.src && s.src.includes('main.js');
        });
        if (mainJsInDom) {
            return;
        }
        var script = document.createElement('script');
        script.type = 'module';
        script.src = '{$main_js_url}?ver={$main_js_version}';
        document.body.appendChild(script);
    })();";
    wp_print_inline_script_tag($fallback_script, array('id' => 'chatprojects-fallback-loader'));
    ?>

    <?php
    // Projects app script - using wp_print_inline_script_tag for WordPress compliance
    $projects_json = wp_json_encode($all_projects);
    $current_user_json = wp_json_encode($current_user);
    $home_url = esc_url(home_url());
    $error_create = esc_js(__('Failed to create project.', 'chatprojects'));
    $error_update = esc_js(__('Failed to update project.', 'chatprojects'));
    $error_delete = esc_js(__('Failed to delete project.', 'chatprojects'));
    $error_general = esc_js(__('An error occurred. Please try again.', 'chatprojects'));

    $projects_app_script = "function projectsApp() {
            // Helper to decode HTML entities
            const decodeHtml = (html) => {
                const txt = document.createElement('textarea');
                txt.innerHTML = html;
                return txt.value;
            };

            return {
                projects: {$projects_json},
                searchQuery: '',
                filterMode: 'all',
                sortBy: 'modified',
                viewMode: 'grid',
                sidebarOpen: false,

                // Create Modal State
                createModal: {
                    show: false,
                    saving: false,
                    error: '',
                    project: {
                        title: '',
                        description: '',
                        category: '',
                        instructions: ''
                    }
                },

                // Edit Modal State
                editModal: {
                    show: false,
                    saving: false,
                    error: '',
                    project: {
                        id: null,
                        title: '',
                        description: '',
                        category: '',
                        instructions: ''
                    }
                },

                // Delete Modal State
                deleteModal: {
                    show: false,
                    deleting: false,
                    error: '',
                    project: {
                        id: null,
                        title: ''
                    }
                },

                get filteredProjects() {
                    let filtered = this.projects.map(item => {
                        const post = item.post;
                        const owner = item.is_owner ? {$current_user_json} : null;

                        return {
                            id: post.ID,
                            title: decodeHtml(post.post_title),
                            description: post.post_content,
                            instructions: item.instructions || '',
                            categories: item.categories || [],
                            url: '{$home_url}/chatpr_project/' + post.post_name + '/',
                            is_owner: item.is_owner,
                            owner_name: owner ? owner.display_name : 'Unknown',
                            message_count: item.message_count || 0,
                            file_count: item.file_count || 0,
                            shared_count: 0,
                            is_active: true,
                            created_date: new Date(post.post_date).toLocaleDateString(),
                            modified_date: new Date(post.post_modified).toLocaleDateString(),
                            _post_modified: post.post_modified,
                            _post_date: post.post_date
                        };
                    });

                    // Apply search filter
                    if (this.searchQuery) {
                        filtered = filtered.filter(p =>
                            p.title.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                            p.description.toLowerCase().includes(this.searchQuery.toLowerCase())
                        );
                    }

                    // Apply ownership filter
                    if (this.filterMode === 'own') {
                        filtered = filtered.filter(p => p.is_owner);
                    } else if (this.filterMode === 'shared') {
                        filtered = filtered.filter(p => !p.is_owner);
                    }

                    // Apply sorting
                    filtered.sort((a, b) => {
                        switch (this.sortBy) {
                            case 'modified':
                                return new Date(b._post_modified) - new Date(a._post_modified);
                            case 'created':
                                return new Date(b._post_date) - new Date(a._post_date);
                            case 'title':
                                return a.title.localeCompare(b.title);
                            default:
                                return 0;
                        }
                    });

                    return filtered;
                },

                openCreateModal() {
                    this.createModal.project = {
                        title: '',
                        description: '',
                        category: '',
                        instructions: ''
                    };
                    this.createModal.error = '';
                    this.createModal.show = true;
                },

                closeCreateModal() {
                    this.createModal.show = false;
                    this.createModal.error = '';
                    this.createModal.project = {
                        title: '',
                        description: '',
                        category: '',
                        instructions: ''
                    };
                },

                async createProject() {
                    this.createModal.saving = true;
                    this.createModal.error = '';

                    const formData = new FormData();
                    formData.append('action', 'chatpr_create_project');
                    formData.append('nonce', chatprAjax.nonce);
                    formData.append('title', this.createModal.project.title);
                    formData.append('description', this.createModal.project.description);
                    formData.append('category', this.createModal.project.category || '');
                    formData.append('instructions', this.createModal.project.instructions || '');

                    try {
                        const response = await fetch(chatprAjax.ajaxUrl, {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Reload page to show new project
                            window.location.reload();
                        } else {
                            this.createModal.error = data.data?.message || '{$error_create}';
                        }
                    } catch (error) {
                        this.createModal.error = '{$error_general}';
                    } finally {
                        this.createModal.saving = false;
                    }
                },

                openEditModal(project) {
                    // Get the first category ID if available
                    const categoryId = (project.categories && project.categories.length > 0) ? project.categories[0].term_id : '';

                    this.editModal.project = {
                        id: project.id,
                        title: project.title,
                        description: project.description,
                        category: categoryId,
                        instructions: project.instructions || ''
                    };
                    this.editModal.error = '';
                    this.editModal.show = true;
                },

                async saveProject() {
                    this.editModal.saving = true;
                    this.editModal.error = '';

                    const formData = new FormData();
                    formData.append('action', 'chatpr_update_project');
                    formData.append('nonce', chatprAjax.nonce);
                    formData.append('project_id', this.editModal.project.id);
                    formData.append('title', this.editModal.project.title);
                    formData.append('description', this.editModal.project.description);
                    formData.append('category', this.editModal.project.category || '');
                    formData.append('instructions', this.editModal.project.instructions || '');

                    try {
                        const response = await fetch(chatprAjax.ajaxUrl, {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Reload page to reflect changes
                            window.location.reload();
                        } else {
                            this.editModal.error = data.data?.message || '{$error_update}';
                        }
                    } catch (error) {
                        this.editModal.error = '{$error_general}';
                    } finally {
                        this.editModal.saving = false;
                    }
                },

                openDeleteModal(project) {
                    this.deleteModal.project = {
                        id: project.id,
                        title: project.title
                    };
                    this.deleteModal.error = '';
                    this.deleteModal.show = true;
                },

                async confirmDelete() {
                    this.deleteModal.deleting = true;
                    this.deleteModal.error = '';

                    const formData = new FormData();
                    formData.append('action', 'chatpr_delete_project');
                    formData.append('nonce', chatprData.nonce);
                    formData.append('project_id', this.deleteModal.project.id);

                    try {
                        const response = await fetch(chatprData.ajax_url, {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Remove project from list
                            this.projects = this.projects.filter(p => p.post.ID !== this.deleteModal.project.id);
                            this.deleteModal.show = false;
                        } else {
                            this.deleteModal.error = data.data.message || '{$error_delete}';
                        }
                    } catch (error) {
                        this.deleteModal.error = '{$error_general}';
                    } finally {
                        this.deleteModal.deleting = false;
                    }
                }
            }
        }";
    wp_print_inline_script_tag($projects_app_script, array('id' => 'chatprojects-projects-app'));

    // Theme toggle event listener
    $theme_toggle_script = "document.getElementById('vp-theme-toggle')?.addEventListener('click', function() { window.VPTheme?.toggle(); });";
    wp_print_inline_script_tag($theme_toggle_script, array('id' => 'chatprojects-theme-toggle'));

    // Handle back-forward cache (bfcache) to reinitialize Alpine components
    $bfcache_script = "(function() {
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Page was restored from bfcache, reload to reinitialize
                window.location.reload();
            }
        });
    })();";
    wp_print_inline_script_tag($bfcache_script, array('id' => 'chatprojects-bfcache-handler'));
    ?>
</body>
</html>
<?php
});
