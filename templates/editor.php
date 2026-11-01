<?php if ( ! defined( 'ABSPATH' ) ) exit;
$map_id = isset($_GET['map_id']) ? intval($_GET['map_id']) : intval($map_id ?? 0);
?>
<div id="nmm-editor" class="nmm-app" data-map-id="<?php echo $map_id; ?>">

  <div id="nmm-editor-toolbar" class="nmm-editor-toolbar">
    <a href="<?php echo home_url('/maps/'); ?>" class="nmm-btn nmm-btn-nav">← Maps</a>
    <a href="<?php echo esc_url( home_url( '/kanban/?map_id=' . $map_id ) ); ?>" class="nmm-btn nmm-btn-kanban" title="This map as a Kanban board">⬡ Kanban</a>
    <a href="<?php echo esc_url( home_url( '/timeline/?map_id=' . $map_id ) ); ?>" class="nmm-btn nmm-btn-kanban" title="This map on a timeline">◷ Timeline</a>
    <span class="nmm-sep"></span>
    <span id="nmm-map-title-display" class="nmm-map-title-display" title="Click to rename"></span>
    <span class="nmm-sep"></span>
    <button class="nmm-btn nmm-btn-harmony" onclick="nmmHarmony()">✦ Harmony</button>
    <button class="nmm-btn" id="nmm-undo-btn" onclick="nmmUndo()" title="Undo (Ctrl+Z)" disabled>↩ Undo</button>
    <span class="nmm-sep"></span>
    <button class="nmm-btn nmm-btn-primary" onclick="nmmAddRoot()">+ Island</button>
    <button class="nmm-btn" onclick="nmmCenter()" title="Re-center map">⊙ Center</button>
    <button class="nmm-btn" onclick="nmmExpandAll()">Expand all</button>
    <button class="nmm-btn" onclick="nmmCollapseAll()">Collapse</button>
    <span class="nmm-sep"></span>
    <button class="nmm-btn" onclick="nmmOpenSettings()">Settings</button>
    <button class="nmm-btn" onclick="nmmOpenOutlineImport()">📋 Import outline</button>
    <button class="nmm-btn" onclick="nmmOpenGCalModal()">→ GCal</button>
    <button class="nmm-btn" onclick="nmmExportPNG()">Export PNG</button>
    <button class="nmm-btn nmm-btn-save" id="nmm-save-btn" onclick="nmmSave()">Save</button>
    <span id="nmm-save-status" class="nmm-save-status"></span>
    <button class="nmm-btn nmm-mobile-menu-btn" id="nmm-mobile-menu-btn" onclick="nmmToggleMobileMenu()">☰</button>
  </div>

  <div id="nmm-mobile-menu" class="nmm-mobile-menu">
    <button onclick="nmmHarmony();nmmToggleMobileMenu()">✦ Harmony</button>
    <button onclick="nmmUndo();nmmToggleMobileMenu()">↩ Undo</button>
    <button onclick="nmmAddRoot();nmmToggleMobileMenu()">+ New Island</button>
    <button onclick="nmmCenter();nmmToggleMobileMenu()">⊙ Re-center</button>
    <button onclick="nmmExpandAll();nmmToggleMobileMenu()">Expand all</button>
    <button onclick="nmmCollapseAll();nmmToggleMobileMenu()">Collapse all</button>
    <button onclick="nmmOpenSettings();nmmToggleMobileMenu()">Settings</button>
    <button onclick="nmmOpenOutlineImport();nmmToggleMobileMenu()">📋 Import</button>
    <button onclick="nmmOpenGCalModal();nmmToggleMobileMenu()">→ GCal</button>
    <button onclick="nmmExportPNG();nmmToggleMobileMenu()">Export PNG</button>
    <button onclick="nmmSave();nmmToggleMobileMenu()">Save</button>
  </div>

  <div id="nmm-canvas-wrap" class="nmm-canvas-wrap">
    <div id="nmm-bg-layer" class="nmm-bg-layer"></div>
    <div id="nmm-bg-overlay" class="nmm-bg-overlay"></div>
    <div id="nmm-canvas" class="nmm-canvas">
      <svg id="nmm-lines"></svg>
      <div id="nmm-motif" class="nmm-motif"></div>
    </div>
  </div>

  <!-- Node Edit Modal -->
  <div class="nmm-modal-overlay" id="nmm-node-modal">
    <div class="nmm-modal">
      <h3>Edit Node</h3>
      <div class="nmm-field"><label>Title</label><input type="text" id="nme-title" class="nmm-input" /></div>
      <div class="nmm-field"><label>Subtitle lines (one per line)</label><textarea id="nme-sub" rows="3" class="nmm-input"></textarea></div>
      <div class="nmm-field"><label>Date (Google Calendar compatible)</label><input type="date" id="nme-date" class="nmm-input" /></div>
      <div class="nmm-field">
        <label>Parent node</label>
        <select id="nme-parent" class="nmm-input"></select>
      </div>
      <div class="nmm-field">
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

  <!-- GCal Export Modal -->
  <div class="nmm-modal-overlay" id="nmm-gcal-modal">
    <div class="nmm-modal">
      <h3>→ Export to Google Calendar</h3>
      <p style="font-size:12px;color:#666;margin-bottom:12px">All dated nodes are selected. Uncheck any you don't want to export.</p>
      <div id="nmm-gcal-list" class="nmm-gcal-list"></div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" onclick="nmmCloseGCalModal()">Cancel</button>
        <button class="nmm-btn nmm-btn-primary" onclick="nmmDoGCalExport()">Export Selected →</button>
      </div>
    </div>
  </div>

  <div id="nmm-export-overlay" class="nmm-export-overlay">Generating PNG...</div>
</div>
