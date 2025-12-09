<?php
require_once "config.php"; // conexión PDO

/**
 * Genera IDs cortos tipo u1, u2, u3... o p1, p2, p3...
 * según el último ID existente en la tabla.
 *
 * $prefijo  Ej: 'u' para usuario, 'p' para paciente
 * $tabla    Nombre de la tabla en BD (ej. 'usuario')
 * $campoId  Nombre del campo ID (ej. 'idUsuario')
 */
function generarIdCorto($prefijo, $tabla, $campoId, PDO $conn) {
    // Buscar el último ID que empiece con ese prefijo
    // Ej: SELECT idUsuario FROM usuario WHERE idUsuario LIKE 'u%' ORDER BY CAST(SUBSTRING(idUsuario, 2) AS UNSIGNED) DESC LIMIT 1
    $sql = "SELECT $campoId 
            FROM $tabla 
            WHERE $campoId LIKE :prefijo
            ORDER BY CAST(SUBSTRING($campoId, 2) AS UNSIGNED) DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':prefijo' => $prefijo . '%']);
    $ultimo = $stmt->fetchColumn();

    // Si no hay ningún registro, empezamos en 1
    if (!$ultimo) {
        return $prefijo . "1"; // u1, p1, etc.
    }

    // Extraer la parte numérica: u5 -> 5, p12 -> 12
    $numero = intval(substr($ultimo, 1));
    return $prefijo . ($numero + 1);
}

// 1. Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método no permitido";
    exit;
}

// 2. Leer datos del formulario
$nombre           = trim($_POST['nombre'] ?? '');
$telefono         = trim($_POST['telefono'] ?? '');
$fechaNacimiento  = trim($_POST['fecha_nacimiento'] ?? '');
$sexo             = trim($_POST['sexo'] ?? '');
$email            = trim($_POST['email'] ?? '');
$emailConfirm     = trim($_POST['email_confirm'] ?? '');
$password         = $_POST['password'] ?? '';
$passwordConfirm  = $_POST['password_confirm'] ?? '';
$aceptaPrivacidad = $_POST['acepta_privacidad'] ?? '0';

// 3. Validaciones básicas
$errores = [];

// Campos obligatorios
if ($nombre === '' || $telefono === '' || $fechaNacimiento === '' || $sexo === '' ||
    $email === '' || $emailConfirm === '' || $password === '' || $passwordConfirm === '') {
    $errores[] = "Todos los campos son obligatorios.";
}

// Email coincide
if ($email !== $emailConfirm) {
    $errores[] = "El correo electrónico y su confirmación no coinciden.";
}

// Formato de email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores[] = "El correo electrónico no tiene un formato válido.";
}

// Contraseña coincide
if ($password !== $passwordConfirm) {
    $errores[] = "La contraseña y su confirmación no coinciden.";
}

// Longitud mínima de contraseña
if (strlen($password) < 8) {
    $errores[] = "La contraseña debe tener al menos 8 caracteres.";
}

// Teléfono: 10 dígitos numéricos
if (!preg_match('/^[0-9]{10}$/', $telefono)) {
    $errores[] = "El teléfono debe tener 10 dígitos numéricos.";
}

// Sexo permitido
if (!in_array($sexo, ['F', 'M', 'Otro'])) {
    $errores[] = "El valor de género no es válido.";
}

// Aviso de privacidad
if ($aceptaPrivacidad !== '1') {
    $errores[] = "Debes aceptar el Aviso de Privacidad para continuar.";
}

// Si hay errores, los mostramos y detenemos
if (!empty($errores)) {
    echo "<h2>Errores en el registro:</h2><ul>";
    foreach ($errores as $e) {
        echo "<li>" . htmlspecialchars($e) . "</li>";
    }
    echo "</ul><a href='javascript:history.back()'>Regresar</a>";
    exit;
}

try {
    // 4. Verificar que el email no exista en la tabla "usuario"
    $stmt = $conn->prepare("SELECT COUNT(*) FROM usuario WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $existe = $stmt->fetchColumn();

    if ($existe > 0) {
        echo "<h2>El correo ya está registrado.</h2>";
        echo "<a href='javascript:history.back()'>Regresar</a>";
        exit;
    }

    // 5. Iniciar transacción
    $conn->beginTransaction();

    // 5.1 Generar idUsuario tipo u1, u2, u3...
    $idUsuario    = generarIdCorto('u', 'usuario', 'idUsuario', $conn);
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $zonaHoraria  = "America/Mexico_City";
    $ahora        = date('Y-m-d H:i:s');

    // Insertar en tabla "usuario" (minúsculas)
    $sqlUsuario = "INSERT INTO usuario (
                        idUsuario,
                        email,
                        passwordHash,
                        rol,
                        nombre,
                        telefono,
                        zonaHoraria,
                        createdAt,
                        updatedAt
                   ) VALUES (
                        :idUsuario,
                        :email,
                        :passwordHash,
                        :rol,
                        :nombre,
                        :telefono,
                        :zonaHoraria,
                        :createdAt,
                        :updatedAt
                   )";

    $stmtUsuario = $conn->prepare($sqlUsuario);
    $stmtUsuario->execute([
        ':idUsuario'    => $idUsuario,
        ':email'        => $email,
        ':passwordHash' => $passwordHash,
        ':rol'          => 'PACIENTE',
        ':nombre'       => $nombre,
        ':telefono'     => $telefono,
        ':zonaHoraria'  => $zonaHoraria,
        ':createdAt'    => $ahora,
        ':updatedAt'    => $ahora
    ]);

    // 5.2 Generar idPaciente tipo p1, p2, p3...
    $idPaciente = generarIdCorto('p', 'paciente', 'idPaciente', $conn);

    // Insertar en tabla "paciente" (minúsculas)
    $sqlPaciente = "INSERT INTO paciente (
                        idPaciente,
                        idUsuario,
                        fechaNacimiento,
                        sexo,
                        contactoEmergencia
                    ) VALUES (
                        :idPaciente,
                        :idUsuario,
                        :fechaNacimiento,
                        :sexo,
                        :contactoEmergencia
                    )";

    $stmtPaciente = $conn->prepare($sqlPaciente);
    $stmtPaciente->execute([
        ':idPaciente'        => $idPaciente,
        ':idUsuario'         => $idUsuario,
        ':fechaNacimiento'   => $fechaNacimiento,
        ':sexo'              => $sexo,
        ':contactoEmergencia'=> null   // por ahora lo dejamos NULL
    ]);

    // 6. Confirmar transacción
    $conn->commit();

    echo "<h2>Registro completado correctamente ✔</h2>";
    echo "<p>Ya puedes iniciar sesión con tu correo y contraseña.</p>";

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Ocurrió un error al registrar al paciente: " . htmlspecialchars($e->getMessage());
}
