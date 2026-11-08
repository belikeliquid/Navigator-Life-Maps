<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="nmm-timeline" class="nmm-app">

  <!-- Toolbar: ← Maps, map picker | view tabs (same order on every page) | dates -->
  <div class="nmm-editor-toolbar">
    <a href="<?php echo esc_url( home_url( '/maps/' ) ); ?>" class="nmm-btn nmm-btn-nav">← Maps</a>
    <div class="nmm-kb-filter-wrap" id="nmm-tl-filter-wrap">
      <button class="nmm-btn nmm-btn-keep" id="nmm-tl-filter-btn">⊞ All maps ▾</button>
      <div class="nmm-kb-filter-panel" id="nmm-tl-filter-panel" style="display:none">
        <div class="nmm-kb-filter-actions">
          <button data-select="all">Select all</button>
          <button data-select="none">None</button>
        </div>
        <div id="nmm-tl-filter-list"></div>
      </div>
    </div>
    <span class="nmm-sep"></span>
    <?php NMM_Shortcodes::view_tabs( 'timeline', NMM_Shortcodes::ids_from_url() ); ?>
    <span class="nmm-sep"></span>
    <div class="nmm-tl-tools">
      <div class="nmm-tl-date-wrap">
        <label for="nmm-tl-start">From</label>
        <input type="date" id="nmm-tl-start" class="nmm-tl-date-input" />
      </div>
      <div class="nmm-tl-date-wrap">
        <label for="nmm-tl-end">To</label>
        <input type="date" id="nmm-tl-end" class="nmm-tl-date-input" />
      </div>
      <button class="nmm-btn nmm-btn-primary nmm-btn-keep" id="nmm-tl-apply-btn">Apply</button>
    </div>
  </div>

  <!-- Timeline canvas -->
  <div class="nmm-tl-body" id="nmm-tl-body">
    <div id="nmm-tl-loading" class="nmm-loading"><div class="nmm-spinner"></div><span>Loading your maps...</span></div>
    <div id="nmm-tl-range-note" class="nmm-tl-range-note" style="display:none"></div>
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
    <div id="nmm-tl-empty" style="display:none;text-align:center;padding:80px 20px;color:#aaa">
      <div style="font-size:48px;margin-bottom:16px">⬡</div>
      <p style="font-size:18px;font-weight:600;color:#4b5563">No dated cards yet</p>
      <p style="font-size:15px;color:#6b7280">In a mind map, edit a card and give it a <strong>date</strong>. It will appear here.</p>
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
