<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ucfirst((string) config('app.name', 'Fullbolito')) }}</title>
    @vite('resources/js/tenant/main.ts')
</head>
<body>
    <div id="app"></div>
</body>
</html>
