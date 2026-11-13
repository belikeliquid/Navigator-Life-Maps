<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="nmm-calendar" class="nmm-app">

  <!-- Toolbar: ← Maps, map picker | view tabs (same order on every page) | month, week or list -->
  <div class="nmm-editor-toolbar">
    <a href="<?php echo esc_url( home_url( '/maps/' ) ); ?>" class="nmm-btn nmm-btn-nav">← Maps</a>
    <div class="nmm-kb-filter-wrap" id="nmm-cal-filter-wrap">
      <button class="nmm-btn nmm-btn-keep" id="nmm-cal-filter-btn">⊞ All maps ▾</button>
      <div class="nmm-kb-filter-panel" id="nmm-cal-filter-panel" style="display:none">
        <div class="nmm-kb-filter-actions">
          <button data-select="all">Select all</button>
          <button data-select="none">None</button>
        </div>
        <div id="nmm-cal-filter-list"></div>
      </div>
    </div>
    <span class="nmm-sep"></span>
    <?php NMM_Shortcodes::view_tabs( 'calendar', NMM_Shortcodes::ids_from_url() ); ?>
    <span class="nmm-sep"></span>
    <div class="nmm-cal-tools">
      <button type="button" class="nmm-btn nmm-btn-keep" id="nmm-cal-prev" aria-label="Previous">‹</button>
      <button type="button" class="nmm-btn nmm-btn-keep" id="nmm-cal-today">Today</button>
      <button type="button" class="nmm-btn nmm-btn-keep" id="nmm-cal-next" aria-label="Next">›</button>
      <span class="nmm-cal-label" id="nmm-cal-label" aria-live="polite"></span>
      <div class="nmm-cal-modes" role="group" aria-label="Show as">
        <button type="button" data-mode="month">Month</button>
        <button type="button" data-mode="week">Week</button>
        <button type="button" data-mode="list">List</button>
      </div>
    </div>
    <span class="nmm-toolbar-gap"></span>
    <button class="nmm-btn nmm-btn-keep" id="nmm-more-btn" type="button">More ▾</button>
    <?php if ( is_user_logged_in() ) : ?>
      <button type="button" class="nmm-btn nmm-btn-keep nmm-help-btn" title="Help: how Life Maps works" aria-label="Help">?</button>
    <?php endif; ?>
  </div>

  <div class="nmm-cal-body" id="nmm-cal-body">
    <div id="nmm-cal-loading" class="nmm-loading"><div class="nmm-spinner"></div><span>Loading your maps...</span></div>
    <div id="nmm-cal-note" class="nmm-cal-note" style="display:none"></div>
    <div id="nmm-cal-grid" class="nmm-cal-grid" style="display:none"></div>
    <div id="nmm-cal-empty" class="nmm-cal-empty" style="display:none">
      <div class="nmm-cal-empty-icon">▦</div>
      <p class="nmm-cal-empty-title">No dated tasks yet</p>
      <p>The calendar shows Kanban tasks that have a date. In a map, edit a card, tick <strong>Kanban task</strong> and give it a <strong>date</strong>.</p>
    </div>
  </div>

  <!-- Card dialog -->
  <div class="nmm-modal-overlay" id="nmm-cal-edit-modal">
    <div class="nmm-modal">
      <h3>Edit card</h3>
      <div class="nmm-field"><label for="nmm-cal-title">Title</label><input type="text" id="nmm-cal-title" class="nmm-input" /></div>
      <div class="nmm-field"><label for="nmm-cal-sub">Subtitle lines (one per line)</label><textarea id="nmm-cal-sub" rows="2" class="nmm-input"></textarea></div>
      <div class="nmm-cal-dates">
        <div class="nmm-field"><label for="nmm-cal-date">Start</label><input type="date" id="nmm-cal-date" class="nmm-input" /></div>
        <div class="nmm-field"><label for="nmm-cal-end">End (optional)</label><input type="date" id="nmm-cal-end" class="nmm-input" /></div>
      </div>
      <div class="nmm-field"><label for="nmm-cal-tag">Kanban stage</label>
        <select id="nmm-cal-tag" class="nmm-input">
          <option value="">Triage</option>
          <option value="start">Start</option>
          <option value="doing">Doing</option>
          <option value="sprint">Sprint</option>
          <option value="review">Review</option>
          <option value="done">Done</option>
        </select>
      </div>
      <p class="nmm-cal-where" id="nmm-cal-where"></p>
      <div class="nmm-modal-actions">
        <a href="#" id="nmm-cal-open-map" class="nmm-btn" style="margin-right:auto">◈ Open in map</a>
        <button type="button" class="nmm-btn" id="nmm-cal-cancel">Cancel</button>
        <button type="button" class="nmm-btn nmm-btn-primary" id="nmm-cal-save">Save</button>
      </div>
    </div>
  </div>

  <!-- A day's cards, when there are more than fit in the month cell -->
  <div class="nmm-modal-overlay" id="nmm-cal-day-modal">
    <div class="nmm-modal nmm-cal-day-modal">
      <h3 id="nmm-cal-day-title"></h3>
      <div id="nmm-cal-day-list" class="nmm-cal-day-list"></div>
      <div class="nmm-modal-actions"><button type="button" class="nmm-btn" id="nmm-cal-day-close">Close</button></div>
    </div>
  </div>

  <?php NMM_Shortcodes::more_menu( 'calendar' ); ?>
</div>
