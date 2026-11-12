<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NMM_REST_API {

    public static function register_routes() {
        add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
        add_action( 'nmm_share_email', [ __CLASS__, 'send_share_email' ], 10, 3 );
        add_action( 'nmm_invite_email', [ __CLASS__, 'send_invite_email' ], 10, 4 );
    }

    public static function routes() {
        $namespace = 'nmm/v1';

        // Maps — only their owner or an admin; share links use /share/<key>
        register_rest_route( $namespace, '/maps', [
            [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_maps' ],   'permission_callback' => '__return_true' ],
            [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'create_map' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
        ]);
        register_rest_route( $namespace, '/maps/(?P<id>\d+)', [
            [ 'methods' => 'GET',    'callback' => [ __CLASS__, 'get_map' ],    'permission_callback' => '__return_true' ],
            [ 'methods' => 'PUT',    'callback' => [ __CLASS__, 'update_map' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
            [ 'methods' => 'DELETE', 'callback' => [ __CLASS__, 'delete_map' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
        ]);

        // Sharing with other members. Only the owner (or an admin) manages the list;
        // anyone it is shared with can leave.
        register_rest_route( $namespace, '/maps/(?P<id>\d+)/people', [
            [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_people' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
            [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'add_person' ], 'permission_callback' => [ __CLASS__, 'auth' ] ],
        ]);
        register_rest_route( $namespace, '/maps/(?P<id>\d+)/people/remove', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'remove_person' ], 'permission_callback' => [ __CLASS__, 'auth' ],
        ]);
        // Featured maps: an admin marks them; every member can read and copy them
        register_rest_route( $namespace, '/featured', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'get_featured' ], 'permission_callback' => '__return_true',
        ]);
        register_rest_route( $namespace, '/maps/(?P<id>\d+)/copy', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'copy_map' ], 'permission_callback' => [ __CLASS__, 'auth' ],
        ]);
        // Invitations for people without an account: the link's token is the proof they got the email
        register_rest_route( $namespace, '/invite/(?P<token>[A-Za-z0-9]{20,64})', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'get_invite' ], 'permission_callback' => '__return_true',
        ]);
        register_rest_route( $namespace, '/invite/accept', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'accept_invite' ], 'permission_callback' => [ __CLASS__, 'auth' ],
        ]);
        register_rest_route( $namespace, '/maps/(?P<id>\d+)/leave', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'leave_map' ], 'permission_callback' => [ __CLASS__, 'auth' ],
        ]);

        // Islands — same rule; creating one is admin only (no app screen makes them)
        register_rest_route( $namespace, '/islands', [
            [ 'methods' => 'GET',  'callback' => [ __CLASS__, 'get_islands' ],   'permission_callback' => '__return_true' ],
            [ 'methods' => 'POST', 'callback' => [ __CLASS__, 'create_island' ], 'permission_callback' => function() { return current_user_can( 'manage_options' ); } ],
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

        // AI test — verifies Anthropic API key is working
        register_rest_route( $namespace, '/ai/test', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'ai_test' ],
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ]);

        // AI questions — reads notes and returns smart clarifying questions
        register_rest_route( $namespace, '/ai/questions', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'ai_questions' ],
            'permission_callback' => [ __CLASS__, 'auth' ],
        ]);

        // AI organize — reads notes and returns structured nodes
        register_rest_route( $namespace, '/ai/organize', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'ai_organize' ],
            'permission_callback' => [ __CLASS__, 'auth' ],
        ]);

        // Reorganize an existing map: questions first, then the rewrite, and a way back
        register_rest_route( $namespace, '/maps/(?P<id>\d+)/reorganize/questions', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'reorganize_questions' ], 'permission_callback' => [ __CLASS__, 'auth' ],
        ]);
        register_rest_route( $namespace, '/maps/(?P<id>\d+)/reorganize', [
            'methods' => 'POST', 'callback' => [ __CLASS__, 'reorganize' ], 'permission_callback' => [ __CLASS__, 'auth' ],
        ]);
        register_rest_route( $namespace, '/maps/(?P<id>\d+)/reorganize/before', [
            'methods' => 'GET', 'callback' => [ __CLASS__, 'reorganize_before' ], 'permission_callback' => [ __CLASS__, 'auth' ],
        ]);

    }

    public static function auth() {
        // Accept nmm_edit_maps (subscribers via Navigator signup) OR edit_posts (editors/admins)
        return current_user_can( 'nmm_edit_maps' ) || current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
    }

    // The person who made it, or an admin: they can delete it and choose who it is shared with.
    private static function can_manage( $post ) {
        return current_user_can( 'manage_options' ) || ( is_user_logged_in() && (int) $post->post_author === get_current_user_id() );
    }

    // Owner, admin, or a member the map is shared with. Others get the same "not found" as a missing map.
    private static function can_access( $post ) {
        return self::can_manage( $post ) || ( $post->post_type === 'nmm_mindmap' && self::is_editor( $post ) );
    }

    // Featured maps can be read (not changed) by anyone signed in
    private static function is_featured( $post ) {
        return (bool) get_post_meta( $post->ID, 'nmm_featured', true );
    }
    private static function can_read( $post ) {
        return self::can_access( $post ) || ( is_user_logged_in() && $post->post_type === 'nmm_mindmap' && self::is_featured( $post ) );
    }

    // Members a map is shared with: one nmm_editor meta row per user id, so maps can be looked up by it.
    private static function editors( $post_id ) {
        return array_map( 'intval', get_post_meta( $post_id, 'nmm_editor', false ) );
    }
    private static function is_editor( $post ) {
        return is_user_logged_in() && in_array( get_current_user_id(), self::editors( $post->ID ), true );
    }

    public static function get_maps( $request ) {
        if ( ! is_user_logged_in() ) return rest_ensure_response( [] );
        $args = [
            'post_type'   => 'nmm_mindmap',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby'     => 'modified',
            'order'       => 'DESC',
        ];
        // Admins see all; everyone else sees their own maps and the ones shared with them
        if ( current_user_can( 'manage_options' ) ) {
            $posts = get_posts( $args );
        } else {
            $uid   = get_current_user_id();
            $posts = get_posts( $args + [ 'author' => $uid ] );
            $own   = wp_list_pluck( $posts, 'ID' );
            foreach ( get_posts( $args + [ 'meta_key' => 'nmm_editor', 'meta_value' => (string) $uid ] ) as $p ) {
                if ( ! in_array( $p->ID, $own, true ) ) $posts[] = $p;
            }
            usort( $posts, function ( $a, $b ) { return strcmp( $b->post_modified, $a->post_modified ); } );
        }
        return rest_ensure_response( array_map( [ __CLASS__, 'format_map' ], $posts ) );
    }

    public static function get_map( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_mindmap' || ! self::can_read( $post ) )
            return new WP_Error( 'not_found', 'Map not found', [ 'status' => 404 ] );
        return rest_ensure_response( self::format_map( $post, true ) );
    }

    public static function create_map( $request ) {
        $limit = NMM_Auth::limit_error();
        if ( $limit ) return $limit;
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
        if ( ! $post || $post->post_type !== 'nmm_mindmap' || ! self::can_access( $post ) )
            return new WP_Error( 'not_found', 'Map not found', [ 'status' => 404 ] );
        $params = $request->get_json_params();
        // The editor sends the revision it loaded; refuse the save if another
        // tab or page (kanban, maps list) saved this map since then.
        $rev = (int) get_post_meta( $post->ID, 'nmm_rev', true );
        if ( isset( $params['base_rev'] ) && (int) $params['base_rev'] !== $rev )
            return new WP_Error( 'conflict', 'This map was changed somewhere else since it was opened.', [ 'status' => 409, 'rev' => $rev ] );
        update_post_meta( $post->ID, 'nmm_rev', $rev + 1 );
        update_post_meta( $post->ID, 'nmm_last_editor', get_current_user_id() );
        if ( isset( $params['title'] ) )    wp_update_post([ 'ID' => $post->ID, 'post_title' => sanitize_text_field( $params['title'] ) ]);
        if ( isset( $params['nodes'] ) )    update_post_meta( $post->ID, 'nmm_nodes',    wp_slash( json_encode( $params['nodes'] ) ) );
        if ( isset( $params['settings'] ) ) update_post_meta( $post->ID, 'nmm_settings', wp_slash( json_encode( $params['settings'] ) ) );
        if ( isset( $params['category'] ) ) update_post_meta( $post->ID, 'nmm_category', sanitize_text_field( $params['category'] ) );
        if ( isset( $params['color'] ) )    update_post_meta( $post->ID, 'nmm_color',    sanitize_hex_color( $params['color'] ) );
        if ( isset( $params['featured'] ) && current_user_can( 'manage_options' ) ) {
            if ( $params['featured'] ) update_post_meta( $post->ID, 'nmm_featured', 1 );
            else delete_post_meta( $post->ID, 'nmm_featured' );
        }
        // Linked islands belong to the owner, so only they can change which are linked
        if ( isset( $params['islands'] ) && self::can_manage( $post ) ) update_post_meta( $post->ID, 'nmm_islands', wp_slash( json_encode( $params['islands'] ) ) );
        return rest_ensure_response( self::format_map( get_post( $post->ID ), true ) );
    }

    public static function delete_map( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_mindmap' || ! self::can_access( $post ) )
            return new WP_Error( 'not_found', 'Map not found', [ 'status' => 404 ] );
        if ( ! self::can_manage( $post ) )
            return new WP_Error( 'not_owner', 'Only the owner can delete this map. You can leave it instead.', [ 'status' => 403 ] );
        wp_delete_post( $post->ID, true );
        return rest_ensure_response([ 'deleted' => true ]);
    }

    public static function get_islands( $request ) {
        if ( ! is_user_logged_in() ) return rest_ensure_response( [] );
        $args = [ 'post_type' => 'nmm_island', 'post_status' => 'publish', 'numberposts' => -1 ];
        if ( ! current_user_can( 'manage_options' ) ) $args['author'] = get_current_user_id();
        $posts = get_posts( $args );
        return rest_ensure_response( array_map( [ __CLASS__, 'format_island' ], $posts ) );
    }

    public static function get_island( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_island' || ! self::can_access( $post ) )
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
        if ( ! $post || $post->post_type !== 'nmm_island' || ! self::can_access( $post ) )
            return new WP_Error( 'not_found', 'Island not found', [ 'status' => 404 ] );
        $params = $request->get_json_params();
        if ( isset( $params['title'] ) ) wp_update_post([ 'ID' => $post->ID, 'post_title' => sanitize_text_field( $params['title'] ) ]);
        if ( isset( $params['nodes'] ) ) update_post_meta( $post->ID, 'nmm_nodes', wp_slash( json_encode( $params['nodes'] ) ) );
        if ( isset( $params['color'] ) ) update_post_meta( $post->ID, 'nmm_color', sanitize_hex_color( $params['color'] ) );
        return rest_ensure_response( self::format_island( get_post( $post->ID ), true ) );
    }

    public static function delete_island( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_island' || ! self::can_access( $post ) )
            return new WP_Error( 'not_found', 'Island not found', [ 'status' => 404 ] );
        wp_delete_post( $post->ID, true );
        return rest_ensure_response([ 'deleted' => true ]);
    }

    // ---------------------------------------------------------------
    // SHARING WITH MEMBERS
    // The reply never says whether an account has the email, so the form can't be used
    // to find out who is on the site. nmm_people keeps every address the owner entered
    // (with the user it matched, or 0); only the owner sees the addresses.
    // An address that matches nobody grants nothing, even if someone signs up with it
    // later: signup doesn't confirm that the email belongs to the person.
    // ---------------------------------------------------------------
    const SHARE_MAX = 25;

    private static function people( $post_id ) {
        $p = json_decode( get_post_meta( $post_id, 'nmm_people', true ) ?: '[]', true );
        return is_array( $p ) ? array_values( $p ) : [];
    }
    private static function save_people( $post_id, $people ) {
        update_post_meta( $post_id, 'nmm_people', wp_slash( json_encode( array_values( $people ) ) ) );
    }
    private static function people_response( $post_id, $message = '' ) {
        return rest_ensure_response( [ 'people' => array_column( self::people( $post_id ), 'email' ), 'message' => $message ] );
    }

    // The map, if the current user may manage who it is shared with
    private static function managed_map( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_mindmap' || ! self::can_access( $post ) )
            return new WP_Error( 'not_found', 'Map not found', [ 'status' => 404 ] );
        if ( ! self::can_manage( $post ) )
            return new WP_Error( 'not_owner', 'Only the owner can choose who this map is shared with.', [ 'status' => 403 ] );
        return $post;
    }

    public static function get_people( $request ) {
        $post = self::managed_map( $request );
        if ( is_wp_error( $post ) ) return $post;
        return self::people_response( $post->ID );
    }

    public static function add_person( $request ) {
        $post = self::managed_map( $request );
        if ( is_wp_error( $post ) ) return $post;
        $params = $request->get_json_params();
        $email  = strtolower( sanitize_email( $params['email'] ?? '' ) );
        if ( ! is_email( $email ) )
            return new WP_Error( 'bad_email', 'Please enter a valid email address.', [ 'status' => 400 ] );

        $done   = 'Thanks. We\'ve let them know: a member can open the map from their maps list, and anyone else gets an invitation to join.';
        $people = self::people( $post->ID );
        if ( in_array( $email, array_column( $people, 'email' ), true ) ) return self::people_response( $post->ID, $done );
        if ( count( $people ) >= self::SHARE_MAX )
            return new WP_Error( 'too_many', 'A map can be shared with up to ' . self::SHARE_MAX . ' people.', [ 'status' => 400 ] );
        if ( ! self::count_share() )
            return new WP_Error( 'share_limit', 'You have shared with a lot of people today. Please try again tomorrow.', [ 'status' => 429 ] );

        $user = get_user_by( 'email', $email );
        $uid  = $user ? (int) $user->ID : 0;
        $self = $uid === (int) $post->post_author;
        if ( $self ) $uid = 0;
        $people[] = [ 'email' => $email, 'user' => $uid ];
        self::save_people( $post->ID, $people );
        // Emails go out through WP cron, so the reply takes the same time whichever one is sent
        if ( $uid && ! in_array( $uid, self::editors( $post->ID ), true ) ) {
            add_post_meta( $post->ID, 'nmm_editor', $uid );
            wp_schedule_single_event( time(), 'nmm_share_email', [ $post->ID, $uid, get_current_user_id() ] );
        } elseif ( ! $uid && ! $self ) {
            $token = wp_generate_password( 32, false );
            update_post_meta( $post->ID, self::invite_key( $token ), wp_slash( wp_json_encode( [
                'email' => $email, 'by' => get_current_user_id(), 'expires' => time() + self::INVITE_DAYS * DAY_IN_SECONDS,
            ] ) ) );
            wp_schedule_single_event( time(), 'nmm_invite_email', [ $post->ID, $email, get_current_user_id(), $token ] );
        }
        return self::people_response( $post->ID, $done );
    }

    public static function remove_person( $request ) {
        $post = self::managed_map( $request );
        if ( is_wp_error( $post ) ) return $post;
        $params = $request->get_json_params();
        $email  = strtolower( sanitize_email( $params['email'] ?? '' ) );
        $keep   = [];
        $gone   = [];
        foreach ( self::people( $post->ID ) as $p ) {
            if ( $p['email'] === $email ) $gone[] = (int) $p['user'];
            else $keep[] = $p;
        }
        self::save_people( $post->ID, $keep );
        foreach ( self::invites( $post->ID ) as $key => $inv ) {
            if ( $inv['email'] === $email ) delete_post_meta( $post->ID, $key );
        }
        $still = array_map( 'intval', array_column( $keep, 'user' ) );
        foreach ( array_filter( $gone ) as $uid ) {
            if ( ! in_array( $uid, $still, true ) ) delete_post_meta( $post->ID, 'nmm_editor', $uid );
        }
        return self::people_response( $post->ID, '' );
    }

    public static function leave_map( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_mindmap' || ! self::is_editor( $post ) )
            return new WP_Error( 'not_found', 'Map not found', [ 'status' => 404 ] );
        $uid = get_current_user_id();
        delete_post_meta( $post->ID, 'nmm_editor', $uid );
        self::save_people( $post->ID, array_filter( self::people( $post->ID ), function ( $p ) use ( $uid ) { return (int) $p['user'] !== $uid; } ) );
        return rest_ensure_response( [ 'left' => true ] );
    }

    public static function send_share_email( $map_id, $user_id, $sharer_id ) {
        $post = get_post( $map_id );
        $user = get_userdata( $user_id );
        // Removed again before the email went out: send nothing
        if ( ! $post || ! $user || ! in_array( (int) $user_id, self::editors( $map_id ), true ) ) return;
        $sharer = get_userdata( $sharer_id );
        $name   = $sharer ? $sharer->display_name : 'Someone';
        $title  = wp_strip_all_tags( $post->post_title );
        NMM_Mail::send( $user->user_email, $name . ' shared a Life Map with you: ' . $title, [
            'preheader'  => $name . ' wants to work on "' . $title . '" with you.',
            'heading'    => $name . ' shared a Life Map with you',
            'paragraphs' => [ 'Hi ' . $user->display_name . ',', $name . ' shared this map with you so you can work on it together. You can both open it, add to it and move it forward, and every change is saved for both of you.' ],
            'map_title'  => $title,
            'button'     => 'Open the map',
            'url'        => home_url( '/map-editor/?map_id=' . $map_id ),
            'note'       => 'It is also in your maps list, marked "Shared by ' . $name . '".',
            'reason'     => 'You received this because ' . $name . ' shared a map with you on Navigator.',
        ] );
    }

    // ---- Invitations ----
    // The token is sent only in the email; the map keeps a hash of it (meta nmm_inv_<sha256>),
    // with the address, who invited and when it expires. Accepting needs the token, so signing
    // up with someone else's address gets nothing.
    const INVITE_DAYS = 14;
    const SHARE_DAILY = 20;

    private static function invite_key( $token ) { return 'nmm_inv_' . hash( 'sha256', $token ); }
    private static function invites( $post_id ) {
        $out = [];
        foreach ( get_post_meta( $post_id ) as $key => $vals ) {
            if ( strpos( $key, 'nmm_inv_' ) !== 0 ) continue;
            $inv = json_decode( $vals[0], true );
            if ( is_array( $inv ) ) $out[ $key ] = $inv;
        }
        return $out;
    }
    // The map an invitation token belongs to, with its details, or null (expired ones are cleared)
    private static function find_invite( $token ) {
        global $wpdb;
        if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9]{20,64}$/', $token ) ) return null;
        $key = self::invite_key( $token );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1", $key ) );
        if ( ! $row ) return null;
        $inv  = json_decode( $row->meta_value, true );
        $post = get_post( (int) $row->post_id );
        if ( ! is_array( $inv ) || ! $post || $post->post_type !== 'nmm_mindmap' || $post->post_status !== 'publish' ) return null;
        if ( (int) $inv['expires'] < time() ) { delete_post_meta( $post->ID, $key ); return null; }
        return [ 'post' => $post, 'key' => $key, 'invite' => $inv ];
    }
    // At most SHARE_DAILY shares a day per person (admins excepted). Counted for every share, member
    // or not, so hitting the limit gives nothing away. Returns false when the limit is reached.
    private static function count_share() {
        if ( current_user_can( 'manage_options' ) ) return true;
        $uid = get_current_user_id();
        $log = array_filter( (array) get_user_meta( $uid, 'nmm_share_log', true ), function ( $t ) { return (int) $t > time() - DAY_IN_SECONDS; } );
        if ( count( $log ) >= self::SHARE_DAILY ) return false;
        $log[] = time();
        update_user_meta( $uid, 'nmm_share_log', array_values( $log ) );
        return true;
    }

    public static function send_invite_email( $map_id, $email, $sharer_id, $token ) {
        $found = self::find_invite( $token );
        if ( ! $found || (int) $found['post']->ID !== (int) $map_id ) return; // cancelled before it went out
        $sharer = get_userdata( $sharer_id );
        $name   = $sharer ? $sharer->display_name : 'A Navigator member';
        $title  = wp_strip_all_tags( $found['post']->post_title );
        $until  = wp_date( 'F j', (int) $found['invite']['expires'] );
        NMM_Mail::send( $email, $name . ' invited you to collaborate on a Life Map: ' . $title, [
            'preheader'  => 'Join Navigator to open "' . $title . '" and work on it together.',
            'heading'    => $name . ' invited you to collaborate on a Life Map',
            'paragraphs' => [ 'Hi,', $name . ' would like to work on this map with you on Navigator. Join to open it, add your part and take action together. Your account is free, and this invitation takes you straight to the map.' ],
            'map_title'  => $title,
            'button'     => 'Join and open the map',
            'url'        => home_url( '/maps/?invite=' . $token ),
            'note'       => 'This invitation is just for you and works until ' . $until . '. Already have a Navigator account? Use the same button and log in. If you weren\'t expecting this, you can ignore this email.',
            'reason'     => 'You received this because ' . $name . ' invited ' . $email . ' to a map on Navigator.',
        ] );
    }

    // For the signup page banner. Says only what the person holding the email already knows.
    public static function get_invite( $request ) {
        $found = self::find_invite( $request['token'] );
        if ( ! $found ) return rest_ensure_response( [ 'valid' => false ] );
        $by = get_userdata( (int) $found['invite']['by'] );
        return rest_ensure_response( [
            'valid' => true, 'map_title' => $found['post']->post_title,
            'by' => $by ? $by->display_name : 'A Navigator member', 'email' => $found['invite']['email'],
        ] );
    }

    // Gives the current user access to the invitation's map. Used right after signup or login too.
    public static function redeem_invite( $token, $uid ) {
        $found = self::find_invite( $token );
        if ( ! $found ) return new WP_Error( 'invite_invalid', 'This invitation has expired or was already used. Ask the person who invited you to share the map again.', [ 'status' => 404 ] );
        $post = $found['post'];
        delete_post_meta( $post->ID, $found['key'] );
        if ( (int) $post->post_author !== (int) $uid && ! in_array( (int) $uid, self::editors( $post->ID ), true ) ) {
            add_post_meta( $post->ID, 'nmm_editor', (int) $uid );
        }
        // The owner's list shows the invited address; link it to this account so Remove works
        $people = self::people( $post->ID );
        foreach ( $people as &$p ) { if ( $p['email'] === $found['invite']['email'] ) $p['user'] = (int) $uid; }
        unset( $p );
        self::save_people( $post->ID, $people );
        return (int) $post->ID;
    }
    public static function invite_is_valid( $token ) { return (bool) self::find_invite( $token ); }
    public static function accept_invite( $request ) {
        $params = $request->get_json_params();
        $id = self::redeem_invite( (string) ( $params['token'] ?? '' ), get_current_user_id() );
        return is_wp_error( $id ) ? $id : rest_ensure_response( [ 'map_id' => $id ] );
    }

    public static function get_featured( $request ) {
        if ( ! is_user_logged_in() ) return rest_ensure_response( [] );
        $posts = get_posts( [ 'post_type' => 'nmm_mindmap', 'post_status' => 'publish', 'numberposts' => 50,
            'meta_key' => 'nmm_featured', 'meta_value' => '1', 'orderby' => 'title', 'order' => 'ASC' ] );
        return rest_ensure_response( array_map( function ( $p ) {
            $m = self::format_map( $p );
            $nodes = json_decode( get_post_meta( $p->ID, 'nmm_nodes', true ) ?: '[]', true );
            $m['cards'] = is_array( $nodes ) ? count( $nodes ) : 0;
            return $m;
        }, $posts ) );
    }

    // A new map of your own with the same cards and settings (linked islands stay with the original)
    public static function copy_map( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_mindmap' || ! self::can_read( $post ) )
            return new WP_Error( 'not_found', 'Map not found', [ 'status' => 404 ] );
        $limit = NMM_Auth::limit_error();
        if ( $limit ) return $limit;
        $id = wp_insert_post( [ 'post_type' => 'nmm_mindmap', 'post_title' => $post->post_title, 'post_status' => 'publish', 'post_author' => get_current_user_id() ] );
        if ( is_wp_error( $id ) ) return $id;
        foreach ( [ 'nmm_nodes', 'nmm_settings', 'nmm_category', 'nmm_color' ] as $key ) {
            $v = get_post_meta( $post->ID, $key, true );
            if ( $v !== '' ) update_post_meta( $id, $key, wp_slash( $v ) );
        }
        update_post_meta( $id, 'nmm_share_key', substr( md5( $id . get_bloginfo( 'url' ) . microtime() ), 0, 12 ) );
        return rest_ensure_response( self::format_map( get_post( $id ), true ) );
    }

    public static function get_shared_map( $request ) {
        $key = sanitize_text_field( $request['key'] );
        $posts = get_posts([ 'post_type' => 'nmm_mindmap', 'meta_key' => 'nmm_share_key', 'meta_value' => $key, 'numberposts' => 1 ]);
        if ( empty( $posts ) ) return new WP_Error( 'not_found', 'Shared map not found', [ 'status' => 404 ] );
        return rest_ensure_response( self::format_map( $posts[0], true ) );
    }

    public static function ai_questions( $request ) {
        $limit = NMM_Auth::limit_error(); // no new map possible, so don't spend AI credit on one
        if ( $limit ) return $limit;
        $limit = NMM_Auth::ai_error();
        if ( $limit ) return $limit;
        $params = $request->get_json_params();
        $title  = sanitize_text_field( $params['title'] ?? '' );
        $notes  = sanitize_textarea_field( $params['notes'] ?? '' );
        if ( mb_strlen( $notes ) > self::NOTES_MAX ) return self::too_long();
        if ( $notes ) {
            $limit = NMM_Auth::ai_questions_error();
            if ( $limit ) return $limit;
        }

        if ( ! $notes ) {
            return new WP_Error( 'no_notes', 'No notes provided', [ 'status' => 400 ] );
        }

        $api_key = get_option( 'nmm_anthropic_key', '' );
        if ( ! $api_key ) {
            return new WP_Error( 'no_key', 'Anthropic API key not configured', [ 'status' => 500 ] );
        }

        $today  = date('Y-m-d');
        $prompt = 'You are helping a user build a mind map from their notes. Today is ' . $today . '. Read their notes carefully and generate 3 to 5 short clarifying questions that will help you better organize their content. Focus on: timeframes and deadlines, urgency and priority, what needs to happen first, key people or relationships involved, and what success looks like.

Map title: ' . $title . '

Notes:
' . $notes . '

Return ONLY a JSON array of question strings. No explanation. No markdown. Example format:
["What is the overall deadline for this project?", "Which item is most urgent right now?"]

Return the JSON array only.';

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 45,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => json_encode([
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 500,
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ]),
        ]);

        $error = self::anthropic_error( $response );
        if ( $error ) return $error;

        $questions = self::reply_json( $response );
        if ( ! is_array( $questions ) ) {
            return new WP_Error( 'parse_error', 'Could not parse questions', [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'questions' => array_values( array_filter( $questions, 'is_string' ) ) ] );
    }

    const NOTES_MAX = 30000;  // characters of notes the AI will read
    const MAP_MAX   = 40000;  // characters of map JSON a Reorganize will send
    private static function too_long() {
        return new WP_Error( 'too_long', 'That is more text than the AI can take in one go. Please shorten it and try again.', [ 'status' => 400 ] );
    }

    // The JSON array in a Claude reply (its first text block, code fences and extra words removed), or null.
    private static function reply_json( $response ) {
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $text = '';
        foreach ( (array) ( $body['content'] ?? [] ) as $block ) {
            if ( ( $block['type'] ?? '' ) === 'text' ) { $text = $block['text']; break; }
        }
        $text  = trim( preg_replace( '/```json|```/', '', $text ) );
        $start = strpos( $text, '[' );
        $end   = strrpos( $text, ']' );
        if ( $start === false || $end === false ) return null;
        $data = json_decode( substr( $text, $start, $end - $start + 1 ), true );
        return is_array( $data ) ? $data : null;
    }

    private static function claude( $model, $prompt, $max_tokens, $timeout ) {
        return wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => get_option( 'nmm_anthropic_key', '' ),
                'anthropic-version' => '2023-06-01',
            ],
            'body' => json_encode([
                'model'      => $model,
                'max_tokens' => $max_tokens,
                'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
            ]),
        ]);
    }

    // Turns a failed Anthropic call (connection error or non-200) into a WP_Error
    // that carries the API's own message, e.g. an invalid key or a retired model.
    private static function anthropic_error( $response ) {
        if ( is_wp_error( $response ) ) {
            $msg = $response->get_error_message();
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code === 200 ) return null;
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            $msg  = $body['error']['message'] ?? 'HTTP ' . $code;
        }
        error_log( 'Navigator Mind Maps: Anthropic request failed: ' . $msg );
        return new WP_Error( 'api_error', 'AI request failed: ' . $msg, [ 'status' => 502 ] );
    }

    public static function ai_test( $request ) {
        $api_key = get_option( 'nmm_anthropic_key', '' );
        if ( ! $api_key ) {
            return rest_ensure_response([ 'success' => false, 'message' => 'No API key saved in settings.' ]);
        }
        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 15,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => json_encode([
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 10,
                'messages'   => [ [ 'role' => 'user', 'content' => 'Hi' ] ],
            ]),
        ]);
        if ( is_wp_error( $response ) ) {
            return rest_ensure_response([ 'success' => false, 'message' => $response->get_error_message() ]);
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $code === 200 ) {
            return rest_ensure_response([ 'success' => true, 'model' => $body['model'] ?? 'connected' ]);
        }
        $error = $body['error']['message'] ?? 'HTTP ' . $code;
        return rest_ensure_response([ 'success' => false, 'message' => $error ]);
    }

    public static function ai_organize( $request ) {
        $limit = NMM_Auth::limit_error();
        if ( $limit ) return $limit;
        $limit = NMM_Auth::ai_error();
        if ( $limit ) return $limit;
        $params = $request->get_json_params();
        $title  = sanitize_text_field( $params['title'] ?? 'My Map' );
        $notes   = sanitize_textarea_field( $params['notes'] ?? '' );
        $answers = $params['answers'] ?? [];
        $layout  = sanitize_key( $params['layout'] ?? 'flow' );

        if ( ! $notes ) {
            return new WP_Error( 'no_notes', 'No notes provided', [ 'status' => 400 ] );
        }
        if ( mb_strlen( $notes ) > self::NOTES_MAX ) return self::too_long();

        $api_key = get_option( 'nmm_anthropic_key', '' );
        if ( ! $api_key ) {
            return new WP_Error( 'no_key', 'Anthropic API key not configured', [ 'status' => 500 ] );
        }

        $answers_text = self::answers_text( $answers );

        $today  = date('Y-m-d');
        $kanban = 'Kanban stages — assign by urgency and importance, and keep each stage small so the board stays focused:
- sprint: at most 3 cards. Must happen immediately, the highest priority of all
- doing: at most 5 cards. Already in progress according to the notes or answers
- start: at most 5 cards. The next things to begin
- review: at most 5 cards. Waiting on something or needs checking
- done: already completed according to the notes or answers
These are limits, not targets. Choose only the most important and most urgent cards for these stages. Every other task gets tag "" and task true, so it waits in Triage until there is room.
Headings and cards that only hold information get tag "" and task false.';
        $prompt = 'You are organizing content for a mind map based on the Kabbalistic Tree of Life.

Today is ' . $today . '. Use this as the reference for all dates. All dates must be on or after today.

Map title: ' . $title . '

Notes:
' . $notes . $answers_text . '

Assign each key topic to the most appropriate Sefirot position. Use the answers to set Kanban tags (start/doing/sprint/review/done) based on urgency and priority, and add dates in YYYY-MM-DD format where mentioned or implied.

Sefirot guide:
- kether: vision, purpose, highest aspiration
- chochma: wisdom, insight, strategy
- binah: understanding, structure, planning
- chesed: love, relationships, heart work
- geburah: discipline, challenges, boundaries
- tiphereth: beauty, center, core self (use for main theme)
- netzach: victory, goals, achievement
- hod: communication, expression, details
- yesod: foundation, daily practice, habits
- malkuth: material, practical, physical reality

' . $kanban . '

Return ONLY a JSON array of nodes. No explanation. No markdown.

Each node must have:
- id: short unique string like "n1"
- x: number (sefirot position)
- y: number (sefirot position)
- title: short topic title (under 6 words)
- subs: array of subtitle strings (key points, max 4)
- parent: "c" for all nodes
- sefirot: sefirot name
- collapsed: false
- date: "YYYY-MM-DD" if date mentioned, otherwise ""
- tag: one of start/doing/sprint/review/done or ""
- task: true if the card is something to do, false if it is a heading or only information

Include center node: id "c", sefirot "tiphereth", x 1100, y 550, title = map title, parent null, tag "", task false, date "".

Sefirot x,y positions:
kether: 1100,100 — chochma: 800,250 — binah: 1400,250
chesed: 800,450 — geburah: 1400,450 — tiphereth: 1100,550
netzach: 800,750 — hod: 1400,750 — yesod: 1100,900 — malkuth: 1100,1100

Return JSON array only. Start with [ end with ].';

        // Flow Chart and Mind Map need a plain hierarchy; the editor lays it out from there.
        if ( $layout !== 'tol' ) {
            $prompt = 'You are organizing notes into a clear hierarchy for a mind map.

Today is ' . $today . '. Use this as the reference for all dates. All dates must be on or after today.

Map title: ' . $title . '

Notes:
' . $notes . $answers_text . '

Group the notes into 3 to 7 main branches under one root card, with the details as cards beneath them. Keep the wording of the notes where you can. Use the answers to set Kanban tags based on urgency and priority, and add dates where they are mentioned.

' . $kanban . '

Return ONLY a JSON array of nodes. No explanation. No markdown.

Each node must have:
- id: short unique string like "n1"
- x: 0
- y: 0
- title: short topic title (under 6 words)
- subs: array of subtitle strings (key points, max 4)
- parent: the id of the card it belongs under
- sefirot: ""
- collapsed: false
- date: "YYYY-MM-DD" if date mentioned, otherwise ""
- tag: one of start/doing/sprint/review/done or ""
- task: true if the card is something to do, false if it is a heading or only information

Include the root card: id "c", title = map title, parent null, tag "", task false, date "".

Return JSON array only. Start with [ end with ].';
        }

        // Longer notes need longer answers, and the model can take a minute to write them
        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 120,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => json_encode([
                'model'      => 'claude-opus-4-6',
                'max_tokens' => 4000,
                'messages'   => [
                    [ 'role' => 'user', 'content' => $prompt ]
                ],
            ]),
        ]);

        $error = self::anthropic_error( $response );
        if ( $error ) return $error;

        $nodes = self::reply_json( $response );
        if ( ! is_array( $nodes ) ) {
            return new WP_Error( 'parse_error', 'The AI answer could not be read. Please try again.', [ 'status' => 500 ] );
        }
        NMM_Auth::ai_log( 'new map' );

        return rest_ensure_response( [ 'nodes' => self::limit_stages( $nodes ) ] );
    }

    private static function answers_text( $answers ) {
        $text = '';
        foreach ( (array) $answers as $qa ) {
            if ( ! is_array( $qa ) ) continue;
            $q = sanitize_text_field( $qa['question'] ?? '' );
            $a = sanitize_textarea_field( $qa['answer'] ?? '' );
            if ( $q && $a ) $text .= "Q: $q\nA: $a\n";
        }
        return $text ? "\n\nUser answers to clarifying questions:\n" . mb_substr( $text, 0, 6000 ) : '';
    }

    // ------------------------------------------------------------
    // REORGANIZE: rewrite an existing map the way the member asks. One AI action;
    // the map as it was is kept (nmm_before_reorganize) so it can be brought back.
    // ------------------------------------------------------------
    private static function reorganize_map( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_mindmap' || ! self::can_access( $post ) )
            return new WP_Error( 'not_found', 'Map not found', [ 'status' => 404 ] );
        if ( ! get_option( 'nmm_anthropic_key', '' ) )
            return new WP_Error( 'no_key', 'Anthropic API key not configured', [ 'status' => 500 ] );
        return $post;
    }

    // The saved cards, with only what the AI needs to see.
    private static function reorganize_cards( $post ) {
        $nodes = json_decode( get_post_meta( $post->ID, 'nmm_nodes', true ) ?: '[]', true );
        $cards = [];
        foreach ( (array) $nodes as $n ) {
            if ( ! is_array( $n ) || empty( $n['id'] ) ) continue;
            $c = [ 'id' => (string) $n['id'], 'title' => (string) ( $n['title'] ?? '' ), 'parent' => $n['parent'] ?? null ];
            foreach ( [ 'subs', 'date', 'end', 'tag', 'sefirot' ] as $k ) if ( ! empty( $n[ $k ] ) ) $c[ $k ] = $n[ $k ];
            if ( isset( $n['task'] ) ) $c['task'] = (bool) $n['task'];
            $cards[] = $c;
        }
        return $cards;
    }

    public static function reorganize_questions( $request ) {
        $post = self::reorganize_map( $request );
        if ( is_wp_error( $post ) ) return $post;
        $limit = NMM_Auth::ai_error();
        if ( $limit ) return $limit;
        $wish = sanitize_textarea_field( $request->get_json_params()['wish'] ?? '' );
        if ( ! $wish ) return new WP_Error( 'no_wish', 'Say what you would like changed first.', [ 'status' => 400 ] );
        if ( mb_strlen( $wish ) > 2000 ) return self::too_long();
        $titles = implode( "\n", array_slice( array_map( function ( $c ) { return '- ' . $c['title']; }, self::reorganize_cards( $post ) ), 0, 80 ) );
        $limit = NMM_Auth::ai_questions_error();
        if ( $limit ) return $limit;

        $prompt = 'A member wants to reorganize their mind map. Ask 2 or 3 short, friendly questions that will help you do exactly what they want. Ask only about what is unclear in their request: tone, how much text to keep, what matters most, what belongs together. Do not ask about anything they already said.

Map title: ' . $post->post_title . '

Some of the cards:
' . $titles . '

What they asked for:
' . $wish . '

Return ONLY a JSON array of question strings. No explanation. No markdown.';
        $response = self::claude( 'claude-haiku-4-5-20251001', $prompt, 400, 45 );
        $error = self::anthropic_error( $response );
        if ( $error ) return $error;
        $questions = self::reply_json( $response );
        return rest_ensure_response( [ 'questions' => is_array( $questions ) ? array_slice( array_values( array_filter( $questions, 'is_string' ) ), 0, 3 ) : [] ] );
    }

    public static function reorganize( $request ) {
        $post = self::reorganize_map( $request );
        if ( is_wp_error( $post ) ) return $post;
        $limit = NMM_Auth::ai_error();
        if ( $limit ) return $limit;
        $params = $request->get_json_params();
        $wish   = sanitize_textarea_field( $params['wish'] ?? '' );
        if ( ! $wish ) return new WP_Error( 'no_wish', 'Say what you would like changed first.', [ 'status' => 400 ] );
        if ( mb_strlen( $wish ) > 2000 ) return self::too_long();
        // The editor saves first and sends the revision it saw, so the AI works on what is on screen
        $rev = (int) get_post_meta( $post->ID, 'nmm_rev', true );
        if ( isset( $params['base_rev'] ) && (int) $params['base_rev'] !== $rev )
            return new WP_Error( 'conflict', 'This map was changed somewhere else. Reload it and try again.', [ 'status' => 409 ] );

        $settings = json_decode( get_post_meta( $post->ID, 'nmm_settings', true ) ?: '{}', true );
        $tol   = is_array( $settings ) && ( $settings['layout'] ?? '' ) === 'tol';
        $cards = self::reorganize_cards( $post );
        if ( ! $cards ) return new WP_Error( 'empty', 'This map has no cards to reorganize yet.', [ 'status' => 400 ] );
        $json = wp_json_encode( $cards, JSON_UNESCAPED_UNICODE );
        if ( strlen( $json ) > self::MAP_MAX )
            return new WP_Error( 'too_long', 'This map is too big to reorganize in one go (' . count( $cards ) . ' cards). Try it on a smaller map.', [ 'status' => 400 ] );

        $today  = wp_date( 'Y-m-d' );
        $prompt = 'You are reorganizing a member\'s existing mind map, the way they ask. Today is ' . $today . '.

Map title: ' . $post->post_title . '

What they asked for:
' . $wish . self::answers_text( $params['answers'] ?? [] ) . '

The map now, as JSON cards. parent is the id of the card it sits under; parent null is a root card (an island).
' . $json . '

Rules:
- Do what they asked. You may reword, shorten, regroup, merge, split or reorder cards.
- Keep their meaning. Do not invent facts, stories, people or tasks that are not in the map or their answers.
- When a card stays (even reworded or moved), keep its id. New cards get new ids like "r1", "r2". When cards merge, keep one of their ids.
- Keep each card\'s date, end, tag and task as they are, unless they asked to change them. tag is one of start/doing/sprint/review/done or "".
- Keep the main root card and its id. Keep other root cards unless they asked to change them.
- subs are short lines under the title, at most 4. Titles under 7 words.
' . ( $tol
    ? '- Give each card a Tree of Life position in sefirot: kether, chochma, binah, chesed, geburah, tiphereth, netzach, hod, yesod or malkuth. The main root card is tiphereth.'
    : '- sefirot: keep it as it is, or "" when the card has none.' ) . '

Return ONLY a JSON array of cards with: id, title, subs, parent, date, end, tag, task, sefirot. No explanation. No markdown. Start with [ end with ].';

        @set_time_limit( 200 );
        $max = min( 16000, max( 4000, (int) ( strlen( $json ) / 2 ) ) );
        $response = self::claude( 'claude-opus-4-6', $prompt, $max, 180 );
        $error = self::anthropic_error( $response );
        if ( $error ) return $error;
        $nodes = self::reply_json( $response );
        $nodes = is_array( $nodes ) ? self::clean_reorganized( $nodes ) : [];
        if ( ! $nodes ) return new WP_Error( 'parse_error', 'The AI answer could not be read, so your map was not changed. Please try again.', [ 'status' => 500 ] );

        // Count it, and keep the map as it was, only once the new version is ready
        NMM_Auth::ai_log( 'reorganize', $post->ID );
        update_post_meta( $post->ID, 'nmm_before_reorganize', wp_slash( wp_json_encode( [
            'at' => time(), 'by' => get_current_user_id(),
            'nodes' => json_decode( get_post_meta( $post->ID, 'nmm_nodes', true ) ?: '[]', true ),
        ] ) ) );
        return rest_ensure_response( [ 'nodes' => self::limit_stages( $nodes ), 'ai' => NMM_Auth::ai_status() ] );
    }

    // The map as it was before its last Reorganize ({at: 0} when there is none).
    public static function reorganize_before( $request ) {
        $post = get_post( $request['id'] );
        if ( ! $post || $post->post_type !== 'nmm_mindmap' || ! self::can_access( $post ) )
            return new WP_Error( 'not_found', 'Map not found', [ 'status' => 404 ] );
        $before = json_decode( get_post_meta( $post->ID, 'nmm_before_reorganize', true ) ?: 'null', true );
        if ( ! is_array( $before ) ) return rest_ensure_response( [ 'at' => 0 ] );
        $before['when'] = wp_date( 'F j, g:i a', (int) $before['at'] );
        return rest_ensure_response( $before );
    }

    // Plain, safe cards from the AI: text only, real dates, parents that exist and no loops.
    private static function clean_reorganized( $nodes ) {
        $out = []; $ids = [];
        foreach ( array_slice( $nodes, 0, 400 ) as $n ) {
            if ( ! is_array( $n ) ) continue;
            $id = substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $n['id'] ?? '' ) ), 0, 40 );
            if ( $id === '' || isset( $ids[ $id ] ) ) $id = 'r' . substr( md5( uniqid( '', true ) ), 0, 8 );
            $ids[ $id ] = true;
            $date = (string) ( $n['date'] ?? '' );
            $end  = (string) ( $n['end'] ?? '' );
            $out[] = [
                'id'      => $id,
                'title'   => sanitize_text_field( (string) ( $n['title'] ?? '' ) ) ?: 'Untitled',
                'subs'    => array_slice( array_values( array_filter( array_map( function ( $x ) { return is_scalar( $x ) ? sanitize_text_field( (string) $x ) : ''; }, (array) ( $n['subs'] ?? [] ) ) ) ), 0, 6 ),
                'parent'  => isset( $n['parent'] ) && is_scalar( $n['parent'] ) && $n['parent'] !== '' ? (string) $n['parent'] : null,
                'date'    => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '',
                'end'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ? $end : '',
                'tag'     => (string) ( $n['tag'] ?? '' ),
                'task'    => ! empty( $n['task'] ),
                'sefirot' => sanitize_key( (string) ( $n['sefirot'] ?? '' ) ),
            ];
        }
        $by = [];
        foreach ( $out as $i => $n ) $by[ $n['id'] ] = $i;
        foreach ( $out as $i => $n ) {
            if ( $n['parent'] === null ) continue;
            if ( ! isset( $by[ $n['parent'] ] ) || $n['parent'] === $n['id'] ) { $out[ $i ]['parent'] = null; continue; }
            // Walk up the parents; a loop makes this card a root
            $seen = [ $n['id'] => true ]; $p = $n['parent'];
            while ( $p !== null && isset( $by[ $p ] ) ) {
                if ( isset( $seen[ $p ] ) ) { $out[ $i ]['parent'] = null; break; }
                $seen[ $p ] = true; $p = $out[ $by[ $p ] ]['parent'];
            }
        }
        return $out;
    }

    /**
     * The prompt asks for small Kanban stages, and this holds the AI to it: the soonest
     * cards keep their stage and the rest wait in Triage. Matches LIMITS in kanban.js.
     */
    public static function limit_stages( $nodes ) {
        $limits = [ 'sprint' => 3, 'doing' => 5, 'start' => 5, 'review' => 5 ];
        $stages = [];
        foreach ( $nodes as $i => $n ) {
            if ( ! is_array( $n ) ) continue;
            $tag = strtolower( trim( (string) ( $n['tag'] ?? '' ) ) );
            if ( ! isset( $limits[ $tag ] ) && $tag !== 'done' ) $tag = '';
            $nodes[ $i ]['tag']  = $tag;
            $nodes[ $i ]['task'] = $tag !== '' || ! empty( $n['task'] );
            if ( $tag !== '' ) $stages[ $tag ][] = $i;
        }
        foreach ( $limits as $tag => $max ) {
            $list = $stages[ $tag ] ?? [];
            usort( $list, function ( $a, $b ) use ( $nodes ) {
                $da = $nodes[ $a ]['date'] ?? '';
                $db = $nodes[ $b ]['date'] ?? '';
                if ( $da !== $db ) {
                    if ( $da === '' ) return 1;
                    if ( $db === '' ) return -1;
                    return strcmp( $da, $db );
                }
                return $a - $b;
            } );
            foreach ( array_slice( $list, $max ) as $i ) $nodes[ $i ]['tag'] = '';
        }
        return $nodes;
    }

    private static function format_map( $post, $include_data = false ) {
        $map = [
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'modified'  => $post->post_modified,
            'created'   => $post->post_date,
            'rev'       => (int) get_post_meta( $post->ID, 'nmm_rev', true ),
            'category'  => get_post_meta( $post->ID, 'nmm_category', true ),
            'color'     => get_post_meta( $post->ID, 'nmm_color', true ) ?: '#0BBFBF',
        ];
        // What the current user can do, and who else is on the map
        $manage = self::can_manage( $post );
        $map['can_manage'] = $manage;
        $map['can_edit']   = self::can_access( $post );
        $map['mine']       = is_user_logged_in() && (int) $post->post_author === get_current_user_id();
        $map['featured']   = self::is_featured( $post );
        if ( $manage ) {
            $map['share_key'] = get_post_meta( $post->ID, 'nmm_share_key', true );
            $map['people']    = count( self::people( $post->ID ) );
        } elseif ( self::is_editor( $post ) ) {
            $owner = get_userdata( (int) $post->post_author );
            $map['shared_by'] = $owner ? $owner->display_name : 'Another member';
        }
        if ( $include_data ) {
            $map['nodes']    = json_decode( get_post_meta( $post->ID, 'nmm_nodes',    true ) ?: '[]', true );
            // Cast so empty settings go out as {} rather than [], which the editor can't add keys to
            $map['settings'] = (object) json_decode( get_post_meta( $post->ID, 'nmm_settings', true ) ?: '{}', true );
            $map['islands']  = json_decode( get_post_meta( $post->ID, 'nmm_islands',  true ) ?: '[]', true );
            $last = get_userdata( (int) get_post_meta( $post->ID, 'nmm_last_editor', true ) );
            $map['edited_by'] = $last ? $last->display_name : '';
        }
        return $map;
    }

    private static function format_island( $post, $include_data = false ) {
        $island = [ 'id' => $post->ID, 'title' => $post->post_title, 'modified' => $post->post_modified, 'color' => get_post_meta( $post->ID, 'nmm_color', true ) ?: '#5DCAA5' ];
        if ( $include_data ) $island['nodes'] = json_decode( get_post_meta( $post->ID, 'nmm_nodes', true ) ?: '[]', true );
        return $island;
    }
}
