import { useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, CheckCircle2, Download, FileSpreadsheet, Upload } from 'lucide-react'
import { useId, useRef, useState } from 'react'
import { useAvisos } from '@/components/ui/avisos-contexto'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Casilla } from '@/components/ui/casilla'
import { TablaDatos } from '@/components/ui/tabla-datos'
import { descargar, guardarArchivo, subir, type ErrorApi } from '@/lib/api'
import type { Credencial, RevisionImportacion } from '@/lib/personas'

const COLUMNAS = [
  ['tipo_documento', 'DNI, CE o PASAPORTE', 'Sí'],
  ['numero_documento', 'DNI de 8 dígitos; CE o pasaporte con letras y números', 'Sí'],
  ['nombres', 'Solo letras, espacios, apóstrofo y guion', 'Sí'],
  ['apellidos', 'Igual que los nombres', 'Sí'],
  ['fecha_nacimiento', 'dd/mm/aaaa (por ejemplo, 14/05/2012)', 'Sí'],
  ['sexo', 'M o F', 'Sí'],
  ['nivel', 'PRIMARIA o SECUNDARIA', 'Si se matricula'],
  ['grado', '1 a 6 en primaria; 1 a 5 en secundaria', 'Si se matricula'],
  ['seccion', 'Letra de la sección (A, B…) o "Única"', 'Si se matricula'],
  ['correo', 'usuario@dominio.com', 'No'],
  ['celular', '9 dígitos, empieza con 9', 'No'],
  ['direccion', '5 a 200 caracteres', 'No'],
  ['codigo', 'E-AAAA-NNN; si se deja vacío, se genera solo', 'No'],
] as const

const A = '/admin/estudiantes'

// CU-11 (mockup admin 10): plantilla → archivo → revisión → importación
export function PestanaImportar() {
  const [archivo, setArchivo] = useState<File | null>(null)
  const [matricular, setMatricular] = useState(true)
  const [crearUsuario, setCrearUsuario] = useState(true)
  const [revision, setRevision] = useState<RevisionImportacion | null>(null)
  const [errorArchivo, setErrorArchivo] = useState<string | null>(null)
  const [ocupado, setOcupado] = useState(false)
  const [resultado, setResultado] = useState<{
    importados: number
    con_error: number
    credenciales: Credencial[]
  } | null>(null)
  const entrada = useRef<HTMLInputElement>(null)
  const idArchivo = useId()
  const avisos = useAvisos()
  const cliente = useQueryClient()

  const formulario = (f: File, conMatricula: boolean) => {
    const datos = new FormData()
    datos.append('archivo', f)
    datos.append('matricular', conMatricula ? '1' : '0')
    datos.append('crear_usuario', crearUsuario ? '1' : '0')
    return datos
  }

  const revisar = async (f: File, conMatricula = matricular) => {
    setOcupado(true)
    setErrorArchivo(null)
    setRevision(null)
    try {
      setRevision(await subir<RevisionImportacion>(`${A}/importar/revisar`, formulario(f, conMatricula)))
    } catch (e) {
      const error = e as ErrorApi
      setErrorArchivo(error.errores?.archivo?.[0] ?? error.message)
    } finally {
      setOcupado(false)
    }
  }

  const elegir = (f: File | undefined) => {
    setResultado(null)
    setArchivo(f ?? null)
    if (f) revisar(f)
  }

  const importar = async () => {
    if (!archivo) return
    setOcupado(true)
    try {
      const r = await subir<{ mensaje: string; importados: number; con_error: number; credenciales: Credencial[] }>(
        `${A}/importar`,
        formulario(archivo, matricular),
      )
      avisos.exito(r.mensaje)
      setResultado(r)
      setRevision(null)
      setArchivo(null)
      if (entrada.current) entrada.current.value = ''
      await cliente.invalidateQueries({ queryKey: ['personas'] })
    } catch (e) {
      avisos.error((e as ErrorApi).message)
    } finally {
      setOcupado(false)
    }
  }

  const bajarErrores = async () => {
    if (!archivo) return
    try {
      await descargar(`${A}/importar/errores`, 'filas_con_error.xlsx', {
        method: 'POST',
        body: formulario(archivo, matricular),
      })
    } catch (e) {
      avisos.error((e as ErrorApi).message)
    }
  }

  const bajarPlantilla = async () => {
    try {
      await descargar(`${A}/plantilla`, 'plantilla_estudiantes.xlsx')
    } catch (e) {
      avisos.error((e as ErrorApi).message)
    }
  }

  const bajarCredenciales = () => {
    if (!resultado) return
    const celda = (v: string) => `"${v.replace(/"/g, '""')}"`
    const lineas = [
      'codigo,nombre,contrasena_temporal',
      ...resultado.credenciales.map((c) => [c.codigo, c.nombre, c.contrasena ?? ''].map(celda).join(',')),
    ]
    // BOM para que Excel lea bien las tildes
    guardarArchivo(
      new Blob(['﻿' + lineas.join('\r\n')], { type: 'text/csv;charset=utf-8' }),
      'credenciales_estudiantes.csv',
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <Card className="flex flex-col gap-4">
        <Paso numero={1} titulo="Prepara el archivo" />
        <p className="text-texto-suave">
          Usa la plantilla: una fila por estudiante, con estos encabezados en la primera fila. Hasta 1000 filas y 5 MB.
        </p>
        <TablaDatos
          filas={COLUMNAS.map(([columna, formato, obligatoria]) => ({ columna, formato, obligatoria }))}
          claveFila={(c) => c.columna}
          descripcion="Columnas de la plantilla"
          porPagina={COLUMNAS.length}
          columnas={[
            {
              clave: 'columna',
              titulo: 'Columna',
              principal: true,
              celda: (c) => <code className="font-bold">{c.columna}</code>,
            },
            { clave: 'formato', titulo: 'Formato', celda: (c) => c.formato },
            { clave: 'obligatoria', titulo: 'Obligatoria', celda: (c) => c.obligatoria },
          ]}
        />
        <Button variante="secundario" className="self-start" onClick={bajarPlantilla}>
          <Download className="size-5" aria-hidden /> Descargar plantilla
        </Button>
      </Card>

      <Card className="flex flex-col gap-4">
        <Paso numero={2} titulo="Sube el archivo" />
        <label
          htmlFor={idArchivo}
          className="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-borde p-6 text-center hover:border-accion"
        >
          <FileSpreadsheet className="size-10 text-accion" aria-hidden />
          <span className="font-bold [overflow-wrap:anywhere]">
            {archivo ? archivo.name : 'Elige el archivo de Excel (.xlsx)'}
          </span>
          <span className="text-sm text-texto-suave">Se revisa antes de guardar nada.</span>
        </label>
        <input
          ref={entrada}
          id={idArchivo}
          type="file"
          accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
          className="sr-only"
          aria-label="Archivo de Excel"
          onChange={(e) => elegir(e.target.files?.[0])}
        />
        {errorArchivo && (
          <p role="alert" className="font-semibold text-peligro">
            {errorArchivo}
          </p>
        )}
        <Casilla
          etiqueta="Matricular en el periodo en curso (según nivel, grado y sección)"
          checked={matricular}
          onChange={(e) => {
            setMatricular(e.target.checked)
            if (archivo) revisar(archivo, e.target.checked)
          }}
        />
        <Casilla
          etiqueta="Crear usuario con contraseña temporal"
          checked={crearUsuario}
          onChange={(e) => setCrearUsuario(e.target.checked)}
        />
        {!crearUsuario && (
          <p className="text-sm text-texto-suave">Las cuentas se crean desactivadas; luego se activan en Usuarios.</p>
        )}
      </Card>

      {(revision || ocupado) && (
        <Card className="flex flex-col gap-4" aria-live="polite">
          <Paso numero={3} titulo="Revisa e importa" />
          {ocupado && !revision ? (
            <p className="text-texto-suave">Revisando el archivo…</p>
          ) : (
            revision && (
              <>
                <div className="grid grid-cols-3 gap-3 text-center">
                  <Conteo texto="Filas" valor={revision.filas} />
                  <Conteo texto="Válidas" valor={revision.validas} tono="text-exito" />
                  <Conteo
                    texto="Con error"
                    valor={revision.errores.length}
                    tono={revision.errores.length ? 'text-peligro' : undefined}
                  />
                </div>
                {matricular && revision.periodo && (
                  <p className="text-texto-suave">Se matricularán en el periodo {revision.periodo}.</p>
                )}
                {revision.errores.length > 0 && (
                  <div className="flex flex-col gap-3">
                    <ul className="flex max-h-80 flex-col gap-2 overflow-y-auto" aria-label="Errores por fila">
                      {revision.errores.map((e) => (
                        <li key={e.fila} className="rounded-xl bg-peligro/8 p-3">
                          <p className="flex items-center gap-2 font-bold text-peligro">
                            <AlertTriangle className="size-4" aria-hidden /> Fila {e.fila}
                          </p>
                          <ul className="ml-6 list-disc text-sm">
                            {e.mensajes.map((m) => (
                              <li key={m}>{m}</li>
                            ))}
                          </ul>
                        </li>
                      ))}
                    </ul>
                    <Button variante="secundario" className="self-start" onClick={bajarErrores}>
                      <Download className="size-5" aria-hidden /> Descargar filas con error
                    </Button>
                    <p className="text-sm text-texto-suave">
                      Las filas con error no se importan. Corrígelas en ese archivo y súbelo otra vez.
                    </p>
                  </div>
                )}
                <Button className="self-end" onClick={importar} disabled={ocupado || revision.validas === 0}>
                  <Upload className="size-5" aria-hidden />
                  {ocupado
                    ? 'Importando…'
                    : `Importar ${revision.validas} ${revision.validas === 1 ? 'estudiante' : 'estudiantes'}`}
                </Button>
              </>
            )
          )}
        </Card>
      )}

      {resultado && (
        <Card className="flex flex-col gap-4" aria-label="Resultado de la importación">
          <p className="flex items-center gap-2 text-xl font-black text-exito">
            <CheckCircle2 className="size-6" aria-hidden />
            {resultado.importados === 1
              ? 'Se importó 1 estudiante'
              : `Se importaron ${resultado.importados} estudiantes`}
          </p>
          {resultado.con_error > 0 && (
            <p className="text-texto-suave">
              {resultado.con_error === 1
                ? '1 fila tenía errores y no se importó.'
                : `${resultado.con_error} filas tenían errores y no se importaron.`}
            </p>
          )}
          {resultado.credenciales.length > 0 && (
            <>
              <p className="text-texto-suave">
                Contraseñas temporales: descárgalas ahora, no se volverán a mostrar. Cada estudiante la cambiará al
                ingresar.
              </p>
              <TablaDatos
                filas={resultado.credenciales}
                claveFila={(c) => c.codigo}
                descripcion="Credenciales generadas"
                porPagina={20}
                columnas={[
                  {
                    clave: 'codigo',
                    titulo: 'Código (usuario)',
                    principal: true,
                    celda: (c) => <strong>{c.codigo}</strong>,
                  },
                  { clave: 'nombre', titulo: 'Estudiante', celda: (c) => c.nombre },
                  { clave: 'clave', titulo: 'Contraseña temporal', celda: (c) => <code>{c.contrasena}</code> },
                ]}
              />
              <Button variante="secundario" className="self-start" onClick={bajarCredenciales}>
                <Download className="size-5" aria-hidden /> Descargar credenciales (CSV)
              </Button>
            </>
          )}
        </Card>
      )}
    </div>
  )
}

function Paso({ numero, titulo }: { numero: number; titulo: string }) {
  return (
    <h2 className="flex items-center gap-3 text-xl font-black">
      <span className="grid size-8 place-items-center rounded-full bg-accion text-base text-white" aria-hidden>
        {numero}
      </span>
      <span>
        <span className="sr-only">Paso {numero}: </span>
        {titulo}
      </span>
    </h2>
  )
}

function Conteo({ texto, valor, tono }: { texto: string; valor: number; tono?: string }) {
  return (
    <div className="rounded-xl bg-fondo p-3">
      <p className={`text-3xl font-black ${tono ?? ''}`}>{valor}</p>
      <p className="text-sm text-texto-suave">{texto}</p>
    </div>
  )
}
