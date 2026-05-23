<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$results = DB::table('transactions')
    ->select(DB::raw('strftime("%Y-%m", transaction_date) as yr_mo'), 'source', DB::raw('count(*) as count'))
    ->groupBy('yr_mo', 'source')
    ->orderBy('yr_mo')
    ->get();

foreach ($results as $res) {
    echo "Month: {$res->yr_mo} | Source: {$res->source} | Count: {$res->count}\n";
}
