@php($logout = $logout ?? false)
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $logout ? 'Cierre de sesión' : 'Inicio de sesión con SSO' }} · Escenia</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #061627; color: #e6edf5; font: 16px/1.5 system-ui, sans-serif; }
        main { max-width: 26rem; padding: 2rem; text-align: center; }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
        p { margin: 0; color: #9fb0c3; }
    </style>
</head>
<body>
<main>
    @if ($invalid ?? false)
        <h1>Solicitud no válida</h1>
        <p>La solicitud de cierre de sesión no es válida y se ignoró.</p>
    @elseif ($logout && $ok)
        <h1>Sesión cerrada</h1>
        <p>También se cerró la sesión en tu proveedor de identidad.</p>
    @elseif ($logout)
        <h1>Sesión cerrada en Escenia</h1>
        <p>No se pudo confirmar el cierre en tu proveedor de identidad.</p>
    @elseif ($ok)
        <h1>Sesión iniciada</h1>
        <p>Ya puedes volver a Escenia.</p>
    @else
        <h1>No se pudo iniciar sesión con SSO</h1>
        <p>El inicio de sesión expiró o no es válido. Vuelve a intentarlo desde Escenia.</p>
    @endif
</main>
</body>
</html>
