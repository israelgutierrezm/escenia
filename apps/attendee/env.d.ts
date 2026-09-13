/// <reference types="vite/client" />

declare global {
  interface ImportMetaEnv {
    readonly VITE_API_URL?: string
    readonly VITE_API_PROXY?: string
  }
}

declare module 'vue-router' {
  interface RouteMeta {
    requiresToken?: boolean
  }
}

export {}
