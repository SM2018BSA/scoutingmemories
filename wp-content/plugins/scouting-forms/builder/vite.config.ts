import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    outDir: path.resolve(__dirname, '../assets/builder'),
    emptyOutDir: true,
    // Class private fields stay as they are, so no helper functions are put outside the
    // wrapper below (every browser WordPress supports runs ES2022)
    target: 'es2022',
    // The styles stay their own file, builder.css (an IIFE build would put them in builder.js)
    cssCodeSplit: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/main.ts'),
      output: {
        // One self-contained function: WordPress loads builder.js as a plain script, and an ES
        // module's top-level names would land on window. A minified "function wp" replaced
        // WordPress's own wp object (wp.svgPainter, wp.i18n, ...) on the builder screens.
        format: 'iife',
        entryFileNames: 'builder.js',
        chunkFileNames: 'chunks/[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'builder.css';
          }
          return 'assets/[name][extname]';
        },
      },
    },
  },
});
