<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { CheckoutResult, Money, Ticket } from '@escenia/types'

import { api } from '@/lib/attendeeApi'
import { useAttendeeStore } from '@/stores/attendee'

const route = useRoute()
const router = useRouter()
const store = useAttendeeStore()
const eventId = route.params.eventId as string

const tickets = ref<Ticket[]>([])
const cantidades = ref<Record<string, number>>({})
const nombre = ref('')
const correo = ref('')
const cupon = ref('')
const cargando = ref(true)
const enviando = ref(false)
const error = ref<string | null>(null)
const resultado = ref<CheckoutResult | null>(null)

const moneda = computed(() => tickets.value[0]?.price.currency ?? 'USD')

function dinero(m: Money): string {
  return new Intl.NumberFormat('es', { style: 'currency', currency: m.currency }).format(m.minor_units / 100)
}
function dineroMinor(minor: number): string {
  return new Intl.NumberFormat('es', { style: 'currency', currency: moneda.value }).format(minor / 100)
}

const items = computed(() =>
  tickets.value
    .map((t) => ({ ticket: t.id, quantity: cantidades.value[t.id] ?? 0 }))
    .filter((i) => i.quantity > 0),
)
const subtotal = computed(() =>
  items.value.reduce((sum, i) => {
    const t = tickets.value.find((x) => x.id === i.ticket)
    return sum + (t !== undefined ? t.price.minor_units * i.quantity : 0)
  }, 0),
)
const puedeComprar = computed(
  () => items.value.length > 0 && nombre.value.trim() !== '' && correo.value.trim() !== '',
)

function cambiar(t: Ticket, delta: number): void {
  const max = t.remaining ?? 99
  const actual = cantidades.value[t.id] ?? 0
  cantidades.value[t.id] = Math.max(0, Math.min(max, actual + delta))
}

async function cargar(): Promise<void> {
  cargando.value = true
  try {
    tickets.value = (await api.tickets(eventId)).data.filter((t) => t.on_sale)
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar las entradas.'
  } finally {
    cargando.value = false
  }
}

async function comprar(): Promise<void> {
  if (!puedeComprar.value) return
  enviando.value = true
  error.value = null
  try {
    resultado.value = (
      await api.checkout(eventId, {
        buyer_name: nombre.value.trim(),
        buyer_email: correo.value.trim(),
        items: items.value,
        coupon_code: cupon.value.trim() || undefined,
      })
    ).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo completar la compra.'
  } finally {
    enviando.value = false
  }
}

function acceder(): void {
  if (resultado.value === null) return
  store.setSession(eventId, { token: resultado.value.token, attendee: resultado.value.attendee })
  void router.push({ name: 'live', params: { eventId } })
}

onMounted(cargar)
</script>

<template>
  <main class="wrap">
    <header class="cab">
      <div class="marca"><span class="marca__punto"></span><strong>escenia</strong></div>
      <h1>Comprar entradas</h1>
    </header>

    <p v-if="error" class="error-text" role="alert">{{ error }}</p>

    <!-- Confirmación -->
    <section v-if="resultado" class="panel resumen">
      <p class="ok">✓ Pedido creado</p>
      <dl>
        <div v-if="resultado.order.subtotal"><dt>Subtotal</dt><dd>{{ dinero(resultado.order.subtotal) }}</dd></div>
        <div v-if="resultado.order.discount && resultado.order.discount.minor_units > 0" class="desc">
          <dt>Descuento<template v-if="resultado.order.coupon_code"> · {{ resultado.order.coupon_code }}</template></dt>
          <dd>−{{ dinero(resultado.order.discount) }}</dd>
        </div>
        <div class="total"><dt>Total</dt><dd>{{ dinero(resultado.order.total) }}</dd></div>
      </dl>

      <template v-if="resultado.payment.redirect_url">
        <p class="muted small">Continúa al proveedor de pago para completar tu compra.</p>
        <a class="pagar" :href="resultado.payment.redirect_url" target="_blank" rel="noopener">Continuar al pago →</a>
      </template>
      <p v-else class="muted small">Tu pedido quedó <strong>pendiente de pago</strong>; recibirás la confirmación por correo.</p>

      <AppButton variant="ghost" @click="acceder">Acceder al evento en vivo</AppButton>
    </section>

    <!-- Formulario -->
    <template v-else>
      <p v-if="cargando" class="muted">Cargando entradas…</p>
      <p v-else-if="tickets.length === 0" class="panel empty">No hay entradas a la venta para este evento.</p>

      <template v-else>
        <ul class="entradas">
          <li v-for="t in tickets" :key="t.id" class="panel entrada">
            <div class="entrada__info">
              <strong>{{ t.name }}</strong>
              <p v-if="t.description" class="muted small">{{ t.description }}</p>
              <span class="precio">{{ dinero(t.price) }}</span>
              <span v-if="t.remaining !== null" class="muted xsmall">{{ t.remaining }} disponibles</span>
            </div>
            <div class="stepper">
              <button type="button" :disabled="(cantidades[t.id] ?? 0) === 0" aria-label="Quitar" @click="cambiar(t, -1)">−</button>
              <span class="qty">{{ cantidades[t.id] ?? 0 }}</span>
              <button type="button" :disabled="t.remaining !== null && (cantidades[t.id] ?? 0) >= t.remaining" aria-label="Añadir" @click="cambiar(t, 1)">+</button>
            </div>
          </li>
        </ul>

        <form class="panel datos" @submit.prevent="comprar">
          <label class="field"><span>Nombre</span><input v-model="nombre" class="control" autocomplete="name" required /></label>
          <label class="field"><span>Correo electrónico</span><input v-model="correo" type="email" class="control" autocomplete="email" required /></label>
          <label class="field"><span>Cupón (opcional)</span><input v-model="cupon" class="control" placeholder="Código de descuento" /></label>

          <div class="subtotal">
            <span>Subtotal ({{ items.reduce((s, i) => s + i.quantity, 0) }})</span>
            <strong>{{ dineroMinor(subtotal) }}</strong>
          </div>
          <p class="muted xsmall">Los descuentos por cupón se aplican al confirmar.</p>

          <AppButton type="submit" :disabled="enviando || !puedeComprar">
            {{ enviando ? 'Procesando…' : 'Comprar' }}
          </AppButton>
        </form>
      </template>
    </template>
  </main>
</template>

<style scoped>
.wrap {
  width: 100%;
  max-width: 640px;
  margin: 0 auto;
  padding: var(--escenia-space-6) var(--escenia-space-4) var(--escenia-space-8);
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-4);
}

.cab {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
}

.marca {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.marca__punto {
  width: 11px;
  height: 11px;
  border-radius: 50%;
  background: var(--escenia-gradient-brand);
}

h1 {
  margin: 0;
  font-size: 1.6rem;
}

.entradas {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
}

.entrada {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-4);
}

.entrada__info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}

.precio {
  font-weight: 600;
  color: var(--escenia-color-accent);
}

.small {
  font-size: 0.82rem;
}

.xsmall {
  font-size: 0.75rem;
}

.stepper {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.stepper button {
  width: 34px;
  height: 34px;
  font-size: 1.2rem;
  line-height: 1;
  color: var(--escenia-color-text);
  background: transparent;
  border: 1px solid var(--escenia-color-border-strong);
  border-radius: var(--escenia-radius-sm);
  cursor: pointer;
}

.stepper button:disabled {
  opacity: 0.4;
  cursor: default;
}

.qty {
  min-width: 20px;
  text-align: center;
  font-weight: 600;
}

.datos {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 0.85rem;
}

.subtotal {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: var(--escenia-space-2);
  border-top: 1px solid var(--escenia-color-border);
  font-size: 1.05rem;
}

.resumen {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
}

.ok {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--escenia-color-accent);
}

.resumen dl {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.resumen dl > div {
  display: flex;
  justify-content: space-between;
}

.resumen dt {
  color: var(--escenia-color-text-muted);
}

.resumen .desc dd {
  color: var(--escenia-color-accent);
}

.resumen .total {
  padding-top: 8px;
  border-top: 1px solid var(--escenia-color-border);
  font-size: 1.1rem;
  font-weight: 700;
}

.pagar {
  display: inline-block;
  padding: 11px 18px;
  font-weight: 600;
  text-decoration: none;
  text-align: center;
  color: #04101d;
  background: var(--escenia-color-primary);
  border-radius: var(--escenia-radius-sm);
}
</style>
