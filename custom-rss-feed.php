<?php
/*
Plugin Name: Subcategory Menu Plugin
Description: Muestra un menú de subcategorías y carga posts dinámicamente.
Version: 1.0
Author: Mauricio Aguirre
*/

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

// Función para mostrar el menú de subcategorías
function display_subcategory_menu($atts) {
    $output = '';
    $current_category = get_queried_object();

    $category_id = ($current_category instanceof WP_Term && $current_category->taxonomy === 'category')
        ? (($current_category->parent == 0) ? $current_category->term_id : $current_category->parent)
        : intval(shortcode_atts(['category_id' => 0], $atts)['category_id']);

    $subcategories = get_categories(['parent' => $category_id, 'hide_empty' => false]);

    if ($subcategories) {
        $output .= '<div class="subcategory-menu-container">';
        $output .= '<h3>Filtrar por subcategoría:</h3>';
        $output .= '<div class="subcategory-menu" data-parent-category="' . esc_attr($category_id) . '">';
        $output .= '<a href="' . esc_url(get_category_link($category_id)) . '" data-category-id="' . esc_attr($category_id) . '" class="active">Todos</a>';

        foreach ($subcategories as $subcategory) {
            $output .= '<a href="' . esc_url(get_category_link($subcategory->term_id)) . '" data-category-id="' . esc_attr($subcategory->term_id) . '">' . esc_html($subcategory->name) . '</a>';
        }

        $output .= '</div></div>';
        $output .= '<div id="category-content" class="post-grid">';
        $output .= load_initial_posts($category_id);
        $output .= '</div>';
        $output .= '<div id="loading-spinner" style="display: none;">Cargando...</div>';
    }

    return $output;
}
add_shortcode('subcategory_menu', 'display_subcategory_menu');

// Función para cargar los scripts y estilos necesarios
function add_subcategory_menu_assets() {
    wp_enqueue_script('jquery');
    ?>
    <style>
        .subcategory-menu-container {
            background-color: #f8f8f8;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 10px;
        }
        .subcategory-menu-container h3 {
            margin: 0 0 15px;
            font-size: 20px;
            color: #333;
        }
        .subcategory-menu {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .subcategory-menu a {
            text-decoration: none;
            padding: 10px 20px;
            background-color: #e0e0e0;
            color: #333;
            border-radius: 25px;
            transition: 0.3s ease;
            font-size: 16px;
        }
        .subcategory-menu a:hover, .subcategory-menu a.active {
            background-color: #e91e63;
            color: #fff;
        }
        .post-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }
        .post-item {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            transition: 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        .post-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .post-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .post-item h2 {
            font-size: 18px;
            margin: 15px;
            color: #e91e63;
        }
        .post-item .meta-info {
            font-size: 14px;
            color: #666;
            margin: 0 15px 15px;
        }
        #loading-spinner {
            text-align: center;
            padding: 20px;
            font-size: 18px;
            color: #666;
        }
    </style>
    <script>
    jQuery(document).ready(function($) {
        $('.subcategory-menu a').on('click', function(e) {
            e.preventDefault();
            var $this = $(this);
            var categoryId = $this.data('category-id');

            $('.subcategory-menu a').removeClass('active');
            $this.addClass('active');

            $('#category-content').addClass('loading');
            $('#loading-spinner').show();

            $.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                action: 'load_category_content',
                category_id: categoryId
            }, function(response) {
                $('#category-content').html(response).removeClass('loading');
            }).fail(function() {
                $('#category-content').html('<p>Error al cargar el contenido. Por favor, intente de nuevo.</p>').removeClass('loading');
            }).always(function() {
                $('#loading-spinner').hide();
            });
        });

        $(document).on('click', '.post-item', function() {
            window.location.href = $(this).find('h2 a').attr('href');
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'add_subcategory_menu_assets');

// Función para cargar los posts iniciales
function load_initial_posts($category_id) {
    $query = new WP_Query(['cat' => $category_id, 'posts_per_page' => 9]);
    ob_start();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            ?>
            <div class="post-item">
                <?php if (has_post_thumbnail()) : ?>
                    <?php the_post_thumbnail('medium'); ?>
                <?php else : ?>
                    <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'default-image.jpg'); ?>" alt="Imagen por defecto">
                <?php endif; ?>
                <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <p class="meta-info"><?php echo get_the_date(); ?> // <?php comments_number('No hay comentarios', '1 comentario', '% comentarios'); ?></p>
            </div>
            <?php
        }
    } else {
        echo '<p>No se encontraron posts en esta categoría.</p>';
    }
    wp_reset_postdata();

    return ob_get_clean();
}

// Función AJAX para cargar contenido de categorías
function load_category_content() {
    check_ajax_referer('load_category_nonce', 'security');
    $category_id = intval($_POST['category_id']);
    echo load_initial_posts($category_id);
    wp_die();
}
add_action('wp_ajax_load_category_content', 'load_category_content');
add_action('wp_ajax_nopriv_load_category_content', 'load_category_content');

// Mostrar el shortcode en el backend
function subcategory_menu_shortcode_page() {
    ?>
    <div class="wrap">
        <h1>Subcategory Menu Shortcode</h1>
        <p>Usa el siguiente shortcode para mostrar el menú de subcategorías:</p>
        <code>[subcategory_menu]</code>
    </div>
    <?php
}

function subcategory_menu_admin_menu() {
    add_menu_page('Subcategory Menu', 'Subcategory Menu', 'manage_options', 'subcategory-menu', 'subcategory_menu_shortcode_page', 'dashicons-list-view', 20);
}
add_action('admin_menu', 'subcategory_menu_admin_menu');
?>
