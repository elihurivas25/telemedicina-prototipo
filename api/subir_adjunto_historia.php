<?php
/*
  Archivo: subir_adjunto_historia.php
  Propósito:
  - Permitir al médico subir un archivo y asociarlo a la
    historia clínica de un paciente.
  - Registrar el adjunto en la tabla "adjunto".
  - Registrar la acción en LogAuditoria.

  Notas:
  - Usa una carpeta física /uploads/historia_clinica/ en el servidor.
*/

session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["rol"]) || !in_array($_SESSION["rol"], ["MEDICO","PACIENTE"], true)) {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}


require_once __DIR__ . "/config.php";

$idUsuario  = $_SESSION["idUsuario"] ?? null;
$idPaciente = $_POST["idPaciente"]   ?? null;

if (!$idUsuario || !$idPaciente) {
    echo json_encode(["ok" => false, "error" => "Datos incompletos para subir el adjunto"]);
    exit;
}

if (!isset($_FILES["archivo"]) || $_FILES["archivo"]["error"] !== UPLOAD_ERR_OK) {
    echo json_encode(["ok" => false, "error" => "No se recibió el archivo correctamente"]);
    exit;
}

try {
    $conn->beginTransaction();

    // 1) Obtener o crear historia clínica
    $sqlHC = $conn->prepare("
        SELECT idHistoriaClinica
        FROM historiaclinica
        WHERE idPaciente = ?
        LIMIT 1
    ");
    $sqlHC->execute([$idPaciente]);
    $filaHC = $sqlHC->fetch(PDO::FETCH_ASSOC);

    if ($filaHC) {
        $idHistoriaClinica = $filaHC["idHistoriaClinica"];
    } else {
        // Crear historia mínima si no existe
        $sqlUlt = $conn->query("
            SELECT idHistoriaClinica
            FROM historiaclinica
            WHERE idHistoriaClinica LIKE 'hc%'
            ORDER BY CAST(SUBSTRING(idHistoriaClinica, 3) AS UNSIGNED) DESC
            LIMIT 1
        ");
        $filaUlt = $sqlUlt->fetch(PDO::FETCH_ASSOC);

        $nuevoNum = 1;
        if ($filaUlt && isset($filaUlt["idHistoriaClinica"])) {
            $ultimoId = $filaUlt["idHistoriaClinica"];
            $parteNum = (int)substr($ultimoId, 2);
            $nuevoNum = $parteNum + 1;
        }

        $idHistoriaClinica = "hc" . $nuevoNum;

        $sqlIns = $conn->prepare("
            INSERT INTO historiaclinica (idHistoriaClinica, idPaciente, alergias, antecedentes, medicamentos, notaEvolucion, ultimaActualizacion)
            VALUES (?, ?, '', '', '', '', NOW())
        ");
        $sqlIns->execute([$idHistoriaClinica, $idPaciente]);
    }

    // 2) Calcular nuevo idAdjunto (adj1, adj2, ...)
    $sqlUltAdj = $conn->query("
        SELECT idAdjunto
        FROM adjunto
        WHERE idAdjunto LIKE 'adj%'
        ORDER BY CAST(SUBSTRING(idAdjunto, 4) AS UNSIGNED) DESC
        LIMIT 1
    ");
    $filaUltAdj = $sqlUltAdj->fetch(PDO::FETCH_ASSOC);

    $nuevoAdjNum = 1;
    if ($filaUltAdj && isset($filaUltAdj["idAdjunto"])) {
        $ultimoAdj = $filaUltAdj["idAdjunto"]; // adj7
        $parteNum  = (int)substr($ultimoAdj, 3);
        $nuevoAdjNum = $parteNum + 1;
    }

    $idAdjunto = "adj" . $nuevoAdjNum;

    // 3) Procesar archivo
    $archivoTmp  = $_FILES["archivo"]["tmp_name"];
    $nombreOrig  = basename($_FILES["archivo"]["name"]);
    $extension   = pathinfo($nombreOrig, PATHINFO_EXTENSION);

    $carpetaRelativa = "/uploads/historia_clinica";
    $carpetaFisica   = $_SERVER["DOCUMENT_ROOT"] . $carpetaRelativa;

    if (!is_dir($carpetaFisica)) {
        mkdir($carpetaFisica, 0755, true);
    }

    $nombreDestino = $idAdjunto . "_" . preg_replace("/[^A-Za-z0-9_\.-]/", "_", $nombreOrig);
    $rutaFisica    = $carpetaFisica . "/" . $nombreDestino;
    $rutaRelativa  = $carpetaRelativa . "/" . $nombreDestino;

    if (!move_uploaded_file($archivoTmp, $rutaFisica)) {
        throw new Exception("No fue posible mover el archivo subido.");
    }

    // 4) Calcular tamaño en MB
    $tamBytes = filesize($rutaFisica);
    $tamMb    = $tamBytes / (1024 * 1024);

    // 5) Insertar en tabla adjunto
    $sqlInsAdj = $conn->prepare("
        INSERT INTO adjunto (idAdjunto, idHistoriaClinica, tipo, url, tamanoMb)
        VALUES (?, ?, ?, ?, ?)
    ");
    $sqlInsAdj->execute([
        $idAdjunto,
        $idHistoriaClinica,
        $extension,        // tipo: extensión del archivo (pdf, png, etc.)
        $rutaRelativa,     // url relativa dentro del servidor
        $tamMb
    ]);

    // 6) Registrar en LogAuditoria
    $sqlUltLog = $conn->query("
        SELECT idLogAuditoria
        FROM logauditoria
        WHERE idLogAuditoria LIKE 'log%'
        ORDER BY CAST(SUBSTRING(idLogAuditoria, 4) AS UNSIGNED) DESC
        LIMIT 1
    ");
    $filaUltLog = $sqlUltLog->fetch(PDO::FETCH_ASSOC);

    $nuevoLogNum = 1;
    if ($filaUltLog && isset($filaUltLog["idLogAuditoria"])) {
        $ultimoLog = $filaUltLog["idLogAuditoria"]; // log7
        $parteNum  = (int)substr($ultimoLog, 3);
        $nuevoLogNum = $parteNum + 1;
    }

    $idLog = "log" . $nuevoLogNum;

    $detalle = [
        "modulo"           => "HISTORIA_CLINICA_ADJUNTO",
        "idPaciente"       => $idPaciente,
        "idHistoriaClinica"=> $idHistoriaClinica,
        "idAdjunto"        => $idAdjunto,
        "archivo"          => $nombreOrig
    ];

    $ip = $_SERVER["REMOTE_ADDR"] ?? null;

    $sqlLog = $conn->prepare("
        INSERT INTO logauditoria (idLogAuditoria, idUsuario, accion, detalle, timestamp, ip)
        VALUES (?, ?, 'AGREGAR_ADJUNTO', ?, NOW(), ?)
    ");
    $sqlLog->execute([
        $idLog,
        $idUsuario,
        json_encode($detalle, JSON_UNESCAPED_UNICODE),
        $ip
    ]);

    $conn->commit();

    echo json_encode([
        "ok" => true,
        "idAdjunto" => $idAdjunto,
        "url" => $rutaRelativa
    ]);

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en subir_adjunto_historia.php: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error al subir el adjunto"]);
}
