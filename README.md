# Event Operating System (Escenia)

Plataforma SaaS para producción audiovisual, webinars, streaming, eventos virtuales/híbridos, formación, monetización, automatización, analytics e IA.

Laravel es el **control plane** (API JSON pura); las SPAs de Vue viven en `apps/*`. La infraestructura de media (LiveKit) será el **media plane** en fases posteriores.

## Documentación inicial

Lee, en orden:

1. `CLAUDE.md`
2. `AGENTS.md`
3. `docs/product/master-specification.md`
4. `docs/architecture/overview.md`
5. `docs/roadmap/master-roadmap.md`
6. `docs/progress/current-state.md`
7. Los ADRs en `docs/decisions/`

## Arquitectura base

- Laravel 12: Control Plane · MySQL 8: OLTP · Redis: cache/locks/queues
- Vue 3 + TypeScript: aplicaciones web · LiveKit: Media Plane (futuro)
- S3/R2 + CDN: media (futuro) · Reverb: realtime (futuro) · ClickHouse/Qdrant: cuando se justifiquen

## Estructura del repositorio

```text
app/                  Backend Laravel (Domain / Application / Infrastructure / Http)
routes/ config/ database/ tests/   Backend
apps/
  admin/              SPA de administración (Vue 3 + TS)
packages/
  types/              Contratos de API compartidos
  api-client/         Cliente HTTP tipado (Sanctum + X-Tenant-Id)
  ui/                 Design system base (tokens + componentes)
docs/                 Fuente de verdad arquitectónica (product, architecture, decisions, ...)
```

## Requisitos

PHP 8.3+, Composer 2, MySQL 8+, Node 22+, pnpm 9+.

## Puesta en marcha — Backend

```bash
composer install
cp .env.example .env
php artisan key:generate
# Crea la base de datos `escenia` en MySQL, luego:
php artisan migrate --seed
php artisan serve   # http://127.0.0.1:8000
```

## Puesta en marcha — Frontend

```bash
pnpm install
pnpm --filter @escenia/admin dev   # http://localhost:5173 (proxy a /api y /sanctum)
```

## Calidad

```bash
# Backend
./vendor/bin/pint --test
php -d memory_limit=512M ./vendor/bin/phpstan analyse
php artisan test

# Frontend
pnpm -r typecheck
pnpm --filter @escenia/admin lint
pnpm --filter @escenia/admin test
pnpm --filter @escenia/admin build
```

CI (`.github/workflows/ci.yml`) ejecuta ambos conjuntos en cada push/PR.

## Estado

**Fase 0 — Foundation** completada. Ver `docs/progress/current-state.md`. No avanzar a Fase 1 sin revisión.
