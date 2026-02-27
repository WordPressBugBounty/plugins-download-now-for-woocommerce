<?php
/**
 * Sanitizer for PDF/Document Viewer Settings (somdn_docviewer_settings)
 * 
 * All fields in this section are FREE features.
 */

add_filter('wpe_settings_sanitize_free-downloads-docviewer', function($sanitized, $data) {
  $out = [];

  // Toggle: Enable PDF Viewer
  $out['somdn_docviewer_enable'] = !empty($data['somdn_docviewer_enable']) ? 'on' : '';

  // Select: Display type (1 = Button, 2 = Link)
  $out['somdn_docviewer_single_display'] = isset($data['somdn_docviewer_single_display']) 
    ? sanitize_text_field($data['somdn_docviewer_single_display']) 
    : '1';

  // Text: Link/button text
  $out['somdn_docviewer_single_link_text'] = isset($data['somdn_docviewer_single_link_text']) 
    ? sanitize_text_field($data['somdn_docviewer_single_link_text']) 
    : '';

  return $out;
}, 10, 2);
