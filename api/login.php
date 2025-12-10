<?php
session_start();
header("Content-Type: application/json");

// Cargar configuración de BD
require_once __DIR__ . "/config.php";

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8",
        $DB_USER,
        $DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    echo json_encode(["ok" => false, "error" => "Error de conexión"]);
    exit;
}

// VALIDACIÓN BÁSICA
$email = $_POST["email"] ?? null;
$password = $_POST["password"] ?? null;

if (!$email || !$password) {
    echo json_encode(["ok" => false, "error" => "Datos incompletos"]);
    exit;
}

// CONSULTAR USUARIO
$stmt = $pdo->prepare("SELECT * FROM Usuario WHERE email = ?");
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
}

echo json_encode([
    "ok" => true,
    "redirect" => $redirect
]);
