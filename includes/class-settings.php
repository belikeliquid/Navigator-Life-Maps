<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NMM_Settings {

    public static function register() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_post_nmm_save_settings', [ __CLASS__, 'save_settings' ] );
        add_action( 'admin_post_nmm_create_page', [ __CLASS__, 'create_page' ] );
    }

    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=nmm_mindmap',
            'Mind Maps Settings',
            'Settings',
            'manage_options',
            'nmm-settings',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function get( $key, $default = '' ) {
        return get_option( 'nmm_setting_' . $key, $default );
    }

    public static function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'nmm_settings' );
        $fields = [ 'default_bg_url', 'default_bg_mode', 'default_motif', 'default_quotes', 'editor_page_slug', 'share_page_slug', 'theme_css' ];
        // Cloudflare Turnstile keys
        if ( isset( $_POST['turnstile_site_key'] ) ) {
            update_option( 'nmm_turnstile_site_key', sanitize_text_field( wp_unslash( $_POST['turnstile_site_key'] ) ) );
        }
        if ( isset( $_POST['turnstile_secret_key'] ) ) {
            update_option( 'nmm_turnstile_secret_key', sanitize_text_field( wp_unslash( $_POST['turnstile_secret_key'] ) ) );
        }
        // Custom CSS saved separately
        if ( isset( $_POST['custom_css'] ) ) {
            update_option( 'nmm_custom_css', wp_strip_all_tags( wp_unslash( $_POST['custom_css'] ) ) );
        }
        // Anthropic API key
        if ( isset( $_POST['anthropic_key'] ) && ! empty( $_POST['anthropic_key'] ) ) {
            update_option( 'nmm_anthropic_key', sanitize_text_field( wp_unslash( $_POST['anthropic_key'] ) ) );
        }
        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_option( 'nmm_setting_' . $field, sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) );
            }
        }
        wp_redirect( admin_url( 'edit.php?post_type=nmm_mindmap&page=nmm-settings&saved=1' ) );
        exit;
    }

    public static function create_page() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'nmm_create_page' );
        $type = sanitize_text_field( $_POST['page_type'] ?? '' );
        $pages = [
            'manager' => [ 'title' => 'Maps',       'slug' => 'maps',       'content' => '[navigator_maps]' ],
            'editor'  => [ 'title' => 'Map Editor',  'slug' => 'map-editor', 'content' => '[navigator_mindmap id="0"]' ],
            'share'   => [ 'title' => 'Map View',    'slug' => 'map-share',  'content' => '[navigator_mindmap id="0" readonly="true"]' ],
            'kanban'   => [ 'title' => 'Kanban',     'slug' => 'kanban',     'content' => '[navigator_kanban]' ],
            'timeline' => [ 'title' => 'Timeline',  'slug' => 'timeline',   'content' => '[navigator_timeline]' ],
        ];
        if ( isset( $pages[ $type ] ) ) {
            $p = $pages[ $type ];
            $existing = get_page_by_path( $p['slug'] );
            if ( ! $existing ) {
                wp_insert_post([
                    'post_title'   => $p['title'],
                    'post_name'    => $p['slug'],
                    'post_content' => $p['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ]);
            }
        }
        wp_redirect( admin_url( 'edit.php?post_type=nmm_mindmap&page=nmm-settings&created=1' ) );
        exit;
    }

    public static function check_page( $slug ) {
        $page = get_page_by_path( $slug );
        return $page && $page->post_status === 'publish';
    }

    public static function check_rest( $endpoint ) {
        $url = rest_url( 'nmm/v1/' . $endpoint );
        $response = wp_remote_get( $url, [ 'timeout' => 5, 'sslverify' => false ] );
        if ( is_wp_error( $response ) ) return 'error';
        $code = wp_remote_retrieve_response_code( $response );
        return $code === 200 ? 'ok' : 'error';
    }

    public static function render_page() {
        $saved   = isset( $_GET['saved'] );
        $created = isset( $_GET['created'] );

        $default_bg     = self::get( 'default_bg_url', '' );
        $default_bg_mode = self::get( 'default_bg_mode', 'repeat' );
        $default_motif  = self::get( 'default_motif', 'In the ocean of life, you are the navigator' );
        $default_quotes = self::get( 'default_quotes', 'Navigate by stars not by fear,Roots run deep branches reach far,What you tend to grows' );
        $theme_css      = self::get( 'theme_css', self::default_theme_css() );
        $custom_css     = get_option( 'nmm_custom_css', self::default_enfold_css() );
        $anthropic_key  = get_option( 'nmm_anthropic_key', '' );

        // Page status checks
        $pages = [
            'manager' => [ 'label' => 'Maps manager',  'slug' => 'maps',       'shortcode' => '[navigator_maps]',                          'type' => 'manager' ],
            'editor'  => [ 'label' => 'Map editor',    'slug' => 'map-editor', 'shortcode' => '[navigator_mindmap id="0"]',                'type' => 'editor' ],
            'share'   => [ 'label' => 'Public share',  'slug' => 'map-share',  'shortcode' => '[navigator_mindmap id="0" readonly="true"]', 'type' => 'share' ],
            'kanban'   => [ 'label' => 'Kanban board',  'slug' => 'kanban',     'shortcode' => '[navigator_kanban]',       'type' => 'kanban' ],
            'timeline' => [ 'label' => 'Timeline',      'slug' => 'timeline',   'shortcode' => '[navigator_timeline]',     'type' => 'timeline' ],
        ];

        // REST checks
        $rest_base = self::check_rest( '' ) === 'ok' ? 'ok' : 'error';
        $rest_maps = self::check_rest( 'maps' );
        ?>
        <div class="wrap" style="font-family:-apple-system,sans-serif">
        <h1 style="display:flex;align-items:center;gap:10px">
            <span style="font-size:22px">⬡</span> Navigator Mind Maps — Settings
        </h1>

        <?php if ( $saved ) : ?><div class="notice notice-success"><p>Settings saved.</p></div><?php endif; ?>
        <?php if ( $created ) : ?><div class="notice notice-success"><p>Page created successfully.</p></div><?php endif; ?>

        <style>
        .nmm-settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-top:24px; }
        .nmm-settings-card { background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px 24px; }
        .nmm-settings-card h2 { font-size:14px; font-weight:700; margin:0 0 16px; padding-bottom:10px; border-bottom:1px solid #f0f0f0; color:#1a2e4a; }
        .nmm-field { margin-bottom:14px; }
        .nmm-field label { display:block; font-size:12px; font-weight:600; color:#666; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.05em; }
        .nmm-field input[type=text], .nmm-field textarea { width:100%; border:1px solid #ddd; border-radius:4px; padding:6px 8px; font-size:13px; }
        .nmm-field textarea { resize:vertical; font-family:monospace; font-size:12px; }
        .nmm-status { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; }
        .nmm-status.ok { color:#0f6e56; } .nmm-status.ok::before { content:"✓"; }
        .nmm-status.error { color:#cc3333; } .nmm-status.error::before { content:"✗"; }
        .nmm-status.missing { color:#cc3333; } .nmm-status.missing::before { content:"✗"; }
        .nmm-page-row { display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f5f5f5; }
        .nmm-page-row:last-child { border-bottom:none; }
        .nmm-page-label { font-size:13px; font-weight:600; color:#333; }
        .nmm-page-slug { font-size:11px; color:#888; font-family:monospace; }
        .nmm-shortcode { font-family:monospace; font-size:11px; background:#f5f5f5; padding:2px 6px; border-radius:3px; color:#333; }
        .nmm-btn-small { padding:4px 10px; font-size:11px; border-radius:4px; border:1px solid #0BBFBF; background:#0BBFBF; color:white; cursor:pointer; text-decoration:none; }
        .nmm-btn-small:hover { background:#09aaaa; }
        .nmm-accordion { border:1px solid #e0e0e0; border-radius:6px; overflow:hidden; margin-top:8px; }
        .nmm-accordion-item { border-bottom:1px solid #e0e0e0; }
        .nmm-accordion-item:last-child { border-bottom:none; }
        .nmm-accordion-header { padding:10px 14px; font-size:13px; font-weight:600; cursor:pointer; background:#fafafa; display:flex; justify-content:space-between; }
        .nmm-accordion-header:hover { background:#f0f0f0; }
        .nmm-accordion-body { padding:12px 14px; font-size:12px; color:#555; line-height:1.7; display:none; }
        .nmm-accordion-body.open { display:block; }
        table.nmm-ref { width:100%; border-collapse:collapse; font-size:12px; }
        table.nmm-ref th { text-align:left; padding:6px 8px; background:#f5f5f5; border:1px solid #e0e0e0; font-weight:700; }
        table.nmm-ref td { padding:6px 8px; border:1px solid #e0e0e0; vertical-align:top; }
        table.nmm-ref code { background:#f0f0f0; padding:1px 4px; border-radius:2px; font-size:11px; }
        </style>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('nmm_settings'); ?>
        <input type="hidden" name="action" value="nmm_save_settings" />

        <div class="nmm-settings-grid">

        <!-- LEFT COLUMN -->
        <div>

          <!-- Page Setup -->
          <div class="nmm-settings-card" style="margin-bottom:20px">
            <h2>📄 Page Setup</h2>
            <?php foreach ( $pages as $key => $page ) :
                $exists = self::check_page( $page['slug'] );
                $page_obj = get_page_by_path( $page['slug'] );
                $edit_url = $page_obj ? get_edit_post_link( $page_obj->ID ) : '';
            ?>
            <div class="nmm-page-row">
                <div>
                    <div class="nmm-page-label"><?php echo esc_html( $page['label'] ); ?></div>
                    <div class="nmm-page-slug">/<?php echo esc_html( $page['slug'] ); ?>/</div>
                    <div style="margin-top:3px"><span class="nmm-shortcode"><?php echo esc_html( $page['shortcode'] ); ?></span></div>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <?php if ( $exists ) : ?>
                        <span class="nmm-status ok">Active</span>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="nmm-btn-small" style="background:transparent;color:#0BBFBF">Edit</a>
                    <?php else : ?>
                        <span class="nmm-status missing">Missing</span>
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline">
                            <?php wp_nonce_field('nmm_create_page'); ?>
                            <input type="hidden" name="action" value="nmm_create_page" />
                            <input type="hidden" name="page_type" value="<?php echo esc_attr( $key ); ?>" />
                            <button type="submit" class="nmm-btn-small">Create</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- REST API Health -->
          <div class="nmm-settings-card" style="margin-bottom:20px">
            <h2>🔌 REST API Health</h2>
            <div class="nmm-page-row">
                <span style="font-size:13px">WordPress REST API</span>
                <span class="nmm-status <?php echo $rest_base; ?>"><?php echo $rest_base === 'ok' ? 'Responding' : 'Not responding'; ?></span>
            </div>
            <div class="nmm-page-row">
                <span style="font-size:13px">Mind Maps endpoint <code style="font-size:11px">/nmm/v1/maps</code></span>
                <span class="nmm-status <?php echo $rest_maps; ?>"><?php echo $rest_maps === 'ok' ? 'Public' : 'Blocked'; ?></span>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
                <a href="<?php echo esc_url( rest_url('nmm/v1/maps') ); ?>" target="_blank" class="nmm-btn-small">Test /maps</a>
                <a href="<?php echo esc_url( rest_url() ); ?>" target="_blank" class="nmm-btn-small">Test wp-json/</a>
                <a href="<?php echo esc_url( admin_url('options-permalink.php') ); ?>" class="nmm-btn-small" style="background:transparent;color:#0BBFBF;border-color:#0BBFBF">Flush Permalinks</a>
            </div>
          </div>

          <!-- Theme CSS Override -->
          <div class="nmm-settings-card">
            <h2>🎨 Theme CSS Override</h2>
            <p style="font-size:12px;color:#666;margin-bottom:12px">Paste this into your theme's Quick CSS to fix layout issues. For Enfold: Theme Options → General Styling → Quick CSS.</p>
            <div class="nmm-field">
                <label>CSS override</label>
                <textarea name="theme_css" rows="12"><?php echo esc_textarea( $theme_css ); ?></textarea>
            </div>
            <button type="button" onclick="navigator.clipboard.writeText(document.querySelector('[name=theme_css]').value).then(function(){alert('CSS copied to clipboard!')})" class="nmm-btn-small">Copy CSS</button>
          </div>

        </div>

        <!-- RIGHT COLUMN -->
        <div>

          <!-- Default Map Settings -->
          <div class="nmm-settings-card" style="margin-bottom:20px">
            <h2>🗺 Default Map Settings</h2>
            <p style="font-size:11px;color:#888;margin-bottom:8px">Background uses the built-in topographic SVG pattern. Individual maps can set a custom background image in their Settings panel inside the editor.</p>
            <div class="nmm-field">
                <label>Default motif (always shown)</label>
                <input type="text" name="default_motif" value="<?php echo esc_attr( $default_motif ); ?>" />
            </div>
            <div class="nmm-field">
                <label>Default quotes (comma separated, shown when no motif)</label>
                <textarea name="default_quotes" rows="3"><?php echo esc_textarea( $default_quotes ); ?></textarea>
            </div>
          </div>

          <!-- Shortcode Reference -->
          <div class="nmm-settings-card" style="margin-bottom:20px">
            <h2>📋 Shortcode Reference</h2>
            <table class="nmm-ref">
                <tr><th>Shortcode</th><th>Description</th></tr>
                <tr><td><code>[navigator_maps]</code></td><td>Full maps manager dashboard. Requires login to create/edit.</td></tr>
                <tr><td><code>[navigator_mindmap id="123"]</code></td><td>Embed a specific map (editable if logged in).</td></tr>
                <tr><td><code>[navigator_mindmap id="123" readonly="true"]</code></td><td>Read-only embed for clients or public pages.</td></tr>
            </table>
            <p style="font-size:11px;color:#888;margin-top:8px">The <code>id</code> is the WordPress post ID of the mind map. Find it in the URL when editing a map.</p>
          </div>

          <!-- Changelog -->
          <div class="nmm-settings-card">
            <h2>📝 Changelog</h2>
            <div class="nmm-accordion">
                <div class="nmm-accordion-item">
                    <div class="nmm-accordion-header" onclick="this.nextElementSibling.classList.toggle('open')">v1.6.0 — Cleanup, map ↔ Kanban switching <span>▾</span></div>
                    <div class="nmm-accordion-body">
                        Kanban and Timeline open on the map you came from, with a ◈ Mind Map button back to it. The URL keeps the selection (?map_id=…).<br>
                        Kanban cards show where they sit in the map (e.g. Work › Divination); click a card to edit it. Only the selected maps are loaded.<br>
                        Fixed: share links from the maps list showed "No map ID specified"; Outline mode ignored the outline; new Blank / Tree of Life maps opened empty; Edit on the maps list wiped the motif; tag badges disappeared after dragging; Undo wasn't saved; the Center button did nothing; the editor's category setting wasn't saved.<br>
                        Code: scripts moved out of the templates into assets/js (one per page plus shared core and canvas); PNG export library loads only when used.
                    </div>
                </div>
                <div class="nmm-accordion-item">
                    <div class="nmm-accordion-header" onclick="this.nextElementSibling.classList.toggle('open')">v1.5.8 — Editor saves reliably <span>▾</span></div>
                    <div class="nmm-accordion-body">
                        Autosave runs 2 seconds after a change (was 30), when you switch tabs, and when you leave the page.<br>
                        Unsaved work is kept in the browser until the server confirms it, and offered back if a save never arrived.<br>
                        The editor won't silently overwrite a map changed elsewhere (another tab, Kanban, the maps list); it asks first.
                    </div>
                </div>
                <div class="nmm-accordion-item">
                    <div class="nmm-accordion-header" onclick="this.nextElementSibling.classList.toggle('open')">v1.5.7 — Clear AI error messages <span>▾</span></div>
                    <div class="nmm-accordion-body">
                        AI organize and Analyze notes now show Anthropic's actual error (e.g. invalid API key, retired model) instead of a generic failure.<br>
                        Failed AI requests are written to the PHP error log.
                    </div>
                </div>
                <div class="nmm-accordion-item">
                    <div class="nmm-accordion-header" onclick="this.nextElementSibling.classList.toggle('open')">v1.5.3 — Auth, Kanban, landing page <span>▾</span></div>
                    <div class="nmm-accordion-body">
                        Two-column login/signup landing page with Cloudflare Turnstile support.<br>
                        nmm_edit_maps custom capability for subscriber users.<br>
                        Kanban multi-select map filter, right-click node edit, empty state.<br>
                        Edit map card (title, category, motif) on manager.<br>
                        Quote randomizer fixed — true random pool rotation.<br>
                        Recenter button in editor toolbar.<br>
                        Hide WP admin bar for non-admin users.<br>
                        Outline import moved to New Map modal on manager.
                    </div>
                </div>
                <div class="nmm-accordion-item">
                    <div class="nmm-accordion-header" onclick="this.nextElementSibling.classList.toggle('open')">v1.5.0 — Tags, Kanban, Tree of Life, Auth <span>▾</span></div>
                    <div class="nmm-accordion-body">
                        Node tags (Start/Doing/Sprint/Review/Done) with color badges.<br>
                        Sefirot (Tree of Life) positions on nodes.<br>
                        Kanban board view with drag-to-tag.<br>
                        Landing page with login/signup.<br>
                        WordPress user auth via REST API.<br>
                        Outline import with hierarchy detection.<br>
                        GCal export modal with multi-select.<br>
                        Custom CSS editor in Settings.<br>
                        Anthropic API key field in Settings.
                    </div>
                </div>
                <div class="nmm-accordion-item">
                    <div class="nmm-accordion-header" onclick="this.nextElementSibling.classList.toggle('open')">v1.3.0 — Settings page, UI fixes <span>▾</span></div>
                    <div class="nmm-accordion-body">
                        Settings page with page setup, REST health check, theme CSS helper, changelog, shortcode reference.<br>
                        Default background configurable globally and per-map.<br>
                        Canvas auto-centers on load.<br>
                        Motif fixed to top-center, white text.<br>
                        Editor toolbar admin bar offset fix.<br>
                        Enfold layout CSS baked into plugin.
                    </div>
                </div>
                <div class="nmm-accordion-item">
                    <div class="nmm-accordion-header" onclick="this.nextElementSibling.classList.toggle('open')">v1.2.0 — Public access, April map seed <span>▾</span></div>
                    <div class="nmm-accordion-body">
                        REST API GET endpoints made public (no login required to view maps).<br>
                        April 2026 map seeded automatically on activation.<br>
                        Topographic SVG background baked into CSS.<br>
                        Default motif: "In the ocean of life, you are the navigator".<br>
                        Nonce inline-injected via wp_head to fix Enfold/SG loading order.
                    </div>
                </div>
                <div class="nmm-accordion-item">
                    <div class="nmm-accordion-header" onclick="this.nextElementSibling.classList.toggle('open')">v1.1.0 — jQuery removed <span>▾</span></div>
                    <div class="nmm-accordion-body">
                        Rewrote manager script with vanilla JS fetch() — no jQuery dependency.<br>
                        Fixed "jQuery is not defined" error caused by Enfold loading jQuery in footer.<br>
                        Seed timing moved to wp_loaded for reliable post type registration.
                    </div>
                </div>
                <div class="nmm-accordion-item">
                    <div class="nmm-accordion-header" onclick="this.nextElementSibling.classList.toggle('open')">v1.0.0 — Initial release <span>▾</span></div>
                    <div class="nmm-accordion-body">
                        Mind Maps and Map Islands custom post types.<br>
                        REST API with full CRUD for maps and islands.<br>
                        Manager dashboard, editor, and public share view.<br>
                        Cross-map shared islands (read-only with source link).<br>
                        Google Calendar .ics export.<br>
                        PNG export via bundled html2canvas.<br>
                        Drag to move, drag to re-parent, collapse/expand nodes.<br>
                        Enfold theme + SG Optimizer compatibility.
                    </div>
                </div>
            </div>
          </div>

        </div>
        </div><!-- .nmm-settings-grid -->

        <!-- Custom CSS + API Key — full width below grid -->
        <div style="margin-top:20px">

          <div class="nmm-settings-card" style="margin-bottom:20px">
            <h2>✏️ Custom CSS Override</h2>
            <p style="font-size:12px;color:#666;margin-bottom:12px;">
              This CSS loads <strong>after</strong> the core plugin styles — use it for theme compatibility fixes and custom overrides.
              Pre-filled with the Enfold layout fix. Changes are saved to the database.
            </p>
            <div class="nmm-field">
              <label>mindmaps-custom.css</label>
              <textarea name="custom_css" rows="14" style="font-family:monospace;font-size:12px"><?php echo esc_textarea( $custom_css ); ?></textarea>
            </div>
          </div>

          <div class="nmm-settings-card" style="margin-bottom:20px">
            <h2>🤖 AI Integration (Anthropic)</h2>
            <p style="font-size:12px;color:#666;margin-bottom:12px;">
              Required for AI-powered features in v1.6.0 — auto Sefirot assignment, smart outline import.
              Your key is stored securely server-side and never exposed to the browser.
              Get your key at <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a>.
            </p>
            <div class="nmm-field">
              <label>Anthropic API Key</label>
              <input type="password" name="anthropic_key" value="" placeholder="<?php echo $anthropic_key ? 'Paste new key to replace' : 'sk-ant-...'; ?>" style="font-family:monospace" />
              <?php if ( $anthropic_key ) : ?>
                <p style="font-size:11px;color:#0f6e56;margin-top:4px">✓ API key is saved. Paste a new key above to replace it.</p>
              <?php else : ?>
                <p style="font-size:11px;color:#cc3333;margin-top:4px">No API key saved yet.</p>
              <?php endif; ?>
            </div>
            <div style="display:flex;align-items:center;gap:12px;margin-top:8px">
              <button type="button" id="nmm-test-api-btn" class="nmm-btn-small">Test API connection</button>
              <span id="nmm-test-api-result" style="font-size:12px"></span>
            </div>
          </div>

          <script>
          document.getElementById('nmm-test-api-btn').addEventListener('click', function() {
            var btn = this;
            var result = document.getElementById('nmm-test-api-result');
            btn.textContent = 'Testing...'; btn.disabled = true;
            result.textContent = ''; result.style.color = '#888';
            fetch('<?php echo esc_js( rest_url("nmm/v1/ai/test") ); ?>', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce("wp_rest") ); ?>' }
            }).then(function(r){ return r.json(); }).then(function(data) {
              btn.textContent = 'Test API connection'; btn.disabled = false;
              if (data.success) {
                result.textContent = '✓ Connected — ' + (data.model || 'API responding');
                result.style.color = '#0f6e56';
              } else {
                result.textContent = '✗ ' + (data.message || 'Connection failed');
                result.style.color = '#cc3333';
              }
            }).catch(function(e) {
              btn.textContent = 'Test API connection'; btn.disabled = false;
              result.textContent = '✗ Request error';
              result.style.color = '#cc3333';
            });
          });
          </script>

        </div>

        <!-- Turnstile + Registration -->
        <div style="margin-top:20px">
          <div class="nmm-settings-card" style="margin-bottom:20px">
            <h2>🔒 Security — Cloudflare Turnstile</h2>
            <p style="font-size:12px;color:#666;margin-bottom:12px">
              Protects the signup form from bots. You already have Turnstile set up on this site — reuse the same keys.
              Find them at <a href="https://dash.cloudflare.com/" target="_blank">dash.cloudflare.com</a> → Turnstile.
            </p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
              <div class="nmm-field">
                <label>Site Key (public)</label>
                <input type="text" name="turnstile_site_key" value="<?php echo esc_attr( get_option('nmm_turnstile_site_key','') ); ?>" placeholder="0x4AAAAAAA..." style="font-family:monospace;font-size:12px" />
              </div>
              <div class="nmm-field">
                <label>Secret Key (private)</label>
                <input type="password" name="turnstile_secret_key" value="<?php echo get_option('nmm_turnstile_secret_key') ? '••••••••••••' : ''; ?>" placeholder="0x4AAAAAAA..." style="font-family:monospace;font-size:12px" />
                <?php if ( get_option('nmm_turnstile_secret_key') ) : ?><p style="font-size:11px;color:#0f6e56;margin-top:4px">✓ Secret key is saved</p><?php endif; ?>
              </div>
            </div>
            <div style="background:#fff8e6;border:1px solid #fac775;border-radius:6px;padding:10px 14px;font-size:12px;color:#633806;margin-top:8px">
              <strong>WP Registration:</strong> Make sure "Anyone can register" is checked in 
              <a href="<?php echo admin_url('options-general.php'); ?>">Settings → General</a> 
              and New User Default Role is set to <strong>Subscriber</strong>. 
              The plugin automatically grants <code>nmm_edit_maps</code> capability to new users.
            </div>
          </div>
        </div>

        <div style="margin-top:20px">
            <button type="submit" class="button button-primary" style="background:#0BBFBF;border-color:#0BBFBF;font-size:13px;height:36px;padding:0 20px">Save Settings</button>
        </div>
        </form>
        </div>
        <?php
    }

    public static function default_enfold_css() {
        return '/* ============================================
   Navigator Mind Maps — Enfold Layout Fix
   Targets only pages containing .nmm-app
   so no other pages are affected.
   ============================================ */

/* Fix Enfold blank template display:table constraint */
body:has(.nmm-app) #top.avia-blank {
    display: block !important;
    table-layout: unset !important;
    width: 100% !important;
}

/* Remove padding/margin/max-width from Enfold wrappers */
body:has(.nmm-app) #wrap_all,
body:has(.nmm-app) #main,
body:has(.nmm-app) .container_wrap,
body:has(.nmm-app) .container_wrap.fullsize,
body:has(.nmm-app) .main_color,
body:has(.nmm-app) .container {
    max-width: 100% !important;
    width: 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

/* Remove entry-content padding on NMM pages */
body:has(.nmm-app) .entry-content {
    padding: 0 !important;
    margin: 0 !important;
}';
    }

    public static function default_theme_css() {
        return '/* ============================================
   Navigator Mind Maps — Enfold Layout Fix
   Targets only pages containing .nmm-app
   so no other pages are affected.
   ============================================ */

/* Fix Enfold blank template display:table constraint */
body:has(.nmm-app) #top.avia-blank {
    display: block !important;
    table-layout: unset !important;
    width: 100% !important;
}

/* Remove padding/margin/max-width from Enfold wrappers */
body:has(.nmm-app) #wrap_all,
body:has(.nmm-app) #main,
body:has(.nmm-app) .container_wrap,
body:has(.nmm-app) .container_wrap.fullsize,
body:has(.nmm-app) .main_color,
body:has(.nmm-app) .container {
    max-width: 100% !important;
    width: 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

/* Remove entry-content padding on NMM pages */
body:has(.nmm-app) .entry-content {
    padding: 0 !important;
    margin: 0 !important;
}';
    }
}
