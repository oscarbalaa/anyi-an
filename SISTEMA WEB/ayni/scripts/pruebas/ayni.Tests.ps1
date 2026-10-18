# Pruebas del panel de control (Pester 3.4, incluido en Windows)
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
. (Join-Path (Split-Path -Parent $here) 'ayni.ps1')

Describe 'Puertos' {
    It 'detecta un puerto ocupado por otro programa' {
        $ocupante = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, 0)
        $ocupante.Start()
        $puerto = $ocupante.LocalEndpoint.Port
        try { Test-PuertoLibre $puerto | Should Be $false }
        finally { $ocupante.Stop() }
    }

    It 'reconoce un puerto libre' {
        $temporal = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, 0)
        $temporal.Start(); $puerto = $temporal.LocalEndpoint.Port; $temporal.Stop()
        Test-PuertoLibre $puerto | Should Be $true
    }

    It 'propone el siguiente puerto libre' {
        $ocupante = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Any, 0)
        $ocupante.Start()
        $puerto = $ocupante.LocalEndpoint.Port
        try {
            $alternativo = Buscar-PuertoLibre ($puerto - 1)
            $alternativo | Should Not Be $puerto
            $alternativo | Should BeGreaterThan ($puerto - 1)
        } finally { $ocupante.Stop() }
    }
}

Describe 'Docker' {
    It 'avisa que Docker no está instalado si no lo encuentra' {
        Mock Get-Command { $null } -ParameterFilter { $Name -eq 'docker' }
        Mock Test-Path { $false }
        Test-DockerInstalado | Should Be $false
    }

    It 'no considera Docker corriendo si no está instalado' {
        Mock Test-DockerInstalado { $false }
        Test-DockerCorriendo | Should Be $false
    }

    It 'muestra NO INSTALADO cuando no hay Docker ni configuración' {
        Mock Test-DockerCorriendo { $false }
        Mock Test-Path { $false } -ParameterFilter { $LiteralPath -like '*.env' }
        Estado-Ayni | Should Be 'NO INSTALADO'
    }
}

Describe 'Configuración local (.env)' {
    $archivo = Join-Path $env:TEMP "ayni-prueba-$([guid]::NewGuid()).env"

    It 'lee los valores' {
        Set-Content -LiteralPath $archivo -Value "AYNI_PUERTO_WEB=5173`nAYNI_BD_CLAVE=abc" -Encoding UTF8
        $cfg = Leer-Env $archivo
        $cfg['AYNI_PUERTO_WEB'] | Should Be '5173'
        $cfg['AYNI_BD_CLAVE'] | Should Be 'abc'
    }

    It 'cambia un valor sin tocar los demás' {
        Escribir-ValorEnv 'AYNI_PUERTO_WEB' '5174' $archivo
        $cfg = Leer-Env $archivo
        $cfg['AYNI_PUERTO_WEB'] | Should Be '5174'
        $cfg['AYNI_BD_CLAVE'] | Should Be 'abc'
    }

    It 'agrega un valor que no existía' {
        Escribir-ValorEnv 'AYNI_APP_KEY' 'base64:xyz' $archivo
        (Leer-Env $archivo)['AYNI_APP_KEY'] | Should Be 'base64:xyz'
    }

    It 'genera una clave de aplicación válida para Laravel' {
        $clave = Nueva-ClaveApp
        $clave | Should Match '^base64:'
        [Convert]::FromBase64String($clave.Substring(7)).Length | Should Be 32
        Nueva-ClaveApp | Should Not Be $clave
    }

    It 'genera un secreto E2E aleatorio de 40 caracteres hexadecimales' {
        $secreto = Nuevo-SecretoE2E
        $secreto | Should Match '^[0-9a-f]{40}$'
        Nuevo-SecretoE2E | Should Not Be $secreto
    }

    Remove-Item -LiteralPath $archivo -ErrorAction SilentlyContinue
}

Describe 'Reporte de pruebas' {
    It 'resume un archivo JUnit' {
        $xml = Join-Path $env:TEMP "ayni-junit-$([guid]::NewGuid()).xml"
        Set-Content -LiteralPath $xml -Encoding UTF8 -Value '<testsuites><testsuite tests="3"><testsuite tests="3"><testcase name="a"/><testcase name="b"><failure/></testcase><testcase name="c"/></testsuite></testsuite></testsuites>'
        $r = Resumen-Junit $xml
        $r.Total | Should Be 3
        $r.Fallidas | Should Be 1
        Remove-Item -LiteralPath $xml
    }

    It 'genera un reporte HTML con el resultado general' {
        $html = Reporte-Html @([pscustomobject]@{ Nombre = 'Backend'; Ok = $true; Resumen = [pscustomobject]@{ Total = 3; Fallidas = 0; Omitidas = 0 } })
        $html | Should Match 'Todas las pruebas pasaron'
        $html | Should Match '<td>Backend</td><td>3</td><td>3</td><td>0</td>'
    }

    It 'incluye los puntajes de Lighthouse cuando existen' {
        $lh = @([pscustomobject]@{ pagina = '/'; dispositivo = 'celular'; performance = 95; accessibility = 100; 'best-practices' = 96; seo = 82 })
        $html = Reporte-Html @([pscustomobject]@{ Nombre = 'E2E'; Ok = $true; Resumen = $null }) $lh
        $html | Should Match 'Lighthouse'
        $html | Should Match '<td class="ok">95</td><td class="ok">100</td>'
        $html | Should Match '<td class="mal">82</td>'
    }
}

Describe 'Credenciales' {
    It 'escribe las direcciones con los puertos configurados' {
        $original = $script:Inicio
        $script:Inicio = Join-Path $env:TEMP "ayni-inicio-$([guid]::NewGuid())"
        New-Item -ItemType Directory -Path $script:Inicio | Out-Null
        Mock Puertos { @{ AYNI_PUERTO_WEB = 5199; AYNI_PUERTO_PMA = 8099; AYNI_PUERTO_CORREO = 8098; AYNI_PUERTO_BD = 3399 } }
        try {
            Actualizar-Credenciales
            $texto = Get-Content -LiteralPath (Join-Path $script:Inicio 'CREDENCIALES.txt') -Raw -Encoding UTF8
            $texto | Should Match 'http://localhost:5199'
            $texto | Should Match 'http://localhost:8099'
            $texto | Should Match 'puerto 3399'
            $texto | Should Match 'cmedina\s+Ayni#2026'
            $texto | Should Match 'E-2026-001\s+piki2026'
        } finally {
            Remove-Item -LiteralPath $script:Inicio -Recurse -Force
            $script:Inicio = $original
        }
    }
}
