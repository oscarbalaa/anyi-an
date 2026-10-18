@extends('correos.base')

@section('contenido')
    <p>Hola, {{ $nombre }}:</p>
    <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta <strong>{{ $usuario }}</strong>.</p>
    <p style="text-align:center;margin:24px 0">
        <a href="{{ $enlace }}" style="background:#b3522f;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 24px;border-radius:12px;display:inline-block">Restablecer mi contraseña</a>
    </p>
    <p>El enlace sirve una sola vez y vence en {{ $minutos }} minutos.</p>
    <p style="color:#55606a;font-size:13px">Si no pediste este cambio, ignora este mensaje: tu contraseña sigue siendo la misma.</p>
    <p style="color:#55606a;font-size:12px;word-break:break-all">Si el botón no funciona, copia este enlace en tu navegador:<br>{{ $enlace }}</p>
@endsection
