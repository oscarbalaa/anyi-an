import { z } from 'zod'
import type { Rol } from '@/layout/roles'

// Reglas de docs/validaciones.md (las mismas que valida el backend)
export const REGLAS = {
  codigoEstudiante: /^E-\d{4}-\d{3}$/,
  dni: /^\d{8}$/,
  usuarioAdmin: /^[A-Za-z][A-Za-z0-9._]{3,29}$/,
  correo: /^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$/,
  celular: /^9\d{8}$/,
  nombre: /^[\p{L}' -]+$/u,
  cargo: /^[\p{L}\d .,-]+$/u,
}

export const filtros = {
  codigoEstudiante: (v: string) => v.toUpperCase().replace(/[^A-Z0-9-]/g, ''),
  dni: (v: string) => v.replace(/\D/g, ''),
  usuarioAdmin: (v: string) => v.replace(/[^A-Za-z0-9._]/g, ''),
  captcha: (v: string) => v.toUpperCase().replace(/[^A-Z0-9]/g, ''),
  celular: (v: string) => v.replace(/\D/g, ''),
  nombre: (v: string) => v.replace(/[^\p{L}' -]/gu, ''),
  /** Nota: solo dígitos y un separador decimal (punto o coma) */
  nota: (v: string) => v.replace(/[^\d.,]/g, '').replace(/([.,].*)[.,]/, '$1'),
}

const contrasena = z
  .string()
  .min(1, 'Ingresa tu contraseña.')
  .max(64, 'La contraseña no debe tener más de 64 caracteres.')

const captcha = z
  .string()
  .min(1, 'Escribe el código de la imagen.')
  .length(5, 'El código de la imagen tiene 5 caracteres.')

export const esquemasIngreso = {
  estudiante: z.object({
    usuario: z
      .string()
      .min(1, 'Ingresa tu código de estudiante.')
      .regex(REGLAS.codigoEstudiante, 'El código tiene el formato E-AAAA-NNN (por ejemplo, E-2026-001).'),
    contrasena,
    captcha,
    recordarme: z.boolean(),
  }),
  docente: z.object({
    usuario: z.string().min(1, 'Ingresa tu DNI.').regex(REGLAS.dni, 'El DNI debe tener 8 dígitos.'),
    contrasena,
    captcha,
    recordarme: z.boolean(),
  }),
  admin: z.object({
    usuario: z
      .string()
      .min(1, 'Ingresa tu usuario.')
      .regex(
        REGLAS.usuarioAdmin,
        'El usuario tiene de 4 a 30 caracteres, empieza con letra y solo usa letras, números, punto o guion bajo.',
      ),
    contrasena,
    captcha,
    recordarme: z.boolean(),
  }),
}

export type DatosIngreso = z.infer<typeof esquemasIngreso.estudiante>

const MENSAJE_USUARIO: Record<Rol, [RegExp, string, string]> = {
  estudiante: [
    REGLAS.codigoEstudiante,
    'Ingresa tu código de estudiante.',
    'El código tiene el formato E-AAAA-NNN (por ejemplo, E-2026-001).',
  ],
  docente: [REGLAS.dni, 'Ingresa tu DNI.', 'El DNI debe tener 8 dígitos.'],
  admin: [
    REGLAS.usuarioAdmin,
    'Ingresa tu usuario.',
    'El usuario tiene de 4 a 30 caracteres, empieza con letra y solo usa letras, números, punto o guion bajo.',
  ],
}

export const esquemaRecuperar = (rol: Rol) =>
  z.object({
    usuario: z.string().min(1, MENSAJE_USUARIO[rol][1]).regex(MENSAJE_USUARIO[rol][0], MENSAJE_USUARIO[rol][2]),
    captcha,
  })

export type DatosRecuperar = z.infer<ReturnType<typeof esquemaRecuperar>>

/** RN-16: requisitos de la contraseña según el rol (se muestran como lista) */
export function requisitosContrasena(rol: Rol | null) {
  const base = [{ texto: 'Al menos 8 caracteres', cumple: (v: string) => v.length >= 8 }]
  if (!rol) return base
  if (rol === 'estudiante') {
    return [
      ...base,
      { texto: 'Al menos una letra', cumple: (v: string) => /\p{L}/u.test(v) },
      { texto: 'Al menos un número', cumple: (v: string) => /\d/.test(v) },
    ]
  }
  return [
    ...base,
    { texto: 'Una mayúscula y una minúscula', cumple: (v: string) => /\p{Lu}/u.test(v) && /\p{Ll}/u.test(v) },
    { texto: 'Al menos un número', cumple: (v: string) => /\d/.test(v) },
    { texto: 'Al menos un símbolo (#, $, %…)', cumple: (v: string) => /[^\p{L}\d]/u.test(v) },
  ]
}

const MENSAJE_REQUISITO: Record<string, string> = {
  'Al menos 8 caracteres': 'La nueva contraseña debe tener al menos 8 caracteres.',
  'Al menos una letra': 'La nueva contraseña debe contener al menos una letra.',
  'Al menos un número': 'La nueva contraseña debe contener al menos un número.',
  'Una mayúscula y una minúscula': 'La nueva contraseña debe contener al menos una mayúscula y una minúscula.',
  'Al menos un símbolo (#, $, %…)': 'La nueva contraseña debe contener al menos un símbolo.',
}

/** Nueva contraseña con confirmación; sin rol solo se revisa el largo (el servidor revisa el resto) */
export function esquemaNuevaContrasena(rol: Rol | null, usuario = '') {
  return z
    .object({
      contrasena: z
        .string()
        .max(64, 'La nueva contraseña no debe tener más de 64 caracteres.')
        .superRefine((v, ctx) => {
          const falla = requisitosContrasena(rol).find((r) => !r.cumple(v))
          if (falla) ctx.addIssue({ code: 'custom', message: MENSAJE_REQUISITO[falla.texto] })
          else if (usuario && v.toLowerCase() === usuario.toLowerCase())
            ctx.addIssue({ code: 'custom', message: 'La contraseña no puede ser igual a tu usuario.' })
        }),
      confirmacion: z.string().min(1, 'Repite la nueva contraseña.'),
    })
    .refine((d) => d.contrasena === d.confirmacion, {
      path: ['confirmacion'],
      message: 'La confirmación de la nueva contraseña no coincide.',
    })
}

export type DatosNuevaContrasena = z.infer<ReturnType<typeof esquemaNuevaContrasena>>

const limpio = (v: string) => v.trim().replace(/\s+/g, ' ')

/** Correo y celular opcionales (docs/validaciones.md, "Personas") */
export const esquemaContacto = z.object({
  correo: z
    .string()
    .transform(limpio)
    .pipe(
      z
        .string()
        .max(120, 'El correo no debe tener más de 120 caracteres.')
        .refine((v) => v === '' || REGLAS.correo.test(v), 'El correo debe tener el formato usuario@dominio.com.'),
    ),
  celular: z
    .string()
    .refine((v) => v === '' || REGLAS.celular.test(v), 'El celular debe tener 9 dígitos y empezar con 9.'),
})

export type DatosContacto = z.infer<typeof esquemaContacto>

const nombre = (campo: 'nombres' | 'apellidos') =>
  z
    .string()
    .transform(limpio)
    .pipe(
      z
        .string()
        .min(1, campo === 'nombres' ? 'Ingresa los nombres.' : 'Ingresa los apellidos.')
        .min(2, `Los ${campo} deben tener al menos 2 letras.`)
        .max(100, `Los ${campo} no deben tener más de 100 caracteres.`)
        .regex(REGLAS.nombre, 'Solo se permiten letras, espacios, apóstrofo y guion.'),
    )

/** Cuenta administrativa (RF-12) */
export const esquemaUsuarioAdmin = esquemaContacto.extend({
  nombres: nombre('nombres'),
  apellidos: nombre('apellidos'),
  dni: z.string().min(1, 'Ingresa el DNI.').regex(REGLAS.dni, 'El DNI debe tener 8 dígitos.'),
  usuario: z.string().min(1, 'Ingresa el nombre de usuario.').regex(REGLAS.usuarioAdmin, MENSAJE_USUARIO.admin[2]),
  cargo: z
    .string()
    .transform(limpio)
    .pipe(
      z
        .string()
        .min(1, 'Ingresa el cargo.')
        .min(2, 'El cargo debe tener al menos 2 caracteres.')
        .max(80, 'El cargo no debe tener más de 80 caracteres.')
        .regex(REGLAS.cargo, 'El cargo solo puede tener letras, números, espacios y . , -'),
    ),
})

export type DatosUsuarioAdmin = z.input<typeof esquemaUsuarioAdmin>

// --- Estructura académica (M6) ---

const fechaObligatoria = (mensaje: string) => z.string().regex(/^\d{4}-\d{2}-\d{2}$/, mensaje)

export const esquemaPeriodo = z
  .object({
    nombre: z
      .string()
      .min(1, 'Ingresa el año del periodo.')
      .regex(/^\d{4}$/, 'El periodo es un año de 4 dígitos entre 2020 y 2100.')
      .refine((v) => Number(v) >= 2020 && Number(v) <= 2100, 'El periodo es un año de 4 dígitos entre 2020 y 2100.'),
    fecha_inicio: fechaObligatoria('Ingresa una fecha de inicio válida.'),
    fecha_fin: fechaObligatoria('Ingresa una fecha de fin válida.'),
  })
  .refine((d) => d.fecha_fin > d.fecha_inicio, {
    path: ['fecha_fin'],
    message: 'La fecha de fin debe ser posterior a la de inicio.',
  })

export type DatosPeriodo = z.infer<typeof esquemaPeriodo>

export const esquemaBimestre = z
  .object({
    fecha_inicio: fechaObligatoria('Ingresa una fecha de inicio válida.'),
    fecha_fin: fechaObligatoria('Ingresa una fecha de fin válida.'),
    fecha_cierre_notas: z.string(),
  })
  .refine((d) => d.fecha_fin > d.fecha_inicio, {
    path: ['fecha_fin'],
    message: 'La fecha de fin debe ser posterior a la de inicio.',
  })
  .refine((d) => !d.fecha_cierre_notas || d.fecha_cierre_notas >= d.fecha_inicio, {
    path: ['fecha_cierre_notas'],
    message: 'La fecha de cierre de notas no puede ser anterior al inicio del bimestre (RN-02).',
  })

export type DatosBimestre = z.infer<typeof esquemaBimestre>

const ambiente = z
  .string()
  .max(40, 'El ambiente no debe tener más de 40 caracteres.')
  .refine(
    (v) => v === '' || /^[\p{L}\d .-]+$/u.test(v),
    'El ambiente solo puede tener letras, números, espacios, punto y guion.',
  )

const capacidad = z
  .string()
  .min(1, 'Ingresa la capacidad.')
  .refine((v) => /^\d+$/.test(v) && Number(v) >= 1 && Number(v) <= 60, 'La capacidad es un número entero de 1 a 60.')

export const esquemaAula = z.object({
  grado_id: z.string().min(1, 'Elige un grado válido.'),
  seccion: z
    .string()
    .min(1, 'Ingresa la sección.')
    .regex(/^(Única|[A-Z]{1,3})$/u, 'La sección es "Única" o de 1 a 3 letras mayúsculas (A, B, C…).'),
  capacidad,
  tutor_docente_id: z.string(),
  coordinador_docente_id: z.string(),
  ambiente,
})

export type DatosAula = z.infer<typeof esquemaAula>

export const esquemaAsignatura = z.object({
  codigo: z
    .string()
    .min(1, 'Ingresa el código.')
    .regex(/^[A-Za-z0-9-]{2,20}$/, 'El código tiene de 2 a 20 caracteres: letras, números y guion.'),
  nombre: z
    .string()
    .transform(limpio)
    .pipe(
      z
        .string()
        .min(1, 'Ingresa el nombre.')
        .regex(/^[\p{L}\d ]{3,100}$/u, 'El nombre tiene de 3 a 100 caracteres: letras, números y espacios.'),
    ),
  horas_semanales: z
    .string()
    .min(1, 'Ingresa las horas semanales.')
    .refine(
      (v) => /^\d+$/.test(v) && Number(v) >= 1 && Number(v) <= 20,
      'Las horas semanales son un número entero de 1 a 20.',
    ),
  estado: z.enum(['ACTIVO', 'INACTIVO']),
})

export type DatosAsignatura = z.input<typeof esquemaAsignatura>

export const esquemaTipo = z.object({
  nombre: z
    .string()
    .transform(limpio)
    .pipe(
      z
        .string()
        .min(1, 'Ingresa el nombre del tipo.')
        .regex(/^[\p{L} ]{3,40}$/u, 'El nombre tiene de 3 a 40 letras.'),
    ),
})

const hora = z
  .string()
  .regex(/^\d{2}:\d{2}$/, 'La hora tiene el formato HH:MM.')
  .refine((v) => v >= '06:00' && v <= '20:00', 'La hora debe estar entre 06:00 y 20:00.')

export const esquemaBloque = z
  .object({
    curso_id: z.string().min(1, 'Elige el curso.'),
    dia_semana: z.string().min(1, 'Elige un día de lunes a sábado.'),
    hora_inicio: hora,
    hora_fin: hora,
    ambiente,
  })
  .refine((d) => d.hora_fin > d.hora_inicio, {
    path: ['hora_fin'],
    message: 'La hora de fin debe ser posterior a la de inicio.',
  })

export type DatosBloque = z.infer<typeof esquemaBloque>

const num = (v: string) => Number(v.replace(',', '.'))
const esNumero = (v: string) => /^\d+([.,]\d{1,2})?$/.test(v)

/**
 * RF-26: datos del colegio, umbrales y escala literal (RN-11).
 * Se valida un objeto plano: min_AD, max_AD… para la escala.
 */
export const esquemaColegio = z.record(z.string(), z.string()).superRefine((d, ctx) => {
  const error = (campo: string, message: string) => ctx.addIssue({ code: 'custom', path: [campo], message })
  const nombre = d.nombre.trim()
  if (nombre.length < 3 || nombre.length > 150) error('nombre', 'El nombre del colegio tiene de 3 a 150 caracteres.')
  if (d.codigo_modular && !/^\d{7}$/.test(d.codigo_modular))
    error('codigo_modular', 'El código modular tiene 7 dígitos.')
  if (d.telefono && !/^\d{6,9}$/.test(d.telefono)) error('telefono', 'El teléfono tiene de 6 a 9 dígitos.')
  if (d.correo && !REGLAS.correo.test(d.correo.trim()))
    error('correo', 'El correo debe tener el formato usuario@dominio.com.')
  const direccion = d.direccion.trim()
  if (direccion && (direccion.length < 5 || !/^[\p{L}\d .,#\-/°]+$/u.test(direccion)))
    error('direccion', 'La dirección tiene de 5 a 200 caracteres: letras, números, espacios y . , # - / °')

  if (!esNumero(d.nota_aprobatoria) || num(d.nota_aprobatoria) > 20)
    error('nota_aprobatoria', 'La nota aprobatoria es un número de 0 a 20.')
  for (const c of ['umbral_asistencia_alto', 'umbral_asistencia_medio'])
    if (!/^\d+$/.test(d[c]) || num(d[c]) > 100) error(c, 'El umbral es un porcentaje de 0 a 100.')
  if (num(d.umbral_asistencia_alto) >= num(d.umbral_asistencia_medio))
    error('umbral_asistencia_alto', 'El umbral de asistencia ALTO debe ser menor que el MEDIO.')
  for (const c of ['umbral_promedio_alto', 'umbral_promedio_medio'])
    if (!esNumero(d[c]) || num(d[c]) > 20) error(c, 'El umbral de promedio es un número de 0 a 20.')
  if (num(d.umbral_promedio_alto) > num(d.umbral_promedio_medio))
    error('umbral_promedio_alto', 'El umbral de promedio ALTO no puede ser mayor que el MEDIO.')
  if (
    !/^\d+$/.test(d.umbral_pendientes_alto) ||
    num(d.umbral_pendientes_alto) < 1 ||
    num(d.umbral_pendientes_alto) > 20
  )
    error('umbral_pendientes_alto', 'Las entregas pendientes son un número entero de 1 a 20.')
  if (!/^\d+$/.test(d.dias_cierre_bimestre) || num(d.dias_cierre_bimestre) > 30)
    error('dias_cierre_bimestre', 'Los días para el cierre son un número entero de 0 a 30.')

  // De C a AD: cada rango empieza 0,01 después del anterior y AD termina en 20
  let esperado = 0
  for (const codigo of ['C', 'B', 'A', 'AD']) {
    const min = d[`min_${codigo}`]
    const max = d[`max_${codigo}`]
    const valido =
      esNumero(min) &&
      esNumero(max) &&
      Math.abs(num(min) - esperado) < 0.001 &&
      num(max) >= num(min) &&
      (codigo !== 'AD' || Math.abs(num(max) - 20) < 0.001)
    if (!valido) {
      error(
        'escala',
        'Los rangos deben ir de 0 a 20, de C a AD, sin huecos ni cruces (cada uno empieza 0,01 después del anterior).',
      )
      return
    }
    esperado = Math.round((num(max) + 0.01) * 100) / 100
  }
})

// --- Personas y matrículas (M7) ---

export const DOCUMENTOS = {
  DNI: [/^\d{8}$/, 'El DNI debe tener 8 dígitos.'],
  CE: [/^[A-Z0-9]{9,12}$/, 'El CE tiene de 9 a 12 caracteres: letras mayúsculas y números.'],
  PASAPORTE: [/^[A-Z0-9]{6,12}$/, 'El pasaporte tiene de 6 a 12 caracteres: letras mayúsculas y números.'],
} as const

const MENSAJE_DIRECCION = 'La dirección tiene de 5 a 200 caracteres: letras, números, espacios y . , # - / °'

/** Edad cumplida a hoy (o a la fecha indicada) */
export function edad(nacimiento: string, alDia = new Date().toISOString().slice(0, 10)) {
  const [a, m, d] = nacimiento.split('-').map(Number)
  const [ha, hm, hd] = alDia.split('-').map(Number)
  return ha - a - (hm < m || (hm === m && hd < d) ? 1 : 0)
}

/** Persona: docente (solo DNI, fecha opcional, 18 a 75 años) o estudiante (fecha y sexo obligatorios) */
export function esquemaPersona(estudiante: boolean) {
  return esquemaContacto
    .extend({
      tipo_documento: z.enum(['DNI', 'CE', 'PASAPORTE']),
      numero_documento: z.string().transform((v) => v.toUpperCase().replace(/\s/g, '')),
      nombres: nombre('nombres'),
      apellidos: nombre('apellidos'),
      fecha_nacimiento: z.string(),
      sexo: z.string(),
      direccion: z
        .string()
        .transform(limpio)
        .refine(
          (v) => v === '' || (v.length >= 5 && v.length <= 200 && /^[\p{L}\d .,#\-/°]+$/u.test(v)),
          MENSAJE_DIRECCION,
        ),
    })
    .superRefine((d, ctx) => {
      const error = (campo: string, message: string) => ctx.addIssue({ code: 'custom', path: [campo], message })
      if (!estudiante && d.tipo_documento !== 'DNI') error('tipo_documento', 'Los docentes se registran con su DNI.')
      if (!d.numero_documento) error('numero_documento', 'Ingresa el número de documento.')
      else if (!DOCUMENTOS[d.tipo_documento][0].test(d.numero_documento))
        error('numero_documento', DOCUMENTOS[d.tipo_documento][1])
      const hoy = new Date().toISOString().slice(0, 10)
      if (!d.fecha_nacimiento) {
        if (estudiante) error('fecha_nacimiento', 'Ingresa la fecha de nacimiento.')
      } else if (d.fecha_nacimiento >= hoy) {
        error('fecha_nacimiento', 'La fecha de nacimiento no puede ser de hoy ni futura.')
      } else if (!estudiante) {
        const anios = edad(d.fecha_nacimiento)
        if (anios < 18 || anios > 75)
          error(
            'fecha_nacimiento',
            `La edad (${anios} años) no es coherente: el personal tiene de 18 a 75 años (RN-23).`,
          )
      }
      if (estudiante && !d.sexo) error('sexo', 'Elige el sexo.')
    })
}

const textoDocente = (max: number, campo: string) =>
  z
    .string()
    .transform(limpio)
    .refine(
      (v) => v === '' || (v.length <= max && /^[\p{L}\d .,()-]+$/u.test(v)),
      `${campo} tiene hasta ${max} caracteres: letras, números, espacios y . , - ( )`,
    )

export const esquemaDocente = esquemaPersona(false)
  .and(
    z.object({
      titulo: textoDocente(120, 'El título'),
      especialidad: textoDocente(100, 'La especialidad'),
      fecha_inicio_contrato: z.string(),
      fecha_fin_contrato: z.string(),
      estado: z.string(),
      coordina_aula_id: z.string(),
    }),
  )
  .refine((d) => !d.fecha_fin_contrato || !d.fecha_inicio_contrato || d.fecha_fin_contrato >= d.fecha_inicio_contrato, {
    path: ['fecha_fin_contrato'],
    message: 'El fin del contrato no puede ser anterior a su inicio.',
  })

export type DatosDocente = z.input<typeof esquemaDocente>

export const esquemaEstudiante = esquemaPersona(true).and(
  z.object({ aula_id: z.string(), estado: z.string(), fecha_ingreso: z.string() }),
)

export type DatosEstudiante = z.input<typeof esquemaEstudiante>

export const esquemaApoderado = z.object({
  nombres: z
    .string()
    .transform(limpio)
    .pipe(
      z
        .string()
        .min(1, 'Ingresa el nombre del apoderado.')
        .refine(
          (v) => v.length >= 3 && v.length <= 150 && REGLAS.nombre.test(v),
          'El nombre tiene de 3 a 150 caracteres: letras, espacios, apóstrofo y guion.',
        ),
    ),
  parentesco: z.string().min(1, 'Elige el parentesco.'),
  celular: z
    .string()
    .refine((v) => v === '' || REGLAS.celular.test(v), 'El celular debe tener 9 dígitos y empezar con 9.'),
  correo: z
    .string()
    .transform(limpio)
    .refine((v) => v === '' || REGLAS.correo.test(v), 'El correo debe tener el formato usuario@dominio.com.'),
  es_principal: z.boolean(),
})

export type DatosApoderado = z.input<typeof esquemaApoderado>

export const esquemaMotivo = z.object({
  motivo: z
    .string()
    .transform(limpio)
    .refine((v) => v.length >= 10 && v.length <= 300, 'Explica el motivo en 10 a 300 caracteres.'),
})

/** Asistencia de una clase: la justificación (J) exige su motivo; las observaciones, hasta 300 caracteres */
export const esquemaAsistencia = z.object({
  tema: z
    .string()
    .transform(limpio)
    .refine((v) => v.length <= 200, 'El tema no debe tener más de 200 caracteres.'),
  registros: z.array(
    z
      .object({
        matricula_id: z.number(),
        estado: z.enum(['P', 'F', 'T', 'J'], 'El estado de asistencia debe ser P, F, T o J.'),
        observacion: z
          .string()
          .transform(limpio)
          .refine((v) => v.length <= 300, 'La observación no debe tener más de 300 caracteres.'),
      })
      .superRefine((r, ctx) => {
        if (r.estado === 'J' && !r.observacion)
          ctx.addIssue({ code: 'custom', path: ['observacion'], message: 'Escribe el motivo de la justificación.' })
      }),
  ),
})

export type DatosAsistencia = z.input<typeof esquemaAsistencia>

/** Evaluación de un curso (RF-38) */
export const esquemaEvaluacion = z
  .object({
    titulo: z
      .string()
      .transform(limpio)
      .refine((v) => v.length > 0, 'Escribe el título de la evaluación.')
      .refine(
        (v) => v.length === 0 || (v.length >= 3 && v.length <= 150),
        'El título debe tener de 3 a 150 caracteres.',
      ),
    tipo_evaluacion_id: z.string().min(1, 'Elige el tipo de evaluación.'),
    subperiodo_id: z.string().min(1, 'Elige el bimestre.'),
    fecha_asignacion: z.string().min(1, 'Ingresa una fecha de asignación válida.'),
    fecha_limite: z.string().min(1, 'Ingresa una fecha límite válida.'),
    peso: z.string().regex(/^([1-9]|10)$/, 'El peso es un número entero de 1 a 10.'),
    indicaciones: z.string().max(2000, 'Las indicaciones no deben tener más de 2000 caracteres.'),
    requiere_entrega: z.boolean(),
  })
  .refine((d) => !d.fecha_asignacion || !d.fecha_limite || d.fecha_limite >= d.fecha_asignacion, {
    path: ['fecha_limite'],
    message: 'La fecha límite no puede ser anterior a la de asignación.',
  })

export type DatosEvaluacion = z.input<typeof esquemaEvaluacion>

/** RN-08: de 0 a 20, hasta 2 decimales, con punto o coma. Devuelve el error o null */
export function errorNota(valor: string): string | null {
  const v = valor.trim()
  if (v === '') return null
  if (!/^\d{1,2}([.,]\d{1,2})?$/.test(v)) return 'La nota debe ser un número de 0 a 20, con hasta 2 decimales.'
  return Number(v.replace(',', '.')) > 20 ? 'La nota debe estar entre 0 y 20.' : null
}

/** Incidencia de un estudiante (RF-49) */
export const esquemaIncidencia = z.object({
  matricula_id: z.string().min(1, 'Elige al estudiante.'),
  tipo_incidencia_id: z.string().min(1, 'Elige el tipo de incidencia.'),
  gravedad: z.enum(['LEVE', 'MODERADA', 'GRAVE'], 'Elige la gravedad: leve, moderada o grave.'),
  fecha: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Ingresa una fecha válida.'),
  descripcion: z
    .string()
    .transform(limpio)
    .refine((v) => v.length > 0, 'Describe lo ocurrido.')
    .refine(
      (v) => v.length === 0 || (v.length >= 10 && v.length <= 500),
      'Describe lo ocurrido en 10 a 500 caracteres.',
    ),
})

export type DatosIncidencia = z.input<typeof esquemaIncidencia>

/** Intervención de una alerta (RF-55) */
export const esquemaIntervencion = z.object({
  tipo: z.string().min(1, 'Elige el tipo de intervención.'),
  descripcion: z
    .string()
    .transform(limpio)
    .refine((v) => v.length > 0, 'Describe la intervención.')
    .refine(
      (v) => v.length === 0 || (v.length >= 10 && v.length <= 500),
      'Describe la intervención en 10 a 500 caracteres.',
    ),
  fecha: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Ingresa una fecha válida.'),
  resultado: z
    .string()
    .transform(limpio)
    .refine((v) => v.length <= 300, 'El resultado no debe tener más de 300 caracteres.'),
})

export type DatosIntervencion = z.input<typeof esquemaIntervencion>

/** Justificación para cerrar, descartar o reasignar una alerta */
export const esquemaJustificacion = z.object({
  justificacion: z
    .string()
    .transform(limpio)
    .refine((v) => v.length > 0, 'Escribe la justificación.')
    .refine(
      (v) => v.length === 0 || (v.length >= 10 && v.length <= 400),
      'La justificación debe tener de 10 a 400 caracteres.',
    ),
})

export type DatosJustificacion = z.input<typeof esquemaJustificacion>
