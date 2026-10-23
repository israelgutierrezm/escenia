# ADR-037 — Webhooks de automatizaciones: egress seguro y credenciales cifradas

## Status

Accepted (2026-10-22). Resuelve la deuda de ADR-025 (TD-023, TD-025) y extiende
a toda la plataforma el guard de egress de ADR-035.

## Context

El paso `webhook` de las automatizaciones (ADR-025) hace un POST desde el
servidor a una URL que configura el tenant. Solo exigía `https://`:

- **SSRF**: sin validar a qué dirección resolvía el host, y siguiendo hasta 5
  redirecciones (también de https a http). Un `https://` propio que redirigiera
  a `http://169.254.169.254/…` o a un servicio interno hacía que el servidor lo
  llamara, y el log del run devolvía el código de estado (un oráculo para
  explorar la red interna). El DNS también podía cambiar entre guardar y llamar.
- **Credenciales en claro y expuestas**: la URL y el secreto HMAC vivían en el
  JSON `config` sin cifrar, y la API los devolvía a quien tuviera
  `automations.view` — que incluye al rol Miembro. Muchos receptores (Slack,
  Discord, Zapier, Make) autentican **solo por la URL**, y con el secreto
  cualquiera podría falsificar envíos firmados.
- **Logs**: un fallo registraba la URL completa (y el mensaje de cURL también
  la incluye).
- La `config` de cada paso y las condiciones no se validaban por tipo.

## Decision

- **Egress de plataforma**: `OutboundUrlGuard` (ADR-035) deja de ser solo de
  Enterprise. Se registra en `AppServiceProvider` y se configura en
  `config/egress.php`. Toda llamada del servidor a una URL del tenant pasa por
  él: el host debe resolver **solo** a IPs públicas, la conexión se fija a la IP
  validada (`CURLOPT_RESOLVE`) y no se siguen redirecciones. La excepción para
  desarrollo local pasa a llamarse `EGRESS_ALLOW_PRIVATE` (se sigue leyendo
  `ENTERPRISE_EGRESS_ALLOW_PRIVATE`) y **se ignora en producción** (fail closed,
  como los fakes de ADR-034).
- **Webhook por el guard**: el handler llama solo a través de las opciones
  fijadas del guard, con `connect_timeout` de 5 s. Un destino bloqueado no se
  llama: el paso termina como `webhook_blocked (destination not allowed)` y la
  secuencia sigue (best-effort, como antes). Los logs llevan solo el **host** y
  la clase de la excepción.
- **Credenciales cifradas y nunca devueltas**: URL y secreto pasan a la columna
  `automation_steps.credentials` (`encrypted:array`, oculta en serialización),
  modelados por el value object `WebhookEndpoint`. La API muestra solo el
  **origen** (`config.target`, p. ej. `https://hooks.slack.com`) y `has_secret`.
  Una migración mueve las credenciales existentes (reversible). Mientras dure un
  despliegue gradual se sigue leyendo `config` como respaldo.
- **Validación al guardar** (TD-025): cada tipo de paso valida lo que su handler
  lee (`exclude_unless` por tipo): webhook = URL `https` cuyo host resuelve a
  una IP pública (la regla `PublicOutboundUrl` usa el mismo guard) + secreto
  opcional; `tag_contact` = etiqueta; `notify` = asunto y mensaje; `wait` = de
  1 s a 365 días. Las condiciones exigen `field`, un `op` válido y `value`. Se
  guardan solo las claves que lee cada tipo.

## Consequences

- Una automatización ya no puede alcanzar la red interna, ni por redirección ni
  por rebinding de DNS; el guard se comprueba al guardar (error temprano) y otra
  vez al llamar (la garantía real).
- **Contrato de la API**: los pasos webhook ya no devuelven `config.url` ni
  `config.secret` (no se pueden recuperar: para cambiarlos se crea otro paso);
  aparecen `config.target` y `has_secret`.
- Altas que antes se aceptaban con datos incompletos ahora devuelven 422.
- Guardar un webhook resuelve DNS: un host que aún no existe se rechaza.
- **Deuda**: la firma `X-Escenia-Signature` no lleva marca de tiempo (el
  receptor no puede rechazar reenvíos, TD-048); sin reintentos de webhook (TD-024).
