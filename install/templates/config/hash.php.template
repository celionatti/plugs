<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Hash Configuration File
|--------------------------------------------------------------------------
|
| This file is used to configure the hashing options for the application.
| You may specify the default hash driver as well as options for each
| supported driver.
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default hash driver that will be used to hash
    | passwords for your application. Supported: "bcrypt", "argon", "argon2id"
    |
    */

    'driver' => $_ENV['HASH_DRIVER'] ?? 'argon2id',

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Bcrypt algorithm. This will allow you
    | to control the amount of time it takes to hash the given password.
    |
    */

    'bcrypt' => [
        'rounds' => (int) ($_ENV['BCRYPT_ROUNDS'] ?? 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | Here you may specify the configuration options that should be used when
    | passwords are hashed using the Argon algorithm. These will allow you
    | to control the amount of time it takes to hash the given password.
    |
    */

    'argon' => [
        'memory' => (int) ($_ENV['ARGON_MEMORY'] ?? 65536),
        'time' => (int) ($_ENV['ARGON_TIME'] ?? 4),
        'threads' => (int) ($_ENV['ARGON_THREADS'] ?? 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon2id Options
    |--------------------------------------------------------------------------
    |
    | Argon2id is a hybrid version that combines Argon2i and Argon2d.
    | It is the recommended algorithm for password hashing.
    |
    */

    'argon2id' => [
        'memory' => (int) ($_ENV['ARGON2ID_MEMORY'] ?? 65536),
        'time' => (int) ($_ENV['ARGON2ID_TIME'] ?? 4),
        'threads' => (int) ($_ENV['ARGON2ID_THREADS'] ?? 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verify Options
    |--------------------------------------------------------------------------
    |
    | When verifying passwords, you can specify whether to automatically
    | rehash the password if it needs to be updated to use newer options.
    |
    */

    'verify' => [
        'auto_rehash' => (bool) ($_ENV['HASH_AUTO_REHASH'] ?? false),
    ],
];
