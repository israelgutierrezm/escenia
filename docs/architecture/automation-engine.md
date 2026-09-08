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
