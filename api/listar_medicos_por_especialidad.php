<?php
/*
  Archivo: listar_medicos_por_especialidad.php
  Propósito: Devolver los médicos disponibles para una especialidad dada.
*/

session_start();
header("Content-Type: application/json");

// Solo los pacientes deben consumir este endpoint
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "PACIENTE") {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}

require_once __DIR__ . "/config.php";

// Leer la especialidad desde la URL (?especialidad=General)
$especialidad = $_GET["especialidad"] ?? "";

$especialidadesPermitidas = [
    "General",
    "Pediatría",
    "Psicología",
    "Familiar",
    "Geriatría",
    "Cardiología"
];

if (!in_array($especialidad, $especialidadesPermitidas, true)) {
    echo json_encode(["ok" => false, "error" => "Especialidad no válida"]);
    exit;
}

try {
    // IMPORTANTE: en el servidor las tablas están en minúsculas (medico, usuario)
    $sql = "
        SELECT 
            m.idMedico,
            u.nombre,
            u.email,
            m.cedulaProfesional,
            m.especialidad,
            m.bio
        FROM medico m
        INNER JOIN usuario u ON m.idUsuario = u.idUsuario
        WHERE m.especialidad = ?
    ";

    $consulta = $conn->prepare($sql);
    $consulta->execute([$especialidad]);
    $medicos = $consulta->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok"      => true,
        "medicos" => $medicos
    ]);

} catch (Throwable $e) {
    // Registrar el error en el log interno del servidor
    error_log("Error en listar_medicos_por_especialidad: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error interno del servidor"]);
}
