<?php
return [
    'connections' => [
        'couchbase-default' => [
            'name' => 'couchbase',
            'driver' => 'couchbase',
            'port' => 8091,
            'host' => '127.0.0.1',
//            'host' => 'fieldstone-laravel-couchbase-server',
            'bucket' => 'test-ing',
            'username' => 'dbuser_backend',
            'password' => 'password_backend',
            'auth_type' => \Fieldstone\Couchbase\Connection::AUTH_TYPE_USER_PASSWORD,
            'admin_username' => 'admin',
            'admin_password' => 'password'
        ],
        'couchbase-not-default' => [
            'name' => 'couchbase',
            'driver' => 'couchbase',
            'port' => 8091,
            'host' => '127.0.0.1',
            'bucket' => 'test-ing2',
            'username' => 'dbuser_backend',
            'password' => 'password_backend',
            'auth_type' => \Fieldstone\Couchbase\Connection::AUTH_TYPE_USER_PASSWORD,
            'admin_username' => 'admin',
            'admin_password' => 'password'
        ],
        'mysql' => [
            'name' => 'mysql',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'testing',
            'username' => 'dbuser_backend',
            'password' => 'password_backend',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => ''
        ],
    ],
];
