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
    <a href="<?php echo home_url('/kanban/'); ?>" class="nmm-btn nmm-btn-kanban">⬡ Kanban</a>
    <a href="<?php echo home_url('/timeline/'); ?>" class="nmm-btn nmm-btn-kanban">◷ Timeline</a>
    <span style="flex:1"></span>
    <?php if ( current_user_can('nmm_edit_maps') || current_user_can('edit_posts') || current_user_can('manage_options') ) : ?>
    <button class="nmm-btn nmm-btn-primary" id="nmm-new-map-btn">+ New Map</button>
    <?php endif; ?>
    <?php if ( is_user_logged_in() ) : ?>
    <button class="nmm-btn" id="nmm-logout-btn" style="opacity:0.8">Log out</button>
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

  <!-- New Map Modal — four modes: AI Organize, Outline, Tree of Life, Blank -->
  <div class="nmm-modal-overlay" id="nmm-new-modal">
    <div class="nmm-modal" style="width:560px;max-width:96vw">
      <h3>Create a new map</h3>

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
        <label>Start from</label>
        <div class="nmm-mode-tabs">
          <button class="nmm-mode-tab active" data-mode="ai">&#10022; AI Organize</button>
          <button class="nmm-mode-tab" data-mode="outline">&#9776; Outline</button>
          <button class="nmm-mode-tab" data-mode="treeoflife">&#11041; Tree of Life</button>
          <button class="nmm-mode-tab" data-mode="blank">&#9633; Blank</button>
        </div>
      </div>

      <div id="nmm-mode-ai" class="nmm-mode-section">
        <div style="background:rgba(11,191,191,0.07);border-left:2px solid #0BBFBF;border-radius:0 6px 6px 0;padding:8px 12px;font-size:11px;color:#0a8a8a;line-height:1.6;margin-bottom:10px">
          Paste your notes. Navigator will ask a few questions to understand your priorities, then organize everything with tags and dates.
        </div>
        <div class="nmm-field">
          <label>Paste your notes</label>
          <textarea id="nmm-ai-notes" class="nmm-input" rows="5" placeholder="Paste anything. Bullet points, paragraphs, goals, tasks, ideas."></textarea>
        </div>
        <button type="button" class="nmm-btn nmm-btn-primary" id="nmm-ai-analyze-btn" style="width:100%;margin-bottom:10px">Analyze notes &#8594;</button>
        <div id="nmm-ai-questions-wrap" style="display:none">
          <div id="nmm-ai-questions-list"></div>
        </div>
        <div id="nmm-ai-status" style="display:none;font-size:11px;color:#0BBFBF;margin-bottom:8px;text-align:center">Organizing your content...</div>
      </div>

      <div id="nmm-mode-outline" class="nmm-mode-section" style="display:none">
        <div class="nmm-field">
          <label>Paste your outline</label>
          <textarea id="nmm-new-outline" class="nmm-input" rows="6" placeholder="Vision&#10;  Goal one&#10;    Detail A&#10;    Detail B&#10;  Goal two&#10;Foundation"></textarea>
          <p style="font-size:10px;color:#aaa;margin-top:4px">Indent with spaces or tabs to create hierarchy.</p>
        </div>
      </div>

      <div id="nmm-mode-treeoflife" class="nmm-mode-section" style="display:none">
        <div class="nmm-tol-hint">
          Tree of Life maps your content across 10 Sefirot positions. Paste your notes and Navigator will ask questions, then assign each topic to the right position with tags and dates.
        </div>
        <div class="nmm-field" style="margin-top:10px">
          <label>Paste your notes</label>
          <textarea id="nmm-tol-notes" class="nmm-input" rows="5" placeholder="Paste anything. Navigator will map it to the Tree of Life."></textarea>
        </div>
        <button type="button" class="nmm-btn nmm-btn-primary" id="nmm-tol-analyze-btn" style="width:100%;margin-bottom:10px">Analyze notes &#8594;</button>
        <div id="nmm-tol-questions-wrap" style="display:none">
          <div id="nmm-tol-questions-list"></div>
        </div>
        <div id="nmm-tol-status" style="display:none;font-size:11px;color:#0BBFBF;margin-bottom:8px;text-align:center">Organizing your content...</div>
      </div>

      <div id="nmm-mode-blank" class="nmm-mode-section" style="display:none">
        <div style="padding:16px 0;text-align:center;font-size:12px;color:#aaa">
          Start with an empty canvas. Add nodes freely in the editor.
        </div>
      </div>

      <div class="nmm-modal-actions">
        <button class="nmm-btn" id="nmm-new-cancel">Cancel</button>
        <button class="nmm-btn nmm-btn-primary" id="nmm-new-confirm">Create Map &#8594;</button>
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
