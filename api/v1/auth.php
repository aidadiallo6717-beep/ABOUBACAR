<?php
// api/v1/auth.php
require_once '../../config/ghost_config.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ============================================
// REGISTER
// ============================================
if ($method === 'POST' && ($input['action'] ?? '') === 'register') {
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $input['password'] ?? '';
    $phone = preg_replace('/[^0-9+]/', '', $input['phone'] ?? '');
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', $input['username'] ?? '');
    
    // Fingerprint matériel
    $device_fingerprint = generateDeviceFingerprint([
        'android_id' => $input['android_id'] ?? '',
        'serial' => $input['serial'] ?? '',
        'mac' => $input['mac_address'] ?? '',
        'imei' => $input['imei'] ?? '',
        'google_ad_id' => $input['google_ad_id'] ?? ''
    ]);
    
    $hardware_id = $input['hardware_id'] ?? '';
    $android_id = $input['android_id'] ?? '';
    
    // Validation
    if (!$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Email invalide']);
        exit;
    }
    
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Mot de passe trop court (min 8 caractères)']);
        exit;
    }
    
    // Vérifier les bans
    if (isBanned($pdo, 'email', $email)) {
        http_response_code(403);
        echo json_encode(['error' => 'Compte banni']);
        exit;
    }
    
    if ($phone && isBanned($pdo, 'phone', $phone)) {
        http_response_code(403);
        echo json_encode(['error' => 'Numéro banni']);
        exit;
    }
    
    // Vérifier si existe déjà
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$email, $phone]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email ou téléphone déjà utilisé']);
        exit;
    }
    
    // Créer l'utilisateur
    $password_hash = password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 2048, 'time_cost' => 4, 'threads' => 3]);
    $api_key = generateApiKey();
    
    $stmt = $pdo->prepare("
        INSERT INTO users (
            username, email, phone, password_hash, api_key,
            device_fingerprint, hardware_id, android_id,
            last_ip, last_user_agent,
            trial_start, trial_end, trial_used
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), TRUE)
    ");
    
    try {
        $stmt->execute([
            $username ?: explode('@', $email)[0],
            $email,
            $phone,
            $password_hash,
            $api_key,
            $device_fingerprint,
            $hardware_id,
            $android_id,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        $userId = $pdo->lastInsertId();
        
        logActivity($pdo, $userId, null, 'user_registered', ['email' => $email]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Inscription réussie. Vous avez 3 jours d\'essai.',
            'api_key' => $api_key,
            'trial_end' => date('Y-m-d H:i:s', strtotime('+3 days'))
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de l\'inscription']);
    }
    exit;
}

// ============================================
// LOGIN
// ============================================
if ($method === 'POST' && ($input['action'] ?? '') === 'login') {
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Email ou mot de passe incorrect']);
        exit;
    }
    
    // Vérifier le statut
    if ($user['account_status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['error' => 'Compte ' . $user['account_status']]);
        exit;
    }
    
    // Vérifier si banni
    if (isBanned($pdo, 'user_id', $user['id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Compte banni']);
        exit;
    }
    
    // Mettre à jour la connexion
    $stmt = $pdo->prepare("
        UPDATE users SET last_login = NOW(), last_ip = ?, last_user_agent = ? 
        WHERE id = ?
    ");
    $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, $user['id']]);
    
    logActivity($pdo, $user['id'], null, 'user_login', null);
    
    echo json_encode([
        'success' => true,
        'api_key' => $user['api_key'],
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'account_type' => $user['account_type'],
            'trial_end' => $user['trial_end'],
            'subscription_end' => $user['subscription_end'],
            'devices_count' => $user['total_devices'],
            'max_devices' => PRICING[$user['account_type']]['max_devices']
        ]
    ]);
    exit;
}

// ============================================
// VERIFICATION DE LA CLÉ API
// ============================================
if ($method === 'GET' && ($_GET['action'] ?? '') === 'verify') {
    $headers = getallheaders();
    $api_key = $headers['X-API-Key'] ?? '';
    
    if (!$api_key) {
        http_response_code(401);
        echo json_encode(['error' => 'API key requise']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE api_key = ?");
    $stmt->execute([$api_key]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(403);
        echo json_encode(['error' => 'API key invalide']);
        exit;
    }
    
    echo json_encode([
        'valid' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'account_type' => $user['account_type'],
            'subscription_end' => $user['subscription_end']
        ]
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Méthode non autorisée']);
?>
