/// <reference types="vite/client" />

declare global {
  interface ImportMetaEnv {
    readonly VITE_API_URL?: string
    readonly VITE_API_PROXY?: string
    readonly VITE_REVERB_APP_KEY?: string
    readonly VITE_REVERB_HOST?: string
    readonly VITE_REVERB_PORT?: string
    readonly VITE_REVERB_SCHEME?: string
  }
}

declare module 'vue-router' {
  interface RouteMeta {
    requiresToken?: boolean
  }
}

export {}
