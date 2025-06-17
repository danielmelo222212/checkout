<?php
// core/database.php

function connect_db() {
    // DB_HOST, DB_USER, DB_PASS, DB_NAME are expected to be defined in config.php
    if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
        // In a real app, log this error and handle it more gracefully
        error_log("Database configuration constants are not defined.");
        return null;
    }

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // In a real app, you should log this error and not expose details to the user.
        // For development, this can be helpful:
        error_log("Database Connection Error: " . $e->getMessage());
        // throw new PDOException($e->getMessage(), (int)$e->getCode()); // Or handle more gracefully
        return null; // Indicate connection failure
    }
}
?>
