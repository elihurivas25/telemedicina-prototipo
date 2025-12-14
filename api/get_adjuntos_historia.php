<?php
/*
  Archivo: get_adjuntos_historia.php
  Propósito:
  - Devolver en formato JSON la lista de archivos adjuntos
    asociados a la historia clínica de un paciente.
  - Se usa desde la vista de Historia Clínica (botón "Adjuntos").

  Notas:
  - Solo rol MEDICO puede consultar adjuntos de cualquier paciente.
*/

session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "MEDICO") {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}

require_once __DIR__ . "/config.php";

$idPaciente = $_GET["idPaciente"] ?? null;

if (!$idPaciente) {
    echo json_encode(["ok" => false, "error" => "Falta el identificador del paciente"]);
    exit;
}

try {
    // Buscar historia clínica asociada
    $sqlHC = $conn->prepare("
        SELECT idHistoriaClinica
        FROM historiaclinica
        WHERE idPaciente = ?
        LIMIT 1
    ");
    $sqlHC->execute([$idPaciente]);
    $filaHC = $sqlHC->fetch(PDO::FETCH_ASSOC);

    if (!$filaHC) {
        // Sin historia → sin adjuntos
        echo json_encode(["ok" => true, "adjuntos" => []]);
        exit;
    }

    $idHistoriaClinica = $filaHC["idHistoriaClinica"];

    // Obtener adjuntos
    $sqlAdj = $conn->prepare("
        SELECT idAdjunto, tipo, url, tamanoMb
        FROM adjunto
        WHERE idHistoriaClinica = ?
        ORDER BY idAdjunto ASC
    ");
    $sqlAdj->execute([$idHistoriaClinica]);
    $adjuntos = $sqlAdj->fetchAll(PDO::FETCH_ASSOC);

    // Calcular fecha aproximada a partir del archivo (si existe)
    foreach ($adjuntos as &$adj) {
        $rutaRelativa = $adj["url"]; // Ej. /uploads/historia/adj1_analisis.pdf
        $rutaFisica   = $_SERVER["DOCUMENT_ROOT"] . $rutaRelativa;

        $fecha = null;
        if (is_file($rutaFisica)) {
            $ts = filemtime($rutaFisica);
            $fecha = date("Y-m-d H:i:s", $ts);
        }
        $adj["fechaArchivo"] = $fecha;
    }
    unset($adj);

    echo json_encode([
        "ok" => true,
        "adjuntos" => $adjuntos
    ]);

} catch (Throwable $e) {
    error_log("Error en get_adjuntos_historia.php: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error al consultar los adjuntos"]);
}
