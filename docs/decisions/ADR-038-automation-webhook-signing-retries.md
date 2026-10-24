# ADR-038 — Webhooks de automatizaciones: firma Standard Webhooks, rotación y reintentos

## Status

Accepted (2026-10-24). Resuelve TD-048 y la parte de reintentos de TD-024;
completa ADR-037.

## Context

Tras ADR-037 los webhooks salían seguros (sin SSRF, credenciales cifradas),
pero:

- La firma `X-Escenia-Signature` era un HMAC hex del cuerpo, sin identificador
  ni marca de tiempo: un receptor no podía rechazar un envío capturado y
  reenviado (TD-048). El secreto lo elegía el tenant, sin requisitos.
- Nada se reintentaba (TD-024): un receptor caído perdía el envío, y un paso
  que lanzaba dejaba el run `failed` para siempre.
- Robustez del motor: `current_position` solo se guardaba en esperas, fin o
  fallo; un worker que moría a mitad de secuencia dejaba el run `running` para
  siempre, y `automations:resume` no tenía más exclusión que el mutex del
  scheduler (24 h si el proceso moría).

## Decision

- **Firma Standard Webhooks** (https://www.standardwebhooks.com): cada envío
  lleva `webhook-id`, `webhook-timestamp` y `webhook-signature`
  (`v1,` + base64 de HMAC-SHA256 de `id.timestamp.cuerpo`; varias firmas
  separadas por espacio durante una rotación). El id es
  `msg_{run}_{posición}`: estable entre reintentos y réplicas del outbox, para
  que el receptor deduplique; el timestamp es el de cada intento, para que
  rechace reenvíos (tolerancia recomendada: 5 minutos).
- **Secretos emitidos por el servidor**: `whsec_` + base64 de 32 bytes
  aleatorios, devueltos **una sola vez** (al crear la automatización, en
  `webhook_secrets`, o al rotar) con `Cache-Control: no-store`. Se acepta un
  secreto propio solo en ese formato (24–64 bytes), útil para recrear un paso
  conservando el del receptor; cualquier otro da 422. Todo paso nuevo firma.
- **Rotación**: `POST /automations/{a}/steps/{s}/secret`
  (`automations.manage`, auditada sin el secreto). El secreto anterior sigue
  firmando **24 h**; rotar de nuevo dentro de la gracia descarta el más antiguo
  (dos claves como máximo).
- **Pasos anteriores a este ADR**: su secreto libre se trata como *heredado*:
  siguen recibiendo `X-Escenia-Signature` (sin firma `v1`, para no inventar una
  derivación de clave no estándar) más `webhook-id`/`webhook-timestamp`, hasta
  que se rota; la rotación es la vía de migración. Las credenciales siguen
  siendo un JSON plano (`url`, `secret`, `legacy`, `previous_*`) que el código
  anterior todavía lee; sin la marca `legacy: false`, un secreto es heredado.
- **Reintentos del mismo paso** (`config/automation.php`, `RetryPolicy`):
  - Un webhook se reintenta ante error de conexión o timeout, host que no
    resuelve al enviar (`UnresolvableHostException`), 408, 429 y 5xx, con
    `Retry-After` respetado hasta 5 h. No se reintentan 3xx (no se siguen),
    el resto de 4xx (410 incluido), destinos bloqueados ni URLs no https.
  - Calendario: 1 min, 5 min, 30 min, 2 h, 5 h (6 intentos, ~7,6 h) con hasta
    un 10 % de jitter. Al agotarse, el paso termina como `gave_up` y la
    secuencia continúa (best-effort, como en ADR-025).
  - Un paso que lanza se reintenta a 1, 5 y 30 min; después el run falla
    (`failed_reason` acotado a 250 caracteres; el log, visible a quien tiene
    `automations.view`, solo lleva la clase de la excepción).
  - El reintento aparca el run en `waiting` en la **misma** posición, con
    `step_attempts` (columna nueva) contando los intentos fallidos; se pone a 0
    al avanzar. Un único contador para ambos tipos de reintento.
- **Idempotencia y exclusión**: checkpoint tras **cada** paso (un paso
  terminado nunca se repite); `resume_at` es, mientras el run está `running`,
  el **lease** del worker (10 min, renovado en cada checkpoint). Cada escritura
  del ejecutor exige el mismo lease, así que un worker que lo perdió se detiene
  en vez de pisar el run; `automations:resume` reclama cada run con un
  compare-and-set y recupera los `running` con lease vencido desde su último
  checkpoint. Los mutex de `outbox:dispatch` y `automations:resume` caducan a
  los 10 min. El log guarda una entrada por intento (`outcome`, `attempt`,
  `status`, `webhook_id`, `next_attempt_at`), nunca la URL, el secreto, las
  firmas ni la respuesta.

## Consequences

- Los receptores pueden verificar autenticidad, frescura e idempotencia con
  las librerías estándar (`standardwebhooks` en PHP, Node, Python…).
- Entrega **al menos una vez**: un paso en curso cuando cae un worker, o un
  receptor que responde tras el timeout de 10 s, puede recibir el mismo
  `webhook-id` dos veces (debe deduplicar ≥ 24 h). Un `notify` reintentado
  podría duplicar una notificación (TD-052).
- Un webhook que falla retrasa los pasos siguientes de su run hasta ~7,6 h.
- Contrato de la API: `POST /automations` añade `webhook_secrets`; los pasos
  webhook añaden `legacy_signature` y `previous_secret_expires_at`; los runs,
  `step_attempts` (y `resume_at` es null mientras corren). Un secreto propio
  que no sea `whsec_` ahora da 422.
- Rollback: el código anterior no envía `v1` (rompe a los receptores que ya
  verifican `webhook-signature`) y firma `X-Escenia-Signature` con la cadena
  `whsec_…` como clave. Revertir la migración de 2026-10-22 descarta las marcas
  `legacy`/`previous_*`. Orden de despliegue: migrar y luego el código.
- **Deuda**: TD-051 (retirar `X-Escenia-Signature` de los pasos heredados) y
  TD-052 (entrega síncrona en el scheduler, sin desactivar ante 410 ni avisar
  al rendirse, `notify` duplicable).
