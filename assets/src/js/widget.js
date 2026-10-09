/**
 * ChatProjects Widget - Entry Point.
 *
 * Standalone IIFE bundle that initializes ChatWidget instances.
 * Supports two initialization sources:
 *   - window.chatprWidgetConfig:    Global floating widget (auto-inject or shortcode without project).
 *   - window.chatprWidgetInstances: Inline widget instances from shortcodes with project attribute.
 *
 * Config is injected by WordPress via wp_localize_script / wp_add_inline_script.
 *
 * @package ChatProjects
 */

import { ChatWidget } from './widget/ChatWidget.js';

( function () {
	// Legacy floating widget (auto-inject or shortcode without project).
	if ( window.chatprWidgetConfig ) {
		window.chatprWidget = new ChatWidget( window.chatprWidgetConfig );
	}

	// Inline widget instances from shortcodes with project attribute.
	if ( window.chatprWidgetInstances && window.chatprWidgetInstances.length ) {
		window.chatprWidgets = [];
		for ( const instanceConfig of window.chatprWidgetInstances ) {
			const targetEl = document.querySelector(
				'[data-cpw-instance="' + instanceConfig.instanceId + '"]'
			);
			if ( targetEl ) {
				instanceConfig.targetEl = targetEl;
				window.chatprWidgets.push( new ChatWidget( instanceConfig ) );
			}
		}
	}
} )();
