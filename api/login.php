<?php
session_start();

// IMPORTANTE: evitar que los errores se impriman en la respuesta JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // no mostrar errores al navegador
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

header("Content-Type: application/json; charset=utf-8");

// Cargar configuración de BD (crea $conn)
require_once __DIR__ . "/config.php";

// Verificar que $conn exista y sea PDO
if (!isset($conn) || !($conn instanceof PDO)) {
    echo json_encode(["ok" => false, "error" => "Error de conexión (config)"]);
    exit;
}

$pdo = $conn; // usamos la conexión existente

// VALIDACIÓN BÁSICA
$email = $_POST["email"] ?? null;
$password = $_POST["password"] ?? null;

if (!$email || !$password) {
    echo json_encode(["ok" => false, "error" => "Datos incompletos"]);
    exit;
}

try {
    // CONSULTAR USUARIO
    $stmt = $pdo->prepare("SELECT * FROM usuario WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["ok" => false, "error" => "Usuario no encontrado"]);
        exit;
    }

    // VALIDAR PASSWORD
    if (!password_verify($password, $user["passwordHash"])) {
        echo json_encode(["ok" => false, "error" => "Contraseña incorrecta"]);
        exit;
    }

    // CREAR SESIÓN
    $_SESSION["idUsuario"] = $user["idUsuario"];
    $_SESSION["rol"]       = $user["rol"];
    $_SESSION["nombre"]    = $user["nombre"];

    // REDIRECCIÓN POR ROL
    $redirect = "";

    switch ($user["rol"]) {
        case "PACIENTE":
            $redirect = "/dashboard-paciente";
            break;
        case "MEDICO":
            $redirect = "/dashboard-medico";
            break;
        case "ADMIN":
            $redirect = "/panel-admin";
            break;
        default:
            // rol inesperado
            $redirect = "/";
            break;
    }

    echo json_encode([
        "ok" => true,
        "redirect" => $redirect
    ]);

} catch (Throwable $e) {
    // Cualquier excepción se registra en el log, pero no se muestra al usuario
    error_log("Error en login.php: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error interno en el servidor"]);
}
