#!/bin/bash
# install_ghost.sh - Installation complète de GHOST-OS

echo "========================================="
echo "    GHOST-OS - Installation Script      "
echo "========================================="
echo ""

# Vérification des prérequis
echo "[1] Vérification des prérequis..."

command -v php >/dev/null 2>&1 || { echo "❌ PHP requis"; exit 1; }
command -v mysql >/dev/null 2>&1 || { echo "❌ MySQL requis"; exit 1; }
command -v node >/dev/null 2>&1 || { echo "❌ Node.js requis"; exit 1; }
command -v npm >/dev/null 2>&1 || { echo "❌ npm requis"; exit 1; }
command -v git >/dev/null 2>&1 || { echo "❌ git requis"; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "❌ Composer requis"; exit 1; }

echo "✅ Tous les prérequis sont satisfaits"
echo ""

# Configuration
echo "[2] Configuration du système"

read -p "Nom de la base de données [ghost_os]: " DB_NAME
DB_NAME=${DB_NAME:-ghost_os}

read -p "Utilisateur MySQL: " DB_USER
read -sp "Mot de passe MySQL: " DB_PASS
echo ""

read -p "URL du site (ex: https://votre-domaine.com): " SITE_URL
read -p "Email admin: " ADMIN_EMAIL
read -sp "Mot de passe admin: " ADMIN_PASS
echo ""

# Création de la base de données
echo "[3] Création de la base de données..."
mysql -u $DB_USER -p$DB_PASS -e "CREATE DATABASE IF NOT EXISTS $DB_NAME"
mysql -u $DB_USER -p$DB_PASS $DB_NAME < database_advanced.sql

if [ $? -eq 0 ]; then
    echo "✅ Base de données créée"
else
    echo "❌ Erreur lors de la création de la base"
    exit 1
fi

# Configuration des fichiers
echo "[4] Configuration des fichiers..."

# Créer les dossiers nécessaires
mkdir -p uploads/{screens,camera,audio,files,thumbnails,temp}
mkdir -p logs
mkdir -p cache

# Configurer les permissions
chmod -R 755 uploads logs cache
chmod -R 777 uploads/temp

# Copier la configuration
cp config/ghost_config.example.php config/ghost_config.php

# Remplacer les valeurs
sed -i "s/define('DB_NAME', '.*');/define('DB_NAME', '$DB_NAME');/" config/ghost_config.php
sed -i "s/define('DB_USER', '.*');/define('DB_USER', '$DB_USER');/" config/ghost_config.php
sed -i "s/define('DB_PASS', '.*');/define('DB_PASS', '$DB_PASS');/" config/ghost_config.php
sed -i "s|define('GHOST_URL', '.*');|define('GHOST_URL', '$SITE_URL');|" config/ghost_config.php

echo "✅ Configuration terminée"

# Installation des dépendances PHP
echo "[5] Installation des dépendances PHP..."
composer install --no-dev

# Installation des dépendances Node.js
echo "[6] Installation des dépendances Node.js..."
npm install ws express socket.io

# Création de l'utilisateur admin
echo "[7] Création de l'utilisateur admin..."

HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_ARGON2ID);")

mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "
INSERT INTO users (username, email, password_hash, api_key, account_type, account_status, trial_used)
VALUES ('admin', '$ADMIN_EMAIL', '$HASH', '$(openssl rand -hex 32)', 'enterprise', 'active', FALSE);
"

echo "✅ Admin créé"

# Configuration du serveur WebSocket
echo "[8] Configuration du serveur WebSocket..."

cat > websocket/config.json << EOF
{
    "port": 8080,
    "ssl": {
        "enabled": false,
        "key": "",
        "cert": ""
    },
    "database": {
        "host": "localhost",
        "name": "$DB_NAME",
        "user": "$DB_USER",
        "pass": "$DB_PASS"
    }
}
EOF

# Démarrer le serveur WebSocket
echo "[9] Démarrage du serveur WebSocket..."

cd websocket
npm install
node server.js > ../logs/websocket.log 2>&1 &
echo $! > ../websocket.pid
cd ..

echo "✅ Serveur WebSocket démarré (PID: $(cat websocket.pid))"

# Configuration de la tâche cron pour le nettoyage
echo "[10] Configuration de la tâche CRON..."

cat > /etc/cron.d/ghost_cleanup << EOF
# Nettoyage des fichiers temporaires toutes les heures
0 * * * * root find $PWD/uploads/temp -type f -mmin +60 -delete

# Suppression des vieux logs (> 30 jours)
0 0 * * * root find $PWD/logs -name "*.log" -mtime +30 -delete

# Vérification des abonnements expirés
*/5 * * * * root php $PWD/cron/check_subscriptions.php
EOF

echo "✅ CRON configuré"

# Configuration finale
echo ""
echo "========================================="
echo "    INSTALLATION TERMINÉE !              "
echo "========================================="
echo ""
echo "🌐 URL d'accès: $SITE_URL/panel"
echo "📧 Email admin: $ADMIN_EMAIL"
echo "🔑 Mot de passe: [CACHÉ]"
echo ""
echo "📁 Dossiers importants:"
echo "   - Uploads: $PWD/uploads"
echo "   - Logs: $PWD/logs"
echo "   - Cache: $PWD/cache"
echo ""
echo "🚀 Serveur WebSocket: Port 8080"
echo ""
echo "⚠️  IMPORTANT:"
echo "   - Testez d'abord sur vos propres appareils"
echo "   - Configurez HTTPS pour plus de sécurité"
echo "   - Sauvegardez vos clés API"
echo ""
echo "🔧 Commandes utiles:"
echo "   - Démarrer WebSocket: npm run websocket"
echo "   - Arrêter WebSocket: kill $(cat websocket.pid)"
echo "   - Voir les logs: tail -f logs/error.log"
echo ""
echo "Merci d'avoir choisi GHOST-OS !"
