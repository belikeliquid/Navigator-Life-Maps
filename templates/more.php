<?php if ( ! defined( 'ABSPATH' ) ) exit;
// The More menu and its dialogs, shared by the Mind Map, Kanban, Timeline and Roadmap.
// Included through NMM_Shortcodes::more_menu( $view ); assets/js/more.js makes it work.
// $view: map / kanban / timeline / roadmap
?>
  <div id="nmm-more-menu" class="nmm-menu" role="menu">
<?php if ( 'map' === $view ) : ?>
    <button type="button" role="menuitem" class="nmm-narrow-only" onclick="nmmCenter()">⊙ Center</button>
    <button type="button" role="menuitem" class="nmm-narrow-only" onclick="nmmExpandAll()">Expand all</button>
    <button type="button" role="menuitem" class="nmm-narrow-only" onclick="nmmCollapseAll()">Collapse</button>
<?php endif; ?>
    <button type="button" role="menuitem" class="nmm-menu-split" onclick="nmmAnimate()"><span>▶ Animate<small><?php echo 'map' === $view ? 'Reveal the map one card at a time' : 'Reveal the cards one at a time'; ?></small></span></button>
    <button type="button" role="menuitem" onclick="nmmOpenSettings()">Settings</button>
    <button type="button" role="menuitem" onclick="nmmOpenOutlineImport()">📋 Import an outline</button>
    <button type="button" role="menuitem" onclick="nmmOpenGCalModal()">→ Google Calendar</button>
    <button type="button" role="menuitem" onclick="nmmExportPrint()"><span>Export for print<small>White background, easy to read</small></span></button>
    <button type="button" role="menuitem" onclick="nmmOpenPromptExport()"><span>Export Prompt<small>Text outline for Basecamp or AI</small></span></button>
  </div>

  <!-- Google Calendar: the dated cards, all ticked -->
  <div class="nmm-modal-overlay" id="nmm-gcal-modal">
    <div class="nmm-modal">
      <h3>→ Export to Google Calendar</h3>
      <p style="font-size:12px;color:#666;margin-bottom:12px">All dated cards are selected. Untick any you don't want to export.</p>
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

  <div id="nmm-export-overlay" class="nmm-export-overlay">Making the image…</div>
