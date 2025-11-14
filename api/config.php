<?php
$DB_HOST = "mx58.hostgator.mx";
$DB_NAME = "amartimx_telemeddb";
$DB_USER = "amartimx_telemeddb";
$DB_PASS = "Prueba123_2025";

try {
    $conn = new PDO(
        "mysql:host=$DB_HOST;port=3306;dbname=$DB_NAME;charset=utf8",
        $DB_USER,
        $DB_PASS
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
