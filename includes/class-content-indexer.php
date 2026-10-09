<?php
/**
 * Content Indexer Class
 *
 * Handles automatic indexing of WordPress content into project vector stores (Auto-RAG).
 * Extracts posts, pages, and other post types, converts them to text files,
 * and uploads them to OpenAI vector stores for AI-powered search.
 *
 * @package ChatProjects
 */

namespace ChatProjects;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Indexer Class
 */
class Content_Indexer {

	/**
	 * Maximum posts per batch processing tick.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 10;

	/**
	 * Maximum posts allowed in the free version.
	 *
	 * @var int
	 */
	const FREE_MAX_POSTS = 100;

	/**
	 * Delay between API uploads in seconds.
	 *
	 * @var int
	 */
	const UPLOAD_DELAY = 1;

	/**
	 * Maximum content length per post in bytes (50KB).
	 *
	 * @var int
	 */
	const MAX_CONTENT_LENGTH = 51200;

	/**
	 * Vector Store instance.
	 *
	 * @var Vector_Store
	 */
	private $vector_store;

	/**
	 * Whether hooks were added (the class is also instantiated by AJAX handlers).
	 *
	 * @var bool
	 */
	private static $hooks_added = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->vector_store = new Vector_Store();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		if ( self::$hooks_added ) {
			return;
		}
		self::$hooks_added = true;

		// Batch processing cron.
		add_action( 'chatprojects_process_index_batch', array( $this, 'process_batch_cron' ) );

		// Keep indexed content in step with the site: content that stops being
		// public must leave the vector store (the public widget can query it),
		// and edits should be re-indexed.
		add_action( 'transition_post_status', array( $this, 'on_post_status_change' ), 10, 3 );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 1 );
		add_action( 'before_delete_post', array( $this, 'on_post_deleted' ) );
		add_action( 'chatprojects_sync_indexed_post', array( $this, 'sync_post' ) );
	}

	/**
	 * Projects in which a post is indexed.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private function projects_indexing_post( $post_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT project_id FROM %i WHERE post_id = %d',
				$wpdb->prefix . 'chatprojects_indexed_content',
				absint( $post_id )
			)
		);
		return array_map( 'absint', (array) $ids );
	}

	/**
	 * A post changed status (published, unpublished, made private, trashed...).
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public function on_post_status_change( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status ) {
			$this->handle_post_change( $post->ID );
		}
	}

	/**
	 * A post was saved (content, title, password...).
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_post_updated( $post_id ) {
		$this->handle_post_change( $post_id );
	}

	/**
	 * A post is being permanently deleted: remove it from every index now.
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_post_deleted( $post_id ) {
		foreach ( $this->projects_indexing_post( $post_id ) as $project_id ) {
			$this->remove_post( $project_id, $post_id );
		}
	}

	/**
	 * Remove a no-longer-public post right away; queue re-indexing otherwise.
	 *
	 * @param int $post_id Post ID.
	 */
	private function handle_post_change( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$projects = $this->projects_indexing_post( $post_id );
		if ( empty( $projects ) ) {
			return;
		}

		if ( is_wp_error( $this->extract_content( $post_id ) ) ) {
			// Private, draft, password-protected or trashed: never leave it answerable.
			foreach ( $projects as $project_id ) {
				$this->withdraw_post( $project_id, $post_id );
			}
			return;
		}

		// Uploading is slow; re-index in the background (unchanged content is skipped).
		$args = array( (int) $post_id );
		if ( ! wp_next_scheduled( 'chatprojects_sync_indexed_post', $args ) ) {
			wp_schedule_single_event( time() + 30, 'chatprojects_sync_indexed_post', $args );
		}
	}

	/**
	 * Cron: bring a post's index entries up to date in every project.
	 *
	 * @param int $post_id Post ID.
	 */
	public function sync_post( $post_id ) {
		$indexable = ! is_wp_error( $this->extract_content( $post_id ) );
		foreach ( $this->projects_indexing_post( $post_id ) as $project_id ) {
			if ( $indexable ) {
				$this->index_post( $project_id, $post_id );
			} else {
				$this->withdraw_post( $project_id, $post_id );
			}
		}
	}

	/**
	 * Take a post that stopped being public out of a project's vector store.
	 *
	 * The tracking row is kept with status "removed" (no file) so the post is
	 * indexed again if it is published again.
	 *
	 * @param int $project_id Project ID.
	 * @param int $post_id    Post ID.
	 */
	private function withdraw_post( $project_id, $post_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'chatprojects_indexed_content';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
		$record = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, file_id FROM %i WHERE project_id = %d AND post_id = %d',
				$table,
				absint( $project_id ),
				absint( $post_id )
			)
		);
		if ( ! $record ) {
			return;
		}

		if ( ! empty( $record->file_id ) ) {
			$this->remove_file_from_store( $project_id, $record->file_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
		$wpdb->update(
			$table,
			array(
				'status'       => 'removed',
				'file_id'      => null,
				'content_hash' => '',
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'id' => $record->id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get allowed post types for indexing.
	 *
	 * Free version: posts and pages only.
	 * Pro version: configurable via settings.
	 *
	 * @return array Array of post type slugs.
	 */
	public function get_indexable_post_types() {
		$default_types = array( 'post', 'page' );

		if ( defined( 'CHATPROJECTS_PRO_VERSION' ) ) {
			$configured = get_option( 'chatprojects_autorag_post_types', $default_types );
			if ( is_array( $configured ) && ! empty( $configured ) ) {
				return $configured;
			}
		}

		return $default_types;
	}

	/**
	 * Extract content from a WordPress post into structured text.
	 *
	 * Produces a metadata header followed by the plain-text content,
	 * suitable for upload to an OpenAI vector store.
	 *
	 * @param int $post_id Post ID.
	 * @return string|\WP_Error Extracted text content or error.
	 */
	public function extract_content( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'invalid_post', __( 'Post not found or not published.', 'chatprojects' ) );
		}

		// Password-protected or otherwise non-public content must never reach a vector store
		// that the public widget can query.
		if ( '' !== $post->post_password || ( function_exists( 'is_post_publicly_viewable' ) && ! is_post_publicly_viewable( $post ) ) ) {
			return new \WP_Error( 'private_post', __( 'Post is not publicly viewable.', 'chatprojects' ) );
		}

		// Build metadata header.
		$lines = array();
		$lines[] = 'Title: ' . $post->post_title;
		$lines[] = 'URL: ' . get_permalink( $post_id );
		$lines[] = 'Type: ' . $post->post_type;
		$lines[] = 'Date: ' . get_the_date( 'Y-m-d', $post_id );
		$lines[] = 'Author: ' . get_the_author_meta( 'display_name', $post->post_author );

		// Categories (only if taxonomy exists for this post type).
		$categories = get_the_terms( $post_id, 'category' );
		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			$cat_names = wp_list_pluck( $categories, 'name' );
			$lines[]   = 'Categories: ' . implode( ', ', $cat_names );
		}

		// Tags.
		$tags = get_the_terms( $post_id, 'post_tag' );
		if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
			$tag_names = wp_list_pluck( $tags, 'name' );
			$lines[]   = 'Tags: ' . implode( ', ', $tag_names );
		}

		// Excerpt.
		if ( ! empty( $post->post_excerpt ) ) {
			$lines[] = 'Excerpt: ' . $post->post_excerpt;
		}

		$lines[] = '---';

		// Strip shortcodes first to avoid executing them, then strip HTML tags.
		$content = $post->post_content;
		$content = strip_shortcodes( $content );
		$content = wp_strip_all_tags( $content );

		// Normalize whitespace: collapse multiple newlines but keep paragraph breaks.
		$content = preg_replace( '/\n{3,}/', "\n\n", $content );
		$content = trim( $content );

		$lines[] = $content;

		$full_text = implode( "\n", $lines );

		// Enforce maximum content length.
		if ( strlen( $full_text ) > self::MAX_CONTENT_LENGTH ) {
			$full_text = substr( $full_text, 0, self::MAX_CONTENT_LENGTH );
		}

		return $full_text;
	}

	/**
	 * Generate a content hash for change detection.
	 *
	 * @param string $content Extracted text content.
	 * @return string SHA-256 hash.
	 */
	public function generate_content_hash( $content ) {
		return hash( 'sha256', $content );
	}

	/**
	 * Index a single post into a project's vector store.
	 *
	 * @param int $project_id Project ID.
	 * @param int $post_id    Post ID.
	 * @return array|\WP_Error Result array with file_id on success, or error.
	 */
	public function index_post( $project_id, $post_id ) {
		global $wpdb;

		$project_id = absint( $project_id );
		$post_id    = absint( $post_id );

		// Extract content.
		$content = $this->extract_content( $post_id );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$content_hash = $this->generate_content_hash( $content );
		$post         = get_post( $post_id );
		$table        = $wpdb->prefix . 'chatprojects_indexed_content';

		// Check if already indexed with same hash.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, file_id, content_hash, status FROM %i WHERE project_id = %d AND post_id = %d",
				$table,
				$project_id,
				$post_id
			)
		);

		if ( $existing && 'indexed' === $existing->status && $existing->content_hash === $content_hash ) {
			// Content unchanged, skip.
			return array(
				'status'  => 'skipped',
				'file_id' => $existing->file_id,
				'reason'  => 'content_unchanged',
			);
		}

		// If previously indexed with different content, remove old file first.
		if ( $existing && ! empty( $existing->file_id ) ) {
			$this->remove_file_from_store( $project_id, $existing->file_id );
		}

		// Generate filename.
		$filename = sprintf( 'wp-%s-%d.txt', sanitize_key( $post->post_type ), $post_id );

		// Upload to vector store.
		$result = $this->vector_store->upload_content_file( $project_id, $content, $filename );

		if ( is_wp_error( $result ) ) {
			// Record failure.
			$now = current_time( 'mysql' );
			$this->upsert_index_record( $project_id, $post_id, $post->post_type, $content_hash, array(
				'status'        => 'failed',
				'error_message' => $result->get_error_message(),
				'updated_at'    => $now,
			) );
			return $result;
		}

		// Record success.
		$now = current_time( 'mysql' );
		$this->upsert_index_record( $project_id, $post_id, $post->post_type, $content_hash, array(
			'file_id'       => $result['id'],
			'status'        => 'indexed',
			'error_message' => null,
			'indexed_at'    => $now,
			'updated_at'    => $now,
		) );

		return array(
			'status'  => 'indexed',
			'file_id' => $result['id'],
		);
	}

	/**
	 * Remove a post from the project's vector store index.
	 *
	 * @param int $project_id Project ID.
	 * @param int $post_id    Post ID.
	 * @return bool|\WP_Error True on success, error on failure.
	 */
	public function remove_post( $project_id, $post_id ) {
		global $wpdb;

		$project_id = absint( $project_id );
		$post_id    = absint( $post_id );
		$table      = $wpdb->prefix . 'chatprojects_indexed_content';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, file_id FROM %i WHERE project_id = %d AND post_id = %d",
				$table,
				$project_id,
				$post_id
			)
		);

		if ( ! $record ) {
			return new \WP_Error( 'not_indexed', __( 'Post is not indexed in this project.', 'chatprojects' ) );
		}

		// Remove file from vector store.
		if ( ! empty( $record->file_id ) ) {
			$this->remove_file_from_store( $project_id, $record->file_id );
		}

		// Delete tracking record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
		$wpdb->delete( $table, array( 'id' => $record->id ), array( '%d' ) );

		return true;
	}

	/**
	 * Get index status for a project.
	 *
	 * @param int $project_id Project ID.
	 * @return array Status counts: total, indexed, pending, failed.
	 */
	public function get_index_status( $project_id ) {
		global $wpdb;

		$project_id = absint( $project_id );
		$table      = $wpdb->prefix . 'chatprojects_indexed_content';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table aggregation
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count FROM %i WHERE project_id = %d GROUP BY status",
				$table,
				$project_id
			)
		);

		$status = array(
			'total'   => 0,
			'indexed' => 0,
			'pending' => 0,
			'failed'  => 0,
		);

		if ( $results ) {
			foreach ( $results as $row ) {
				if ( 'removed' === $row->status ) {
					continue; // Unpublished posts waiting to be re-indexed.
				}
				$status[ $row->status ] = absint( $row->count );
				$status['total']       += absint( $row->count );
			}
		}

		// Count total indexable posts.
		$status['available'] = $this->count_indexable_posts( $this->get_indexable_post_types() );

		return $status;
	}

	/**
	 * Start a batch indexing job for a project.
	 *
	 * @param int   $project_id Project ID.
	 * @param array $post_types Post types to index.
	 * @return array|\WP_Error Job data on success, error on failure.
	 */
	public function start_batch_index( $project_id, $post_types = array() ) {
		$project_id = absint( $project_id );

		// Verify project exists and has a vector store.
		$vector_store_id = get_post_meta( $project_id, '_cp_vector_store_id', true );
		if ( empty( $vector_store_id ) ) {
			return new \WP_Error( 'no_vector_store', __( 'Project does not have a vector store.', 'chatprojects' ) );
		}

		// Check for existing running job.
		$existing_job = get_transient( 'chatpr_index_job_' . $project_id );
		if ( $existing_job && 'running' === $existing_job['status'] ) {
			return new \WP_Error( 'job_running', __( 'An indexing job is already running for this project.', 'chatprojects' ) );
		}

		// Validate post types.
		$allowed_types = $this->get_indexable_post_types();
		if ( empty( $post_types ) ) {
			$post_types = $allowed_types;
		} else {
			$post_types = array_intersect( $post_types, $allowed_types );
		}

		if ( empty( $post_types ) ) {
			return new \WP_Error( 'no_post_types', __( 'No valid post types selected for indexing.', 'chatprojects' ) );
		}

		// Count total posts to index.
		$total = $this->count_indexable_posts( $post_types );

		// Enforce free version limit.
		if ( ! defined( 'CHATPROJECTS_PRO_VERSION' ) && $total > self::FREE_MAX_POSTS ) {
			$total = self::FREE_MAX_POSTS;
		}

		if ( 0 === $total ) {
			return new \WP_Error( 'no_posts', __( 'No published posts found for the selected post types.', 'chatprojects' ) );
		}

		// Create job.
		$job_id = 'idx_' . wp_generate_password( 12, false );
		$job    = array(
			'job_id'     => $job_id,
			'project_id' => $project_id,
			'post_types' => $post_types,
			'total'      => $total,
			'processed'  => 0,
			'indexed'    => 0,
			'skipped'    => 0,
			'failed'     => 0,
			'offset'     => 0,
			'status'     => 'running',
			'errors'     => array(),
			'started_at' => current_time( 'mysql' ),
		);

		// Store job state (expires in 1 hour).
		set_transient( 'chatpr_index_job_' . $project_id, $job, HOUR_IN_SECONDS );

		// Schedule first batch.
		if ( ! wp_next_scheduled( 'chatprojects_process_index_batch', array( $project_id ) ) ) {
			wp_schedule_single_event( time() + 5, 'chatprojects_process_index_batch', array( $project_id ) );
		}

		return $job;
	}

	/**
	 * Process the next batch of posts for a project's indexing job.
	 *
	 * Called by WP-Cron.
	 *
	 * @param int $project_id Project ID.
	 */
	public function process_batch_cron( $project_id ) {
		$project_id = absint( $project_id );
		$job        = get_transient( 'chatpr_index_job_' . $project_id );

		if ( ! $job || 'running' !== $job['status'] ) {
			return;
		}

		$limit = self::BATCH_SIZE;

		// Enforce free version limit.
		if ( ! defined( 'CHATPROJECTS_PRO_VERSION' ) ) {
			$remaining_allowed = self::FREE_MAX_POSTS - $job['processed'];
			if ( $remaining_allowed <= 0 ) {
				$job['status'] = 'completed';
				set_transient( 'chatpr_index_job_' . $project_id, $job, HOUR_IN_SECONDS );
				return;
			}
			$limit = min( $limit, $remaining_allowed );
		}

		// Get posts to process.
		$posts = $this->get_posts_for_indexing( $job['post_types'], $limit, $job['offset'] );

		if ( empty( $posts ) ) {
			$job['status'] = 'completed';
			set_transient( 'chatpr_index_job_' . $project_id, $job, HOUR_IN_SECONDS );
			return;
		}

		foreach ( $posts as $post ) {
			$result = $this->index_post( $project_id, $post->ID );

			$job['processed']++;

			if ( is_wp_error( $result ) ) {
				$job['failed']++;
				$job['errors'][] = array(
					'post_id' => $post->ID,
					'title'   => $post->post_title,
					'error'   => $result->get_error_message(),
				);
			} elseif ( 'skipped' === $result['status'] ) {
				$job['skipped']++;
			} else {
				$job['indexed']++;
			}

			// Delay between uploads to respect API rate limits.
			if ( 'indexed' === ( $result['status'] ?? '' ) ) {
				sleep( self::UPLOAD_DELAY );
			}
		}

		$job['offset'] += count( $posts );

		// Check if more posts remain.
		$next_posts = $this->get_posts_for_indexing( $job['post_types'], 1, $job['offset'] );

		if ( empty( $next_posts ) || ( ! defined( 'CHATPROJECTS_PRO_VERSION' ) && $job['processed'] >= self::FREE_MAX_POSTS ) ) {
			$job['status'] = 'completed';
		}

		// Update job state.
		set_transient( 'chatpr_index_job_' . $project_id, $job, HOUR_IN_SECONDS );

		// Schedule next batch if still running.
		if ( 'running' === $job['status'] ) {
			wp_schedule_single_event( time() + 30, 'chatprojects_process_index_batch', array( $project_id ) );
		}
	}

	/**
	 * Cancel a running indexing job.
	 *
	 * @param int $project_id Project ID.
	 * @return bool True if cancelled, false if no job was running.
	 */
	public function cancel_job( $project_id ) {
		$project_id = absint( $project_id );
		$job        = get_transient( 'chatpr_index_job_' . $project_id );

		if ( ! $job || 'running' !== $job['status'] ) {
			return false;
		}

		$job['status'] = 'cancelled';
		set_transient( 'chatpr_index_job_' . $project_id, $job, HOUR_IN_SECONDS );

		// Unschedule pending cron event.
		$timestamp = wp_next_scheduled( 'chatprojects_process_index_batch', array( $project_id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'chatprojects_process_index_batch', array( $project_id ) );
		}

		return true;
	}

	/**
	 * Get the current job status for a project.
	 *
	 * @param int $project_id Project ID.
	 * @return array|null Job data or null if no job exists.
	 */
	public function get_job_status( $project_id ) {
		$project_id = absint( $project_id );
		return get_transient( 'chatpr_index_job_' . $project_id );
	}

	/**
	 * Clear all indexed content for a project.
	 *
	 * @param int $project_id Project ID.
	 * @return int Number of records removed.
	 */
	public function clear_index( $project_id ) {
		global $wpdb;

		$project_id = absint( $project_id );
		$table      = $wpdb->prefix . 'chatprojects_indexed_content';

		// Get all file IDs to remove from vector store.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
		$records = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT file_id FROM %i WHERE project_id = %d AND file_id IS NOT NULL",
				$table,
				$project_id
			)
		);

		foreach ( $records as $record ) {
			if ( ! empty( $record->file_id ) ) {
				$this->remove_file_from_store( $project_id, $record->file_id );
			}
		}

		// Delete all tracking records.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
		$deleted = $wpdb->delete(
			$table,
			array( 'project_id' => $project_id ),
			array( '%d' )
		);

		// Clear any running job.
		delete_transient( 'chatpr_index_job_' . $project_id );

		return absint( $deleted );
	}

	/**
	 * Get indexed items list for a project.
	 *
	 * @param int $project_id Project ID.
	 * @param int $limit      Number of items to return.
	 * @param int $offset     Offset for pagination.
	 * @return array Array of indexed item records.
	 */
	public function get_indexed_items( $project_id, $limit = 50, $offset = 0 ) {
		global $wpdb;

		$project_id = absint( $project_id );
		$table      = $wpdb->prefix . 'chatprojects_indexed_content';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ic.*, p.post_title FROM %i ic
				LEFT JOIN {$wpdb->posts} p ON ic.post_id = p.ID
				WHERE ic.project_id = %d AND ic.status <> 'removed'
				ORDER BY ic.indexed_at DESC
				LIMIT %d OFFSET %d",
				$table,
				$project_id,
				$limit,
				$offset
			)
		);
	}

	// ==================== PRIVATE HELPERS ====================

	/**
	 * Insert or update an index tracking record.
	 *
	 * @param int    $project_id   Project ID.
	 * @param int    $post_id      Post ID.
	 * @param string $post_type    Post type.
	 * @param string $content_hash Content hash.
	 * @param array  $data         Additional data to store.
	 */
	private function upsert_index_record( $project_id, $post_id, $post_type, $content_hash, $data = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'chatprojects_indexed_content';
		$now   = current_time( 'mysql' );

		// Check if record exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE project_id = %d AND post_id = %d",
				$table,
				$project_id,
				$post_id
			)
		);

		$record = array_merge(
			array(
				'project_id'   => $project_id,
				'post_id'      => $post_id,
				'post_type'    => $post_type,
				'content_hash' => $content_hash,
			),
			$data
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
			$wpdb->update( $table, $record, array( 'id' => $existing ) );
		} else {
			$record['created_at'] = $now;
			if ( ! isset( $record['updated_at'] ) ) {
				$record['updated_at'] = $now;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert
			$wpdb->insert( $table, $record );
		}
	}

	/**
	 * Remove a file from the vector store and project file list.
	 *
	 * Silently handles errors to avoid blocking batch operations.
	 *
	 * @param int    $project_id Project ID.
	 * @param string $file_id    OpenAI file ID.
	 */
	private function remove_file_from_store( $project_id, $file_id ) {
		// Use the Vector_Store's delete method which handles
		// both vector store removal and file metadata cleanup.
		// We call the API directly here because the delete_file method
		// requires edit permission checks that may not apply during cron.
		$api             = new API_Handler();
		$vector_store_id = get_post_meta( $project_id, '_cp_vector_store_id', true );

		if ( ! empty( $vector_store_id ) ) {
			$api->remove_file_from_vector_store( $vector_store_id, $file_id );
		}

		$api->delete_file( $file_id );

		// Remove from project file metadata.
		$files = get_post_meta( $project_id, '_cp_files', true );
		if ( is_array( $files ) ) {
			$files = array_filter(
				$files,
				function ( $file ) use ( $file_id ) {
					return $file['file_id'] !== $file_id;
				}
			);
			update_post_meta( $project_id, '_cp_files', array_values( $files ) );
		}
	}

	/**
	 * Count indexable posts for the given post types.
	 *
	 * @param array $post_types Post types to count.
	 * @return int Total count.
	 */
	private function count_indexable_posts( $post_types ) {
		$total = 0;
		foreach ( (array) $post_types as $post_type ) {
			$counts = wp_count_posts( $post_type );
			$total += isset( $counts->publish ) ? (int) $counts->publish : 0;
		}
		return $total;
	}

	/**
	 * Get posts for indexing.
	 *
	 * @param array $post_types Post types to query.
	 * @param int   $limit      Number of posts to return.
	 * @param int   $offset     Offset for pagination.
	 * @return array Array of WP_Post objects.
	 */
	private function get_posts_for_indexing( $post_types, $limit, $offset ) {
		return get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
	}
}
