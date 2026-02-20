<?php
// check_installation.php
require_once 'config/ghost_config.php';

$checks = [
    'PHP Version' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'OpenSSL' => extension_loaded('openssl'),
    'JSON' => extension_loaded('json'),
    'GD' => extension_loaded('gd'),
    'ZIP' => extension_loaded('zip'),
    'Curl' => extension_loaded('curl'),
    'FileInfo' => extension_loaded('fileinfo')
];

$directories = [
    'uploads/' => is_writable('uploads/'),
    'uploads/screens/' => is_writable('uploads/screens/'),
    'uploads/camera/' => is_writable('uploads/camera/'),
    'uploads/audio/' => is_writable('uploads/audio/'),
    'uploads/files/' => is_writable('uploads/files/'),
    'logs/' => is_writable('logs/'),
    'cache/' => is_writable('cache/')
];

$database = false;
try {
    $pdo->query("SELECT 1");
    $database = true;
} catch (Exception $e) {
    $database = false;
}

$websocket = false;
$fp = @fsockopen('localhost', 8080, $errno, $errstr, 1);
if ($fp) {
    $websocket = true;
    fclose($fp);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GHOST-OS - Vérification</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">🔍 Vérification de l'installation</h1>
        
        <!-- Extensions PHP -->
        <div class="bg-gray-800 rounded-lg p-6 mb-4">
            <h2 class="text-xl font-bold mb-4">Extensions PHP</h2>
            <div class="grid grid-cols-2 gap-3">
                <?php foreach ($checks as $name => $passed): ?>
                <div class="flex items-center">
                    <span class="mr-2 <?= $passed ? 'text-green-500' : 'text-red-500' ?>">
                        <?= $passed ? '✓' : '✗' ?>
                    </span>
                    <span class="text-gray-300"><?= $name ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Permissions -->
        <div class="bg-gray-800 rounded-lg p-6 mb-4">
            <h2 class="text-xl font-bold mb-4">Permissions des dossiers</h2>
            <div class="grid grid-cols-2 gap-3">
                <?php foreach ($directories as $dir => $writable): ?>
                <div class="flex items-center">
                    <span class="mr-2 <?= $writable ? 'text-green-500' : 'text-red-500' ?>">
                        <?= $writable ? '✓' : '✗' ?>
                    </span>
                    <span class="text-gray-300"><?= $dir ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Base de données -->
        <div class="bg-gray-800 rounded-lg p-6 mb-4">
            <h2 class="text-xl font-bold mb-4">Base de données</h2>
            <div class="flex items-center">
                <span class="mr-2 <?= $database ? 'text-green-500' : 'text-red-500' ?>">
                    <?= $database ? '✓' : '✗' ?>
                </span>
                <span class="text-gray-300">
                    <?= $database ? 'Connexion réussie' : 'Échec de connexion' ?>
                </span>
            </div>
        </div>
        
        <!-- WebSocket -->
        <div class="bg-gray-800 rounded-lg p-6 mb-4">
            <h2 class="text-xl font-bold mb-4">Serveur WebSocket</h2>
            <div class="flex items-center">
                <span class="mr-2 <?= $websocket ? 'text-green-500' : 'text-red-500' ?>">
                    <?= $websocket ? '✓' : '✗' ?>
                </span>
                <span class="text-gray-300">
                    <?= $websocket ? 'Serveur actif (port 8080)' : 'Serveur non détecté' ?>
                </span>
            </div>
            <?php if (!$websocket): ?>
            <p class="text-sm text-yellow-500 mt-2">
                ℹ️ Lancez: <code class="bg-gray-900 px-2 py-1 rounded">node websocket/server.js</code>
            </p>
            <?php endif; ?>
        </div>
        
        <!-- Résumé -->
        <div class="bg-gray-800 rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4">Résumé</h2>
            <?php
            $allGood = !in_array(false, $checks) && 
                      !in_array(false, $directories) && 
                      $database && $websocket;
            ?>
            <div class="text-center p-4 rounded-lg <?= $allGood ? 'bg-green-600' : 'bg-yellow-600' ?>">
                <p class="text-xl font-bold">
                    <?= $allGood ? '✅ Installation réussie !' : '⚠️ Certains problèmes nécessitent votre attention' ?>
                </p>
                <?php if ($allGood): ?>
                <p class="mt-2">Vous pouvez maintenant accéder au panel d'administration</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
