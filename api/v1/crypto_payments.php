<?php
// api/v1/crypto_payments.php
require_once '../../config/ghost_config.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ============================================
// VÉRIFICATION API KEY
// ============================================
$headers = getallheaders();
$api_key = $headers['X-API-Key'] ?? '';

if ($api_key) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE api_key = ?");
    $stmt->execute([$api_key]);
    $user = $stmt->fetch();
}

// ============================================
// GÉNÉRER UNE ADRESSE DE PAIEMENT
// ============================================
if ($method === 'POST' && ($input['action'] ?? '') === 'generate_address') {
    $plan = $input['plan'] ?? 'premium';
    
    if (!isset(PRICING[$plan])) {
        http_response_code(400);
        echo json_encode(['error' => 'Plan invalide']);
        exit;
    }
    
    $currency = $input['currency'] ?? 'USDT_TRC20';
    $amount = PRICING[$plan]['price_usdt'];
    
    // Générer une adresse unique (dans un vrai système, vous utiliseriez une API)
    $address = '';
    $network = '';
    
    switch ($currency) {
        case 'USDT_TRC20':
            $address = USDT_TRC20_ADDRESS;
            $network = 'TRC20';
            break;
        case 'USDT_ERC20':
            $address = USDT_ERC20_ADDRESS;
            $network = 'ERC20';
            break;
        case 'BTC':
            $address = BTC_ADDRESS;
            $network = 'Bitcoin';
            break;
        case 'ETH':
            $address = ETH_ADDRESS;
            $network = 'Ethereum';
            break;
    }
    
    // Générer un ID de transaction unique
    $tx_ref = 'GHOST_' . bin2hex(random_bytes(16));
    
    // Sauvegarder en session/BDD
    $_SESSION['pending_payment'] = [
        'tx_ref' => $tx_ref,
        'plan' => $plan,
        'amount' => $amount,
        'currency' => $currency,
        'address' => $address,
        'created_at' => time(),
        'user_id' => $user['id'] ?? null
    ];
    
    echo json_encode([
        'success' => true,
        'payment' => [
            'address' => $address,
            'amount' => $amount,
            'currency' => $currency,
            'network' => $network,
            'tx_ref' => $tx_ref,
            'qr_code' => "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=$address&choe=UTF-8",
            'expires_in' => 3600 // 1 heure
        ]
    ]);
    exit;
}

// ============================================
// VÉRIFIER UN PAIEMENT
// ============================================
if ($method === 'POST' && ($input['action'] ?? '') === 'verify_payment') {
    $tx_ref = $input['tx_ref'] ?? '';
    $tx_hash = $input['tx_hash'] ?? '';
    
    if (!$tx_ref || !$tx_hash) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètres manquants']);
        exit;
    }
    
    // Vérifier la transaction sur la blockchain
    $verified = verifyTransaction($tx_hash, $input['currency'] ?? 'USDT_TRC20');
    
    if (!$verified) {
        echo json_encode(['success' => false, 'message' => 'Transaction non trouvée']);
        exit;
    }
    
    // Vérifier le montant
    if ($verified['amount'] < $verified['expected_amount']) {
        echo json_encode(['success' => false, 'message' => 'Montant insuffisant']);
        exit;
    }
    
    // Récupérer le plan depuis la session
    $pending = $_SESSION['pending_payment'] ?? null;
    if (!$pending || $pending['tx_ref'] !== $tx_ref) {
        http_response_code(400);
        echo json_encode(['error' => 'Session expirée']);
        exit;
    }
    
    // Activer l'abonnement
    $user_id = $pending['user_id'];
    $plan = $pending['plan'];
    $duration = PRICING[$plan]['duration_days'];
    
    $pdo->beginTransaction();
    
    try {
        // Enregistrer la transaction
        $stmt = $pdo->prepare("
            INSERT INTO crypto_transactions (user_id, tx_hash, from_address, to_address, amount, currency, status, plan_name, days_added)
            VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $tx_hash,
            $verified['from'],
            $verified['to'],
            $verified['amount'],
            $pending['currency'],
            $plan,
            $duration
        ]);
        
        // Mettre à jour l'utilisateur
        $stmt = $pdo->prepare("
            UPDATE users 
            SET account_type = ?,
                subscription_id = ?,
                subscription_start = NOW(),
                subscription_end = DATE_ADD(NOW(), INTERVAL ? DAY),
                auto_renew = TRUE
            WHERE id = ?
        ");
        $stmt->execute([$plan, $tx_ref, $duration, $user_id]);
        
        $pdo->commit();
        
        logActivity($pdo, $user_id, null, 'payment_success', [
            'plan' => $plan,
            'amount' => $verified['amount'],
            'tx_hash' => $tx_hash
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Paiement confirmé',
            'subscription_end' => date('Y-m-d H:i:s', strtotime("+$duration days"))
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de l\'activation']);
    }
    exit;
}

// ============================================
// VÉRIFIER UNE TRANSACTION USDT (TRC20)
// ============================================
function verifyTransaction($tx_hash, $currency) {
    // Simulation - À remplacer par un vrai appel API
    // Pour TRC20: https://api.trongrid.io/v1/transactions/$tx_hash
    // Pour ERC20: https://api.etherscan.io/api?module=account&action=tokentx&txhash=$tx_hash
    
    $expected_amount = $_SESSION['pending_payment']['amount'] ?? 0;
    $expected_address = $_SESSION['pending_payment']['address'] ?? '';
    
    // Simuler une vérification réussie (à remplacer par du vrai code)
    return [
        'confirmed' => true,
        'amount' => $expected_amount,
        'expected_amount' => $expected_amount,
        'from' => 'TFromAddress',
        'to' => $expected_address,
        'hash' => $tx_hash,
        'confirmations' => 32
    ];
}

// ============================================
// VÉRIFIER LE STATUT D'UN ABONNEMENT
// ============================================
if ($method === 'GET' && ($_GET['action'] ?? '') === 'check_subscription') {
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Non authentifié']);
        exit;
    }
    
    $now = time();
    $trial_end = strtotime($user['trial_end']);
    $sub_end = strtotime($user['subscription_end']);
    
    $is_active = ($trial_end && $trial_end > $now) || ($sub_end && $sub_end > $now);
    
    echo json_encode([
        'is_active' => $is_active,
        'account_type' => $user['account_type'],
        'trial_end' => $user['trial_end'],
        'subscription_end' => $user['subscription_end'],
        'days_left' => $is_active ? floor(($sub_end ?: $trial_end - $now) / 86400) : 0,
        'features' => PRICING[$user['account_type']]['features']
    ]);
    exit;
}

// ============================================
// GÉNÉRER UN CODE DE PARRAINAGE
// ============================================
if ($method === 'POST' && ($input['action'] ?? '') === 'generate_referral') {
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Non authentifié']);
        exit;
    }
    
    $discount = $input['discount'] ?? 10;
    $max_uses = $input['max_uses'] ?? 100;
    
    $code = 'GHOST' . strtoupper(bin2hex(random_bytes(4)));
    
    $stmt = $pdo->prepare("
        INSERT INTO referral_codes (user_id, code, discount_percent, max_uses)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$user['id'], $code, $discount, $max_uses]);
    
    echo json_encode([
        'success' => true,
        'code' => $code,
        'discount' => $discount . '%',
        'url' => GHOST_URL . '/register?ref=' . $code
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Méthode non autorisée']);
?>
