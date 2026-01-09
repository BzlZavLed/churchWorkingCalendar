import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
  plugins: [vue()],
  base: command === 'build' ? '/spa/' : '/',
  build: {
    outDir: '../public/spa',
    emptyOutDir: true,
  },
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    origin: 'http://localhost:5173',
    proxy: {
      '/api': 'http://localhost:8000',
      '/broadcasting': 'http://localhost:8000',
    },
  },
}))
