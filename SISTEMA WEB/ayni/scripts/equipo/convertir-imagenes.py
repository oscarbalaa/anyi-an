"""Convierte las imágenes de "NUBE RECURSOS" a WebP livianas para el frontend.

Uso (solo el equipo, requiere Python con Pillow):
    python scripts/equipo/convertir-imagenes.py

Recorta los bordes transparentes y genera varios anchos para cargar solo lo necesario.
"""

from pathlib import Path
import shutil

from PIL import Image

RAIZ = Path(__file__).resolve().parents[2]
ORIGEN = RAIZ.parent.parent / "NUBE RECURSOS"
DESTINO = RAIZ / "frontend" / "src" / "assets" / "img"

IMAGENES = {
    "fondos/1.ayni-fondo-landing-colinas.png": ("colinas", [1672, 1100, 720]),
    "mascotas/2.piki_login.png": ("piki", [640, 360]),
    "mascotas/2.tampo_login.png": ("tambo", [640, 360]),
    "extras/2.ayni_cesped_long.png": ("cesped", [1600, 800]),
    "extras/03-borrde_login_estudiante.png": ("borde-estudiante", [1100, 600]),
    "extras/04-borde_login_docente.png": ("borde-docente", [1100, 600]),
    "extras/05-borde_login_admin.png": ("borde-admin", [1100, 600]),
}

LOGOS = {
    "logos/Recurso 3.svg": "logo-ayni.svg",
    "logos/logo_docentes.svg": "logo-docente.svg",
    "logos/logo_adminitrativo.svg": "logo-admin.svg",
}


def main() -> None:
    DESTINO.mkdir(parents=True, exist_ok=True)
    total_antes = total_despues = 0

    for origen, (nombre, anchos) in IMAGENES.items():
        ruta = ORIGEN / origen
        imagen = Image.open(ruta).convert("RGBA")
        imagen = imagen.crop(imagen.getchannel("A").getbbox())
        total_antes += ruta.stat().st_size
        for ancho in anchos:
            ancho = min(ancho, imagen.width)
            alto = round(imagen.height * ancho / imagen.width)
            salida = DESTINO / f"{nombre}-{ancho}.webp"
            imagen.resize((ancho, alto), Image.LANCZOS).save(salida, "WEBP", quality=82, method=6)
            total_despues += salida.stat().st_size
            print(f"{salida.name:32} {ancho}x{alto}  {salida.stat().st_size // 1024} KB")

    for origen, nombre in LOGOS.items():
        shutil.copyfile(ORIGEN / origen, DESTINO.parent / nombre)

    print(f"\nOriginales: {total_antes // 1024} KB  ->  WebP (todos los tamaños): {total_despues // 1024} KB")


if __name__ == "__main__":
    main()
