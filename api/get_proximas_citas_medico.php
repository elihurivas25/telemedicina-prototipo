<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

ini_set("display_errors", 0);
error_reporting(E_ALL);

function responder($ok, $arr = []) {
  echo json_encode(array_merge(["ok" => $ok], $arr), JSON_UNESCAPED_UNICODE);
  exit;
}

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "MEDICO") {
  responder(false, ["error" => "Acceso no autorizado"]);
}

require_once __DIR__ . "/config.php";

$idUsuario = $_SESSION["idUsuario"] ?? null;
if (!$idUsuario) {
  responder(false, ["error" => "Sesión inválida"]);
}

$filtro = $_GET["filtro"] ?? "hoy";

try {
  // 1) Obtener idMedico
  $stmt = $conn->prepare("SELECT idMedico FROM medico WHERE idUsuario = ? LIMIT 1");
  $stmt->execute([$idUsuario]);
  $m = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$m) {
    responder(false, ["error" => "No se encontró médico asociado"]);
  }

  $idMedico = $m["idMedico"];

  // 2) Definir rango
  $ahora = new DateTime("now");
  $hoy00 = new DateTime("today 00:00:00");

  if ($filtro === "hoy") {
    $inicio = new DateTime("today 00:00:00");
    $fin    = new DateTime("today 23:59:59");
  } elseif ($filtro === "manana") {
    $inicio = new DateTime("tomorrow 00:00:00");
    $fin    = new DateTime("tomorrow 23:59:59");
  } elseif ($filtro === "48h") {
    // Incluye TODO hoy + mañana (y también “en curso”)
    $inicio = $hoy00;
    $fin    = (clone $hoy00)->modify("+2 days");
  } elseif ($filtro === "7d") {
    $inicio = $hoy00;
    $fin    = (clone $hoy00)->modify("+7 days");
  } elseif ($filtro === "todas") {
    $inicio = $hoy00;
    $fin    = (clone $hoy00)->modify("+365 days");
  } else {
    $inicio = $hoy00;
    $fin    = (clone $hoy00)->modify("+7 days");
    $filtro = "7d";
  }

  $inicioSql = $inicio->format("Y-m-d H:i:s");
  $finSql    = $fin->format("Y-m-d H:i:s");

  // 3) Consultar citas
  // - Para hoy/mañana basta el BETWEEN.
  // - Para 48h/7d/todas agregamos OR “en curso” para no perder citas que empezaron antes de NOW.
  $usaEnCurso = in_array($filtro, ["48h","7d","todas"], true);

  $whereTiempo = $usaEnCurso
    ? "( (c.inicio BETWEEN ? AND ?) OR (c.inicio <= NOW() AND c.fin >= NOW()) )"
    : "(c.inicio BETWEEN ? AND ?)";

  $sql = "
    SELECT
      c.idCita,
      c.inicio,
      c.fin,
      c.estado,
      c.canal,
      p.idPaciente,
      u.nombre AS nombrePaciente
    FROM cita c
    INNER JOIN paciente p ON p.idPaciente = c.idPaciente
    INNER JOIN usuario u ON u.idUsuario = p.idUsuario
    WHERE c.idMedico = ?
      AND $whereTiempo
      AND c.estado IS NOT NULL
    ORDER BY c.inicio ASC
    LIMIT 20
  ";

  $stmt = $conn->prepare($sql);

  if ($usaEnCurso) {
    $stmt->execute([$idMedico, $inicioSql, $finSql]);
  } else {
    $stmt->execute([$idMedico, $inicioSql, $finSql]);
  }

  $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

  responder(true, [
    "filtro" => $filtro,
    "rango"  => ["inicio"=>$inicioSql, "fin"=>$finSql],
    "citas"  => $citas
  ]);

} catch (Throwable $e) {
  error_log("get_proximas_citas_medico.php ERROR: " . $e->getMessage());

  if (isset($_GET["debug"]) && $_GET["debug"] == "1") {
    responder(false, ["error" => "Error al consultar próximas citas", "debug" => $e->getMessage()]);
  }

  responder(false, ["error" => "Error al consultar próximas citas"]);
}
