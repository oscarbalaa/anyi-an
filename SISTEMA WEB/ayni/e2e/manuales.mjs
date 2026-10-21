// M15 - Arma los manuales finales (Word y PDF) a partir de docs/manual/*.md y sus capturas,
//   y la lista de verificación (docs/lista-de-verificacion.md).
//   Word: pandoc (índice que Word actualiza al abrir) · PDF: pandoc → HTML → Chromium (índice con enlaces)
// Uso: node manuales.mjs [--revisar]
//   AYNI_DOCS   carpeta docs (por defecto ../docs)
//   AYNI_SALIDA carpeta de salida (por defecto "../1. INICIO/MANUAL")
// Con --revisar falla si alguna captura citada no existe.
import { chromium } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { basename, dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const aqui = dirname(fileURLToPath(import.meta.url))
const DOCS = resolve(process.env.AYNI_DOCS ?? join(aqui, '../docs'))
const MANUAL = join(DOCS, 'manual')
const SALIDA = resolve(process.env.AYNI_SALIDA ?? join(aqui, '../1. INICIO/MANUAL'))
const REVISAR = process.argv.includes('--revisar')
const faltan = new Set()

const leer = (archivo) => readFileSync(join(MANUAL, archivo), 'utf8').replace(/\r\n/g, '\n')

/** Secciones "## …" de un archivo: [{ titulo, cuerpo }] (el título sin su número) */
function secciones(archivo) {
  const partes = leer(archivo).split(/^## /m)
  return partes.slice(1).map((p) => {
    const [titulo, ...resto] = p.split('\n')
    return { titulo: titulo.replace(/^\d+\.\s*/, '').trim(), cuerpo: resto.join('\n').trim() }
  })
}

/** Título del archivo sin "Manual de usuario — " */
const tituloDe = (archivo) =>
  leer(archivo)
    .match(/^# (.+)$/m)[1]
    .replace(/^Manual de (usuario|instalación de AYNI)\s*—?\s*/, '')
    .trim()

/** Baja un nivel los encabezados y quita su número manual (pandoc los numera) */
const bajar = (texto, niveles) =>
  texto.replace(
    /^(#{1,5}) (?:\d+\.\s*)?(.+)$/gm,
    (_, almohadillas, t) => `${'#'.repeat(almohadillas.length + niveles)} ${t}`,
  )

/** Tamaño de un PNG (ancho y alto en píxeles) leído de su cabecera */
function tamano(ruta) {
  const b = readFileSync(ruta)
  return { ancho: b.readUInt32BE(16), alto: b.readUInt32BE(20) }
}

/**
 * Las capturas son de página completa: para el manual se recortan (el celular, a su primera pantalla),
 * se achican y pasan a JPEG. Se anotan aquí y se convierten con Chromium antes de armar los documentos.
 */
const imagenes = new Map()
function preparar(origen, tipo) {
  const { ancho: a, alto: h } = tamano(origen)
  const recorte = Math.min(h, Math.round(a * (tipo === 'pc' ? 1.5 : 2.2)))
  const anchoFinal = Math.min(a, tipo === 'pc' ? 1100 : 520)
  const destino = join(temporal, 'imagenes', `${tipo}-${basename(origen, '.png')}.jpg`)
  const imagen = { origen, recorte, ancho: anchoFinal, alto: Math.round((recorte * anchoFinal) / a) }
  imagenes.set(destino, imagen)
  return { ruta: destino, ...imagen }
}

async function convertirImagenes(navegador) {
  mkdirSync(join(temporal, 'imagenes'), { recursive: true })
  const pagina = await navegador.newPage()
  for (const [destino, { origen, recorte, ancho, alto }] of imagenes) {
    const src = `data:image/png;base64,${readFileSync(origen).toString('base64')}`
    const jpeg = await pagina.evaluate(
      async ({ src, recorte, ancho, alto }) => {
        const img = new Image()
        img.src = src
        await img.decode()
        const lienzo = document.createElement('canvas')
        lienzo.width = ancho
        lienzo.height = alto
        const ctx = lienzo.getContext('2d')
        ctx.imageSmoothingQuality = 'high'
        ctx.drawImage(img, 0, 0, img.naturalWidth, recorte, 0, 0, ancho, alto)
        return lienzo.toDataURL('image/jpeg', 0.85).split(',')[1]
      },
      { src, recorte, ancho, alto },
    )
    writeFileSync(destino, Buffer.from(jpeg, 'base64'))
  }
  await pagina.close()
}

/** Imagen lista para el manual, con su ancho en cm para que no pase de anchoMax ni de altoMax */
function imagen(alt, origen, tipo, anchoMax, altoMax) {
  const { ruta, ancho: a, alto: h } = preparar(origen, tipo)
  return `![${alt}](${ruta}){width=${Math.min(anchoMax, (altoMax * a) / h).toFixed(1)}cm}`
}

/**
 * Capturas: rutas absolutas y tamaño para Word y PDF. Una captura de PC sola en su línea va junto a la
 * del celular (misma pantalla) si existe. Las que están dentro de tablas se achican.
 */
function capturas(texto) {
  return texto
    .split('\n')
    .map((linea) => {
      const sola = linea.match(/^!\[([^\]]*)\]\(capturas\/pc\/([^)]+)\)\s*$/)
      if (sola) {
        const [, alt, archivo] = sola
        const pc = join(MANUAL, 'capturas/pc', archivo)
        const celular = join(MANUAL, 'capturas/celular', archivo)
        if (!existsSync(pc)) {
          faltan.add(`pc/${archivo}`)
          return `*(Captura pendiente: ${alt || archivo})*`
        }
        if (!existsSync(celular)) return imagen(alt, pc, 'pc', 16, 19)
        return `::: {.par}\n${imagen(`${alt} (PC)`, pc, 'pc', 11.5, 17)} ${imagen(`${alt} (celular)`, celular, 'celular', 4.6, 17)}\n:::`
      }
      return linea.replace(/!\[([^\]]*)\]\(capturas\/(pc|celular)\/([^)]+)\)/g, (_, alt, tipo, archivo) => {
        const ruta = join(MANUAL, 'capturas', tipo, archivo)
        if (!existsSync(ruta)) {
          faltan.add(`${tipo}/${archivo}`)
          return `*(Captura pendiente)*`
        }
        return imagen(alt, ruta, tipo, tipo === 'pc' ? 5.6 : 3.8, 9)
      })
    })
    .join('\n')
}

/** Quita las notas internas ("> Sección del Módulo N…") y los enlaces a otros .md del repositorio */
const limpiar = (texto) =>
  texto
    .replace(/^> Sección del Módulo.*\n?/gm, '')
    .replace(/\[([^\]]+)\]\((?!https?:)[^)]*\.md\)/g, '$1')
    .replace(/`(usuario-\d+[^`]*|instalacion)\.md`/g, 'este manual')
    // GitHub acepta una lista pegada al párrafo; pandoc necesita una línea en blanco antes
    .replace(/^(?!\s*[-*+] |\s*\d+\. |\s*$|\||>|#|```)(.+)\n(\s*[-*+] )/gm, '$1\n\n$2')

// ---------------------------------------------------------------- Manual de usuario

const MODULOS = [
  'usuario-03-usuarios-y-perfil.md',
  'usuario-04-estructura-academica.md',
  'usuario-05-personas-y-matriculas.md',
  'usuario-06-asistencia.md',
  'usuario-07-evaluaciones-y-notas.md',
  'usuario-08-incidencias-y-notificaciones.md',
  'usuario-09-alertas-y-riesgo.md',
  'usuario-10-inicio-y-reportes.md',
]

const ROLES = `| Rol | Qué hace en AYNI | Dónde está |
|---|---|---|
| **Administrador** | Gestiona usuarios, la estructura académica (periodos, aulas, cursos, horarios), docentes, estudiantes y matrículas; corrige asistencia; cierra bimestres; revisa el panel de riesgo, todas las alertas y los reportes | Usuarios · Estructura académica · Docentes, estudiantes y matrículas · Asistencia · Evaluaciones y notas (cierre) · Incidencias · Alertas y riesgo · Inicio, ficha 360° y reportes |
| **Coordinador** | Es un docente con un ámbito a cargo (aula, grado, nivel o colegio): además de lo del docente, ve el panel de riesgo de su ámbito y puede reasignar alertas | Alertas y riesgo (Coordinación) · Inicio |
| **Docente** | Registra asistencia, evaluaciones y notas de sus cursos, publica promedios, registra incidencias, atiende las alertas a su cargo y consulta a sus estudiantes | Asistencia · Evaluaciones y notas · Incidencias · Alertas · Inicio y Mis estudiantes |
| **Estudiante** | Consulta sus notas, asistencia, horario y actividades; entrega sus trabajos en PDF; recibe avisos y descarga su boleta | Asistencia · Evaluaciones y notas · Notificaciones · Inicio y boleta |
| **Todos** | Mi perfil (celular y correo), notificaciones de la campana, cerrar sesión | Usuarios y Mi perfil · Notificaciones |`

function manualUsuario() {
  const acceso = secciones('usuario-02-inicio-de-sesion.md')
  const navegacion = secciones('usuario-01-acceso-y-navegacion.md')
  const de = (lista, titulo) => lista.find((s) => s.titulo.startsWith(titulo)) ?? { titulo, cuerpo: '' }
  const seccion = (s, nivel = 2) => `${'#'.repeat(nivel)} ${s.titulo}\n\n${bajar(s.cuerpo, nivel - 2)}`
  const problemas = []

  const capitulos = [
    '# Requisitos de acceso',
    seccion({ ...de(acceso, 'Requisitos'), titulo: 'Quién ingresa y con qué usuario' }),
    `## Formas de usar AYNI\n\n${secciones('instalacion.md').find((s) => s.titulo.startsWith('Formas'))?.cuerpo ?? ''}`,
    '# Inicio de sesión, captcha y recuperación de contraseña',
    ...['Pantallas de ingreso'].map((t) => seccion(de(navegacion, t))),
    ...['Iniciar sesión', 'Recuperar', 'Cambiar la contraseña temporal', 'Cuenta bloqueada'].map((t) =>
      seccion(de(acceso, t)),
    ),
    '# Navegación principal',
    ...['Página de inicio', 'Elegir cómo ingresar', 'Navegación dentro', 'Elementos comunes', 'Estado del sistema'].map(
      (t) => seccion(de(navegacion, t)),
    ),
    ...secciones('usuario-10-inicio-y-reportes.md')
      .filter((s) => s.titulo.startsWith('Navegación principal'))
      .map((s) => seccion({ ...s, titulo: 'El inicio de cada rol' })),
    `# Funciones por rol\n\n${ROLES}`,
  ]
  for (const archivo of MODULOS) {
    const todas = secciones(archivo).filter(
      (s) => !(archivo.includes('usuario-10') && s.titulo.startsWith('Navegación principal')),
    )
    const intro = leer(archivo)
      .split(/^## /m)[0]
      .replace(/^# .+$/m, '')
      .trim()
    capitulos.push(`## ${tituloDe(archivo)}\n\n${limpiar(intro)}`)
    for (const s of todas) {
      if (s.titulo.startsWith('Problemas frecuentes')) problemas.push({ titulo: tituloDe(archivo), cuerpo: s.cuerpo })
      else if (!s.titulo.startsWith('Prueba tú mismo')) capitulos.push(seccion(s, 3))
    }
  }
  capitulos.push(
    '# Cierre de sesión',
    seccion({ ...de(acceso, 'Cerrar sesión'), titulo: 'Cerrar sesión a pedido o por inactividad' }),
  )
  const problemasAcceso = de(acceso, 'Problemas frecuentes')
  capitulos.push(
    '# Solución de problemas frecuentes',
    `## Inicio de sesión\n\n${problemasAcceso.cuerpo}`,
    ...problemas.map((p) => `## ${p.titulo}\n\n${p.cuerpo}`),
  )
  return capitulos.map(limpiar).map(capturas).join('\n\n')
}

// ---------------------------------------------------------------- Manual de instalación

function manualInstalacion() {
  // La publicación en el hosting es solo del equipo: no va en el manual que se entrega
  return secciones('instalacion.md')
    .filter((s) => !s.titulo.startsWith('Publicar en el hosting'))
    .map((s) => ({ ...s, cuerpo: s.cuerpo.replace(/^\| (Publicar:|.*CREDENCIALES_PRIVADAS).*\n/gm, '') }))
    .map(
      (s) =>
        `# ${s.titulo}\n\n${bajar(s.cuerpo, -1).replace(/^(#{1,5}) /gm, (m, a) => `${'#'.repeat(Math.max(2, a.length))} `)}`,
    )
    .map(limpiar)
    .map(capturas)
    .join('\n\n')
}

// ---------------------------------------------------------------- Lista de verificación (M13)

function listaVerificacion() {
  const texto = readFileSync(join(DOCS, 'lista-de-verificacion.md'), 'utf8').replace(/\r\n/g, '\n')
  return limpiar(bajar(texto.replace(/^# .+\n/, ''), -1)).replace(/^> Módulo \d+\. /m, '> ')
}

// ---------------------------------------------------------------- Salida

const CSS = `
@page { size: A4; margin: 18mm 16mm 20mm; }
body { font-family: 'Nunito Sans', 'Noto Sans', 'Liberation Sans', Arial, sans-serif; font-size: 10.5pt; color: #263238; line-height: 1.45; max-width: none; margin: 0; padding: 0; }
h1, h2, h3, h4 { color: #9e4528; line-height: 1.2; break-after: avoid; }
h1 { font-size: 20pt; border-bottom: 2px solid #f0d4c4; padding-bottom: 4pt; break-before: page; }
h2 { font-size: 14pt; margin-top: 18pt; }
h3 { font-size: 12pt; color: #6e4b6b; }
.portada { height: 250mm; display: flex; flex-direction: column; justify-content: center; text-align: center; break-after: page; }
.portada h1 { break-before: auto; border: 0; font-size: 34pt; margin: 0; }
.portada .sub { font-size: 15pt; color: #55606a; margin-top: 8pt; }
.portada .pie { margin-top: 60pt; color: #55606a; }
#TOC { break-after: page; }
#TOC ul { list-style: none; padding-left: 14pt; }
#TOC > ul { padding-left: 0; }
#TOC a { color: #263238; text-decoration: none; }
table { border-collapse: collapse; width: 100%; margin: 8pt 0; font-size: 9.5pt; break-inside: avoid; }
th, td { border: 1px solid #d9d4cf; padding: 4pt 6pt; vertical-align: top; text-align: left; }
th { background: #fbe9df; }
img { max-width: 100%; height: auto; border: 1px solid #e3ded9; border-radius: 4pt; }
figure { margin: 8pt 0; break-inside: avoid; text-align: center; }
figcaption { font-size: 9pt; color: #55606a; }
.par { display: flex; gap: 10pt; align-items: flex-start; justify-content: center; break-inside: avoid; margin: 8pt 0; }
.par img { max-height: 170mm; }
code { font-size: 9pt; background: #f4f1ee; padding: 0 2pt; border-radius: 2pt; }
pre { background: #f4f1ee; padding: 6pt; font-size: 8.5pt; white-space: pre-wrap; }
blockquote { border-left: 3px solid #f0d4c4; margin-left: 0; padding-left: 10pt; color: #55606a; }
`

// pandoc lee los argumentos según el idioma del sistema: con UTF-8 las tildes llegan bien
const pandoc = (args) => execFileSync('pandoc', args, { env: { ...process.env, LANG: 'C.UTF-8', LC_ALL: 'C.UTF-8' } })

/** Con capitulosEnHoja, cada capítulo (#) empieza en una hoja nueva */
function generar(nombre, titulo, cuerpo, temporal, capitulosEnHoja) {
  const fecha = new Intl.DateTimeFormat('es-PE', { dateStyle: 'long', timeZone: 'America/Lima' }).format(new Date())
  const md = join(temporal, `${nombre}.md`)
  writeFileSync(md, cuerpo)
  const comunes = [
    '--from',
    'markdown+fenced_divs+pipe_tables+link_attributes',
    '--toc',
    '--toc-depth=2',
    '--number-sections',
    '--resource-path',
    MANUAL,
  ]
  const meta = [
    '-M',
    `title=${titulo}`,
    '-M',
    'subtitle=AYNI · Sistema de gestión académica y seguimiento del estudiante',
    '-M',
    'author=Grupo 02 · IEP El Señor es mi Pastor',
    '-M',
    `date=${fecha}`,
    '-M',
    'lang=es-PE',
    '-M',
    'toc-title=Contenido',
  ]
  const plantilla = join(MANUAL, 'plantilla.docx')
  pandoc([
    md,
    ...comunes,
    ...meta,
    ...(existsSync(plantilla) ? ['--reference-doc', plantilla] : []),
    '-o',
    join(SALIDA, `${nombre}.docx`),
  ])

  const portada = `<div class="portada"><h1>${titulo}</h1><div class="sub">AYNI · Sistema de gestión académica y seguimiento del estudiante</div><div class="pie">Grupo 02 · IEP El Señor es mi Pastor<br>${fecha}</div></div>`
  const css = join(temporal, 'manual.css')
  writeFileSync(css, capitulosEnHoja ? CSS : `${CSS}\nh1 { break-before: auto; }\n`)
  const html = join(temporal, `${nombre}.html`)
  pandoc([
    md,
    ...comunes,
    '--standalone',
    '--embed-resources',
    '--css',
    css,
    '-M',
    `pagetitle=${titulo}`,
    '-M',
    'lang=es-PE',
    '-M',
    'toc-title=Contenido',
    '-V',
    `include-before=${portada}`,
    '-o',
    html,
  ])
  return html
}

const temporal = mkdtempSync(join(tmpdir(), 'ayni-manuales-'))
mkdirSync(SALIDA, { recursive: true })
const manuales = [
  ['Manual de usuario', 'Manual de usuario', manualUsuario(), true],
  ['Manual de instalación', 'Manual de instalación', manualInstalacion(), true],
  ['Lista de verificación', 'Lista de verificación', listaVerificacion(), false],
]
if (REVISAR && faltan.size) {
  console.error(`Faltan capturas (ejecuta scripts/equipo/capturas.ps1):\n- ${[...faltan].sort().join('\n- ')}`)
  process.exit(1)
}

// AYNI_CHROMIUM: otro Chrome si el de Playwright no está instalado (por ejemplo, fuera del contenedor)
const navegador = await chromium.launch(process.env.AYNI_CHROMIUM ? { executablePath: process.env.AYNI_CHROMIUM } : {})
await convertirImagenes(navegador)
for (const [nombre, titulo, cuerpo, capitulosEnHoja] of manuales) {
  const html = generar(nombre, titulo, cuerpo, temporal, capitulosEnHoja)
  const pagina = await navegador.newPage()
  await pagina.goto(`file://${html}`)
  await pagina.pdf({
    path: join(SALIDA, `${nombre}.pdf`),
    format: 'A4',
    printBackground: true,
    displayHeaderFooter: true,
    headerTemplate: '<span></span>',
    footerTemplate: `<div style="width:100%;font-size:8pt;color:#777;padding:0 16mm;display:flex;justify-content:space-between"><span>AYNI · ${titulo}</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>`,
    margin: { top: '18mm', bottom: '20mm', left: '16mm', right: '16mm' },
  })
  await pagina.close()
  console.log(`${nombre}: .docx y .pdf listos`)
}
await navegador.close()
rmSync(temporal, { recursive: true, force: true })
if (faltan.size)
  console.warn(
    `Aviso: ${faltan.size} capturas pendientes (scripts/equipo/capturas.ps1): ${[...faltan].sort().join(', ')}`,
  )
