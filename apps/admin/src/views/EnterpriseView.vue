<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type {
  ApiKey,
  ApiKeyScope,
  AuditLogEntry,
  CustomDomain,
  DataRegion,
  DomainStatus,
  IssuedApiKey,
  SsoConnection,
  SsoProvider,
  TenantSettings,
} from '@escenia/types'

import { fecha } from '@/lib/eventLabels'
import { api } from '@/lib/api'
import TabList from '@/components/TabList.vue'

type Tab = 'dominios' | 'claves' | 'sso' | 'residencia' | 'auditoria'
const tab = ref<Tab>('dominios')
const tabs: [Tab, string][] = [
  ['dominios', 'Dominios'],
  ['claves', 'Claves API'],
  ['sso', 'SSO'],
  ['residencia', 'Residencia'],
  ['auditoria', 'Auditoría'],
]

const loading = ref(true)
const error = ref<string | null>(null)
const busy = ref(false)
const copiado = ref<string | null>(null)

// Dominios
const domains = ref<CustomDomain[]>([])
const nuevoDominio = ref('')
const estadoDominio: Record<DomainStatus, { label: string; clase: string }> = {
  pending: { label: 'Pendiente', clase: '' },
  active: { label: 'Activo', clase: 'chip--live' },
  failed: { label: 'Falló', clase: 'chip--danger' },
}

// Claves API
const keys = ref<ApiKey[]>([])
const tokenReciente = ref<IssuedApiKey | null>(null)
const nuevaClave = ref({ name: '', scopes: [] as ApiKeyScope[], expires_at: '' })
const scopesDisponibles: [ApiKeyScope, string][] = [
  ['events.read', 'Leer eventos'],
  ['events.write', 'Escribir eventos'],
  ['analytics.read', 'Leer analíticas'],
]

// SSO
const ssos = ref<SsoConnection[]>([])
const nuevoSso = ref({
  provider: 'oidc' as SsoProvider,
  display_name: '',
  domain: '',
  default_role: 'member' as 'admin' | 'member',
  config: '',
})
const proveedorLabel: Record<SsoProvider, string> = { oidc: 'OIDC', saml: 'SAML' }
const rolLabel: Record<'admin' | 'member', string> = { admin: 'Administrador', member: 'Miembro' }

// Residencia
const settings = ref<TenantSettings | null>(null)
const residencia = ref<{ data_region: DataRegion; is_dedicated: boolean }>({ data_region: 'us', is_dedicated: false })
const regiones: [DataRegion, string][] = [
  ['us', 'Estados Unidos (US)'],
  ['eu', 'Europa (EU)'],
  ['ap', 'Asia-Pacífico (AP)'],
]

// Auditoría
const logs = ref<AuditLogEntry[]>([])
const filtros = ref({ action: '', actor: '', from: '', to: '' })

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function run(fn: () => Promise<void>): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await fn()
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [d, k, s, cfg, al] = await Promise.all([
      api.customDomains(),
      api.apiKeys(),
      api.ssoConnections(),
      api.tenantSettings(),
      api.auditLogs({ per_page: 50 }),
    ])
    domains.value = d.data
    keys.value = k.data
    ssos.value = s.data
    settings.value = cfg.data
    residencia.value = { data_region: cfg.data.data_region, is_dedicated: cfg.data.is_dedicated }
    logs.value = al.data
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
  }
}

async function copiar(text: string, id: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(text)
    copiado.value = id
    setTimeout(() => {
      if (copiado.value === id) copiado.value = null
    }, 1600)
  } catch {
    error.value = 'No se pudo copiar al portapapeles.'
  }
}

// Dominios
function crearDominio(): void {
  if (nuevoDominio.value.trim() === '') return
  run(async () => {
    await api.createCustomDomain({ hostname: nuevoDominio.value.trim() })
    nuevoDominio.value = ''
    domains.value = (await api.customDomains()).data
  })
}
function verificarDominio(d: CustomDomain): void {
  run(async () => {
    const updated = (await api.verifyCustomDomain(d.id)).data
    const i = domains.value.findIndex((x) => x.id === d.id)
    if (i !== -1) domains.value[i] = updated
  })
}
function eliminarDominio(d: CustomDomain): void {
  run(async () => {
    await api.deleteCustomDomain(d.id)
    domains.value = domains.value.filter((x) => x.id !== d.id)
  })
}

// Claves API
function toggleScope(s: ApiKeyScope): void {
  const i = nuevaClave.value.scopes.indexOf(s)
  if (i === -1) nuevaClave.value.scopes.push(s)
  else nuevaClave.value.scopes.splice(i, 1)
}
function crearClave(): void {
  if (nuevaClave.value.name.trim() === '' || nuevaClave.value.scopes.length === 0) return
  run(async () => {
    const res = await api.createApiKey({
      name: nuevaClave.value.name,
      scopes: [...nuevaClave.value.scopes],
      expires_at: nuevaClave.value.expires_at || undefined,
    })
    tokenReciente.value = res.data
    nuevaClave.value = { name: '', scopes: [], expires_at: '' }
    keys.value = (await api.apiKeys()).data
  })
}
function revocarClave(k: ApiKey): void {
  run(async () => {
    const updated = (await api.revokeApiKey(k.id)).data
    const i = keys.value.findIndex((x) => x.id === k.id)
    if (i !== -1) keys.value[i] = updated
  })
}

// SSO
function crearSso(): void {
  if (nuevoSso.value.display_name.trim() === '') return
  let config: Record<string, unknown> = {}
  if (nuevoSso.value.config.trim() !== '') {
    try {
      config = JSON.parse(nuevoSso.value.config) as Record<string, unknown>
    } catch {
      error.value = 'La configuración del proveedor no es un JSON válido.'
      return
    }
  }
  run(async () => {
    await api.createSsoConnection({
      provider: nuevoSso.value.provider,
      display_name: nuevoSso.value.display_name,
      domain: nuevoSso.value.domain || undefined,
      default_role: nuevoSso.value.default_role,
      config,
    })
    nuevoSso.value = { provider: 'oidc', display_name: '', domain: '', default_role: 'member', config: '' }
    ssos.value = (await api.ssoConnections()).data
  })
}
function alternarSso(c: SsoConnection): void {
  run(async () => {
    const updated = (await api.updateSsoConnection(c.id, { is_active: !c.is_active })).data
    const i = ssos.value.findIndex((x) => x.id === c.id)
    if (i !== -1) ssos.value[i] = updated
  })
}
function eliminarSso(c: SsoConnection): void {
  run(async () => {
    await api.deleteSsoConnection(c.id)
    ssos.value = ssos.value.filter((x) => x.id !== c.id)
  })
}
function callbackUrl(c: SsoConnection): string {
  return `${window.location.origin}/api/v1/sso/${c.id}/callback`
}

// Residencia
function guardarResidencia(): void {
  run(async () => {
    settings.value = (await api.updateTenantSettings({
      data_region: residencia.value.data_region,
      is_dedicated: residencia.value.is_dedicated,
    })).data
  })
}

// Auditoría
function filtrar(): void {
  run(async () => {
    logs.value = (await api.auditLogs({
      action: filtros.value.action || undefined,
      actor: filtros.value.actor || undefined,
      from: filtros.value.from || undefined,
      to: filtros.value.to || undefined,
      per_page: 50,
    })).data
  })
}
function contextoCorto(ctx: Record<string, unknown> | null): string {
  if (ctx === null || Object.keys(ctx).length === 0) return '—'
  return JSON.stringify(ctx)
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <div class="page-head">
      <h1>Enterprise</h1>
      <p>Dominios propios, claves de API, inicio de sesión único, residencia de datos y auditoría.</p>
    </div>

    <TabList v-model="tab" :tabs="tabs" label="Secciones de enterprise" base="enterprise" />

    <p v-if="loading" class="muted">Cargando…</p>

    <template v-else>
      <!-- DOMINIOS -->
      <div v-if="tab === 'dominios'" class="stack">
        <div class="panel stack">
          <h2>Añadir dominio</h2>
          <div class="add">
            <input v-model="nuevoDominio" class="control grow" placeholder="eventos.tudominio.com" @keyup.enter="crearDominio" />
            <AppButton :disabled="busy || !nuevoDominio.trim()" @click="crearDominio">Añadir dominio</AppButton>
          </div>
        </div>

        <p v-if="domains.length === 0" class="panel empty">Aún no hay dominios personalizados.</p>
        <div v-for="d in domains" :key="d.id" class="panel stack">
          <div class="row-head">
            <div>
              <strong class="mono">{{ d.hostname }}</strong>
              <span class="chip" :class="estadoDominio[d.status].clase">{{ estadoDominio[d.status].label }}</span>
            </div>
            <div class="actions">
              <AppButton v-if="d.status !== 'active'" variant="ghost" :disabled="busy" @click="verificarDominio(d)">Verificar</AppButton>
              <AppButton variant="danger" :disabled="busy" @click="eliminarDominio(d)">Eliminar</AppButton>
            </div>
          </div>
          <div v-if="d.status !== 'active'" class="dns">
            <p class="muted small">Crea este registro DNS para verificar la propiedad del dominio:</p>
            <table class="admin-table">
              <thead><tr><th>Tipo</th><th>Nombre</th><th>Valor</th></tr></thead>
              <tbody>
                <tr>
                  <td class="mono">{{ d.dns_challenge.type }}</td>
                  <td class="mono">{{ d.dns_challenge.name }}</td>
                  <td class="mono valor">{{ d.dns_challenge.value }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="muted small">Verificado el {{ fecha(d.verified_at) }} · destino {{ d.target }}</p>
        </div>
      </div>

      <!-- CLAVES API -->
      <div v-else-if="tab === 'claves'" class="stack">
        <div v-if="tokenReciente" class="panel token-panel">
          <h2>Clave creada: {{ tokenReciente.key.name }}</h2>
          <p class="warn small">Copia la clave ahora. Por seguridad no se volverá a mostrar.</p>
          <div class="token-row">
            <code class="token">{{ tokenReciente.token }}</code>
            <AppButton @click="copiar(tokenReciente.token, 'token')">{{ copiado === 'token' ? 'Copiado' : 'Copiar' }}</AppButton>
          </div>
          <div class="actions"><AppButton variant="ghost" @click="tokenReciente = null">Entendido</AppButton></div>
        </div>

        <div class="panel stack">
          <h2>Nueva clave API</h2>
          <div class="fila">
            <label class="field grow"><span>Nombre</span><input v-model="nuevaClave.name" class="control" placeholder="Integración CRM" /></label>
            <label class="field"><span>Expira (opcional)</span><input v-model="nuevaClave.expires_at" type="datetime-local" class="control" /></label>
          </div>
          <div class="scopes">
            <label v-for="[s, label] in scopesDisponibles" :key="s" class="check">
              <input type="checkbox" :checked="nuevaClave.scopes.includes(s)" @change="toggleScope(s)" />
              <span>{{ label }} <code class="mini-code">{{ s }}</code></span>
            </label>
          </div>
          <div class="actions">
            <AppButton :disabled="busy || !nuevaClave.name.trim() || nuevaClave.scopes.length === 0" @click="crearClave">Crear clave</AppButton>
          </div>
        </div>

        <p v-if="keys.length === 0" class="panel empty">Aún no hay claves API.</p>
        <div v-else class="panel">
          <div class="table-wrap">
            <table class="admin-table">
              <thead><tr><th>Nombre</th><th>Prefijo</th><th>Permisos</th><th>Último uso</th><th>Estado</th><th></th></tr></thead>
              <tbody>
                <tr v-for="k in keys" :key="k.id">
                  <td><strong>{{ k.name }}</strong></td>
                  <td class="mono">{{ k.prefix }}…</td>
                  <td><span v-for="s in k.scopes" :key="s" class="chip mini-chip">{{ s }}</span></td>
                  <td class="muted">{{ k.last_used_at ? fecha(k.last_used_at) : 'Nunca' }}</td>
                  <td>
                    <span v-if="k.revoked_at" class="chip chip--danger">Revocada</span>
                    <span v-else class="chip chip--live">Activa</span>
                  </td>
                  <td>
                    <AppButton v-if="!k.revoked_at" variant="danger" :disabled="busy" @click="revocarClave(k)">Revocar</AppButton>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- SSO -->
      <div v-else-if="tab === 'sso'" class="stack">
        <div class="panel stack">
          <h2>Nueva conexión SSO</h2>
          <div class="fila">
            <label class="field">
              <span>Proveedor</span>
              <select v-model="nuevoSso.provider" class="control">
                <option value="oidc">OIDC</option>
                <option value="saml">SAML</option>
              </select>
            </label>
            <label class="field grow"><span>Nombre visible</span><input v-model="nuevoSso.display_name" class="control" placeholder="Acme SSO" /></label>
            <label class="field"><span>Dominio (opcional)</span><input v-model="nuevoSso.domain" class="control" placeholder="acme.com" /></label>
            <label class="field">
              <span>Rol por defecto</span>
              <select v-model="nuevoSso.default_role" class="control">
                <option value="member">Miembro</option>
                <option value="admin">Administrador</option>
              </select>
            </label>
          </div>
          <label class="field">
            <span>Configuración del proveedor (JSON, opcional)</span>
            <textarea v-model="nuevoSso.config" class="control mono" rows="3" placeholder='{ "issuer": "https://…", "client_id": "…", "client_secret": "…" }'></textarea>
          </label>
          <p class="muted small">El propietario nunca se delega a un IdP externo: SSO solo puede asignar Miembro o Administrador.</p>
          <div class="actions">
            <AppButton :disabled="busy || !nuevoSso.display_name.trim()" @click="crearSso">Crear conexión</AppButton>
          </div>
        </div>

        <p v-if="ssos.length === 0" class="panel empty">Aún no hay conexiones SSO.</p>
        <div v-for="c in ssos" :key="c.id" class="panel stack">
          <div class="row-head">
            <div>
              <strong>{{ c.display_name }}</strong>
              <span class="chip chip--primary">{{ proveedorLabel[c.provider] }}</span>
              <span class="chip" :class="c.is_active ? 'chip--live' : ''">{{ c.is_active ? 'Activa' : 'Pausada' }}</span>
            </div>
            <div class="actions">
              <AppButton variant="ghost" :disabled="busy" @click="alternarSso(c)">{{ c.is_active ? 'Pausar' : 'Activar' }}</AppButton>
              <AppButton variant="danger" :disabled="busy" @click="eliminarSso(c)">Eliminar</AppButton>
            </div>
          </div>
          <p class="muted small">
            Rol por defecto: {{ rolLabel[c.default_role] }}<template v-if="c.domain"> · Dominio: {{ c.domain }}</template>
          </p>
          <label class="field">
            <span>URL de callback (configúrala en tu IdP)</span>
            <div class="webhook">
              <input :value="callbackUrl(c)" readonly class="control mono" />
              <AppButton variant="ghost" @click="copiar(callbackUrl(c), c.id)">{{ copiado === c.id ? 'Copiado' : 'Copiar' }}</AppButton>
            </div>
          </label>
        </div>
      </div>

      <!-- RESIDENCIA -->
      <div v-else-if="tab === 'residencia'" class="panel stack">
        <h2>Residencia de datos</h2>
        <p class="muted">Define dónde residen los datos de <strong>{{ settings?.name }}</strong> y si usa infraestructura dedicada.</p>
        <label class="field">
          <span>Región de datos</span>
          <select v-model="residencia.data_region" class="control">
            <option v-for="[r, label] in regiones" :key="r" :value="r">{{ label }}</option>
          </select>
        </label>
        <label class="check">
          <input v-model="residencia.is_dedicated" type="checkbox" />
          <span>Infraestructura dedicada</span>
        </label>
        <div class="actions">
          <AppButton :disabled="busy" @click="guardarResidencia">Guardar residencia</AppButton>
        </div>
      </div>

      <!-- AUDITORÍA -->
      <div v-else class="panel stack">
        <h2>Registro de auditoría</h2>
        <div class="fila">
          <label class="field grow"><span>Acción</span><input v-model="filtros.action" class="control" placeholder="commerce.payment_account.saved" /></label>
          <label class="field grow"><span>Actor</span><input v-model="filtros.actor" class="control" placeholder="correo o nombre" /></label>
          <label class="field"><span>Desde</span><input v-model="filtros.from" type="date" class="control" /></label>
          <label class="field"><span>Hasta</span><input v-model="filtros.to" type="date" class="control" /></label>
          <AppButton :disabled="busy" @click="filtrar">Filtrar</AppButton>
        </div>

        <p v-if="logs.length === 0" class="empty">Sin registros para estos filtros.</p>
        <div v-else class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>Fecha</th><th>Acción</th><th>Actor</th><th>Recurso</th><th>IP</th><th>Contexto</th></tr></thead>
            <tbody>
              <tr v-for="l in logs" :key="l.id">
                <td class="muted nowrap">{{ fecha(l.created_at) }}</td>
                <td class="mono">{{ l.action }}</td>
                <td>{{ l.actor_label ?? '—' }}</td>
                <td class="muted">{{ l.auditable_type ?? '—' }}</td>
                <td class="muted mono">{{ l.ip_address ?? '—' }}</td>
                <td class="muted small ctx" :title="contextoCorto(l.context)">{{ contextoCorto(l.context) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <p v-if="error" role="alert" class="error-text">{{ error }}</p>
    </template>
  </section>
</template>

<style scoped>
.small { font-size: 0.8rem; margin: 4px 0 0; }
.grow { flex: 1; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.add { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; }
.fila { display: flex; gap: var(--escenia-space-3); flex-wrap: wrap; align-items: end; }

.tabs { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; }
.tab {
  padding: 9px 16px; font: inherit; font-weight: 600; font-size: 0.85rem;
  color: var(--escenia-color-text-muted); background: transparent;
  border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm);
  cursor: pointer; transition: all 0.14s ease;
}
.tab:hover { color: var(--escenia-color-text); }
.tab.is-active { color: #fff; background: var(--escenia-color-primary); border-color: transparent; }

.row-head { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--escenia-space-4); flex-wrap: wrap; }
.row-head > div:first-child { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.dns .valor { word-break: break-all; }
.table-wrap { overflow-x: auto; }
.nowrap { white-space: nowrap; }
.ctx { max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.scopes { display: flex; flex-direction: column; gap: var(--escenia-space-2); }
.check { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; }
.mini-code { font-family: ui-monospace, monospace; font-size: 0.78rem; color: var(--escenia-color-text-muted); }
.mini-chip { font-size: 0.72rem; margin-right: 4px; }

.token-panel { border-color: color-mix(in srgb, var(--escenia-color-primary) 45%, transparent); }
.token-row { display: flex; gap: var(--escenia-space-2); align-items: center; }
.token { flex: 1; font-family: ui-monospace, monospace; font-size: 0.85rem; padding: 10px 12px; background: rgba(4, 16, 29, 0.6); border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); word-break: break-all; }
.warn { color: var(--escenia-color-text); }

.webhook { display: flex; gap: var(--escenia-space-2); }
.webhook .control { flex: 1; font-size: 0.82rem; }
textarea.control { resize: vertical; }
</style>
