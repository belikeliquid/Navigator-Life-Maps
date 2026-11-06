<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="nmm-kanban" class="nmm-app">

  <!-- Header toolbar — matches editor style exactly -->
  <div class="nmm-editor-toolbar">
    <a href="<?php echo esc_url( home_url( '/maps/' ) ); ?>" class="nmm-btn nmm-btn-nav">← Maps</a>
    <div class="nmm-logo">
      <svg width="22" height="22" viewBox="0 0 28 28" fill="none">
        <circle cx="14" cy="14" r="12" stroke="#0BBFBF" stroke-width="1.5"/>
        <circle cx="14" cy="14" r="3" fill="#0BBFBF"/>
        <line x1="14" y1="2" x2="14" y2="8" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="14" y1="20" x2="14" y2="26" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="2" y1="14" x2="8" y2="14" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
        <line x1="20" y1="14" x2="26" y2="14" stroke="#0BBFBF" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
      <span class="nmm-logo-text">NAVIGATOR KANBAN</span>
    </div>
    <span class="nmm-sep"></span>
    <!-- Multi-select map filter -->
    <div class="nmm-kb-filter-wrap" id="nmm-kb-filter-wrap">
      <button class="nmm-btn" id="nmm-kb-filter-btn">⊞ All maps ▾</button>
      <div class="nmm-kb-filter-panel" id="nmm-kb-filter-panel" style="display:none">
        <div class="nmm-kb-filter-actions">
          <button data-select="all">Select all</button>
          <button data-select="none">None</button>
        </div>
        <div id="nmm-kb-filter-list"></div>
      </div>
    </div>
    <a href="#" id="nmm-kb-map-link" class="nmm-btn nmm-btn-kanban" style="display:none" title="Open this map">◈ Mind Map</a>
    <a href="<?php echo esc_url( home_url( '/timeline/' ) ); ?>" id="nmm-kb-timeline-link" class="nmm-btn nmm-btn-kanban">◷ Timeline</a>
  </div>

  <!-- Kanban body -->
  <div class="nmm-kb-body">
    <div id="nmm-kb-loading" class="nmm-loading"><div class="nmm-spinner"></div><span>Loading your maps...</span></div>

    <!-- Triage row -->
    <div class="nmm-kb-triage-wrap" id="nmm-kb-triage-wrap" style="display:none">
      <div class="nmm-kb-triage-header">
        <span class="nmm-kb-triage-label">⬡ Triage</span>
        <span class="nmm-kb-triage-sub">Cards without a tag. Drag a card into a column, or click it to edit.</span>
      </div>
      <div id="nmm-kb-triage" class="nmm-kb-triage-cards" data-tag=""></div>
    </div>

    <!-- 5 Kanban columns -->
    <div class="nmm-kb-columns" id="nmm-kb-columns" style="display:none">
      <div class="nmm-kb-col" data-tag="start">
        <div class="nmm-kb-col-header nmm-kb-start">Start <span class="nmm-kb-col-count"></span></div>
        <div class="nmm-kb-col-warn" role="status" style="display:none"></div>
        <div class="nmm-kb-col-cards" data-tag="start"></div>
      </div>
      <div class="nmm-kb-col" data-tag="doing">
        <div class="nmm-kb-col-header nmm-kb-doing">Doing <span class="nmm-kb-col-count"></span></div>
        <div class="nmm-kb-col-warn" role="status" style="display:none"></div>
        <div class="nmm-kb-col-cards" data-tag="doing"></div>
      </div>
      <div class="nmm-kb-col" data-tag="sprint">
        <div class="nmm-kb-col-header nmm-kb-sprint">Sprint <span class="nmm-kb-col-count"></span></div>
        <div class="nmm-kb-col-warn" role="status" style="display:none"></div>
        <div class="nmm-kb-col-cards" data-tag="sprint"></div>
      </div>
      <div class="nmm-kb-col" data-tag="review">
        <div class="nmm-kb-col-header nmm-kb-review">Review <span class="nmm-kb-col-count"></span></div>
        <div class="nmm-kb-col-warn" role="status" style="display:none"></div>
        <div class="nmm-kb-col-cards" data-tag="review"></div>
      </div>
      <div class="nmm-kb-col" data-tag="done">
        <div class="nmm-kb-col-header nmm-kb-done">Done <span class="nmm-kb-col-count"></span></div>
        <div class="nmm-kb-col-warn" role="status" style="display:none"></div>
        <div class="nmm-kb-col-cards" data-tag="done"></div>
      </div>
    </div>

    <!-- Empty state -->
    <div id="nmm-kb-empty" style="display:none;text-align:center;padding:80px 20px;color:#aaa">
      <div style="font-size:48px;margin-bottom:16px">⬡</div>
      <p style="font-size:18px;font-weight:600;color:#4b5563">No Kanban tasks yet</p>
      <p style="font-size:15px;color:#6b7280">In a mind map, edit a card and tick <strong>Kanban task</strong>. It will appear here.</p>
      <a href="<?php echo home_url('/maps/'); ?>" class="nmm-btn nmm-btn-primary" style="margin-top:16px;display:inline-block">← Go to Mind Maps</a>
    </div>
  </div>

  <!-- Node edit modal -->
  <div class="nmm-modal-overlay" id="nmm-kb-edit-modal">
    <div class="nmm-modal">
      <h3>Edit Node</h3>
      <div class="nmm-field"><label>Title</label><input type="text" id="nmm-kb-title" class="nmm-input" /></div>
      <div class="nmm-field"><label>Subtitle lines (one per line)</label><textarea id="nmm-kb-sub" rows="2" class="nmm-input"></textarea></div>
      <div class="nmm-field"><label>Date</label><input type="date" id="nmm-kb-date" class="nmm-input" /></div>
      <label class="nmm-check"><input type="checkbox" id="nmm-kb-task" /> Kanban task <small>Untick to take this card off the board</small></label>
      <div class="nmm-field"><label>Kanban tag</label>
        <select id="nmm-kb-tag" class="nmm-input">
          <option value="">No tag (Triage)</option>
          <option value="start">🔵 Start</option>
          <option value="doing">🟡 Doing</option>
          <option value="sprint">🟠 Sprint</option>
          <option value="review">🩷 Review</option>
          <option value="done">🟢 Done</option>
        </select>
      </div>
      <div class="nmm-field"><label>Tree of Life position</label>
        <select id="nmm-kb-sefirot" class="nmm-input">
          <option value="">None</option>
          <option value="kether">Kether — Crown · Vision</option>
          <option value="chochma">Chochma — Wisdom · Insight</option>
          <option value="binah">Binah — Understanding · Structure</option>
          <option value="chesed">Chesed — Love · Mercy</option>
          <option value="geburah">Geburah — Strength · Discipline</option>
          <option value="tiphereth">Tiphereth — Beauty · Center · Self</option>
          <option value="netzach">Netzach — Victory · Goals</option>
          <option value="hod">Hod — Splendour · Communication</option>
          <option value="yesod">Yesod — Foundation · Practice</option>
          <option value="malkuth">Malkuth — Kingdom · Material</option>
        </select>
      </div>
      <div class="nmm-modal-actions">
        <a href="#" id="nmm-kb-open-map" class="nmm-btn" style="margin-right:auto">◈ Open in map</a>
        <button class="nmm-btn" onclick="nmmKbCloseEdit()">Cancel</button>
        <button class="nmm-btn nmm-btn-primary" onclick="nmmKbSaveEdit()">Save</button>
      </div>
    </div>
  </div>
</div>
