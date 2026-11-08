<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="nmm-roadmap" class="nmm-app">

  <!-- Toolbar: ← Maps, map picker | view tabs (same order on every page) | scale, lanes, today -->
  <div class="nmm-editor-toolbar">
    <a href="<?php echo esc_url( home_url( '/maps/' ) ); ?>" class="nmm-btn nmm-btn-nav">← Maps</a>
    <button type="button" class="nmm-map-picker" id="nmm-rm-picker" title="Show another map's roadmap"><span id="nmm-rm-map-name" class="nmm-map-title-display">Roadmap</span><span aria-hidden="true">▾</span></button>
    <span class="nmm-sep"></span>
    <?php NMM_Shortcodes::view_tabs( 'roadmap', NMM_Shortcodes::ids_from_url() ); ?>
    <span class="nmm-sep"></span>
    <div class="nmm-tl-tools nmm-rm-tools">
      <div class="nmm-seg" id="nmm-rm-scale" role="group" aria-label="Time scale">
        <button type="button" data-scale="week">Weeks</button><button type="button" data-scale="month">Months</button><button type="button" data-scale="quarter">Quarters</button>
      </div>
      <div class="nmm-seg" id="nmm-rm-group" role="group" aria-label="Lanes by">
        <button type="button" data-group="phase">Phases</button><button type="button" data-group="branch">Branches</button>
      </div>
      <button type="button" class="nmm-btn nmm-btn-keep" id="nmm-rm-lanes-btn" title="Choose a set of lanes">Lanes…</button>
      <button type="button" class="nmm-btn nmm-btn-keep" id="nmm-rm-today">Today</button>
    </div>
  </div>
  <div id="nmm-rm-picker-menu" class="nmm-menu" role="menu"></div>
  <div id="nmm-rm-lane-menu" class="nmm-menu" role="menu"></div>

  <div class="nmm-rm-body">
    <div id="nmm-rm-loading" class="nmm-loading"><div class="nmm-spinner"></div><span>Loading your roadmap...</span></div>

    <!-- First visit to a map's roadmap: choose its lanes -->
    <section id="nmm-rm-setup" class="nmm-rm-setup" style="display:none">
      <h2>How should this roadmap be organized?</h2>
      <p>Lanes are the rows of the roadmap. Pick a set of phases to start from (you can rename, add, move and remove them later), or use the map's own main branches.</p>
      <div id="nmm-rm-presets" class="nmm-rm-presets"></div>
      <button type="button" class="nmm-btn" id="nmm-rm-setup-cancel" style="display:none">Keep the current lanes</button>
    </section>

    <!-- Cards without dates; drag one onto a lane (or tap it) to schedule it -->
    <section id="nmm-rm-tray" class="nmm-rm-tray" style="display:none">
      <div class="nmm-rm-tray-head"><strong>Not on the roadmap yet</strong> <span id="nmm-rm-tray-count"></span> <span class="nmm-rm-tray-hint">Drag a card onto a lane to give it dates, or click it.</span></div>
      <div id="nmm-rm-tray-cards" class="nmm-rm-tray-cards"></div>
    </section>

    <div id="nmm-rm-scroll" class="nmm-rm-scroll" style="display:none"><div id="nmm-rm-grid" class="nmm-rm-grid"></div></div>

    <div id="nmm-rm-empty" class="nmm-rm-empty" style="display:none">
      <p>No maps yet. Create a map, give its cards dates, and they appear here.</p>
      <a href="<?php echo esc_url( home_url( '/maps/' ) ); ?>" class="nmm-btn nmm-btn-primary">← Go to Maps</a>
    </div>
  </div>

  <!-- Edit a bar, or add a tray card to the roadmap -->
  <div class="nmm-modal-overlay" id="nmm-rm-modal">
    <div class="nmm-modal">
      <h3 id="nmm-rm-modal-title">Edit card</h3>
      <p class="nmm-new-hint" id="nmm-rm-where" style="margin:-6px 0 10px"></p>
      <div class="nmm-field"><label for="nmm-rm-title">Title</label><input type="text" id="nmm-rm-title" class="nmm-input" /></div>
      <div class="nmm-field-row">
        <div class="nmm-field"><label for="nmm-rm-start">Start date</label><input type="date" id="nmm-rm-start" class="nmm-input" /></div>
        <div class="nmm-field"><label for="nmm-rm-end">End date</label><input type="date" id="nmm-rm-end" class="nmm-input" /></div>
      </div>
      <p class="nmm-new-hint" style="margin:-4px 0 10px">Leave the end empty for a 3-week bar that fades out.</p>
      <div class="nmm-field-row">
        <div class="nmm-field"><label for="nmm-rm-tag">Stage</label><select id="nmm-rm-tag" class="nmm-input"></select></div>
        <div class="nmm-field" id="nmm-rm-lane-field"><label for="nmm-rm-lane">Lane</label><select id="nmm-rm-lane" class="nmm-input"></select></div>
      </div>
      <div class="nmm-field"><label for="nmm-rm-sub">Notes (one per line)</label><textarea id="nmm-rm-sub" rows="3" class="nmm-input"></textarea></div>
      <div class="nmm-modal-actions">
        <a href="#" id="nmm-rm-open-map" class="nmm-btn">◈ Open in map</a>
        <button type="button" class="nmm-btn" id="nmm-rm-clear" title="Take it off the roadmap (removes its dates)">Clear dates</button>
        <button type="button" class="nmm-btn" id="nmm-rm-cancel">Cancel</button>
        <button type="button" class="nmm-btn nmm-btn-primary" id="nmm-rm-save">Save</button>
      </div>
    </div>
  </div>
</div>
