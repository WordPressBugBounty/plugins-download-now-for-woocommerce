<?php
/**
 * Free Downloads - WooCommerce shop listing output
 */

defined('ABSPATH') || exit;

// Need to override the default WooCommerce shop loop
if (!function_exists('woocommerce_template_loop_add_to_cart')) {

  function woocommerce_template_loop_add_to_cart($args = array()) {

    $genoptions = get_option('somdn_gen_settings');
    $hide_readmore = (bool) ($genoptions['somdn_hide_readmore_button_archive'] ?? false);

    global $product;

    $product_id = somdn_get_product_id($product);

    $woo_args = $args;

    $download_page_args = apply_filters(
      'somdn_template_loop_add_to_cart_args',
      [
        'archive'=> true,
        'product' => $product,
        'echo' => true
      ]
    );

    if (somdn_is_product_valid($product_id)) {
      // If the user is completely entitled to download this product
      do_action('somdn_shop_download_button', $product_id, $download_page_args, $woo_args);
      return;

    } elseif (somdn_is_product_valid($product_id, false)) {
      // If the user would be able to download the product if they were logged in
      if ($hide_readmore) {
        do_action('somdn_shop_free_no_download_login_hide_readmore', $product_id, $download_page_args, $woo_args);
      } else {
        do_action('somdn_shop_free_if_logged_in', $product_id, $download_page_args, $woo_args);
      }

    } else {
      // An extra action check that plugins can hook into
      $extra_archive_action = somdn_extra_archive_action($product_id, $hide_readmore);
      if ($extra_archive_action && $hide_readmore) {
        return;
      }

      // See function somdn_do_default_woo_archive()
      do_action('somdn_default_woo_archive', $args);
    }

  }
}

/*
 * Button/link to display if the user could download the file if they were logged in
 */
function somdn_shop_free_if_logged_in_button($product_id, $download_page_args, $woo_args)
{
  $product = somdn_get_product($product_id);
  if (empty($product)) {
    return;
  }

  $buttontext = esc_html(somdn_shop_button_get_text('logged_out'));

  $classes = esc_html(apply_filters('somdn_shop_free_if_logged_in_button_class', 'button'));

  echo '<a rel="nofollow" href="'.esc_url(get_the_permalink($product_id)).'" class="'.$classes.'">'.$buttontext.'</a>';
}

function somdn_do_shop_download_button($product_id, $download_page_args, $woo_args)
{
  do_action('somdn_archive_product_page_before', $download_page_args, $woo_args);

  echo '<div>';
  do_action('somdn_archive_product_page', $download_page_args);
  echo '</div>';

  do_action('somdn_archive_product_page_after', $download_page_args, $woo_args);
}

function somdn_do_default_woo_archive($args = [])
{
  global $product;
  if ( $product ) {
    $defaults = array(
      'quantity'   => 1,
      'class'      => implode(
        ' ',
        array_filter(
          array(
            'button',
            'product_type_' . $product->get_type(),
            $product->is_purchasable() && $product->is_in_stock() ? 'add_to_cart_button' : '',
            $product->supports( 'ajax_add_to_cart' ) && $product->is_purchasable() && $product->is_in_stock() ? 'ajax_add_to_cart' : '',
          )
        )
      ),
      'attributes' => array(
        'data-product_id'  => $product->get_id(),
        'data-product_sku' => $product->get_sku(),
        'aria-label'       => $product->add_to_cart_description(),
        'rel'              => 'nofollow',
      ),
    );

    $args = apply_filters( 'woocommerce_loop_add_to_cart_args', wp_parse_args( $args, $defaults ), $product );

    if ( isset( $args['attributes']['aria-label'] ) ) {
      $args['attributes']['aria-label'] = wp_strip_all_tags( $args['attributes']['aria-label'] );
    }

    wc_get_template( 'loop/add-to-cart.php', $args );
  }
}
