<?php

/**
 * Eliskapp – First-time seed script.
 * Creates initial admin user.
 *
 * Run once from CLI:
 *   php db/seed.php
 *
 * Or via browser: http://yoursite/db/seed.php
 * (Remove or protect this file after use!)
 */

declare(strict_types=1);

// Load Nette autoloader
require dirname(__DIR__) . '/vendor/autoload.php';

$container = App\Bootstrap::boot()->createContainer();

/** @var Nette\Database\Explorer $db */
$db = $container->getByType(Nette\Database\Explorer::class);

/** @var App\Model\UserRepository $userRepo */
$userRepo = $container->getByType(App\Model\UserRepository::class);

/** @var App\Model\SettingsRepository $settingsRepo */
$settingsRepo = $container->getByType(App\Model\SettingsRepository::class);

// ---------------------------------------------------------------
// Configuration – edit these before running!
// ---------------------------------------------------------------
$adminUsername = 'admin';
$adminPassword = 'yourpassword';
$adminDisplayName = 'Administrátor';

// ---------------------------------------------------------------
// Check if admin already exists
// ---------------------------------------------------------------
$existing = $userRepo->findByUsername($adminUsername);
if ($existing) {
    echo "Uživatel '$adminUsername' již existuje (UID: {$existing->uid}).\n";
    echo "Seedování přeskočeno.\n";
    exit(0);
}

// ---------------------------------------------------------------
// Create user
// ---------------------------------------------------------------
$user = $userRepo->create([
    'username'     => $adminUsername,
    'password'     => $adminPassword,
    'display_name' => $adminDisplayName,
    'is_active'    => 1,
]);

$settingsRepo->initDefaults($user->uid);

echo "=================================================\n";
echo " Eliskapp – Uživatel vytvořen\n";
echo "=================================================\n";
echo " Uživatelské jméno : $adminUsername\n";
echo " Heslo             : $adminPassword\n";
echo " UID               : {$user->uid}\n";
echo " Odkaz pro dítě    : /go/{$user->uid}\n";
echo "=================================================\n";
echo " DŮLEŽITÉ: Smažte nebo ochraňte tento soubor!\n";
echo "=================================================\n";
