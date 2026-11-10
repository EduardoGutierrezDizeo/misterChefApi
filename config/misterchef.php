<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mister Chef
    |--------------------------------------------------------------------------
    |
    | Valores de configuración propios de la aplicación.
    |
    */

    /*
    | Minutos tras los cuales una ubicación de domiciliario se considera
    | obsoleta y deja de aparecer en el mapa del administrador.
    */
    'location_stale_minutes' => env('LOCATION_STALE_MINUTES', 5),

    /*
    | Contraseña inicial del empleado administrador que crea el seeder.
    | Obligatoria en producción (ADMIN_PASSWORD).
    */
    'admin_password' => env('ADMIN_PASSWORD'),

];
