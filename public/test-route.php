<?php
// Quick test to see if routes are accessible
echo "Testing API Routes...\n\n";

$routes = [
    'POST /api/v0.1/auth/register' => 'http://localhost:8000/api/v0.1/auth/register',
    'POST /api/v0.1/auth/login' => 'http://localhost:8000/api/v0.1/auth/login',
    'GET /api/v0.1/auth/me' => 'http://localhost:8000/api/v0.1/auth/me',
];

echo "Available Routes:\n";
foreach ($routes as $method => $url) {
    echo "$method => $url\n";
}

echo "\n\nTo test:\n";
echo "1. Use Postman\n";
echo "2. Or open: http://localhost:8000/test-api.html\n";
echo "3. Or use browser console with fetch()\n";

