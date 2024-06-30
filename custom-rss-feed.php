<?php
/*
Plugin Name: Feed Personalizado RDF - Pulzo
Description: Plugin para generar un feed personalizado para pulzo
Version: 1.0
Author: Mauricio Aguirre
Author URI: https://mauroaguirre.com
*/

// Agregar hook para generar el feed personalizado
add_action('init', 'generar_feed_personalizado');

function generar_feed_personalizado() {
  add_feed('feed-personalizado', 'generar_feed');
}

// Generar el contenido del feed
function generar_feed() {
  // Obtener las categorías excluidas desde el backend
  $excluded_categories = get_option('feed_personalizado_excluded_categories', array());

  // Definir los argumentos de la consulta para obtener las entradas recientes
  $args = array(
    'post_type' => 'post',
    'posts_per_page' => 10,
    'orderby' => 'date',
    'order' => 'DESC',
    'category__not_in' => $excluded_categories, // Excluir las categorías especificadas
  );

  // Obtener las entradas recientes
  $recent_posts = new WP_Query($args);

  // Iniciar la salida del feed en formato XML
  header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);

  // Generar el encabezado del feed
  echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?' . '>';
?>
<rss version="2.0"
     xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
     xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
  <title><?php bloginfo('name'); ?></title>
  <link><?php bloginfo('url'); ?></link>
  <description><?php bloginfo('description'); ?></description>
  <language><?php bloginfo('language'); ?></language>
  <pubDate><?php echo date('Y-m-d H:i:s'); ?></pubDate>
<lastBuildDate><?php echo date('Y-m-d H:i:s'); ?></lastBuildDate>
  <generator></generator>

<?php
  // Generar los elementos para cada entrada
  while ($recent_posts->have_posts()) : $recent_posts->the_post();
    $post_id = get_the_ID();
?>
  <item>
    <title><?php the_title_rss(); ?></title>
    <link><?php the_permalink_rss(); ?></link>
    <description><?php the_excerpt_rss(); ?></description>
    <guid isPermaLink="false"><?php the_guid(); ?></guid>
<pubDate><?php echo date('Y-m-d H:i:s', get_post_time('U', true, $post_id)); ?></pubDate>
    <content:encoded><![CDATA[<?php the_content_feed(); ?>]]></content:encoded>
    <image>
      <?php
        // Obtener la URL de la imagen destacada si existe
        if (has_post_thumbnail($post_id)) {
          $image_id = get_post_thumbnail_id($post_id);
          $image_url = wp_get_attachment_image_src($image_id, 'full');
          $image_description = get_post_meta($image_id, '_wp_attachment_image_alt', true);
          echo '<url>' . $image_url[0] . '</url>';
		echo '<copyright>' . ($image_description ? $image_description : 'Fotografia archivo ElDiario.com.co') . '</copyright>';

        }
      ?>
    </image>
  </item>
<?php endwhile; ?>

</channel>
</rss>
<?php
  // Restablecer laconsulta principal de WordPress
  wp_reset_postdata();
}

// Agregar una página de configuración en el backend para excluir categorías
add_action('admin_menu', 'agregar_pagina_configuracion');

function agregar_pagina_configuracion() {
  add_options_page(
    'Feed Personalizado - Configuración',
    'Feed Personalizado',
    'manage_options',
    'feed-personalizado-config',
    'mostrar_pagina_configuracion'
  );
}

function mostrar_pagina_configuracion() {
  // Guardar las opciones si se ha enviado el formulario
  if (isset($_POST['feed_personalizado_submit'])) {
    $excluded_categories = isset($_POST['feed_personalizado_excluded_categories']) ? $_POST['feed_personalizado_excluded_categories'] : array();
    update_option('feed_personalizado_excluded_categories', $excluded_categories);
    echo '<div class="notice notice-success"><p>Las categorías excluidas se han actualizado correctamente.</p></div>';
  }

  // Obtener las categorías existentes
  $categories = get_categories();

  // Obtener las categorías excluidas guardadas
  $excluded_categories = get_option('feed_personalizado_excluded_categories', array());
?>
  <div class="wrap">
    <h1>Configuración del Feed Personalizado</h1>
    <form method="post" action="">
      <table class="form-table">
        <tr valign="top">
          <th scope="row">Categorías excluidas:</th>
          <td>
            <select name="feed_personalizado_excluded_categories[]" multiple="multiple" style="min-height: 150px;">
              <?php
                foreach ($categories as $category) {
                  $selected = in_array($category->term_id, $excluded_categories) ? 'selected' : '';
                  echo '<option value="' . $category->term_id . '" ' . $selected . '>' . $category->name . '</option>';
                }
              ?>
            </select>
            <p class="description">Seleccione las categorías que desea excluir del feed personalizado.</p>
          </td>
        </tr>
      </table>
      <p class="submit">
        <input type="submit" name="feed_personalizado_submit" class="button-primary" value="Guardar cambios">
      </p>
    </form>
  </div>
<?php
}
