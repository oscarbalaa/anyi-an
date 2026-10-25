const { chromium } = require('playwright');
const path = require('node:path');
const A = {
 'Administrador': ['CU-01 Iniciar sesión','CU-04 Gestionar usuarios','CU-06 Configurar periodos, bimestres y colegio','CU-07 Configurar grados, secciones, aulas y asignaturas','CU-08 Asignar carga, coordinadores y horarios','CU-09 Gestionar docentes','CU-10 Gestionar estudiantes y apoderados','CU-11 Importar estudiantes desde Excel','CU-12 Gestionar matrículas','CU-14 Corregir y justificar asistencia','CU-20 Cerrar bimestre','CU-22 Registrar y consultar incidencias','CU-25 Atender alerta estudiantil','CU-26 Consultar panel de riesgo','CU-27 Consultar inicio y ficha 360°','CU-28 Generar reportes'],
 'Docente': ['CU-01 Iniciar sesión','CU-13 Registrar asistencia','CU-16 Gestionar evaluaciones y material','CU-18 Registrar notas','CU-19 Publicar promedios del bimestre','CU-22 Registrar y consultar incidencias','CU-25 Atender alerta estudiantil','CU-27 Consultar inicio, mis estudiantes y ficha 360°','CU-28 Exportar la asistencia de sus aulas'],
 'Coordinador académico': ['CU-01 Iniciar sesión','CU-25 Atender o reasignar alertas de su ámbito','CU-26 Consultar el panel de coordinación','CU-27 Consultar ficha 360° de su ámbito','(además, todo lo del docente)'],
 'Estudiante': ['CU-01 Iniciar sesión','CU-02 Recuperar contraseña','CU-05 Gestionar mi perfil','CU-15 Consultar mi asistencia','CU-17 Entregar actividad','CU-21 Consultar mis notas y horario','CU-23 Revisar notificaciones','CU-28 Descargar mi boleta'],
 'Visitante y sistema': ['CU-00 Visitar la landing y elegir acceso (visitante)','CU-20 Cerrar bimestre en su fecha (sistema)','CU-24 Calcular riesgo y generar alertas (sistema)','CU-23 Crear avisos por fecha (sistema)'],
};
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 820, height: 600 }, deviceScaleFactor: 2 });
  let i = 0;
  for (const [a, c] of Object.entries(A)) {
    i++;
    await p.goto('file://' + path.resolve('cu-actor.html') + '?a=' + encodeURIComponent(a) + '&c=' + encodeURIComponent(JSON.stringify(c)));
    await (await p.$('#lienzo')).screenshot({ path: `cu-actor${i}.png` });
  }
  await b.close();
})();
