<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Test Google Sign-In</title>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 40px auto; }
        pre { background: #f4f4f4; padding: 12px; white-space: pre-wrap; word-break: break-all; }
    </style>
</head>
<body>
    <h1>Prueba de login con Google</h1>

    @if (! config('services.google.client_id'))
        <p style="color:red">Falta GOOGLE_CLIENT_ID en el .env.</p>
    @else
        <div id="g_id_onload"
             data-client_id="{{ config('services.google.client_id') }}"
             data-callback="handleCredentialResponse">
        </div>
        <div class="g_id_signin" data-type="standard"></div>

        <h3>Respuesta del backend:</h3>
        <pre id="result">(todavía nada)</pre>

        <script>
            async function handleCredentialResponse(response) {
                document.getElementById('result').textContent = 'Verificando con el backend...';

                const res = await fetch('/api/auth/google', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ credential: response.credential }),
                });

                const data = await res.json();
                document.getElementById('result').textContent = JSON.stringify(data, null, 2);
            }
        </script>
    @endif
</body>
</html>
