<?php
/*
  Archivo: registrar_pago.php
  Propósito: Registrar un pago simulado para una cita existente
  y actualizar su estado a 'Confirmada'.
*/

session_start();
header("Content-Type: application/json");

// Solo PACIENTE puede pagar su cita
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "PACIENTE") {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}

require_once __DIR__ . "/config.php";

$idCita = $_POST["idCita"] ?? "";

if (!$idCita) {
    echo json_encode(["ok" => false, "error" => "Falta el identificador de la cita"]);
    exit;
}

$idUsuario = $_SESSION["idUsuario"] ?? null;

if (!$idUsuario) {
    echo json_encode(["ok" => false, "error" => "No se encontró el usuario en sesión"]);
    exit;
}

// Monto y datos simulados de pago
$monto   = 500.00;         // Monto fijo de ejemplo
$moneda  = "MXN";
$estadoPago = "Autorizado";
// En la BD el proveedor debe ser uno del ENUM, usamos 'Stripe' como sandbox
$proveedor = "Stripe";
$referencia = "SANDBOX-" . strtoupper(uniqid());

try {
    $conn->beginTransaction();

    // 1) Obtener idPaciente del usuario en sesión
    $sqlPaciente = $conn->prepare("SELECT idPaciente FROM paciente WHERE idUsuario = ?");
    $sqlPaciente->execute([$idUsuario]);
    $filaPaciente = $sqlPaciente->fetch(PDO::FETCH_ASSOC);

    if (!$filaPaciente) {
        $conn->rollBack();
        echo json_encode(["ok" => false, "error" => "No se encontró información del paciente"]);
        exit;
    }

    $idPaciente = $filaPaciente["idPaciente"];

    // 2) Verificar que la cita existe, pertenece al paciente y está PendientePago
    $sqlCita = $conn->prepare("
        SELECT idCita, estado
        FROM cita
        WHERE idCita = ? AND idPaciente = ?
        LIMIT 1
    ");
    $sqlCita->execute([$idCita, $idPaciente]);
    $cita = $sqlCita->fetch(PDO::FETCH_ASSOC);

    if (!$cita) {
        $conn->rollBack();
        echo json_encode(["ok" => false, "error" => "No se encontró la cita para este paciente"]);
        exit;
    }

    if ($cita["estado"] !== "PendientePago") {
        $conn->rollBack();
        echo json_encode(["ok" => false, "error" => "La cita no está en estado PendientePago"]);
        exit;
    }

    // 3) Generar idPago como p1, p2, p3...
    $sqlUltPago = $conn->query("
        SELECT idPago
        FROM pago
        WHERE idPago LIKE 'p%'
        ORDER BY CAST(SUBSTRING(idPago, 2) AS UNSIGNED) DESC
        LIMIT 1
    ");

    $filaUltPago = $sqlUltPago->fetch(PDO::FETCH_ASSOC);
    $nuevoPagoNum = 1;

    if ($filaUltPago && isset($filaUltPago["idPago"])) {
        $ultimoPagoId = $filaUltPago["idPago"]; // ejemplo 'p5'
        $parteNumPago = (int)substr($ultimoPagoId, 1);
        $nuevoPagoNum = $parteNumPago + 1;
    }

    $idPago = "p" . $nuevoPagoNum;

    // 4) Insertar pago en tabla pago
    $sqlInsertPago = $conn->prepare("
        INSERT INTO pago (idPago, idCita, monto, moneda, proveedor, referencia, estado, createdAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $sqlInsertPago->execute([
        $idPago,
        $idCita,
        $monto,
        $moneda,
        $proveedor,
        $referencia,
        $estadoPago
    ]);

    // 5) Actualizar cita a estado Confirmada
    $sqlUpdateCita = $conn->prepare("
        UPDATE cita
        SET estado = 'Confirmada'
        WHERE idCita = ?
    ");
    $sqlUpdateCita->execute([$idCita]);

    // 6) Registrar en LogAuditoria como CREAR_PAGO
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
        $ultimoLogId = $filaUltLog["idLogAuditoria"]; // 'log7'
        $parteNumLog = (int)substr($ultimoLogId["idLogAuditoria"] ?? '', 3);
    }

    if ($filaUltLog && isset($filaUltLog["idLogAuditoria"])) {
        $ultimoLogId = $filaUltLog["idLogAuditoria"];
        $parteNumLog = (int)substr($ultimoLogId, 3);
        $nuevoLogNum = $parteNumLog + 1;
    }

    $idLog = "log" . $nuevoLogNum;

    $detalle = json_encode([
        "idPago"      => $idPago,
        "idCita"      => $idCita,
        "monto"       => $monto,
        "moneda"      => $moneda,
        "proveedor"   => $proveedor,
        "referencia"  => $referencia,
        "estadoPago"  => $estadoPago
    ], JSON_UNESCAPED_UNICODE);

    $ip = $_SERVER["REMOTE_ADDR"] ?? null;

    $sqlLog = $conn->prepare("
        INSERT INTO logauditoria (idLogAuditoria, idUsuario, accion, detalle, timestamp, ip)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    $sqlLog->execute([
        $idLog,
        $idUsuario,
        "CREAR_PAGO",
        $detalle,
        $ip
    ]);

    $conn->commit();

    echo json_encode([
        "ok"        => true,
        "idPago"    => $idPago,
        "referencia"=> $referencia,
        "monto"     => $monto,
        "moneda"    => $moneda
    ]);

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en registrar_pago: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error al registrar el pago"]);
}
