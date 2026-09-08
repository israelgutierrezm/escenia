# Frontend Architecture

## Aplicaciones

```text
apps/
  admin/
  studio/
  attendee/
  speaker/
  public/

packages/
  ui/
  api/
  types/
  auth/
  event-sdk/
```

No es obligatorio crear todas desde Fase 0 si el repositorio aún es pequeño, pero la estructura debe permitirlo.

## Studio

Prioridad desktop.

Módulos previstos:

- Preview
- Program
- Participants
- Green Room
- Backstage
- Scenes
- Media
- Brand
- Chat/Q&A
- Polls
- CTA
- Destinations
- Health
- Run of Show

## Attendee

Mobile-first/responsive.

## Estado

Pinia para estado de aplicación.

No guardar server state complejo de forma duplicada sin estrategia.

## Tipado

Compartir contratos API mediante tipos generados o package común.

TypeScript strict obligatorio.
