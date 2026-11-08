<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="nmm-manager" class="nmm-app nmm-manager">

  <!-- Header toolbar — matches editor/kanban style -->
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
      <span class="nmm-logo-text">NAVIGATOR MAPS</span>
    </div>
    <span class="nmm-sep"></span>
    <?php NMM_Shortcodes::view_tabs( '' ); ?>
    <span class="nmm-toolbar-gap"></span>
    <?php if ( current_user_can('nmm_edit_maps') || current_user_can('edit_posts') || current_user_can('manage_options') ) : ?>
    <span id="nmm-map-count" class="nmm-map-count" style="display:none"></span>
    <button class="nmm-btn nmm-btn-primary nmm-btn-keep" id="nmm-new-map-btn">+ New Map</button>
    <?php endif; ?>
    <?php if ( is_user_logged_in() ) : ?>
    <button class="nmm-btn nmm-btn-keep" id="nmm-logout-btn" style="opacity:0.8">Log out</button>
    <?php endif; ?>
  </div>

  <!-- Search + filter bar -->
  <div class="nmm-manager-search-bar">
    <input type="text" id="nmm-search" class="nmm-search-input" placeholder="Search maps..." />
    <select id="nmm-filter-category" class="nmm-select">
      <option value="">All categories</option>
      <option value="personal">Personal</option>
      <option value="client">Client</option>
      <option value="project">Project</option>
      <option value="team">Team</option>
    </select>
  </div>

  <!-- Maps grid -->
  <div class="nmm-manager-body">
    <div id="nmm-loading" class="nmm-loading"><div class="nmm-spinner"></div><span>Loading your maps...</span></div>
    <div id="nmm-maps-grid" class="nmm-maps-grid" style="display:none"></div>
    <div id="nmm-empty" class="nmm-empty-state" style="display:none">
      <div class="nmm-empty-icon">⬡</div>
      <p>No maps yet. Create your first map to get started.</p>
      <?php if ( current_user_can('nmm_edit_maps') || current_user_can('edit_posts') || current_user_can('manage_options') ) : ?>
      <button class="nmm-btn nmm-btn-primary" id="nmm-empty-new-btn">+ Create your first map</button>
      <?php endif; ?>
    </div>
  </div>

  <!-- New Map Modal — details (layout + how to start), then AI questions one at a time. Driven by manager.js -->
  <div class="nmm-modal-overlay" id="nmm-new-modal">
    <div class="nmm-modal" style="width:560px;max-width:96vw">
      <h3>Create a new map</h3>
      <div id="nmm-new-error" class="nmm-login-error" style="display:none"></div>

      <div id="nmm-new-step-details">
      <div class="nmm-field">
        <label>Map title</label>
        <input type="text" id="nmm-new-title" class="nmm-input" placeholder="e.g. April 2026 Planning" />
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="nmm-field">
          <label>Category</label>
          <select id="nmm-new-category" class="nmm-input">
            <option value="">No category</option>
            <option value="personal">Personal</option>
            <option value="client">Client</option>
            <option value="project">Project</option>
            <option value="team">Team</option>
          </select>
        </div>
        <div class="nmm-field">
          <label>Color</label>
          <div class="nmm-color-swatches" style="margin-top:6px">
            <div class="nmm-color-swatch active" data-color="#0BBFBF" style="background:#0BBFBF"></div>
            <div class="nmm-color-swatch" data-color="#534AB7" style="background:#534AB7"></div>
            <div class="nmm-color-swatch" data-color="#EF9F27" style="background:#EF9F27"></div>
            <div class="nmm-color-swatch" data-color="#5DCAA5" style="background:#5DCAA5"></div>
            <div class="nmm-color-swatch" data-color="#F4C0D1" style="background:#F4C0D1"></div>
            <div class="nmm-color-swatch" data-color="#1a2e4a" style="background:#1a2e4a"></div>
          </div>
          <input type="hidden" id="nmm-new-color" value="#0BBFBF" />
        </div>
      </div>

      <div class="nmm-field">
        <label>Layout</label>
        <div class="nmm-mode-tabs nmm-tabs-3">
          <button type="button" class="nmm-mode-tab nmm-layout-tab active" data-layout="flow"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"><rect x="8.5" y="2.5" width="7" height="5" rx="1.2" fill="currentColor"/><path d="M12 7.5v4M4.5 11.5h15M4.5 11.5v4M12 11.5v4M19.5 11.5v4"/><rect x="1.5" y="15.5" width="6" height="5" rx="1.2" fill="currentColor"/><rect x="9" y="15.5" width="6" height="5" rx="1.2" fill="currentColor"/><rect x="16.5" y="15.5" width="6" height="5" rx="1.2" fill="currentColor"/></svg> Flow Chart</button>
          <button type="button" class="nmm-mode-tab nmm-layout-tab" data-layout="mind"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"><rect x="8" y="9.5" width="8" height="5" rx="2"/><rect x="1" y="2" width="5" height="4" rx="1.2"/><rect x="18" y="2" width="5" height="4" rx="1.2"/><rect x="1" y="18" width="5" height="4" rx="1.2"/><rect x="18" y="18" width="5" height="4" rx="1.2"/><path d="M9.5 9.5V4H6M14.5 9.5V4H18M9.5 14.5V20H6M14.5 14.5V20H18"/></svg> Mind Map</button>
          <button type="button" class="nmm-mode-tab nmm-layout-tab" data-layout="tol"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M12 2.5v19M6 6.5h12M6 11.5h12M6 16.5h12M6 6.5v10M18 6.5v10"/><g fill="currentColor" stroke="none"><circle cx="12" cy="2.5" r="1.8"/><circle cx="6" cy="6.5" r="1.8"/><circle cx="18" cy="6.5" r="1.8"/><circle cx="6" cy="11.5" r="1.8"/><circle cx="18" cy="11.5" r="1.8"/><circle cx="12" cy="14.5" r="1.8"/><circle cx="6" cy="16.5" r="1.8"/><circle cx="18" cy="16.5" r="1.8"/><circle cx="12" cy="21.5" r="1.8"/></g></svg> Tree of Life</button>
        </div>
        <p id="nmm-layout-hint" class="nmm-new-hint"></p>
      </div>

      <div class="nmm-field">
        <label>Start from</label>
        <div class="nmm-mode-tabs nmm-tabs-3">
          <button type="button" class="nmm-mode-tab nmm-start-tab active" data-mode="ai">&#10022; AI Organize</button>
          <button type="button" class="nmm-mode-tab nmm-start-tab" data-mode="outline">&#9776; Outline</button>
          <button type="button" class="nmm-mode-tab nmm-start-tab" data-mode="blank">&#9633; Blank</button>
        </div>
      </div>

      <div id="nmm-mode-ai" class="nmm-mode-section">
        <div class="nmm-field">
          <label>Paste your notes</label>
          <textarea id="nmm-ai-notes" class="nmm-input" rows="5" placeholder="Paste anything. Bullet points, paragraphs, goals, tasks, ideas."></textarea>
          <p class="nmm-new-hint">Navigator will ask a few questions about your priorities, then organize everything with tags and dates.</p>
        </div>
      </div>

      <div id="nmm-mode-outline" class="nmm-mode-section" style="display:none">
        <div class="nmm-field">
          <label>Paste your outline</label>
          <textarea id="nmm-new-outline" class="nmm-input" rows="6" placeholder="Vision&#10;  Goal one&#10;    Detail A&#10;    Detail B&#10;  Goal two&#10;Foundation"></textarea>
          <p class="nmm-new-hint">Indent with spaces or tabs to create hierarchy.</p>
        </div>
      </div>

      <div id="nmm-mode-blank" class="nmm-mode-section" style="display:none">
        <p class="nmm-new-hint">Start with an empty canvas. Add cards freely in the editor.</p>
      </div>
      </div><!-- /details -->

      <div id="nmm-new-step-question" style="display:none">
        <p id="nmm-q-progress" class="nmm-q-progress"></p>
        <div class="nmm-q-bar"><span id="nmm-q-bar-fill"></span></div>
        <label id="nmm-q-text" class="nmm-q-text" for="nmm-q-answer"></label>
        <textarea id="nmm-q-answer" class="nmm-input" rows="4" placeholder="Your answer..."></textarea>
        <p class="nmm-new-hint">Not sure? Leave it blank and press Next.</p>
      </div>

      <div id="nmm-new-step-working" class="nmm-new-working" style="display:none">
        <svg class="nmm-chart" viewBox="0 0 280 140" role="img" aria-label="Charting your map">
          <g class="nmm-chart-branch nmm-chart-b1"><path d="M140 70 C100 70 96 22 62 22" pathLength="1"/><rect x="10" y="12" width="52" height="20" rx="6"/><line x1="19" y1="22" x2="46" y2="22"/></g>
          <g class="nmm-chart-branch nmm-chart-b2"><path d="M140 70 C180 70 184 22 218 22" pathLength="1"/><rect x="218" y="12" width="52" height="20" rx="6"/><line x1="227" y1="22" x2="254" y2="22"/></g>
          <g class="nmm-chart-branch nmm-chart-b3"><path d="M140 70 C100 70 96 118 62 118" pathLength="1"/><rect x="10" y="108" width="52" height="20" rx="6"/><line x1="19" y1="118" x2="46" y2="118"/></g>
          <g class="nmm-chart-branch nmm-chart-b4"><path d="M140 70 C180 70 184 118 218 118" pathLength="1"/><rect x="218" y="108" width="52" height="20" rx="6"/><line x1="227" y1="118" x2="254" y2="118"/></g>
          <circle class="nmm-chart-face" cx="140" cy="70" r="31"/>
          <circle class="nmm-chart-orbit" cx="140" cy="70" r="39"/>
          <g class="nmm-chart-ticks"><line x1="140" y1="39" x2="140" y2="45" transform="rotate(0 140 70)"/><line x1="140" y1="41" x2="140" y2="45" transform="rotate(45 140 70)"/><line x1="140" y1="39" x2="140" y2="45" transform="rotate(90 140 70)"/><line x1="140" y1="41" x2="140" y2="45" transform="rotate(135 140 70)"/><line x1="140" y1="39" x2="140" y2="45" transform="rotate(180 140 70)"/><line x1="140" y1="41" x2="140" y2="45" transform="rotate(225 140 70)"/><line x1="140" y1="39" x2="140" y2="45" transform="rotate(270 140 70)"/><line x1="140" y1="41" x2="140" y2="45" transform="rotate(315 140 70)"/></g>
          <g class="nmm-chart-needle"><path class="nmm-chart-north" d="M140 46 L146 70 L134 70 Z"/><path class="nmm-chart-south" d="M140 94 L146 70 L134 70 Z"/><circle cx="140" cy="70" r="3"/></g>
        </svg>
        <p id="nmm-work-text" class="nmm-work-text" aria-live="polite">Reading your notes</p>
        <p class="nmm-new-hint">This can take up to a minute or two. Please keep this page open.</p>
      </div>

      <div class="nmm-modal-actions" id="nmm-new-actions">
        <button type="button" class="nmm-btn" id="nmm-new-cancel">Cancel</button>
        <button type="button" class="nmm-btn nmm-btn-primary" id="nmm-new-confirm">Next &#8594;</button>
      </div>
    </div>
  </div>

  <!-- Share Map Modal -->
  <div class="nmm-modal-overlay" id="nmm-share-modal">
    <div class="nmm-modal" style="width:480px;max-width:96vw">
      <h3>Share map</h3>
      <p class="nmm-share-map-title" id="nmm-share-title"></p>
      <div class="nmm-field">
        <label for="nmm-share-email">Edit together with another member</label>
        <div class="nmm-share-row">
          <input type="email" id="nmm-share-email" class="nmm-input" placeholder="Their email address" autocomplete="off" />
          <button type="button" class="nmm-btn nmm-btn-primary" id="nmm-share-add">Share</button>
        </div>
        <p class="nmm-new-hint" id="nmm-share-msg" role="status"></p>
      </div>
      <div class="nmm-field" id="nmm-share-people-wrap" style="display:none">
        <label>Shared with</label>
        <ul class="nmm-share-people" id="nmm-share-people"></ul>
        <p class="nmm-new-hint">An address with no account stays on this list but gives no access.</p>
      </div>
      <div class="nmm-field">
        <label>View-only link</label>
        <p class="nmm-new-hint" style="margin:0 0 8px">Anyone with this link can view the map without signing in.</p>
        <button type="button" class="nmm-btn" id="nmm-share-copy">Copy view link</button>
      </div>
      <div class="nmm-modal-actions">
        <button type="button" class="nmm-btn" id="nmm-share-close">Done</button>
      </div>
    </div>
  </div>

  <!-- Edit Map Modal -->
  <div class="nmm-modal-overlay" id="nmm-edit-modal">
    <div class="nmm-modal">
      <h3>Edit map details</h3>
      <input type="hidden" id="nmm-edit-id" />
      <div class="nmm-field">
        <label>Title</label>
        <input type="text" id="nmm-edit-title" class="nmm-input" />
      </div>
      <div class="nmm-field">
        <label>Category</label>
        <select id="nmm-edit-category" class="nmm-input">
          <option value="">No category</option>
          <option value="personal">Personal</option>
          <option value="client">Client</option>
          <option value="project">Project</option>
          <option value="team">Team</option>
        </select>
      </div>
      <div class="nmm-field">
        <label>Motif (leave blank for random quotes)</label>
        <input type="text" id="nmm-edit-motif" class="nmm-input" placeholder="Leave blank to use random quote pool" />
      </div>
      <div class="nmm-modal-actions">
        <button class="nmm-btn" id="nmm-edit-cancel">Cancel</button>
        <button class="nmm-btn nmm-btn-primary" id="nmm-edit-confirm">Save</button>
      </div>
    </div>
  </div>

</div>
