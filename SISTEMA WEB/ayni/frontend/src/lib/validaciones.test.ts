import { describe, expect, it } from 'vitest'
import {
  edad,
  esquemaApoderado,
  esquemaAula,
  esquemaBimestre,
  esquemaBloque,
  esquemaColegio,
  esquemaContacto,
  esquemaDocente,
  esquemaEstudiante,
  esquemaMotivo,
  esquemaPeriodo,
  esquemaNuevaContrasena,
  esquemaRecuperar,
  esquemasIngreso,
  esquemaUsuarioAdmin,
  filtros,
  requisitosContrasena,
} from './validaciones'

const datos = (usuario: string, contrasena = 'Ayni#2026') => ({
  usuario,
  contrasena,
  captcha: 'K7MP2',
  recordarme: false,
})
const errorDe = (r: { success: boolean; error?: { issues: { message: string }[] } }) => r.error?.issues[0]?.message

describe('ingreso (docs/validaciones.md)', () => {
  it.each(['E-2026-001', 'E-2019-123'])('acepta el código de estudiante %s', (codigo) => {
    expect(esquemasIngreso.estudiante.safeParse(datos(codigo)).success).toBe(true)
  })

  it.each(['E2026001', 'E-26-10', 'e-2026-001', 'E-2026-1234', ''])('rechaza el código %s', (codigo) => {
    expect(esquemasIngreso.estudiante.safeParse(datos(codigo)).success).toBe(false)
  })

  it.each(['41234567', '00000001'])('acepta el DNI %s', (dni) => {
    expect(esquemasIngreso.docente.safeParse(datos(dni)).success).toBe(true)
  })

  it.each(['4123456', '412345678', '4123456a', ' 41234567'])('rechaza el DNI "%s"', (dni) => {
    expect(errorDe(esquemasIngreso.docente.safeParse(datos(dni)))).toBe('El DNI debe tener 8 dígitos.')
  })

  it.each(['cmedina', 'c.medina', 'admin_2'])('acepta el usuario administrativo %s', (u) => {
    expect(esquemasIngreso.admin.safeParse(datos(u)).success).toBe(true)
  })

  it.each(['1admin', 'c medina', 'adm', 'a'.repeat(31), 'c-medina'])('rechaza el usuario administrativo "%s"', (u) => {
    expect(esquemasIngreso.admin.safeParse(datos(u)).success).toBe(false)
  })

  it('exige la contraseña y limita su largo', () => {
    expect(errorDe(esquemasIngreso.docente.safeParse(datos('41234567', '')))).toBe('Ingresa tu contraseña.')
    expect(esquemasIngreso.docente.safeParse(datos('41234567', 'x'.repeat(65))).success).toBe(false)
  })
})

describe('filtros de escritura', () => {
  it('deja solo dígitos en el DNI', () => {
    expect(filtros.dni('41a2b3 45-678')).toBe('412345678')
    expect(filtros.dni('abc')).toBe('')
  })

  it('pasa el código a mayúsculas y quita caracteres raros', () => {
    expect(filtros.codigoEstudiante('e-2026-001 ')).toBe('E-2026-001')
    expect(filtros.codigoEstudiante('e_2026/001')).toBe('E2026001')
  })

  it('quita espacios y símbolos del usuario administrativo', () => {
    expect(filtros.usuarioAdmin('c medina!')).toBe('cmedina')
  })
})

describe('captcha (RN-18)', () => {
  it('pasa a mayúsculas y deja solo letras y números', () => {
    expect(filtros.captcha('k7-m p2')).toBe('K7MP2')
  })

  it('exige los 5 caracteres de la imagen', () => {
    expect(errorDe(esquemasIngreso.docente.safeParse({ ...datos('41234567'), captcha: '' }))).toBe(
      'Escribe el código de la imagen.',
    )
    expect(errorDe(esquemasIngreso.docente.safeParse({ ...datos('41234567'), captcha: 'K7M' }))).toBe(
      'El código de la imagen tiene 5 caracteres.',
    )
  })
})

describe('recuperación (RF-06)', () => {
  it('usa el formato de usuario de cada acceso', () => {
    expect(esquemaRecuperar('docente').safeParse({ usuario: '41234567', captcha: 'K7MP2' }).success).toBe(true)
    expect(errorDe(esquemaRecuperar('estudiante').safeParse({ usuario: '41234567', captcha: 'K7MP2' }))).toMatch(
      'E-AAAA-NNN',
    )
  })
})

describe('nueva contraseña (RN-16)', () => {
  const probar = (
    rol: Parameters<typeof esquemaNuevaContrasena>[0],
    clave: string,
    confirmacion = clave,
    usuario = '',
  ) => errorDe(esquemaNuevaContrasena(rol, usuario).safeParse({ contrasena: clave, confirmacion }))

  it.each([
    ['docente', 'Corta#1', 'La nueva contraseña debe tener al menos 8 caracteres.'],
    ['docente', 'ayni#2026', 'La nueva contraseña debe contener al menos una mayúscula y una minúscula.'],
    ['admin', 'Ayni#abcd', 'La nueva contraseña debe contener al menos un número.'],
    ['docente', 'Ayni2026x', 'La nueva contraseña debe contener al menos un símbolo.'],
    ['estudiante', '12345678', 'La nueva contraseña debe contener al menos una letra.'],
    ['estudiante', 'pikipiki', 'La nueva contraseña debe contener al menos un número.'],
  ] as const)('%s: rechaza "%s"', (rol, clave, mensaje) => {
    expect(probar(rol, clave)).toBe(mensaje)
  })

  it.each([
    ['docente', 'Nueva#2027'],
    ['admin', 'Ayni$2026!'],
    ['estudiante', 'piki2027'],
  ] as const)('%s: acepta "%s"', (rol, clave) => {
    expect(probar(rol, clave)).toBeUndefined()
  })

  it('pide la confirmación igual y que no repita el usuario', () => {
    expect(probar('estudiante', 'piki2027', 'piki2028')).toBe('La confirmación de la nueva contraseña no coincide.')
    expect(probar('estudiante', 'e-2026-001', 'e-2026-001', 'E-2026-001')).toBe(
      'La contraseña no puede ser igual a tu usuario.',
    )
  })

  it('sin rol conocido solo revisa el largo (el servidor revisa el resto)', () => {
    expect(requisitosContrasena(null)).toHaveLength(1)
    expect(probar(null, 'abcdefgh')).toBeUndefined()
  })
})

describe('contacto y cuenta administrativa (M5)', () => {
  const admin = (cambios = {}) => ({
    nombres: '  María   José ',
    apellidos: "D'Angelo Pérez-Gómez",
    dni: '45678912',
    usuario: 'p.romero',
    cargo: 'Secretaría académica',
    correo: '',
    celular: '',
    ...cambios,
  })

  it.each([
    ['dramirez@mail', 'El correo debe tener el formato usuario@dominio.com.'],
    ['dramirez.com', 'El correo debe tener el formato usuario@dominio.com.'],
    ['a@b.c', 'El correo debe tener el formato usuario@dominio.com.'],
  ])('rechaza el correo "%s"', (correo, mensaje) => {
    expect(errorDe(esquemaContacto.safeParse({ correo, celular: '' }))).toBe(mensaje)
  })

  it('acepta correo y celular vacíos o válidos', () => {
    expect(esquemaContacto.safeParse({ correo: '', celular: '' }).success).toBe(true)
    expect(esquemaContacto.safeParse({ correo: ' ana@ayni.edu.pe ', celular: '987654321' }).success).toBe(true)
  })

  it.each(['87654321', '9876543210', '98765432a'])('rechaza el celular "%s"', (celular) => {
    expect(errorDe(esquemaContacto.safeParse({ correo: '', celular }))).toBe(
      'El celular debe tener 9 dígitos y empezar con 9.',
    )
  })

  it('acepta nombres con tildes, apóstrofo y guion, y limpia los espacios', () => {
    const r = esquemaUsuarioAdmin.safeParse(admin())
    expect(r.success).toBe(true)
    expect(r.data?.nombres).toBe('María José')
  })

  it.each([
    [{ nombres: 'Juan2' }, 'Solo se permiten letras, espacios, apóstrofo y guion.'],
    [{ nombres: 'J' }, 'Los nombres deben tener al menos 2 letras.'],
    [{ dni: '4567891' }, 'El DNI debe tener 8 dígitos.'],
    [
      { usuario: '1admin' },
      'El usuario tiene de 4 a 30 caracteres, empieza con letra y solo usa letras, números, punto o guion bajo.',
    ],
    [{ cargo: '<b>' }, 'El cargo solo puede tener letras, números, espacios y . , -'],
  ])('rechaza %o', (cambios, mensaje) => {
    expect(errorDe(esquemaUsuarioAdmin.safeParse(admin(cambios)))).toBe(mensaje)
  })

  it('los filtros dejan solo dígitos en el celular y solo letras en los nombres', () => {
    expect(filtros.celular('987-654 321')).toBe('987654321')
    expect(filtros.nombre("Ana2 D'Angelo-Pérez!")).toBe("Ana D'Angelo-Pérez")
  })
})

describe('estructura académica (M6)', () => {
  it('periodo: año de 2020 a 2100 y fin posterior al inicio', () => {
    const p = (nombre: string, fin = '2027-12-17') =>
      esquemaPeriodo.safeParse({ nombre, fecha_inicio: '2027-03-01', fecha_fin: fin })
    expect(p('2027').success).toBe(true)
    expect(errorDe(p('2019'))).toBe('El periodo es un año de 4 dígitos entre 2020 y 2100.')
    expect(errorDe(p('2026-I'))).toBe('El periodo es un año de 4 dígitos entre 2020 y 2100.')
    expect(errorDe(p('2027', '2027-01-01'))).toBe('La fecha de fin debe ser posterior a la de inicio.')
  })

  it('bimestre: el cierre no puede ser anterior al inicio (RN-02)', () => {
    const b = (cierre: string) =>
      esquemaBimestre.safeParse({ fecha_inicio: '2027-03-01', fecha_fin: '2027-05-07', fecha_cierre_notas: cierre })
    expect(b('').success).toBe(true)
    expect(b('2027-05-14').success).toBe(true)
    expect(errorDe(b('2027-02-28'))).toBe(
      'La fecha de cierre de notas no puede ser anterior al inicio del bimestre (RN-02).',
    )
  })

  it.each([
    [{ seccion: 'a' }, 'La sección es "Única" o de 1 a 3 letras mayúsculas (A, B, C…).'],
    [{ seccion: '1' }, 'La sección es "Única" o de 1 a 3 letras mayúsculas (A, B, C…).'],
    [{ capacidad: '0' }, 'La capacidad es un número entero de 1 a 60.'],
    [{ capacidad: '61' }, 'La capacidad es un número entero de 1 a 60.'],
    [{ ambiente: '<b>' }, 'El ambiente solo puede tener letras, números, espacios, punto y guion.'],
  ])('aula: rechaza %o', (cambios, mensaje) => {
    const aula = {
      grado_id: '7',
      seccion: 'Única',
      capacidad: '30',
      tutor_docente_id: '',
      coordinador_docente_id: '',
      ambiente: 'Lab. 1',
      ...cambios,
    }
    expect(errorDe(esquemaAula.safeParse(aula))).toBe(mensaje)
  })

  it('bloque: de 06:00 a 20:00 y fin después del inicio', () => {
    const b = (inicio: string, fin: string) =>
      esquemaBloque.safeParse({ curso_id: '1', dia_semana: '1', hora_inicio: inicio, hora_fin: fin, ambiente: '' })
    expect(b('08:00', '09:30').success).toBe(true)
    expect(errorDe(b('05:30', '07:00'))).toBe('La hora debe estar entre 06:00 y 20:00.')
    expect(errorDe(b('09:30', '08:00'))).toBe('La hora de fin debe ser posterior a la de inicio.')
  })

  it('colegio: la escala va de 0 a 20 sin huecos ni cruces (RN-11)', () => {
    const base = {
      nombre: 'IEP Ayni',
      codigo_modular: '',
      ugel: '',
      direccion: '',
      telefono: '',
      correo: '',
      nota_aprobatoria: '11',
      umbral_asistencia_alto: '85',
      umbral_asistencia_medio: '90',
      umbral_promedio_alto: '11',
      umbral_promedio_medio: '12',
      umbral_pendientes_alto: '3',
      dias_cierre_bimestre: '7',
      min_C: '0',
      max_C: '10.99',
      min_B: '11',
      max_B: '13.99',
      min_A: '14',
      max_A: '17.99',
      min_AD: '18',
      max_AD: '20',
    }
    expect(esquemaColegio.safeParse(base).success).toBe(true)
    expect(esquemaColegio.safeParse({ ...base, max_B: '13,99' }).success).toBe(true)
    expect(errorDe(esquemaColegio.safeParse({ ...base, min_A: '15' }))).toMatch('Los rangos deben ir de 0 a 20')
    expect(errorDe(esquemaColegio.safeParse({ ...base, max_AD: '19.99' }))).toMatch('Los rangos deben ir de 0 a 20')
    expect(errorDe(esquemaColegio.safeParse({ ...base, umbral_promedio_alto: '13' }))).toBe(
      'El umbral de promedio ALTO no puede ser mayor que el MEDIO.',
    )
  })
})

describe('personas y matrículas (M7)', () => {
  const persona = {
    tipo_documento: 'DNI' as const,
    numero_documento: '78123456',
    nombres: 'diego  alonso',
    apellidos: 'Ramírez Torres',
    fecha_nacimiento: '2012-05-14',
    sexo: 'M',
    correo: '',
    celular: '',
    direccion: '',
  }
  const estudiante = { ...persona, aula_id: '', estado: 'ACTIVO', fecha_ingreso: '' }
  const docente = {
    ...persona,
    fecha_nacimiento: '1985-03-10',
    titulo: '',
    especialidad: '',
    fecha_inicio_contrato: '',
    fecha_fin_contrato: '',
    estado: 'ACTIVO',
    coordina_aula_id: '',
  }

  it('calcula la edad cumplida', () => {
    expect(edad('2012-05-14', '2026-05-13')).toBe(13)
    expect(edad('2012-05-14', '2026-05-14')).toBe(14)
  })

  it('estudiante: documento según su tipo, fecha y sexo obligatorios', () => {
    expect(esquemaEstudiante.safeParse(estudiante).success).toBe(true)
    expect(errorDe(esquemaEstudiante.safeParse({ ...estudiante, numero_documento: '7812345' }))).toBe(
      'El DNI debe tener 8 dígitos.',
    )
    expect(
      esquemaEstudiante.safeParse({ ...estudiante, tipo_documento: 'CE', numero_documento: 'ab 12345 67' }).success,
    ).toBe(true)
    expect(
      errorDe(esquemaEstudiante.safeParse({ ...estudiante, tipo_documento: 'PASAPORTE', numero_documento: 'A1' })),
    ).toBe('El pasaporte tiene de 6 a 12 caracteres: letras mayúsculas y números.')
    expect(errorDe(esquemaEstudiante.safeParse({ ...estudiante, fecha_nacimiento: '' }))).toBe(
      'Ingresa la fecha de nacimiento.',
    )
    expect(errorDe(esquemaEstudiante.safeParse({ ...estudiante, sexo: '' }))).toBe('Elige el sexo.')
    expect(errorDe(esquemaEstudiante.safeParse({ ...estudiante, fecha_nacimiento: '2999-01-01' }))).toBe(
      'La fecha de nacimiento no puede ser de hoy ni futura.',
    )
    expect(errorDe(esquemaEstudiante.safeParse({ ...estudiante, direccion: 'Av' }))).toMatch(
      'La dirección tiene de 5 a 200',
    )
  })

  it('docente: solo DNI, de 18 a 75 años y contrato coherente', () => {
    expect(esquemaDocente.safeParse(docente).success).toBe(true)
    expect(esquemaDocente.safeParse({ ...docente, fecha_nacimiento: '', sexo: '' }).success).toBe(true)
    expect(errorDe(esquemaDocente.safeParse({ ...docente, tipo_documento: 'CE', numero_documento: 'AB1234567' }))).toBe(
      'Los docentes se registran con su DNI.',
    )
    expect(errorDe(esquemaDocente.safeParse({ ...docente, fecha_nacimiento: '2015-01-01' }))).toMatch(
      /no es coherente: el personal tiene de 18 a 75 años \(RN-23\)/,
    )
    expect(
      errorDe(
        esquemaDocente.safeParse({ ...docente, fecha_inicio_contrato: '2026-03-01', fecha_fin_contrato: '2026-02-01' }),
      ),
    ).toBe('El fin del contrato no puede ser anterior a su inicio.')
    expect(esquemaDocente.safeParse({ ...docente, titulo: 'Licenciado(a) en Educación' }).success).toBe(true)
    expect(errorDe(esquemaDocente.safeParse({ ...docente, especialidad: 'Matemática<script>' }))).toMatch(
      'La especialidad tiene hasta 100 caracteres',
    )
  })

  it('apoderado: nombre, parentesco y contacto opcional', () => {
    const base = { nombres: 'Rosa Torres', parentesco: 'Madre', celular: '', correo: '', es_principal: true }
    expect(esquemaApoderado.safeParse(base).success).toBe(true)
    expect(errorDe(esquemaApoderado.safeParse({ ...base, nombres: '  ' }))).toBe('Ingresa el nombre del apoderado.')
    expect(errorDe(esquemaApoderado.safeParse({ ...base, parentesco: '' }))).toBe('Elige el parentesco.')
    expect(errorDe(esquemaApoderado.safeParse({ ...base, celular: '812345678' }))).toBe(
      'El celular debe tener 9 dígitos y empezar con 9.',
    )
  })

  it('motivo de retiro o anulación: de 10 a 300 caracteres', () => {
    expect(esquemaMotivo.safeParse({ motivo: 'Traslado a otra ciudad' }).success).toBe(true)
    expect(errorDe(esquemaMotivo.safeParse({ motivo: '   corto   ' }))).toBe(
      'Explica el motivo en 10 a 300 caracteres.',
    )
  })
})
