<?php
/*
  Template Name: Dashboard Paciente
  Template Post Type: page
  Descripción: Plantilla para el panel del paciente.
*/

require_once $_SERVER["DOCUMENT_ROOT"] . "/api/proteger.php";

if ( !is_admin() && !isset($_GET['elementor-preview']) ) {
    requerirSesion();
    requerirRol("PACIENTE");
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
