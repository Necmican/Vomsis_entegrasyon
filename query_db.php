<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$output = "Total transactions: " . \App\Models\Transaction::count() . "\n";
$output .= "Before Dec 9: " . \App\Models\Transaction::where('transaction_date', '<', '2025-12-09 00:00:00')->count() . "\n";
$output .= "Between Dec 9 and Jan 19: " . \App\Models\Transaction::where('transaction_date', '>=', '2025-12-09 00:00:00')->where('transaction_date', '<', '2026-01-20 00:00:00')->count() . "\n";
$output .= "Between Jan 20 and Jan 26: " . \App\Models\Transaction::where('transaction_date', '>=', '2026-01-20 00:00:00')->where('transaction_date', '<=', '2026-01-26 23:59:59')->count() . "\n";
$output .= "Between Jan 27 and Feb 9: " . \App\Models\Transaction::where('transaction_date', '>=', '2026-01-27 00:00:00')->where('transaction_date', '<=', '2026-02-09 23:59:59')->count() . "\n";
$output .= "After Feb 9: " . \App\Models\Transaction::where('transaction_date', '>', '2026-02-09 23:59:59')->count() . "\n";

file_put_contents('query_results.txt', $output);
echo "Done";
