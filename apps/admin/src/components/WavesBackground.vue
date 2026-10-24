<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import {
  AmbientLight,
  BufferAttribute,
  BufferGeometry,
  DoubleSide,
  DynamicDrawUsage,
  LinearSRGBColorSpace,
  Mesh,
  MeshPhongMaterial,
  PerspectiveCamera,
  PointLight,
  Scene,
  Vector3,
  WebGLRenderer,
} from 'three'

/**
 * Olas del panel de acceso (TD-050): reimplementación del efecto «WAVES» de
 * vanta.js 0.5.24 sobre la three actual, con el aspecto que tenía en three r134.
 *
 * r134 no gestionaba el color ni usaba iluminación física, así que:
 * - los hex se cargan como lineales (`setHex(…, LinearSRGBColorSpace)`), también
 *   el especular por defecto, y la salida no se codifica (`outputColorSpace`
 *   lineal);
 * - r134 multiplicaba por π la intensidad de las luces y un PointLight con
 *   `distance` 0 no se atenuaba: intensidad × π y `decay` 0.
 *
 * Con movimiento reducido dibuja un cuadro fijo. Libera la GPU al desmontarse.
 */

// Opciones con las que el login configuraba vanta.
const COLOR = 0x0e2a4a
const ESPECULAR = 0x111111 // por defecto de MeshPhongMaterial, fijado en lineal
const BRILLO = 40
const ALTURA_OLA = 15
const VELOCIDAD = 0.85
const ZOOM = 0.92
const TAMANO_MINIMO = 200

// Constantes internas de vanta WAVES.
const COLUMNAS = 100
const FILAS = 80
const CELDA = 18
const RUIDO = 4
const INTENSIDAD = 0.9 * Math.PI
const SUAVIZADO = 0.02 // por cuadro, como vanta
const POSICION_CAMARA = new Vector3(240, 200, 390)
const OBJETIVO_CAMARA = new Vector3(140, -30, 190)

const raiz = ref<HTMLElement | null>(null)
const lienzo = ref<HTMLCanvasElement | null>(null)
let detener: (() => void) | null = null

onMounted(() => {
  if (raiz.value !== null && lienzo.value !== null) detener = iniciar(raiz.value, lienzo.value)
})

onBeforeUnmount(() => {
  detener?.()
  detener = null
})

function crearRenderer(canvas: HTMLCanvasElement): WebGLRenderer | null {
  try {
    return new WebGLRenderer({ canvas, alpha: true, antialias: true })
  } catch {
    return null // sin WebGL 2 queda el fondo neutro del panel
  }
}

function iniciar(contenedor: HTMLElement, canvas: HTMLCanvasElement): (() => void) | null {
  const renderer = crearRenderer(canvas)
  if (renderer === null) return null
  renderer.outputColorSpace = LinearSRGBColorSpace

  // Rejilla (COLUMNAS+1)×(FILAS+1) con ruido vertical y una diagonal al azar por
  // celda. Mismo orden de Math.random que vanta: primero vértices, luego caras.
  const porColumna = FILAS + 1
  const total = (COLUMNAS + 1) * porColumna
  const posiciones = new Float32Array(total * 3)
  const alturas = new Float32Array(total)
  const fases = new Float64Array(total)
  for (let i = 0; i <= COLUMNAS; i++) {
    for (let j = 0; j <= FILAS; j++) {
      const v = i * porColumna + j
      const x = (i - COLUMNAS * 0.5) * CELDA
      const y = Math.random() * RUIDO - 10
      const z = (FILAS * 0.5 - j) * CELDA
      posiciones[v * 3] = x
      posiciones[v * 3 + 1] = y
      posiciones[v * 3 + 2] = z
      alturas[v] = y
      // Parte fija de la fase de vanta: −s·x·0.025 + s·z·0.015 + √s·cos(−x − 0.7·z)
      fases[v] = -VELOCIDAD * x * 0.025 + VELOCIDAD * z * 0.015 + Math.sqrt(VELOCIDAD) * Math.cos(-x - z * 0.7)
    }
  }
  const indices = new Uint16Array(COLUMNAS * FILAS * 6) // 8181 vértices < 65536
  let k = 0
  for (let i = 1; i <= COLUMNAS; i++) {
    for (let j = 1; j <= FILAS; j++) {
      const a = (i - 1) * porColumna + j - 1
      const b = i * porColumna + j - 1
      const c = (i - 1) * porColumna + j
      const d = i * porColumna + j
      indices.set(Math.random() >= 0.5 ? [a, b, c, b, c, d] : [a, b, d, a, c, d], k)
      k += 6
    }
  }

  const posicion = new BufferAttribute(posiciones, 3).setUsage(DynamicDrawUsage)
  const geometria = new BufferGeometry()
  geometria.setAttribute('position', posicion)
  geometria.setIndex(new BufferAttribute(indices, 1))

  const material = new MeshPhongMaterial({ shininess: BRILLO, flatShading: true, side: DoubleSide })
  material.color.setHex(COLOR, LinearSRGBColorSpace)
  material.specular.setHex(ESPECULAR, LinearSRGBColorSpace)

  // El blanco es igual en sRGB y en lineal.
  const foco = new PointLight(0xffffff, INTENSIDAD, 0, 0)
  foco.position.set(-100, 250, -100)
  const escena = new Scene()
  escena.add(new Mesh(geometria, material), new AmbientLight(0xffffff, INTENSIDAD), foco)

  const camara = new PerspectiveCamera(35, 1, 50, 10000)
  camara.position.copy(POSICION_CAMARA)
  camara.lookAt(OBJETIVO_CAMARA)
  // Como vanta: hasta el primer movimiento del puntero la cámara no aplica el zoom.
  const destino = POSICION_CAMARA.clone()
  const origen = POSICION_CAMARA.clone().divideScalar(ZOOM)

  let ancho = TAMANO_MINIMO
  let alto = TAMANO_MINIMO
  let t = 0 // en cuadros de 60 fps, como vanta
  let anterior: number | null = null
  let cuadro = 0
  let corriendo = false
  let sinContexto = false
  const reducido = window.matchMedia('(prefers-reduced-motion: reduce)')

  const dibujar = (): void => renderer.render(escena, camara)

  const ondular = (): void => {
    const avance = VELOCIDAD * t * 0.02
    for (let v = 0; v < total; v++) {
      const delta = Math.sin(avance + fases[v]!)
      posiciones[v * 3 + 1] = alturas[v]! + ((delta + 1) ** 2 / 4) * ALTURA_OLA
    }
    posicion.needsUpdate = true
  }

  const seguirPuntero = (): void => {
    const p = camara.position
    if (Math.abs(destino.x - p.x) > 0.01) p.x += (destino.x - p.x) * SUAVIZADO
    if (Math.abs(destino.y - p.y) > 0.01) p.y += (destino.y - p.y) * SUAVIZADO
    if (Math.abs(destino.z - p.z) > 0.01) p.z += (destino.z - p.z) * SUAVIZADO
    camara.lookAt(OBJETIVO_CAMARA)
  }

  const paso = (): void => {
    const ahora = performance.now()
    // En cuadros de 60 fps, acotado a [0.2, 5] por paso como vanta: una pestaña
    // oculta (sin requestAnimationFrame) no provoca un salto al volver.
    if (anterior !== null) t += Math.min(Math.max((ahora - anterior) / (1000 / 60), 0.2), 5)
    anterior = ahora
    seguirPuntero()
    ondular()
    dibujar()
    cuadro = requestAnimationFrame(paso)
  }

  const actualizar = (): void => {
    const animar = !sinContexto && !reducido.matches
    if (animar && !corriendo) {
      corriendo = true
      anterior = null
      paso() // primer cuadro síncrono, como vanta
    } else if (!animar && corriendo) {
      corriendo = false
      cancelAnimationFrame(cuadro)
      if (!sinContexto) dibujar() // movimiento reducido: el cuadro actual queda fijo
    }
  }

  const reajustar = (): void => {
    ancho = Math.max(contenedor.offsetWidth, TAMANO_MINIMO)
    alto = Math.max(contenedor.offsetHeight, TAMANO_MINIMO)
    camara.aspect = ancho / alto
    camara.updateProjectionMatrix()
    renderer.setPixelRatio(window.devicePixelRatio)
    renderer.setSize(ancho, alto)
    if (!corriendo && !sinContexto) dibujar() // cambiar el tamaño vacía el lienzo
  }

  const apuntar = (clientX: number, clientY: number): void => {
    const rect = canvas.getBoundingClientRect()
    const x = clientX - rect.left
    const y = clientY - rect.top
    if (x < 0 || y < 0 || x > rect.width || y > rect.height) return
    const nx = x / ancho - 0.5
    const ny = y / alto - 0.5
    destino.set(origen.x + (nx * 100) / ZOOM, origen.y - (ny * 100) / ZOOM, origen.z - (nx * 50) / ZOOM)
  }
  const alMoverRaton = (e: MouseEvent): void => apuntar(e.clientX, e.clientY)
  const alTocar = (e: TouchEvent): void => {
    const toque = e.touches[0]
    if (e.touches.length === 1 && toque !== undefined) apuntar(toque.clientX, toque.clientY)
  }
  const alPerderContexto = (): void => {
    sinContexto = true // three ya llama a preventDefault para poder recuperarlo
    actualizar()
  }
  const alRecuperarContexto = (): void => {
    sinContexto = false // three reinicia su estado y vuelve a subir buffers y programas
    actualizar()
    if (!corriendo) dibujar()
  }

  const observadorTamano = new ResizeObserver(reajustar)

  ondular() // t = 0: también es el cuadro fijo con movimiento reducido
  reajustar()
  observadorTamano.observe(contenedor)
  window.addEventListener('mousemove', alMoverRaton, { passive: true })
  window.addEventListener('touchstart', alTocar, { passive: true })
  window.addEventListener('touchmove', alTocar, { passive: true })
  reducido.addEventListener('change', actualizar)
  canvas.addEventListener('webglcontextlost', alPerderContexto)
  canvas.addEventListener('webglcontextrestored', alRecuperarContexto)
  actualizar()

  return () => {
    corriendo = false
    cancelAnimationFrame(cuadro)
    observadorTamano.disconnect()
    window.removeEventListener('mousemove', alMoverRaton)
    window.removeEventListener('touchstart', alTocar)
    window.removeEventListener('touchmove', alTocar)
    reducido.removeEventListener('change', actualizar)
    canvas.removeEventListener('webglcontextlost', alPerderContexto)
    canvas.removeEventListener('webglcontextrestored', alRecuperarContexto)
    geometria.dispose()
    material.dispose()
    foco.dispose()
    renderer.dispose() // quita sus listeners y cachés; no suelta el contexto
    renderer.forceContextLoss() // libera el contexto WebGL ya, sin esperar al GC
  }
}
</script>

<template>
  <div ref="raiz" class="olas" aria-hidden="true">
    <canvas ref="lienzo" class="olas__lienzo"></canvas>
  </div>
</template>

<style scoped>
.olas {
  position: absolute;
  inset: 0;
}

.olas__lienzo {
  position: absolute;
  top: 0;
  left: 0;
  display: block;
}
</style>
