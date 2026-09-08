# Billing & Entitlements

## Principios

Separar:

- Plan
- Feature
- Entitlement
- Subscription
- Usage
- Pricing

No usar `if plan === 'pro'` en el dominio.

## Feature Manager

API conceptual:

- canUse(feature)
- remaining(feature)
- consume(feature)
- limit(feature)

## Usage Ledger

Append-only.

Métricas previstas:

- studio_minutes
- viewer_minutes
- attendee_hours
- recording_minutes
- storage_bytes
- bandwidth
- AI usage
- transcription_minutes
- translation_minutes
- emails
- SMS
- WhatsApp
- destinations

## Pricing

Preparar:

base subscription + included usage + overage + add-ons.
