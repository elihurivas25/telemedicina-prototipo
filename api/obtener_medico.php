<?php
/*
  Archivo: obtener_medico.php
  Propósito: Devolver los datos básicos de un médico para mostrarlos al paciente.
*/

session_start();
header("Content-Type: application/json");

// Solo pacientes (o ajusta si quieres que otros roles lo usen)
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "PACIENTE") {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}

require_once __DIR__ . "/config.php";

$idMedico = $_GET["idMedico"] ?? "";

if (!$idMedico) {
    echo json_encode(["ok" => false, "error" => "Falta el identificador del médico"]);
    exit;
}

try {
    // Tablas en minúsculas en el servidor: medico, usuario
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
        WHERE m.idMedico = ?
        LIMIT 1
    ";

    $consulta = $conn->prepare($sql);
    $consulta->execute([$idMedico]);
    $medico = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$medico) {
        echo json_encode(["ok" => false, "error" => "No se encontró información del médico"]);
        return;
    }

    echo json_encode([
        "ok"     => true,
        "medico" => $medico
    ]);

} catch (Throwable $e) {
    error_log("Error en obtener_medico: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error interno del servidor"]);
}
