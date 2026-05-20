<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$u1 = App\Models\User::find(1);
$u1->password = bcrypt('Bigenmi@2026');
$u1->save();
echo "Password reset OK for: " . $u1->email . "\n";

$u2 = App\Models\User::find(2);
$u2->password = bcrypt('Admin@2026');
$u2->save();
echo "Password reset OK for: " . $u2->email . "\n";
