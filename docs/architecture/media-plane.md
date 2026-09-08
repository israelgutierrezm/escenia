# Media Plane

## Principio

Laravel NO transporta video.

## Proveedor inicial

LiveKit.

## Abstracción

Crear `MediaProviderContract`.

Operaciones esperadas:

- createRoom
- closeRoom
- createIngress
- deleteIngress
- startCompositeEgress
- stopEgress
- addStreamDestination
- removeStreamDestination
- startTrackRecording
- getRoomHealth
- getParticipants
- removeParticipant

Las respuestas externas deben convertirse a DTOs internos.

## Modos de audiencia

### Interactive
WebRTC para hosts, speakers, backstage y participantes en stage.

### Broadcast
LL-HLS/HLS + CDN para audiencias grandes.

## Adaptive Audience

Diseñar transición futura:

HLS Viewer -> WebRTC -> Backstage -> Stage -> HLS Viewer.

## Ingest

- browser WebRTC
- RTMP/RTMPS
- WHIP
- SRT

## Egress

- MP4
- HLS
- RTMP/RTMPS
- SRT
- track recording

## Resiliencia

Diseñar:

- destination-level retries;
- health state;
- backup slate;
- failover strategy;
- incident timeline.
