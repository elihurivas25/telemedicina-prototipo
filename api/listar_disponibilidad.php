<?php
/*
  Archivo: listar_disponibilidad.php
  Propósito: Devolver los bloques de disponibilidad del médico en sesión.
*/

session_start();
header("Content-Type: application/json");

// Solo los médicos pueden consultar su agenda de disponibilidad
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "MEDICO") {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}

require_once __DIR__ . "/config.php";

$idUsuario = $_SESSION["idUsuario"];

try {
    // 1) Obtener idMedico a partir del usuario en sesión
    $consultaMedico = $conn->prepare("SELECT idMedico FROM medico WHERE idUsuario = ?");
    $consultaMedico->execute([$idUsuario]);
    $medico = $consultaMedico->fetch(PDO::FETCH_ASSOC);

    if (!$medico) {
        echo json_encode(["ok" => false, "error" => "No se encontró información del médico"]);
        exit;
    }

    $idMedico = $medico["idMedico"];

    // 2) Consultar los bloques de disponibilidad de ese médico
    $consultaDisp = $conn->prepare("
        SELECT diaSemana, horaInicio, horaFin, duracionBloqueMin
        FROM disponibilidad
        WHERE idMedico = ?
        ORDER BY diaSemana, horaInicio
    ");
    $consultaDisp->execute([$idMedico]);
    $bloques = $consultaDisp->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok"      => true,
        "bloques" => $bloques
    ]);

} catch (Throwable $e) {
    error_log("Error en listar_disponibilidad.php: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error interno del servidor"]);
}
