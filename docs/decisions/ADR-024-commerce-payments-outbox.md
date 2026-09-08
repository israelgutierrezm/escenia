# ADR-024 — Commerce: tickets, gateways de pago, checkout y Outbox

## Status

Accepted (2026-09-08)

## Context

La Fase 7 monetiza los eventos: venta de **tickets**, **checkout** público, cobro vía **Stripe** y **Mercado Pago**, **CTAs** in-event y **revenue analytics**. Es la primera vez que el sistema mueve dinero e integra proveedores de pago externos con webhooks — el caso canónico del **Outbox (ADR-007)** ("payment completed"). El dinero nunca se representa como `float` (CLAUDE.md); ya existe el Value Object `Money` (minor units + ISO-4217).

## Decision

- **Dominio `Commerce` nuevo** (tenant-owned, ULID): `Ticket`, `Order` + `OrderItem`, `Payment`, `PaymentAccount`, `Cta` + `CtaClick`. Todo el dinero se guarda como enteros `*_minor` + `currency` y se expone con `Money` (`{minor_units, currency}`), nunca float. El precio se **snapshotea** en `order_items` para no reescribir historia.

- **Gateways detrás de `PaymentGateway` (contract)** — el dominio solo habla `Money` y Value Objects agnósticos (`PaymentIntentResult`, `GatewayEvent`); jamás el SDK. `FakePaymentGateway` (default, determinista, testeado) + adaptadores `Stripe` (PaymentIntents + verificación HMAC de `Stripe-Signature`) y `MercadoPago` (preferencia + `init_point`, match por `external_reference`, fetch del pago para el status). Selección por `PaymentGatewayFactory` según el `PaymentAccount` del tenant, con fallback a `config/payments.php`. **Credenciales cifradas** (`encrypted` cast) + `$hidden`, nunca en logs/auditoría/analytics (como stream keys, ADR-021). Los adaptadores reales **no** están probados contra infra (deuda; el default `fake` mantiene dev/tests deterministas).

- **Checkout público**: evento resuelto **sin scope**, tenant derivado (nunca del request), como el registro. `StartCheckoutAction` emite el asistente del comprador (reutiliza `IssueAttendeeAction`, compartido con el registro) devolviendo el join token **una vez**, crea la `Order` (pending) + items, y crea el intent vía gateway. **La llamada externa al gateway va fuera de la transacción** para no retener locks. La orden solo pasa a `paid` por el **webhook verificado**.

- **Order lifecycle** (`pending → paid → refunded`, o `pending → canceled`) guardado + optimistic locking (`422 invalid_order_transition` / `409 order_conflict`), como los demás. `ConfirmPaymentAction` aplica el `GatewayEvent`: en `paid` reserva stock (`sold_count` avanza **en el cobro**, no en el checkout — evita retener inventario por carritos abandonados) y **escribe `order.paid` en el Outbox** en la misma transacción; en `refunded` libera stock. Idempotente ante webhooks repetidos (unique `(gateway, gateway_reference)` + compare-and-swap).

- **Webhooks** públicos `POST /checkout/webhooks/{account}`: la cuenta se identifica por su ULID público en la URL, pero la **autenticidad viene de la firma** verificada por el gateway con el `webhook_secret` de la cuenta, no de la URL. El cuerpo crudo se pasa sin parsear para que la firma sobre los bytes exactos verifique.

- **Outbox (ADR-007) implementado**: tabla `outbox_events` append-only + `DispatchOutboxAction` + comando `outbox:dispatch` (agendado cada minuto, `withoutOverlapping`). Lee **sin scope** (proceso de sistema), enruta cada evento a su handler dentro del contexto de tenant del evento, y marca `processed_at`; un handler que falla incrementa `attempts` y se reintenta (at-least-once). Consumidor de `order.paid` = `FulfillPaidOrderHandler`: registra revenue en el plano de analytics y sella `fulfilled_at` (idempotente).

- **CTAs**: el host las publica (opcionalmente ligadas a un ticket = oferta); el asistente ve las **live** (ventana activa) y los clicks se trackean (`commerce.cta_clicked`). "Offers" = CTA con `compare_at`; códigos de descuento → deuda.

- **Revenue analytics**: nuevos eventos analíticos `commerce.order_paid` / `commerce.cta_clicked`. El reporte de revenue (`EventCommerceReport`) se lee del **ledger OLTP** (orders/items), no del plano de telemetría, porque el dinero debe ser exacto: bruto/refunded/neto, órdenes, ticket medio, por ticket, y CTR de CTAs.

- **RBAC** `commerce.view` / `commerce.manage` en `EventPolicy`; `PaymentAccount` es tenant-level (chequeo directo de permiso). Frontend: `ApiClient` de host + `AttendeeClient` (checkout público + CTAs) en `@escenia/api-client`.

## Consequences

- Los gateways son intercambiables/combinables; el dominio no ve el SDK y el dinero nunca es float.
- Las credenciales de pago nunca se exponen ni se registran.
- El Outbox garantiza la publicación de `order.paid` y queda como infraestructura reutilizable (migrará el lifecycle de broadcast/engagement, TD-010/013).
- Deuda: adaptadores Stripe/MP sin tests de integración ni verificación exacta de firma en producción; `createIntent` es una llamada externa síncrona en el request; reserva de stock en el cobro (posible oversell bajo concurrencia); reembolsos solo vía webhook (sin refund iniciado desde el host); códigos de descuento. Ver `technical-debt.md` (TD-019..022).
