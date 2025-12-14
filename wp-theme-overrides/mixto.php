<?php
/*
  Template Name: mixto
  Template Post Type: page
  Descripción: Plantilla para la página Mi Perfil, permite PACIENTE y MEDICO.
*/

require_once $_SERVER["DOCUMENT_ROOT"] . "/api/proteger.php";

if ( !is_admin() && !isset($_GET['elementor-preview']) ) {
    requerirSesion();

    // ✅ Permitir ambos roles sin "cerrar sesión"
    $rol = $_SESSION["rol"] ?? null;
    if ($rol !== "PACIENTE" && $rol !== "MEDICO") {
        // Redirige a login (o a donde tú manejes el acceso)
        wp_redirect(home_url("/login"));
        exit;
    }
}

get_header();
?>

<div class="contenido-dashboard">
    <?php
    if ( have_posts() ) {
        while ( have_posts() ) {
            the_post();
            the_content();
        }
    }
    ?>
</div>

<?php get_footer(); ?>
