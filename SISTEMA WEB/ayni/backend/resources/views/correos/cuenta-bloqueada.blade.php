@extends('correos.base')

@section('contenido')
    <p>Hola, {{ $nombre }}:</p>
    <p>Tu cuenta <strong>{{ $usuario }}</strong> se bloqueó después de {{ $intentos }} intentos fallidos de ingreso ({{ $fecha }}).</p>
    @if ($temporal)
        <p>Podrás volver a intentarlo en {{ $minutos }} minutos.</p>
    @else
        <p>Para desbloquearla, comunícate con la administración del colegio.</p>
    @endif
    <p style="color:#55606a;font-size:13px">Si no fuiste tú, avisa a la administración: alguien podría estar intentando entrar con tu usuario.</p>
@endsection
