<?php
/*
  Archivo: salir.php
  Propósito: Cerrar la sesión del usuario y enviarlo al inicio de sesión.
*/

session_start();
session_destroy();

// Se envía al usuario al login
header("Location: /login");
exit;
