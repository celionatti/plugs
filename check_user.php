<?php

require __DIR__ . '/vendor/autoload.php';

use Plugs\Bootstrap\Bootstrapper;

$bootstrapper = new Bootstrapper(__DIR__);
$app = $bootstrapper->boot();
$app->boot();

$user = \Plugs\Database\DB::table('users')->latest('id')->first();

if ($user) {
    echo "ID: " . $user->id . "\n";
    echo "Name: " . $user->name . "\n";
    echo "Email: " . $user->email . "\n";
    echo "Role: " . $user->role . "\n";
} else {
    echo "No users found.\n";
}
