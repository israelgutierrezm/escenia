# Realtime Architecture

## Dos sistemas realtime diferentes

### Media realtime
LiveKit / WebRTC.

Transporta:

- audio;
- video;
- screen share;
- realtime media metadata.

### Application realtime
Laravel Reverb.

Transporta:

- participant waiting;
- stage changes;
- scene changes;
- poll lifecycle;
- CTA;
- broadcast state;
- destination errors;
- moderation events.

## Seguridad

Usar canales privados/presence.

La autorización de un channel debe validar tenant + workspace/event/session ownership.

Conocer un ID no da derecho a subscribirse.
