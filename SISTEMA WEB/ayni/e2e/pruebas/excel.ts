import { crc32 } from 'node:zlib'

// .xlsx mínimo (una hoja, texto en línea) para las pruebas de importación.
// Es un zip sin compresión: así no hace falta otra dependencia.

const xml = (texto: string) => texto.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

const columna = (i: number) => {
  let nombre = ''
  for (let n = i + 1; n > 0; n = Math.floor((n - 1) / 26)) nombre = String.fromCharCode(65 + ((n - 1) % 26)) + nombre
  return nombre
}

function hoja(filas: string[][]) {
  const cuerpo = filas
    .map(
      (fila, f) =>
        `<row r="${f + 1}">${fila
          .map((v, c) => (v === '' ? '' : `<c r="${columna(c)}${f + 1}" t="inlineStr"><is><t>${xml(v)}</t></is></c>`))
          .join('')}</row>`,
    )
    .join('')
  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>${cuerpo}</sheetData></worksheet>`
}

const ARCHIVOS = {
  '[Content_Types].xml':
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
  '_rels/.rels':
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
  'xl/workbook.xml':
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Estudiantes" sheetId="1" r:id="rId1"/></sheets></workbook>',
  'xl/_rels/workbook.xml.rels':
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
}

/** Arma el .xlsx con la primera fila como encabezados */
export function crearXlsx(filas: string[][]): Buffer {
  const archivos = { ...ARCHIVOS, 'xl/worksheets/sheet1.xml': hoja(filas) }
  const locales: Buffer[] = []
  const centrales: Buffer[] = []
  let desplazamiento = 0

  for (const [nombre, contenido] of Object.entries(archivos)) {
    const datos = Buffer.from(contenido, 'utf8')
    const ruta = Buffer.from(nombre, 'utf8')
    const crc = crc32(datos)

    const local = Buffer.alloc(30)
    local.writeUInt32LE(0x04034b50, 0)
    local.writeUInt16LE(20, 4)
    local.writeUInt16LE(0x0800, 6) // nombres en UTF-8
    local.writeUInt32LE(crc, 14)
    local.writeUInt32LE(datos.length, 18)
    local.writeUInt32LE(datos.length, 22)
    local.writeUInt16LE(ruta.length, 26)
    locales.push(local, ruta, datos)

    const central = Buffer.alloc(46)
    central.writeUInt32LE(0x02014b50, 0)
    central.writeUInt16LE(20, 4)
    central.writeUInt16LE(20, 6)
    central.writeUInt16LE(0x0800, 8)
    central.writeUInt32LE(crc, 16)
    central.writeUInt32LE(datos.length, 20)
    central.writeUInt32LE(datos.length, 24)
    central.writeUInt16LE(ruta.length, 28)
    central.writeUInt32LE(desplazamiento, 42)
    centrales.push(central, ruta)

    desplazamiento += 30 + ruta.length + datos.length
  }

  const directorio = Buffer.concat(centrales)
  const fin = Buffer.alloc(22)
  fin.writeUInt32LE(0x06054b50, 0)
  fin.writeUInt16LE(Object.keys(archivos).length, 8)
  fin.writeUInt16LE(Object.keys(archivos).length, 10)
  fin.writeUInt32LE(directorio.length, 12)
  fin.writeUInt32LE(desplazamiento, 16)
  return Buffer.concat([...locales, directorio, fin])
}

export const COLUMNAS = [
  'tipo_documento',
  'numero_documento',
  'nombres',
  'apellidos',
  'fecha_nacimiento',
  'sexo',
  'nivel',
  'grado',
  'seccion',
  'correo',
  'celular',
  'direccion',
  'codigo',
]
