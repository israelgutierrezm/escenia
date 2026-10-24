# Automation Engine

## Objetivo

Crear workflows visuales versionados.

## Entidades conceptuales

- Workflow
- WorkflowVersion
- Node
- Edge
- Trigger
- Condition
- Action
- Execution
- ExecutionStep

## Triggers

- registered
- attended
- did_not_attend
- joined
- left
- poll_answered
- question_asked
- cta_clicked
- resource_downloaded
- purchase
- recording_watched
- certificate_earned

## Actions

- email
- SMS
- WhatsApp
- webhook
- CRM update
- tag
- segment
- sales task
- coupon
- certificate
- notification

## Reglas técnicas

- versionado;
- idempotencia;
- retries;
- execution history;
- dead-letter/error state;
- no ejecutar steps críticos dos veces.

## Webhooks salientes (ADR-037, ADR-038)

- **Destino**: solo `https`, y siempre a través del guard de egress de
  plataforma (`OutboundUrlGuard`): el host debe resolver solo a IPs públicas,
  la conexión se fija a la IP validada y no se siguen redirecciones. Se
  comprueba al guardar el paso y otra vez en cada envío; un destino bloqueado
  no se llama.
- **Credenciales**: la URL y el secreto son credenciales (muchos receptores
  autentican solo por la URL): se guardan cifrados en
  `automation_steps.credentials` y la API solo muestra el origen y el estado de
  la firma.
- **Firma (Standard Webhooks)**: cada envío lleva `webhook-id`
  (`msg_{run}_{posición}`, igual en todos los reintentos), `webhook-timestamp`
  (segundos Unix del intento) y `webhook-signature` = `v1,` + base64 de
  HMAC-SHA256 de `id.timestamp.cuerpo` con los bytes decodificados del secreto
  `whsec_…`. Durante una rotación van dos firmas separadas por espacio. El
  cuerpo es `{"trigger", "run", "context"}` y nunca incluye las claves internas
  del contexto (prefijo `_`).
- **Secretos**: los genera Escenia (o se trae uno propio en formato `whsec_`) y
  se muestran una sola vez. Rotar uno mantiene el anterior firmando 24 h. Los
  pasos anteriores a ADR-038 conservan su secreto libre y la cabecera antigua
  `X-Escenia-Signature` (HMAC hex del cuerpo, sin marca de tiempo) hasta que se
  rota.
- **Reintentos**: error de conexión, host que no resuelve, 408, 429 y 5xx se
  reintentan en el mismo paso a 1 min, 5 min, 30 min, 2 h y 5 h (+ hasta 10 %
  de jitter; `Retry-After` respetado hasta 5 h). Después el paso queda como
  `gave_up` y la secuencia sigue. 3xx, el resto de 4xx (410 incluido) y los
  destinos bloqueados no se reintentan. Un paso que lanza una excepción se
  reintenta a 1, 5 y 30 min y después el run falla.
- **Idempotencia**: checkpoint tras cada paso (uno terminado no se repite) y un
  lease de 10 min por run: un worker que lo pierde deja de escribir, y
  `automations:resume` recupera los runs cuyo worker murió. La entrega es *al
  menos una vez*: el receptor debe deduplicar por `webhook-id`.
- **Registro**: una entrada por intento (`outcome`, `attempt`, `status`,
  `webhook_id`, `next_attempt_at`); los logs del servidor solo llevan el host.

### Verificar en el receptor

Rechaza un `webhook-timestamp` a más de 5 minutos del reloj (en ambos
sentidos), compara la firma en tiempo constante, guarda los `webhook-id` ya
procesados al menos 24 h, responde 2xx en menos de 10 s y procesa en segundo
plano. Con las librerías de Standard Webhooks basta
`(new \StandardWebhooks\Webhook($secret))->verify($body, $headers)` (PHP) o
`new Webhook(secret).verify(body, headers)` (npm `standardwebhooks`). Sin
dependencias:

```php
function verifyEsceniaWebhook(string $body, ?string $id, ?string $timestamp, ?string $signatures, string $secret): array
{
    if ($id === null || $timestamp === null || $signatures === null || ! ctype_digit($timestamp)) {
        throw new RuntimeException('Missing webhook headers');
    }

    if (abs(time() - (int) $timestamp) > 300) {
        throw new RuntimeException('Timestamp outside tolerance');
    }

    $key = base64_decode(substr($secret, strlen('whsec_')), true);
    $expected = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", (string) $key, true));

    foreach (explode(' ', $signatures) as $versioned) {
        [$version, $signature] = array_pad(explode(',', $versioned, 2), 2, '');

        if ($version === 'v1' && hash_equals($expected, $signature)) {
            return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        }
    }

    throw new RuntimeException('No matching signature');
}
```

```js
import { createHmac, timingSafeEqual } from 'node:crypto'

export function verifyEsceniaWebhook(rawBody, headers, secret, toleranceSeconds = 300) {
  const id = headers['webhook-id']
  const timestamp = headers['webhook-timestamp']
  const signatures = headers['webhook-signature']
  if (!id || !timestamp || !signatures || !/^\d+$/.test(timestamp)) throw new Error('missing webhook headers')
  if (Math.abs(Date.now() / 1000 - Number(timestamp)) > toleranceSeconds) throw new Error('timestamp outside tolerance')

  const key = Buffer.from(secret.slice('whsec_'.length), 'base64')
  const expected = createHmac('sha256', key).update(`${id}.${timestamp}.`).update(rawBody).digest()

  for (const versioned of signatures.split(' ')) {
    const [version, signature] = versioned.split(',')
    if (version !== 'v1' || !signature) continue
    const given = Buffer.from(signature, 'base64')
    if (given.length === expected.length && timingSafeEqual(given, expected)) return JSON.parse(rawBody.toString('utf8'))
  }
  throw new Error('no matching signature')
}
```
