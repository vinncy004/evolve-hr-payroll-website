<?php
/**
 * Clear opcache and test endpoint
 */

// Try to clear opcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "Opcache cleared\n";
} else {
    echo "Opcache not available\n";
}

// Test the change password endpoint
echo "\nTesting change password endpoint...\n";
echo "URL: /backend/api/employee/auth.php?action=change-password\n";
echo "\nTo test:\n";
echo "1. Login as john.doe / Employee@2025!\n";
echo "2. You'll be redirected to change password\n";
echo "3. Fill in the form and submit\n";
echo "\nIf you see 'Username and password required', restart Apache from XAMPP Control Panel\n";
?>
