<?php
/**
 * Environment Variable Loader
 * Loads variables from .env file into $_ENV and getenv()
 */

class EnvLoader
{
    private static $loaded = false;
    private static $envVars = [];

    /**
     * Load .env file
     */
    public static function load($path = null)
    {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            $path = __DIR__ . '/../.env';
        }

        if (!file_exists($path)) {
            // In production, .env must exist
            if (getenv('APP_ENV') === 'production') {
                throw new Exception('.env file not found in production environment');
            }
            // In development, use defaults
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes
                $value = trim($value, '"\'');

                // Set environment variable
                if (!array_key_exists($key, $_ENV)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                    self::$envVars[$key] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get environment variable
     */
    public static function get($key, $default = null)
    {
        self::load();

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (isset(self::$envVars[$key])) {
            return self::$envVars[$key];
        }

        return $default;
    }

    /**
     * Get as boolean
     */
    public static function getBool($key, $default = false)
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get as integer
     */
    public static function getInt($key, $default = 0)
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return (int)$value;
    }
}

// Auto-load when included
EnvLoader::load();
