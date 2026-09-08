import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

const apiProxy = process.env.VITE_API_PROXY ?? 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    // Proxy API + Sanctum to the Laravel backend so the SPA and API share an
    // origin in development (no CORS, first-party cookies work out of the box).
    proxy: {
      '/api': { target: apiProxy, changeOrigin: true },
      '/sanctum': { target: apiProxy, changeOrigin: true },
    },
  },
  test: {
    environment: 'jsdom',
  },
})
