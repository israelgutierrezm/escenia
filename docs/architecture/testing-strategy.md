# Testing Strategy

## Backend

- Unit
- Feature
- Integration
- Policy
- Job
- Event
- Webhook
- Provider contract tests

## Frontend

- component tests
- composable/store tests
- Playwright E2E

## Flujos críticos

1. signup tenant
2. create workspace
3. invite team
4. create event
5. registration
6. guest join
7. device check
8. backstage
9. stage
10. start broadcast
11. scene change
12. poll/Q&A/CTA
13. viewer watch
14. stop broadcast
15. recording
16. analytics
17. payment
18. certificate

## Multi-tenancy

Agregar tests cross-tenant sistemáticamente.

## Providers

Usar fake implementations.

La suite normal no debe necesitar Internet.
