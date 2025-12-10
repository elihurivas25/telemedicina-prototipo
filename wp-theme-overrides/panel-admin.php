<?php
/*
  Template Name: Panel Administrador
  Template Post Type: page
  Descripción: Panel privado para el administrador.
*/

require_once $_SERVER["DOCUMENT_ROOT"] . "/api/proteger.php";

if ( !is_admin() && !isset($_GET['elementor-preview']) ) {
    requerirSesion();
    requerirRol("ADMIN");
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
