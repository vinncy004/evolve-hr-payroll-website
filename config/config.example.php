<?php
/**
 * Environment Configuration Example
 * Copy this file to config.php and update with your actual values
 * NEVER commit config.php to version control
 */

return [
    // Database Configuration
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'name' => getenv('DB_NAME') ?: 'evolve',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],

    // Application Configuration
    'app' => [
        'name' => getenv('APP_NAME') ?: 'HR Management System',
        'env' => getenv('APP_ENV') ?: 'development', // development, staging, production
        'debug' => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'url' => getenv('APP_URL') ?: 'http://localhost',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Africa/Nairobi',
    ],

    // Security Configuration
    'security' => [
        'jwt_secret' => getenv('JWT_SECRET') ?: 'CHANGE_THIS_IN_PRODUCTION',
        'jwt_expiry' => (int)(getenv('JWT_EXPIRY') ?: 86400), // 24 hours in seconds
        'password_min_length' => 8,
        'password_require_special' => true,
        'password_require_number' => true,
        'password_require_uppercase' => true,
        'max_login_attempts' => 5,
        'lockout_duration' => 1800, // 30 minutes in seconds
        'session_lifetime' => 86400, // 24 hours for employer, 8 hours for employee
        'enable_2fa' => filter_var(getenv('ENABLE_2FA') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    ],

    // CORS Configuration
    'cors' => [
        'allowed_origins' => array_filter(explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost:5173')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-User-Type'],
        'allow_credentials' => true,
        'max_age' => 86400,
    ],

    // Rate Limiting
    'rate_limit' => [
        'enabled' => filter_var(getenv('RATE_LIMIT_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'max_requests' => (int)(getenv('RATE_LIMIT_MAX') ?: 100),
        'window' => (int)(getenv('RATE_LIMIT_WINDOW') ?: 60), // seconds
        'login_max' => 5, // max login attempts per window
        'login_window' => 300, // 5 minutes
    ],

    // Logging Configuration
    'logging' => [
        'enabled' => true,
        'level' => getenv('LOG_LEVEL') ?: 'error', // debug, info, warning, error
        'path' => getenv('LOG_PATH') ?: __DIR__ . '/../logs',
        'max_files' => 30, // days to keep logs
        'log_queries' => filter_var(getenv('LOG_QUERIES') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    ],

    // Email Configuration (for notifications)
    'mail' => [
        'driver' => getenv('MAIL_DRIVER') ?: 'smtp',
        'host' => getenv('MAIL_HOST') ?: 'smtp.mailtrap.io',
        'port' => (int)(getenv('MAIL_PORT') ?: 2525),
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@example.com',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'HR Management System',
    ],

    // File Upload Configuration
    'uploads' => [
        'max_size' => (int)(getenv('UPLOAD_MAX_SIZE') ?: 5242880), // 5MB in bytes
        'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
        'path' => getenv('UPLOAD_PATH') ?: __DIR__ . '/../uploads',
    ],

    // Cache Configuration
    'cache' => [
        'driver' => getenv('CACHE_DRIVER') ?: 'file', // file, redis, memcached
        'ttl' => (int)(getenv('CACHE_TTL') ?: 3600), // 1 hour
        'prefix' => getenv('CACHE_PREFIX') ?: 'hrms_',
        // Redis configuration
        'redis' => [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => (int)(getenv('REDIS_DB') ?: 0),
        ],
    ],

    // Backup Configuration
    'backup' => [
        'enabled' => filter_var(getenv('BACKUP_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'path' => getenv('BACKUP_PATH') ?: __DIR__ . '/../backups',
        'schedule' => getenv('BACKUP_SCHEDULE') ?: 'daily', // hourly, daily, weekly
        'retention_days' => (int)(getenv('BACKUP_RETENTION_DAYS') ?: 30),
    ],

    // Feature Flags
    'features' => [
        'biometric_attendance' => filter_var(getenv('FEATURE_BIOMETRIC') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'geolocation' => filter_var(getenv('FEATURE_GEOLOCATION') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'mobile_app' => filter_var(getenv('FEATURE_MOBILE_APP') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'payroll_integration' => filter_var(getenv('FEATURE_PAYROLL') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    ],

    // Monitoring & Analytics
    'monitoring' => [
        'enabled' => filter_var(getenv('MONITORING_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'sentry_dsn' => getenv('SENTRY_DSN') ?: '',
        'google_analytics' => getenv('GA_TRACKING_ID') ?: '',
    ],
];
