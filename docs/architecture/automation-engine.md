# Automation Engine

## Objetivo

Crear workflows visuales versionados.

## Entidades conceptuales

- Workflow
- WorkflowVersion
- Node
- Edge
- Trigger
- Condition
- Action
- Execution
- ExecutionStep

## Triggers

- registered
- attended
- did_not_attend
- joined
- left
- poll_answered
- question_asked
- cta_clicked
- resource_downloaded
- purchase
- recording_watched
- certificate_earned

## Actions

- email
- SMS
- WhatsApp
- webhook
- CRM update
- tag
- segment
- sales task
- coupon
- certificate
- notification

## Reglas técnicas

- versionado;
- idempotencia;
- retries;
- execution history;
- dead-letter/error state;
- no ejecutar steps críticos dos veces.

## Webhooks salientes (ADR-037)

- Solo `https`, y siempre a través del guard de egress de plataforma
  (`OutboundUrlGuard`): el host debe resolver solo a IPs públicas, la conexión
  se fija a la IP validada y no se siguen redirecciones. Se comprueba al
  guardar el paso y otra vez en cada envío; un destino bloqueado no se llama.
- La URL y el secreto son credenciales (muchos receptores autentican solo por
  la URL): se guardan cifrados en `automation_steps.credentials` y la API solo
  muestra el origen y si el envío va firmado.
- Cada envío lleva `X-Escenia-Signature` = HMAC-SHA256 del cuerpo con el
  secreto del paso. El cuerpo nunca incluye las claves internas del contexto
  (prefijo `_`).
- Best-effort: un receptor caído no detiene la secuencia; el run registra el
  resultado (`webhook_sent`, `webhook_failed`, `webhook_blocked`) y los logs
  solo el host.
