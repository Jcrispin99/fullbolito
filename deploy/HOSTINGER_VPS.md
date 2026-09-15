# Despliegue en Hostinger VPS

Esta guía asume Ubuntu 24.04/22.04 con Nginx o CloudPanel, PHP 8.3, MySQL y
Supervisor. El `Document Root` del dominio debe apuntar a `public/`.

## 1. Dependencias del servidor

Verifica la versión y la ruta de PHP:

```bash
php -v
command -v php
```

El proyecto requiere PHP 8.3 y, para facturación/archivos, las extensiones
`curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `mysql`, `openssl`, `soap`,
`xml` y `zip`.

Instala Supervisor si la plantilla elegida no lo incluye:

```bash
sudo apt update
sudo apt install supervisor
sudo systemctl enable --now supervisor
```

## 2. Variables de producción

Además de credenciales de base de datos, correo, pasarela y Greenter, confirma:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com
CENTRAL_DOMAINS=tu-dominio.com
QUEUE_CONNECTION=database
DB_QUEUE_CONNECTION=mysql
DB_QUEUE_RETRY_AFTER=90
SUNAT_DEDICATED_QUEUES_ENABLED=false
CACHE_STORE=database
```

Mantén `SUNAT_DEDICATED_QUEUES_ENABLED=false` hasta que exista al menos un
tenant Enterprise y su worker dedicado ya esté escuchando la cola asignada.

## 3. Primera instalación

Ejecuta desde el directorio del proyecto:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan tenants:migrate
php artisan storage:link
php artisan optimize
```

El usuario del sitio debe poder escribir en `storage/` y `bootstrap/cache/`.
En CloudPanel utiliza el usuario asignado al sitio, no `root`.

## 4. Workers compartidos

Copia `deploy/supervisor/fullbolito-workers.conf.example` a
`/etc/supervisor/conf.d/fullbolito-workers.conf` y reemplaza:

- `__PHP_BINARY__`: salida de `command -v php`.
- `__APP_PATH__`: ruta absoluta del proyecto.
- `__APP_USER__`: usuario Linux propietario del sitio.

La plantilla inicia un worker general y cuatro procesos SUNAT compartidos. Los
slots contratados limitan a cada tenant dentro de la aplicación; `numprocs=4`
es la capacidad física inicial del VPS y se puede ampliar según CPU, memoria y
volumen real.

Activa la configuración:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## 5. Scheduler

Agrega al `crontab` del usuario del sitio, reemplazando las rutas:

```cron
* * * * * cd __APP_PATH__ && __PHP_BINARY__ artisan schedule:run >> /dev/null 2>&1
```

Este cron también es parte del sistema de cobros: reconcilia cada minuto los
pagos de Mercado Pago cuyo webhook se retrasó y aplica los downgrades/cambios
de ciclo al terminar el periodo pagado. Sin `schedule:run`, un downgrade puede
quedar programado pero no llegar a aplicarse.

## 6. Despliegues posteriores

Después de publicar una nueva versión:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan tenants:migrate
php artisan optimize
php artisan queue:restart
```

Supervisor vuelve a levantar automáticamente los procesos luego del reinicio
graceful de Laravel.

## 7. Tenant Enterprise

Obtén el nombre exacto de las colas y la cantidad de procesos:

```bash
php artisan sunat:dedicated-queues --commands
```

Crea un archivo por tenant tomando como base
`deploy/supervisor/fullbolito-enterprise.conf.example`. Activa primero el
worker con Supervisor y recién después cambia:

```dotenv
SUNAT_DEDICATED_QUEUES_ENABLED=true
```

Finaliza con:

```bash
php artisan optimize
php artisan queue:restart
sudo supervisorctl status
```

## 8. Comprobaciones

```bash
php artisan migrate:status
php artisan schedule:list
php artisan billing:reconcile-mercadopago
php artisan billing:apply-scheduled-plan-changes
php artisan queue:failed
sudo supervisorctl status
tail -f storage/logs/worker-sunat-00.log
```

Nunca ejecutes `queue:work` únicamente desde una sesión SSH en producción: al
cerrarla, el proceso termina. Supervisor debe ser quien lo administre.

## 9. Política de cambios de plan

Cada plan tiene un `billing_rank` administrable desde el panel central:

- Un rango mayor es un upgrade: se cobra una sola vez la diferencia
  prorrateada restante y, al aprobarse, se habilita el plan superior. La misma
  suscripción recurrente cambia al precio nuevo para su próxima renovación.
- Un rango menor es un downgrade: no cobra ni devuelve dinero en ese momento;
  se programa para el final del periodo ya pagado.
- El mismo rango con otra duración (mensual/anual) es un cambio de ciclo y se
  programa para la renovación.

Después de migrar, revisa en el CRUD central que los rangos expresen el orden
comercial esperado. La migración asigna inicialmente: prueba `0`, básico `10`,
pro `20` y Enterprise `30`; para planes personalizados infiere el rango por sus
canales simultáneos SUNAT.

En Mercado Pago configura la misma URL HTTPS en las pestañas **Modo de prueba**
y **Modo productivo**, seleccionando **Planes y suscripciones** y **Pagos**:

```text
https://tu-dominio.com/api/v1/webhooks/mercadopago
```

Las pruebas con usuarios/credenciales de prueba no siempre emiten una
notificación de pago real. El comando `billing:reconcile-mercadopago`, ejecutado
por el scheduler, cubre ese caso consultando directamente el estado remoto.

## 10. Cancelación al final del periodo

Cuando el cliente cancela, la recurrencia se detiene inmediatamente en Mercado
Pago, pero la suscripción local permanece activa hasta `ends_at`. No se realiza
una devolución automática. Durante ese periodo la interfaz muestra la fecha
final de acceso y bloquea cambios de plan o complementos pagos.

El comando `subscriptions:expire`, ejecutado cada minuto por el scheduler,
finaliza localmente estas suscripciones al alcanzar la fecha pagada. Después de
desplegar esta política es obligatorio ejecutar la migración central con
`php artisan migrate --force`.
