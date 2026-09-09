import type { CapabilityKey, EventStatus, EventTypeKey } from '@escenia/types'

export const estadoEvento: Record<EventStatus, { label: string; clase: string }> = {
  draft: { label: 'Borrador', clase: '' },
  scheduled: { label: 'Programado', clase: 'chip--primary' },
  live: { label: 'En vivo', clase: 'chip--live' },
  ended: { label: 'Finalizado', clase: '' },
  archived: { label: 'Archivado', clase: '' },
  canceled: { label: 'Cancelado', clase: 'chip--danger' },
}

export const tipoEvento: Record<EventTypeKey, string> = {
  webinar: 'Webinar',
  live_studio: 'Estudio en vivo',
  evergreen: 'Evergreen',
  simulive: 'Simulive',
  training: 'Capacitación',
  course: 'Curso',
  town_hall: 'Town hall',
  product_launch: 'Lanzamiento',
  virtual_conference: 'Conferencia virtual',
  hybrid_event: 'Evento híbrido',
  podcast: 'Podcast',
  on_demand: 'Bajo demanda',
}

export const capacidad: Record<CapabilityKey, string> = {
  registration: 'Registro',
  payments: 'Pagos',
  chat: 'Chat',
  qa: 'Preguntas y respuestas',
  polls: 'Encuestas',
  tests: 'Evaluaciones',
  certificates: 'Certificados',
  networking: 'Networking',
  expo: 'Expo',
  sponsors: 'Patrocinadores',
  recording: 'Grabación',
  multistream: 'Multistream',
  automation: 'Automatización',
  ai: 'IA',
  commerce: 'Comercio',
  replay: 'Replay',
  translation: 'Traducción',
  captions: 'Subtítulos',
  white_label: 'Marca blanca',
  gamification: 'Gamificación',
  breakout_rooms: 'Salas simultáneas',
}

export const todasLasCapacidades = Object.keys(capacidad) as CapabilityKey[]

export function fecha(iso: string | null): string {
  if (iso === null) return '—'
  const d = new Date(iso)
  return d.toLocaleDateString('es', { day: '2-digit', month: 'short', year: 'numeric' })
}
