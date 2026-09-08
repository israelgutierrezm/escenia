# Architecture Overview

## Estilo

El sistema inicia como **Modular Monolith**.

La arquitectura debe permitir extraer servicios en el futuro sin obligarnos a hacerlo ahora.

## Capas principales

```text
Vue Applications
      |
      v
Laravel Control Plane
      |
+-----+-------+-------------+
|             |             |
MySQL        Redis       Providers
                            |
              +-------------+----------------+
              |                              |
          LiveKit                        External APIs
              |
        Media Plane
              |
      Ingress / SFU / Egress
              |
      HLS / RTMP / Recording
              |
        Object Storage + CDN
```

## Control Plane

Responsabilidades:

- tenants;
- users;
- workspaces;
- events;
- permissions;
- registrations;
- commerce;
- automation;
- analytics orchestration;
- billing;
- provider orchestration;
- audit.

## Media Plane

Responsabilidades:

- WebRTC;
- ingress;
- SFU;
- egress;
- recording;
- composition;
- RTMP/SRT;
- HLS/LL-HLS.

LiveKit es implementación inicial.

## Integración entre dominios

Preferir:

- Application Services / Actions;
- Domain Events;
- Contracts;
- Jobs.

Evitar imports arbitrarios entre dominios.

## Escalamiento

Escalar independientemente:

- Laravel API;
- queue workers;
- Reverb;
- media nodes;
- egress workers;
- analytics ingestion;
- CDN.

## Principio

No optimizar prematuramente, pero no tomar decisiones que obliguen a reescribir el core.
