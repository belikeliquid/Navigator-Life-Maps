<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="nmm-timeline" class="nmm-app">

  <div class="nmm-editor-toolbar">
    <div class="nmm-logo">
      <svg width="22" height="22" viewBox="0 0 28 28" fill="none">
        <circle cx="14" cy="14" r="12" stroke="#0BBFBF" stroke-width="1.5"/>
        <circle cx="14" cy="14" r="3" fill="#0BBFBF"/>
        <line x1="14" y1="2" x2="14" y2="8" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="14" y1="20" x2="14" y2="26" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="2" y1="14" x2="8" y2="14" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="20" y1="14" x2="26" y2="14" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
      <span class="nmm-logo-text">NAVIGATOR TIMELINE</span>
    </div>
    <span class="nmm-sep"></span>
    <div class="nmm-tl-date-wrap">
      <label style="font-size:10px;color:rgba(255,255,255,0.6);letter-spacing:0.06em;text-transform:uppercase">From</label>
      <input type="date" id="nmm-tl-start" class="nmm-tl-date-input" />
    </div>
    <div class="nmm-tl-date-wrap">
      <label style="font-size:10px;color:rgba(255,255,255,0.6);letter-spacing:0.06em;text-transform:uppercase">To</label>
      <input type="date" id="nmm-tl-end" class="nmm-tl-date-input" />
    </div>
    <button class="nmm-btn nmm-btn-primary" id="nmm-tl-apply-btn">Apply</button>
    <span class="nmm-sep"></span>
    <div class="nmm-kb-filter-wrap" id="nmm-tl-filter-wrap">
      <button class="nmm-btn" id="nmm-tl-filter-btn">⊞ All maps ▾</button>
      <div class="nmm-kb-filter-panel" id="nmm-tl-filter-panel" style="display:none">
        <div class="nmm-kb-filter-actions">
          <button data-select="all">Select all</button>
          <button data-select="none">None</button>
        </div>
        <div id="nmm-tl-filter-list"></div>
      </div>
    </div>
    <a href="#" id="nmm-tl-map-link" class="nmm-btn nmm-btn-kanban" style="display:none" title="Open this map">◈ Mind Map</a>
    <a href="<?php echo esc_url( home_url( '/kanban/' ) ); ?>" id="nmm-tl-kanban-link" class="nmm-btn nmm-btn-kanban">⬡ Kanban</a>
    <span style="flex:1"></span>
    <a href="<?php echo esc_url( home_url( '/maps/' ) ); ?>" class="nmm-btn nmm-btn-nav">← All maps</a>
  </div>

  <!-- Timeline canvas -->
  <div class="nmm-tl-body" id="nmm-tl-body">
    <div id="nmm-tl-loading" class="nmm-loading"><div class="nmm-spinner"></div><span>Loading your maps...</span></div>
    <div id="nmm-tl-canvas-wrap" class="nmm-tl-canvas-wrap" style="display:none">
      <div id="nmm-tl-canvas" class="nmm-tl-canvas">
        <!-- Date markers -->
        <div id="nmm-tl-markers" class="nmm-tl-markers"></div>
        <!-- Spine -->
        <div id="nmm-tl-spine" class="nmm-tl-spine"></div>
        <!-- Nodes -->
        <div id="nmm-tl-nodes" class="nmm-tl-nodes"></div>
      </div>
    </div>
    <div id="nmm-tl-empty" style="display:none;text-align:center;padding:80px 20px;font-family:Barlow Condensed,sans-serif;color:#aaa">
      <div style="font-size:48px;margin-bottom:16px">⬡</div>
      <p style="font-size:18px;font-weight:600;color:#888">No nodes to display</p>
      <p style="font-size:14px">Set a date range and select maps to see your timeline.</p>
    </div>
  </div>

  <!-- Edit Node Modal -->
  <div class="nmm-modal-overlay" id="nmm-tl-edit-modal">
    <div class="nmm-modal">
      <h3>Edit Node</h3>
      <div class="nmm-field"><label>Title</label><input type="text" id="nmm-tl-title" class="nmm-input" /></div>
      <div class="nmm-field"><label>Subtitle lines (one per line)</label><textarea id="nmm-tl-sub" rows="2" class="nmm-input"></textarea></div>
      <div class="nmm-field"><label>Date</label><input type="date" id="nmm-tl-date" class="nmm-input" /></div>
      <div class="nmm-field"><label>Kanban tag</label>
        <select id="nmm-tl-tag" class="nmm-input">
          <option value="">No tag (Triage)</option>
          <option value="start">Start</option>
          <option value="doing">Doing</option>
          <option value="sprint">Sprint</option>
          <option value="review">Review</option>
          <option value="done">Done</option>
        </select>
      </div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" onclick="nmmTlCloseEdit()">Cancel</button>
        <button class="nmm-btn nmm-btn-primary" onclick="nmmTlSaveEdit()">Save</button>
      </div>
    </div>
  </div>
</div>
