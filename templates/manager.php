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
    <span style="flex:1"></span>
    <?php if ( current_user_can('nmm_edit_maps') || current_user_can('edit_posts') || current_user_can('manage_options') ) : ?>
    <button class="nmm-btn nmm-btn-primary" id="nmm-new-map-btn">+ New Map</button>
    <?php endif; ?>
    <?php if ( is_user_logged_in() ) : ?>
    <button class="nmm-btn" id="nmm-logout-btn" style="font-size:11px;opacity:0.7">Log out</button>
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

  <!-- New Map Modal — includes outline import + template -->
  <div class="nmm-modal-overlay" id="nmm-new-modal">
    <div class="nmm-modal" style="width:520px;max-width:95vw">
      <h3>Create a new map</h3>
      <div class="nmm-field">
        <label>Map title</label>
        <input type="text" id="nmm-new-title" class="nmm-input" placeholder="e.g. April 2026 Planning" />
      </div>
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
        <div class="nmm-color-swatches">
          <div class="nmm-color-swatch active" data-color="#0BBFBF" style="background:#0BBFBF"></div>
          <div class="nmm-color-swatch" data-color="#534AB7" style="background:#534AB7"></div>
          <div class="nmm-color-swatch" data-color="#EF9F27" style="background:#EF9F27"></div>
          <div class="nmm-color-swatch" data-color="#5DCAA5" style="background:#5DCAA5"></div>
          <div class="nmm-color-swatch" data-color="#F4C0D1" style="background:#F4C0D1"></div>
          <div class="nmm-color-swatch" data-color="#1a2e4a" style="background:#1a2e4a"></div>
        </div>
        <input type="hidden" id="nmm-new-color" value="#0BBFBF" />
      </div>

      <!-- Outline import section -->
      <div class="nmm-field">
        <label>Start from</label>
        <div class="nmm-template-row">
          <label class="nmm-template-opt"><input type="radio" name="nmm-new-template" value="blank" checked /><span>Blank canvas</span></label>
          <label class="nmm-template-opt"><input type="radio" name="nmm-new-template" value="outline" /><span>Paste outline</span></label>
          <label class="nmm-template-opt"><input type="radio" name="nmm-new-template" value="treeoflife" /><span>Tree of Life</span></label>
        </div>
      </div>

      <div id="nmm-outline-section" style="display:none">
        <div class="nmm-field">
          <label>Paste your outline</label>
          <textarea id="nmm-new-outline" class="nmm-input" rows="6"
            placeholder="Vision&#10;  Goal one&#10;    Detail A&#10;    Detail B&#10;  Goal two&#10;Foundation"></textarea>
          <p style="font-size:10px;color:#aaa;margin-top:4px">Indent with spaces or tabs to create hierarchy. Supports numbered and bullet lists.</p>
        </div>
      </div>

      <div id="nmm-tol-section" style="display:none">
        <div class="nmm-tol-hint">
          Tree of Life places your content across 10 Sefirot: from Crown (vision) to Kingdom (reality). Harmony will snap nodes to sacred geometry positions. You can assign Sefirot to each node in the editor.
        </div>
      </div>

      <div class="nmm-modal-actions">
        <button class="nmm-btn" id="nmm-new-cancel">Cancel</button>
        <button class="nmm-btn nmm-btn-primary" id="nmm-new-confirm">Create Map →</button>
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

<script>
(function() {
document.addEventListener('DOMContentLoaded', function() {

var nonce    = (typeof NMM !== 'undefined') ? NMM.nonce    : '';
var restUrl  = (typeof NMM !== 'undefined') ? NMM.restUrl  : '';
var isAdmin  = (typeof NMM !== 'undefined') ? NMM.isAdmin  : false;
var editorUrl = (typeof NMM !== 'undefined') ? NMM.editorUrl : '/map-editor/';
var homeUrl   = (typeof NMM !== 'undefined') ? NMM.homeUrl   : '';
var allMaps   = [];
var selectedColor = '#0BBFBF';

// Nonce refresh every 10min
setInterval(function() {
  fetch(restUrl + 'nonce').then(function(r){ return r.json(); }).then(function(d){ if(d.nonce) nonce=d.nonce; });
}, 600000);

function api(method, endpoint, data) {
  return fetch(restUrl + endpoint, {
    method: method,
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
    body: data ? JSON.stringify(data) : undefined
  }).then(function(r) { return r.json(); });
}

// LOAD MAPS
function loadMaps() {
  var loading = document.getElementById('nmm-loading');
  var grid    = document.getElementById('nmm-maps-grid');
  var empty   = document.getElementById('nmm-empty');
  loading.style.display = 'flex'; grid.style.display = 'none'; empty.style.display = 'none';
  api('GET', 'maps').then(function(maps) {
    allMaps = maps || [];
    renderMaps(allMaps);
  }).catch(function() {
    loading.innerHTML = '<p style="color:#cc3333;font-family:Barlow Condensed,sans-serif">Failed to load maps. Please refresh.</p>';
  });
}

function renderMaps(maps) {
  var loading = document.getElementById('nmm-loading');
  var grid    = document.getElementById('nmm-maps-grid');
  var empty   = document.getElementById('nmm-empty');
  loading.style.display = 'none';
  if (!maps.length) { empty.style.display = 'block'; grid.style.display = 'none'; return; }
  empty.style.display = 'none'; grid.style.display = 'grid'; grid.innerHTML = '';

  maps.forEach(function(map) {
    var cat      = map.category ? '<span class="nmm-card-cat">' + map.category + '</span>' : '';
    var modified = new Date(map.modified).toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'});
    var openUrl  = editorUrl + '?map_id=' + map.id;

    var card = document.createElement('div');
    card.className = 'nmm-card'; card.dataset.id = map.id;
    card.innerHTML =
      '<div class="nmm-card-color-bar" style="background:' + (map.color||'#0BBFBF') + '"></div>' +
      '<div class="nmm-card-body">' +
        '<div class="nmm-card-header">' +
          '<h3 class="nmm-card-title">' + map.title + '</h3>' + cat +
        '</div>' +
        '<p class="nmm-card-meta">Last edited ' + modified + '</p>' +
      '</div>' +
      '<div class="nmm-card-actions">' +
        '<a href="' + openUrl + '" class="nmm-btn nmm-btn-primary nmm-btn-sm">Open</a>' +
        '<button class="nmm-btn nmm-btn-sm nmm-edit-btn" data-id="' + map.id + '" data-title="' + map.title + '" data-category="' + (map.category||'') + '" data-motif="' + (map.motif||'') + '">Edit</button>' +
        '<button class="nmm-btn nmm-btn-sm nmm-share-btn" data-url="' + homeUrl + '/map-share/?share=' + map.share_key + '">Share</button>' +
        '<button class="nmm-btn nmm-btn-sm nmm-delete-btn" data-id="' + map.id + '">Delete</button>' +
      '</div>';

    // Edit button
    card.querySelector('.nmm-edit-btn').addEventListener('click', function(e) {
      e.stopPropagation();
      document.getElementById('nmm-edit-id').value       = this.dataset.id;
      document.getElementById('nmm-edit-title').value    = this.dataset.title;
      document.getElementById('nmm-edit-category').value = this.dataset.category;
      document.getElementById('nmm-edit-motif').value    = this.dataset.motif || '';
      document.getElementById('nmm-edit-modal').classList.add('open');
    });

    // Delete button
    card.querySelector('.nmm-delete-btn').addEventListener('click', function(e) {
      e.stopPropagation();
      var id    = parseInt(this.dataset.id);
      var title = card.querySelector('.nmm-card-title').textContent;
      if (!confirm('Delete "' + title + '"? This cannot be undone.')) return;
      api('DELETE', 'maps/' + id).then(function() {
        card.style.transition = 'opacity 0.2s'; card.style.opacity = '0';
        setTimeout(function(){ card.remove(); allMaps = allMaps.filter(function(m){ return m.id!==id; }); if(!allMaps.length){ grid.style.display='none'; document.getElementById('nmm-empty').style.display='block'; } }, 200);
      });
    });

    // Share button
    card.querySelector('.nmm-share-btn').addEventListener('click', function(e) {
      e.stopPropagation();
      var url = this.dataset.url;
      if (navigator.clipboard) { navigator.clipboard.writeText(url).then(function(){ alert('Share link copied!'); }); }
      else { prompt('Copy this link:', url); }
    });

    grid.appendChild(card);
  });
}

// Search + filter
var searchEl = document.getElementById('nmm-search');
var filterEl = document.getElementById('nmm-filter-category');
if (searchEl) searchEl.addEventListener('input', filterMaps);
if (filterEl) filterEl.addEventListener('change', filterMaps);
function filterMaps() {
  var q   = searchEl ? searchEl.value.toLowerCase() : '';
  var cat = filterEl ? filterEl.value : '';
  renderMaps(allMaps.filter(function(m){ return (!q||m.title.toLowerCase().includes(q))&&(!cat||m.category===cat); }));
}

// New map modal
function openNewModal() {
  document.getElementById('nmm-new-modal').classList.add('open');
  document.getElementById('nmm-new-title').value = '';
  setTimeout(function(){ document.getElementById('nmm-new-title').focus(); }, 50);
}
var newBtn      = document.getElementById('nmm-new-map-btn');
var emptyNewBtn = document.getElementById('nmm-empty-new-btn');
if (newBtn)      newBtn.addEventListener('click', openNewModal);
if (emptyNewBtn) emptyNewBtn.addEventListener('click', openNewModal);

var cancelBtn = document.getElementById('nmm-new-cancel');
if (cancelBtn) cancelBtn.addEventListener('click', function(){ document.getElementById('nmm-new-modal').classList.remove('open'); });

// Template toggle — show/hide outline and ToL sections
document.querySelectorAll('input[name="nmm-new-template"]').forEach(function(r) {
  r.addEventListener('change', function() {
    document.getElementById('nmm-outline-section').style.display = this.value==='outline'     ? 'block' : 'none';
    document.getElementById('nmm-tol-section').style.display     = this.value==='treeoflife'  ? 'block' : 'none';
  });
});

// Color swatches
document.querySelectorAll('.nmm-color-swatch').forEach(function(swatch) {
  swatch.addEventListener('click', function() {
    document.querySelectorAll('.nmm-color-swatch').forEach(function(s){ s.classList.remove('active'); });
    this.classList.add('active'); selectedColor = this.dataset.color;
    document.getElementById('nmm-new-color').value = selectedColor;
  });
});

// Create map
document.getElementById('nmm-new-confirm').addEventListener('click', function() {
  var title    = document.getElementById('nmm-new-title').value.trim();
  var category = document.getElementById('nmm-new-category').value;
  var template = document.querySelector('input[name="nmm-new-template"]:checked').value;
  var outline  = document.getElementById('nmm-new-outline') ? document.getElementById('nmm-new-outline').value.trim() : '';
  if (!title) { alert('Please enter a map title.'); return; }
  var btn = this; btn.textContent = 'Creating...'; btn.disabled = true;

  api('POST', 'maps', {
    title: title, category: category, color: selectedColor,
    nodes: [], settings: { motif: '', template: template }
  }).then(function(map) {
    document.getElementById('nmm-new-modal').classList.remove('open');
    btn.textContent = 'Create Map →'; btn.disabled = false;
    // Redirect with outline + template params if outline mode
    var url = editorUrl + '?map_id=' + map.id;
    if (template === 'outline' && outline) {
      url += '&outline=' + encodeURIComponent(outline) + '&template=outline';
    } else if (template === 'treeoflife') {
      url += '&template=treeoflife';
    }
    window.location.href = url;
  }).catch(function() {
    btn.textContent = 'Create Map →'; btn.disabled = false;
    alert('Failed to create map. Please try again.');
  });
});

// Edit map save
document.getElementById('nmm-edit-confirm').addEventListener('click', function() {
  var id       = document.getElementById('nmm-edit-id').value;
  var title    = document.getElementById('nmm-edit-title').value.trim();
  var category = document.getElementById('nmm-edit-category').value;
  var motif    = document.getElementById('nmm-edit-motif').value.trim();
  if (!title) { alert('Please enter a title.'); return; }
  var btn = this; btn.textContent = 'Saving...'; btn.disabled = true;

  // Load map, update settings.motif, save back
  api('GET', 'maps/' + id).then(function(map) {
    var settings = map.settings || {};
    settings.motif = motif;
    return api('PUT', 'maps/' + id, { title: title, category: category, nodes: map.nodes || [], settings: settings, islands: map.islands || [] });
  }).then(function() {
    btn.textContent = 'Save'; btn.disabled = false;
    document.getElementById('nmm-edit-modal').classList.remove('open');
    // Update card in place
    var card = document.querySelector('.nmm-card[data-id="' + id + '"]');
    if (card) {
      card.querySelector('.nmm-card-title').textContent = title;
      var catEl = card.querySelector('.nmm-card-cat');
      if (catEl) catEl.textContent = category;
      // Update edit button data
      var editBtn = card.querySelector('.nmm-edit-btn');
      if (editBtn) { editBtn.dataset.title = title; editBtn.dataset.category = category; editBtn.dataset.motif = motif; }
    }
    // Update allMaps
    var m = allMaps.find(function(x){ return String(x.id)===String(id); });
    if (m) { m.title = title; m.category = category; }
  }).catch(function(){ btn.textContent = 'Save'; btn.disabled = false; alert('Save failed.'); });
});

document.getElementById('nmm-edit-cancel').addEventListener('click', function(){ document.getElementById('nmm-edit-modal').classList.remove('open'); });

// Close modals on overlay click
document.querySelectorAll('.nmm-modal-overlay').forEach(function(o){
  o.addEventListener('click', function(e){ if(e.target===o) o.classList.remove('open'); });
});
document.addEventListener('keydown', function(e){ if(e.key==='Escape') document.querySelectorAll('.nmm-modal-overlay').forEach(function(o){ o.classList.remove('open'); }); });

// Logout
var logoutBtn = document.getElementById('nmm-logout-btn');
if (logoutBtn) logoutBtn.addEventListener('click', function() {
  window.location.href = '<?php echo wp_logout_url( home_url("/maps/") ); ?>';
});

loadMaps();
});
})();
</script>
