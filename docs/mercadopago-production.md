# Mercado Pago: puesta en producción

La integración cubre la facturación recurrente de Fullbolito a sus tenants. Los pagos de reservas bajo el modelo marketplace quedan fuera de este primer alcance.

## 1. Crear y validar la cuenta

1. Crear la cuenta empresarial peruana de Mercado Pago con el RUC de Fullbolito.
2. Completar la validación de identidad y la cuenta bancaria de retiro.
3. En **Tus integraciones**, crear una aplicación de pagos online para suscripciones.
4. Usar credenciales de prueba durante la certificación y el `Access Token` productivo únicamente en producción.

## 2. Variables de entorno

```dotenv
APP_URL=https://fullbolito.com
CENTRAL_DOMAINS=fullbolito.com
MERCADOPAGO_ACCESS_TOKEN=APP_USR-...
MERCADOPAGO_WEBHOOK_SECRET=...
MERCADOPAGO_CURRENCY=PEN
MERCADOPAGO_TIMEOUT=15
MERCADOPAGO_CHECKOUT_TTL_HOURS=24
```

No se debe exponer el Access Token en Vue, Vite, logs ni respuestas HTTP.

Los checkouts, cambios de plan, addons y estados de suscripción solo pueden ser modificados por el correo propietario del tenant o por un usuario con rol `admin`.

Después de cambiar variables:

```bash
php artisan optimize:clear
php artisan config:cache
```

## 3. Base de datos

Ejecutar antes de habilitar los checkouts:

```bash
php artisan migrate --force
```

La migración conserva las columnas históricas de Stripe, pero el código ya no las utiliza. Esto permite auditar o migrar datos antiguos sin pérdida destructiva.

## 4. Webhook

Configurar esta URL productiva en Mercado Pago:

```text
https://fullbolito.com/api/v1/webhooks/mercadopago
```

Activar estos tópicos:

- `subscription_preapproval`
- `subscription_authorized_payment`
- `payment`

Copiar la clave secreta generada al valor `MERCADOPAGO_WEBHOOK_SECRET`.

El endpoint rechaza firmas inválidas, consulta el recurso directamente en Mercado Pago y registra el evento en `payment_webhook_events`. Un evento ya procesado no vuelve a aplicar cambios.

## 5. Pruebas obligatorias

Verificar con usuarios y tarjetas de prueba:

- alta de una suscripción mensual;
- abandono del checkout sin perder el trial o plan vigente;
- autorización y primer cobro;
- webhook duplicado;
- pago rechazado y posterior reintento;
- pausa, reactivación y cancelación;
- cambio entre plan mensual y anual;
- activación y retiro de addons;
- caída temporal de la API sin conceder funcionalidades gratis.

Comandos locales:

```bash
php artisan test
npm run build
```

## 6. Corte desde Stripe

Antes del despliegue, comprobar si existen suscripciones reales activas en Stripe. Si existen, no deben quedar cobrando en paralelo: se programa su cancelación y se pide al cliente autorizar la nueva suscripción de Mercado Pago. Las suscripciones pendientes de Mercado Pago no sustituyen el plan local hasta recibir el webhook `authorized`.

## 7. Operación

- Mantener activo el scheduler que ejecuta `subscriptions:expire`.
- Alertar sobre errores no procesados en `payment_webhook_events.error`.
- Conciliar periódicamente `payments.transaction_id` contra los reportes de Mercado Pago.
- Rotar el Access Token y la clave del webhook si se sospecha exposición.
- Conservar siempre URLs distintas de webhook para prueba y producción.
