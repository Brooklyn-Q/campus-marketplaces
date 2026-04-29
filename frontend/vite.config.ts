import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  base: '/public/',
  plugins: [react()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        app: './index.html'
      },
      output: {
        entryFileNames: `[name].js`,
        chunkFileNames: `[name].js`,
        assetFileNames: `[name].[ext]`,
        manualChunks(id) {
          const normalizedId = id.replace(/\\/g, '/');
          if (!normalizedId.includes('/node_modules/')) {
            return undefined;
          }

          if (normalizedId.includes('react-router-dom')) return 'router';
          if (normalizedId.includes('/react-dom/') || normalizedId.includes('/react/')) return 'react-vendor';
          if (normalizedId.includes('framer-motion') || normalizedId.includes('/motion/')) return 'motion';
          if (normalizedId.includes('chart.js') || normalizedId.includes('react-chartjs-2')) return 'charts';
          if (normalizedId.includes('@paper-design/shaders')) return 'shaders';
          if (normalizedId.includes('lucide-react') || normalizedId.includes('react-icons')) return 'icons';

          return 'vendor';
        }
      }
    }
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      'components': path.resolve(__dirname, './components'),
      'lib': path.resolve(__dirname, './lib')
    }
  }
});
