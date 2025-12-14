<?php
/*
  Archivo: guardar_historia_clinica.php
  Propósito:
  - Permitir al médico agregar información a la historia clínica
    (alergias, antecedentes, medicamentos, nota de evolución),
    concatenando texto en los campos TEXT existentes.
  - Si el paciente aún no tiene historia clínica, crea un registro nuevo.
  - Registra la acción en LogAuditoria.

  Solo rol MEDICO puede usar este endpoint.
*/

session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== "MEDICO") {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}

require_once __DIR__ . "/config.php";

$idUsuario = $_SESSION["idUsuario"] ?? null;
if (!$idUsuario) {
    echo json_encode(["ok" => false, "error" => "No se encontró el usuario en sesión"]);
    exit;
}

// Datos enviados por POST
$idPaciente = $_POST["idPaciente"] ?? null;
$campo      = $_POST["campo"]      ?? null; // alergias, antecedentes, medicamentos, nota
$textoNuevo = $_POST["texto"]      ?? null;

if (!$idPaciente || !$campo || !$textoNuevo) {
    echo json_encode(["ok" => false, "error" => "Datos incompletos para guardar la historia clínica"]);
    exit;
}

// Mapeo de campo lógico -> columna real en la BD
$mapaCampos = [
    "alergia"      => "alergias",
    "antecedente"  => "antecedentes",
    "medicamento"  => "medicamentos",
    "nota"         => "notaEvolucion"
];

if (!isset($mapaCampos[$campo])) {
    echo json_encode(["ok" => false, "error" => "Tipo de campo no válido"]);
    exit;
}

$columnaBD = $mapaCampos[$campo];

try {
    $conn->beginTransaction();

    // 1) Buscar historia clínica existente del paciente
    $sqlBuscar = $conn->prepare("
        SELECT idHistoriaClinica, alergias, antecedentes, medicamentos, notaEvolucion
        FROM historiaclinica
        WHERE idPaciente = ?
        LIMIT 1
    ");
    $sqlBuscar->execute([$idPaciente]);
    $filaHC = $sqlBuscar->fetch(PDO::FETCH_ASSOC);

    $idHistoriaClinica = null;
    $textoAnterior     = "";

    if ($filaHC) {
        $idHistoriaClinica = $filaHC["idHistoriaClinica"];
        $textoAnterior = $filaHC[$columnaBD] ?? "";
    } else {
        // 2) Crear nueva historia clínica para el paciente
        $sqlUlt = $conn->query("
            SELECT idHistoriaClinica
            FROM historiaclinica
            WHERE idHistoriaClinica LIKE 'hc%'
            ORDER BY CAST(SUBSTRING(idHistoriaClinica, 3) AS UNSIGNED) DESC
            LIMIT 1
        ");
        $filaUlt = $sqlUlt->fetch(PDO::FETCH_ASSOC);

        $nuevoNum = 1;
        if ($filaUlt && isset($filaUlt["idHistoriaClinica"])) {
            $ultimoId = $filaUlt["idHistoriaClinica"]; // ejemplo hc5
            $parteNum = (int)substr($ultimoId, 2);
            $nuevoNum = $parteNum + 1;
        }

        $idHistoriaClinica = "hc" . $nuevoNum;

        // Insertar registro inicial vacío
        $sqlIns = $conn->prepare("
            INSERT INTO historiaclinica (idHistoriaClinica, idPaciente, alergias, antecedentes, medicamentos, notaEvolucion, ultimaActualizacion)
            VALUES (?, ?, '', '', '', '', NOW())
        ");
        $sqlIns->execute([$idHistoriaClinica, $idPaciente]);

        $textoAnterior = "";
    }

    // 3) Concatenar texto
    $textoNuevo = trim($textoNuevo);
    if ($textoAnterior) {
        $textoActualizado = $textoAnterior . "\n" . $textoNuevo;
    } else {
        $textoActualizado = $textoNuevo;
    }

    // 4) Actualizar la columna correspondiente
    $sqlUpdate = $conn->prepare("
        UPDATE historiaclinica
        SET $columnaBD = ?, ultimaActualizacion = NOW()
        WHERE idHistoriaClinica = ?
    ");
    $sqlUpdate->execute([$textoActualizado, $idHistoriaClinica]);

    // Obtener la nueva fecha de actualización
    $sqlFecha = $conn->prepare("
        SELECT ultimaActualizacion
        FROM historiaclinica
        WHERE idHistoriaClinica = ?
        LIMIT 1
    ");
    $sqlFecha->execute([$idHistoriaClinica]);
    $filaFecha = $sqlFecha->fetch(PDO::FETCH_ASSOC);
    $ultimaActualizacion = $filaFecha ? $filaFecha["ultimaActualizacion"] : null;

    // 5) Registrar en LogAuditoria
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
        $ultimoLog = $filaUltLog["idLogAuditoria"]; // log7
        $parteNum  = (int)substr($ultimoLog, 3);
        $nuevoLogNum = $parteNum + 1;
    }

    $idLog = "log" . $nuevoLogNum;

    $detalle = [
        "modulo"      => "HISTORIA_CLINICA",
        "idPaciente"  => $idPaciente,
        "idHistoriaClinica" => $idHistoriaClinica,
        "campo"       => $columnaBD,
        "textoNuevo"  => $textoNuevo
    ];

    $ip = $_SERVER["REMOTE_ADDR"] ?? null;

    $sqlLog = $conn->prepare("
        INSERT INTO logauditoria (idLogAuditoria, idUsuario, accion, detalle, timestamp, ip)
        VALUES (?, ?, 'ACTUALIZAR_HISTORIA', ?, NOW(), ?)
    ");
    $sqlLog->execute([
        $idLog,
        $idUsuario,
        json_encode($detalle, JSON_UNESCAPED_UNICODE),
        $ip
    ]);

    $conn->commit();

    echo json_encode([
        "ok"                  => true,
        "campo"               => $columnaBD,
        "texto"               => $textoActualizado,
        "ultimaActualizacion" => $ultimaActualizacion
    ]);

} catch (Throwable $e) {
    $conn->rollBack();
    error_log("Error en guardar_historia_clinica.php: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error al guardar la historia clínica"]);
}
