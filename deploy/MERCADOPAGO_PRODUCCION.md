# Mercado Pago: credenciales y salida a producción

Esta guía está adaptada a la integración actual de Fullbolito: una sola cuenta
vendedora de Mercado Pago cobra las suscripciones SaaS de todos los tenants.
Los tenants son los compradores; no necesitan entregar credenciales de Mercado
Pago a Fullbolito.

> **Advertencia:** al colocar credenciales productivas se habilitan cobros
> reales. No uses tarjetas, usuarios ni contraseñas de prueba con esas
> credenciales.

Documentación oficial de referencia:

- [Credenciales de Mercado Pago](https://www.mercadopago.com.pe/developers/es/docs/your-integrations/credentials)
- [Suscripciones](https://www.mercadopago.com.pe/developers/es/docs/subscriptions/overview)
- [Configuración de Webhooks](https://www.mercadopago.com.pe/developers/es/docs/links-and-debts/additional-content/your-integrations/notifications/webhooks)
- [Requisitos para salir a producción](https://www.mercadopago.com.pe/developers/es/docs/checkout-api-orders/go-to-production)

## 1. Qué credenciales usa Fullbolito

La aplicación actualmente necesita:

| Dato | Uso | Variable en el VPS |
| --- | --- | --- |
| Access Token productivo | Crear, consultar y cancelar suscripciones desde el backend | `MERCADOPAGO_ACCESS_TOKEN` |
| Clave secreta del Webhook productivo | Validar que las notificaciones sean auténticas | `MERCADOPAGO_WEBHOOK_SECRET` |

En el panel también aparecerán `Public Key`, `Client ID` y `Client Secret`, pero
Fullbolito no los usa actualmente:

- No necesita `Public Key` porque el comprador completa el pago en el checkout
  alojado por Mercado Pago.
- No necesita `Client ID` ni `Client Secret` porque no se está usando OAuth ni
  conectando una cuenta de Mercado Pago diferente por tenant.

Nunca coloques el Access Token en Vue, JavaScript, URLs, capturas de pantalla,
mensajes, tickets o repositorios Git. Es una credencial privada de backend.

## 2. Requisitos de la cuenta vendedora

Usa la cuenta real de Mercado Pago Perú que recibirá el dinero de las
suscripciones de Fullbolito.

Antes de continuar, verifica:

- La cuenta corresponde al titular o negocio que venderá el servicio.
- Mercado Pago no muestra verificaciones de identidad o datos del negocio
  pendientes.
- Puedes ingresar normalmente a Mercado Pago y a Mercado Pago Developers.
- `https://fullbolito.com` funciona con HTTPS y certificado válido.
- El correo, teléfono y datos de recuperación de la cuenta están actualizados.

La cuenta compradora de cada cliente es independiente de esta cuenta vendedora.
Según el medio de pago disponible, el cliente puede pagar iniciando sesión en
Mercado Pago o continuar sin una cuenta.

## 3. Activar las credenciales productivas

1. Ingresa a [Mercado Pago Developers](https://www.mercadopago.com.pe/developers/).
2. Pulsa **Tus integraciones**.
3. Abre la aplicación usada por Fullbolito. Puedes usar la misma aplicación en
   la que probaste Suscripciones; no necesitas crear otra solamente por pasar a
   producción.
4. En el menú izquierdo abre **Producción → Credenciales de producción**.
5. Si todavía no están habilitadas, pulsa **Activar credenciales**.
6. Completa los datos solicitados:
   - Industria o rubro correspondiente al SaaS/servicio de Fullbolito.
   - Sitio web: `https://fullbolito.com`.
   - Declaración de privacidad, términos y reCAPTCHA.
7. Pulsa **Activar credenciales de producción**.
8. Copia de manera segura el **Access Token** mostrado en esta sección.

No determines si una credencial es de prueba o producción solamente por su
prefijo. Cópiala expresamente desde **Credenciales de producción** y valida
luego el usuario vendedor con `/users/me`.

## 4. Configurar el Webhook productivo

Dentro de la misma aplicación:

1. Abre **Webhooks → Configurar notificaciones**.
2. Selecciona la pestaña **Modo productivo**.
3. Coloca esta URL HTTPS exacta:

   ```text
   https://fullbolito.com/api/v1/webhooks/mercadopago
   ```

4. Activa estos eventos:
   - **Planes y suscripciones**.
   - **Pagos** o **Pagos (legacy)**, según el nombre que muestre el panel.
5. Guarda la configuración.
6. Revela y copia la **Clave secreta** generada para el modo productivo.

La clave secreta de producción es diferente de la de pruebas. La configuración
actual de Fullbolito admite una sola clave a la vez, por lo que el endpoint debe
usar la clave del mismo ambiente que el Access Token:

| Access Token | Webhook secret | Resultado |
| --- | --- | --- |
| Productivo | Productivo | Correcto para cobrar realmente |
| Prueba | Prueba | Correcto para continuar las pruebas |
| Productivo | Prueba | Incorrecto: webhooks `401` |
| Prueba | Productivo | Incorrecto: webhooks `401` |

Una llamada manual sin los headers firmados de Mercado Pago debe responder
`401 Invalid webhook signature`. Ese `401` es correcto y confirma que un
tercero no puede falsificar notificaciones con un `curl` simple.

## 5. Antes de cambiar el VPS: separar los datos de prueba

Las suscripciones y checkouts creados con el vendedor de prueba pertenecen a
ese ambiente. El Access Token productivo no podrá consultarlos ni cancelarlos.

Como actualmente Fullbolito todavía no tiene clientes reales, la opción más
limpia es iniciar producción con una base central limpia o conservar los datos
de prueba únicamente como históricos con estados terminales. Antes de tocar
datos:

1. Haz un backup de la base central.
2. Comprueba que no exista ningún pago real.
3. Revisa las suscripciones locales todavía vinculadas al vendedor de prueba:

   ```bash
   php artisan tinker --execute='$items=App\Models\Subscription::with("plan")->where("provider","mercadopago")->whereIn("status",["active","past_due","paused"])->get()->map(fn($s)=>["id"=>$s->id,"tenant_id"=>$s->tenant_id,"plan"=>$s->plan?->slug,"status"=>$s->status,"provider_status"=>$s->provider_status,"provider_id"=>$s->provider_id]); dump($items->toArray());'
   ```

4. Revisa las operaciones que el reconciliador todavía considera pendientes:

   ```bash
   php artisan tinker --execute='$items=App\Models\BillingCheckout::where("provider","mercadopago")->whereNull("completed_at")->whereIn("status",["creating","pending","authorized"])->get(["id","tenant_id","status","provider_id","created_at"]); dump($items->toArray());'
   ```

5. Revisa los cambios de plan pendientes:

   ```bash
   php artisan tinker --execute='$items=App\Models\SubscriptionPlanChange::whereIn("status",["pending_payment","paid","applying","scheduled","ready"])->get(["id","tenant_id","status","provider_payment_id","created_at"]); dump($items->toArray());'
   ```

No borres ni marques operaciones masivamente sin revisar el resultado. Toda
suscripción activa que pertenezca al vendedor de prueba debe finalizarse o
aislarse antes del cambio. Si aparece algún registro dudoso, resuélvelo antes de
cambiar las credenciales. De lo contrario, los cambios de plan o el scheduler
intentarán consultar identificadores de prueba usando el token productivo y
registrarán errores.

## 6. Configurar el `.env` productivo del VPS

Edita `/home/fullbolito/htdocs/fullbolito.com/.env` directamente en el VPS. No
pegues las credenciales en comandos que queden guardados en el historial del
shell.

La sección debe quedar con este formato, reemplazando únicamente los valores
entre `<...>`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://fullbolito.com

MERCADOPAGO_ACCESS_TOKEN=<ACCESS_TOKEN_PRODUCTIVO>
MERCADOPAGO_WEBHOOK_SECRET=<CLAVE_SECRETA_WEBHOOK_PRODUCTIVO>
MERCADOPAGO_CURRENCY=PEN
MERCADOPAGO_TIMEOUT=15
MERCADOPAGO_CHECKOUT_TTL_HOURS=24
```

No agregues las cuentas de prueba, contraseñas, códigos de verificación,
`Public Key`, `Client ID` ni `Client Secret` al `.env`: la integración actual no
los necesita.

Después de guardar:

```bash
cd /home/fullbolito/htdocs/fullbolito.com

php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
```

Si acabas de desplegar cambios de código, ejecuta además las migraciones antes
de cachear la configuración:

```bash
php artisan migrate --force
```

## 7. Validar el Access Token sin mostrarlo

Primero confirma que Laravel cargó las dos credenciales:

```bash
php artisan tinker --execute='dump(["access_token_configured"=>filled(config("mercadopago.access_token")),"webhook_secret_configured"=>filled(config("mercadopago.webhook_secret")),"currency"=>config("mercadopago.currency")]);'
```

El resultado esperado es:

```text
access_token_configured: true
webhook_secret_configured: true
currency: PEN
```

Después consulta al usuario dueño del token sin imprimir la credencial:

```bash
php artisan tinker --execute='$r=Illuminate\Support\Facades\Http::withToken((string) config("mercadopago.access_token"))->get("https://api.mercadopago.com/users/me"); dump(["http_status"=>$r->status(),"seller_id"=>$r->json("id"),"nickname"=>$r->json("nickname"),"country_id"=>$r->json("country_id")]);'
```

Debe responder `http_status: 200`. Comprueba manualmente que `seller_id`
pertenece a la cuenta vendedora real y que el país corresponde a Perú. Si sigue
apareciendo el usuario vendedor de prueba, no continúes: el token no es el
productivo correcto.

## 8. Verificar HTTPS, endpoint y scheduler

Comprueba el endpoint público:

```bash
curl -i -X POST \
  https://fullbolito.com/api/v1/webhooks/mercadopago \
  -H 'Accept: application/json'
```

El resultado esperado para esta llamada sin firma es `401`, no `200`.

Verifica que el scheduler conoce los procesos de facturación:

```bash
php artisan schedule:list | grep -E 'billing:|subscriptions:expire'
```

El cron del VPS debe ejecutar cada minuto:

```cron
* * * * * cd /home/fullbolito/htdocs/fullbolito.com && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Confirma la ruta real de PHP con `which php`; reemplaza `/usr/bin/php` si el
servidor devuelve otra ubicación.

## 9. Primera compra real controlada

No uses el botón **Simular notificación** como prueba final del negocio. La
prueba concluyente es una suscripción real de bajo riesgo:

1. Elige un tenant controlado por ti y verifica su correo de facturación.
2. Desde `/admin/billing` selecciona el plan pagado que realmente deseas
   probar.
3. Confirma que la redirección sea al dominio oficial
   `mercadopago.com.pe`.
4. Paga con un medio de pago real. No uses tarjetas ni usuarios de prueba.
5. Evita pagar desde la misma cuenta vendedora que recibirá el dinero; usa un
   comprador real diferente.
6. Regresa a Fullbolito y espera unos segundos.
7. Ejecuta una reconciliación manual como respaldo:

   ```bash
   php artisan billing:reconcile-mercadopago --days=7 -vvv
   ```

8. Comprueba la suscripción del tenant:

   ```bash
   php artisan tinker --execute='$s=App\Models\Subscription::with("plan")->where("tenant_id","<TENANT_ID>")->latest()->first(); dump(["plan"=>$s?->plan?->slug,"status"=>$s?->status,"provider_status"=>$s?->provider_status,"next_billing_at"=>$s?->next_billing_at?->toDateTimeString(),"cancel_at_period_end"=>$s?->cancel_at_period_end]);'
   ```

El resultado esperado después de la autorización es:

```text
status: active
provider_status: authorized
next_billing_at: una fecha futura
cancel_at_period_end: false
```

9. Comprueba que se recibió un webhook real:

   ```bash
   php artisan tinker --execute='$e=App\Models\PaymentWebhookEvent::latest()->first(); dump(["type"=>$e?->event_type,"action"=>$e?->action,"resource_id"=>$e?->resource_id,"processed_at"=>$e?->processed_at?->toDateTimeString(),"error"=>$e?->error]);'
   ```

10. Verifica el movimiento directamente en la cuenta vendedora de Mercado
    Pago.

Esta compra es real y puede generar comisiones. Si cancelas la renovación desde
Fullbolito, no habrá devolución automática: el tenant conservará acceso hasta
la fecha pagada y no recibirá cobros futuros.

## 10. Checklist de aprobación

No abras Fullbolito a clientes hasta poder marcar todo:

- [ ] Cuenta vendedora real verificada.
- [ ] Credenciales activadas desde **Modo productivo**.
- [ ] Access Token productivo guardado solamente en el VPS.
- [ ] Webhook configurado en **Modo productivo**.
- [ ] Eventos **Planes y suscripciones** y **Pagos** habilitados.
- [ ] Clave secreta productiva colocada en el `.env`.
- [ ] `APP_DEBUG=false`.
- [ ] HTTPS válido en `fullbolito.com`.
- [ ] `/users/me` responde `200` y muestra al vendedor real.
- [ ] Scheduler ejecutándose cada minuto.
- [ ] No quedan operaciones de prueba pendientes de reconciliación.
- [ ] Compra real aprobada y reflejada como `active/authorized`.
- [ ] Webhook real procesado sin error.
- [ ] Renovación cancelada en una prueba controlada y acceso conservado hasta
      `ends_at`.
- [ ] Backup de la base central disponible.

## 11. Rotación de credenciales expuestas

Si un Access Token o una clave secreta apareció en una captura, chat, consola
compartida o repositorio, considéralo comprometido aunque sea de prueba:

1. En **Credenciales**, usa la opción **Renovar** para generar un nuevo par.
2. En **Webhooks**, usa **Restablecer** para generar una nueva clave secreta.
3. Actualiza inmediatamente el `.env` del VPS.
4. Ejecuta `php artisan optimize:clear`, `php artisan config:cache` y
   `php artisan queue:restart`.
5. Verifica nuevamente `/users/me` y un webhook real.

Renovar credenciales invalida las anteriores. Coordina el cambio para no dejar
el servidor usando un Access Token antiguo.

## 12. Prueba backend con credenciales dentro de un tenant

Fullbolito también dispone de una base experimental para guardar una conexión
de Mercado Pago dentro de la base propia de un tenant. Esto está separado de la
facturación SaaS central:

- Las variables del `.env` continúan siendo necesarias para que Fullbolito
  cobre sus planes.
- La copia tenant queda cifrada con `APP_KEY`.
- Los secretos nunca se muestran en la salida del comando.
- La conexión nace inactiva y solamente se activa si `/users/me` responde
  correctamente.
- Esta conexión todavía no crea cobros de reservas ni procesa webhooks de
  clientes finales; es la base backend para implementar ese módulo después.

Primero despliega y migra la base del tenant elegido:

```bash
php artisan tenants:migrate --tenants=jhamil
```

Para copiar las credenciales de prueba actuales del `.env`:

```bash
php artisan tenant-payments:import-mercadopago-env jhamil --environment=test
```

Para validar la conexión guardada:

```bash
php artisan tenant-payments:test-mercadopago jhamil --environment=test
```

El comando devuelve únicamente:

- ID del tenant.
- Ambiente.
- ID y nickname de la cuenta vendedora.
- País.
- Fecha de validación.

No ejecutes la importación productiva para tenants de clientes. Copiar las
credenciales productivas centrales haría que los pagos de esos negocios se
dirijan a la cuenta de Fullbolito. Para que cada negocio reciba su propio
dinero, se debe implementar la conexión del vendedor mediante OAuth.
