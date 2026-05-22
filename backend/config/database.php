<?php
// Oracle database connection configuration.
// Override the password locally via the COBBLECART_DB_PASSWORD environment variable.

return [
    'username'   => getenv('COBBLECART_DB_USER')     ?: 'COBBLECART',
    'password'   => getenv('COBBLECART_DB_PASSWORD') ?: 'Oracle#12345@',
    'connection' => getenv('COBBLECART_DB_DSN')      ?: 'localhost/FREEPDB1',
    'charset'    => 'AL32UTF8',
];
