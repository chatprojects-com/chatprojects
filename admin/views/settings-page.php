<?php
/**
 * Settings Page Template
 *
 * Renders a tabbed settings interface using WordPress native nav-tab styling.
 * All tabs share a single form so settings from all tabs are submitted together.
 *
 * @package ChatProjects
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check permissions.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'chatprojects' ) );
}

// Define tabs.
$tabs = array(
	'api'    => array(
		'label' => __( 'API Keys', 'chatprojects' ),
		'icon'  => 'dashicons-admin-network',
	),
	'chat'   => array(
		'label' => __( 'Chat', 'chatprojects' ),
		'icon'  => 'dashicons-format-chat',
	),
	'widget' => array(
		'label' => __( 'Chat Widget', 'chatprojects' ),
		'icon'  => 'dashicons-admin-comments',
	),
);

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab display only, no form processing.
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'api';
if ( ! isset( $tabs[ $active_tab ] ) ) {
	$active_tab = 'api';
}
?>

<style>
	.chatpr-settings-wrap {
		max-width: 900px;
	}
	.chatpr-settings-wrap .nav-tab-wrapper {
		margin-bottom: 0;
		border-bottom: 1px solid #c3c4c7;
		padding-top: 6px;
	}
	.chatpr-settings-wrap .nav-tab {
		font-size: 13px;
		padding: 8px 16px;
		margin-bottom: -1px;
	}
	.chatpr-settings-wrap .nav-tab .dashicons {
		font-size: 16px;
		width: 16px;
		height: 16px;
		vertical-align: text-bottom;
		margin-right: 4px;
	}
	.chatpr-settings-wrap .nav-tab-active {
		border-bottom-color: #f0f0f1;
		background: #f0f0f1;
	}
	.chatpr-settings-content {
		background: #fff;
		border: 1px solid #c3c4c7;
		border-top: none;
		padding: 20px 24px 0;
	}
	.chatpr-settings-content .form-table {
		margin-top: 0;
	}
	.chatpr-settings-content .form-table th {
		padding-top: 16px;
		padding-bottom: 16px;
	}
	.chatpr-settings-content .form-table td {
		padding-top: 12px;
		padding-bottom: 12px;
	}
	.chatpr-settings-content h2 {
		font-size: 14px;
		font-weight: 600;
		color: #1d2327;
		margin: 24px 0 4px;
		padding: 0 0 8px;
		border-bottom: 1px solid #e2e4e7;
	}
	.chatpr-settings-content h2:first-child {
		margin-top: 0;
	}
	.chatpr-settings-content p {
		margin-top: 2px;
		margin-bottom: 12px;
		color: #50575e;
		font-size: 13px;
	}
	.chatpr-settings-footer {
		background: #fff;
		border: 1px solid #c3c4c7;
		border-top: none;
		padding: 0 24px 16px;
	}
	.chatpr-info-card {
		background: #fff;
		border: 1px solid #c3c4c7;
		border-radius: 4px;
		padding: 16px 20px;
		margin-top: 20px;
	}
	.chatpr-info-card h3 {
		margin: 0 0 8px;
		font-size: 13px;
		font-weight: 600;
		color: #1d2327;
	}
	.chatpr-info-card ol,
	.chatpr-info-card ul {
		margin: 0 0 0 20px;
		padding: 0;
	}
	.chatpr-info-card li {
		margin-bottom: 6px;
		font-size: 13px;
		color: #50575e;
		line-height: 1.5;
	}
	.chatpr-info-card p {
		margin: 8px 0 0;
		font-size: 13px;
		color: #50575e;
	}
	.chatpr-info-card code {
		background: #f0f0f1;
		padding: 2px 6px;
		border-radius: 3px;
		font-size: 12px;
	}
	/* Widget method cards */
	.chatpr-widget-methods {
		display: flex;
		flex-direction: column;
		gap: 12px;
		margin-top: 4px;
	}
	.chatpr-widget-method {
		display: flex;
		align-items: flex-start;
		gap: 12px;
	}
	.chatpr-widget-method-num {
		flex-shrink: 0;
		width: 24px;
		height: 24px;
		background: #2271b1;
		color: #fff;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 12px;
		font-weight: 600;
		margin-top: 1px;
	}
	.chatpr-widget-method strong {
		display: block;
		font-size: 13px;
		color: #1d2327;
		margin-bottom: 2px;
	}
	.chatpr-widget-method-desc {
		font-size: 13px;
		color: #50575e;
		line-height: 1.5;
	}
	/* Shortcode reference tables */
	.chatpr-shortcode-table {
		width: 100%;
		border-collapse: collapse;
		margin: 8px 0 16px;
		font-size: 13px;
	}
	.chatpr-shortcode-table th {
		text-align: left;
		font-weight: 600;
		color: #1d2327;
		padding: 8px 12px;
		border-bottom: 2px solid #e2e4e7;
		font-size: 12px;
		text-transform: uppercase;
		letter-spacing: 0.03em;
	}
	.chatpr-shortcode-table td {
		padding: 8px 12px;
		border-bottom: 1px solid #f0f0f1;
		color: #50575e;
		vertical-align: top;
	}
	.chatpr-shortcode-table tr:last-child td {
		border-bottom: none;
	}
	.chatpr-shortcode-table code {
		background: #f0f0f1;
		padding: 2px 6px;
		border-radius: 3px;
		font-size: 12px;
		white-space: nowrap;
	}
	.chatpr-shortcode-table--attrs td:first-child {
		width: 140px;
	}
	.chatpr-shortcode-table--attrs td:last-child {
		color: #8c8f94;
		font-size: 12px;
	}
	.chatpr-info-card h4 {
		margin: 16px 0 4px;
		font-size: 13px;
		font-weight: 600;
		color: #1d2327;
	}
	.chatpr-shortcode-example {
		background: #f6f7f7;
		border: 1px solid #e2e4e7;
		border-radius: 4px;
		padding: 10px 14px;
		margin: 12px 0;
	}
	.chatpr-shortcode-example strong {
		display: block;
		font-size: 11px;
		text-transform: uppercase;
		letter-spacing: 0.03em;
		color: #8c8f94;
		margin-bottom: 4px;
	}
	.chatpr-shortcode-example code {
		background: none;
		padding: 0;
		font-size: 12px;
		color: #1d2327;
		word-break: break-all;
	}
	.chatpr-shortcode-note {
		font-style: italic;
		margin-top: 8px;
	}
	.chatpr-features {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 4px 24px;
		margin-top: 8px;
	}
	.chatpr-feature {
		font-size: 13px;
		line-height: 1.8;
		color: #50575e;
	}
	.chatpr-feature .dashicons {
		font-size: 14px;
		width: 14px;
		height: 14px;
		vertical-align: text-bottom;
		margin-right: 2px;
	}
	.chatpr-feature--free .dashicons { color: #00a32a; }
	.chatpr-feature--pro .dashicons { color: #2271b1; }
	.chatpr-feature--pro { color: #8c8f94; }
</style>

<div class="wrap chatpr-settings-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings tabs', 'chatprojects' ); ?>">
		<?php foreach ( $tabs as $tab_key => $tab_data ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key, admin_url( 'admin.php?page=chatprojects-settings' ) ) ); ?>"
			   class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>"
			   aria-current="<?php echo $active_tab === $tab_key ? 'page' : 'false'; ?>">
				<span class="dashicons <?php echo esc_attr( $tab_data['icon'] ); ?>"></span>
				<?php echo esc_html( $tab_data['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="options.php">
		<?php settings_fields( 'chatprojects_settings' ); ?>

		<div class="chatpr-settings-content">
			<?php
			$tab_pages = array(
				'api'    => 'chatprojects-tab-api',
				'chat'   => 'chatprojects-tab-chat',
				'widget' => 'chatprojects-tab-widget',
			);
			$page = isset( $tab_pages[ $active_tab ] ) ? $tab_pages[ $active_tab ] : 'chatprojects-tab-api';
			do_settings_sections( $page );
			?>
		</div>

		<div class="chatpr-settings-footer">
			<?php submit_button(); ?>
		</div>
	</form>

	<?php if ( 'api' === $active_tab ) : ?>
		<div class="chatpr-info-card">
			<h3><?php esc_html_e( 'Quick Start Guide', 'chatprojects' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Enter your OpenAI API key above and save settings.', 'chatprojects' ); ?></li>
				<li>
					<?php
					printf(
						/* translators: %s: projects URL path */
						esc_html__( 'Visit %s — if you see a 404, go to Settings > Permalinks and click Save.', 'chatprojects' ),
						'<code>/projects/</code>'
					);
					?>
				</li>
				<li><?php esc_html_e( 'Create a new project from the Projects menu.', 'chatprojects' ); ?></li>
				<li><?php esc_html_e( 'Upload files to the project\'s Vector Store.', 'chatprojects' ); ?></li>
				<li><?php esc_html_e( 'Start chatting with your AI assistant!', 'chatprojects' ); ?></li>
			</ol>
			<p>
				<?php
				printf(
					/* translators: %s: Documentation link */
					esc_html__( 'Need help? Visit our %s for guides and tutorials.', 'chatprojects' ),
					'<a href="https://chatprojects.com" target="_blank">' . esc_html__( 'documentation', 'chatprojects' ) . '</a>'
				);
				?>
			</p>
		</div>

		<div class="chatpr-info-card">
			<h3><?php esc_html_e( 'Available Features', 'chatprojects' ); ?></h3>
			<div class="chatpr-features">
				<?php
				$features = array(
					array( 'AI Chat', true ),
					array( 'Vector Stores', true ),
					array( 'Custom Instructions', true ),
					array( 'Auto-RAG Site Indexing', true ),
					array( 'Chat Widget', true ),
					array( 'File Uploads', true ),
					array( 'Transcription', false ),
					array( 'Prompt Library', false ),
					array( 'Remove Branding', false ),
					array( 'Live Web Search', false ),
					array( 'Embeddable Chatbot', false ),
					array( 'Custom PWA', false ),
					array( 'Image Studio', false ),
					array( 'Advanced Sharing', false ),
					array( 'More File Types', false ),
					array( 'Cloud Import & Sync', false ),
				);
				foreach ( $features as $feature ) :
					$class = $feature[1] ? 'chatpr-feature--free' : 'chatpr-feature--pro';
					$icon  = $feature[1] ? 'dashicons-yes-alt' : 'dashicons-lock';
					$badge = $feature[1] ? '' : ' (Pro)';
					?>
					<span class="chatpr-feature <?php echo esc_attr( $class ); ?>">
						<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
						<?php echo esc_html( $feature[0] . $badge ); ?>
					</span>
				<?php endforeach; ?>
			</div>
			<p>
				<?php esc_html_e( 'Upgrade to Pro at', 'chatprojects' ); ?>
				<a href="https://chatprojects.com" target="_blank">chatprojects.com</a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( 'widget' === $active_tab ) : ?>
		<div class="chatpr-info-card">
			<h3><?php esc_html_e( 'Two Ways to Display', 'chatprojects' ); ?></h3>
			<div class="chatpr-widget-methods">
				<div class="chatpr-widget-method">
					<span class="chatpr-widget-method-num">1</span>
					<div>
						<strong><?php esc_html_e( 'Global Widget (this page)', 'chatprojects' ); ?></strong>
						<span class="chatpr-widget-method-desc"><?php esc_html_e( 'Enable above and turn on "Auto-inject" to show a floating chat bubble on every page. Uses the project and appearance settings configured above.', 'chatprojects' ); ?></span>
					</div>
				</div>
				<div class="chatpr-widget-method">
					<span class="chatpr-widget-method-num">2</span>
					<div>
						<strong><?php esc_html_e( 'Shortcodes (per-project)', 'chatprojects' ); ?></strong>
						<span class="chatpr-widget-method-desc">
							<?php esc_html_e( 'Embed chatbots for any project on any page. You can place multiple chatbots on the same page, each connected to a different project.', 'chatprojects' ); ?>
							<?php
							printf(
								/* translators: %s: link to projects list */
								esc_html__( 'Copy-ready shortcodes are shown on each %s.', 'chatprojects' ),
								'<a href="' . esc_url( admin_url( 'edit.php?post_type=chatpr_project' ) ) . '">' . esc_html__( "project's edit screen", 'chatprojects' ) . '</a>'
							);
							?>
						</span>
					</div>
				</div>
			</div>
		</div>

		<div class="chatpr-info-card">
			<h3><?php esc_html_e( 'Shortcode Reference', 'chatprojects' ); ?></h3>
			<table class="chatpr-shortcode-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Example', 'chatprojects' ); ?></th>
						<th><?php esc_html_e( 'Result', 'chatprojects' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>[chatprojects_widget project="123"]</code></td>
						<td><?php esc_html_e( 'Inline chat panel for project 123', 'chatprojects' ); ?></td>
					</tr>
					<tr>
						<td><code>[chatprojects_widget project="123" mode="floating"]</code></td>
						<td><?php esc_html_e( 'Floating bubble for project 123', 'chatprojects' ); ?></td>
					</tr>
					<tr>
						<td><code>[chatprojects_widget]</code></td>
						<td><?php esc_html_e( 'Floating bubble using global settings above', 'chatprojects' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h4><?php esc_html_e( 'Available Attributes', 'chatprojects' ); ?></h4>
			<table class="chatpr-shortcode-table chatpr-shortcode-table--attrs">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attribute', 'chatprojects' ); ?></th>
						<th><?php esc_html_e( 'Description', 'chatprojects' ); ?></th>
						<th><?php esc_html_e( 'Default', 'chatprojects' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>project</code></td>
						<td><?php esc_html_e( 'Project ID (find it in the Projects list)', 'chatprojects' ); ?></td>
						<td><?php esc_html_e( 'Global setting', 'chatprojects' ); ?></td>
					</tr>
					<tr>
						<td><code>mode</code></td>
						<td><code>inline</code> <?php esc_html_e( 'or', 'chatprojects' ); ?> <code>floating</code></td>
						<td><?php esc_html_e( 'inline when project is set', 'chatprojects' ); ?></td>
					</tr>
					<tr>
						<td><code>color</code></td>
						<td><?php esc_html_e( 'Hex color for the theme', 'chatprojects' ); ?></td>
						<td><?php echo esc_html( get_option( 'chatprojects_widget_primary_color', '#2563eb' ) ); ?></td>
					</tr>
					<tr>
						<td><code>title</code></td>
						<td><?php esc_html_e( 'Header title text', 'chatprojects' ); ?></td>
						<td><?php echo esc_html( get_bloginfo( 'name' ) ); ?></td>
					</tr>
					<tr>
						<td><code>welcome_message</code></td>
						<td><?php esc_html_e( 'First message shown to visitors', 'chatprojects' ); ?></td>
						<td><?php esc_html_e( 'Welcome Message setting', 'chatprojects' ); ?></td>
					</tr>
					<tr>
						<td><code>placeholder</code></td>
						<td><?php esc_html_e( 'Input field placeholder text', 'chatprojects' ); ?></td>
						<td><?php esc_html_e( 'Input Placeholder setting', 'chatprojects' ); ?></td>
					</tr>
					<tr>
						<td><code>height</code></td>
						<td><?php esc_html_e( 'Panel height in pixels (inline mode)', 'chatprojects' ); ?></td>
						<td>500</td>
					</tr>
				</tbody>
			</table>

			<div class="chatpr-shortcode-example">
				<strong><?php esc_html_e( 'Full example:', 'chatprojects' ); ?></strong>
				<code>[chatprojects_widget project="123" color="#e11d48" title="Support" welcome_message="Hi there!" height="600"]</code>
			</div>

			<p class="chatpr-shortcode-note">
				<?php esc_html_e( 'The project must have indexed content (files uploaded to its Vector Store). You can find the project ID in the Projects list or on the project edit screen URL.', 'chatprojects' ); ?>
			</p>
		</div>
	<?php endif; ?>
</div>
