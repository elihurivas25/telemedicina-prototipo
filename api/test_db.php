<?php
require_once "config.php";

try {
    $stmt = $conn->query("SELECT idUsuario, nombre FROM usuario LIMIT 5");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Conexión exitosa ✔️</h2>";
    echo "<pre>";
    print_r($usuarios);
    echo "</pre>";
} catch (Exception $e) {
    echo "Error en consulta: " . $e->getMessage();
}
