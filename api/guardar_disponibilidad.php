<?php
/*
  Archivo: guardar_disponibilidad.php
  Propósito: Registrar bloques de disponibilidad para el médico.
*/

session_start();
header("Content-Type: application/json");

// Solo los médicos pueden registrar disponibilidad
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "MEDICO") {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}

// Conexión a la base de datos
require_once __DIR__ . "/config.php";

// Recibir datos del formulario
$diaSemana  = $_POST["diaSemana"]  ?? null;   // puede ser "0"
$horaInicio = $_POST["horaInicio"] ?? null;
$horaFin    = $_POST["horaFin"]    ?? null;
$duracion   = $_POST["duracion"]   ?? null;

// ✅ Validaciones mínimas (sin romper Domingo=0)
if ($diaSemana === null || $diaSemana === "" || $horaInicio === null || $horaInicio === "" || $horaFin === null || $horaFin === "" || $duracion === null || $duracion === "") {
    echo json_encode(["ok" => false, "error" => "Todos los campos son obligatorios"]);
    exit;
}

// ✅ Validar día de semana (0–6)
$diaSemanaInt = (int)$diaSemana;
if ($diaSemanaInt < 0 || $diaSemanaInt > 6) {
    echo json_encode(["ok" => false, "error" => "Día de la semana inválido"]);
    exit;
}

// ✅ Validar horario
if ($horaFin <= $horaInicio) {
    echo json_encode(["ok" => false, "error" => "La hora final debe ser mayor que la hora de inicio"]);
    exit;
}

// ✅ Validar duración
$duracionInt = (int)$duracion;
if ($duracionInt < 1) {
    echo json_encode(["ok" => false, "error" => "La duración debe ser mayor a cero"]);
    exit;
}

$idUsuario = $_SESSION["idUsuario"] ?? null;
if (!$idUsuario) {
    echo json_encode(["ok" => false, "error" => "Sesión inválida"]);
    exit;
}

try {

    // 1) Obtener el idMedico a partir del usuario en sesión
    $consultaMedico = $conn->prepare("SELECT idMedico FROM medico WHERE idUsuario = ? LIMIT 1");
    $consultaMedico->execute([$idUsuario]);
    $medico = $consultaMedico->fetch(PDO::FETCH_ASSOC);

    if (!$medico) {
        echo json_encode(["ok" => false, "error" => "No se encontró información del médico"]);
        exit;
    }

    $idMedico = $medico["idMedico"];

    // 2) Insertar el bloque de disponibilidad
    $idDisponibilidad = uniqid("disp_", true);

    $insertar = $conn->prepare("
        INSERT INTO disponibilidad (idDisponibilidad, idMedico, diaSemana, horaInicio, horaFin, duracionBloqueMin)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $insertar->execute([
        $idDisponibilidad,
        $idMedico,
        $diaSemanaInt,   // ✅ usar int, ya validado
        $horaInicio,
        $horaFin,
        $duracionInt     // ✅ usar int, ya validado
    ]);

    // 3) Registrar acción en log de auditoría
    $detalle = json_encode([
        "diaSemana"   => $diaSemanaInt,
        "horaInicio"  => $horaInicio,
        "horaFin"     => $horaFin,
        "duracionMin" => $duracionInt
    ], JSON_UNESCAPED_UNICODE);

    $idLog = uniqid("log_", true);
    $ip    = $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";

    $auditoria = $conn->prepare("
        INSERT INTO logauditoria (idLogAuditoria, idUsuario, accion, detalle, timestamp, ip)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");

    $auditoria->execute([
        $idLog,
        $idUsuario,
        "CREAR_DISPONIBILIDAD",
        $detalle,
        $ip
    ]);

    echo json_encode(["ok" => true]);

} catch (Throwable $e) {
    error_log("Error en guardar_disponibilidad.php: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error interno en el servidor"]);
}
