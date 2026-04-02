<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new \App\Services\VomsisService();
$token = $service->getToken();

echo "Testing DD-MM-YYYY format \n";
$r1 = Illuminate\Support\Facades\Http::withToken($token)->get('https://developers.vomsis.com/api/v2/transactions', [
    'beginDate' => '09-12-2025',
    'endDate' => '09-02-2026'
])->json();
if(isset($r1['transactions'])) {
    echo "Found " . count($r1['transactions']) . " transactions (DD-MM-YYYY)\n";
    if(count($r1['transactions']) > 0) {
        echo "First Date: {$r1['transactions'][0]['system_date']}\n";
        echo "Last Date: {$r1['transactions'][count($r1['transactions'])-1]['system_date']}\n";
    }
} else { print_r($r1); }

echo "\nTesting YYYY-MM-DD format (my change) \n";
$r2 = Illuminate\Support\Facades\Http::withToken($token)->get('https://developers.vomsis.com/api/v2/transactions', [
    'beginDate' => '2025-12-09',
    'endDate' => '2026-02-09'
])->json();
if(isset($r2['transactions'])) {
    echo "Found " . count($r2['transactions']) . " transactions (YYYY-MM-DD)\n";
    if(count($r2['transactions']) > 0) {
        echo "First Date: {$r2['transactions'][0]['system_date']}\n";
        echo "Last Date: {$r2['transactions'][count($r2['transactions'])-1]['system_date']}\n";
    }
} else { print_r($r2); }

echo "\nTesting begin_date/end_date YYYY-MM-DD format \n";
$r3 = Illuminate\Support\Facades\Http::withToken($token)->get('https://developers.vomsis.com/api/v2/transactions', [
    'begin_date' => '2025-12-09',
    'end_date' => '2026-02-09'
])->json();
if(isset($r3['transactions'])) {
    echo "Found " . count($r3['transactions']) . " transactions (begin_date)\n";
} else { print_r($r3); }

echo "\nTesting begin_date/end_date DD-MM-YYYY format \n";
$r4 = Illuminate\Support\Facades\Http::withToken($token)->get('https://developers.vomsis.com/api/v2/transactions', [
    'begin_date' => '09-12-2025',
    'end_date' => '09-02-2026'
])->json();
if(isset($r4['transactions'])) {
    echo "Found " . count($r4['transactions']) . " transactions (begin_date DD-MM-YYYY)\n";
} else { print_r($r4); }
