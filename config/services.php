<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Greenter — facturación electrónica SUNAT (Perú).
     *
     * Por defecto usamos firma local con greenter/lite (lee certificado del
     * BillingCredential del tenant). Si en el futuro se conecta un PSE
     * externo, configurar `api_url` + `api_token` y el servicio caerá al
     * canal API automáticamente cuando no haya credenciales locales.
     */
    'greenter' => [
        'api_url' => env('GREENTER_API_URL', ''),
        'api_token' => env('GREENTER_API_TOKEN', ''),

        // Endpoints REST para GRE (Guía de Remisión Electrónica).
        // SUNAT no tiene URL beta para GRE; se prueba con credenciales de
        // homologación en producción, o con la sandbox comunitaria de
        // Nubefact (https://gre-test.nubefact.com/v1) — se sobreescribe
        // con env vars cuando se quiera testear sin tocar producción.
        'gre_endpoints' => [
            'auth' => env('GREENTER_GRE_AUTH_URL', 'https://api-seguridad.sunat.gob.pe/v1'),
            'cpe' => env('GREENTER_GRE_CPE_URL', 'https://api-cpe.sunat.gob.pe/v1'),
        ],
    ],

];
