<?php if ( ! defined( 'ABSPATH' ) ) exit;
// Supports both direct map_id and share key from URL
$map_id = isset($map_id) ? intval($map_id) : 0;
$share_key = isset($_GET['share']) ? sanitize_text_field($_GET['share']) : '';
?>
<div id="nmm-view" class="nmm-app nmm-view-mode" data-map-id="<?php echo intval( $map_id ); ?>" data-share-key="<?php echo esc_attr( $share_key ); ?>">
  <div class="nmm-view-header">
    <div class="nmm-logo">
      <svg width="22" height="22" viewBox="0 0 28 28" fill="none"><circle cx="14" cy="14" r="12" stroke="#0BBFBF" stroke-width="2"/><circle cx="14" cy="14" r="3" fill="#0BBFBF"/><line x1="14" y1="2" x2="14" y2="8" stroke="#0BBFBF" stroke-width="2" stroke-linecap="round"/><line x1="14" y1="20" x2="14" y2="26" stroke="#0BBFBF" stroke-width="2" stroke-linecap="round"/><line x1="2" y1="14" x2="8" y2="14" stroke="#0BBFBF" stroke-width="2" stroke-linecap="round"/><line x1="20" y1="14" x2="26" y2="14" stroke="#0BBFBF" stroke-width="2" stroke-linecap="round"/></svg>
      <span class="nmm-logo-text">NAVIGATOR</span>
    </div>
    <span id="nmm-view-title" class="nmm-view-title"></span>
    <div class="nmm-view-actions">
      <button class="nmm-btn" onclick="nmmViewExpandAll()">Expand all</button>
      <button class="nmm-btn" onclick="nmmViewCollapseAll()">Collapse</button>
      <button class="nmm-btn" onclick="nmmViewExportPNG()">Export PNG</button>
    </div>
  </div>

  <div id="nmm-canvas-wrap" class="nmm-canvas-wrap">
    <div id="nmm-bg-layer" class="nmm-bg-layer"></div>
    <div id="nmm-bg-overlay" class="nmm-bg-overlay"></div>
    <div id="nmm-canvas" class="nmm-canvas">
      <svg id="nmm-lines"></svg>
      <div id="nmm-motif" class="nmm-motif"></div>
    </div>
  </div>
  <div id="nmm-export-overlay" class="nmm-export-overlay">Generating PNG...</div>
</div>
