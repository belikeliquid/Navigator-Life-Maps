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
  <div id="nmm-more-menu" class="nmm-menu" role="menu">
    <button type="button" role="menuitem" class="nmm-narrow-only" onclick="nmmCenter()">⊙ Center</button>
    <button type="button" role="menuitem" class="nmm-narrow-only" onclick="nmmExpandAll()">Expand all</button>
    <button type="button" role="menuitem" class="nmm-narrow-only" onclick="nmmCollapseAll()">Collapse</button>
    <button type="button" role="menuitem" class="nmm-menu-split" onclick="nmmAnimate()"><span>▶ Animate<small>Reveal the map one card at a time</small></span></button>
    <button type="button" role="menuitem" onclick="nmmOpenSettings()">Settings</button>
    <button type="button" role="menuitem" onclick="nmmOpenOutlineImport()">📋 Import an outline</button>
    <button type="button" role="menuitem" onclick="nmmOpenGCalModal()">→ Google Calendar</button>
    <button type="button" role="menuitem" onclick="nmmExportPNG()">Export PNG</button>
    <button type="button" role="menuitem" onclick="nmmOpenPromptExport()"><span>Export Prompt<small>Text outline for Basecamp or AI</small></span></button>
  </div>

  <!-- Layout style menu (opened from the ▾ next to Harmony; outside the toolbar so it isn't clipped) -->
  <div id="nmm-layout-menu" class="nmm-layout-menu" role="menu">
    <button type="button" data-layout="flow" role="menuitem">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><rect x="8.5" y="2.5" width="7" height="5" rx="1.2" fill="currentColor"/><path d="M12 7.5v4M4.5 11.5h15M4.5 11.5v4M12 11.5v4M19.5 11.5v4"/><rect x="1.5" y="15.5" width="6" height="5" rx="1.2" fill="currentColor"/><rect x="9" y="15.5" width="6" height="5" rx="1.2" fill="currentColor"/><rect x="16.5" y="15.5" width="6" height="5" rx="1.2" fill="currentColor"/></svg>
      <span><strong>Flow Chart</strong><small>Top to bottom</small></span>
    </button>
    <button type="button" data-layout="mind" role="menuitem">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"><rect x="8" y="9.5" width="8" height="5" rx="2"/><rect x="1" y="2" width="5" height="4" rx="1.2"/><rect x="18" y="2" width="5" height="4" rx="1.2"/><rect x="1" y="10" width="5" height="4" rx="1.2"/><rect x="18" y="10" width="5" height="4" rx="1.2"/><rect x="1" y="18" width="5" height="4" rx="1.2"/><rect x="18" y="18" width="5" height="4" rx="1.2"/><path d="M8 12H6M16 12h2M9.5 9.5V4H6M14.5 9.5V4H18M9.5 14.5V20H6M14.5 14.5V20H18"/></svg>
      <span><strong>Mind Map</strong><small>From the centre out</small></span>
    </button>
    <button type="button" data-layout="tol" role="menuitem">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M12 2.5v19M6 6.5h12M6 11.5h12M6 16.5h12M6 6.5v10M18 6.5v10M12 2.5L6 6.5M12 2.5l6 4M6 6.5l6 3.5 6-3.5M6 11.5l6 3 6-3M6 16.5l6 2 6-2"/><g fill="currentColor" stroke="none"><circle cx="12" cy="2.5" r="1.8"/><circle cx="6" cy="6.5" r="1.8"/><circle cx="18" cy="6.5" r="1.8"/><circle cx="12" cy="9.5" r="1.8"/><circle cx="6" cy="11.5" r="1.8"/><circle cx="18" cy="11.5" r="1.8"/><circle cx="12" cy="14.5" r="1.8"/><circle cx="6" cy="16.5" r="1.8"/><circle cx="18" cy="16.5" r="1.8"/><circle cx="12" cy="18.5" r="1.8"/><circle cx="12" cy="21.5" r="1.8"/></g></svg>
      <span><strong>Tree of Life</strong><small>Cards by their Tree of Life position</small></span>
    </button>
  </div>

  <div id="nmm-mobile-menu" class="nmm-mobile-menu">
    <button onclick="nmmHarmony();nmmToggleMobileMenu()">✦ Harmony</button>
    <button onclick="nmmUndo();nmmToggleMobileMenu()">↩ Undo</button>
    <button onclick="nmmAddRoot();nmmToggleMobileMenu()">+ New Island</button>
    <button onclick="nmmCenter();nmmToggleMobileMenu()">⊙ Re-center</button>
    <button onclick="nmmExpandAll();nmmToggleMobileMenu()">Expand all</button>
    <button onclick="nmmCollapseAll();nmmToggleMobileMenu()">Collapse all</button>
    <button onclick="nmmToggleMobileMenu();nmmAnimate()">▶ Animate</button>
    <button onclick="nmmOpenSettings();nmmToggleMobileMenu()">Settings</button>
    <button onclick="nmmOpenOutlineImport();nmmToggleMobileMenu()">📋 Import</button>
    <button onclick="nmmOpenGCalModal();nmmToggleMobileMenu()">→ GCal</button>
    <button onclick="nmmExportPNG();nmmToggleMobileMenu()">Export PNG</button>
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

  <!-- Export Prompt: the map as a text outline with a description, for Basecamp or AI -->
  <div class="nmm-modal-overlay" id="nmm-prompt-modal">
    <div class="nmm-modal nmm-prompt-modal">
      <h3>Export Prompt</h3>
      <p class="nmm-prompt-intro">Your map as a text outline with a description of how to read it. Copy it into Basecamp, Claude or any AI.</p>
      <div class="nmm-field">
        <label for="nmm-prompt-purpose">Use it to</label>
        <select id="nmm-prompt-purpose" class="nmm-input" onchange="nmmUpdatePrompt()">
          <option value="basecamp">Create a Basecamp project</option>
          <option value="document">Write a document</option>
          <option value="synthesis">Write a synthesis</option>
          <option value="outline">Just the outline</option>
        </select>
      </div>
      <div class="nmm-field">
        <label for="nmm-prompt-text">Prompt</label>
        <textarea id="nmm-prompt-text" class="nmm-input nmm-prompt-text" rows="16" spellcheck="false"></textarea>
      </div>
      <div class="nmm-prompt-send">
        <button type="button" class="nmm-btn nmm-btn-basecamp" onclick="nmmSendToBasecamp()">Send to Basecamp as a new project</button>
        <p id="nmm-prompt-soon" class="nmm-prompt-soon" role="status" hidden></p>
      </div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" onclick="nmmClosePromptExport()">Close</button>
        <button class="nmm-btn" onclick="nmmDownloadPrompt()">Download .md</button>
        <button class="nmm-btn nmm-btn-primary" id="nmm-prompt-copy" onclick="nmmCopyPrompt()">Copy</button>
      </div>
    </div>
  </div>

  <div id="nmm-export-overlay" class="nmm-export-overlay">Generating PNG...</div>
</div>
