# ADR-039 — Tailwind 4 y navegadores soportados

## Status

Accepted (2026-10-24). Resuelve TD-049.

## Context

- Las cuatro apps (`admin`, `studio`, `attendee`, `speaker`) usaban Tailwind
  3.4 vía PostCSS (`tailwind.config.js` + `postcss.config.js` + autoprefixer).
  Tailwind 3 declara `postcss-selector-parser` ^6 y el aviso
  GHSA-rj75-hqrm-r3gf solo da por corregida la 7.1.6+, así que la raíz forzaba
  7.1.7 con `pnpm.overrides`: una combinación que Tailwind 3 no soporta
  (TD-049). Tailwind 3 ya no recibe desarrollo.
- Las apps apenas usan utilidades. Casi todo es CSS propio (`style.css` de
  cada app y `<style scoped>`) sobre los tokens `--escenia-*`. Solo cuatro
  clases coinciden con utilidades: `block`, `grid`, `sr-only` y `grow`, y solo
  `grow` en `EventDetailView` depende de la utilidad.
- El CSS propio ya exigía navegadores modernos: `color-mix()` está por todas
  partes (Chrome 111, Safari 16.2, Firefox 113).

## Decision

- **Tailwind 4.3 con `@tailwindcss/vite`**, no con `@tailwindcss/postcss`:
  es lo que recomienda la guía oficial para Vite, y hace innecesarios
  autoprefixer, `postcss.config.js` y `tailwind.config.js`. Las dependencias
  son `tailwindcss` y `@tailwindcss/vite` en cada app, siempre en la misma
  versión (`@tailwindcss/vite` fija su `tailwindcss` interno exacto). Se
  elimina el override: `postcss-selector-parser` queda solo por
  eslint-plugin-vue, en 7.1.7.
- **CSS-first, sin `@theme`**: los tokens siguen siendo `--escenia-*` en
  `packages/ui/tokens.css`.
- **Fuentes explícitas**: `@import 'tailwindcss' source(none)` más
  `@source` con los mismos globs que el `content` de v3 (`index.html`,
  `src/**/*.{vue,ts}` y `packages/ui/src`). La detección automática dependería
  del root de Vite y no ve `packages/ui` (llega por `node_modules`).
- **Compatibilidad con v3 en `@layer base`**, que pierde ante cualquier
  regla nuestra (las nuestras no tienen capa):
  - `cursor: pointer` en botones;
  - `[type='search']{appearance: textfield}`, sin el cual Safari dibuja su
    buscador nativo en `ListToolbar`.
  - No se añaden los snippets de color de borde, placeholder ni `<dialog>` de
    la guía: el inventario no encontró código que dependa de ellos.
- **Controles deshabilitados**: el Preflight de v4 pone `opacity: 1` a los
  controles y con eso anula la atenuación que Chrome aplicaba a los `<select>`
  deshabilitados. `.control:disabled` y los campos de `.field` deshabilitados
  llevan ahora `opacity: 0.6` y `cursor: not-allowed` en todos los
  navegadores. Antes un `<input>` deshabilitado no se distinguía.
- **Navegadores soportados** (decisión de producto): Safari/iOS 16.4+,
  Chrome/Edge 111+ y Firefox 128+ (ESR 115 con mejor esfuerzo), en las cuatro
  apps, también en las páginas públicas de attendee. Es lo que soporta Tailwind
  4 y casi coincide con lo que ya exigía `color-mix()`.

## Consequences

- El CSS generado se comparó con el de v3, aplicación por aplicación:
  - Nuestras reglas son semánticamente idénticas. Lightning CSS solo cambia el
    formato, reordena propiedades y da a `color-mix()` un respaldo opaco dentro
    de `@supports`.
  - Los chunks de los componentes son iguales, salvo los prefijos obsoletos
    `::-moz-placeholder` y `-o-object-fit`.
  - `-webkit-backdrop-filter` se mantiene. Lo añade el minificador de Vite en
    los componentes y Lightning CSS en `style.css`.
- Estilo computado de las páginas reales (admin con datos de la demo,
  studio, attendee, speaker): sin cambios, salvo estos tres, aceptados:
  - los campos de fecha y fecha-hora miden 2px menos y quedan iguales al resto
    de campos;
  - el padding de `<option>` en la lista nativa;
  - la atenuación de los controles deshabilitados.
- Navegadores sin `@layer` (iOS < 15.4, Chrome < 99) pierden el Preflight y la
  utilidad `grow`: la maquetación se degrada (márgenes y viñetas del navegador),
  pero no se rompe. Los que tienen `@layer` pero no `@property` (Safari
  15.4–16.3, Firefox 97–127) usan el respaldo de Tailwind.
- En desarrollo (`vite dev`) no corre Lightning CSS ni la minificación: Safari
  16.4–17 no verá el desenfoque de fondo de los componentes en dev (solo en
  dev). No se debe cambiar `build.cssTarget` ni `build.cssMinify` sin repetir
  la comparación.
- Código futuro escrito con defaults de v3 (bordes `gray-200`, placeholders
  `gray-400`) se verá distinto: en v4 el borde por defecto es `currentColor`.
  Los colores salen de los tokens.
- Cada subida de Tailwind actualiza `tailwindcss` y `@tailwindcss/vite`
  juntos. `pnpm why -r tailwindcss` debe mostrar una sola versión.
