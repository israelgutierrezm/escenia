# ADR-002 — LiveKit como Media Plane inicial

## Status
Accepted

## Decision
Usar LiveKit inicialmente para WebRTC, ingress y egress.

Laravel será Control Plane.

Crear `MediaProviderContract` para evitar acoplamiento directo.

## Consequences
El proveedor puede reemplazarse o combinarse en el futuro.
