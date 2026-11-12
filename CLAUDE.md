# Navigator Mind Maps — notes for Claude

WordPress plugin `navigator-mindmaps`, live on navigatorproject.org. Mind maps, a Kanban board and a timeline over the same data. Read this before exploring; it should save you most of the searching.

## Layout

| Path | What |
|---|---|
| `navigator-mindmaps.php` | Bootstrap: `NMM_VERSION`, the inline `NMM` JS config in `<head>`, script registration (`PAGE_SCRIPTS`) |
| `includes/class-rest-api.php` | REST API `nmm/v1`: maps, islands, share, AI (Anthropic), nonce |
| `includes/class-shortcodes.php` | Shortcodes → `render($name)`: includes `templates/<name>.php` and enqueues `assets/js/<name>.js` |
| `includes/class-auth.php` | Login / signup endpoints, Turnstile, `nmm_edit_maps` capability; both accept an invitation `invite` token |
| `includes/class-mail.php` | `NMM_Mail`: the branded HTML email layout (share and invitation), with plain-text version |
| `includes/class-settings.php` | WP Admin settings page, **changelog** (add an entry each release) |
| `includes/class-post-types.php` | `nmm_mindmap`, `nmm_island` post types |
| `templates/*.php` | Markup only. No inline scripts |
| `assets/js/core.js` | `NMMCore`: `api()`, `patchNode()`, nonce refresh, map-filter dropdown, `esc()`, tag colors, lazy html2canvas |
| `assets/js/canvas.js` | `NMMCanvas.create()`: map rendering shared by the editor and the share view (nodes, lines, motif, PNG export) |
| `assets/js/editor.js` | Editor: save/draft/conflict logic, drag, modals, outline import, .ics export, Export Prompt (`promptText()`: map → described text outline) |
| `assets/js/outline.js` | `NMMOutline.create()`: the phone list view (below 700px, List / Map switch) for the editor and share view; redrawn from the editor's wrapped `C.render` |
| `assets/js/more.js` | The More menu (`templates/more.php` via `NMM_Shortcodes::more_menu($view)`), the same on Mind Map, Kanban, Timeline and Roadmap: Animate, Settings, Import, Google Calendar, Export PNG, Export for print (white, `.nmm-print` on the app while capturing), Export Prompt. Each view calls `NMMMore.init({view, maps(), image() or imageEl(), cards()})`; elsewhere Settings and Import open the editor with `&open=settings|outline` |
| `assets/js/help.js` | The ? Help panel (all signed-in pages): questions and answers in `TOPICS`, matched by keywords. Opens on a short welcome (the page's own question first, `PAGE_Q`), with all topics folded behind "Browse all topics". The panel sits in its own `.nmm-app.nmm-help-host` on `<body>` so the theme header can't cover it; backdrop, × and Esc close it. Keep the copy current when features change |
| `assets/js/{view,kanban,timeline,manager,landing}.js` | One script per page |
| `assets/mindmaps.css` | All styles. App UI uses `--nmm-font` (Fira Sans); map cards stay Barlow Condensed because their size drives saved layouts |

Pages (Enfold "Blank" template): `/maps` (manager or landing), `/map-editor/?map_id=N`, `/map-share/?share=KEY`, `/kanban/?map_id=N`, `/timeline/?map_id=N`, `/roadmap/?map_id=N` (one map; no id = most recently edited). Kanban and timeline take one or more ids (`?map_id=1,2`); no id means all maps.

## Data

A map is an `nmm_mindmap` post. Its whole content is JSON in post meta: `nmm_nodes` (array), `nmm_settings` (motif, quotes, template), `nmm_islands` (linked island ids), plus `nmm_category`, `nmm_color`, `nmm_share_key`, `nmm_rev`.

Node: `{id, x, y, title, subs[], parent, collapsed, date, end, task, tag, sefirot, lane}`. `date` is the start; `end` is optional. `parent: null` = island (root card). `x, y` = top-left on the canvas. `tag` ∈ start/doing/sprint/review/done or "" (Triage). Only Kanban tasks show on the board: `NMMCore.isTask(n)` (a node without `task` counts as one if it has a tag). `nmm_settings.layout` ∈ flow/mind/tol picks the Harmony style; `mind` also switches the canvas to sideways connectors.

**Zoom.** `canvas.js` scales `#nmm-canvas` with a CSS transform inside a JS-created `.nmm-canvas-sizer` (and moves `#nmm-motif` out of it, pinned to the top of the map area behind the cards). Dragging empty space and the arrow keys pan the map. Node x, y stay unzoomed: convert pointer positions with `C.toCanvas(clientX, clientY)`, never `clientX + scrollLeft`.

**Access.** Maps and islands are readable and writable only by their author, an admin, or (maps only) a member the map is shared with (`can_access()`, a 404 otherwise). Deleting and managing sharing need `can_manage()` (author or admin). the public route is `/share/<key>`, which doesn't include linked-island cards for logged-out viewers. Post types have `show_in_rest` off. Signup needs `nmm_invite_code` (option on the site only; the repo is public, so never commit the code). Limits live in `NMM_Auth`: standard 3, host 5 (role `nmm_host` or WP Author), admin/editor none; enforced in `create_map` and the AI routes. **AI allowance** (also `NMM_Auth`): an AI action is a map built from notes (`/ai/organize`) or a Reorganize; members 3 per rolling 30 days, hosts and editors 3 per rolling 7 days, admins none. Times in user meta `nmm_ai_log`; every call (including the question steps, capped at 15 a day) goes in option `nmm_ai_usage` (60 days), shown in Settings → AI use. `auth/me` returns `ai: {used, limit, period, next}`. Notes over 30,000 characters and maps over 40,000 characters of JSON are refused before anything is sent.

**Reorganize.** Layout menu → Reorganize with AI (editor.js, `#nmm-reorganize-modal`): wish → `/maps/<id>/reorganize/questions` (Haiku) → warning → `/maps/<id>/reorganize` with `base_rev` (the editor saves first). The server reads the saved cards, asks the organize model, cleans the answer (`clean_reorganized()`: text only, real dates, parents that exist, no loops), stores the old cards in meta `nmm_before_reorganize`, and logs the action. The editor merges by card id (keeping fields the AI does not send), lays the map out in its style and autosaves; Undo works, and `/reorganize/before` lets the dialog bring the old version back.

**Creating.** The new-map dialog (`manager.js`) saves `settings.layout` and sends `layout` to `/ai/organize`: `tol` gets the sefirot prompt, anything else a plain hierarchy with x, y of 0. It opens the editor with `&arrange=1`, which lays the map out once.

**Timeline.** `timeline.js` measures each card, then `fitDistance()` puts it in the nearest free spot above or below the line. Width follows the busiest stretch of dates (capped). Dates are whole-day numbers (`dayNum`). Cards outside the range are hidden and counted in the range note. The default range is today to 30 days out; if no selected card falls in it (an older map) `fitRange()` fits the cards' own dates, until the member picks dates (`rangeChosen`). "Show all dates" also fits to the cards.

**Sharing.** `nmm_editor` post meta, one row per user id, grants editing (and lets `get_maps` find shared maps). `nmm_people` (JSON `[{email, user}]`) keeps every address the owner typed, matched or not, so replies never reveal whether an account exists; only the owner sees it (`/maps/<id>/people`). An unmatched address gets an invitation instead: a 32-character token sent only in the email, stored as meta `nmm_inv_<sha256>` (`{email, by, expires}`, 14 days). `/invite/<token>` (public) feeds the signup banner; signup and login with `invite` redeem it (signup then needs no invitation code); `/invite/accept` covers someone already signed in. Access comes only from the token, because signup doesn't verify email. Shares are capped at 20 a day per member (`nmm_share_log` user meta), counted for every share so the limit reveals nothing. The share email goes out through WP cron (`nmm_share_email`). `format_map` adds `can_manage`, `people` (count, owner only), `shared_by` (for members) and `edited_by`; `share_key` is owner only.

**Featured.** `nmm_featured` meta (admins set it through `featured` on PUT). `can_read()` lets any signed-in member GET a featured map; writing still needs `can_access()`. `/featured` lists them, `/maps/<id>/copy` makes a copy for the current user (limit applies, islands not copied). `format_map` adds `created`, `featured`, `can_edit`; the editor sends `can_edit: false` maps to the read-only view, which shows "Use this map".

**Hover cards and notices.** `NMMCore.hoverCard()` (mouse only) and `NMMCore.notice()` (screens 900px+) are shared by the editor, view, Kanban and timeline. `NMMCore.attention()` sorts dated cards not in Review or Done by days from today; Kanban keeps only overdue ones and those due within 2 days still in Triage or Start. "Don't remind me" mutes a card in this browser (localStorage `nmm_notice_muted`, by card id and date, so a new date brings it back).

**Roadmap.** `roadmap.js`: bars from `date` to `end` (no end = 21 days, drawn fading). `settings.roadmap = {group: 'phase'|'branch', preset, lanes: [{id, label}]}`; in phase mode a card's `lane` is a lane id (unknown = Unsorted), in branch mode its lane is its top-level branch. Saves through `NMMCore.patchNode` / `patchMap`. Tray = undated tasks and leaf cards.

**Kanban limits.** Sprint 3; Start, Doing and Review 5 each. `limit_stages()` in `class-rest-api.php` enforces them on AI maps (overflow goes to Triage with `task: true`, soonest date stays). `LIMITS` in `kanban.js` only warns, counting the cards on the board across the selected maps. Keep the two in step.

**Saving.** Every write sends the whole node array. `nmm_rev` increments on each save. The editor sends `base_rev` and gets a 409 if the map changed since it loaded; it then merges card by card against `baseBody` (`mergeBodies()`) and saves again. On shared maps it polls every 15 s while idle and shows other people's saves; Kanban/timeline/maps list use `NMMCore.patchNode()` (fresh GET, then PUT, no `base_rev`). The editor autosaves 2 s after a change and keeps a localStorage draft (`nmm_draft_<id>`) until the save is confirmed.

**Toolbar.** Every app page uses the same order: ← Maps and the map picker, then `NMM_Shortcodes::view_tabs($current, $ids)` (Mind Map · Kanban · Timeline, kept current by `NMMCore.setViewTabs()`), then that view's tools, More and Save. Menus use `NMMCore.dropdown()` and sit outside the toolbar. Below 768px most `.nmm-btn` in a toolbar hide (the ☰ menu has them); add `.nmm-btn-keep` to keep one.

## Gotchas

- Enfold restyles `<button>`; app CSS targets `.nmm-app`.
- Speed Optimizer: bump `NMM_VERSION` on any CSS/JS change, then `wp sg purge`. Plugin scripts are excluded from combining.
- AI model ids are hard-coded in `class-rest-api.php` (`/ai/questions`, `/ai/organize`, Reorganize). Read replies with `reply_json()`, which takes the first text block (newer models can put a thinking block first).
- Titles are user text: always build HTML with `NMMCore.esc()`.
- REST nonces: a REST call without a nonce counts as logged out, so never fetch a nonce from a REST route. `NMMCore.refreshNonce()` uses `admin-ajax.php?action=rest-nonce`; `api()` retries once on `rest_cookie_invalid_nonce`. The landing page sends no nonce.
- Turnstile: the Simple Cloudflare Turnstile plugin (email popup) loads the script with `render=explicit` on the same pages, so `.cf-turnstile` never auto-renders. `landing.js` renders its own widget and reads `getResponse(widgetId)`.

## Testing

No staging site yet. There's no build step. Check JS with `node --check assets/js/*.js` and PHP with `php -l` on the server. To exercise pages without logging in, render a template on the server with `wp eval-file` and serve it locally against a mock of the REST API (see git history around v1.6.0 for the approach).

## Releasing

See README → Auto-Updates. In short: bump the version in both places in `navigator-mindmaps.php`, add a changelog entry in `class-settings.php`, commit, tag `vX.Y.Z`, publish a GitHub release with `navigator-mindmaps.zip`, then `wp plugin update navigator-mindmaps` on the server after a DB export.
