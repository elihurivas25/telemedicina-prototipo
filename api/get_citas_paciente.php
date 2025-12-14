<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "PACIENTE") {
  echo json_encode(["ok"=>false,"error"=>"Acceso no autorizado"]);
  exit;
}

require_once __DIR__ . "/config.php";

$idUsuario = $_SESSION["idUsuario"] ?? null;
if (!$idUsuario) {
  echo json_encode(["ok"=>false,"error"=>"No se encontró el usuario en sesión"]);
  exit;
}

try {
  // 1) idPaciente del usuario en sesión
  $stmt = $conn->prepare("SELECT idPaciente FROM paciente WHERE idUsuario = ? LIMIT 1");
  $stmt->execute([$idUsuario]);
  $p = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$p) {
    echo json_encode(["ok"=>false,"error"=>"No se encontró información del paciente"]);
    exit;
  }

  $idPaciente = $p["idPaciente"];

  // 2) Próximas citas (puedes ajustar estados si quieres)
  $sql = "
    SELECT
      c.idCita,
      c.idPaciente,
      c.inicio,
      c.fin,
      c.estado,
      c.canal,
      c.especialidad,
      umed.nombre AS nombreMedico
    FROM cita c
    INNER JOIN medico m    ON c.idMedico = m.idMedico
    INNER JOIN usuario umed ON m.idUsuario = umed.idUsuario
  WHERE c.idPaciente = ?
  AND c.estado = 'Confirmada'
  AND (
        c.inicio >= NOW()
        OR (c.inicio <= NOW() AND c.fin >= NOW())
        OR (c.fin < NOW() AND c.fin >= DATE_SUB(NOW(), INTERVAL 30 DAY))
      )
ORDER BY c.inicio DESC
LIMIT 10


  ";

  $stmt = $conn->prepare($sql);
  $stmt->execute([$idPaciente]);
  $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(["ok"=>true, "idPaciente"=>$idPaciente, "citas"=>$citas]);

} catch (Throwable $e) {
  error_log("Error en get_proximas_citas_paciente: " . $e->getMessage());
  echo json_encode(["ok"=>false,"error"=>"Error al obtener próximas citas"]);
}
