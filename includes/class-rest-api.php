<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NMM_REST_API {

    public static function register_routes() {
        add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
    }

    public static function routes() {
        $namespace = 'nmm/v1';

        // Maps — GET is public, write requires auth
        register_rest_route( $namespace, '/maps', [
            [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_maps' ],   'permission_callback' => '__return_true' ],
            [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'create_map' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
        ]);
        register_rest_route( $namespace, '/maps/(?P<id>\d+)', [
            [ 'methods' => 'GET',    'callback' => [ __CLASS__, 'get_map' ],    'permission_callback' => '__return_true' ],
            [ 'methods' => 'PUT',    'callback' => [ __CLASS__, 'update_map' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
            [ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'delete_map' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
        ]);

        // Islands — GET is public
        register_rest_route( $namespace, '/islands', [
            [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_islands' ],   'permission_callback' => '__return_true' ],
            [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'create_island' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
        ]);
        register_rest_route( $namespace, '/islands/(?P<id>\d+)', [
            [ 'methods' => 'GET',    'callback' => [ __CLASS__, 'get_island' ],    'permission_callback' => '__return_true' ],
            [ 'methods' => 'PUT',    'callback' => [ __CLASS__, 'update_island' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
            [ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'delete_island' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
        ]);

        // Public share — no auth
        register_rest_route( $namespace, '/share/(?P<key>[a-zA-Z0-9]+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_shared_map' ],
            'permission_callback' => '__return_true',
        ]);

        // Nonce refresh
        register_rest_route( $namespace, '/nonce', [
            'methods'             => 'GET',
            'callback'            => function() {
                return rest_ensure_response([ 'nonce' => wp_create_nonce('wp_rest') ]);
            },
            'permission_callback' => '__return_true',
        ]);
    }

    public static function auth() {
        // Accept nmm_edit_maps (subscribers via Navigator signup) OR edit_posts (editors/admins)
        return current_user_can( 'nmm_edit_maps' ) || current_user_can( 'edit_posts' );
    }

    public static function get_maps( $request ) {
        $args = [
            'post_type'   => 'nmm_mindmap',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby'     => 'modified',
            'order'       => 'DESC',
        ];
        // Filter by current user if logged in (admins see all)
        if ( is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
            $args['author'] = get_current_user_id();
        }
        $posts = get_posts( $args );
        return rest_ensure_response( array_map( [ __CLASS__, 'format_map' ], $posts ) );
    }

    public static function get_map( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_mindmap' )
            return new WP_Error( 'not_found', 'Map not found', [ 'status' => 404 ] );
        return rest_ensure_response( self::format_map( $post, true ) );
    }

    public static function create_map( $request ) {
        $params = $request->get_json_params();
        $id = wp_insert_post([
            'post_type'   => 'nmm_mindmap',
            'post_title'  => sanitize_text_field( $params['title'] ?? 'Untitled Map' ),
            'post_status' => 'publish',
        ]);
        if ( is_wp_error( $id ) ) return $id;
        // Set author to current user
        if ( is_user_logged_in() ) {
            wp_update_post([ 'ID' => $id, 'post_author' => get_current_user_id() ]);
        }
        if ( isset( $params['nodes'] ) )    update_post_meta( $id, 'nmm_nodes', wp_slash( json_encode( $params['nodes'] ) ) );
        if ( isset( $params['settings'] ) ) update_post_meta( $id, 'nmm_settings', wp_slash( json_encode( $params['settings'] ) ) );
        if ( isset( $params['category'] ) ) update_post_meta( $id, 'nmm_category', sanitize_text_field( $params['category'] ) );
        if ( isset( $params['color'] ) )    update_post_meta( $id, 'nmm_color', sanitize_hex_color( $params['color'] ) );
        $share_key = substr( md5( $id . get_bloginfo('url') . time() ), 0, 12 );
        update_post_meta( $id, 'nmm_share_key', $share_key );
        return rest_ensure_response( self::format_map( get_post( $id ), true ) );
    }

    public static function update_map( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_mindmap' )
            return new WP_Error( 'not_found', 'Map not found', [ 'status' => 404 ] );
        $params = $request->get_json_params();
        if ( isset( $params['title'] ) )    wp_update_post([ 'ID' => $post->ID, 'post_title' => sanitize_text_field( $params['title'] ) ]);
        if ( isset( $params['nodes'] ) )    update_post_meta( $post->ID, 'nmm_nodes',    wp_slash( json_encode( $params['nodes'] ) ) );
        if ( isset( $params['settings'] ) ) update_post_meta( $post->ID, 'nmm_settings', wp_slash( json_encode( $params['settings'] ) ) );
        if ( isset( $params['category'] ) ) update_post_meta( $post->ID, 'nmm_category', sanitize_text_field( $params['category'] ) );
        if ( isset( $params['color'] ) )    update_post_meta( $post->ID, 'nmm_color',    sanitize_hex_color( $params['color'] ) );
        if ( isset( $params['islands'] ) )  update_post_meta( $post->ID, 'nmm_islands',  wp_slash( json_encode( $params['islands'] ) ) );
        return rest_ensure_response( self::format_map( get_post( $post->ID ), true ) );
    }

    public static function delete_map( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_mindmap' )
            return new WP_Error( 'not_found', 'Map not found', [ 'status' => 404 ] );
        wp_delete_post( $post->ID, true );
        return rest_ensure_response([ 'deleted' => true ]);
    }

    public static function get_islands( $request ) {
        $posts = get_posts([ 'post_type' => 'nmm_island', 'post_status' => 'publish', 'numberposts' => -1 ]);
        return rest_ensure_response( array_map( [ __CLASS__, 'format_island' ], $posts ) );
    }

    public static function get_island( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_island' )
            return new WP_Error( 'not_found', 'Island not found', [ 'status' => 404 ] );
        return rest_ensure_response( self::format_island( $post, true ) );
    }

    public static function create_island( $request ) {
        $params = $request->get_json_params();
        $id = wp_insert_post([ 'post_type' => 'nmm_island', 'post_title' => sanitize_text_field( $params['title'] ?? 'Untitled Island' ), 'post_status' => 'publish' ]);
        if ( is_wp_error( $id ) ) return $id;
        if ( isset( $params['nodes'] ) ) update_post_meta( $id, 'nmm_nodes', wp_slash( json_encode( $params['nodes'] ) ) );
        if ( isset( $params['color'] ) ) update_post_meta( $id, 'nmm_color', sanitize_hex_color( $params['color'] ) );
        return rest_ensure_response( self::format_island( get_post( $id ), true ) );
    }

    public static function update_island( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_island' )
            return new WP_Error( 'not_found', 'Island not found', [ 'status' => 404 ] );
        $params = $request->get_json_params();
        if ( isset( $params['title'] ) ) wp_update_post([ 'ID' => $post->ID, 'post_title' => sanitize_text_field( $params['title'] ) ]);
        if ( isset( $params['nodes'] ) ) update_post_meta( $post->ID, 'nmm_nodes', wp_slash( json_encode( $params['nodes'] ) ) );
        if ( isset( $params['color'] ) ) update_post_meta( $post->ID, 'nmm_color', sanitize_hex_color( $params['color'] ) );
        return rest_ensure_response( self::format_island( get_post( $post->ID ), true ) );
    }

    public static function delete_island( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_island' )
            return new WP_Error( 'not_found', 'Island not found', [ 'status' => 404 ] );
        wp_delete_post( $post->ID, true );
        return rest_ensure_response([ 'deleted' => true ]);
    }

    public static function get_shared_map( $request ) {
        $key = sanitize_text_field( $request['key'] );
        $posts = get_posts([ 'post_type' => 'nmm_mindmap', 'meta_key' => 'nmm_share_key', 'meta_value' => $key, 'numberposts' => 1 ]);
        if ( empty( $posts ) ) return new WP_Error( 'not_found', 'Shared map not found', [ 'status' => 404 ] );
        return rest_ensure_response( self::format_map( $posts[0], true ) );
    }

    private static function format_map( $post, $include_data = false ) {
        $map = [
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'modified'  => $post->post_modified,
            'category'  => get_post_meta( $post->ID, 'nmm_category', true ),
            'color'     => get_post_meta( $post->ID, 'nmm_color', true ) ?: '#0BBFBF',
            'share_key' => get_post_meta( $post->ID, 'nmm_share_key', true ),
        ];
        if ( $include_data ) {
            $map['nodes']    = json_decode( get_post_meta( $post->ID, 'nmm_nodes',    true ) ?: '[]', true );
            $map['settings'] = json_decode( get_post_meta( $post->ID, 'nmm_settings', true ) ?: '{}', true );
            $map['islands']  = json_decode( get_post_meta( $post->ID, 'nmm_islands',  true ) ?: '[]', true );
        }
        return $map;
    }

    private static function format_island( $post, $include_data = false ) {
        $island = [ 'id' => $post->ID, 'title' => $post->post_title, 'modified' => $post->post_modified, 'color' => get_post_meta( $post->ID, 'nmm_color', true ) ?: '#5DCAA5' ];
        if ( $include_data ) $island['nodes'] = json_decode( get_post_meta( $post->ID, 'nmm_nodes', true ) ?: '[]', true );
        return $island;
    }
}
