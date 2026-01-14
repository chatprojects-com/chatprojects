<?php
/**
 * Projects Panel Partial (Free Version)
 *
 * Embedded projects list for main app shell
 * Free version: Shows shared project, no create button if project exists
 *
 * @package ChatProjects
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

call_user_func(function () {
$user_id = get_current_user_id();

// In Free version, get the shared project (not filtered by author)
$projects_query = new WP_Query(array(
    'post_type' => 'chatpr_project',
    'posts_per_page' => -1,
    'orderby' => 'modified',
    'order' => 'DESC',
    'post_status' => 'publish'
));

$projects = $projects_query->posts;

global $wpdb;
$chats_table = esc_sql($wpdb->prefix . 'chatprojects_chats');

// Check if we have projects
$has_project = !empty($projects);
$project_count = count($projects);
?>

<div class="vp-projects-panel" style="padding: 1.5rem; height: 100%; overflow-y: auto;">

    <!-- Panel Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="margin: 0; font-size: 1.5rem; font-weight: 600; color: var(--vp-text);">
                <?php esc_html_e('Projects', 'chatprojects'); ?>
            </h2>
            <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--vp-text-muted);">
                <?php if ($has_project) : ?>
                    <?php echo esc_html( $project_count ); ?> <?php echo esc_html( _n('project', 'projects', $project_count, 'chatprojects') ); ?>
                <?php else : ?>
                    <?php esc_html_e('No projects yet', 'chatprojects'); ?>
                <?php endif; ?>
            </p>
        </div>
        <button
            @click="$dispatch('vp:project:create')"
            style="display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: var(--vp-primary); color: white; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 500; cursor: pointer;"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <?php esc_html_e('New Project', 'chatprojects'); ?>
        </button>
    </div>

    <?php if (empty($projects)) : ?>
    <!-- Empty State -->
    <div style="text-align: center; padding: 4rem 2rem; background: var(--vp-surface); border-radius: 12px; border: 1px solid var(--vp-border);">
        <svg style="width: 64px; height: 64px; margin: 0 auto 1rem; color: var(--vp-text-muted); opacity: 0.5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
        </svg>
        <h3 style="margin: 0 0 0.5rem; font-size: 1.125rem; font-weight: 600; color: var(--vp-text);">
            <?php esc_html_e('No Projects Yet', 'chatprojects'); ?>
        </h3>
        <p style="margin: 0 0 1.5rem; color: var(--vp-text-muted);">
            <?php esc_html_e('Create your first project to get started with AI-powered assistance.', 'chatprojects'); ?>
        </p>
        <button
            @click="$dispatch('vp:project:create')"
            style="padding: 0.75rem 1.5rem; background: var(--vp-primary); color: white; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 500; cursor: pointer;"
        >
            <?php esc_html_e('Create Your First Project', 'chatprojects'); ?>
        </button>
    </div>
    <?php else : ?>
    <!-- Projects Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
        <?php foreach ($projects as $project) :
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query
            $message_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(message_count) FROM {$chats_table} WHERE project_id = %d",
                    $project->ID
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            // Check if this is the shared project
            $is_shared = get_post_meta($project->ID, '_cp_shared_project', true) === '1';
        ?>
        <div
            class="vp-project-card"
            style="background: var(--vp-surface); border-radius: 12px; border: 1px solid var(--vp-border); overflow: hidden; transition: box-shadow 0.2s, transform 0.2s; cursor: pointer;"
            @click="window.location.href = '<?php echo esc_url(get_permalink($project->ID)); ?>'"
            @mouseenter="$el.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)'; $el.style.transform = 'translateY(-2px)'"
            @mouseleave="$el.style.boxShadow = 'none'; $el.style.transform = 'none'"
        >
            <div style="padding: 1.25rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <h3 style="margin: 0; font-size: 1rem; font-weight: 600; color: var(--vp-text); flex: 1;">
                        <?php echo esc_html($project->post_title); ?>
                    </h3>
                    <?php if ($is_shared) : ?>
                    <span style="padding: 0.125rem 0.375rem; background: var(--vp-primary); color: white; border-radius: 4px; font-size: 0.625rem; text-transform: uppercase;">
                        <?php esc_html_e('Shared', 'chatprojects'); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <p style="margin: 0 0 1rem; font-size: 0.875rem; color: var(--vp-text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                    <?php echo esc_html(wp_trim_words($project->post_content, 15, '...')); ?>
                </p>
                <div style="display: flex; align-items: center; gap: 1rem; font-size: 0.75rem; color: var(--vp-text-muted);">
                    <span style="display: flex; align-items: center; gap: 0.25rem;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <?php echo esc_html( $message_count ); ?> <?php echo esc_html( _n( 'message', 'messages', $message_count, 'chatprojects' ) ); ?>
                    </span>
                    <span>
                        <?php echo esc_html(human_time_diff(strtotime($project->post_modified), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'chatprojects'); ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<!-- Create Project Modal -->
<div
    x-data="{ showCreateModal: false }"
    @vp:project:create.window="showCreateModal = true"
>
    <template x-if="showCreateModal">
        <div
            style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;"
            @click.self="showCreateModal = false"
        >
            <div style="background: var(--vp-surface, white); border-radius: 12px; padding: 1.5rem; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
                <h3 style="margin: 0 0 1rem; font-size: 1.125rem; font-weight: 600; color: var(--vp-text);">
                    <?php esc_html_e('Create New Project', 'chatprojects'); ?>
                </h3>
                <form
                    @submit.prevent="
                        fetch(chatprData.ajax_url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'chatpr_create_project',
                                nonce: chatprData.nonce,
                                title: $refs.projectTitle.value,
                                description: $refs.projectDescription.value
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                window.location.reload();
                            } else {
                                alert(data.data?.message || 'Failed to create project');
                            }
                        });
                    "
                >
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--vp-text);">
                            <?php esc_html_e('Project Title', 'chatprojects'); ?>
                        </label>
                        <input
                            type="text"
                            x-ref="projectTitle"
                            required
                            style="width: 100%; padding: 0.625rem; border: 1px solid var(--vp-border); border-radius: 6px; font-size: 0.875rem; background: var(--vp-bg); color: var(--vp-text);"
                            placeholder="<?php esc_attr_e('Enter project title', 'chatprojects'); ?>"
                        >
                    </div>
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--vp-text);">
                            <?php esc_html_e('Description', 'chatprojects'); ?>
                        </label>
                        <textarea
                            x-ref="projectDescription"
                            rows="3"
                            style="width: 100%; padding: 0.625rem; border: 1px solid var(--vp-border); border-radius: 6px; font-size: 0.875rem; resize: vertical; background: var(--vp-bg); color: var(--vp-text);"
                            placeholder="<?php esc_attr_e('Enter project description', 'chatprojects'); ?>"
                        ></textarea>
                    </div>
                    <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            style="padding: 0.625rem 1rem; background: var(--vp-bg); color: var(--vp-text); border: 1px solid var(--vp-border); border-radius: 6px; font-size: 0.875rem; cursor: pointer;"
                        >
                            <?php esc_html_e('Cancel', 'chatprojects'); ?>
                        </button>
                        <button
                            type="submit"
                            style="padding: 0.625rem 1rem; background: var(--vp-primary); color: white; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer;"
                        >
                            <?php esc_html_e('Create Project', 'chatprojects'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
<?php
});
