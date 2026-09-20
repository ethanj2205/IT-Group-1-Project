<?php

/*
 * MediQueue SA - Database Connection
 */

$host = "localhost";
$db   = "mediqueue_sa";
$user = "root";
$pass = "";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Connect to MySQL first so the application can create the database if needed.
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace("`", "``", $db) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . str_replace("`", "``", $db) . "`");

    // Automatically install/repair the MediQueue schema and demo records.
    // This prevents the common "Patient ID 1 does not exist" error when
    // schema.sql has not yet been run manually.
    $schemaFile = __DIR__ . DIRECTORY_SEPARATOR . "schema.sql";
    if (is_file($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        $sql = preg_replace('/^\s*CREATE DATABASE IF NOT EXISTS.*?;\s*/ims', '', $sql, 1);
        $sql = preg_replace('/^\s*USE\s+[^;]+;\s*/ims', '', $sql, 1);

        // Execute one statement at a time. The project schema does not use
        // stored procedures or DELIMITER blocks, so this is safe here.
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    header("Content-Type: application/json; charset=utf-8");

    echo json_encode([
        "success" => false,
        "error" => "Database connection failed. Check that MySQL is running and the mediqueue_sa database exists."
    ]);
    exit;
}
?>
