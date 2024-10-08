<?php
/**
 * Free Downloads - Download Page
 * 
 * The function to display download links.
 * 
 * @version  2.4.6
 */

defined('ABSPATH') || exit;

use SOM\FreeDownloads\Helpers\Numerics;

add_action('somdn_download_button', 'somdn_get_download_button', 10, 6);
add_action('somdn_single_download_link', 'somdn_get_single_download_link', 10, 5);
add_action('somdn_multi_download_link', 'somdn_get_multi_download_link', 10, 5);
add_action('somdn_archive_product_page', 'somdn_product_page');

function somdn_product_page($args = array())
{
  $defaults = array(
    'archive' => false,
    'product' => '',
    'echo' => true,
    'archive_enabled' => false,
    'shortcode' => false,
    'shortcode_text' => '',
    'product_page_short' => false
  );

  $somdn_args = apply_filters('somdn_product_page_args', wp_parse_args($args, $defaults), $args, $defaults);

  $archive = $somdn_args['archive'];
  $product = $somdn_args['product'];
  $echo = $somdn_args['echo'];
  $archive_enabled = $somdn_args['archive_enabled'];
  $shortcode = $somdn_args['shortcode'];
  $shortcode_text = $somdn_args['shortcode_text'];
  $product_page_short = $somdn_args['product_page_short'];

  if (!$product) {
    $product = somdn_get_product();
  }
 
  if (!$product) {
    $product = somdn_get_global_product();
  }

  if (!$product) {
    return false;
  }

  if (!$archive && !somdn_is_single_product() && !is_page() && !$archive_enabled) {
    return false;
  }

  $product_id = Numerics::absInt(somdn_get_product_id($product));

  if ($shortcode == true || $archive == true) {
    $valid_default = somdn_is_product_valid($product_id);
    if (!$valid_default) {
      return;
    }
  } else {
    $valid_no_login = somdn_is_product_valid($product_id, false);
    if (!$valid_no_login) {
      return;
    }
  }

  $genoptions = get_option('somdn_gen_settings');
  $singleoptions = get_option('somdn_single_settings');
  $multioptions = get_option('somdn_multi_settings');
  $docoptions = get_option('somdn_docviewer_settings');

  $requirelogin = isset($genoptions['somdn_require_login']) ? true : false ;

  $allowed_tags = somdn_get_allowed_html_tags();

  $login_check_valid = (bool) apply_filters('somdn_is_login_check_valid', true, $requirelogin, $product_id, $product, $somdn_args);

  if ($login_check_valid === false) {
    ob_start();
    do_action('somdn_login_check_failed_message', $product_id, $product, $somdn_args);
    $message_content = ob_get_clean();
    if ($echo) {
      echo $message_content;
      return;
    } else {
      return $message_content;
    }
  }

  $downloads = somdn_get_files($product);

  // if product is object and is bundled product and downloads is empty, return
  if (is_object($product) && (get_class($product) == "WC_Product_Bundle") && empty($downloads)) {
    return;
  }


  $downloads_count = count($downloads);
  $is_single_download = (1 == $downloads_count) ? true : false ;

  $shownumber = (isset($multioptions['somdn_show_numbers']) && $multioptions['somdn_show_numbers']) ? true : false ;
   
  $buttoncss = (isset($genoptions['somdn_button_css']) && $genoptions['somdn_button_css']) ? esc_attr($genoptions['somdn_button_css']) : '' ;
  $buttonclass = (isset($genoptions['somdn_button_class']) && $genoptions['somdn_button_class']) ? esc_attr($genoptions['somdn_button_class']) : '' ;
  
  $linkcss = (isset($genoptions['somdn_link_css']) && $genoptions['somdn_link_css']) ? esc_attr($genoptions['somdn_link_css']) : '' ;
  $linkclass = (isset($genoptions['somdn_link_class']) && $genoptions['somdn_link_class']) ? esc_attr($genoptions['somdn_link_class']) : '' ;

  $pdfenabled = (isset($docoptions['somdn_docviewer_enable']) && $docoptions['somdn_docviewer_enable']) ? true : false ;

  if ($is_single_download) {

    // check if tracking is on
    
    $track_options = get_option('somdn_pro_track_settings');
    $track_enabled = isset($track_options['somdn_pro_track_enable']) ? $track_options['somdn_pro_track_enable'] : 0;

    if (is_array($singleoptions) && isset($singleoptions['somdn_single_again_button_text'])) {
        $download_again_text = $singleoptions['somdn_single_again_button_text'];
    } else {
        $download_again_text = '';
    }
    
    if ($track_enabled == '1' && $download_again_text !== '') {
        $product_id = somdn_get_product_id($product);
    
        if (somdn_has_user_downloaded_product($product_id)) {
            $buttontext = esc_html($download_again_text);
        } else {
            $buttontext = isset($singleoptions['somdn_single_button_text']) ? esc_html($singleoptions['somdn_single_button_text']) : __('Download Now', 'somdn-pro');
        }
    } else {
        if (isset($singleoptions['somdn_single_button_text']) && !empty($singleoptions['somdn_single_button_text'])) {
            $buttontext = esc_html($singleoptions['somdn_single_button_text']);
        } else {
            $buttontext = __('Download Now', 'somdn-pro');
        }
    }

  } else {

    if (isset($multioptions['somdn_multi_button_text']) && !empty($multioptions['somdn_multi_button_text'])) {
      $buttontext = esc_html($multioptions['somdn_multi_button_text']);
    } else {
      $buttontext = __('Download All', 'somdn-pro');
    }
  
  }
  
  $single_type = (isset($singleoptions['somdn_single_type']) && 2 == $singleoptions['somdn_single_type']) ? 2 : 1 ;
  
  $pdf_default = __('Download PDF', 'somdn-pro');
  $pdf_output = false;

  if (!$archive_enabled) {
    $archive_enabled = (isset($genoptions['somdn_include_archive_items']) && $genoptions['somdn_include_archive_items']) ? true : false ;
  }

  if ($archive_enabled && $archive && !$shortcode) {
    $buttontext = esc_html(somdn_shop_button_get_text());
    $single_type = 1;
  }
  
  if ($shortcode) {
    $single_type = 1;
  }
  
  if ($shortcode_text) {
    $buttontext = $shortcode_text;
  }
  
  ob_start();
  
  if ((is_page() || somdn_is_single_product()) && !$archive) {
    echo somdn_hide_cart_style();
  }

  if ($is_single_download) {

    if (empty($shortcode) && !$archive) {
      do_action('somdn_before_simple_wrap', $product_id);
    }
  
    /**
     * Load the single file only template
     */
    $template = somdn_get_template('single-file');
    if (!empty($template)) {
      include($template);
    }

    if (empty($shortcode) && !$archive) {
      do_action('somdn_after_simple_wrap', $product_id);
    }

    if (empty($shortcode) && !$archive) {
      do_action('somdn_single_errors');
    }

  } else {

    if ( class_exists('WC_Bundles') && $product->get_type() == 'bundle' && is_archive()) {
      $buttoncss = (isset($genoptions['somdn_button_css']) && $genoptions['somdn_button_css']) ? esc_attr($genoptions['somdn_button_css']) : '' ;
      $buttonclass = (isset($genoptions['somdn_button_class']) && $genoptions['somdn_button_class']) ? esc_attr($genoptions['somdn_button_class']) : '' ;
      ?>
      <div class="somdn-archive-download-form somdn-download-form bundled-single-file" data-url="<?php echo esc_url(get_permalink($product_id)); ?>">
        <a href="<?php echo esc_url(get_permalink($product_id)); ?>" class="single_add_to_cart_button button somdn-archive-download-button <?php echo esc_attr($buttonclass); ?> <?php echo esc_attr($buttoncss); ?>"><?php echo esc_html($buttontext); ?></a>
      </div>
      <?php 
    } else {

      $multi_type = (isset($multioptions['somdn_display_type']) && $multioptions['somdn_display_type']) ? $multioptions['somdn_display_type'] : '1' ;
  
      if ($archive_enabled && $archive) {
        $multi_type = 2;
      }
  
      if ($shortcode) {
        $multi_type = 2;
      }
  
      /**
       * 1. Links Only
       */
      if (1 == $multi_type) {
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_before_simple_wrap', $product_id);
        }
  
        /**
         * Load the multi-file links only template
         */
        $template = somdn_get_template('multi-file-links');
        if (!empty($template)) {
          include($template);
        }
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_after_simple_wrap', $product_id);
        }
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_single_errors');
        }
  
      /**
       * 2. Button Only
       */
      } elseif (2 == $multi_type) {
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_before_simple_wrap', $product_id);
        }
  
        /**
         * Load the multi-file button only template
         */
        $template = somdn_get_template('multi-file-button');
        if (!empty($template)) {
          include($template);
        }
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_after_simple_wrap', $product_id);
        }
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_single_errors');
        }
  
      /**
       * 3. Button + Checkboxes
       */
      } elseif (3 == $multi_type) {
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_before_simple_wrap', $product_id);
        }
  
        if (empty($buttonclass)) {
          $buttonclass = 'somdn-checkbox-submit';
        } else {
          $buttonclass .= 'somdn-checkbox-submit';
        }
  
        /**
         * Load the multi-file button & checkboxes template
         */
        $template = somdn_get_template('multi-file-button-checkboxes');
        if (!empty($template)) {
          include($template);
        }
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_after_simple_wrap', $product_id);
        }
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_single_errors');
        }
  
      /**
       * 4. Button + Links
       */
      } elseif (4 == $multi_type) {
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_before_simple_wrap', $product_id);
        }
  
        /**
         * Load the multi-file button & links template
         */
        $template = somdn_get_template('multi-file-button-links');
        if (!empty($template)) {
          include($template);
        }
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_after_simple_wrap', $product_id);
        }
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_single_errors');
        }
  
      /**
       * 5. Button & Filenames
       */
      } elseif (5 == $multi_type) {
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_before_simple_wrap', $product_id);
        }
  
        /**
         * Load the multi-file button & filenames template
         */
        $template = somdn_get_template('multi-file-button-filenames');
        if (!empty($template)) {
          include($template);
        }
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_after_simple_wrap', $product_id);
        }
  
        if (empty($shortcode) && !$archive) {
          do_action('somdn_single_errors');
        }
  
      }

    }

  }

  $content = ob_get_clean();
    
  if ($echo) {
    echo $content;
    return;
  } else {
    return $content;
  }

}

function somdn_get_download_button($text, $css, $archive = false, $product_id = '', $class = '', $all_zip = false) {
  echo apply_filters('somdn_get_download_button', $button = '', $text, $css, $archive, $product_id, $class, $all_zip);
}

function somdn_get_single_download_link($text, $css, $archive = false, $product_id = '', $class = '') {
  echo apply_filters('somdn_get_single_download_link', $link = '', $text, $css, $archive, $product_id, $class);
}

function somdn_get_multi_download_link($count, $css, $shownumber, $name, $class) {
  echo apply_filters('somdn_get_multi_download_link', $link = '', $count, $css, $shownumber, $name, $class);
}
