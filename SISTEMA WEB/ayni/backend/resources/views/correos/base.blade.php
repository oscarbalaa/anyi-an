<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
</head>
<body style="margin:0;background:#fcf7f0;font-family:'Segoe UI',Arial,sans-serif;color:#263238">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:16px;border:1px solid #ebe2d6">
                    <tr>
                        <td style="padding:24px 28px 8px">
                            <p style="margin:0;font-size:28px;font-weight:900;color:#c65d3a">Ayni</p>
                            <p style="margin:4px 0 0;color:#55606a;font-size:13px">{{ config('ayni.colegio') }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 28px;font-size:15px;line-height:1.6">
                            <h1 style="font-size:20px;margin:12px 0">{{ $titulo }}</h1>
                            @yield('contenido')
                        </td>
                    </tr>
                </table>
                <p style="color:#55606a;font-size:12px;margin-top:16px">Este es un mensaje automático de AYNI. No respondas a este correo.</p>
            </td>
        </tr>
    </table>
</body>
</html>
