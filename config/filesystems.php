<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Hier staat nu 'sftp', afhankelijk van je .env "FILESYSTEM_DISK=sftp".
    */
    'default' => env('FILESYSTEM_DISK', 'sftp'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // 🔥 SFTP Configuratie voor STACK
        'sftp' => [
            'driver' => 'sftp',
            'host' => env('SFTP_HOST'),
            'username' => env('SFTP_USERNAME'),
            'password' => env('SFTP_PASSWORD'),
            'port' => (int) env('SFTP_PORT', 22),            'root' => '/jojos', // Zorg dat deze map bestaat in je STACK
            'visibility' => 'public',
            'throw' => true, // Gooit een error als wachtwoord/pad fout is, handig voor debuggen
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
