<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NMM_Shortcodes {

    public static function register() {
        add_shortcode( 'navigator_maps',     [ __CLASS__, 'maps_manager' ] );
        add_shortcode( 'navigator_mindmap',  [ __CLASS__, 'single_map' ] );
        add_shortcode( 'navigator_kanban',   [ __CLASS__, 'kanban' ] );
        add_shortcode( 'navigator_timeline', [ __CLASS__, 'timeline' ] );
    }

    // Renders templates/<name>.php with $vars in scope and enqueues its script (assets/js/<name>.js).
    private static function render( $name, $vars = [] ) {
        wp_enqueue_script( 'nmm-' . $name );
        extract( $vars );
        ob_start();
        include NMM_PATH . 'templates/' . $name . '.php';
        return ob_get_clean();
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
            return '<p style="padding:2rem;text-align:center;color:#999;font-family:Barlow Condensed,sans-serif;">No map ID specified. <a href="' . esc_url( home_url( '/maps/' ) ) . '">Return to maps</a>.</p>';
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

    // [navigator_kanban]
    public static function kanban( $atts ) {
        if ( ! is_user_logged_in() ) {
            wp_redirect( home_url( '/maps/?from=kanban' ) );
            exit;
        }
        return self::render( 'kanban' );
    }
}
