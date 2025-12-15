<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

ini_set("display_errors", 0);
error_reporting(E_ALL);

require_once __DIR__ . "/config.php";

function responder($ok, $arr = []) {
  echo json_encode(array_merge(["ok"=>$ok], $arr), JSON_UNESCAPED_UNICODE);
  exit;
}

/* ===============================
   Validaciones básicas
================================ */

// Solo POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  responder(false, ["error"=>"Método no permitido"]);
}

// Solo PACIENTE
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "PACIENTE") {
  responder(false, ["error"=>"Acceso no autorizado"]);
}

$idUsuario = $_SESSION["idUsuario"] ?? null;
if (!$idUsuario) {
  responder(false, ["error"=>"Sesión inválida"]);
}

// Leer JSON
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$idCita = $data["idCita"] ?? "";
if (!$idCita) {
  responder(false, ["error"=>"Falta idCita"]);
}

/* ===============================
   Lógica de cancelación (E7)
================================ */

try {

  // 1) Validar que la cita pertenece al paciente
  $sql = "
    SELECT
      c.idCita,
      c.inicio,
      c.estado,
      p.idUsuario
    FROM cita c
    INNER JOIN paciente p ON c.idPaciente = p.idPaciente
    WHERE c.idCita = ?
      AND p.idUsuario = ?
    LIMIT 1
  ";
  $st = $conn->prepare($sql);
  $st->execute([$idCita, $idUsuario]);
  $cita = $st->fetch(PDO::FETCH_ASSOC);

  if (!$cita) {
    responder(false, ["error"=>"La cita no existe o no pertenece al paciente"]);
  }

  if (($cita["estado"] ?? "") !== "Confirmada") {
    responder(false, ["error"=>"Solo se pueden cancelar citas confirmadas"]);
  }

  // 2) Regla de 12 horas
  $stHoras = $conn->prepare("
    SELECT TIMESTAMPDIFF(HOUR, NOW(), inicio)
    FROM cita
    WHERE idCita = ?
    LIMIT 1
  ");
  $stHoras->execute([$idCita]);
  $horas = (int)$stHoras->fetchColumn();

  if ($horas < 12) {
    responder(false, ["error"=>"No se puede cancelar la cita con menos de 12 horas de anticipación"]);
  }

  // 3) Iniciar transacción
  $conn->beginTransaction();

  // 4) Cancelar cita
  $up = $conn->prepare("
    UPDATE cita
    SET estado = 'Cancelada'
    WHERE idCita = ?
    LIMIT 1
  ");
  $up->execute([$idCita]);

  // 5) Buscar pago (si existe)
  $stPago = $conn->prepare("
    SELECT idPago
    FROM pago
    WHERE idCita = ?
    LIMIT 1
  ");
  $stPago->execute([$idCita]);
  $pago = $stPago->fetch(PDO::FETCH_ASSOC);
  $idPago = $pago["idPago"] ?? null;

  // ⚠️ Reembolso SIMULADO: NO tocamos la tabla pago (evita problemas de ENUM)

  /* ===============================
     AUDITORÍA
  ================================ */

  // Función para generar idLogAuditoria tipo logN
  $genLogId = function() use ($conn) {
    $q = $conn->query("
      SELECT idLogAuditoria
      FROM logauditoria
      WHERE idLogAuditoria LIKE 'log%'
      ORDER BY CAST(SUBSTRING(idLogAuditoria, 4) AS UNSIGNED) DESC
      LIMIT 1
    ");
    $f = $q->fetch(PDO::FETCH_ASSOC);
    $n = 1;
    if ($f && isset($f["idLogAuditoria"])) {
      $n = ((int)substr($f["idLogAuditoria"], 3)) + 1;
    }
    return "log".$n;
  };

  $ip = $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";

  // Log: cancelación de cita
  $idLog1 = $genLogId();
  $detalle1 = json_encode([
    "evento" => "cancelacion_cita",
    "idCita" => $idCita,
    "motivo" => "Cancelación solicitada por el paciente (simulado)",
    "regla"  => ">=12h"
  ], JSON_UNESCAPED_UNICODE);

  $ins1 = $conn->prepare("
    INSERT INTO logauditoria
      (idLogAuditoria, idUsuario, accion, detalle, `timestamp`, ip)
    VALUES
      (?, ?, ?, CAST(? AS JSON), NOW(), ?)
  ");
  $ins1->execute([$idLog1, $idUsuario, "CANCELAR_CITA", $detalle1, $ip]);

  // Log: reembolso simulado
  $idLog2 = $genLogId();
  $detalle2 = json_encode([
    "evento" => "reembolso_simulado",
    "idCita" => $idCita,
    "idPago" => $idPago,
    "nota"   => "Reembolso simulado, no se procesa pasarela real"
  ], JSON_UNESCAPED_UNICODE);

  $ins2 = $conn->prepare("
    INSERT INTO logauditoria
      (idLogAuditoria, idUsuario, accion, detalle, `timestamp`, ip)
    VALUES
      (?, ?, ?, CAST(? AS JSON), NOW(), ?)
  ");
  $ins2->execute([$idLog2, $idUsuario, "REEMBOLSO_SIMULADO", $detalle2, $ip]);

  // 6) Confirmar
  $conn->commit();

  responder(true, [
    "mensaje" => "Cita cancelada correctamente. Reembolso procesado (simulado)."
  ]);

} catch (Throwable $e) {
  if ($conn->inTransaction()) $conn->rollBack();
  error_log("Error cancelar_cita: ".$e->getMessage());
  responder(false, ["error"=>"No se pudo cancelar la cita"]);
}
