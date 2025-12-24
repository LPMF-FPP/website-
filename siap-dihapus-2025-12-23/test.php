<?php
// test_connection.php

// Your database credentials
$host = "127.0.0.1";
$port = "5432";
$dbname = "PengujianLPMF";
$username = "postgres";
$password = "LPMFjaya123";

try {
    // Create PDO connection
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password);

    // Set error mode to exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Successfully connected to PostgreSQL database: $dbname\n";
    echo "Connection details:\n";
    echo "- Host: $host\n";
    echo "- Port: $port\n";
    echo "- Database: $dbname\n";
    echo "- Username: $username\n";

} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    echo "Make sure:\n";
    echo "1. PostgreSQL is running\n";
    echo "2. Database '$dbname' exists\n";
    echo "3. Username and password are correct\n";
    echo "4. PHP PostgreSQL extensions are installed\n";
}
?>
