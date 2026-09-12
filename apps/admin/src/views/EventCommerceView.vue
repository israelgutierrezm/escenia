<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { Cta, Money, Order, OrderStatus, RevenueReport, Ticket } from '@escenia/types'

import { fecha } from '@/lib/eventLabels'
import { api } from '@/lib/api'

const route = useRoute()
const id = route.params.id as string

const tickets = ref<Ticket[]>([])
const orders = ref<Order[]>([])
const revenue = ref<RevenueReport | null>(null)
const ctas = ref<Cta[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const creando = ref(false)
const guardando = ref(false)
const form = ref({ name: '', amount: 0, currency: 'MXN', capacity: '' })

const creandoCta = ref(false)
const guardandoCta = ref(false)
const ctaForm = ref({ title: '', body: '', url: '', ticket: '', starts_at: '', ends_at: '' })

const estadoOrden: Record<OrderStatus, { label: string; clase: string }> = {
  pending: { label: 'Pendiente', clase: '' },
  paid: { label: 'Pagada', clase: 'chip--live' },
  canceled: { label: 'Cancelada', clase: '' },
  refunded: { label: 'Reembolsada', clase: 'chip--danger' },
}

function dinero(m: Money | null): string {
  if (m === null) return '—'
  return new Intl.NumberFormat('es', { style: 'currency', currency: m.currency }).format(m.minor_units / 100)
}

function dineroMinor(minor: number, currency: string): string {
  return new Intl.NumberFormat('es', { style: 'currency', currency }).format(minor / 100)
}

const netoActual = computed(() => (revenue.value ? dineroMinor(revenue.value.net_minor, revenue.value.currency) : '—'))

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [t, o, r, c] = await Promise.all([api.tickets(id), api.orders(id), api.revenue(id), api.ctas(id)])
    tickets.value = t.data
    orders.value = o.data
    revenue.value = r.data
    ctas.value = c.data
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
  }
}

async function crearTicket(): Promise<void> {
  if (form.value.name.trim() === '') return
  guardando.value = true
  error.value = null
  try {
    await api.createTicket(id, {
      name: form.value.name,
      amount_minor: Math.round(form.value.amount * 100),
      currency: form.value.currency,
      capacity: form.value.capacity ? Number(form.value.capacity) : undefined,
    })
    form.value = { name: '', amount: 0, currency: form.value.currency, capacity: '' }
    creando.value = false
    await load()
  } catch (e) {
    error.value = message(e)
  } finally {
    guardando.value = false
  }
}

async function crearCta(): Promise<void> {
  if (ctaForm.value.title.trim() === '') return
  guardandoCta.value = true
  error.value = null
  try {
    await api.createCta(id, {
      title: ctaForm.value.title,
      body: ctaForm.value.body.trim() || undefined,
      url: ctaForm.value.url.trim() || undefined,
      ticket: ctaForm.value.ticket || undefined,
      starts_at: ctaForm.value.starts_at || undefined,
      ends_at: ctaForm.value.ends_at || undefined,
    })
    ctaForm.value = { title: '', body: '', url: '', ticket: '', starts_at: '', ends_at: '' }
    creandoCta.value = false
    ctas.value = (await api.ctas(id)).data
  } catch (e) {
    error.value = message(e)
  } finally {
    guardandoCta.value = false
  }
}

function nombreTicket(ulid: string | undefined): string {
  if (ulid === undefined) return ''
  return tickets.value.find((t) => t.id === ulid)?.name ?? ''
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <RouterLink :to="{ name: 'event-detail', params: { id } }" class="back muted">‹ Volver al evento</RouterLink>

    <div class="page-head">
      <h1>Comercio</h1>
      <p>Entradas, ventas e ingresos del evento.</p>
    </div>

    <p v-if="loading" class="muted">Cargando…</p>
    <p v-else-if="error" class="error-text">{{ error }}</p>

    <template v-else>
      <div v-if="revenue" class="stat-grid">
        <div class="stat">
          <div class="stat__value">{{ netoActual }}</div>
          <div class="stat__label">Ingresos netos</div>
        </div>
        <div class="stat">
          <div class="stat__value">{{ dineroMinor(revenue.gross_minor, revenue.currency) }}</div>
          <div class="stat__label">Bruto</div>
        </div>
        <div class="stat">
          <div class="stat__value">{{ revenue.orders_paid }}</div>
          <div class="stat__label">Órdenes pagadas</div>
        </div>
        <div class="stat">
          <div class="stat__value">{{ dineroMinor(revenue.avg_order_minor, revenue.currency) }}</div>
          <div class="stat__label">Ticket medio</div>
        </div>
      </div>

      <!-- Tickets -->
      <div class="panel stack">
        <div class="actions" style="justify-content: space-between">
          <h2 style="margin: 0">Entradas</h2>
          <AppButton @click="creando = !creando">{{ creando ? 'Cerrar' : '+ Nueva entrada' }}</AppButton>
        </div>

        <div v-if="creando" class="crear">
          <label class="field">
            <span>Nombre</span>
            <input v-model="form.name" type="text" placeholder="p. ej. General" />
          </label>
          <label class="field">
            <span>Precio</span>
            <input v-model.number="form.amount" type="number" min="0" step="0.01" />
          </label>
          <label class="field">
            <span>Moneda</span>
            <select v-model="form.currency">
              <option value="MXN">MXN</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
            </select>
          </label>
          <label class="field">
            <span>Cupo (opcional)</span>
            <input v-model="form.capacity" type="number" min="0" placeholder="Ilimitado" />
          </label>
          <div class="actions">
            <AppButton :disabled="guardando || !form.name.trim()" @click="crearTicket">Crear entrada</AppButton>
          </div>
        </div>

        <p v-if="tickets.length === 0" class="empty">Aún no hay entradas.</p>
        <div v-else class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>Entrada</th><th>Precio</th><th>Disponibles</th><th>Estado</th></tr>
            </thead>
            <tbody>
              <tr v-for="t in tickets" :key="t.id">
                <td><strong>{{ t.name }}</strong></td>
                <td>{{ dinero(t.price) }}</td>
                <td class="muted">{{ t.remaining ?? 'Ilimitado' }}</td>
                <td>
                  <span class="chip" :class="t.on_sale ? 'chip--live' : ''">{{ t.on_sale ? 'A la venta' : 'Pausada' }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Órdenes -->
      <div class="panel">
        <h2>Órdenes <span class="muted">({{ orders.length }})</span></h2>
        <p v-if="orders.length === 0" class="empty">Aún no hay órdenes.</p>
        <div v-else class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>Comprador</th><th>Total</th><th>Estado</th><th>Fecha</th></tr>
            </thead>
            <tbody>
              <tr v-for="o in orders" :key="o.id">
                <td>
                  <strong>{{ o.buyer_name }}</strong>
                  <div class="muted email">{{ o.buyer_email }}</div>
                </td>
                <td>{{ dinero(o.total) }}</td>
                <td><span class="chip" :class="estadoOrden[o.status].clase">{{ estadoOrden[o.status].label }}</span></td>
                <td class="muted">{{ fecha(o.paid_at ?? o.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Llamadas a la acción (CTAs) -->
      <div class="panel stack">
        <div class="actions" style="justify-content: space-between">
          <h2 style="margin: 0">Llamadas a la acción <span class="muted">({{ ctas.length }})</span></h2>
          <AppButton @click="creandoCta = !creandoCta">{{ creandoCta ? 'Cerrar' : '+ Nueva CTA' }}</AppButton>
        </div>
        <p class="muted small">Botones y ofertas que aparecen a los asistentes durante el evento.</p>

        <div v-if="creandoCta" class="crear">
          <label class="field"><span>Título</span><input v-model="ctaForm.title" type="text" placeholder="Compra con descuento" /></label>
          <label class="field"><span>Texto (opcional)</span><input v-model="ctaForm.body" type="text" placeholder="Solo durante el evento" /></label>
          <label class="field"><span>URL (opcional)</span><input v-model="ctaForm.url" type="text" placeholder="https://…" /></label>
          <label class="field">
            <span>Entrada (opcional)</span>
            <select v-model="ctaForm.ticket">
              <option value="">Ninguna</option>
              <option v-for="t in tickets" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </label>
          <label class="field"><span>Desde (opcional)</span><input v-model="ctaForm.starts_at" type="datetime-local" /></label>
          <label class="field"><span>Hasta (opcional)</span><input v-model="ctaForm.ends_at" type="datetime-local" /></label>
          <div class="actions">
            <AppButton :disabled="guardandoCta || !ctaForm.title.trim()" @click="crearCta">Crear CTA</AppButton>
          </div>
        </div>

        <p v-if="ctas.length === 0" class="empty">Aún no hay llamadas a la acción.</p>
        <ul v-else class="cta-list">
          <li v-for="c in ctas" :key="c.id">
            <div class="cta-main">
              <div class="cta-title">
                <strong>{{ c.title }}</strong>
                <span class="chip" :class="c.live ? 'chip--live' : ''">{{ c.live ? 'En vivo' : (c.is_active ? 'Programada' : 'Inactiva') }}</span>
              </div>
              <p v-if="c.body" class="muted small">{{ c.body }}</p>
              <p class="muted small">
                <template v-if="nombreTicket(c.ticket)">Entrada: {{ nombreTicket(c.ticket) }} · </template>
                <a v-if="c.url" :href="c.url" target="_blank" rel="noopener" class="muted">{{ c.url }}</a>
              </p>
            </div>
            <span class="chip">{{ c.clicks_count }} clics</span>
          </li>
        </ul>
      </div>
    </template>
  </section>
</template>

<style scoped>
.back {
  text-decoration: none;
  font-size: 0.85rem;
}

.back:hover {
  color: var(--escenia-color-text);
}

.crear {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: var(--escenia-space-3);
  align-items: end;
  border-top: 1px solid var(--escenia-color-border);
  padding-top: var(--escenia-space-4);
}

.email {
  font-size: 0.78rem;
}

.small {
  font-size: 0.8rem;
  margin: 2px 0 0;
}

.cta-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
}

.cta-list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-3);
  padding: 12px;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.35);
}

.cta-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.cta-list a {
  text-decoration: none;
}
</style>
