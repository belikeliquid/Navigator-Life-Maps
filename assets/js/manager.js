/* Navigator Mind Maps — maps list (/maps/ when logged in). Markup: templates/manager.php */
(function() {
if (!document.getElementById('nmm-manager')) return;

var api       = NMMCore.api, esc = NMMCore.esc;
var editorUrl = NMM.editorUrl;
var allMaps   = [];
var selectedColor = '#0BBFBF';

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
    loading.innerHTML = '<p style="color:#cc3333;font-family:inherit">Failed to load maps. Please refresh.</p>';
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
    card.style.cursor = 'pointer';
    card.addEventListener('click', function(e) {
      if (!e.target.closest('.nmm-card-actions')) {
        window.location.href = openUrl;
      }
    });
    card.innerHTML =
      '<div class="nmm-card-color-bar" style="background:' + (map.color||'#0BBFBF') + '"></div>' +
      '<div class="nmm-card-body">' +
        '<div class="nmm-card-header">' +
          '<h3 class="nmm-card-title">' + esc(map.title) + '</h3>' + cat +
        '</div>' +
        '<p class="nmm-card-meta">Last edited ' + modified + '</p>' +
      '</div>' +
      '<div class="nmm-card-actions">' +
        '<a href="' + openUrl + '" class="nmm-btn nmm-btn-primary nmm-btn-sm">Open</a>' +
        '<button class="nmm-btn nmm-btn-sm nmm-edit-btn" data-id="' + map.id + '" data-title="' + esc(map.title) + '" data-category="' + esc(map.category||'') + '">Edit</button>' +
        '<button class="nmm-btn nmm-btn-sm nmm-share-btn" data-url="' + NMM.shareUrl + '?share=' + map.share_key + '">Share</button>' +
        '<button class="nmm-btn nmm-btn-sm nmm-delete-btn" data-id="' + map.id + '">Delete</button>' +
      '</div>';

    // Edit button
    card.querySelector('.nmm-edit-btn').addEventListener('click', function(e) {
      e.stopPropagation();
      document.getElementById('nmm-edit-id').value       = this.dataset.id;
      document.getElementById('nmm-edit-title').value    = this.dataset.title;
      document.getElementById('nmm-edit-category').value = this.dataset.category;
      // The list has no settings, so load the motif before it can be edited (saving a blank one would wipe it)
      var motifEl = document.getElementById('nmm-edit-motif'), confirmBtn = document.getElementById('nmm-edit-confirm');
      motifEl.value = ''; motifEl.disabled = true; confirmBtn.disabled = true;
      api('GET', 'maps/' + this.dataset.id).then(function(full) {
        motifEl.value = (full.settings && full.settings.motif) || '';
        motifEl.disabled = false; confirmBtn.disabled = false;
      });
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

// Mode tabs
var currentMode = 'ai';
var aiQuestions = [];
var tolQuestions = [];

document.querySelectorAll('.nmm-mode-tab').forEach(function(tab) {
  tab.addEventListener('click', function() {
    currentMode = this.dataset.mode;
    document.querySelectorAll('.nmm-mode-tab').forEach(function(t){ t.classList.remove('active'); });
    this.classList.add('active');
    document.querySelectorAll('.nmm-mode-section').forEach(function(s){ s.style.display='none'; });
    document.getElementById('nmm-mode-' + currentMode).style.display = 'block';
  });
});

function buildQuestionsUI(questions, listId, wrapId) {
  var listEl = document.getElementById(listId);
  var wrapEl = document.getElementById(wrapId);
  listEl.innerHTML = '';
  questions.forEach(function(q, i) {
    var div = document.createElement('div');
    div.className = 'nmm-field';
    div.style.marginBottom = '8px';
    div.innerHTML = '<label style="font-size:11px;font-weight:600;color:#555;text-transform:none;letter-spacing:0;margin-bottom:4px;display:block">' + q + '</label>' +
      '<input type="text" class="nmm-input nmm-q-answer" data-index="' + i + '" placeholder="Your answer..." style="font-size:12px" />';
    listEl.appendChild(div);
  });
  wrapEl.style.display = 'block';
}

function collectAnswers(questions, listId) {
  var answers = [];
  var inputs = document.getElementById(listId).querySelectorAll('.nmm-q-answer');
  inputs.forEach(function(input, i) {
    if (questions[i]) answers.push({ question: questions[i], answer: input.value.trim() });
  });
  return answers;
}

function analyzeNotes(notesId, btnId, listId, wrapId, questionsVar) {
  var notes = document.getElementById(notesId).value.trim();
  var title = document.getElementById('nmm-new-title').value.trim();
  if (!notes) { alert('Please paste your notes first.'); return; }
  var btn = document.getElementById(btnId);
  btn.textContent = 'Analyzing...'; btn.disabled = true;
  api('POST', 'ai/questions', { title: title, notes: notes }).then(function(data) {
    btn.textContent = 'Analyze notes →'; btn.disabled = false;
    if (data.questions && data.questions.length) {
      if (questionsVar === 'ai') aiQuestions = data.questions;
      else tolQuestions = data.questions;
      buildQuestionsUI(data.questions, listId, wrapId);
    } else if (data.message) {
      alert('Could not analyze notes. ' + data.message);
    }
  }).catch(function() {
    btn.textContent = 'Analyze notes →'; btn.disabled = false;
    alert('Could not analyze notes. Please try again.');
  });
}

var aiAnalyzeBtn = document.getElementById('nmm-ai-analyze-btn');
if (aiAnalyzeBtn) aiAnalyzeBtn.addEventListener('click', function() {
  analyzeNotes('nmm-ai-notes', 'nmm-ai-analyze-btn', 'nmm-ai-questions-list', 'nmm-ai-questions-wrap', 'ai');
});

var tolAnalyzeBtn = document.getElementById('nmm-tol-analyze-btn');
if (tolAnalyzeBtn) tolAnalyzeBtn.addEventListener('click', function() {
  analyzeNotes('nmm-tol-notes', 'nmm-tol-analyze-btn', 'nmm-tol-questions-list', 'nmm-tol-questions-wrap', 'tol');
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
document.getElementById('nmm-new-confirm').addEventListener('click', async function() {
  var title    = document.getElementById('nmm-new-title').value.trim();
  var category = document.getElementById('nmm-new-category').value;
  if (!title) { alert('Please enter a map title.'); return; }
  var btn = this; btn.disabled = true;

  // AI mode or Tree of Life mode — call server to organize notes into nodes
  if (currentMode === 'ai' || currentMode === 'treeoflife') {
    var notesId  = currentMode === 'ai' ? 'nmm-ai-notes' : 'nmm-tol-notes';
    var statusId = currentMode === 'ai' ? 'nmm-ai-status' : 'nmm-tol-status';
    var qList    = currentMode === 'ai' ? 'nmm-ai-questions-list' : 'nmm-tol-questions-list';
    var qStore   = currentMode === 'ai' ? aiQuestions : tolQuestions;
    var notes    = document.getElementById(notesId).value.trim();
    if (!notes) { alert('Please paste your notes first.'); btn.disabled = false; return; }
    var answers  = collectAnswers(qStore, qList);
    btn.textContent = 'Organizing...';
    document.getElementById(statusId).style.display = 'block';
    try {
      var aiResp = await api('POST', 'ai/organize', { title: title, notes: notes, answers: answers, mode: currentMode });
      document.getElementById(statusId).style.display = 'none';
      if (!aiResp || !aiResp.nodes) { throw new Error((aiResp && aiResp.message) || 'No nodes returned'); }
      var map = await api('POST', 'maps', {
        title: title, category: category, color: selectedColor,
        nodes: aiResp.nodes, settings: { motif: '', template: 'treeoflife' }
      });
      document.getElementById('nmm-new-modal').classList.remove('open');
      btn.textContent = 'Create Map →'; btn.disabled = false;
      window.location.href = editorUrl + '?map_id=' + map.id + '&template=treeoflife';
    } catch(e) {
      document.getElementById(statusId).style.display = 'none';
      btn.textContent = 'Create Map →'; btn.disabled = false;
      alert('AI organize failed: ' + e.message + '\n\nPlease try again or use Outline mode.');
    }
    return;
  }

  // Outline, Tree of Life, Blank
  btn.textContent = 'Creating...';
  var outline = document.getElementById('nmm-new-outline') ? document.getElementById('nmm-new-outline').value.trim() : '';
  api('POST', 'maps', {
    title: title, category: category, color: selectedColor,
    nodes: [], settings: { motif: '', template: currentMode }
  }).then(function(map) {
    document.getElementById('nmm-new-modal').classList.remove('open');
    btn.textContent = 'Create Map →'; btn.disabled = false;
    var url = editorUrl + '?map_id=' + map.id;
    if (currentMode === 'outline' && outline) {
      url += '&outline=' + encodeURIComponent(outline) + '&template=outline';
    } else if (currentMode === 'treeoflife') {
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
      if (editBtn) { editBtn.dataset.title = title; editBtn.dataset.category = category; }
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
  window.location.href = NMM.logoutUrl;
});

loadMaps();
})();
