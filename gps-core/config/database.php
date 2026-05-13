<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for all database work. Of course
    | you may use many connections at once using the Database library.
    |
    */

    'default' => env('DB_CONNECTION', 'auth_db'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the database connections setup for your application.
    | Of course, examples of configuring each database platform that is
    | supported by Laravel is shown below to make development simple.
    |
    |
    | All database work in Laravel is done through the PHP PDO facilities
    | so make sure you have the driver for your particular database of
    | choice installed on your machine before you begin development.
    |
    */

    'connections' => [

        'auth_db' => [
            'driver' => 'mysql',
            'host' => env('AUTH_DB_HOST', '127.0.0.1'),
            'port' => env('AUTH_DB_PORT', '3306'),
            'database' => env('AUTH_DB_DATABASE', 'auth_db'),
            'username' => env('AUTH_DB_USERNAME', 'root'),
            'password' => env('AUTH_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'options' => extension_loaded('pdo_mysql') ? [
                PDO::ATTR_TIMEOUT => 3,
            ] : [],
        ],

        'gps5' => [
            'driver' => 'mysql',
            'host' => env('GPS5_DB_HOST'),
            'port' => env('GPS5_DB_PORT'),
            'database' => env('GPS5_DB_DATABASE'),
            'username' => env('GPS5_DB_USERNAME'),
            'password' => env('GPS5_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::ATTR_TIMEOUT => 10,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=28800",
                PDO::ATTR_PERSISTENT => false,
            ]) : [],
        ],

        'gps10' => [
            'driver' => 'mysql',
            'host' => env('GPS10_DB_HOST'),
            'port' => env('GPS10_DB_PORT'),
            'database' => env('GPS10_DB_DATABASE'),
            'username' => env('GPS10_DB_USERNAME'),
            'password' => env('GPS10_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'options' => [PDO::ATTR_TIMEOUT => 3],
        ],

        'gps13' => [
            'driver' => 'mysql',
            'host' => env('GPS13_DB_HOST'),
            'port' => env('GPS13_DB_PORT'),
            'database' => env('GPS13_DB_DATABASE'),
            'username' => env('GPS13_DB_USERNAME'),
            'password' => env('GPS13_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'options' => [PDO::ATTR_TIMEOUT => 3],
        ],

        'gps14' => [
            'driver' => 'mysql',
            'host' => env('GPS14_DB_HOST'),
            'port' => env('GPS14_DB_PORT'),
            'database' => env('GPS14_DB_DATABASE'),
            'username' => env('GPS14_DB_USERNAME'),
            'password' => env('GPS14_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'options' => [PDO::ATTR_TIMEOUT => 3],
        ],

        'gps16' => [
            'driver' => 'mysql',
            'host' => env('GPS16_DB_HOST'),
            'port' => env('GPS16_DB_PORT'),
            'database' => env('GPS16_DB_DATABASE'),
            'username' => env('GPS16_DB_USERNAME'),
            'password' => env('GPS16_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'options' => [PDO::ATTR_TIMEOUT => 3],
        ],

        'gps19' => [
            'driver' => 'mysql',
            'host' => env('GPS19_DB_HOST'),
            'port' => env('GPS19_DB_PORT'),
            'database' => env('GPS19_DB_DATABASE'),
            'username' => env('GPS19_DB_USERNAME'),
            'password' => env('GPS19_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'options' => [PDO::ATTR_TIMEOUT => 3],
        ],

        'gps20' => [
            'driver' => 'mysql',
            'host' => env('GPS20_DB_HOST'),
            'port' => env('GPS20_DB_PORT'),
            'database' => env('GPS20_DB_DATABASE'),
            'username' => env('GPS20_DB_USERNAME'),
            'password' => env('GPS20_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'options' => [PDO::ATTR_TIMEOUT => 3],
        ],

        'gps21' => [
            'driver' => 'mysql',
            'host' => env('GPS21_DB_HOST'),
            'port' => env('GPS21_DB_PORT'),
            'database' => env('GPS21_DB_DATABASE'),
            'username' => env('GPS21_DB_USERNAME'),
            'password' => env('GPS21_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'options' => [PDO::ATTR_TIMEOUT => 3],
        ],


    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run in the database.
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as APC or Memcached. Laravel makes it easy to dig right in.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', ''),
        ],

        'default' => [
            'host' => '127.0.0.1',
            'password' => env('REDIS_PASSWORD'),
            'port' => 6379,
            'database' => 0,
        ],

        'cache' => [
            'host' => '127.0.0.1',
            'password' => env('REDIS_PASSWORD'),
            'port' => 6379,
            'database' => 1,
        ],

        'gps5' => [
            'host' => env('REDIS_GPS5_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_GPS5_PORT', 7405),
            'database' => env('REDIS_DB', 0),
        ],

        'gps10' => [
            'host' => env('REDIS_GPS10_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_GPS10_PORT', 7410),
            'database' => env('REDIS_DB', 0),
        ],

        'gps13' => [
            'host' => env('REDIS_GPS13_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_GPS13_PORT', 7413),
            'database' => env('REDIS_DB', 0),
        ],

        'gps14' => [
            'host' => env('REDIS_GPS14_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_GPS14_PORT', 7414),
            'database' => env('REDIS_DB', 0),
        ],

        'gps16' => [
            'host' => env('REDIS_GPS16_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_GPS16_PORT', 7416),
            'database' => env('REDIS_DB', 0),
        ],

        'gps19' => [
            'host' => env('REDIS_GPS19_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_GPS19_PORT', 7419),
            'database' => env('REDIS_DB', 0),
        ],

        'gps20' => [
            'host' => env('REDIS_GPS20_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_GPS20_PORT', 7420),
            'database' => env('REDIS_DB', 0),
        ],

        'gps21' => [
            'host' => env('REDIS_GPS21_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_GPS21_PORT', 7421),
            'database' => env('REDIS_DB', 0),
        ],
    ],

];
