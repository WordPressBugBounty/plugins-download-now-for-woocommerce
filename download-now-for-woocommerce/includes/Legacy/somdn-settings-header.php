<?php
/**
 * Settings Header
 *
 * Branded Wp Enhanced header
 *
 * @version  2.1
 */

defined('ABSPATH') || exit;

  $logo = plugins_url('/assets/images/logo.png', SOMDN_FILE);
  
  ?>

<div class="som-settings-nav">
  <a href="https://wpenhanced.com" target="_blank" rel="nofollow">
    <img class="som-brand-img" src="<?php echo $logo ?>">
    <h1 class="som-brand-name">WP Enhanced</h1>
  </a>
  <a href="https://profiles.wordpress.org/wpenhanced/" target="_blank"><div class="dashicons dashicons-wordpress"></div></a>
  <a href="https://wpenhanced.com" target="_blank"><div class="dashicons dashicons-desktop"></div></a>
</div>

<div class="som-settings-settings-spacer"></div>

<div class="somdn-debug">
  <h3>Plugin debugging</h3>
  <?php
    $schedule = wp_next_scheduled('somdn_delete_download_files_event');
    echo 'Next folder cleanup at ' . gmdate("d-m-Y T H:i:s", $schedule);
  ?>
</div>
