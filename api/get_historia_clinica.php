<?php
/*
  Archivo: get_historia_clinica.php
  Propósito:
  - Devolver en formato JSON la información básica de un paciente
    (nombre, sexo, edad) y su historia clínica, si existe.
  - Se usa principalmente en la vista de Historia Clínica del médico.

  Notas:
  - Para el rol MEDICO se recibe idPaciente por GET.
  - Para el rol PACIENTE, en una versión futura, podría ignorarse el idPaciente
    y tomarlo desde la sesión.
*/

session_start();
header("Content-Type: application/json");

// Verificación de sesión y rol
if (!isset($_SESSION["rol"])) {
    echo json_encode(["ok" => false, "error" => "Sesión no válida"]);
    exit;
}

$rolSesion = $_SESSION["rol"];

if ($rolSesion !== "MEDICO" && $rolSesion !== "PACIENTE") {
    echo json_encode(["ok" => false, "error" => "Acceso no autorizado"]);
    exit;
}

require_once __DIR__ . "/config.php";

try {

    // Determinar idPaciente según el rol
    $idPaciente = null;

    if ($rolSesion === "MEDICO") {
        // El médico recibe el idPaciente en la URL: ?idPaciente=p1
        $idPaciente = $_GET["idPaciente"] ?? null;
        if (!$idPaciente) {
            echo json_encode(["ok" => false, "error" => "Falta el identificador del paciente"]);
            exit;
        }

        $sql = "
            SELECT
                p.idPaciente,
                u.nombre,
                p.fechaNacimiento,
                p.sexo,
                hc.alergias,
                hc.antecedentes,
                hc.medicamentos,
                hc.notaEvolucion,
                hc.ultimaActualizacion
            FROM paciente p
            INNER JOIN usuario u
                ON u.idUsuario = p.idUsuario
            LEFT JOIN historiaclinica hc
                ON hc.idPaciente = p.idPaciente
            WHERE p.idPaciente = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$idPaciente]);

    } else {
        // Rol PACIENTE (por si más adelante se usa historia clínica del propio paciente)
        $idUsuario = $_SESSION["idUsuario"] ?? null;

        if (!$idUsuario) {
            echo json_encode(["ok" => false, "error" => "No se encontró el usuario en sesión"]);
            exit;
        }

        $sql = "
            SELECT
                p.idPaciente,
                u.nombre,
                p.fechaNacimiento,
                p.sexo,
                hc.alergias,
                hc.antecedentes,
                hc.medicamentos,
                hc.notaEvolucion,
                hc.ultimaActualizacion
            FROM paciente p
            INNER JOIN usuario u
                ON u.idUsuario = p.idUsuario
            LEFT JOIN historiaclinica hc
                ON hc.idPaciente = p.idPaciente
            WHERE u.idUsuario = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$idUsuario]);
    }

    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fila) {
        echo json_encode(["ok" => false, "error" => "No se encontró información del paciente"]);
        exit;
    }

    // Cálculo de edad aproximada (si hay fecha de nacimiento)
    $edad = null;
    if (!empty($fila["fechaNacimiento"]) && $fila["fechaNacimiento"] !== "0000-00-00") {
        $fechaNac = new DateTime($fila["fechaNacimiento"]);
        $hoy = new DateTime();
        $diff = $hoy->diff($fechaNac);
        $edad = $diff->y;
    }

    // Preparar respuesta, asegurando que no haya nulls en los textos
    $respuesta = [
        "ok" => true,
        "paciente" => [
            "idPaciente"         => $fila["idPaciente"],
            "nombre"             => $fila["nombre"],
            "sexo"               => $fila["sexo"],
            "fechaNacimiento"    => $fila["fechaNacimiento"],
            "edad"               => $edad,
        ],
        "historia" => [
            "alergias"           => $fila["alergias"]          ?? "",
            "antecedentes"       => $fila["antecedentes"]      ?? "",
            "medicamentos"       => $fila["medicamentos"]      ?? "",
            "notaEvolucion"      => $fila["notaEvolucion"]     ?? "",
            "ultimaActualizacion"=> $fila["ultimaActualizacion"] ?? null,
        ]
    ];

    echo json_encode($respuesta);

} catch (Throwable $e) {
    error_log("Error en get_historia_clinica.php: " . $e->getMessage());
    echo json_encode(["ok" => false, "error" => "Error al consultar la historia clínica"]);
}
