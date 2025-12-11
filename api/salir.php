<?php
/*
  Archivo: salir.php
  Prop車sito: Cerrar la sesi車n del usuario y enviarlo al inicio de sesi車n.
*/

session_start();
file_put_contents(__DIR__ . "/debug_logout.txt", date("c") . " - logout\n", FILE_APPEND);


// Vaciar todas las variables de sesi車n
$_SESSION = [];

// Borrar la cookie de sesi車n si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesi車n
session_destroy();

// Evitar cach谷 del navegador en p芍ginas protegidas
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

header("Location: /login");
exit;
