<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config.php";

function responder($ok, $arr = []) {
    echo json_encode(array_merge(["ok" => $ok], $arr), JSON_UNESCAPED_UNICODE);
    exit;
}

// Solo PACIENTE
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "PACIENTE") {
    responder(false, ["error" => "Acceso no autorizado"]);
}

$idUsuario = $_SESSION["idUsuario"] ?? null;
if (!$idUsuario) {
    responder(false, ["error" => "Sesión inválida"]);
}

// Leer JSON del body
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$idCita        = $data["idCita"]        ?? "";
$satisfaccion  = $data["satisfaccion"]  ?? "";
$resuelto      = $data["resuelto"]      ?? "";
$comentarios   = trim($data["comentarios"] ?? "");

// Validaciones mínimas
if (!$idCita || !$satisfaccion || !$resuelto) {
    responder(false, ["error" => "Datos incompletos de la encuesta"]);
}

try {

    // 1) Verificar que la cita pertenece al paciente
    $st = $conn->prepare("
        SELECT 1
        FROM cita c
        INNER JOIN paciente p ON c.idPaciente = p.idPaciente
        WHERE c.idCita = ?
          AND p.idUsuario = ?
        LIMIT 1
    ");
    $st->execute([$idCita, $idUsuario]);

    if (!$st->fetchColumn()) {
        responder(false, ["error" => "La cita no existe o no pertenece al paciente"]);
    }

    // 2) Evitar duplicar encuesta (ya respondida)
    $qExiste = $conn->prepare("
        SELECT 1
        FROM logauditoria
        WHERE accion = 'ENCUESTA_POSTCONSULTA_RESPONDIDA'
          AND JSON_SEARCH(detalle, 'one', :idCita) IS NOT NULL
        LIMIT 1
    ");
    $qExiste->execute([":idCita" => $idCita]);

    if ($qExiste->fetchColumn()) {
        responder(false, ["error" => "La encuesta ya fue registrada anteriormente"]);
    }

    // 3) Generar idLogAuditoria consecutivo tipo logN
    $stmtUltLog = $conn->query("
        SELECT idLogAuditoria
        FROM logauditoria
        WHERE idLogAuditoria LIKE 'log%'
        ORDER BY CAST(SUBSTRING(idLogAuditoria, 4) AS UNSIGNED) DESC
        LIMIT 1
    ");
    $filaUltLog = $stmtUltLog->fetch(PDO::FETCH_ASSOC);

    $nuevoNumLog = 1;
    if ($filaUltLog && isset($filaUltLog["idLogAuditoria"])) {
        $ultimo = $filaUltLog["idLogAuditoria"]; // ej: log58
        $nuevoNumLog = ((int)substr($ultimo, 3)) + 1;
    }
    $idLog = "log" . $nuevoNumLog;

    // 4) Preparar auditoría
    $accion = "ENCUESTA_POSTCONSULTA_RESPONDIDA";

    $detalleArr = [
        "evento"        => "encuesta_postconsulta",
        "idCita"        => $idCita,
        "satisfaccion"  => $satisfaccion,
        "resuelto"      => $resuelto,
        "comentarios"   => $comentarios
    ];
    $detalle = json_encode($detalleArr, JSON_UNESCAPED_UNICODE);

    $ip = $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";

    // 5) Insertar en logauditoria
    $sqlIns = "
        INSERT INTO logauditoria
          (idLogAuditoria, idUsuario, accion, detalle, `timestamp`, ip)
        VALUES
          (:idLog, :idUsuario, :accion, CAST(:detalle AS JSON), NOW(), :ip)
    ";
    $ins = $conn->prepare($sqlIns);
    $ins->execute([
        ":idLog"     => $idLog,
        ":idUsuario" => $idUsuario,
        ":accion"    => $accion,
        ":detalle"   => $detalle,
        ":ip"        => $ip
    ]);

    responder(true);

} catch (Throwable $e) {
    error_log("Error postconsulta_encuesta: " . $e->getMessage());
    responder(false, ["error" => "No se pudo registrar la encuesta"]);
}
