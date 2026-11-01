# Navigator Mind Maps

A visual mind mapping plugin for WordPress, built for Navigator Leadership Group.

**Version:** 1.5.8  
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

The updater (`includes/plugin-update-checker/update-checker.php`) reads the **latest** release, strips a leading `v` from its tag, and offers an update when that is higher than `NMM_VERSION`. It installs the release's `.zip` asset, or the tag's source zip if there is none. Results are cached for 6 hours.

To release an update:
1. Bump the version in **both** places in `navigator-mindmaps.php`: the `Version:` header and `define( 'NMM_VERSION', ... )`.
2. Commit and push to `main`. Main must always hold the code that is released.
3. Tag the commit `vX.Y.Z`, exactly. A tag like `v.1.5.5` is read as `.1.5.5` and breaks the version comparison.
   ```bash
   git tag -a v1.5.7 -m "v1.5.7" && git push origin v1.5.7
   ```
4. Build the zip from the tag, with a top-level `navigator-mindmaps/` folder:
   ```bash
   git archive --format=zip --prefix=navigator-mindmaps/ -o navigator-mindmaps.zip v1.5.7 -- . ':!.gitignore'
   ```
5. Publish the release with the zip attached:
   ```bash
   gh release create v1.5.7 navigator-mindmaps.zip --title v1.5.7 --latest --notes "..."
   ```
6. In WP Admin → Plugins, the site shows the update within 6 hours. Take a database export before you install it.

Never edit the plugin directly on the server. The next release would overwrite the change.

## Changelog

See Mind Maps → Settings → Changelog in WP Admin.
