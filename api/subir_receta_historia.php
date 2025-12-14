<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Evita que warnings/notices rompan el JSON
ini_set("display_errors", 0);
error_reporting(E_ALL);

function responder($ok, $arr = []) {
    echo json_encode(array_merge(["ok" => $ok], $arr));
    exit;
}

require_once __DIR__ . "/config.php";

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;port=3306;dbname=$DB_NAME;charset=utf8",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    responder(false, ["error" => "Error de conexión"]);
}

$idPaciente = $_POST["idPaciente"] ?? null;
if (!$idPaciente) responder(false, ["error" => "Falta idPaciente"]);

if (!isset($_FILES["archivo"])) responder(false, ["error" => "Falta archivo"]);
if ($_FILES["archivo"]["error"] !== UPLOAD_ERR_OK) responder(false, ["error" => "Archivo inválido"]);

$nombreOriginal = basename($_FILES["archivo"]["name"]);
$ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
$permitidos = ["pdf", "png", "jpg", "jpeg"];
if (!in_array($ext, $permitidos)) responder(false, ["error" => "Tipo de archivo no permitido"]);

try {
    // 1) Buscar historia clínica
    $stmt = $pdo->prepare("SELECT idHistoriaClinica FROM historiaclinica WHERE idPaciente = ? LIMIT 1");
    $stmt->execute([$idPaciente]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) responder(false, ["error" => "El paciente no tiene historia clínica registrada"]);

    $idHistoriaClinica = $row["idHistoriaClinica"];

    // 2) Carpeta destino
    $carpetaDestino = rtrim($_SERVER["DOCUMENT_ROOT"], "/") . "/uploads/recetas/";
    if (!is_dir($carpetaDestino)) {
        if (!@mkdir($carpetaDestino, 0755, true)) {
            responder(false, ["error" => "No se pudo crear carpeta de destino"]);
        }
    }

    // 3) Guardar archivo
    $nombreFinal = "receta_" . $idPaciente . "_" . date("Ymd_His") . "." . $ext;
    $rutaFinal = $carpetaDestino . $nombreFinal;

    if (!move_uploaded_file($_FILES["archivo"]["tmp_name"], $rutaFinal)) {
        responder(false, ["error" => "No se pudo guardar el archivo"]);
    }

    $urlPublica = "/uploads/recetas/" . $nombreFinal;
    $tamanoBytes = @filesize($rutaFinal);
    $tamanoMb = $tamanoBytes ? round($tamanoBytes / 1048576, 2) : 0;

    // 4) Generar idAdjunto estilo adjN
    $stmt = $pdo->query("SELECT idAdjunto FROM adjunto WHERE idAdjunto LIKE 'adj%'");
    $max = 0;
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (preg_match('/^adj(\d+)$/', $r["idAdjunto"], $m)) {
            $n = (int)$m[1];
            if ($n > $max) $max = $n;
        }
    }
    $idAdjunto = "adj" . ($max + 1);

    // 5) Insert Adjunto (tipo RECETA)
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO adjunto (idAdjunto, idHistoriaClinica, tipo, url, tamanoMb)
        VALUES (?, ?, 'RECETA', ?, ?)
    ");
    $stmt->execute([$idAdjunto, $idHistoriaClinica, $urlPublica, $tamanoMb]);

    // (Opcional) Auditoría si hay sesión
    $idUsuario = $_SESSION["idUsuario"] ?? null;
    if ($idUsuario) {
        $stmt = $pdo->query("SELECT idLogAuditoria FROM logauditoria WHERE idLogAuditoria LIKE 'log%'");
        $maxLog = 0;
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (preg_match('/^log(\d+)$/', $r["idLogAuditoria"], $m)) {
                $n = (int)$m[1];
                if ($n > $maxLog) $maxLog = $n;
            }
        }
        $idLog = "log" . ($maxLog + 1);

        $detalle = json_encode([
            "idPaciente" => $idPaciente,
            "idHistoriaClinica" => $idHistoriaClinica,
            "idAdjunto" => $idAdjunto,
            "tipo" => "RECETA",
            "url" => $urlPublica
        ]);

        $ip = $_SERVER["REMOTE_ADDR"] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO logauditoria (idLogAuditoria, idUsuario, accion, detalle, timestamp, ip)
            VALUES (?, ?, 'SUBIR_RECETA', ?, NOW(), ?)
        ");
        $stmt->execute([$idLog, $idUsuario, $detalle, $ip]);
    }

    $pdo->commit();

    responder(true, ["idAdjunto" => $idAdjunto, "url" => $urlPublica]);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    responder(false, ["error" => "Error interno al subir receta"]);
}
