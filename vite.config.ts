import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig(() => {
  return {
    plugins: [
      laravel({
        input: ['resources/js/main.tsx'],
        refresh: true,
      }),
      react(),
      tailwindcss(),
    ],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'resources/js'),
        '@/src': path.resolve(__dirname, 'resources/js'),
      },
    },
  };
});
