# Diagramas del informe

Fuentes de las figuras del informe de la Unidad 1 que no salen de las capturas del sistema.

| Archivo | Figura |
|---|---|
| `cu.html` | Diagrama de casos de uso |
| `flujo-actual.html`, `flujo-propuesto.html` | Flujo del proceso actual y del propuesto (carriles) |
| `mapa.html` | Mapa del sitio por perfil |
| `arquitectura.html` | Arquitectura del sistema |
| `despliegue.html` | Diagrama de despliegue |
| `gcp.html` | Arquitectura objetivo en Google Cloud (plan de migración) |
| `modelo-logico-*.mmd` | Modelo lógico por módulo (Mermaid), tomado de `docs/bd/diagrama-er.md` solo con claves |

Para convertir una figura en PNG (usa el Chromium de Playwright):

```bash
node render.cjs cu.html cu.png 1540
```

Los `.mmd` se abren en GitHub o VS Code, que los muestran como diagrama.
