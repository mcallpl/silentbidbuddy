<?php
// ============================================================
// ADMIN SETUP SCRIPT — Create initial admin account
// Run this ONCE to create your admin account
// Usage: php setup-admin.php
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin-accounts.php';

// Create admin account
$username = 'mcallpl';
$password = 'amazing';
$email = 'mcallpl@gmail.com';
$full_name = 'Chip McAllister';

echo "Creating admin account...\n";
echo "Username: $username\n";
echo "Email: $email\n";
echo "Full Name: $full_name\n\n";

try {
    $admin_id = createAdminAccount($username, $password, $email, $full_name);

    if ($admin_id) {
        echo "✓ Admin account created successfully!\n";
        echo "  Admin ID: $admin_id\n";
        echo "  You can now login at /admin.php\n";
        echo "\n  Username: $username\n";
        echo "  Password: (the password you provided)\n";
    } else {
        echo "✗ Failed to create admin account.\n";
        echo "  Possible reasons:\n";
        echo "  - Username already exists\n";
        echo "  - Database connection failed\n";
        echo "  - Password too short (minimum 8 characters)\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

?>
