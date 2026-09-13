import { fileURLToPath, URL } from 'node:url'

import vue from '@vitejs/plugin-vue'
import { loadEnv } from 'vite'
import { defineConfig } from 'vitest/config'

export default defineConfig(({ mode }) => {
  const configDir = fileURLToPath(new URL('.', import.meta.url))
  const env = loadEnv(mode, configDir, '')

  const apiProxy = env.VITE_API_PROXY ?? process.env.VITE_API_PROXY ?? 'http://127.0.0.1:8000'
  const port = Number(env.VITE_PORT ?? process.env.VITE_PORT ?? '5173')

  return {
    plugins: [vue()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      port,
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
  }
})
