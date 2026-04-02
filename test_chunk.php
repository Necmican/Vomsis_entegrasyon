<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new \App\Services\VomsisService();
$token = $service->getToken();

echo "Testing chunk YYYY-MM-DD format (2025-12-09 to 2025-12-15)\n";
$r1 = Illuminate\Support\Facades\Http::withToken($token)->get('https://developers.vomsis.com/api/v2/transactions', [
    'beginDate' => '2025-12-09',
    'endDate' => '2025-12-15'
])->json();
print_r($r1);

echo "Testing chunk DD-MM-YYYY format (09-12-2025 to 15-12-2025)\n";
$r2 = Illuminate\Support\Facades\Http::withToken($token)->get('https://developers.vomsis.com/api/v2/transactions', [
    'beginDate' => '09-12-2025',
    'endDate' => '15-12-2025'
])->json();
print_r($r2);
