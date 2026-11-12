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
    allMaps = Array.isArray(maps) ? maps : [];
    filterMaps();
    loadNextUp();
    showMapCount();
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
    var cat      = map.category ? '<span class="nmm-card-cat">' + esc(map.category) + '</span>' : '';
    if (map.featured) cat = '<span class="nmm-card-cat nmm-card-star">★ Featured</span>' + cat;
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
    var owner    = map.can_manage !== false;
    var sharing  = map.shared_by ? '<p class="nmm-card-shared">Shared by ' + esc(map.shared_by) + '</p>'
                 : map.people ? '<p class="nmm-card-shared">Shared with ' + map.people + (map.people === 1 ? ' person' : ' people') + '</p>' : '';
    card.innerHTML =
      '<div class="nmm-card-color-bar" style="background:' + (map.color||'#0BBFBF') + '"></div>' +
      '<div class="nmm-card-body">' +
        '<div class="nmm-card-header">' +
          '<h3 class="nmm-card-title">' + esc(map.title) + '</h3>' + cat +
        '</div>' +
        '<p class="nmm-card-meta">Last edited ' + modified + '</p>' + sharing +
      '</div>' +
      '<div class="nmm-card-actions">' +
        '<a href="' + openUrl + '" class="nmm-btn nmm-btn-primary nmm-btn-sm">Open</a>' +
        '<button class="nmm-btn nmm-btn-sm nmm-edit-btn" data-id="' + map.id + '" data-title="' + esc(map.title) + '" data-category="' + esc(map.category||'') + '">Edit</button>' +
        (owner
          ? '<button class="nmm-btn nmm-btn-sm nmm-share-btn">Share</button>' +
            '<button class="nmm-btn nmm-btn-sm nmm-delete-btn" data-id="' + map.id + '">Delete</button>'
          : '<button class="nmm-btn nmm-btn-sm nmm-leave-btn">Leave</button>') +
      '</div>';

    // Edit button
    card.querySelector('.nmm-edit-btn').addEventListener('click', function(e) {
      e.stopPropagation();
      document.getElementById('nmm-edit-id').value       = this.dataset.id;
      document.getElementById('nmm-edit-title').value    = this.dataset.title;
      document.getElementById('nmm-edit-category').value = this.dataset.category;
      // The list has no settings, so load the motif before it can be edited (saving a blank one would wipe it)
      var motifEl = document.getElementById('nmm-edit-motif'), confirmBtn = document.getElementById('nmm-edit-confirm');
      var featuredEl = document.getElementById('nmm-edit-featured');
      motifEl.value = ''; motifEl.disabled = true; confirmBtn.disabled = true;
      if (featuredEl) { featuredEl.checked = false; featuredEl.disabled = true; }
      api('GET', 'maps/' + this.dataset.id).then(function(full) {
        motifEl.value = (full.settings && full.settings.motif) || '';
        motifEl.disabled = false; confirmBtn.disabled = false;
        if (featuredEl) { featuredEl.checked = !!full.featured; featuredEl.disabled = false; }
      });
      document.getElementById('nmm-edit-modal').classList.add('open');
    });

    // Leave a map someone shared with you
    if (!owner) card.querySelector('.nmm-leave-btn').addEventListener('click', function(e) {
      e.stopPropagation();
      if (!confirm('Leave "' + map.title + '"? It will no longer be in your maps. ' + map.shared_by + ' can share it with you again.')) return;
      api('POST', 'maps/' + map.id + '/leave').then(function(res) {
        if (!res || !res.left) { alert((res && res.message) || 'Could not leave this map.'); return; }
        allMaps = allMaps.filter(function(m) { return m.id !== map.id; });
        filterMaps();
      });
    });
    if (!owner) { grid.appendChild(card); return; }

    // Delete button
    card.querySelector('.nmm-delete-btn').addEventListener('click', function(e) {
      e.stopPropagation();
      var id    = parseInt(this.dataset.id);
      var title = card.querySelector('.nmm-card-title').textContent;
      if (!confirm('Delete "' + title + '"? This cannot be undone.')) return;
      api('DELETE', 'maps/' + id).then(function() {
        card.style.transition = 'opacity 0.2s'; card.style.opacity = '0';
        setTimeout(function(){ card.remove(); allMaps = allMaps.filter(function(m){ return m.id!==id; }); showMapCount(); if(!allMaps.length){ grid.style.display='none'; document.getElementById('nmm-empty').style.display='block'; } }, 200);
      });
    });

    // Share button
    card.querySelector('.nmm-share-btn').addEventListener('click', function(e) {
      e.stopPropagation();
      openShare(map);
    });

    grid.appendChild(card);
  });

  // New map card after the last map
  if (document.getElementById('nmm-new-map-btn')) {
    var addCard = document.createElement('button');
    addCard.type = 'button'; addCard.className = 'nmm-card-new'; addCard.innerHTML = '<span>+</span> New map';
    addCard.addEventListener('click', openNewModal);
    grid.appendChild(addCard);
    showMapCount();
  }
}

// Search + filter
var searchEl = document.getElementById('nmm-search');
var filterEl = document.getElementById('nmm-filter-category');
var sortEl   = document.getElementById('nmm-sort');
if (searchEl) searchEl.addEventListener('input', filterMaps);
if (filterEl) filterEl.addEventListener('change', filterMaps);
if (sortEl) {
  try { var saved = localStorage.getItem('nmm_maps_sort'); if (saved && sortEl.querySelector('option[value="' + saved + '"]')) sortEl.value = saved; } catch(e) {}
  sortEl.addEventListener('change', function() { try { localStorage.setItem('nmm_maps_sort', sortEl.value); } catch(e) {} filterMaps(); });
}
var SORTS = {
  edited:   function(a, b) { return String(b.modified).localeCompare(String(a.modified)); },
  created:  function(a, b) { return String(b.created).localeCompare(String(a.created)); },
  oldest:   function(a, b) { return String(a.created).localeCompare(String(b.created)); },
  title:    function(a, b) { return a.title.localeCompare(b.title, undefined, { sensitivity: 'base', numeric: true }); },
  category: function(a, b) { return (a.category ? 0 : 1) - (b.category ? 0 : 1) || String(a.category).localeCompare(String(b.category)) || a.title.localeCompare(b.title, undefined, { sensitivity: 'base' }); } // no category last
};
function filterMaps() {
  var q   = searchEl ? searchEl.value.toLowerCase() : '';
  var cat = filterEl ? filterEl.value : '';
  var list = allMaps.filter(function(m){ return (!q||m.title.toLowerCase().includes(q))&&(!cat||m.category===cat); });
  renderMaps(list.slice().sort(SORTS[sortEl ? sortEl.value : 'edited'] || SORTS.edited));
}

// Featured maps: view them read only, or copy one to start your own
function loadFeatured() {
  var wrap = document.getElementById('nmm-featured'), grid = document.getElementById('nmm-featured-grid');
  if (!wrap) return;
  api('GET', 'featured').then(function(list) {
    list = Array.isArray(list) ? list : [];
    wrap.style.display = list.length ? '' : 'none';
    grid.innerHTML = '';
    list.forEach(function(map) {
      var card = document.createElement('div');
      card.className = 'nmm-card nmm-card-featured';
      card.innerHTML =
        '<div class="nmm-card-color-bar" style="background:' + esc(map.color || '#C9A45C') + '"></div>' +
        '<div class="nmm-card-body"><div class="nmm-card-header"><h3 class="nmm-card-title">' + esc(map.title) + '</h3>' +
          (map.category ? '<span class="nmm-card-cat">' + esc(map.category) + '</span>' : '') + '</div>' +
          '<p class="nmm-card-meta">' + esc(map.cards ? map.cards + ' cards' : 'Ready-made map') + '</p></div>' +
        '<div class="nmm-card-actions"><a class="nmm-btn nmm-btn-sm" href="' + NMM.shareUrl + '?map_id=' + map.id + '">View</a>' +
          '<button type="button" class="nmm-btn nmm-btn-primary nmm-btn-sm">Use this map</button></div>';
      card.querySelector('button').addEventListener('click', function() {
        if (mapLimit && ownCount() >= mapLimit) { alert('You have reached your limit of ' + mapLimit + ' maps. Delete a map to make room for a copy.'); return; }
        NMMCore.copyMap(map.id, this);
      });
      grid.appendChild(card);
    });
  }).catch(function() {});
}
loadFeatured();

// New map modal
// Map limit: 0 means none (admins). The server enforces it; this explains it up front.
var mapLimit = 0;
api('GET', 'auth/me').then(function(me) { mapLimit = (me && me.limit) || 0; showMapCount(); }).catch(function() {});
// Maps shared with you don't count toward your limit
function ownCount() { return allMaps.filter(function(m) { return !m.shared_by; }).length; }
function showMapCount() {
  var el = document.getElementById('nmm-map-count');
  var addCard = document.querySelector('.nmm-card-new');
  if (addCard) addCard.style.display = mapLimit && ownCount() >= mapLimit ? 'none' : '';
  if (!el || !mapLimit) return;
  el.textContent = ownCount() + ' of ' + mapLimit + ' maps';
  el.style.display = 'inline';
}
function openNewModal() {
  if (mapLimit && ownCount() >= mapLimit) {
    alert('You have reached your limit of ' + mapLimit + ' maps. Delete a map to make room for a new one.');
    return;
  }
  document.getElementById('nmm-new-modal').classList.add('open');
  document.getElementById('nmm-new-title').value = '';
  showNewError('');
  showStep('details');
  setTimeout(function(){ document.getElementById('nmm-new-title').focus(); }, 50);
}
var newBtn      = document.getElementById('nmm-new-map-btn');
var emptyNewBtn = document.getElementById('nmm-empty-new-btn');
if (newBtn)      newBtn.addEventListener('click', openNewModal);
if (emptyNewBtn) emptyNewBtn.addEventListener('click', openNewModal);

// ---- New map steps: details, then AI questions one at a time, then the map ----
var currentMode = 'ai', currentLayout = 'flow', newStep = 'details';
var questions = [], questionIdx = 0, answers = [], questionsFor = '';
var LAYOUT_HINTS = {
  flow: 'Top to bottom, like an org chart.',
  mind: 'Your main idea in the centre, with branches out to the left and right.',
  tol: 'Topics placed on the ten positions of the Tree of Life.'
};
function $new(id) { return document.getElementById(id); }

function showNewError(msg) { var e = $new('nmm-new-error'); e.textContent = msg; e.style.display = msg ? 'block' : 'none'; }

function showStep(step) {
  newStep = step;
  $new('nmm-new-step-details').style.display  = step === 'details'  ? 'block' : 'none';
  $new('nmm-new-step-question').style.display = step === 'question' ? 'block' : 'none';
  $new('nmm-new-step-working').style.display  = step === 'working'  ? 'block' : 'none';
  $new('nmm-new-actions').style.display       = step === 'working'  ? 'none'  : '';
  showWorkText(step === 'working');
  var confirm = $new('nmm-new-confirm');
  confirm.disabled = false;
  if (step === 'details') {
    $new('nmm-new-cancel').textContent = 'Cancel';
    confirm.textContent = currentMode === 'ai' ? 'Next →' : 'Create Map →';
  } else if (step === 'question') {
    $new('nmm-new-cancel').textContent = '← Back';
    confirm.textContent = questionIdx === questions.length - 1 ? 'Create Map →' : 'Next →';
    $new('nmm-q-progress').textContent = 'Question ' + (questionIdx + 1) + ' of ' + questions.length;
    $new('nmm-q-bar-fill').style.width = ((questionIdx + 1) / questions.length * 100) + '%';
    $new('nmm-q-text').textContent = questions[questionIdx];
    var input = $new('nmm-q-answer');
    input.value = answers[questionIdx] || '';
    setTimeout(function() { input.focus(); }, 50);
  }
}

// Words for the wait. They set the mood; they don't track real progress, so the last one stays.
var WORK_TEXT = ['Reading your notes', 'Finding the main branches', 'Charting the connections', 'Placing your cards', 'Setting your course', 'Still charting, longer notes take a little more time'];
var workTimer = null;
function showWorkText(on) {
  clearInterval(workTimer);
  if (!on) return;
  var i = 0, el = $new('nmm-work-text');
  el.textContent = WORK_TEXT[0];
  workTimer = setInterval(function() {
    if (i < WORK_TEXT.length - 1) el.textContent = WORK_TEXT[++i];
  }, 9000);
}

function pickTab(selector, chosen) {
  document.querySelectorAll(selector).forEach(function(t) { t.classList.toggle('active', t === chosen); });
}
document.querySelectorAll('.nmm-layout-tab').forEach(function(tab) {
  tab.addEventListener('click', function() {
    currentLayout = this.dataset.layout;
    pickTab('.nmm-layout-tab', this);
    $new('nmm-layout-hint').textContent = LAYOUT_HINTS[currentLayout];
  });
});
$new('nmm-layout-hint').textContent = LAYOUT_HINTS[currentLayout];
document.querySelectorAll('.nmm-start-tab').forEach(function(tab) {
  tab.addEventListener('click', function() {
    currentMode = this.dataset.mode;
    pickTab('.nmm-start-tab', this);
    document.querySelectorAll('.nmm-mode-section').forEach(function(s){ s.style.display='none'; });
    $new('nmm-mode-' + currentMode).style.display = 'block';
    showStep('details');
  });
});

function keepAnswer() { if (newStep === 'question') answers[questionIdx] = $new('nmm-q-answer').value.trim(); }

// Cancel on the details step, Back on a question
$new('nmm-new-cancel').addEventListener('click', function() {
  showNewError('');
  if (newStep === 'question') {
    keepAnswer();
    if (questionIdx > 0) { questionIdx--; showStep('question'); } else { showStep('details'); }
    return;
  }
  $new('nmm-new-modal').classList.remove('open');
});

// Enter moves to the next question; Shift+Enter starts a new line
$new('nmm-q-answer').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $new('nmm-new-confirm').click(); }
});

// Next / Create Map
$new('nmm-new-confirm').addEventListener('click', function() {
  showNewError('');
  if (newStep === 'question') {
    keepAnswer();
    if (questionIdx < questions.length - 1) { questionIdx++; showStep('question'); return; }
    organizeWithAI();
    return;
  }
  if (!$new('nmm-new-title').value.trim()) { showNewError('Please enter a map title.'); return; }
  if (currentMode !== 'ai') { createPlainMap(); return; }
  var notes = $new('nmm-ai-notes').value.trim();
  if (!notes) { showNewError('Please paste your notes first.'); return; }
  // Going back and forward with the same notes doesn't ask (or pay) for new questions
  if (questionsFor === notes && questions.length) { questionIdx = 0; showStep('question'); return; }
  var btn = this; btn.disabled = true; btn.textContent = 'Reading your notes...';
  api('POST', 'ai/questions', { title: $new('nmm-new-title').value.trim(), notes: notes }).then(function(data) {
    if (data && data.questions && data.questions.length) {
      questions = data.questions; answers = []; questionIdx = 0; questionsFor = notes;
      showStep('question');
    } else if (data && (data.code === 'nmm_map_limit' || data.code === 'nmm_ai_limit' || data.code === 'too_long')) {
      showNewError(data.message); showStep('details');
    } else {
      questions = []; answers = []; organizeWithAI(); // no questions came back: the notes alone are enough to go on
    }
  }).catch(function() { questions = []; answers = []; organizeWithAI(); });
});

function newMapFields(nodes) {
  return { title: $new('nmm-new-title').value.trim(), category: $new('nmm-new-category').value, color: selectedColor,
    nodes: nodes, settings: { motif: '', layout: currentLayout } };
}

async function organizeWithAI() {
  var back = questions.length ? 'question' : 'details';
  showStep('working');
  try {
    var qa = questions.map(function(q, i) { return { question: q, answer: answers[i] || '' }; });
    var aiResp = await api('POST', 'ai/organize', { title: $new('nmm-new-title').value.trim(), notes: $new('nmm-ai-notes').value.trim(), answers: qa, layout: currentLayout });
    if (!aiResp || !aiResp.nodes) throw new Error((aiResp && aiResp.message) || 'No cards came back.');
    var map = await api('POST', 'maps', newMapFields(aiResp.nodes));
    if (!map || !map.id) throw new Error((map && map.message) || 'The map could not be created.');
    window.location.href = editorUrl + '?map_id=' + map.id + '&arrange=1';
  } catch (e) {
    // A timeout from the server comes back as an HTML page, which shows up here as a JSON error
    var msg = e instanceof SyntaxError ? 'The AI took too long to answer.' : e.message;
    showNewError(msg + ' Your notes and answers are still here, so you can try again.');
    showStep(back);
  }
}

function createPlainMap() {
  var btn = $new('nmm-new-confirm'); btn.disabled = true; btn.textContent = 'Creating...';
  var outline = currentMode === 'outline' ? $new('nmm-new-outline').value.trim() : '';
  api('POST', 'maps', newMapFields([])).then(function(map) {
    if (!map || !map.id) { showNewError((map && map.message) || 'Failed to create map. Please try again.'); showStep('details'); return; }
    window.location.href = editorUrl + '?map_id=' + map.id + (outline ? '&arrange=1&outline=' + encodeURIComponent(outline) : '');
  }).catch(function() { showNewError('Failed to create map. Please try again.'); showStep('details'); });
}

// Color swatches
document.querySelectorAll('.nmm-color-swatch').forEach(function(swatch) {
  swatch.addEventListener('click', function() {
    document.querySelectorAll('.nmm-color-swatch').forEach(function(s){ s.classList.remove('active'); });
    this.classList.add('active'); selectedColor = this.dataset.color;
    document.getElementById('nmm-new-color').value = selectedColor;
  });
});

// Edit map save
document.getElementById('nmm-edit-confirm').addEventListener('click', function() {
  var id       = document.getElementById('nmm-edit-id').value;
  var title    = document.getElementById('nmm-edit-title').value.trim();
  var category = document.getElementById('nmm-edit-category').value;
  var motif    = document.getElementById('nmm-edit-motif').value.trim();
  var featuredEl = document.getElementById('nmm-edit-featured');
  var featured = featuredEl ? featuredEl.checked : undefined;
  if (!title) { alert('Please enter a title.'); return; }
  var btn = this; btn.textContent = 'Saving...'; btn.disabled = true;

  // Load map, update settings.motif, save back
  api('GET', 'maps/' + id).then(function(map) {
    var settings = map.settings || {};
    settings.motif = motif;
    var body = { title: title, category: category, nodes: map.nodes || [], settings: settings, islands: map.islands || [] };
    if (featured !== undefined) body.featured = featured;
    return api('PUT', 'maps/' + id, body);
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
    if (m) { m.title = title; m.category = category; if (featured !== undefined) m.featured = featured; }
    filterMaps(); loadFeatured();
  }).catch(function(){ btn.textContent = 'Save'; btn.disabled = false; alert('Save failed.'); });
});

document.getElementById('nmm-edit-cancel').addEventListener('click', function(){ document.getElementById('nmm-edit-modal').classList.remove('open'); });

// ============================================================
// SHARE — edit together with other members, or copy the view-only link
// ============================================================
var shareMap = null;
function $share(id) { return document.getElementById(id); }
function shareMsg(text, isError) {
  var el = $share('nmm-share-msg');
  el.textContent = text; el.classList.toggle('nmm-share-error', !!isError);
}
var SHARE_HINT = 'Members can open and edit it from their own maps list; anyone else gets an invitation to join. Only you can delete the map or choose who it is shared with.';
function openShare(map) {
  shareMap = map;
  $share('nmm-share-title').textContent = map.title;
  $share('nmm-share-email').value = '';
  shareMsg(SHARE_HINT);
  renderPeople(null);
  $share('nmm-share-modal').classList.add('open');
  api('GET', 'maps/' + map.id + '/people').then(function(res) {
    if (res && res.people) renderPeople(res.people);
    else shareMsg((res && res.message) || 'Could not load who this map is shared with.', true);
  });
}
function renderPeople(people) {
  var list = $share('nmm-share-people');
  $share('nmm-share-people-wrap').style.display = people && people.length ? '' : 'none';
  list.innerHTML = '';
  (people || []).forEach(function(email) {
    var li = document.createElement('li');
    li.innerHTML = '<span>' + esc(email) + '</span><button type="button" class="nmm-btn nmm-btn-sm">Remove</button>';
    li.querySelector('button').addEventListener('click', function() {
      this.disabled = true;
      api('POST', 'maps/' + shareMap.id + '/people/remove', { email: email }).then(function(res) {
        if (res && res.people) { renderPeople(res.people); updatePeopleCount(res.people.length); shareMsg(email + ' no longer has access.'); }
        else shareMsg((res && res.message) || 'Could not remove ' + email + '.', true);
      });
    });
    list.appendChild(li);
  });
}
function updatePeopleCount(n) {
  shareMap.people = n;
  filterMaps();
}
function addPerson() {
  var input = $share('nmm-share-email'), email = input.value.trim(), btn = $share('nmm-share-add');
  if (!email) { input.focus(); return; }
  btn.disabled = true; btn.textContent = 'Sharing...';
  api('POST', 'maps/' + shareMap.id + '/people', { email: email }).then(function(res) {
    if (res && res.people) { input.value = ''; renderPeople(res.people); updatePeopleCount(res.people.length); shareMsg(res.message); }
    else shareMsg((res && res.message) || 'Could not share the map. Please try again.', true);
  }).catch(function() { shareMsg('Could not share the map. Please try again.', true); })
    .then(function() { btn.disabled = false; btn.textContent = 'Share'; });
}
$share('nmm-share-add').addEventListener('click', addPerson);
$share('nmm-share-email').addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); addPerson(); } });
$share('nmm-share-copy').addEventListener('click', function() {
  var url = NMM.shareUrl + '?share=' + shareMap.share_key, btn = this;
  var done = function() { btn.textContent = 'Link copied ✓'; setTimeout(function() { btn.textContent = 'Copy view link'; }, 2000); };
  if (navigator.clipboard) navigator.clipboard.writeText(url).then(done, function() { prompt('Copy this link:', url); });
  else prompt('Copy this link:', url);
});
$share('nmm-share-close').addEventListener('click', function() { $share('nmm-share-modal').classList.remove('open'); });

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

// Next up across your maps: the dated card most in need of a look, bottom right (computer screens).
// Uses your own maps and the ones shared with you, not every map an admin can see.
var managerNotice = NMMCore.notice({
  root: document.getElementById('nmm-manager'), key: 'maps_list',
  label: function(it) { return it.days < 0 ? 'Needs attention' : 'Next up'; },
  detail: function(it) { return it.when + ' · ' + it.n._map_title; },
  onOpen: function(it) { location.href = editorUrl + '?map_id=' + it.n._map_id + '&card=' + encodeURIComponent(it.n.id); }
});
function loadNextUp() {
  if (!window.matchMedia('(min-width: 900px) and (hover: hover)').matches) return;
  var mine = allMaps.filter(function(m) { return m.mine || m.shared_by; }).slice(0, 12);
  Promise.all(mine.map(function(m) {
    return api('GET', 'maps/' + m.id).then(function(full) {
      return ((full && full.nodes) || []).map(function(n) { n._map_id = m.id; n._map_title = m.title; return n; });
    }).catch(function() { return []; });
  })).then(function(lists) {
    managerNotice.update(NMMCore.attention([].concat.apply([], lists)).slice(0, 20));
  });
}

// Opened from an invitation email while already signed in: accept it and open the map
(function() {
  var token = new URLSearchParams(location.search).get('invite');
  if (!token) return;
  history.replaceState(null, '', NMM.mapsUrl);
  api('POST', 'invite/accept', { token: token }).then(function(res) {
    if (res && res.map_id) location.href = editorUrl + '?map_id=' + res.map_id;
    else alert((res && res.message) || 'This invitation could not be used.');
  });
})();

loadMaps();
})();
