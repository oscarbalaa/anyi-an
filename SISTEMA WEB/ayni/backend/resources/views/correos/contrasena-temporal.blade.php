@extends('correos.base')

@section('contenido')
    <p>Hola, {{ $nombre }}:</p>
    <p>La administración del colegio generó una contraseña temporal para tu cuenta <strong>{{ $usuario }}</strong>:</p>
    <p style="font-size:22px;font-weight:700;letter-spacing:2px;background:#f4ede6;border-radius:12px;padding:12px 16px;display:inline-block">{{ $clave }}</p>
    <p>Al ingresar, Ayni te pedirá crear una contraseña nueva.</p>
    <p style="margin:24px 0">
        <a href="{{ $enlace }}" style="background:#b3522f;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 24px;border-radius:12px;display:inline-block">Ingresar a Ayni</a>
    </p>
    <p style="color:#55606a;font-size:13px">Si no pediste este cambio, avisa a la administración.</p>
@endsection
