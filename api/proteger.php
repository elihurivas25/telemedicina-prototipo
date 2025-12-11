<?php
/*
  Archivo: proteger.php
  Propósito: Verificar acceso a páginas privadas del sistema.
  Este archivo se incluye al inicio de cada vista que requiera sesión activa.
*/

session_start();
error_log("SESION ACTUAL: " . print_r($_SESSION, true));


// Cabeceras para evitar que se cacheen las páginas privadas
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
/*
  Función: requerirSesion
  Verifica que el usuario tenga sesión iniciada.
  Si no tiene sesión, se le envía al inicio de sesión.
*/
function requerirSesion() {
    if (!isset($_SESSION["idUsuario"])) {
        header("Location: /login");
        exit;
    }
}

/*
  Función: requerirRol
  Valida que el usuario tenga el rol adecuado
  para acceder a la sección solicitada.
*/
function requerirRol($rolNecesario) {
    if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== $rolNecesario) {
        header("Location: /login");
        exit;
    }
}

