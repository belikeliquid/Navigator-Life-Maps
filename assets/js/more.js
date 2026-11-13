/* Navigator Life Maps — the More menu, shared by the Mind Map, Kanban, Timeline and Roadmap.
 * Markup: templates/more.php (NMM_Shortcodes::more_menu). Each view calls NMMMore.init(cfg) once:
 *   view       'map' | 'kanban' | 'timeline' | 'roadmap'
 *   maps()     the maps on screen: [{ id, title, category, layout, nodes }]
 *   image(print)  optional: make the image itself (the mind map uses its canvas)
 *   imageEl()  otherwise: the element to capture
 *   cards()    for Animate when the view has no animation of its own: card elements in reveal order
 * Animate, Settings and Import an outline keep a view's own handler when it has one (the editor's);
 * elsewhere Settings and Import open the map in the editor with that dialog showing. */
(function() {
const $ = function(id) { return document.getElementById(id); };
const esc = NMMCore.esc;
let cfg = null;

function slug(s) { return String(s || 'map').replace(/[^\w\- ]+/g, '').trim().replace(/\s+/g, '-').toLowerCase() || 'map'; }
function baseName() {
  const maps = cfg.maps();
  return maps.length === 1 ? slug(maps[0].title) : 'life-maps';
}
function open(id) { $(id).classList.add('open'); }
function close(id) { const el = $(id); if (el) el.classList.remove('open'); }

// ---------- images ----------
function capture(el, filename, print) {
  const overlay = $('nmm-export-overlay'), app = el.closest('.nmm-app');
  overlay.style.display = 'flex';
  if (print) app.classList.add('nmm-print');
  return NMMCore.loadHtml2Canvas().then(function(h2c) {
    return h2c(el, { backgroundColor: print ? '#ffffff' : '#e8e4db', scale: 2, logging: false,
      width: el.scrollWidth, height: el.scrollHeight, windowWidth: Math.max(window.innerWidth, el.scrollWidth),
      scrollX: -window.scrollX, scrollY: -window.scrollY });
  }).then(function(c) {
    const a = document.createElement('a');
    a.download = filename; a.href = c.toDataURL('image/png'); a.click();
  }).catch(function(e) {
    alert('Image export failed: ' + e.message);
  }).then(function() {
    app.classList.remove('nmm-print');
    overlay.style.display = 'none';
  });
}
function exportImage(print) {
  const name = baseName() + (cfg.view === 'map' ? '' : '-' + cfg.view) + (print ? '-print' : '') + '.png';
  if (cfg.image) return cfg.image(print, name);
  const el = cfg.imageEl && cfg.imageEl();
  if (!el || !el.offsetWidth) { alert('There is nothing to export yet.'); return; }
  capture(el, name, print);
}

// ---------- Animate: reveal the cards one at a time ----------
let reveal = null;
function stopReveal() {
  if (!reveal) return;
  clearInterval(reveal.timer);
  reveal.els.forEach(function(el) { el.classList.remove('nmm-reveal-hide'); });
  document.removeEventListener('pointerdown', stopReveal, true);
  reveal = null;
}
function animateCards() {
  stopReveal();
  const els = (cfg.cards ? cfg.cards() : []).filter(function(el) { return el.offsetParent; });
  if (!els.length) { alert('There are no cards to show yet.'); return; }
  els.forEach(function(el) { el.classList.add('nmm-reveal', 'nmm-reveal-hide'); });
  let i = 0;
  reveal = { els: els, timer: setInterval(function() {
    if (i >= els.length) { stopReveal(); return; }
    const el = els[i++];
    el.classList.remove('nmm-reveal-hide');
    el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
  }, 260) };
  // A click anywhere shows everything at once
  setTimeout(function() { if (reveal) document.addEventListener('pointerdown', stopReveal, true); }, 0);
}

// ---------- Settings and Import: they belong to one map, in the editor ----------
function inEditor(what) {
  const maps = cfg.maps();
  if (maps.length !== 1) { alert('This works on one map at a time. Choose a single map in the map filter first.'); return; }
  if (NMMCore.isViewOnly(maps[0].id)) { NMMCore.viewOnlyNotice(maps[0].id); return; } // nothing to change on a view-only map
  location.href = NMM.editorUrl + '?map_id=' + maps[0].id + '&open=' + what;
}

// ---------- Google Calendar (.ics) ----------
let calCards = [];
function openCalendar() {
  const maps = cfg.maps(), many = maps.length > 1;
  calCards = [];
  maps.forEach(function(m) {
    (m.nodes || []).forEach(function(n) { if (n.date && !n._readonly) calCards.push({ n: n, map: m.title }); });
  });
  if (!calCards.length) { alert('No cards have dates yet. Give a card a date first.'); return; }
  calCards.sort(function(a, b) { return a.n.date.localeCompare(b.n.date); });
  $('nmm-gcal-list').innerHTML = calCards.map(function(c, i) {
    return '<div class="nmm-gcal-item"><input type="checkbox" id="gcal_' + i + '" data-i="' + i + '" checked />' +
      '<label for="gcal_' + i + '"><strong>' + esc(c.n.title) + '</strong><span class="nmm-gcal-date"> · 📅 ' + esc(c.n.date) +
      (many ? ' · ' + esc(c.map) : '') + '</span></label></div>';
  }).join('');
  open('nmm-gcal-modal');
}
function doCalendar() {
  const checked = document.querySelectorAll('#nmm-gcal-list input[type=checkbox]:checked');
  if (!checked.length) { alert('No cards selected.'); return; }
  const lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//NavigatorMindMaps//EN'];
  checked.forEach(function(cb) {
    const n = calCards[+cb.dataset.i].n, d = n.date.replace(/-/g, '');
    lines.push('BEGIN:VEVENT', 'DTSTART;VALUE=DATE:' + d, 'DTEND;VALUE=DATE:' + (n.end ? NMMCore.dayAfter(n.end) : d),
      'SUMMARY:' + n.title, 'DESCRIPTION:' + (n.subs || []).join('\\n'), 'END:VEVENT');
  });
  lines.push('END:VCALENDAR');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([lines.join('\r\n')], { type: 'text/calendar' }));
  a.download = baseName() + '-events.ics'; a.click();
  close('nmm-gcal-modal');
}

// ---------- Export Prompt: the map as a text outline plus a description of it ----------
const PURPOSES = {
  basecamp: 'Turn this outline into a Basecamp project. Suggest a project name and a one-paragraph project description. Use each main branch as a to-do list and the cards under it as to-dos (deeper cards can become sub-steps in the to-do notes). Keep each card\'s notes as the to-do description, use dates as due dates, and use the Kanban stage as the status (or as Card Table columns: Triage, Start, Doing, Sprint, Review, Done). Flag anything that looks like a milestone.',
  document: 'Write a clear, well-structured document from this outline. Use the main branches as sections and their cards as headings and paragraphs, turn the notes into flowing prose, and keep dates and status where they help the reader. Open with a short summary of the whole map.',
  synthesis: 'Read this outline as a whole and write a synthesis: the main themes and how the branches connect, what is in motion right now (dated cards and Kanban stages), what seems missing, stuck or in tension, and three to five clear next steps.',
  outline: 'Use this outline as context. Keep its structure and wording when you refer to it.'
};
function outlineOf(m) {
  const live = m.nodes || [], byId = {};
  live.forEach(function(n) { byId[n.id] = n; });
  function kids(id) {
    // Reading order follows the layout: top to bottom for Mind Map, left to right otherwise
    return live.filter(function(n) { return n.parent === id; })
      .sort(m.layout === 'mind' ? function(a, b) { return a.y - b.y || a.x - b.x; } : function(a, b) { return a.x - b.x || a.y - b.y; });
  }
  function tags(n) {
    const t = [];
    if (n.date) t.push(n.end ? '[dates: ' + n.date + ' to ' + n.end + ']' : '[date: ' + n.date + ']');
    if (NMMCore.isTask(n)) t.push('[task' + (n.tag && NMMCore.TAG_COLORS[n.tag] ? ', stage: ' + NMMCore.TAG_COLORS[n.tag].label : ', stage: Triage') + ']');
    if (n.sefirot && NMMCore.SEFIROT_LABELS[n.sefirot]) t.push('[Tree of Life: ' + NMMCore.SEFIROT_LABELS[n.sefirot] + ']');
    if (n._readonly) t.push('[linked from: ' + n._island_title + ']');
    return t.length ? '  ' + t.join(' ') : '';
  }
  const lines = [];
  function walk(n, depth) {
    const pad = '  '.repeat(depth);
    lines.push(pad + '- ' + (n.title || 'Untitled') + tags(n));
    (n.subs || []).forEach(function(s) { if (s.trim()) lines.push(pad + '  ' + s.trim()); });
    kids(n.id).forEach(function(c) { walk(c, depth + 1); });
  }
  const roots = live.filter(function(n) { return !n.parent || !byId[n.parent]; }).sort(function(a, b) { return a.x - b.x || a.y - b.y; });
  roots.forEach(function(r) { walk(r, 0); });
  return { text: lines.join('\n'), cards: live.length, roots: roots.length,
    dated: live.filter(function(n) { return n.date; }).length, tasks: live.filter(NMMCore.isTask).length };
}
function promptText(purpose) {
  const maps = cfg.maps().filter(function(m) { return (m.nodes || []).length; });
  const outs = maps.map(outlineOf), one = maps.length === 1;
  const sum = function(k) { return outs.reduce(function(t, o) { return t + o[k]; }, 0); };
  const cards = sum('cards'), roots = sum('roots');
  const today = new Date().toISOString().slice(0, 10);
  return [
    '# ' + (one ? maps[0].title : 'Life Maps: ' + maps.map(function(m) { return m.title; }).join(', ')),
    'Navigator Life Map' + (one ? (maps[0].category ? ' · Category: ' + maps[0].category : '') : 's · ' + maps.length + ' maps') + ' · Exported ' + today,
    '',
    '## About this outline',
    (one ? 'This is a mind map' : 'These are ' + maps.length + ' mind maps, one section each,') + ' from Navigator Life Maps written out as an indented outline. Each line starting with "-" is a card; the lines indented beneath it are the cards that grow from it, so indentation shows how ideas branch. Plain lines directly under a card are its notes. The top-level cards are the map\'s islands, its main areas.',
    'Labels in square brackets:',
    '- [date: YYYY-MM-DD] the day the card is due or scheduled',
    '- [task, stage: …] the card is a task on the Kanban board, in that stage (Triage, Start, Doing, Sprint, Review, Done)',
    '- [Tree of Life: …] the card\'s position on the Tree of Life',
    '- [linked from: …] the card comes from another map linked into this one',
    (one ? 'The map has ' : 'Together the maps have ') + cards + ' cards in ' + roots + ' island' + (roots === 1 ? '' : 's') + ', ' + sum('dated') + ' with dates and ' + sum('tasks') + ' marked as tasks.',
    '',
    '## What to do with it',
    PURPOSES[purpose] || PURPOSES.outline,
    '',
    '## Outline',
    one ? outs[0].text : maps.map(function(m, i) { return '### ' + m.title + '\n' + outs[i].text; }).join('\n\n')
  ].join('\n');
}

// ---------- wiring ----------
function setIfMissing(name, fn) { if (typeof window[name] !== 'function') window[name] = fn; }

window.NMMMore = { init: function(c) {
  cfg = c;
  const btn = $('nmm-more-btn'), menu = $('nmm-more-menu');
  if (btn && menu) NMMCore.dropdown(btn, menu);
  setIfMissing('nmmAnimate', animateCards);
  setIfMissing('nmmOpenSettings', function() { inEditor('settings'); });
  setIfMissing('nmmOpenOutlineImport', function() { inEditor('outline'); });
  window.nmmExportPNG   = function() { exportImage(false); };
  window.nmmExportPrint = function() { exportImage(true); };
  window.nmmOpenGCalModal  = openCalendar;
  window.nmmCloseGCalModal = function() { close('nmm-gcal-modal'); };
  window.nmmDoGCalExport   = doCalendar;
  window.nmmOpenPromptExport = function() {
    if (!cfg.maps().some(function(m) { return (m.nodes || []).length; })) { alert('There are no cards yet.'); return; }
    window.nmmUpdatePrompt();
    open('nmm-prompt-modal');
  };
  window.nmmUpdatePrompt = function() { $('nmm-prompt-text').value = promptText($('nmm-prompt-purpose').value); };
  // Sending straight to Basecamp is planned; until then the button says so and points to the prompt
  window.nmmSendToBasecamp = function() {
    const p = $('nmm-prompt-soon');
    p.innerHTML = '<strong>Coming soon.</strong> Check back shortly. For now, choose <em>Create a Basecamp project</em> above, then copy the prompt into Claude or Basecamp.';
    p.hidden = false;
  };
  window.nmmClosePromptExport = function() { close('nmm-prompt-modal'); };
  window.nmmCopyPrompt = function() {
    const ta = $('nmm-prompt-text'), b = $('nmm-prompt-copy');
    const done = function() { b.textContent = 'Copied ✓'; setTimeout(function() { b.textContent = 'Copy'; }, 1800); };
    if (navigator.clipboard) navigator.clipboard.writeText(ta.value).then(done, function() { ta.select(); document.execCommand('copy'); done(); });
    else { ta.select(); document.execCommand('copy'); done(); }
  };
  window.nmmDownloadPrompt = function() {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([$('nmm-prompt-text').value], { type: 'text/markdown' }));
    a.download = baseName() + '-prompt.md';
    a.click();
  };
  // Escape and a click outside close these dialogs (the editor also closes them itself)
  ['nmm-gcal-modal', 'nmm-prompt-modal'].forEach(function(id) {
    const o = $(id);
    if (o) o.addEventListener('click', function(e) { if (e.target === o) close(id); });
  });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { close('nmm-gcal-modal'); close('nmm-prompt-modal'); stopReveal(); } });
} };
})();
