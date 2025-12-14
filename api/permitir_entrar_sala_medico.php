<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "MEDICO") {
  echo json_encode(["ok"=>false,"error"=>"Acceso no autorizado"]);
  exit;
}

require_once __DIR__ . "/config.php";

$idCita = $_GET["idCita"] ?? "";
if (!$idCita) {
  echo json_encode(["ok"=>false,"error"=>"Falta idCita"]);
  exit;
}

$idUsuario = $_SESSION["idUsuario"] ?? null;
if (!$idUsuario) {
  echo json_encode(["ok"=>false,"error"=>"No se encontró el usuario en sesión"]);
  exit;
}

$MINUTOS_ANTES = 10; // igual que tu UI (10 min antes)

try {
  // 1) Buscar cita y validar que pertenezca al médico en sesión
  $sql = "
    SELECT
      c.idCita,
      c.idPaciente,
      c.inicio,
      c.fin,
      c.estado,
      c.canal,
      c.especialidad,
      upac.nombre AS nombrePaciente
    FROM cita c
    INNER JOIN medico m      ON c.idMedico = m.idMedico
    INNER JOIN usuario umed  ON m.idUsuario = umed.idUsuario
    INNER JOIN paciente p    ON c.idPaciente = p.idPaciente
    INNER JOIN usuario upac  ON p.idUsuario = upac.idUsuario
    WHERE c.idCita = ?
      AND umed.idUsuario = ?
    LIMIT 1
  ";

  $stmt = $conn->prepare($sql);
  $stmt->execute([$idCita, $idUsuario]);
  $cita = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$cita) {
    echo json_encode(["ok"=>false,"error"=>"No se encontró la cita para este médico"]);
    exit;
  }

  // 2) Estado permitido
  if (($cita["estado"] ?? "") !== "Confirmada") {
    echo json_encode(["ok"=>false,"error"=>"La cita aún no está confirmada"]);
    exit;
  }

  // 3) Validación de tiempo (ventana: 10 min antes y durante la cita)
  $tz = new DateTimeZone("America/Monterrey");
  $ahora  = new DateTime("now", $tz);
  $inicio = new DateTime($cita["inicio"], $tz);
  $fin    = new DateTime($cita["fin"], $tz);

  $ventanaInicio = (clone $inicio)->modify("-{$MINUTOS_ANTES} minutes");
  if ($ahora < $ventanaInicio) {
    echo json_encode([
      "ok"=>false,
      "error"=>"Aún no es la hora de la cita",
      "inicio"=>$cita["inicio"]
    ]);
    exit;
  }

  if ($ahora > $fin) {
    echo json_encode(["ok"=>false,"error"=>"La cita ya concluyó"]);
    exit;
  }

  echo json_encode(["ok"=>true, "cita"=>$cita]);

} catch (Throwable $e) {
  error_log("Error en permitir_entrar_sala_medico: " . $e->getMessage());
  echo json_encode(["ok"=>false,"error"=>"Error al validar acceso a sala"]);
}
