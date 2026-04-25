<?php
// =============================================================
// KYZ Logística – Configuración de entorno
// Copia este archivo como config.local.php y ajusta los valores
// NUNCA subas credenciales reales a control de versiones
// =============================================================

require_once __DIR__ . '/Env.php';
Env::load(__DIR__ . '/../.env');

// Base de datos
define('DB_HOST',    Env::get('DB_HOST', 'localhost'));
define('DB_PORT',    Env::get('DB_PORT', '3306'));
define('DB_NAME',    Env::get('DB_NAME', 'kyz_logistic'));
define('DB_USER',    Env::get('DB_USER', 'root'));
define('DB_PASS',    Env::get('DB_PASS', ''));
define('DB_CHARSET', Env::get('DB_CHARSET', 'utf8mb4'));

// JWT
define('JWT_SECRET', Env::get('JWT_SECRET', 'CAMBIA_ESTE_SECRETO_EN_PRODUCCION'));
define('JWT_EXPIRY', Env::getInt('JWT_EXPIRY', 28800)); // 8 horas en segundos

// Entorno
define('APP_ENV',  Env::get('APP_ENV', 'development'));
define('APP_URL',  Env::get('APP_URL', 'http://localhost'));

// Upload de archivos
define('UPLOAD_MAX_MB', Env::getInt('UPLOAD_MAX_MB', 10));
define('UPLOAD_DIR',    __DIR__ . '/../storage/uploads/');

// DeepSeek
define('DEEPSEEK_ENABLED',     Env::getBool('DEEPSEEK_ENABLED', false));
define('DEEPSEEK_API_KEY',     Env::get('DEEPSEEK_API_KEY', ''));
define('DEEPSEEK_BASE_URL',    Env::get('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'));
define('DEEPSEEK_MODEL',       Env::get('DEEPSEEK_MODEL', 'deepseek-chat'));
define('DEEPSEEK_TIMEOUT',     Env::getInt('DEEPSEEK_TIMEOUT', 25));
define('DEEPSEEK_TEMPERATURE', Env::getFloat('DEEPSEEK_TEMPERATURE', 0.2));

// Geocoding
define('GEOCODING_ENABLED',        Env::getBool('GEOCODING_ENABLED', true));
define('GEOCODING_PROVIDER',       Env::get('GEOCODING_PROVIDER', 'nominatim'));
define('GEOCODING_BASE_URL',       Env::get('GEOCODING_BASE_URL', 'https://nominatim.openstreetmap.org'));
define('GEOCODING_GOOGLE_BASE_URL',Env::get('GEOCODING_GOOGLE_BASE_URL', 'https://maps.googleapis.com/maps/api/geocode/json'));
define('GEOCODING_GOOGLE_API_KEY', Env::get('GEOCODING_GOOGLE_API_KEY', ''));
define('GEOCODING_USER_AGENT',     Env::get('GEOCODING_USER_AGENT', 'kyz-logistic-geocoder/1.0'));
define('GEOCODING_TIMEOUT',        Env::getInt('GEOCODING_TIMEOUT', 15));
define('GEOCODING_SLEEP_MS',       Env::getInt('GEOCODING_SLEEP_MS', 1100));
define('GEOCODING_TARGET_CITY',    Env::get('GEOCODING_TARGET_CITY', 'Santa Fe'));
define('GEOCODING_TARGET_PROVINCE',Env::get('GEOCODING_TARGET_PROVINCE', 'Santa Fe'));
define('GEOCODING_TARGET_COUNTRY', Env::get('GEOCODING_TARGET_COUNTRY', 'Argentina'));
define('GEOCODING_FALLBACK_LAT',   Env::getFloat('GEOCODING_FALLBACK_LAT', -31.6333));
define('GEOCODING_FALLBACK_LNG',   Env::getFloat('GEOCODING_FALLBACK_LNG', -60.7000));

// Route Optimization V2
define('ROUTE_V2_ENABLED', Env::getBool('ROUTE_V2_ENABLED', true));
define('ROUTE_WEIGHT_DISTANCE', Env::getFloat('ROUTE_WEIGHT_DISTANCE', 0.55));
define('ROUTE_WEIGHT_SERVICE', Env::getFloat('ROUTE_WEIGHT_SERVICE', 0.25));
define('ROUTE_WEIGHT_SECTION', Env::getFloat('ROUTE_WEIGHT_SECTION', 0.15));
define('ROUTE_WEIGHT_GEOCODED', Env::getFloat('ROUTE_WEIGHT_GEOCODED', 0.05));
define('LOTES_V2_ENABLED', Env::getBool('LOTES_V2_ENABLED', true));
