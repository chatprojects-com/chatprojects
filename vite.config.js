import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    outDir: 'assets/dist',
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'assets/src/main.js'),
        chat: resolve(__dirname, 'assets/src/chat.js'),
        comparison: resolve(__dirname, 'assets/src/comparison.js'),
        admin: resolve(__dirname, 'assets/src/admin.js'),
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name.endsWith('.css')) {
            return 'css/[name].css';
          }
          return 'assets/[name]-[hash][extname]';
        },
        // Manual chunks for better code splitting
        manualChunks: {
          // Alpine.js core (used across all pages)
          'alpine-core': ['alpinejs'],
          // Markdown rendering (only for chat)
          'vendor-markdown': ['marked'],
          // Code highlighting (only for chat with code blocks)
          'vendor-highlight': ['highlight.js/lib/core'],
        },
      },
    },
    manifest: true,
    emptyOutDir: false,
    sourcemap: false,
    // Reduce chunk size warning limit
    chunkSizeWarningLimit: 600,
    // Enable minification
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: false, // Keep console.logs for debugging
        drop_debugger: true,
      },
    },
  },
  css: {
    postcss: './postcss.config.js',
  },
  // Optimize dependencies
  optimizeDeps: {
    include: ['alpinejs'],
    exclude: ['highlight.js', 'marked'], // Lazy load these
  },
});
