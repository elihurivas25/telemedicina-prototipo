<?php
/*
  Archivo: registrar_consentimiento.php
  Propósito: Registrar la aceptación del Consentimiento Informado
  por parte del paciente.
*/

session_start();
header("Content-Type: application/json");

// Solo PACIENTE puede aceptar el consentimiento
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "PACIENTE") {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}

require_once __DIR__ . "/config.php";

$idUsuario = $_SESSION["idUsuario"] ?? null;

if (!$idUsuario) {
    echo json_encode(["ok" => false, "error" => "No se encontró el usuario en sesión"]);
    exit;
}

try {
    // 1) Obtener idPaciente a partir de idUsuario
    // (recuerda que en el servidor la tabla es 'paciente' en minúsculas)
    $sqlPaciente = $conn->prepare("SELECT idPaciente FROM paciente WHERE idUsuario = ?");
    $sqlPaciente->execute([$idUsuario]);
    $filaPaciente = $sqlPaciente->fetch(PDO::FETCH_ASSOC);

    if (!$filaPaciente) {
        echo json_encode(["ok" => false, "error" => "No se encontró información del paciente"]);
        exit;
    }

    $idPaciente = $filaPaciente["idPaciente"];

    // 2) Generar idConsentimiento como cons1, cons2, cons3...
    $sqlUltCons = $conn->query("
        SELECT idConsentimiento
        FROM consentimiento
        WHERE idConsentimiento LIKE 'cons%'
        ORDER BY CAST(SUBSTRING(idConsentimiento, 5) AS UNSIGNED) DESC
        LIMIT 1
    ");

    $filaUltCons = $sqlUltCons->fetch(PDO::FETCH_ASSOC);
    $nuevoConsNum = 1;

    if ($filaUltCons && isset($filaUltCons["idConsentimiento"])) {
        $ultimoConsId = $filaUltCons["idConsentimiento"]; // ejemplo 'cons7'
        $parteNumCons = (int)substr($ultimoConsId, 4);
        $nuevoConsNum = $parteNumCons + 1;
    }

    $idConsentimiento = "cons" . $nuevoConsNum;

    // 3) Definir versión de texto (ejemplo simple)
    $versionTexto = "v1.0-telemedicina";
    $ipAceptacion = $_SERVER["REMOTE_ADDR"] ?? null;

    // 4) Insertar en la tabla consentimiento
    $sqlInsert = $conn->prepare("
        INSERT INTO consentimiento (idConsentimiento, idPaciente, versionTexto, fechaAceptacion, ipAceptacion)
        VALUES (?, ?, ?, NOW(), ?)
    ");

    $sqlInsert->execute([
        $idConsentimiento,
        $idPaciente,
        $versionTexto,
        $ipAceptacion
    ]);

    echo json_encode([
        "ok"               => true,
        "idConsentimiento" => $idConsentimiento
    ]);

} catch (Throwable $e) {
    error_log("Error en registrar_consentimiento: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error al registrar el consentimiento"]);
}
