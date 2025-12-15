<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config.php";

function responder($ok, $arr = []) {
  echo json_encode(array_merge(["ok"=>$ok], $arr), JSON_UNESCAPED_UNICODE);
  exit;
}

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "PACIENTE") {
  responder(false, ["error"=>"Acceso no autorizado"]);
}

$idUsuario = $_SESSION["idUsuario"] ?? null;
if (!$idUsuario) {
  responder(false, ["error"=>"Sesión inválida"]);
}

$idCita = $_GET["idCita"] ?? "";
if (!$idCita) {
  responder(false, ["error"=>"Falta idCita"]);
}

try {
  // Verifica que la cita pertenece al paciente
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
    responder(false, ["error"=>"La cita no existe o no pertenece al paciente"]);
  }

  // Buscar si ya existe respuesta en auditoría
  $q = $conn->prepare("
    SELECT 1
    FROM logauditoria
    WHERE accion = 'ENCUESTA_POSTCONSULTA_RESPONDIDA'
      AND JSON_SEARCH(detalle, 'one', :idCita) IS NOT NULL
    LIMIT 1
  ");
  $q->execute([":idCita" => $idCita]);
  $respondida = (bool)$q->fetchColumn();

  responder(true, ["respondida" => $respondida]);

} catch (Throwable $e) {
  error_log("Error postconsulta_estado: " . $e->getMessage());
  responder(false, ["error"=>"No se pudo consultar el estado de postconsulta"]);
}
