<?php

require __DIR__ . '/vendor/autoload.php';

use Plugs\Bootstrap\Bootstrapper;
use App\Models\User;

$bootstrapper = new Bootstrapper(__DIR__);
$app = $bootstrapper->boot();

$user = User::where('role', 'admin')->first();

if ($user) {
    echo "User ID: " . $user->id . "\n";
    echo "Raw Role Attribute: " . ($user->role ?? 'NULL') . "\n";
    echo "GetAttribute Role: " . ($user->getAttribute('role') ?? 'NULL') . "\n";
    echo "Is Admin: " . ($user->isAdmin() ? 'YES' : 'NO') . "\n";
} else {
    echo "No admin user found in DB.\n";
}
