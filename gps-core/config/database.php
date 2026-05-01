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

//    'connections' => [
//
//        'sqlite' => [
//            'driver' => 'sqlite',
//            'url' => env('DATABASE_URL'),
//            'database' => env('DB_DATABASE', database_path('database.sqlite')),
//            'prefix' => '',
//            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
//        ],
//
//        'mysql' => [
//            'driver' => 'mysql',
//            'url' => env('DATABASE_URL'),
//            'host' => env('DB_HOST', '127.0.0.1'),
//            'port' => env('DB_PORT', '3306'),
//            'database' => env('DB_DATABASE', 'forge'),
//            'username' => env('DB_USERNAME', 'forge'),
//            'password' => env('DB_PASSWORD', ''),
//            'unix_socket' => env('DB_SOCKET', ''),
//            'charset' => 'utf8mb4',
//            'collation' => 'utf8mb4_unicode_ci',
//            'prefix' => '',
//            'prefix_indexes' => true,
//            'strict' => true,
//            'engine' => null,
//            'options' => extension_loaded('pdo_mysql') ? array_filter([
//                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
//            ]) : [],
//        ],
//
//        'pgsql' => [
//            'driver' => 'pgsql',
//            'url' => env('DATABASE_URL'),
//            'host' => env('DB_HOST', '127.0.0.1'),
//            'port' => env('DB_PORT', '5432'),
//            'database' => env('DB_DATABASE', 'forge'),
//            'username' => env('DB_USERNAME', 'forge'),
//            'password' => env('DB_PASSWORD', ''),
//            'charset' => 'utf8',
//            'prefix' => '',
//            'prefix_indexes' => true,
//            'schema' => 'public',
//            'sslmode' => 'prefer',
//        ],
//
//        'sqlsrv' => [
//            'driver' => 'sqlsrv',
//            'url' => env('DATABASE_URL'),
//            'host' => env('DB_HOST', 'localhost'),
//            'port' => env('DB_PORT', '1433'),
//            'database' => env('DB_DATABASE', 'forge'),
//            'username' => env('DB_USERNAME', 'forge'),
//            'password' => env('DB_PASSWORD', ''),
//            'charset' => 'utf8',
//            'prefix' => '',
//            'prefix_indexes' => true,
//        ],
//
//    ],

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
            'options' => [PDO::ATTR_TIMEOUT => 3],
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

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
