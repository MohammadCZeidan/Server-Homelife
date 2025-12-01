<?php
// Quick diagnostic page
header('Content-Type: application/json');

$routes = [
    'test_web' => '/test',
    'api_register' => '/api/v0.1/auth/register',
    'api_login' => '/api/v0.1/auth/login',
];

echo json_encode([
    'status' => 'Server is running',
    'routes_to_test' => $routes,
    'instructions' => [
        '1. Test web route: GET http://localhost:8000/test',
        '2. Test API: POST http://localhost:8000/api/v0.1/auth/register',
        '3. Use Postman with POST method and JSON body',
    ]
], JSON_PRETTY_PRINT);

