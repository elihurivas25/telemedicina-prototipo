<?php
/*
  Archivo: crear_cita.php
  Propósito: Crear una cita en estado 'PendientePago' a partir
  de la selección del paciente (médico, día de la semana y horario).
*/

session_start();
header("Content-Type: application/json");

// Solo PACIENTE puede crear citas
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "PACIENTE") {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}

require_once __DIR__ . "/config.php";

// Leer datos enviados por POST
$idMedico      = $_POST["idMedico"]      ?? "";
$diaSemana     = $_POST["diaSemana"]     ?? "";
$horaInicio    = $_POST["horaInicio"]    ?? "";
$horaFin       = $_POST["horaFin"]       ?? "";
$especialidad  = $_POST["especialidad"]  ?? "";

// Validar datos básicos
if (!$idMedico || $diaSemana === "" || !$horaInicio || !$horaFin || !$especialidad) {
    echo json_encode(["ok" => false, "error" => "Datos incompletos para crear la cita"]);
    exit;
}

$especialidadesPermitidas = [
    "General",
    "Pediatría",
    "Psicología",
    "Familiar",
    "Geriatría",
    "Cardiología"
];

if (!in_array($especialidad, $especialidadesPermitidas, true)) {
    echo json_encode(["ok" => false, "error" => "Especialidad no válida"]);
    exit;
}

// Validar horarios (hora fin > hora inicio)
if ($horaFin <= $horaInicio) {
    echo json_encode(["ok" => false, "error" => "El horario de fin debe ser mayor al de inicio"]);
    exit;
}

$idUsuario = $_SESSION["idUsuario"] ?? null;

if (!$idUsuario) {
    echo json_encode(["ok" => false, "error" => "No se encontró el usuario en sesión"]);
    exit;
}

try {
    // Usaremos transacción para asegurar consistencia
    $conn->beginTransaction();

    // 1) Obtener idPaciente a partir del idUsuario (tablas en minúsculas en el servidor)
    $sqlPaciente = $conn->prepare("SELECT idPaciente FROM paciente WHERE idUsuario = ?");
    $sqlPaciente->execute([$idUsuario]);
    $filaPaciente = $sqlPaciente->fetch(PDO::FETCH_ASSOC);

    if (!$filaPaciente) {
        $conn->rollBack();
        echo json_encode(["ok" => false, "error" => "No se encontró información del paciente"]);
        exit;
    }

    $idPaciente = $filaPaciente["idPaciente"];

    // 2) Calcular fecha de la cita (próxima ocurrencia del día de la semana seleccionado)
    //    Nota: en la BD, diaSemana 0=Domingo ... 6=Sábado
    $tz = new DateTimeZone("America/Mexico_City");
    $hoy = new DateTime("now", $tz);
    $hoy->setTime(0, 0, 0);
    $diaHoy = (int)$hoy->format("w"); // 0 (domingo) ... 6 (sábado)

    $diaSeleccionado = (int)$diaSemana;
    $diferenciaDias = ($diaSeleccionado - $diaHoy + 7) % 7;

    $fechaCita = clone $hoy;
    $fechaCita->modify("+{$diferenciaDias} day");

    // Asignar horas seleccionadas
    // horaInicio y horaFin vienen como 'HH:MM:SS' o 'HH:MM'
    list($hIniH, $hIniM) = explode(":", substr($horaInicio, 0, 5));
    list($hFinH, $hFinM) = explode(":", substr($horaFin, 0, 5));

    $inicioDt = clone $fechaCita;
    $inicioDt->setTime((int)$hIniH, (int)$hIniM, 0);

    $finDt = clone $fechaCita;
    $finDt->setTime((int)$hFinH, (int)$hFinM, 0);

    $inicio = $inicioDt->format("Y-m-d H:i:s");
    $fin    = $finDt->format("Y-m-d H:i:s");

    // 3) Generar idCita siguiendo el estilo c1, c2, c3...
    $sqlUltima = $conn->query("
        SELECT idCita
        FROM cita
        WHERE idCita LIKE 'c%'
        ORDER BY CAST(SUBSTRING(idCita, 2) AS UNSIGNED) DESC
        LIMIT 1
    ");

    $filaUltima = $sqlUltima->fetch(PDO::FETCH_ASSOC);
    $nuevoNumero = 1;

    if ($filaUltima && isset($filaUltima["idCita"])) {
        $ultimoId = $filaUltima["idCita"]; // ejemplo: "c7"
        $parteNum = (int)substr($ultimoId, 1);
        $nuevoNumero = $parteNum + 1;
    }

    $idCita = "c" . $nuevoNumero;

    // 4) Insertar cita en estado PendientePago
    $estado = "PendientePago";
    $canal  = "Video"; // Se elige 'Video' como valor por defecto para el prototipo

    $sqlInsert = $conn->prepare("
        INSERT INTO cita (idCita, idPaciente, idMedico, especialidad, inicio, fin, estado, canal)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $sqlInsert->execute([
        $idCita,
        $idPaciente,
        $idMedico,
        $especialidad,
        $inicio,
        $fin,
        $estado,
        $canal
    ]);

    // 5) Registrar en LogAuditoria con estilo log1, log2...
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
        $ultimoLogId = $filaUltLog["idLogAuditoria"]; // ejemplo: "log5"
        $parteNumLog = (int)substr($ultimoLogId, 3);
        $nuevoLogNum = $parteNumLog + 1;
    }

    $idLog = "log" . $nuevoLogNum;

    $detalle = json_encode([
        "idCita"       => $idCita,
        "idPaciente"   => $idPaciente,
        "idMedico"     => $idMedico,
        "especialidad" => $especialidad,
        "inicio"       => $inicio,
        "fin"          => $fin,
        "estado"       => $estado,
        "canal"        => $canal
    ], JSON_UNESCAPED_UNICODE);

    $ip = $_SERVER["REMOTE_ADDR"] ?? null;

    $sqlLog = $conn->prepare("
        INSERT INTO logauditoria (idLogAuditoria, idUsuario, accion, detalle, timestamp, ip)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $sqlLog->execute([
        $idLog,
        $idUsuario,
        "CREAR_CITA",
        $detalle,
        $ip
    ]);

    $conn->commit();

    echo json_encode([
        "ok"     => true,
        "idCita" => $idCita,
        "inicio" => $inicio,
        "fin"    => $fin,
        "estado" => $estado
    ]);

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en crear_cita: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error al crear la cita"]);
}
