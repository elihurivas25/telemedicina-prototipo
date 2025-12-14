<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

// Evita warnings/notices rompiendo JSON
ini_set("display_errors", 0);
error_reporting(E_ALL);

function responder($ok, $arr = []) {
  echo json_encode(array_merge(["ok" => $ok], $arr));
  exit;
}

require_once __DIR__ . "/config.php";

// Debe estar logueado (PACIENTE o MEDICO)
$rol = $_SESSION["rol"] ?? null;
$idUsuario = $_SESSION["idUsuario"] ?? null;

if (!$rol || !$idUsuario) {
  responder(false, ["error" => "No autenticado"]);
}

if ($rol !== "PACIENTE" && $rol !== "MEDICO") {
  responder(false, ["error" => "Rol no permitido"]);
}

try {
  // OJO hosting Linux: usaremos minúsculas en SQL (tablas/columnas)
  // Asumimos que $conn es PDO en config.php
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  responder(false, ["error" => "Error de conexión"]);
}

// ---------- GET: consultar perfil ----------
if ($_SERVER["REQUEST_METHOD"] === "GET") {
  try {
    // Usuario base
    $stmt = $conn->prepare("
      SELECT idusuario, email, rol, nombre, telefono, updatedat
      FROM usuario
      WHERE idusuario = ?
      LIMIT 1
    ");
    $stmt->execute([$idUsuario]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) responder(false, ["error" => "Usuario no encontrado"]);

    $perfil = [
      "idUsuario"  => $u["idusuario"],
      "email"      => $u["email"],
      "rol"        => $u["rol"],
      "nombre"     => $u["nombre"],
      "telefono"   => $u["telefono"],
      "updatedAt"  => $u["updatedat"],
      // campos rol-específicos (default)
      "fechaNacimiento"    => null,
      "sexo"               => null,
      "contactoEmergencia" => null,
      "cedulaProfesional"  => null,
      "especialidad"       => null,
      "bio"                => null,
    ];

    if ($rol === "PACIENTE") {
      $stmt = $conn->prepare("
        SELECT fechanacimiento, sexo, contactoemergencia
        FROM paciente
        WHERE idusuario = ?
        LIMIT 1
      ");
      $stmt->execute([$idUsuario]);
      $p = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($p) {
        $perfil["fechaNacimiento"]    = $p["fechanacimiento"];
        $perfil["sexo"]               = $p["sexo"];
        $perfil["contactoEmergencia"] = $p["contactoemergencia"];
      }
    }

    if ($rol === "MEDICO") {
      $stmt = $conn->prepare("
        SELECT cedulaprofesional, especialidad, bio
        FROM medico
        WHERE idusuario = ?
        LIMIT 1
      ");
      $stmt->execute([$idUsuario]);
      $m = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($m) {
        $perfil["cedulaProfesional"] = $m["cedulaprofesional"];
        $perfil["especialidad"]      = $m["especialidad"];
        $perfil["bio"]               = $m["bio"];
      }
    }

    responder(true, ["perfil" => $perfil]);

  } catch (Exception $e) {
    responder(false, ["error" => "Error al consultar perfil"]);
  }
}

// ---------- POST: actualizar perfil ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // Campos comunes editables (mínimos)
  $nombre   = trim($_POST["nombre"] ?? "");
  $telefono = trim($_POST["telefono"] ?? "");

  if ($nombre === "") responder(false, ["error" => "Nombre requerido"]);
  if ($telefono === "") responder(false, ["error" => "Teléfono requerido"]);

  // Campos paciente
  $fechaNacimiento    = $_POST["fecha_nacimiento"] ?? null;
  $sexo               = $_POST["sexo"] ?? null;
  $contactoEmergencia = trim($_POST["contacto_emergencia"] ?? "");

  // Campos médico
  $bio = trim($_POST["bio"] ?? "");

  try {
    $conn->beginTransaction();

    // 1) Actualizar usuario
    $stmt = $conn->prepare("
      UPDATE usuario
      SET nombre = ?, telefono = ?, updatedat = NOW()
      WHERE idusuario = ?
      LIMIT 1
    ");
    $stmt->execute([$nombre, $telefono, $idUsuario]);

    // 2) Actualizar según rol (solo lo estrictamente necesario)
    if ($rol === "PACIENTE") {
      // validaciones suaves (por ser prototipo)
      if ($sexo !== null && $sexo !== "" && !in_array($sexo, ["F","M","Otro"], true)) {
        $conn->rollBack();
        responder(false, ["error" => "Sexo inválido"]);
      }

      // Normaliza vacíos a NULL
      $fechaNacimiento = ($fechaNacimiento === "") ? null : $fechaNacimiento;
      $sexo = ($sexo === "") ? null : $sexo;
      $contactoEmergencia = ($contactoEmergencia === "") ? null : $contactoEmergencia;

      $stmt = $conn->prepare("
        UPDATE paciente
        SET fechanacimiento = ?, sexo = ?, contactoemergencia = ?
        WHERE idusuario = ?
        LIMIT 1
      ");
      $stmt->execute([$fechaNacimiento, $sexo, $contactoEmergencia, $idUsuario]);
    }

    if ($rol === "MEDICO") {
      $bio = ($bio === "") ? null : $bio;

      $stmt = $conn->prepare("
        UPDATE medico
        SET bio = ?
        WHERE idusuario = ?
        LIMIT 1
      ");
      $stmt->execute([$bio, $idUsuario]);
    }

    $conn->commit();
    responder(true);

  } catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    responder(false, ["error" => "Error al guardar perfil"]);
  }
}

responder(false, ["error" => "Método no permitido"]);
