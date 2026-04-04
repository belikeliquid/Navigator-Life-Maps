# Navigator Mind Maps

A visual mind mapping plugin for WordPress, built for Navigator Leadership Group.

**Version:** 1.5.3  
**Requires:** WordPress 5.8+, PHP 7.4+  
**Author:** Liquid Media / Navigator Leadership Group

## Features

- Multi-map manager dashboard
- Full canvas mind map editor with drag, drop, collapse, harmony
- Kanban board view with cross-map node tracking
- Tree of Life (Sefirot) template and node positioning
- Outline import from AI or text
- Node tags: Start, Doing, Sprint, Review, Done
- Google Calendar export
- PNG export
- WordPress user auth with custom capability
- Cloudflare Turnstile signup protection
- Landing page with login/signup

## Installation

1. Download the latest release zip
2. WP Admin → Plugins → Add New → Upload Plugin
3. Activate
4. Go to Settings → Permalinks → Save (flush rewrite rules)
5. Pages are created automatically on activation

## Pages Required

| Page | Shortcode |
|------|-----------|
| `/maps` | `[navigator_maps]` |
| `/map-editor` | `[navigator_mindmap id="0"]` |
| `/map-share` | `[navigator_mindmap id="0" readonly="true"]` |
| `/kanban` | `[navigator_kanban]` |

All pages should use the **Blank - No Header, No Footer** template in Enfold.

## Auto-Updates

This plugin checks this GitHub repo for new releases. When a new version is tagged and released, WordPress will show an update notification in WP Admin → Plugins.

To release an update:
1. Upload the new plugin zip as a GitHub Release asset
2. Tag it as `v1.5.4` (matching the plugin version number)
3. WordPress sites with this plugin installed will see the update notification

## Changelog

See Mind Maps → Settings → Changelog in WP Admin.
