# Navigator Mind Maps — WordPress Plugin
Version 1.0.0 | For navigatorproject.org (Enfold theme + SiteGround)

## INSTALLATION

1. Upload the `navigator-mindmaps` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress Admin → Plugins
3. Create two pages in WordPress:

### Page 1: Maps Manager
- Title: Maps
- Slug: maps  (so URL is navigatorproject.org/maps)
- Content: [navigator_maps]
- Template: Blank Page (in Enfold page settings → Layout → Blank Page)
  This removes the Enfold header/footer so the app feels standalone.

### Page 2: Map Editor  
- Title: Map Editor
- Slug: maps/editor
- Content: [navigator_mindmap id="0"]
- Template: Blank Page
- Note: The actual map ID is passed via ?map_id=123 in the URL

### Page 3: Map View (for sharing)
- Title: View Map
- Slug: maps/share
- Content: [navigator_mindmap id="0" readonly="true"]
- Template: Blank Page
- Note: Share key is passed via ?share=abc123 in the URL

## USAGE

### Creating a new map
1. Go to navigatorproject.org/maps
2. Click + NEW MAP
3. Enter title, category, color
4. Click Create Map — opens directly in the editor

### Editing a map
- From the manager, click Open on any map card
- All changes autosave every 30 seconds
- Click Save manually at any time
- Position of nodes is saved automatically when you stop dragging

### Sharing a map
- From the manager, click Share on any map card
- A public view link is copied to your clipboard
- Anyone with the link can view the map (read-only, no login required)

### Cross-map islands (shared node clusters)
1. In the editor, hover any node and click Edit
2. Click ↗ New Island to detach it as a standalone island
3. This saves it as a reusable island in the database
4. In another map, click ⛓ Link Island in the toolbar
5. Select the island to embed it (read-only, with link back to source)

### Embedding a specific map on any page
Add this shortcode to any WordPress page:
[navigator_mindmap id="123"]

For a read-only embed (e.g. for clients):
[navigator_mindmap id="123" readonly="true"]

## CATEGORIES
Maps can be tagged as: personal, client, project, team
Filter by category on the manager dashboard.

## GCAL EXPORT
1. Add dates to nodes in the editor (Edit → Date field)
2. Click → GCal in the editor toolbar
3. Download the .ics file
4. Open it or import to Google Calendar

## TECHNICAL NOTES

### Enfold Compatibility
- Plugin scripts are excluded from Enfold's JS/CSS compression via avf_file_group_skip_file filter
- html2canvas is loaded separately to prevent compression mangling
- Uses Blank Page template to escape Enfold column width constraints

### SiteGround SG Optimizer
- Plugin scripts excluded from JS combination via sgo_js_combine_exclude filter
- Nonce auto-refreshes every 10 minutes to handle SG Optimizer page caching
- REST API calls always use fresh nonce

### Data Storage
- Each mind map is a WordPress custom post type (nmm_mindmap)
- Islands are stored as nmm_island post type
- Node data stored as post meta (nmm_nodes) as JSON
- Settings stored as post meta (nmm_settings) as JSON
- Share keys stored as post meta (nmm_share_key)
- All data lives in your WordPress database — standard WP backup covers it

### REST API Endpoints
GET    /wp-json/nmm/v1/maps           List all maps
POST   /wp-json/nmm/v1/maps           Create map
GET    /wp-json/nmm/v1/maps/{id}      Get map
PUT    /wp-json/nmm/v1/maps/{id}      Update map
DELETE /wp-json/nmm/v1/maps/{id}      Delete map
GET    /wp-json/nmm/v1/islands        List islands
POST   /wp-json/nmm/v1/islands        Create island
GET    /wp-json/nmm/v1/islands/{id}   Get island
PUT    /wp-json/nmm/v1/islands/{id}   Update island
DELETE /wp-json/nmm/v1/islands/{id}   Delete island
GET    /wp-json/nmm/v1/share/{key}    Public share (no auth)
GET    /wp-json/nmm/v1/nonce          Fresh nonce (for cache refresh)

## FUTURE ROADMAP
- User accounts (each user has their own maps)
- Real-time collaboration (multiple editors on one map)
- Version history / undo
- Map templates
- Mobile touch support
- Webhook integration (notify Slack when a map is updated)
