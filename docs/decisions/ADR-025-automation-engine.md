# ADR-025 — Automation: motor de workflows sobre el Outbox

## Status

Accepted (2026-09-08)

## Context

La Fase 8 añade automatización: reaccionar a hechos del sistema (alguien se
registró, pagó, terminó un evento) ejecutando una **secuencia** de acciones con
**lógica condicional** — webhooks salientes, acciones de CRM y notificaciones
(incluye los **reminders** diferidos de Fase 5). Ya existe el **Outbox
(ADR-007)** como backbone fiable de eventos y el `Contact` como raíz de CRM.

## Decision

- **El Outbox es el backbone de triggers.** Los eventos de trigger
  (`registration.completed`, `order.paid`, `event.ended`) se escriben al outbox
  en la **misma transacción** que confirma el hecho, en sus choke points. El
  dispatcher pasa de `topic→handler` a **`topic→lista de handlers`**, así un
  mismo evento alimenta varios consumidores (p.ej. `order.paid` → fulfilment
  **y** automatización). Las automatizaciones reaccionan solo a hechos
  committeados, at-least-once.

- **Dominio `Automation`**: `Automation` (trigger + condiciones + pasos),
  `AutomationStep` (posición, tipo, `config`, condiciones), `AutomationRun`
  (snapshot de contexto, status, `current_position`, `resume_at`, `log`). CRM:
  `contact_tags`. Notificaciones: modelo `Notification` + contract `Notifier`.

- **Motor** (`HandleAutomationTrigger`, consumidor del outbox): construye un
  **contexto aplanado** (`TriggerContextBuilder`) con campos públicos punteados
  (`event.type`, `contact.email`, `order.total_minor`) para condiciones/webhooks
  y claves internas `_`-prefijadas (ids) para acciones — que **nunca salen** del
  sistema. Busca automatizaciones activas del trigger, evalúa condiciones y
  arranca un `AutomationRun`, **único por (automation, outbox_event)** para no
  duplicar secuencias. `AutomationExecutor` corre los pasos desde
  `current_position` hasta un `wait` o el final.

- **Conditional logic segura**: `ConditionEvaluator` evalúa `{field, op, value}`
  (eq/ne/gt/gte/lt/lte/contains) contra el contexto, en AND — **sin ejecución de
  código**; condiciones malformadas **fallan cerradas** (la automatización no
  dispara). Las condiciones aplican a nivel automation y por paso (branching).

- **Pasos** detrás de `StepHandler` (registry tipo→handler): `webhook` (POST
  saliente **firmado HMAC**, solo https, best-effort — un endpoint flaky no
  detiene la secuencia), `tag_contact` (CRM, idempotente), `notify` (vía
  `Notifier`; `LogNotifier` por defecto registra la notificación — email real es
  un canal futuro), `wait` (agenda `resume_at` y pausa = **secuencias**).

- **Reanudación**: un `wait` deja el run en `waiting`; el comando
  `automations:resume` (agendado cada minuto, `withoutOverlapping`) continúa los
  runs cuyo `resume_at` venció — mismo patrón que el dispatcher del outbox.

- **RBAC** `automations.view`/`automations.manage` (tenant-level, chequeo directo
  de permiso). Las automatizaciones son tenant-level; sus condiciones filtran por
  atributos del evento. HTTP host: CRUD + activar/desactivar + listado de runs.
  Frontend: métodos `automations*` en `@escenia/api-client`.

## Consequences

- El motor reacciona a hechos committeados de forma fiable y desacoplada; añadir
  un trigger nuevo = escribir un topic al outbox + registrar el handler.
- La costura de notificaciones (`Notifier`) habilita los reminders sin acoplar el
  motor a un proveedor de email.
- Deuda: SSRF en webhooks salientes (solo https + firma; sin bloqueo de rangos
  privados); reintentos de webhook/run fallido (un run que lanza es terminal);
  el envío real de notificaciones (email/SMS) es un canal futuro; validación de
  `config`/condiciones por tipo de paso es laxa (el motor lee a la defensiva);
  escribir triggers al outbox añade una fila+ciclo por registro/pago. Ver
  `technical-debt.md` (TD-023..025).
