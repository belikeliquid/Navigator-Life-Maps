<?php if ( ! defined( 'ABSPATH' ) ) exit;
$map_id = isset($_GET['map_id']) ? intval($_GET['map_id']) : intval($map_id ?? 0);
?>
<div id="nmm-editor" class="nmm-app" data-map-id="<?php echo $map_id; ?>">

  <div id="nmm-editor-toolbar" class="nmm-editor-toolbar">
    <a href="<?php echo home_url('/maps/'); ?>" class="nmm-btn nmm-btn-nav">← Maps</a>
    <button type="button" class="nmm-map-picker" id="nmm-map-picker" title="Rename this map or open another"><span id="nmm-map-title-display" class="nmm-map-title-display"></span><span aria-hidden="true">▾</span></button>
    <span class="nmm-sep"></span>
    <?php NMM_Shortcodes::view_tabs( 'map', [ $map_id ] ); ?>
    <span class="nmm-sep"></span>
    <span class="nmm-harmony-group">
      <button class="nmm-btn nmm-btn-harmony" onclick="nmmHarmony()" title="Tidy the map in the chosen layout style">✦ Harmony</button>
      <button class="nmm-btn nmm-btn-harmony nmm-btn-layout" id="nmm-layout-btn" onclick="nmmToggleLayoutMenu(event)" aria-haspopup="true" title="Layout style"><span id="nmm-layout-icon"></span>▾</button>
    </span>
    <button class="nmm-btn" id="nmm-undo-btn" onclick="nmmUndo()" title="Undo (Ctrl+Z)" disabled>↩ Undo</button>
    <span class="nmm-sep"></span>
    <button class="nmm-btn nmm-btn-primary" onclick="nmmAddRoot()">+ Island</button>
    <button class="nmm-btn nmm-wide-only" onclick="nmmCenter()" title="Re-center map">⊙ Center</button>
    <button class="nmm-btn nmm-wide-only" onclick="nmmExpandAll()">Expand all</button>
    <button class="nmm-btn nmm-wide-only" onclick="nmmCollapseAll()">Collapse</button>
    <span class="nmm-toolbar-gap"></span>
    <button class="nmm-btn" id="nmm-more-btn" type="button">More ▾</button>
    <button class="nmm-btn nmm-btn-save" id="nmm-save-btn" onclick="nmmSave()">Save</button>
    <span id="nmm-save-status" class="nmm-save-status"></span>
    <button type="button" class="nmm-btn nmm-help-btn" title="Help: how Life Maps works" aria-label="Help">?</button>
    <button class="nmm-btn nmm-mobile-menu-btn" id="nmm-mobile-menu-btn" onclick="nmmToggleMobileMenu()">☰</button>
  </div>

  <!-- Menus opened from the toolbar; outside it so they aren't clipped -->
  <div id="nmm-picker-menu" class="nmm-menu" role="menu">
    <button type="button" role="menuitem" onclick="nmmRenameMap()">✎ Rename this map</button>
    <div class="nmm-menu-label">Open another map</div>
    <div id="nmm-picker-list"><span class="nmm-menu-note">Loading…</span></div>
  </div>
  <?php NMM_Shortcodes::more_menu( 'map' ); ?>

  <?php $nmm_layout_ai = true; include __DIR__ . '/layout-menu.php'; ?>

  <div id="nmm-mobile-menu" class="nmm-mobile-menu">
    <button onclick="nmmHarmony();nmmToggleMobileMenu()">✦ Harmony</button>
    <button onclick="nmmUndo();nmmToggleMobileMenu()">↩ Undo</button>
    <button onclick="nmmAddRoot();nmmToggleMobileMenu()">+ New Island</button>
    <button onclick="nmmCenter();nmmToggleMobileMenu()">⊙ Re-center</button>
    <button onclick="nmmExpandAll();nmmToggleMobileMenu()">Expand all</button>
    <button onclick="nmmCollapseAll();nmmToggleMobileMenu()">Collapse all</button>
    <button onclick="nmmToggleMobileMenu();nmmOpenReorganize()">✦ Reorganize with AI</button>
    <button onclick="nmmToggleMobileMenu();nmmAnimate()">▶ Animate</button>
    <button onclick="nmmOpenSettings();nmmToggleMobileMenu()">Settings</button>
    <button onclick="nmmOpenOutlineImport();nmmToggleMobileMenu()">📋 Import</button>
    <button onclick="nmmOpenGCalModal();nmmToggleMobileMenu()">→ GCal</button>
    <button onclick="nmmExportPrint();nmmToggleMobileMenu()">Export for print</button>
    <button onclick="nmmOpenPromptExport();nmmToggleMobileMenu()">Export Prompt</button>
    <button onclick="nmmSave();nmmToggleMobileMenu()">Save</button>
    <button onclick="nmmToggleMobileMenu();nmmOpenHelp()">? Help</button>
  </div>

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
  <!-- Outline (picked from the layout menu; assets/js/doc.js) shows in place of the map area -->
  <div id="nmm-doc" class="nmm-doc"></div>

  <!-- Node Edit Modal -->
  <div class="nmm-modal-overlay" id="nmm-node-modal">
    <div class="nmm-modal">
      <h3>Edit Node</h3>
      <div class="nmm-field"><label>Title</label><input type="text" id="nme-title" class="nmm-input" /></div>
      <div class="nmm-field"><label>Subtitle lines (one per line)</label><textarea id="nme-sub" rows="3" class="nmm-input"></textarea></div>
      <div class="nmm-field-row">
        <div class="nmm-field"><label for="nme-date">Start date</label><input type="date" id="nme-date" class="nmm-input" /></div>
        <div class="nmm-field"><label for="nme-end">End date <small>(optional)</small></label><input type="date" id="nme-end" class="nmm-input" /></div>
      </div>
      <div class="nmm-field">
        <label>Parent node</label>
        <select id="nme-parent" class="nmm-input"></select>
      </div>
      <label class="nmm-check"><input type="checkbox" id="nme-task" /> Kanban task <small>Show this card on the Kanban board</small></label>
      <div class="nmm-field" id="nme-tag-field">
        <label>Kanban tag</label>
        <select id="nme-tag" class="nmm-input">
          <option value="">No tag (Triage)</option>
          <option value="start">🔵 Start</option>
          <option value="doing">🟡 Doing</option>
          <option value="sprint">🟠 Sprint</option>
          <option value="review">🩷 Review</option>
          <option value="done">🟢 Done</option>
        </select>
      </div>
      <div class="nmm-field">
        <label>Tree of Life position (Sefirot)</label>
        <select id="nme-sefirot" class="nmm-input">
          <option value="">None</option>
          <option value="kether">Kether — Crown · Vision · Will</option>
          <option value="chochma">Chochma — Wisdom · Insight · Seed</option>
          <option value="binah">Binah — Understanding · Structure · Matrix</option>
          <option value="chesed">Chesed — Love · Mercy · Expansion</option>
          <option value="geburah">Geburah — Strength · Discipline · Boundaries</option>
          <option value="tiphereth">Tiphereth — Beauty · Center · Heart · Self</option>
          <option value="netzach">Netzach — Victory · Desire · Goals · Creativity</option>
          <option value="hod">Hod — Splendour · Communication · Expression</option>
          <option value="yesod">Yesod — Foundation · Daily Practice · Rhythm</option>
          <option value="malkuth">Malkuth — Kingdom · Material · Grounded · Reality</option>
        </select>
      </div>
      <!-- Card link: another map, something on Navigator, or a web page (assets/js/editor.js, "CARD LINK") -->
      <div class="nmm-field nmm-lp" id="nme-link">
        <label>Link <small>(optional)</small></label>
        <div class="nmm-lp-chosen" id="nme-link-chosen"></div>
        <button type="button" class="nmm-lp-add" id="nme-link-add">+ Link this card to a map, a Navigator tool or a web page</button>
        <div class="nmm-lp-picker" id="nme-link-picker">
          <div class="nmm-lp-seg" role="tablist">
            <button type="button" data-lt="map" role="tab"><i class="nmm-lp-ic nmm-link-map"></i>My maps</button>
            <button type="button" data-lt="nav" role="tab"><i class="nmm-lp-ic nmm-link-nav"></i>Navigator</button>
            <button type="button" data-lt="web" role="tab"><i class="nmm-lp-ic nmm-link-web"></i>Web link</button>
          </div>
          <div class="nmm-lp-pane" data-pane="map">
            <input type="search" id="nme-link-mapq" class="nmm-input" placeholder="Search your maps…" autocomplete="off" />
            <div class="nmm-lp-list" id="nme-link-maps"></div>
            <button type="button" class="nmm-lp-new" id="nme-link-new"><span>+</span><b>Make a new linked map<small id="nme-link-new-note">Named after this card, linked to it, and opened next</small></b></button>
          </div>
          <div class="nmm-lp-pane" data-pane="nav">
            <input type="search" id="nme-link-navq" class="nmm-input" placeholder="Search courses, consults, communities and tools…" autocomplete="off" />
            <div class="nmm-lp-list" id="nme-link-nav"></div>
          </div>
          <div class="nmm-lp-pane" data-pane="web">
            <input type="url" id="nme-link-url" class="nmm-input" placeholder="https://" autocomplete="off" />
            <input type="text" id="nme-link-name" class="nmm-input" placeholder="Name it (optional)" maxlength="120" style="margin-top:8px" />
            <div class="nmm-lp-hint" id="nme-link-web-hint">Any web page or shared document. It opens in a new tab.</div>
          </div>
        </div>
      </div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" onclick="nmmCloseNodeModal()">Cancel</button>
        <button class="nmm-btn nmm-btn-island" onclick="nmmMakeIsland()">↗ New Island</button>
        <button class="nmm-btn nmm-btn-danger" onclick="nmmDeleteEditing()">Delete</button>
        <button class="nmm-btn nmm-btn-primary" onclick="nmmSaveNode()">Save</button>
      </div>
    </div>
  </div>

  <!-- Settings Modal -->
  <div class="nmm-modal-overlay" id="nmm-settings-modal">
    <div class="nmm-modal">
      <h3>Map Settings</h3>
      <div class="nmm-field"><label>Map Title</label><input type="text" id="nms-title" class="nmm-input" /></div>
      <div class="nmm-field"><label>Category</label>
        <select id="nms-category" class="nmm-input">
          <option value="">No category</option>
          <option value="personal">Personal</option>
          <option value="client">Client</option>
          <option value="project">Project</option>
          <option value="team">Team</option>
        </select>
      </div>
      <div class="nmm-field"><label>Motif (always shown)</label><input type="text" id="nms-motif" class="nmm-input" /></div>
      <div class="nmm-field"><label>Random quotes (comma separated)</label><textarea id="nms-quotes" rows="2" class="nmm-input"></textarea></div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" onclick="nmmCloseSettings()">Cancel</button>
        <button class="nmm-btn nmm-btn-primary" onclick="nmmSaveSettings()">Save Settings</button>
      </div>
    </div>
  </div>

  <!-- Outline Import Modal -->
  <div class="nmm-modal-overlay" id="nmm-outline-modal">
    <div class="nmm-modal" style="width:500px;max-width:95vw">
      <h3>📋 Import Outline</h3>
      <p style="font-size:12px;color:#666;margin-bottom:12px">Paste an outline from Claude or any AI. Supports indented text, numbered lists, and bullet points. We'll build the node tree automatically.</p>
      <div class="nmm-field">
        <label>Paste your outline</label>
        <textarea id="nmm-outline-text" class="nmm-input" rows="8" placeholder="Vision&#10;  Goal one&#10;    Detail A&#10;    Detail B&#10;  Goal two&#10;Foundation&#10;  Daily practice&#10;  Weekly rhythm"></textarea>
      </div>
      <div id="nmm-outline-preview" class="nmm-outline-preview" style="display:none"></div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" onclick="nmmCloseOutlineImport()">Cancel</button>
        <button class="nmm-btn" onclick="nmmPreviewOutline()">Preview</button>
        <button class="nmm-btn nmm-btn-primary" onclick="nmmBuildFromOutline()">Build Map →</button>
      </div>
    </div>
  </div>

  <!-- Reorganize with AI (opened from the layout menu) -->
  <div class="nmm-modal-overlay" id="nmm-reorganize-modal">
    <div class="nmm-modal nmm-reorg">
      <h3>✦ Reorganize with AI</h3>
      <div id="nmm-reorg-error" class="nmm-login-error" style="display:none"></div>

      <div id="nmm-reorg-step-ask">
        <div class="nmm-field">
          <label for="nmm-reorg-wish">What would you like changed?</label>
          <textarea id="nmm-reorg-wish" class="nmm-input" rows="4" maxlength="2000" placeholder="For example: rebuild this map with more personal language and less text"></textarea>
        </div>
        <div class="nmm-reorg-ideas">
          <button type="button" class="nmm-chip">Less text on each card</button>
          <button type="button" class="nmm-chip">More personal language</button>
          <button type="button" class="nmm-chip">Group related cards together</button>
          <button type="button" class="nmm-chip">Put the most important first</button>
        </div>
        <p id="nmm-reorg-allowance" class="nmm-reorg-note"></p>
        <p id="nmm-reorg-restore" class="nmm-reorg-note" style="display:none"></p>
      </div>

      <div id="nmm-reorg-step-questions" style="display:none">
        <p class="nmm-reorg-note">A few questions so the new version fits what you want. Skip any you like.</p>
        <div id="nmm-reorg-questions"></div>
        <div class="nmm-reorg-warn"><strong>This rewrites the whole map.</strong> Cards keep their dates and stages unless you asked otherwise. You can bring this version back afterwards from this dialog.</div>
      </div>

      <div id="nmm-reorg-step-working" class="nmm-new-working" style="display:none">
        <div class="nmm-spinner"></div>
        <p>Reorganizing your map. This can take a minute or two.</p>
      </div>

      <div class="nmm-modal-actions" id="nmm-reorg-actions">
        <button type="button" class="nmm-btn" id="nmm-reorg-cancel">Cancel</button>
        <button type="button" class="nmm-btn nmm-btn-primary" id="nmm-reorg-next">Next</button>
      </div>
    </div>
  </div>

</div>
