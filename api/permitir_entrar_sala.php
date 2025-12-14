<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/config.php";

function responder($ok, $arr = []) {
  echo json_encode(array_merge(["ok"=>$ok], $arr), JSON_UNESCAPED_UNICODE);
  exit;
}

$rol = $_SESSION["rol"] ?? null;
if (!$rol || !in_array($rol, ["PACIENTE","MEDICO"], true)) {
  responder(false, ["error"=>"Acceso no autorizado"]);
}

$idCita = $_GET["idCita"] ?? "";
if (!$idCita) {
  responder(false, ["error"=>"Falta idCita"]);
}

$idUsuario = $_SESSION["idUsuario"] ?? null;
if (!$idUsuario) {
  responder(false, ["error"=>"No se encontró el usuario en sesión"]);
}

$MINUTOS_ANTES = 0;   // puedes poner 10 si quieres entrar 10 min antes
$CERRAR_AL_FINAL = true; // si quieres que después del fin ya no deje entrar

try {

  // Base: siempre traemos la cita + nombres (para mostrar en sala)
 $sqlBase = "
  SELECT
    c.idcita       AS idCita,
    c.idpaciente   AS idPaciente,
    c.idmedico     AS idMedico,
    c.inicio       AS inicio,
    c.fin          AS fin,
    c.estado       AS estado,
    c.canal        AS canal,
    c.especialidad AS especialidad,
    upac.nombre    AS nombrePaciente,
    umed.nombre    AS nombreMedico
  FROM cita c
  INNER JOIN paciente p   ON c.idpaciente = p.idpaciente
  INNER JOIN usuario upac ON p.idusuario  = upac.idusuario
  INNER JOIN medico m     ON c.idmedico   = m.idmedico
  INNER JOIN usuario umed ON m.idusuario  = umed.idusuario
  WHERE c.idcita = ?
";


  // Filtro por rol: el paciente solo sus citas; el médico solo sus citas
 if ($rol === "PACIENTE") {
  $sql = $sqlBase . " AND upac.idusuario = ? LIMIT 1";
  $params = [$idCita, $idUsuario];
} else {
  $sql = $sqlBase . " AND m.idusuario = ? LIMIT 1";
  $params = [$idCita, $idUsuario];
}


  $stmt = $conn->prepare($sql);
  $stmt->execute($params);
  $cita = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$cita) {
    responder(false, ["error"=>"No se encontró la cita para este usuario"]);
  }

  // Recomendado: sólo confirmadas
  if (($cita["estado"] ?? "") !== "Confirmada") {
    responder(false, ["error"=>"La cita aún no está confirmada"]);
  }

  // Ventana de tiempo
  $tz = new DateTimeZone("America/Monterrey");
  $ahora  = new DateTime("now", $tz);

  $inicio = new DateTime($cita["inicio"], $tz);
  $inicio->modify("-{$MINUTOS_ANTES} minutes");

  if ($ahora < $inicio) {
    responder(false, ["error"=>"Aún no es la hora de la cita", "inicio"=>$cita["inicio"]]);
  }

  if ($CERRAR_AL_FINAL && !empty($cita["fin"])) {
    $fin = new DateTime($cita["fin"], $tz);
    if ($ahora > $fin) {
      responder(false, ["error"=>"La cita ya terminó", "fin"=>$cita["fin"]]);
    }
  }

  responder(true, ["cita"=>$cita]);

} catch (Throwable $e) {
  error_log("Error en permitir_entrar_sala: " . $e->getMessage());
  responder(false, ["error"=>"Error al validar acceso a sala"]);
}
