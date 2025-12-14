<?php
session_start();
header("Content-Type: application/json");

require_once __DIR__ . "/config.php";

// Nota: aquí asumimos que tu endpoint de historia clínica ya asegura que exista
// HistoriaClinica para el paciente (o lo crea cuando hace falta).
// Este endpoint solo LISTA adjuntos tipo RECETA vinculados a esa historia.

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;port=3306;dbname=$DB_NAME;charset=utf8",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    echo json_encode(["ok" => false, "error" => "Error de conexión"]);
    exit;
}

$idPaciente = $_GET["idPaciente"] ?? null;
if (!$idPaciente) {
    echo json_encode(["ok" => false, "error" => "Falta idPaciente"]);
    exit;
}

// 1) Obtener idHistoriaClinica de ese paciente
$stmt = $pdo->prepare("SELECT idHistoriaClinica FROM historiaclinica WHERE idPaciente = ? LIMIT 1");
$stmt->execute([$idPaciente]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    // Si no hay historia, devolvemos vacío (no inventamos nada)
    echo json_encode(["ok" => true, "recetas" => []]);
    exit;
}

$idHistoriaClinica = $row["idHistoriaClinica"];

// 2) Traer adjuntos tipo RECETA
$stmt = $pdo->prepare("
    SELECT idAdjunto, tipo, url, tamanoMb
    FROM adjunto
    WHERE idHistoriaClinica = ?
      AND tipo = 'RECETA'
    ORDER BY idAdjunto ASC
");
$stmt->execute([$idHistoriaClinica]);
$recetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si quieres mostrar “fecha”, como tu BD no tiene fecha en Adjunto,
// lo mandamos como N/D. (El front ya lo pone así.)
foreach ($recetas as &$r) {
    $r["fechaArchivo"] = "N/D";
}

echo json_encode(["ok" => true, "recetas" => $recetas]);
