<?php
session_start();
header("Content-Type: application/json; charset=utf-8");
ini_set("display_errors", 0);
error_reporting(E_ALL);

function responder($ok, $arr = []) {
  echo json_encode(array_merge(["ok" => $ok], $arr));
  exit;
}

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "MEDICO") {
  responder(false, ["error" => "Acceso no autorizado"]);
}

require_once __DIR__ . "/config.php";

$idUsuario = $_SESSION["idUsuario"] ?? null;
if (!$idUsuario) responder(false, ["error" => "Sesión inválida"]);

try {
  $pdo = new PDO(
    "mysql:host=$DB_HOST;port=3306;dbname=$DB_NAME;charset=utf8",
    $DB_USER,
    $DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );

  // 1) Obtener idMedico del usuario en sesión
  // OJO: usa nombres en minúscula si así están en tu BD real
  $st = $pdo->prepare("SELECT idmedico FROM medico WHERE idusuario = ? LIMIT 1");
  $st->execute([$idUsuario]);
  $med = $st->fetch(PDO::FETCH_ASSOC);
  if (!$med) responder(false, ["error" => "No se encontró el médico"]);

  $idMedico = $med["idmedico"];

  // 2) Pacientes distintos que tuvieron cita con este médico
  $sql = "
    SELECT DISTINCT
      p.idpaciente,
      u.nombre AS nombrePaciente,
      u.email  AS emailPaciente,
      u.telefono AS telPaciente
    FROM cita c
    INNER JOIN paciente p ON p.idpaciente = c.idpaciente
    INNER JOIN usuario  u ON u.idusuario  = p.idusuario
    WHERE c.idmedico = ?
    ORDER BY u.nombre ASC
  ";

  $st2 = $pdo->prepare($sql);
  $st2->execute([$idMedico]);
  $rows = $st2->fetchAll(PDO::FETCH_ASSOC);

  responder(true, ["pacientes" => $rows]);

} catch (Exception $e) {
  responder(false, ["error" => "Error de servidor"]);
}
