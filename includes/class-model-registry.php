<?php
/**
 * Model Registry
 *
 * Single source of truth for every AI model the plugin knows about: labels,
 * defaults, capability flags, and the legacy-ID remap used when providers
 * retire models. Everything that renders a model list, validates a model ID,
 * or builds a provider request should go through this class.
 *
 * @package ChatProjects
 * @since   1.2.0
 */

namespace ChatProjects;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Model Registry Class
 */
final class Model_Registry {

	/**
	 * Built catalogue cache (provider => model id => entry).
	 *
	 * @var array|null
	 */
	private static $catalogue = null;

	/**
	 * Pattern a runtime-fetched (Chutes / OpenRouter) model id must match.
	 * Column chatprojects_chats.model is VARCHAR(100).
	 */
	const DYNAMIC_ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,99}$/';

	/**
	 * Default values merged into every catalogue entry.
	 *
	 * @var array
	 */
	private static $entry_defaults = array(
		'label'                => '',
		'default'              => false,
		'supports_temperature' => true,
		'reasoning'            => false,
		'reasoning_efforts'    => array(),
		'max_output'           => 16384,
		'context'              => 128000,
		'tier'                 => 'standard',
	);

	/**
	 * Provider metadata.
	 *
	 * 'dynamic' providers fetch their model list at runtime; the static list
	 * below is only a fallback used until the fetch succeeds.
	 *
	 * @return array
	 */
	public static function get_providers() {
		$providers = array(
			'openai'     => array(
				'name'    => 'OpenAI',
				'dynamic' => false,
			),
			'anthropic'  => array(
				'name'    => 'Anthropic Claude',
				'dynamic' => false,
			),
			'gemini'     => array(
				'name'    => 'Google Gemini',
				'dynamic' => false,
			),
			'chutes'     => array(
				'name'    => 'Chutes.ai',
				'dynamic' => true,
			),
			'openrouter' => array(
				'name'    => 'OpenRouter',
				'dynamic' => true,
			),
		);

		/**
		 * Filter the provider map.
		 *
		 * @param array $providers provider id => array( 'name', 'dynamic' ).
		 */
		return apply_filters( 'chatprojects_providers', $providers );
	}

	/**
	 * Provider id => display name.
	 *
	 * @return array
	 */
	public static function get_provider_names() {
		$names = array();
		foreach ( self::get_providers() as $id => $meta ) {
			$names[ $id ] = $meta['name'];
		}
		return $names;
	}

	/**
	 * Whether a provider fetches its model list at runtime.
	 *
	 * @param string $provider Provider id.
	 * @return bool
	 */
	public static function is_dynamic_provider( $provider ) {
		$providers = self::get_providers();
		return ! empty( $providers[ $provider ]['dynamic'] );
	}

	/**
	 * Raw (unfiltered) catalogue definition.
	 *
	 * Model IDs verified against provider documentation on 2026-09-03.
	 * Keep this list to models that are currently served; retired IDs belong
	 * in the legacy map, not here.
	 *
	 * @return array
	 */
	private static function definition() {
		$openai_efforts = array( 'none', 'low', 'medium', 'high', 'xhigh' );

		return array(
			'openai'     => array(
				'gpt-5.6-sol'   => array(
					'label'                => __( 'GPT-5.6 Sol (Recommended)', 'chatprojects' ),
					'default'              => true,
					'supports_temperature' => false,
					'reasoning'            => true,
					'reasoning_efforts'    => array_merge( $openai_efforts, array( 'max' ) ),
					'max_output'           => 128000,
					'context'              => 1050000,
					'tier'                 => 'flagship',
				),
				'gpt-5.6-terra' => array(
					'label'                => __( 'GPT-5.6 Terra (Balanced)', 'chatprojects' ),
					'supports_temperature' => false,
					'reasoning'            => true,
					'reasoning_efforts'    => $openai_efforts,
					'max_output'           => 128000,
					'context'              => 1050000,
					'tier'                 => 'balanced',
				),
				'gpt-5.6-luna'  => array(
					'label'                => __( 'GPT-5.6 Luna (Fast & Low Cost)', 'chatprojects' ),
					'supports_temperature' => false,
					'reasoning'            => true,
					'reasoning_efforts'    => $openai_efforts,
					'max_output'           => 128000,
					'context'              => 1050000,
					'tier'                 => 'fast',
				),
				'gpt-5.5'       => array(
					'label'                => __( 'GPT-5.5', 'chatprojects' ),
					'supports_temperature' => false,
					'reasoning'            => true,
					'reasoning_efforts'    => $openai_efforts,
					'max_output'           => 128000,
					'context'              => 1050000,
					'tier'                 => 'flagship',
				),
				'gpt-5.4'       => array(
					'label'                => __( 'GPT-5.4', 'chatprojects' ),
					'supports_temperature' => false,
					'reasoning'            => true,
					'reasoning_efforts'    => $openai_efforts,
					'max_output'           => 128000,
					'context'              => 400000,
					'tier'                 => 'balanced',
				),
				'gpt-5.4-mini'  => array(
					'label'                => __( 'GPT-5.4 Mini', 'chatprojects' ),
					'supports_temperature' => false,
					'reasoning'            => true,
					'reasoning_efforts'    => $openai_efforts,
					'max_output'           => 128000,
					'context'              => 400000,
					'tier'                 => 'fast',
				),
				'gpt-5.4-nano'  => array(
					'label'                => __( 'GPT-5.4 Nano (Cheapest)', 'chatprojects' ),
					'supports_temperature' => false,
					'reasoning'            => true,
					'reasoning_efforts'    => $openai_efforts,
					'max_output'           => 128000,
					'context'              => 400000,
					'tier'                 => 'fast',
				),
			),
			'anthropic'  => array(
				'claude-opus-5'     => array(
					'label'                => __( 'Claude Opus 5 (Recommended)', 'chatprojects' ),
					'default'              => true,
					'supports_temperature' => false,
					'reasoning'            => true,
					'max_output'           => 128000,
					'context'              => 1000000,
					'tier'                 => 'flagship',
				),
				'claude-fable-5-1'  => array(
					'label'                => __( 'Claude Fable 5.1 (Most Capable)', 'chatprojects' ),
					'supports_temperature' => false,
					'reasoning'            => true,
					'max_output'           => 128000,
					'context'              => 1000000,
					'tier'                 => 'flagship',
				),
				'claude-opus-4-8'   => array(
					'label'                => __( 'Claude Opus 4.8', 'chatprojects' ),
					'supports_temperature' => false,
					'reasoning'            => true,
					'max_output'           => 128000,
					'context'              => 1000000,
					'tier'                 => 'flagship',
				),
				'claude-sonnet-5'   => array(
					'label'                => __( 'Claude Sonnet 5 (Balanced)', 'chatprojects' ),
					'supports_temperature' => false,
					'reasoning'            => true,
					'max_output'           => 128000,
					'context'              => 1000000,
					'tier'                 => 'balanced',
				),
				'claude-sonnet-4-6' => array(
					'label'                => __( 'Claude Sonnet 4.6', 'chatprojects' ),
					'supports_temperature' => true,
					'reasoning'            => true,
					'max_output'           => 128000,
					'context'              => 1000000,
					'tier'                 => 'balanced',
				),
				'claude-haiku-4-5'  => array(
					'label'                => __( 'Claude Haiku 4.5 (Fast)', 'chatprojects' ),
					'supports_temperature' => true,
					'reasoning'            => false,
					'max_output'           => 64000,
					'context'              => 200000,
					'tier'                 => 'fast',
				),
			),
			'gemini'     => array(
				'gemini-3.8-flash'      => array(
					'label'      => __( 'Gemini 3.8 Flash (Recommended)', 'chatprojects' ),
					'default'    => true,
					'max_output' => 65536,
					'context'    => 1048576,
					'tier'       => 'balanced',
				),
				'gemini-3.7-flash'      => array(
					'label'      => __( 'Gemini 3.7 Flash', 'chatprojects' ),
					'max_output' => 65536,
					'context'    => 1048576,
					'tier'       => 'balanced',
				),
				'gemini-3.5-flash'      => array(
					'label'      => __( 'Gemini 3.5 Flash', 'chatprojects' ),
					'max_output' => 65536,
					'context'    => 1048576,
					'tier'       => 'balanced',
				),
				'gemini-3.5-flash-lite' => array(
					'label'      => __( 'Gemini 3.5 Flash Lite (Fast)', 'chatprojects' ),
					'max_output' => 65536,
					'context'    => 1048576,
					'tier'       => 'fast',
				),
				'gemini-3.1-pro-preview' => array(
					'label'      => __( 'Gemini 3.1 Pro (Preview)', 'chatprojects' ),
					'max_output' => 65536,
					'context'    => 1048576,
					'tier'       => 'flagship',
				),
			),
			'chutes'     => array(
				'deepseek-v4-flash' => array(
					'label'      => __( 'DeepSeek V4 Flash (Recommended)', 'chatprojects' ),
					'default'    => true,
					'max_output' => 65536,
					'context'    => 1000000,
					'tier'       => 'balanced',
				),
				'deepseek-v4-pro'   => array(
					'label'      => __( 'DeepSeek V4 Pro', 'chatprojects' ),
					'max_output' => 65536,
					'context'    => 1000000,
					'tier'       => 'flagship',
				),
			),
			'openrouter' => array(),
		);
	}

	/**
	 * Legacy model id => current model id.
	 *
	 * Keys ending in '*' are prefix matches. Keys starting with '/' are regex.
	 * Exact keys are checked first, then prefixes, then regexes, in order.
	 *
	 * @return array
	 */
	public static function get_legacy_map() {
		$map = array(
			// OpenAI general-purpose chat models -> balanced tier.
			'gpt-5.2-chat-latest'   => 'gpt-5.6-terra',
			'gpt-5.2'               => 'gpt-5.6-terra',
			'gpt-5.1-chat-latest'   => 'gpt-5.6-terra',
			'gpt-5.1'               => 'gpt-5.6-terra',
			'gpt-5-chat-latest'     => 'gpt-5.6-terra',
			'gpt-5'                 => 'gpt-5.6-terra',
			'gpt-4.1'               => 'gpt-5.6-terra',
			'gpt-4.1-mini'          => 'gpt-5.6-terra',
			'gpt-4o'                => 'gpt-5.6-terra',
			'gpt-4o-mini'           => 'gpt-5.6-terra',
			'gpt-4-turbo'           => 'gpt-5.6-terra',
			'gpt-4'                 => 'gpt-5.6-terra',
			'gpt-3.5-turbo'         => 'gpt-5.6-terra',
			'chatgpt-4o-latest'     => 'gpt-5.6-terra',
			// Small OpenAI models -> mini.
			'gpt-5-mini'            => 'gpt-5.4-mini',
			'gpt-5-nano'            => 'gpt-5.4-mini',
			'gpt-4.1-nano'          => 'gpt-5.4-mini',
			// Anthropic.
			'claude-haiku-4-5-20251001' => 'claude-haiku-4-5',
			'claude-3-5-haiku*'     => 'claude-haiku-4-5',
			'claude-3-haiku*'       => 'claude-haiku-4-5',
			'claude-sonnet-4-*'     => 'claude-sonnet-5',
			'claude-opus-4-*'       => 'claude-opus-5',
			'/^claude-3(-\d)?-sonnet/' => 'claude-sonnet-5',
			'/^claude-3-7-sonnet/'  => 'claude-sonnet-5',
			'/^claude-3-opus/'      => 'claude-opus-5',
			'/^claude-2/'           => 'claude-sonnet-5',
			// Gemini.
			'gemini-2.5-pro'        => 'gemini-3.1-pro-preview',
			'gemini-3-pro-preview'  => 'gemini-3.1-pro-preview',
			'gemini-pro-1.5'        => 'gemini-3.1-pro-preview',
			'gemini-1.5-pro*'       => 'gemini-3.1-pro-preview',
			'gemini-2.0-*'          => 'gemini-3.8-flash',
			'gemini-2.5-flash*'     => 'gemini-3.8-flash',
			'gemini-3-flash-preview' => 'gemini-3.8-flash',
			'gemini-3.1-flash-lite-preview' => 'gemini-3.5-flash-lite',
			'gemini-1.5-flash*'     => 'gemini-3.8-flash',
			'gemini-pro'            => 'gemini-3.8-flash',
			// DeepSeek (direct or via Chutes).
			'deepseek-chat'         => 'deepseek-v4-flash',
			'deepseek-reasoner'     => 'deepseek-v4-flash',
			'deepseek-ai/DeepSeek-V3*' => 'deepseek-v4-flash',
			'deepseek-ai/DeepSeek-R1*' => 'deepseek-v4-pro',
			// OpenAI reasoning-only models -> flagship (regex so o1-mini etc. match).
			'/^o[134](-|$)/'        => 'gpt-5.6-sol',
			// Any other gpt-4* / gpt-3.5* snapshot.
			'gpt-4*'                => 'gpt-5.6-terra',
			'gpt-3.5*'              => 'gpt-5.6-terra',
		);

		/**
		 * Filter the legacy model remap table.
		 *
		 * @param array $map legacy id (exact, 'prefix*', or '/regex/') => current id.
		 */
		return apply_filters( 'chatprojects_legacy_models', $map );
	}

	/**
	 * Build (once) and return the filtered catalogue.
	 *
	 * @return array
	 */
	private static function catalogue() {
		if ( null !== self::$catalogue ) {
			return self::$catalogue;
		}

		$catalogue = self::definition();

		/**
		 * Filter the model catalogue.
		 *
		 * Add a model:
		 *   $models['openai']['ft:gpt-5.4-mini:acme::abc'] = array(
		 *       'label' => 'Acme fine-tune', 'supports_temperature' => false, 'reasoning' => true,
		 *   );
		 * Remove a model: unset( $models['gemini']['gemini-3.5-flash'] );
		 *
		 * @param array $catalogue provider => model id => entry.
		 */
		$catalogue = apply_filters( 'chatprojects_models', $catalogue );

		foreach ( $catalogue as $provider => $models ) {
			if ( ! is_array( $models ) ) {
				$catalogue[ $provider ] = array();
				continue;
			}
			foreach ( $models as $id => $entry ) {
				if ( is_string( $entry ) ) {
					$entry = array( 'label' => $entry );
				}
				$entry = wp_parse_args( (array) $entry, self::$entry_defaults );
				if ( '' === $entry['label'] ) {
					$entry['label'] = $id;
				}
				$catalogue[ $provider ][ $id ] = $entry;
			}
		}

		self::$catalogue = $catalogue;
		return self::$catalogue;
	}

	/**
	 * Reset the cache (tests / after runtime filter changes).
	 */
	public static function flush_cache() {
		self::$catalogue = null;
	}

	/**
	 * All model entries for a provider.
	 *
	 * @param string $provider Provider id.
	 * @return array model id => entry.
	 */
	public static function get_models( $provider ) {
		$catalogue = self::catalogue();
		return isset( $catalogue[ $provider ] ) ? $catalogue[ $provider ] : array();
	}

	/**
	 * A single model entry.
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return array|null
	 */
	public static function get_model( $provider, $model ) {
		$models = self::get_models( $provider );
		return isset( $models[ $model ] ) ? $models[ $model ] : null;
	}

	/**
	 * Look a model up across every static provider.
	 *
	 * @param string $model Model id.
	 * @return array|null Entry, or null when unknown.
	 */
	public static function find_model( $model ) {
		$provider = self::find_provider( $model );
		return $provider ? self::get_model( $provider, $model ) : null;
	}

	/**
	 * id => label map.
	 *
	 * @param string|null $provider Provider id, or null for provider => ( id => label ).
	 * @return array
	 */
	public static function get_labels( $provider = null ) {
		if ( null !== $provider ) {
			$labels = array();
			foreach ( self::get_models( $provider ) as $id => $entry ) {
				$labels[ $id ] = $entry['label'];
			}
			return $labels;
		}

		$all = array();
		foreach ( array_keys( self::get_providers() ) as $pid ) {
			$all[ $pid ] = self::get_labels( $pid );
		}
		return $all;
	}

	/**
	 * Default model id for a provider.
	 *
	 * @param string $provider Provider id.
	 * @return string Empty string when the provider has no static models.
	 */
	public static function get_default( $provider ) {
		$models = self::get_models( $provider );
		foreach ( $models as $id => $entry ) {
			if ( ! empty( $entry['default'] ) ) {
				return $id;
			}
		}
		$ids = array_keys( $models );
		return $ids ? (string) $ids[0] : '';
	}

	/**
	 * Whether a model id is acceptable for a provider.
	 *
	 * Static providers: must be in the catalogue. Dynamic providers: must be
	 * a plausible id (runtime lists cannot be validated cheaply).
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return bool
	 */
	public static function is_known( $provider, $model ) {
		if ( ! is_string( $model ) || '' === $model ) {
			return false;
		}
		if ( null !== self::get_model( $provider, $model ) ) {
			return true;
		}
		if ( self::is_dynamic_provider( $provider ) ) {
			return (bool) preg_match( self::DYNAMIC_ID_PATTERN, $model );
		}
		return false;
	}

	/**
	 * Provider that owns a static model id.
	 *
	 * @param string $model Model id.
	 * @return string|null
	 */
	public static function find_provider( $model ) {
		foreach ( self::catalogue() as $provider => $models ) {
			if ( isset( $models[ $model ] ) ) {
				return $provider;
			}
		}
		return null;
	}

	/**
	 * Map a retired model id to its current replacement.
	 *
	 * Returns the input unchanged when no rule matches, which makes the
	 * migration routine idempotent.
	 *
	 * @param string $model Model id.
	 * @return string
	 */
	public static function remap_legacy( $model ) {
		if ( ! is_string( $model ) || '' === $model ) {
			return $model;
		}

		// Current ids are never remapped.
		if ( null !== self::find_provider( $model ) ) {
			return $model;
		}

		$map = self::get_legacy_map();

		if ( isset( $map[ $model ] ) ) {
			return $map[ $model ];
		}

		foreach ( $map as $pattern => $target ) {
			if ( '*' === substr( $pattern, -1 ) ) {
				$prefix = substr( $pattern, 0, -1 );
				if ( 0 === strpos( $model, $prefix ) ) {
					return $target;
				}
			}
		}

		foreach ( $map as $pattern => $target ) {
			if ( '/' === $pattern[0] && preg_match( $pattern, $model ) ) {
				return $target;
			}
		}

		return $model;
	}

	/**
	 * Validate / repair a model id coming from user input or storage.
	 *
	 * Order: known -> legacy remap -> fallback -> provider default.
	 *
	 * @param string      $provider Provider id.
	 * @param string      $model    Candidate model id.
	 * @param string|null $fallback Fallback id (itself validated).
	 * @return string
	 */
	public static function resolve( $provider, $model, $fallback = null ) {
		$model = is_string( $model ) ? trim( $model ) : '';

		if ( self::is_known( $provider, $model ) ) {
			return $model;
		}

		if ( '' !== $model ) {
			$remapped = self::remap_legacy( $model );
			if ( $remapped !== $model && self::is_known( $provider, $remapped ) ) {
				return $remapped;
			}
		}

		if ( null !== $fallback && '' !== $fallback && $fallback !== $model ) {
			return self::resolve( $provider, $fallback, null );
		}

		return self::get_default( $provider );
	}

	/**
	 * Model for internal utility calls (all OpenAI unless noted).
	 *
	 * @param string $purpose title | enhance | transcribe | anthropic_validate.
	 * @return string
	 */
	public static function get_utility_model( $purpose ) {
		$defaults = array(
			'title'              => 'gpt-5.4-nano',
			'enhance'            => 'gpt-5.4-mini',
			'transcribe'         => 'gpt-transcribe',
			'anthropic_validate' => 'claude-haiku-4-5',
		);
		$model = isset( $defaults[ $purpose ] ) ? $defaults[ $purpose ] : self::get_default( 'openai' );

		/**
		 * Filter the model used for a background/utility task.
		 *
		 * @param string $model   Model id.
		 * @param string $purpose Task identifier.
		 */
		return apply_filters( 'chatprojects_utility_model', $model, $purpose );
	}

	/**
	 * Default reasoning effort for OpenAI reasoning models.
	 *
	 * @param string $model Model id.
	 * @return string
	 */
	public static function get_reasoning_effort( $model ) {
		/**
		 * Filter the reasoning effort sent to OpenAI.
		 *
		 * @param string $effort One of none|low|medium|high|xhigh|max.
		 * @param string $model  Model id.
		 */
		return apply_filters( 'chatprojects_reasoning_effort', 'medium', $model );
	}

	/**
	 * Build the model-dependent part of an OpenAI Responses API body.
	 *
	 * Drops temperature for reasoning models, adds reasoning.effort, clamps
	 * max_output_tokens. Unknown (filter-added) models pass options through.
	 *
	 * @param string $model   Model id.
	 * @param array  $options Caller options (temperature, max_tokens, reasoning_effort).
	 * @return array Keys to merge into the request body.
	 */
	public static function openai_request_params( $model, $options = array() ) {
		$entry  = self::get_model( 'openai', $model );
		$params = array();

		if ( null === $entry ) {
			if ( isset( $options['temperature'] ) ) {
				$params['temperature'] = (float) $options['temperature'];
			}
			if ( isset( $options['max_tokens'] ) ) {
				$params['max_output_tokens'] = absint( $options['max_tokens'] );
			}
			return $params;
		}

		if ( $entry['supports_temperature'] && isset( $options['temperature'] ) ) {
			$params['temperature'] = (float) $options['temperature'];
		}

		if ( $entry['reasoning'] ) {
			$effort  = isset( $options['reasoning_effort'] ) ? $options['reasoning_effort'] : self::get_reasoning_effort( $model );
			$allowed = $entry['reasoning_efforts'];
			if ( ! empty( $allowed ) && ! in_array( $effort, $allowed, true ) ) {
				$effort = in_array( 'medium', $allowed, true ) ? 'medium' : $allowed[0];
			}
			if ( '' !== $effort ) {
				$params['reasoning'] = array( 'effort' => $effort );
			}
		}

		if ( isset( $options['max_tokens'] ) ) {
			$params['max_output_tokens'] = min( absint( $options['max_tokens'] ), (int) $entry['max_output'] );
		}

		return $params;
	}

	/**
	 * Sanitize callback for the project default model option (OpenAI only).
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_openai_model( $value ) {
		return self::resolve( 'openai', is_string( $value ) ? sanitize_text_field( $value ) : '' );
	}

	/**
	 * Sanitize callback for the general-chat model option (any provider).
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_any_model( $value ) {
		$value    = is_string( $value ) ? sanitize_text_field( $value ) : '';
		$provider = get_option( 'chatprojects_general_chat_provider', 'openai' );

		if ( '' !== $value ) {
			$owner = self::find_provider( $value );
			if ( $owner ) {
				return $value;
			}
			$remapped = self::remap_legacy( $value );
			if ( $remapped !== $value && self::find_provider( $remapped ) ) {
				return $remapped;
			}
			if ( self::is_dynamic_provider( $provider ) && preg_match( self::DYNAMIC_ID_PATTERN, $value ) ) {
				return $value;
			}
		}

		return self::get_default( $provider );
	}
}
