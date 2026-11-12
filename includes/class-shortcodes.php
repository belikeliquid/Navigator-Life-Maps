<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NMM_Shortcodes {

    public static function register() {
        add_shortcode( 'navigator_maps',     [ __CLASS__, 'maps_manager' ] );
        add_shortcode( 'navigator_mindmap',  [ __CLASS__, 'single_map' ] );
        add_shortcode( 'navigator_kanban',   [ __CLASS__, 'kanban' ] );
        add_shortcode( 'navigator_timeline', [ __CLASS__, 'timeline' ] );
        add_shortcode( 'navigator_roadmap',  [ __CLASS__, 'roadmap' ] );
    }

    // Renders templates/<name>.php with $vars in scope and enqueues its script (assets/js/<name>.js).
    private static function render( $name, $vars = [] ) {
        wp_enqueue_script( 'nmm-' . $name );
        if ( ! in_array( $name, [ 'landing', 'view' ], true ) ) wp_enqueue_script( 'nmm-help' ); // the ? panel
        extract( $vars );
        ob_start();
        include NMM_PATH . 'templates/' . $name . '.php';
        return ob_get_clean();
    }

    // The More menu and its dialogs (Animate, Settings, Import, Google Calendar, images, Export Prompt),
    // the same on the Mind Map, Kanban, Timeline and Roadmap. assets/js/more.js runs it.
    public static function more_menu( $view ) {
        include NMM_PATH . 'templates/more.php';
    }

    // Map ids from ?map_id=1,2 (Kanban and timeline take several)
    public static function ids_from_url() {
        if ( empty( $_GET['map_id'] ) ) return [];
        return array_values( array_filter( array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_GET['map_id'] ) ) ) ) ) );
    }

    // The view tabs every app page shares, always in this order and place, so they can be found
    // without looking. $current: map / kanban / timeline, or '' on the maps list. $ids: the maps shown.
    // NMMCore.setViewTabs() keeps the links in step when the map filter changes. Roadmap shows one map.
    public static function view_tabs( $current, $ids = [] ) {
        $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
        $q   = $ids ? '?map_id=' . implode( ',', $ids ) : '';
        $tabs = [
            'map'      => [ '◈', 'Mind Map', count( $ids ) === 1 ? home_url( '/map-editor/?map_id=' . $ids[0] ) : '' ],
            'kanban'   => [ '⬡', 'Kanban',   home_url( '/kanban/' . $q ) ],
            'timeline' => [ '◷', 'Timeline', home_url( '/timeline/' . $q ) ],
            'roadmap'  => [ '▤', 'Roadmap',  home_url( '/roadmap/' . ( count( $ids ) === 1 ? '?map_id=' . $ids[0] : '' ) ) ],
        ];
        echo '<nav class="nmm-view-tabs" aria-label="Views">';
        foreach ( $tabs as $key => $t ) {
            $attrs = 'class="nmm-vtab' . ( $key === $current ? ' active' : '' ) . '" data-view="' . $key . '"' . ( $key === $current ? ' aria-current="page"' : '' );
            if ( $t[2] ) echo '<a ' . $attrs . ' href="' . esc_url( $t[2] ) . '">';
            else echo '<a ' . $attrs . ' aria-disabled="true" title="Open one map to see its mind map">';
            echo '<span aria-hidden="true">' . $t[0] . '</span> ' . $t[1] . '</a>';
        }
        echo '</nav>';
    }

    // [navigator_maps] — landing if logged out, manager if logged in
    public static function maps_manager( $atts ) {
        return self::render( is_user_logged_in() ? 'manager' : 'landing' );
    }

    // [navigator_mindmap id="123"] or [navigator_mindmap id="123" readonly="true"]
    public static function single_map( $atts ) {
        $atts = shortcode_atts([
            'id'       => '',
            'readonly' => 'false',
        ], $atts );

        // Allow map_id from URL query string
        if ( empty( $atts['id'] ) && ! empty( $_GET['map_id'] ) ) {
            $atts['id'] = intval( $_GET['map_id'] );
        }
        if ( empty( $atts['id'] ) && ! empty( $_GET['share'] ) ) {
            $atts['readonly'] = 'true';
            $atts['id']       = '0';
        }

        if ( ! $atts['id'] && empty( $_GET['share'] ) ) {
            return '<p style="padding:2rem;text-align:center;color:#999;">No map ID specified. <a href="' . esc_url( home_url( '/maps/' ) ) . '">Return to maps</a>.</p>';
        }

        $readonly = filter_var( $atts['readonly'], FILTER_VALIDATE_BOOLEAN );
        if ( ! $readonly && ! is_user_logged_in() ) {
            $readonly = true;
        }

        return self::render( $readonly ? 'view' : 'editor', [ 'map_id' => intval( $atts['id'] ) ] );
    }

    // [navigator_timeline]
    public static function timeline( $atts ) {
        if ( ! is_user_logged_in() ) {
            wp_redirect( home_url( '/maps/?from=timeline' ) );
            exit;
        }
        return self::render( 'timeline' );
    }

    // [navigator_roadmap] — one map at a time (?map_id=N, else the most recently edited)
    public static function roadmap( $atts ) {
        if ( ! is_user_logged_in() ) {
            wp_redirect( home_url( '/maps/?from=roadmap' ) );
            exit;
        }
        return self::render( 'roadmap' );
    }

    // [navigator_kanban]
    public static function kanban( $atts ) {
        if ( ! is_user_logged_in() ) {
            wp_redirect( home_url( '/maps/?from=kanban' ) );
            exit;
        }
        return self::render( 'kanban' );
    }
}
