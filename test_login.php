<?php

require __DIR__ . '/vendor/autoload.php';

use Plugs\Bootstrap\Bootstrapper;
use App\Models\User;
use Plugs\Security\Hash;

$bootstrapper = new Bootstrapper(__DIR__);
$app = $bootstrapper->boot();

$email = 'tillytinny@gmail.com';
$password = 'password'; // Assuming this was the password used during registration

$user = User::where('email', $email)->first();

if ($user) {
    echo "User found: " . $user->name . "\n";
    $hashedPassword = $user->getAuthPassword();
    echo "Hashed password in DB: " . $hashedPassword . "\n";
    
    $isValid = Hash::verify($password, $hashedPassword);
    echo "Hash::verify result: " . ($isValid ? 'VALID' : 'INVALID') . "\n";
    
    // Also try verifying against a freshly hashed password
    $newHash = Hash::make($password);
    echo "Freshly hashed password: " . $newHash . "\n";
    $isValidNew = Hash::verify($password, $newHash);
    echo "Hash::verify against fresh hash: " . ($isValidNew ? 'VALID' : 'INVALID') . "\n";
} else {
    echo "User not found: " . $email . "\n";
}
