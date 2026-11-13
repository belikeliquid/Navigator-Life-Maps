<?php if ( ! defined( 'ABSPATH' ) ) exit;
// View-only mind map: a view link (?share=KEY) or a featured map (?map_id=N, signed in).
// Same toolbar as the editor, without the tools that change the map. Kanban, Timeline and
// Roadmap show the same map read only (the view tabs carry the key or id).
$map_id    = isset( $_GET['map_id'] ) ? intval( $_GET['map_id'] ) : intval( $map_id ?? 0 );
$share_key = NMM_Shortcodes::share_key();
?>
<div id="nmm-view" class="nmm-app nmm-view-mode" data-map-id="<?php echo intval( $map_id ); ?>" data-share-key="<?php echo esc_attr( $share_key ); ?>">

  <div class="nmm-editor-toolbar">
    <a href="<?php echo esc_url( home_url( '/maps/' ) ); ?>" class="nmm-btn nmm-btn-nav"><?php echo is_user_logged_in() ? '← Maps' : '← Life Maps'; ?></a>
    <span id="nmm-view-title" class="nmm-map-title-display nmm-view-title-plain"></span>
    <button type="button" class="nmm-viewonly-badge nmm-btn-keep" id="nmm-viewonly-badge" title="What view only means">View only</button>
    <span class="nmm-sep"></span>
    <?php NMM_Shortcodes::view_tabs( 'map', $map_id ? [ $map_id ] : [], true ); ?>
    <span class="nmm-sep"></span>
    <button class="nmm-btn nmm-wide-only" type="button" onclick="nmmCenter()" title="Re-center map">⊙ Center</button>
    <button class="nmm-btn nmm-wide-only" type="button" onclick="nmmExpandAll()">Expand all</button>
    <button class="nmm-btn nmm-wide-only" type="button" onclick="nmmCollapseAll()">Collapse</button>
    <span class="nmm-toolbar-gap"></span>
    <button class="nmm-btn nmm-btn-primary nmm-btn-keep" id="nmm-view-copy" type="button" style="display:none" title="Make your own copy of this map to edit">Use this map</button>
    <button class="nmm-btn nmm-btn-keep" id="nmm-more-btn" type="button">More ▾</button>
    <?php if ( is_user_logged_in() ) : ?>
      <button type="button" class="nmm-btn nmm-btn-keep nmm-help-btn" title="Help: how Life Maps works" aria-label="Help">?</button>
    <?php endif; ?>
  </div>
  <?php NMM_Shortcodes::more_menu( 'map' ); ?>

  <!-- Phones: List / Map switch and the list view (assets/js/outline.js) -->
  <div class="nmm-view-switch" role="group" aria-label="Show the map as">
    <button type="button" data-view="list">☰ List</button>
    <button type="button" data-view="map">◈ Map</button>
  </div>
  <div class="nmm-outline"></div>

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
