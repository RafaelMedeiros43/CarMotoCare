<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | RabbitMQ (plugin rabbitmq_mqtt, porta interna 1883)
    |--------------------------------------------------------------------------
    */

    'host' => env('MQTT_HOST', 'rabbitmq'),
    'port' => (int) env('MQTT_PORT', 1883),

    // Identificador do subscritor (sessão persistente: tem de ser estável entre reinícios).
    // A ligação que publica usa "{client_id}-publisher", porque o broker não aceita dois clientes com o mesmo id.
    'client_id' => env('MQTT_CLIENT_ID', 'ms-obd'),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),

    // Espera entre tentativas de ligação quando o broker cai ou ainda não está pronto.
    'reconnect_delay_seconds' => (int) env('MQTT_RECONNECT_DELAY_SECONDS', 5),

    'topics' => [
        'telemetry_filter' => 'obd/telemetry/+',
        'telemetry_prefix' => 'obd/telemetry/',
        'vehicle_state' => 'app/vehicle/state',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validação V5: velocidade máxima plausível (km/h)
    |--------------------------------------------------------------------------
    | Subir em desenvolvimento quando o simulador usa um SPEED_FACTOR alto.
    */

    'max_speed_kmh' => (float) env('MAX_SPEED_KMH', 250),

];
