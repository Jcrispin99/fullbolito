<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#071a13">
    <title>{{ ucfirst((string) config('app.name', 'Fullbolito')) }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite('resources/js/tenant/main.ts')
</head>
<body>
    <div id="app"></div>
</body>
</html>
