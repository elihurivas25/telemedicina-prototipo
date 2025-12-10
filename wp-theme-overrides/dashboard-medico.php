<?php
/*
  Template Name: Dashboard Médico
  Template Post Type: page
  Descripción: Página privada para el panel del médico.
*/

require_once $_SERVER["DOCUMENT_ROOT"] . "/api/proteger.php";

/*
  Solo aplicamos la protección cuando es un acceso normal del usuario.
  Si se está en el administrador de WordPress o en el editor de Elementor,
  se omite esta verificación para poder editar la página sin problemas.
*/
if ( !is_admin() && !isset($_GET['elementor-preview']) ) {
    requerirSesion();
    requerirRol("MEDICO");
}

get_header();
?>

<div class="contenido-dashboard">
    <?php
    /*
      Elementor necesita que the_content() esté dentro del loop de WordPress.
      Por eso se usa la estructura have_posts() / the_post() / the_content().
    */
    if ( have_posts() ) {
        while ( have_posts() ) {
            the_post();
            the_content();
        }
    }
    ?>
</div>

<?php get_footer(); ?>
