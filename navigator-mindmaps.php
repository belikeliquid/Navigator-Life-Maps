<?php
/**
 * Plugin Name: Navigator Mind Maps
 * Plugin URI: https://navigatorproject.org
 * Description: Visual mind mapping tool for Navigator Leadership Group. Multi-map manager, Kanban view, Tree of Life template, outline import, and WordPress user auth.
 * Version: 1.7.4
 * Author: Liquid Media / Navigator Leadership Group
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'NMM_VERSION', '1.7.4' );
define( 'NMM_PATH', plugin_dir_path( __FILE__ ) );
define( 'NMM_URL', plugin_dir_url( __FILE__ ) );

require_once NMM_PATH . 'includes/class-post-types.php';
require_once NMM_PATH . 'includes/class-rest-api.php';
require_once NMM_PATH . 'includes/class-shortcodes.php';
require_once NMM_PATH . 'includes/class-settings.php';
require_once NMM_PATH . 'includes/class-auth.php';
require_once NMM_PATH . 'includes/plugin-update-checker/update-checker.php';

class Navigator_MindMaps {

    public function __construct() {
        add_action( 'init',              [ $this, 'init' ] );
        // Hide WP admin bar for non-admin users — keeps the interface clean
        add_action( 'after_setup_theme', [ $this, 'maybe_hide_admin_bar' ] );
        add_action( 'init',              [ 'NMM_Settings', 'register' ] );
        add_action( 'init',              [ 'NMM_Auth',     'register' ] );
        add_action( 'wp_head',           [ $this, 'inline_nmm_config' ], 1 );
        add_action( 'wp_head',           [ $this, 'output_custom_css' ], 20 );
        add_action( 'wp_enqueue_scripts',[ $this, 'enqueue_scripts' ] );
        // Enfold JS/CSS compression exclusions
        add_filter( 'avf_file_group_skip_file', [ $this, 'exclude_from_enfold_compression' ], 10, 2 );
        // SG Optimizer exclusions
        add_filter( 'sgo_js_combine_exclude',  [ $this, 'sg_optimizer_exclude' ] );
        add_filter( 'sgo_css_combine_exclude', [ $this, 'sg_optimizer_exclude_css' ] );
        add_action( 'save_post_nmm_mindmap',   [ $this, 'purge_sg_cache' ] );
    }

    public function init() {
        NMM_Post_Types::register();
        NMM_REST_API::register_routes();
        NMM_Shortcodes::register();
        if ( get_option( 'nmm_flush_rewrite' ) ) {
            flush_rewrite_rules();
            delete_option( 'nmm_flush_rewrite' );
        }
        // Seed April map once on first activation
        if ( ! get_option( 'nmm_seeded_v1' ) ) {
            add_action( 'wp_loaded', function() {
                Navigator_MindMaps::seed_april_map();
                update_option( 'nmm_seeded_v1', true );
            });
        }
    }

    // True on pages that hold one of the app's shortcodes
    private static function is_app_page() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) return false;
        foreach ( [ 'navigator_maps', 'navigator_mindmap', 'navigator_kanban', 'navigator_timeline' ] as $sc ) {
            if ( has_shortcode( $post->post_content, $sc ) ) return true;
        }
        return false;
    }

    // Inline NMM JS config in <head> — bypasses Enfold/SG script loading issues
    public function inline_nmm_config() {
        if ( ! self::is_app_page() ) return;
        ?>
        <script>
        var NMM = {
            restUrl:     '<?php echo esc_js( rest_url( "nmm/v1/" ) ); ?>',
            ajaxUrl:     '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>',
            nonce:       '<?php echo esc_js( wp_create_nonce( "wp_rest" ) ); ?>',
            pluginUrl:   '<?php echo esc_js( NMM_URL ); ?>',
            siteUrl:     '<?php echo esc_js( get_site_url() ); ?>',
            homeUrl:     '<?php echo esc_js( home_url() ); ?>',
            isAdmin:     <?php echo current_user_can( 'manage_options' ) ? 'true' : 'false'; ?>,
            isLoggedIn:  <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
            mapsUrl:     '<?php echo esc_js( home_url( "/maps/" ) ); ?>',
            editorUrl:   '<?php echo esc_js( home_url( "/map-editor/" ) ); ?>',
            kanbanUrl:   '<?php echo esc_js( home_url( "/kanban/" ) ); ?>',
            timelineUrl: '<?php echo esc_js( home_url( "/timeline/" ) ); ?>',
            shareUrl:    '<?php echo esc_js( home_url( "/map-share/" ) ); ?>',
            logoutUrl:   '<?php echo esc_js( wp_logout_url( home_url( "/maps/" ) ) ); ?>',
            defaults:    {
                motif:  '<?php echo esc_js( NMM_Settings::get( "default_motif", "In the ocean of life, you are the navigator" ) ); ?>',
                quotes: '<?php echo esc_js( NMM_Settings::get( "default_quotes", "Navigate by stars not by fear,Roots run deep branches reach far" ) ); ?>'
            },
            bgMode:      '<?php echo esc_js( NMM_Settings::get( "default_bg_mode", "repeat" ) ); ?>',
            bgUrl:       '<?php echo esc_js( NMM_Settings::get( "default_bg_url", "" ) ); ?>'
        };
        </script>
        <?php
    }

    // Output custom CSS from settings — after core CSS so it always wins
    public function output_custom_css() {
        if ( ! self::is_app_page() ) return;

        $custom_css = get_option( 'nmm_custom_css', NMM_Settings::default_enfold_css() );
        if ( $custom_css ) {
            echo '<style id="nmm-custom-css">' . wp_strip_all_tags( $custom_css ) . '</style>';
        }
    }

    public function maybe_hide_admin_bar() {
        if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
            add_filter( 'show_admin_bar', '__return_false' );
        }
    }

    // Page scripts are registered here and enqueued by the shortcode that renders the page
    // (NMM_Shortcodes::render), so each page loads only its own. html2canvas loads on demand.
    const PAGE_SCRIPTS = [
        'nmm-canvas'   => [ 'canvas.js',   [ 'nmm-core' ] ],
        'nmm-editor'   => [ 'editor.js',   [ 'nmm-canvas' ] ],
        'nmm-view'     => [ 'view.js',     [ 'nmm-canvas' ] ],
        'nmm-kanban'   => [ 'kanban.js',   [ 'nmm-core' ] ],
        'nmm-timeline' => [ 'timeline.js', [ 'nmm-core' ] ],
        'nmm-manager'  => [ 'manager.js',  [ 'nmm-core' ] ],
        'nmm-landing'  => [ 'landing.js',  [ 'nmm-core' ] ],
    ];

    public function enqueue_scripts() {
        if ( ! self::is_app_page() ) return;

        wp_register_script( 'nmm-core', NMM_URL . 'assets/js/core.js', [], NMM_VERSION, true );
        foreach ( self::PAGE_SCRIPTS as $handle => $def ) {
            wp_register_script( $handle, NMM_URL . 'assets/js/' . $def[0], $def[1], NMM_VERSION, true );
        }
        wp_enqueue_style( 'nmm-styles', NMM_URL . 'assets/mindmaps.css', [], NMM_VERSION );
        wp_enqueue_style( 'nmm-fonts',
            'https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;500;600;700&family=Barlow+Condensed:wght@400;600;700&family=Cormorant+Garamond:ital@1&display=swap',
            [], null );
    }

    public function exclude_from_enfold_compression( $skip, $file ) {
        if ( strpos( $file, 'html2canvas' ) !== false ) return true;
        if ( strpos( $file, 'navigator-mindmaps' ) !== false ) return true;
        return $skip;
    }
    public function sg_optimizer_exclude( $list ) { return array_merge( $list, [ 'nmm-core' ], array_keys( self::PAGE_SCRIPTS ) ); }
    public function sg_optimizer_exclude_css( $list ) { $list[] = 'nmm-styles'; return $list; }
    public function purge_sg_cache() { if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache(); }

    // Seed April 2026 map
    public static function seed_april_map() {
        $existing = get_posts([ 'post_type' => 'nmm_mindmap', 'numberposts' => 1 ]);
        if ( ! empty( $existing ) ) return;

        $nodes = '[{"id":"c","x":1100,"y":420,"title":"April 2026","subs":["Naim + Jessica Rose"],"parent":null,"collapsed":false,"date":"","tag":"","sefirot":"tiphereth"},{"id":"summer","x":960,"y":190,"title":"Summer horizon — June+","subs":["Ava · Mya · Europe · Tradeshows"],"parent":"c","collapsed":false,"date":"","tag":"","sefirot":"kether"},{"id":"ava-summer","x":700,"y":60,"title":"Ava Summer","subs":["Maui, Wyoming, Bali?"],"parent":"summer","collapsed":false,"date":"","tag":"","sefirot":""},{"id":"housing-summer","x":1200,"y":60,"title":"Housing","subs":["Decide if we keep place or sublease","Talk to Landlord"],"parent":"summer","collapsed":false,"date":"2026-04-30","tag":"sprint","sefirot":"malkuth"},{"id":"decisions","x":640,"y":270,"title":"Decisions to lock","subs":["Bumi Cinta · agreement · Kitas"],"parent":"c","collapsed":false,"date":"2026-04-04","tag":"sprint","sefirot":"geburah"},{"id":"tnature","x":1420,"y":270,"title":"True Nature (TNOS)","subs":["Website · employee · TNOS"],"parent":"c","collapsed":false,"date":"","tag":"doing","sefirot":"netzach"},{"id":"embody","x":1460,"y":360,"title":"Embody collection","subs":["Apr 22 soft · May 1 hard"],"parent":"c","collapsed":false,"date":"2026-04-22","tag":"doing","sefirot":"netzach"},{"id":"naim","x":600,"y":370,"title":"Naim personal","subs":["Ocean + swim · writing","Spiritual work · Liquid Media"],"parent":"c","collapsed":false,"date":"","tag":"","sefirot":"chesed"},{"id":"jessica","x":1460,"y":460,"title":"Jessica personal","subs":["Ava\'s birthday Apr 9","Relational alchemy · trade show"],"parent":"c","collapsed":false,"date":"","tag":"","sefirot":"chesed"},{"id":"bumi","x":740,"y":500,"title":"Bumi Cinta flow","subs":["4 days Bumi Cinta · 3 days Ubud"],"parent":"c","collapsed":false,"date":"","tag":"doing","sefirot":"yesod"},{"id":"daily","x":320,"y":640,"title":"Daily rhythm","subs":["7–8am  Rise · meditate · journal · tea","8–9am  Plan day · Ava school · wellness","9–12pm  Morning work session","12–1pm  Break — body · connection · spirit","1–4pm  Afternoon work session","4–6pm  Adventure · gym · nature","6–7:30  Dinner together","8–9pm  Evening session (alt. days)"],"parent":"c","collapsed":false,"date":"","tag":"","sefirot":"yesod"},{"id":"weekly","x":1660,"y":620,"title":"Weekly rhythm","subs":["Date night — Naim + Jessica","Family dinner night","Cook at home — rotating chef","Adventure day — beach · waterfall","Social day — dancing · community","Sunday — weekly planning + check-in"],"parent":"c","collapsed":false,"date":"","tag":"","sefirot":"hod"},{"id":"apr-pri","x":1000,"y":700,"title":"April priorities","subs":["5 focus areas"],"parent":"c","collapsed":false,"date":"","tag":"","sefirot":"binah"},{"id":"mktdir","x":500,"y":880,"title":"Marketing director","subs":["Bumi Cinta role confirmed","Agreement · Kitas in motion"],"parent":"apr-pri","collapsed":false,"date":"2026-04-04","tag":"sprint","sefirot":""},{"id":"emblnch","x":720,"y":880,"title":"Embody launch","subs":["Design brief · logo · roadmap","Content · social · ads"],"parent":"apr-pri","collapsed":false,"date":"2026-04-22","tag":"doing","sefirot":""},{"id":"housing","x":960,"y":880,"title":"Housing decision","subs":["Keep or upgrade","Decide by Apr 30"],"parent":"apr-pri","collapsed":false,"date":"2026-04-30","tag":"start","sefirot":"malkuth"},{"id":"tnops","x":1200,"y":880,"title":"True Nature ops","subs":["TNOS · website · employee","Systems running before we leave"],"parent":"apr-pri","collapsed":false,"date":"","tag":"doing","sefirot":""},{"id":"liqmedia","x":1440,"y":880,"title":"Liquid Media launch","subs":["Website · offerings · portfolio","CV · cards · ready to pitch"],"parent":"apr-pri","collapsed":false,"date":"","tag":"start","sefirot":"chochma"},{"id":"ava-bday","x":1000,"y":1060,"title":"Ava\'s 17th Birthday Apr 9","subs":["Plan now — one week away"],"parent":"apr-pri","collapsed":false,"date":"2026-04-09","tag":"sprint","sefirot":""},{"id":"pizza","x":680,"y":1220,"title":"School pizza day","subs":["Confirm with school · bridge ok","Send flyer to parents","Buy + bring pizza on the day"],"parent":"ava-bday","collapsed":false,"date":"2026-04-09","tag":"start","sefirot":""},{"id":"party","x":980,"y":1220,"title":"Birthday party — Canggu","subs":["Rent villa 1–2 nights · pool + beach","3BR+ · Canggu friends invited","Fun environment for Ava"],"parent":"ava-bday","collapsed":false,"date":"2026-04-11","tag":"start","sefirot":""},{"id":"planahead","x":1280,"y":1220,"title":"Plan ahead — act now","subs":["Book villa early · guest list","Coordinate Bumi Cinta schedule","Apr 9 is days away"],"parent":"ava-bday","collapsed":false,"date":"","tag":"doing","sefirot":""}]';

        $settings = '{"motif":"In the ocean of life, you are the navigator","quotes":"Navigate by stars not by fear,Roots run deep branches reach far,What you tend to grows","bgUrl":"","bgMode":"repeat","bgPosition":"center"}';

        $id = wp_insert_post([
            'post_type'   => 'nmm_mindmap',
            'post_title'  => 'Naim & Jess — April 2026',
            'post_status' => 'publish',
        ]);

        if ( $id && ! is_wp_error( $id ) ) {
            update_post_meta( $id, 'nmm_nodes',    wp_slash( $nodes ) );
            update_post_meta( $id, 'nmm_settings', wp_slash( $settings ) );
            update_post_meta( $id, 'nmm_category', 'personal' );
            update_post_meta( $id, 'nmm_color',    '#0BBFBF' );
            update_post_meta( $id, 'nmm_share_key', substr( md5( $id . 'navigator' . time() ), 0, 12 ) );
        }
    }
}

new Navigator_MindMaps();

// GitHub auto-update checker
// Checks github.com/belikeliquid/Navigator-Life-Maps for new releases
new NMM_GitHub_Updater(
    __FILE__,
    'belikeliquid',
    'Navigator-Life-Maps',
    NMM_VERSION
);

register_activation_hook( __FILE__, function() {
    NMM_Post_Types::register();
    flush_rewrite_rules();
    update_option( 'nmm_flush_rewrite', true );
    delete_option( 'nmm_seeded_v1' );
});

register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
});
