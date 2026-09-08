# Deployment Strategy

## Ambientes

- local
- testing
- staging
- production

## Pipeline objetivo

1. lint
2. static analysis
3. unit tests
4. feature tests
5. frontend tests
6. build
7. migration safety checks
8. deploy staging
9. smoke tests
10. deploy production

## Deploy DB

Preferir expand/migrate/contract.

No hacer cambios destructivos en el mismo deploy que elimina compatibilidad.

## Workers

Separar pools cuando la carga lo justifique:

- default
- notifications
- media
- analytics
- AI
- webhooks

## Health

Exponer health/readiness apropiados sin filtrar secretos.
