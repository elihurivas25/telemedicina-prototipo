<?php
/*
  Archivo: detalle_cita.php
  Propósito: Devolver los datos de una cita confirmada para mostrar
  el comprobante al paciente.
*/

session_start();
header("Content-Type: application/json");

// Solo PACIENTE puede ver el detalle de su cita
if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "PACIENTE") {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}

require_once __DIR__ . "/config.php";

$idCita = $_GET["idCita"] ?? "";

if (!$idCita) {
    echo json_encode(["ok" => false, "error" => "Falta el identificador de la cita"]);
    exit;
}

$idUsuario = $_SESSION["idUsuario"] ?? null;

if (!$idUsuario) {
    echo json_encode(["ok" => false, "error" => "No se encontró el usuario en sesión"]);
    exit;
}

try {

    /*
      Tablas involucradas:
      - cita c
      - paciente paq
      - medico m
      - usuario upac (paciente) y umed (médico)
      - pago p (LEFT JOIN)
    */

    $sql = "
        SELECT
            c.idCita,
            c.inicio,
            c.fin,
            c.estado,
            c.canal,
            c.especialidad,
            m.idMedico,
            umed.nombre   AS nombreMedico,
            umed.email    AS emailMedico,
            m.cedulaProfesional,
            p.idPago,
            p.monto,
            p.moneda,
            p.proveedor,
            p.referencia,
            p.estado      AS estadoPago
        FROM cita c
        INNER JOIN paciente paq   ON c.idPaciente = paq.idPaciente
        INNER JOIN usuario upac   ON paq.idUsuario = upac.idUsuario
        INNER JOIN medico  m      ON c.idMedico   = m.idMedico
        INNER JOIN usuario umed   ON m.idUsuario  = umed.idUsuario
        LEFT JOIN  pago p         ON p.idCita     = c.idCita
        WHERE c.idCita = ?
          AND upac.idUsuario = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$idCita, $idUsuario]);
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cita) {
        echo json_encode(["ok" => false, "error" => "No se encontró la cita para este paciente"]);
        return;
    }

    if ($cita["estado"] !== "Confirmada") {
        echo json_encode(["ok" => false, "error" => "La cita aún no está confirmada"]);
        return;
    }

    // =========================================================
    // E3: ENVÍO DE COMPROBANTE (SIMULADO) + AUDITORÍA
    // - Se registra 1 sola vez por cita para no duplicar logs.
    // =========================================================
    try {

        // 1) Evitar duplicar: si ya existe un log con esa acción y esa idCita, no insertar.
        $qExiste = $conn->prepare("
            SELECT 1
            FROM logauditoria
            WHERE accion = 'ENVIO_COMPROBANTE_SIMULADO'
              AND JSON_SEARCH(detalle, 'one', :idCita) IS NOT NULL
            LIMIT 1
        ");
        $qExiste->execute([":idCita" => $idCita]);
        $yaExiste = (bool)$qExiste->fetchColumn();

        if (!$yaExiste) {

            // 2) Generar idLogAuditoria consecutivo tipo logN
            $stmtUltLog = $conn->query("
                SELECT idLogAuditoria
                FROM logauditoria
                WHERE idLogAuditoria LIKE 'log%'
                ORDER BY CAST(SUBSTRING(idLogAuditoria, 4) AS UNSIGNED) DESC
                LIMIT 1
            ");
            $filaUltLog = $stmtUltLog->fetch(PDO::FETCH_ASSOC);

            $nuevoNumLog = 1;
            if ($filaUltLog && isset($filaUltLog["idLogAuditoria"])) {
                $ultimo = $filaUltLog["idLogAuditoria"]; // ej: log55
                $nuevoNumLog = ((int)substr($ultimo, 3)) + 1;
            }
            $idLog = "log" . $nuevoNumLog;

            $accion = "ENVIO_COMPROBANTE_SIMULADO";

            $detalleArr = [
                "evento"  => "envio_comprobante_simulado",
                "mensaje" => "Pago confirmado. El comprobante se muestra en pantalla y se simula el envío al correo del paciente.",
                "idCita"  => $idCita,
                "idPago"  => ($cita["idPago"] ?? null),
                "monto"   => ($cita["monto"] ?? null),
                "moneda"  => ($cita["moneda"] ?? null)
            ];
            $detalle = json_encode($detalleArr, JSON_UNESCAPED_UNICODE);

            $ip = $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";

            $sqlIns = "
                INSERT INTO logauditoria
                  (idLogAuditoria, idUsuario, accion, detalle, `timestamp`, ip)
                VALUES
                  (:idLog, :idUsuario, :accion, CAST(:detalle AS JSON), NOW(), :ip)
            ";

            $ins = $conn->prepare($sqlIns);
            if ($ins) {
                $ins->execute([
                    ":idLog"     => $idLog,
                    ":idUsuario" => $idUsuario,
                    ":accion"    => $accion,
                    ":detalle"   => $detalle,
                    ":ip"        => $ip
                ]);
            }
        }

    } catch (Throwable $e) {
        // No romper el flujo si falla auditoría
        error_log("Auditoría E3 falló: " . $e->getMessage());
    }

    // Respuesta normal
    echo json_encode([
        "ok"   => true,
        "cita" => $cita
    ]);

} catch (Throwable $e) {
    error_log("Error en detalle_cita: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error al obtener el detalle de la cita"]);
}
