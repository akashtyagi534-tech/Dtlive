<?php

$host = getenv('DB_HOST') ?: '';
$port = (int) (getenv('DB_PORT') ?: 3306);
$db   = getenv('DB_DATABASE') ?: '';
$user = getenv('DB_USERNAME') ?: '';
$pass = getenv('DB_PASSWORD') ?: '';
$sqlFile = __DIR__ . '/dt_live.sql';

if ($host === '' || $db === '' || $user === '' || !file_exists($sqlFile)) {
    exit(0);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli($host, $user, $pass, $db, $port);
    $mysqli->set_charset('utf8mb4');

    $res = $mysqli->query('SHOW TABLES');
    if ($res && $res->num_rows > 0) {
        echo "Database already initialized (" . $res->num_rows . " tables).\n";
        exit(0);
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        exit(0);
    }

    if ($mysqli->multi_query($sql)) {
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
    }

    echo "Imported dt_live.sql into MySQL successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "DB auto-init skipped: " . $e->getMessage() . "\n");
}
