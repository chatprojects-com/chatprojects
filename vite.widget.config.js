/**
 * Vite Build Configuration for ChatProjects Widget.
 *
 * Produces a single, self-contained IIFE bundle with no external dependencies.
 * The widget must work as a single <script> tag on any page.
 *
 * Usage: npx vite build --config vite.widget.config.js
 *
 * @package ChatProjects
 */

import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig( {
	// The plugin's public/ folder holds PHP, not static assets; don't copy it into dist.
	publicDir: false,
	build: {
		outDir: 'assets/dist',
		rollupOptions: {
			input: {
				widget: resolve( __dirname, 'assets/src/js/widget.js' ),
			},
			output: {
				entryFileNames: 'js/widget.js',
				assetFileNames: ( assetInfo ) => {
					if ( assetInfo.name.endsWith( '.css' ) ) {
						return 'css/widget.css';
					}
					return 'assets/[name][extname]';
				},
				format: 'iife',
				inlineDynamicImports: true,
			},
		},
		emptyOutDir: false,
		sourcemap: false,
		minify: 'terser',
		terserOptions: {
			compress: {
				drop_console: true,
				drop_debugger: true,
			},
		},
	},
	css: {
		postcss: false,
	},
} );
