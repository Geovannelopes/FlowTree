<?php

// Usuário administrador criado pelo seeder (único usuário semeado em produção).
return [
    'name' => env('ADMIN_NAME', 'Administrador'),
    'email' => env('ADMIN_EMAIL', 'admin@flowtree.test'),
    'password' => env('ADMIN_PASSWORD', 'password'),
];
