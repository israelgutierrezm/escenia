# Technology Stack

## Backend

- PHP 8.3+ compatible con Laravel 12
- Laravel 12
- Sanctum para auth first-party cuando corresponda
- Spatie Laravel Permission como base RBAC
- Laravel Horizon
- Laravel Reverb
- Redis
- MySQL 8+

## Frontend

- Vue 3
- TypeScript strict
- Vite
- Pinia
- Vue Router
- Tailwind CSS

## Media

- LiveKit
- WebRTC
- RTMP / RTMPS
- SRT
- WHIP
- HLS / LL-HLS
- FFmpeg workers

## Storage / Delivery

- S3-compatible object storage
- Cloudflare R2 como alternativa
- CDN: Cloudflare / CloudFront

## Data

- MySQL: OLTP
- Redis: cache, locks, queues, ephemeral state
- ClickHouse: analytics cuando sea necesario
- Qdrant: embeddings/búsqueda semántica cuando sea necesario

## Observability

- Structured JSON logs
- OpenTelemetry
- Sentry
- Prometheus-compatible metrics

## Testing

Backend:
- PHPUnit/Pest según convención elegida
- PHPStan/Larastan
- Laravel Pint

Frontend:
- Vitest
- Vue Test Utils
- Playwright
- ESLint
- Prettier
