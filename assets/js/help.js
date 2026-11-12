/* Navigator Life Maps — Help: a side panel that answers key questions as a short conversation.
 * Opened by any .nmm-help-btn (or nmmOpenHelp()). Typed questions are matched to the answers below
 * by their keywords; no AI is involved, so it costs nothing and always says the same thing.
 * Copy rules: plain and direct, no dashes, brand words (map, navigator, journey) only where true. */
(function() {
const root = document.querySelector('.nmm-app');
if (!root || !document.querySelector('.nmm-help-btn')) return;
const esc = NMMCore.esc;

// ---------- the answers ----------
const TOPICS = [
  { name: 'Start here', items: [
    { id: 'how', q: 'How does Life Maps work?', k: 'how work works start overview basics what is life maps',
      a: '<p>You put what is on your mind into a <strong>map</strong>: one card in the centre, with branches for the parts of your life or project, and cards under each branch for the details.</p>' +
         '<p>The same cards then show up in four views. The <b>Mind Map</b> shows the whole picture, <b>Kanban</b> shows what is moving, the <b>Timeline</b> shows when, and the <b>Roadmap</b> shows how the phases unfold. Change a card in one view and it changes everywhere.</p>' +
         '<p>Give cards a date and a stage, and Life Maps starts pointing you to what needs attention next.</p>',
      rel: ['why', 'start', 'views'] },
    { id: 'why', q: 'Why did we create Life Maps?', k: 'why created purpose reason made built story',
      a: '<p>Most planning tools hold tasks. A life holds more than tasks: a healing journey, a career turning, a family season, a business finding its feet. Those live in your head as a tangle, and a to-do list flattens them.</p>' +
         '<p>We built Life Maps so you can see the whole of it first, then turn it into steps you actually take, with dates, stages and the people walking it with you.</p>' +
         '<p><em>In the ocean of life, you are the navigator.</em></p>',
      rel: ['how', 'what', 'start'] },
    { id: 'start', q: 'Where do I start?', k: 'start begin first new map create beginner where',
      a: '<p>Click <b>+ New Map</b> on your maps page.</p><ol><li>Give it a title and choose a layout: <b>Flow Chart</b> (top to bottom) is the easiest place to begin.</li>' +
         '<li>Choose how to start: <b>AI Organize</b> turns pasted notes into a map, <b>Outline</b> builds one from an indented list, <b>Blank</b> gives you one card to grow from.</li>' +
         '<li>In the map, use <b>+ Child</b> on a card to branch out, and <b>Edit</b> to add notes, a date and a stage.</li></ol>' +
         '<p>Not sure what to map? Pick one area that feels heavy right now. That is usually the right first map.</p>',
      rel: ['ai', 'what', 'dates'] }
  ]},
  { name: 'The four views', items: [
    { id: 'views', q: 'What is the difference between Mind Map, Kanban, Timeline and Roadmap?', k: 'difference views mind map kanban timeline roadmap compare which view tabs',
      a: '<p>They are four ways of looking at the same cards, in the same tabs at the top of every page.</p><ul>' +
         '<li><b>◈ Mind Map</b> answers <em>what is all of this?</em> The whole picture and how it connects.</li>' +
         '<li><b>⬡ Kanban</b> answers <em>what am I doing now?</em> Cards move through Start, Doing, Sprint, Review and Done.</li>' +
         '<li><b>◷ Timeline</b> answers <em>what is coming up?</em> Dated cards along a line, a month at a time.</li>' +
         '<li><b>▤ Roadmap</b> answers <em>how does it unfold?</em> Cards as bars from start to end date, in lanes by phase.</li></ul>' +
         '<p>Change a card in one view and every view follows.</p>',
      rel: ['mindmap', 'kanban', 'roadmap'] },
    { id: 'mindmap', q: 'When should I use the Mind Map?', k: 'mind map when use canvas harmony layout tree of life flow chart branches',
      a: '<p>Whenever you are thinking: gathering, sorting, seeing connections. It is the home of every map.</p><ul>' +
         '<li><b>Harmony</b> tidies the cards in your chosen layout: Flow Chart, Mind Map or Tree of Life.</li>' +
         '<li><b>Animate</b> (in More) reveals the map card by card, good for walking a client or yourself through it.</li>' +
         '<li>Rest the mouse on a card to read it in full. Zoom with Ctrl and the scroll wheel, and drag empty space to move around.</li></ul>',
      rel: ['views', 'tools', 'phone'] },
    { id: 'kanban', q: 'When should I use Kanban?', k: 'kanban when use board stages sprint doing start review done triage columns limits',
      a: '<p>When it is time to act. Every card marked as a Kanban task appears on the board, in the column for its stage. Drag it along as it moves.</p>' +
         '<p>Keep it light: no more than <b>3 in Sprint</b> and <b>5 each in Start, Doing and Review</b>. The board warns you when a column is over, because too much at once is how things stall. Everything else waits in <b>Triage</b> until there is room.</p>' +
         '<p>On a computer, the board also flags cards that need a decision: overdue, or due soon and not started.</p>',
      rel: ['views', 'dates', 'nextup'] },
    { id: 'timeline', q: 'When should I use the Timeline?', k: 'timeline when use dates calendar upcoming month schedule',
      a: '<p>When you want to see what is coming. Only cards with a date appear, spread along a line from today. Choose the date range at the top, or <b>Show all dates</b>.</p>' +
         '<p>Drag a card sideways to move its date, or click it to read and edit it.</p>',
      rel: ['dates', 'roadmap', 'views'] },
    { id: 'roadmap', q: 'When should I use the Roadmap?', k: 'roadmap when use phases lanes bars start end dates gantt reset',
      a: '<p>When something unfolds over weeks or months: a healing journey, a launch, a career path. Each dated card becomes a bar from its start to its end date.</p><ul>' +
         '<li><b>Lanes</b> are phases you name. Start from a preset like Healing, Launch or Career path, or use the map\'s own branches.</li>' +
         '<li>Drag bars to move them, drag the right edge to change the end, drag cards from <b>Not on the roadmap yet</b> to schedule them.</li>' +
         '<li>No end date? The bar runs three weeks and fades out. <b>Lanes… › Reset roadmap</b> starts the lanes over.</li></ul>',
      rel: ['dates', 'journeys', 'views'] }
  ]},
  { name: 'What you can do', items: [
    { id: 'what', q: 'What can I map?', k: 'what can map ideas examples goals healing journey journeys routines habits systems change business launch career life uses',
      a: '<p>Anything that has parts and a direction. Some of what people map here:</p><ul>' +
         '<li><b>Life goals</b>: the areas that matter and the next real step in each.</li>' +
         '<li><b>Healing journeys</b>: what you are releasing, what you are practising, how it integrates week by week.</li>' +
         '<li><b>Routines and systems</b>: daily and weekly rhythms, the habits that hold them, what to review.</li>' +
         '<li><b>A business, product or program launch</b>: research, plan, build, launch, grow.</li>' +
         '<li><b>A career path</b>: options, decisions, deadlines, the people helping.</li></ul>' +
         '<p>The map is where it becomes clear. Kanban and the roadmap are where it becomes real change.</p>',
      rel: ['journeys', 'featured', 'ai'] },
    { id: 'ai', q: 'How does AI Organize work?', k: 'ai organize notes paste questions claude automatic generate create',
      a: '<p>When you create a map, choose <b>AI Organize</b> and paste your notes as they are: lists, paragraphs, half thoughts.</p>' +
         '<p>It asks a few questions about what it needs to know, one at a time, then builds the map in the layout you chose, with stages and dates where your notes call for them. It keeps the Kanban small on purpose and leaves the rest in Triage.</p>' +
         '<p>Read it through and change anything. It is a first draft of your thinking, not the final word.</p>',
      rel: ['reorganize', 'start', 'limits'] },
    { id: 'reorganize', q: 'Can AI rewrite a map I already have?', k: 'reorganize rewrite ai redo rebuild change shorter personal language regroup undo restore',
      a: '<p>Yes. In the Mind Map, open the layout menu (the ▾ next to <b>Harmony</b>) and choose <b>Reorganize with AI</b>.</p>' +
         '<p>Say what you would like, for example <i>more personal language and less text</i>. It asks a couple of questions, then rewrites the whole map. Cards keep their dates and stages unless you ask otherwise.</p>' +
         '<p>Changed your mind? <b>Undo</b> works straight away, and the same dialog can bring back the map from before the last Reorganize.</p>',
      rel: ['ai', 'limits'] },
    { id: 'dates', q: 'How do I give a card a date or a stage?', k: 'date stage tag start end due deadline task kanban card edit how',
      a: '<p>Click <b>Edit</b> on a card. Set a <b>Start date</b> (and an <b>End date</b> if it runs over time), tick <b>Kanban task</b>, and choose its stage.</p>' +
         '<p>Dated cards appear on the Timeline and Roadmap. Tasks appear on the Kanban board. You can also change both straight from those views.</p>',
      rel: ['kanban', 'timeline', 'nextup'] },
    { id: 'nextup', q: 'What is the Next up notice?', k: 'next up notice attention overdue reminder bottom right priority dismiss stop remind mute',
      a: '<p>On a computer, the mind map and roadmap show one card at the bottom right: the dated card most in need of a look. Overdue cards come first, then the soonest. Cards in Review or Done are left out.</p>' +
         '<p><b>Open card</b> takes you to it. Change its date or stage and the notice moves on to the next one. Dismiss it with × for the rest of your visit, or choose <b>Don&rsquo;t remind me</b> to stop it pointing to that card on this device. If the card gets a new date, it can come back.</p>',
      rel: ['dates', 'kanban'] },
    { id: 'tools', q: 'What do Export Prompt, Export PNG and Google Calendar do?', k: 'export prompt png image print printing white google calendar gcal ics download tools more menu animate',
      a: '<p>They are in the <b>More</b> menu, in every view: Mind Map, Kanban, Timeline and Roadmap.</p><ul><li><b>Export Prompt</b> writes your whole map as a text outline, with instructions for Basecamp, a document or a synthesis. Copy it into Basecamp or any AI.</li>' +
         '<li><b>Export PNG</b> saves what you see as an image. <b>Export for print</b> does the same on a white background, easy to read on paper.</li>' +
         '<li><b>Google Calendar</b> makes a calendar file from the dated cards you pick.</li>' +
         '<li><b>Import an outline</b> adds cards from an indented list.</li></ul>' +
         '<p>Animate reveals the cards one at a time. Settings and Import an outline belong to one map, so in the other views they open it in the Mind Map.</p>',
      rel: ['basecamp', 'mindmap'] },
    { id: 'basecamp', q: 'How do I connect a map to Basecamp?', k: 'basecamp connect project to-do sync integration export',
      a: '<p>Open the map, choose <b>More › Export Prompt</b> and pick <b>Basecamp</b>. You get the map as a project outline: a name, a description, to-do lists from your branches and to-dos from the cards, with dates and stages.</p>' +
         '<p>Paste it into Claude or another AI to build the project, or use it as the plan when you set the project up by hand. A direct link that keeps both in step is on our list, but not built yet.</p>',
      rel: ['tools', 'journeys'] }
  ]},
  { name: 'Guides and clients', items: [
    { id: 'journeys', q: 'How do I start a journey with a client?', k: 'guide host client journey coaching start program session intake',
      a: '<p>A simple way that works well:</p><ol>' +
         '<li>Create a map from your intake notes with <b>AI Organize</b>, or start from a <b>featured map</b> with <b>Use this map</b>.</li>' +
         '<li>On the <b>Roadmap</b>, choose a preset like Healing and place the work in phases.</li>' +
         '<li><b>Share</b> the map with your client by email. If they have no account yet, they get an invitation to join.</li>' +
         '<li>Work in it together. Sessions become dated cards, practices become tasks, and the Next up notice keeps you both honest.</li></ol>' +
         '<p>Guides and Hosts can hold 5 maps of their own. Maps shared with them do not count.</p>',
      rel: ['share', 'featured', 'roadmap'] },
    { id: 'featured', q: 'What are featured maps?', k: 'featured template copy use this map ready made',
      a: '<p>Ready-made maps chosen by the Navigator team, shown at the top of your maps page. <b>View</b> reads one, <b>Use this map</b> makes your own copy to change however you like. The copy counts toward your map limit.</p>',
      rel: ['journeys', 'what'] }
  ]},
  { name: 'Sharing', items: [
    { id: 'share', q: 'How do I share a map?', k: 'share sharing invite collaborate together email someone friend partner client',
      a: '<p>On your maps page, click <b>Share</b> on the map and enter their email.</p><ul>' +
         '<li>Members can open and edit it from their own maps list.</li>' +
         '<li>Anyone else gets an <b>invitation</b> to join that takes them straight to the map. It works for 14 days.</li>' +
         '<li>Only you can delete the map or choose who it is shared with. Remove someone at any time.</li>' +
         '<li><b>Copy view link</b> gives a read-only link anyone can open without signing in.</li></ul>',
      rel: ['together', 'privacy', 'journeys'] },
    { id: 'together', q: 'What happens when two people edit at the same time?', k: 'same time together conflict collaborate merge changes overwrite',
      a: '<p>Your changes are merged card by card. If you both change different cards, both changes stay. If you both change the same card, the last save wins.</p>' +
         '<p>An open shared map also picks up the other person\'s saves every 15 seconds while you are not in the middle of an edit.</p>',
      rel: ['share'] }
  ]},
  { name: 'Your account', items: [
    { id: 'limits', q: 'How many maps can I have?', k: 'limit how many maps number account plan host guide ai actions allowance month week',
      a: '<p>Up to <b>3 maps</b> of your own, or <b>5</b> for Guides and Hosts. Maps others share with you do not count. Delete a map to make room for a new one.</p>' +
         '<p>AI uses a paid service, so each person has a few <b>AI actions</b>: building a map from notes, or a Reorganize, is one each. Members get <b>3 a month</b>, Guides and Hosts <b>3 a week</b>. The dialog shows how many you have left.</p>',
      rel: ['featured', 'share', 'reorganize'] },
    { id: 'privacy', q: 'Who can see my maps?', k: 'private privacy who see security safe visible public',
      a: '<p>Only you, the people you share a map with, and the site administrators. A <b>view link</b> lets anyone with that link read the map, so only send it where you mean to.</p>',
      rel: ['share', 'limits'] },
    { id: 'phone', q: 'Does it work on my phone?', k: 'phone mobile tablet ipad iphone android small screen list',
      a: '<p>Yes. On a phone a map opens as a <b>list</b>: tap a card to open the cards under it, and use Edit and + Card from there. Switch to <b>Map</b> at the top to see the canvas.</p>' +
         '<p>Dragging cards and bars works best on a computer. On a phone, tap a card and change it in its window.</p>',
      rel: ['mindmap', 'views'] }
  ]}
];
const ALL = [].concat.apply([], TOPICS.map(function(t) { return t.items; }));
const byId = {}; ALL.forEach(function(it) { byId[it.id] = it; });

// ---------- matching typed questions ----------
const STOP = 'a an the i my me do does is are to of in on for and or it can how what when why where with this that be use'.split(' ');
function words(s) { return String(s).toLowerCase().replace(/[^a-z0-9 ]+/g, ' ').split(/\s+/).filter(function(w) { return w && STOP.indexOf(w) < 0; }); }
function match(text) {
  const q = words(text);
  if (!q.length) return [];
  return ALL.map(function(it) {
    const hay = words(it.k + ' ' + it.q);
    let score = 0;
    q.forEach(function(w) { hay.forEach(function(h) { if (h === w) score += 3; else if (w.length > 3 && (h.indexOf(w) === 0 || w.indexOf(h) === 0) && h.length > 3) score += 1; }); });
    return { it: it, score: score };
  }).filter(function(r) { return r.score > 0; }).sort(function(a, b) { return b.score - a.score; });
}

// ---------- the panel ----------
// It lives in its own .nmm-app host on <body>, so the theme's sticky header can't cover it.
const host = document.createElement('div');
host.className = 'nmm-app nmm-help-host';
const backdrop = document.createElement('div');
backdrop.className = 'nmm-help-backdrop';
const panel = document.createElement('aside');
panel.className = 'nmm-help'; panel.setAttribute('aria-label', 'Life Maps help'); panel.setAttribute('aria-hidden', 'true');
panel.innerHTML =
  '<div class="nmm-help-head"><div><strong>Life Maps guide</strong><span>Ask a question, or pick one below</span></div>' +
    '<button type="button" class="nmm-help-close" aria-label="Close help" title="Close (Esc)">×</button></div>' +
  '<div class="nmm-help-chat" aria-live="polite"></div>' +
  '<form class="nmm-help-ask"><input type="text" placeholder="Ask about Life Maps…" aria-label="Your question" autocomplete="off" />' +
    '<button type="submit">Ask</button></form>';
host.appendChild(backdrop); host.appendChild(panel); document.body.appendChild(host);
const chat = panel.querySelector('.nmm-help-chat'), input = panel.querySelector('input');

function scrollDown() { chat.scrollTop = chat.scrollHeight; }
function chips(ids) {
  return '<div class="nmm-help-chips">' + ids.map(function(id) { return byId[id] ? '<button type="button" data-q="' + id + '">' + esc(byId[id].q) + '</button>' : ''; }).join('') + '</div>';
}
function say(cls, html) {
  const el = document.createElement('div');
  el.className = 'nmm-help-msg ' + cls; el.innerHTML = html;
  chat.appendChild(el); scrollDown();
  return el;
}
function answer(it, asked) {
  say('nmm-help-you', esc(asked || it.q));
  say('nmm-help-bot', it.a + (it.rel && it.rel.length ? '<div class="nmm-help-more">Related</div>' + chips(it.rel) : ''));
}
// The question that fits the page you are on comes first
const PAGE_Q = { 'nmm-editor': 'mindmap', 'nmm-view': 'mindmap', 'nmm-kanban': 'kanban', 'nmm-timeline': 'timeline', 'nmm-roadmap': 'roadmap', 'nmm-manager': 'start' };
function welcome() {
  chat.innerHTML = '';
  const first = PAGE_Q[root.id] || 'start';
  const picks = [first].concat(['how', 'views', 'share'].filter(function(id) { return id !== first; })).slice(0, 4);
  say('nmm-help-bot', '<p>Hi. Ask me anything about Life Maps, or start with one of these:</p>' + chips(picks) +
    '<p class="nmm-help-small" style="margin-top:12px"><button type="button" class="nmm-help-link" data-act="all">Browse all topics</button></p>');
  chat.scrollTop = 0;
}
// Every topic, folded, so the list stays short until you open one
function allTopics() {
  say('nmm-help-bot', '<p>All topics. Tap one to see its questions.</p>' +
    TOPICS.map(function(t) {
      return '<details class="nmm-help-topic"><summary>' + esc(t.name) + ' <span>' + t.items.length + '</span></summary>' + chips(t.items.map(function(i) { return i.id; })) + '</details>';
    }).join(''));
}
chat.addEventListener('click', function(e) {
  const b = e.target.closest('[data-q]');
  if (b) answer(byId[b.dataset.q]);
  if (e.target.closest('[data-act="topics"]') || e.target.closest('[data-act="all"]')) allTopics();
});
panel.querySelector('form').addEventListener('submit', function(e) {
  e.preventDefault();
  const text = input.value.trim();
  if (!text) return;
  input.value = '';
  const found = match(text);
  if (found.length && found[0].score >= 3) {
    answer(found[0].it, text);
    return;
  }
  say('nmm-help-you', esc(text));
  say('nmm-help-bot', '<p>I don\'t have an answer for that yet.' + (found.length ? ' These might be close:' : ' Here is where to look:') + '</p>' +
    chips((found.length ? found.slice(0, 3).map(function(r) { return r.it.id; }) : ['how', 'views', 'what'])) +
    '<p class="nmm-help-small">Still stuck? Ask your Guide, or reach us through navigatorproject.org. <button type="button" class="nmm-help-link" data-act="topics">See all topics</button></p>');
});
panel.querySelector('.nmm-help-close').addEventListener('click', close);
backdrop.addEventListener('click', close);
document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && panel.classList.contains('open')) close(); });

let opener = null;
function open() {
  opener = document.activeElement;
  if (!chat.childElementCount) welcome();
  panel.classList.add('open'); host.classList.add('open'); panel.setAttribute('aria-hidden', 'false');
  // On touch screens the keyboard would cover the questions, so only focus the box with a mouse
  if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) setTimeout(function() { input.focus(); }, 50);
  else panel.querySelector('.nmm-help-close').focus();
}
function close() {
  panel.classList.remove('open'); host.classList.remove('open'); panel.setAttribute('aria-hidden', 'true');
  if (opener && opener.focus) opener.focus();
}
window.nmmOpenHelp = open;
document.addEventListener('click', function(e) {
  if (e.target.closest('.nmm-help-btn')) { e.preventDefault(); panel.classList.contains('open') ? close() : open(); }
});
})();
