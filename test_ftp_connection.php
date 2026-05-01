<?php
// Test FTP Connection to AlwaysData
$ftp_server = "ftp-campusmarketplace.alwaysdata.net";
$ftp_username = "campusmarketplace";
$ftp_password = "Brooklyn@2006"; // Update this if needed

echo "Testing FTP connection to AlwaysData...\n";
echo "Server: $ftp_server\n";
echo "Username: $ftp_username\n\n";

// Try to connect
$ftp_conn = ftp_connect($ftp_server) or die("Could not connect to $ftp_server");

echo "✅ FTP server connection successful!\n";

// Try to login
$login = ftp_login($ftp_conn, $ftp_username, $ftp_password);

if ($login) {
    echo "✅ FTP login successful!\n";
    
    // Get current directory
    $current_dir = ftp_pwd($ftp_conn);
    echo "Current directory: $current_dir\n";
    
    // List files
    echo "Files in current directory:\n";
    $files = ftp_nlist($ftp_conn, ".");
    if ($files) {
        foreach ($files as $file) {
            echo "- $file\n";
        }
    } else {
        echo "No files found or cannot list directory.\n";
    }
    
} else {
    echo "❌ FTP login failed!\n";
    echo "Error: " . ftp_last_error($ftp_conn) . "\n";
    echo "Please check your username and password.\n";
}

// Close connection
ftp_close($ftp_conn);
echo "\nFTP test completed.\n";
?>