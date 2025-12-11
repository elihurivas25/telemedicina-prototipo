<?php
/*
  Archivo: listar_disponibilidad_medico.php
  Propósito: Devolver los bloques de disponibilidad de un médico específico.
*/

session_start();
header("Content-Type: application/json");

// Paciente o médico pueden consultar, si quieres limitar deja solo PACIENTE
if (
    !isset($_SESSION["rol"]) ||
    !in_array($_SESSION["rol"], ["PACIENTE", "MEDICO"], true)
) {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}

require_once __DIR__ . "/config.php";

// idMedico llega por la URL, por ejemplo: ?idMedico=...
$idMedico = $_GET["idMedico"] ?? "";

if (!$idMedico) {
    echo json_encode(["ok" => false, "error" => "Falta el identificador del médico"]);
    exit;
}

try {
    // En el servidor real las tablas están en minúsculas: disponibilidad
    $sql = "
        SELECT diaSemana, horaInicio, horaFin, duracionBloqueMin
        FROM disponibilidad
        WHERE idMedico = ?
        ORDER BY diaSemana, horaInicio
    ";

    $consulta = $conn->prepare($sql);
    $consulta->execute([$idMedico]);
    $bloques = $consulta->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok"      => true,
        "bloques" => $bloques
    ]);

} catch (Throwable $e) {
    error_log("Error en listar_disponibilidad_medico: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error interno del servidor"]);
}
