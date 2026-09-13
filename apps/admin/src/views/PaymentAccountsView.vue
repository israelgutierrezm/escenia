<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { PaymentAccount, PaymentGatewayName } from '@escenia/types'

import { api } from '@/lib/api'

const accounts = ref<PaymentAccount[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const busy = ref(false)
const copiado = ref<string | null>(null)

const form = ref({
  gateway: 'fake' as PaymentGatewayName,
  display_name: '',
  currency: 'MXN',
  is_active: true,
  secret_key: '',
  access_token: '',
  webhook_secret: '',
})

const gateways: [PaymentGatewayName, string][] = [
  ['fake', 'Pruebas (sandbox)'],
  ['stripe', 'Stripe'],
  ['mercadopago', 'Mercado Pago'],
]
const gatewayLabel: Record<PaymentGatewayName, string> = {
  fake: 'Pruebas',
  stripe: 'Stripe',
  mercadopago: 'Mercado Pago',
}
const monedas = ['MXN', 'USD', 'EUR', 'COP', 'ARS', 'BRL', 'CLP', 'PEN']

const yaConectada = computed(() => accounts.value.some((a) => a.gateway === form.value.gateway))
const requiereCredencial = computed(() => form.value.gateway === 'stripe' || form.value.gateway === 'mercadopago')
const credencialActual = computed(() =>
  form.value.gateway === 'stripe' ? form.value.secret_key : form.value.gateway === 'mercadopago' ? form.value.access_token : '',
)
const puedeGuardar = computed(
  () => form.value.display_name.trim() !== '' && (!requiereCredencial.value || credencialActual.value.trim() !== ''),
)

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    accounts.value = (await api.paymentAccounts()).data
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
  }
}

function prefill(gateway: PaymentGatewayName): void {
  const existing = accounts.value.find((a) => a.gateway === gateway)
  form.value.secret_key = ''
  form.value.access_token = ''
  form.value.webhook_secret = ''
  if (existing) {
    form.value.display_name = existing.display_name
    form.value.currency = existing.currency
    form.value.is_active = existing.is_active
  } else {
    form.value.display_name = ''
    form.value.currency = 'MXN'
    form.value.is_active = true
  }
}

function guardar(): void {
  if (!puedeGuardar.value) return
  const f = form.value
  const credentials: Record<string, string> = {}
  if (f.gateway === 'stripe' && f.secret_key.trim()) credentials.secret_key = f.secret_key.trim()
  if (f.gateway === 'mercadopago' && f.access_token.trim()) credentials.access_token = f.access_token.trim()

  busy.value = true
  error.value = null
  api
    .savePaymentAccount({
      gateway: f.gateway,
      display_name: f.display_name,
      currency: f.currency,
      is_active: f.is_active,
      credentials,
      webhook_secret: f.webhook_secret.trim() || undefined,
    })
    .then(async () => {
      f.secret_key = ''
      f.access_token = ''
      f.webhook_secret = ''
      accounts.value = (await api.paymentAccounts()).data
    })
    .catch((e: unknown) => {
      error.value = message(e)
    })
    .finally(() => {
      busy.value = false
    })
}

async function copiar(url: string, id: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(url)
    copiado.value = id
    setTimeout(() => {
      if (copiado.value === id) copiado.value = null
    }, 1600)
  } catch {
    error.value = 'No se pudo copiar al portapapeles.'
  }
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <div class="page-head">
      <h1>Cuentas de pago</h1>
      <p>Conecta las pasarelas con las que cobrarás entradas y productos. Las credenciales se cifran y nunca se muestran.</p>
    </div>

    <p v-if="loading" class="muted">Cargando…</p>

    <template v-else>
      <!-- Conectadas -->
      <div v-if="accounts.length" class="grid-cards">
        <div v-for="a in accounts" :key="a.id" class="panel stack acc">
          <div class="acc__head">
            <div>
              <strong>{{ a.display_name }}</strong>
              <p class="muted small">{{ gatewayLabel[a.gateway] }} · {{ a.currency }}</p>
            </div>
            <span class="chip" :class="a.is_active ? 'chip--live' : ''">{{ a.is_active ? 'Activa' : 'Pausada' }}</span>
          </div>
          <label class="field">
            <span>URL de webhook</span>
            <div class="webhook">
              <input :value="a.webhook_url" readonly class="control" />
              <AppButton variant="ghost" @click="copiar(a.webhook_url, a.id)">{{ copiado === a.id ? 'Copiado' : 'Copiar' }}</AppButton>
            </div>
          </label>
        </div>
      </div>
      <p v-else class="panel empty">Aún no hay pasarelas conectadas.</p>

      <!-- Conectar / actualizar -->
      <div class="panel stack">
        <h2>Conectar o actualizar pasarela</h2>

        <div class="fila">
          <label class="field">
            <span>Pasarela</span>
            <select v-model="form.gateway" class="control" @change="prefill(form.gateway)">
              <option v-for="[g, label] in gateways" :key="g" :value="g">{{ label }}</option>
            </select>
          </label>
          <label class="field grow"><span>Nombre visible</span><input v-model="form.display_name" class="control" placeholder="Cobros México" /></label>
          <label class="field">
            <span>Moneda</span>
            <select v-model="form.currency" class="control">
              <option v-for="m in monedas" :key="m" :value="m">{{ m }}</option>
            </select>
          </label>
        </div>

        <p v-if="form.gateway === 'fake'" class="muted small">
          La pasarela de pruebas simula pagos sin credenciales. Úsala para validar el flujo de compra.
        </p>

        <div v-else class="fila">
          <label v-if="form.gateway === 'stripe'" class="field grow">
            <span>Clave secreta (secret_key)</span>
            <input v-model="form.secret_key" type="password" class="control" placeholder="sk_live_…" autocomplete="off" />
          </label>
          <label v-else-if="form.gateway === 'mercadopago'" class="field grow">
            <span>Access token</span>
            <input v-model="form.access_token" type="password" class="control" placeholder="APP_USR-…" autocomplete="off" />
          </label>
          <label class="field grow">
            <span>Secreto de webhook (opcional)</span>
            <input v-model="form.webhook_secret" type="password" class="control" placeholder="whsec_…" autocomplete="off" />
          </label>
        </div>

        <label class="check">
          <input v-model="form.is_active" type="checkbox" />
          <span>Activa (disponible para cobrar)</span>
        </label>

        <p v-if="yaConectada" class="warn small">
          Esta pasarela ya está conectada. Al guardar se reemplaza toda su configuración, incluidas las credenciales:
          vuelve a introducirlas.
        </p>

        <div class="actions">
          <AppButton :disabled="busy || !puedeGuardar" @click="guardar">
            {{ yaConectada ? 'Actualizar pasarela' : 'Conectar pasarela' }}
          </AppButton>
        </div>
      </div>

      <p v-if="error" role="alert" class="error-text">{{ error }}</p>
    </template>
  </section>
</template>

<style scoped>
.small { font-size: 0.8rem; margin: 4px 0 0; }
.grow { flex: 1; }
.fila { display: flex; gap: var(--escenia-space-3); flex-wrap: wrap; align-items: end; }

.acc__head { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--escenia-space-3); }
.webhook { display: flex; gap: var(--escenia-space-2); }
.webhook .control { flex: 1; font-size: 0.82rem; }

.check { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; }
.warn {
  padding: 10px 12px;
  border-radius: var(--escenia-radius-sm);
  border: 1px solid color-mix(in srgb, var(--escenia-color-danger, #ff8f6b) 40%, transparent);
  background: color-mix(in srgb, var(--escenia-color-danger, #ff8f6b) 8%, transparent);
  color: var(--escenia-color-text);
}
</style>
