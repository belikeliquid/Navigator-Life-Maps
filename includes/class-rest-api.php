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
        return current_user_can( 'nmm_edit_maps' ) || current_user_can( 'edit_posts' ) || current_user_can( 'manage_options' );
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

    public static function ai_questions( $request ) {
        $params = $request->get_json_params();
        $title  = sanitize_text_field( $params['title'] ?? '' );
        $notes  = sanitize_textarea_field( $params['notes'] ?? '' );

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
            'timeout' => 20,
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

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', 'AI request failed', [ 'status' => 500 ] );
        }

        $body  = json_decode( wp_remote_retrieve_body( $response ), true );
        $text  = $body['content'][0]['text'] ?? '[]';
        $text  = preg_replace('/```json|```/', '', $text);
        $questions = json_decode( trim($text), true );

        if ( ! is_array( $questions ) ) {
            return new WP_Error( 'parse_error', 'Could not parse questions', [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'questions' => $questions ] );
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
        $params = $request->get_json_params();
        $title  = sanitize_text_field( $params['title'] ?? 'My Map' );
        $notes   = sanitize_textarea_field( $params['notes'] ?? '' );
        $answers = $params['answers'] ?? [];
        $mode    = sanitize_text_field( $params['mode'] ?? 'ai' );

        if ( ! $notes ) {
            return new WP_Error( 'no_notes', 'No notes provided', [ 'status' => 400 ] );
        }

        $api_key = get_option( 'nmm_anthropic_key', '' );
        if ( ! $api_key ) {
            return new WP_Error( 'no_key', 'Anthropic API key not configured', [ 'status' => 500 ] );
        }

        $answers_text = '';
        if ( ! empty( $answers ) ) {
            $answers_text = "

User answers to clarifying questions:
";
            foreach ( $answers as $qa ) {
                $q = sanitize_text_field( $qa['question'] ?? '' );
                $a = sanitize_text_field( $qa['answer'] ?? '' );
                if ( $q && $a ) $answers_text .= "Q: $q
A: $a
";
            }
        }

        $today  = date('Y-m-d');
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

Kanban tag guide — assign based on urgency from answers:
- sprint: must happen immediately, highest urgency
- doing: currently in progress
- start: needs to begin soon
- review: waiting on something or needs checking
- done: completed

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

Include center node: id "c", sefirot "tiphereth", x 1100, y 550, title = map title, parent null, tag "", date "".

Sefirot x,y positions:
kether: 1100,100 — chochma: 800,250 — binah: 1400,250
chesed: 800,450 — geburah: 1400,450 — tiphereth: 1100,550
netzach: 800,750 — hod: 1400,750 — yesod: 1100,900 — malkuth: 1100,1100

Return JSON array only. Start with [ end with ].';

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 30,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => json_encode([
                'model'      => 'claude-opus-4-6',
                'max_tokens' => 2000,
                'messages'   => [
                    [ 'role' => 'user', 'content' => $prompt ]
                ],
            ]),
        ]);

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', 'AI request failed', [ 'status' => 500 ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $text = $body['content'][0]['text'] ?? '';

        // Clean up any markdown code fences
        $text = preg_replace('/```json|```/', '', $text );
        $text = trim( $text );

        // Extract JSON array if wrapped in extra text
        if ( strpos( $text, '[' ) !== false ) {
            $start = strpos( $text, '[' );
            $end   = strrpos( $text, ']' );
            if ( $end !== false ) {
                $text = substr( $text, $start, $end - $start + 1 );
            }
        }

        $nodes = json_decode( $text, true );
        if ( ! is_array( $nodes ) ) {
            return new WP_Error( 'parse_error', 'Could not parse AI response: ' . substr( $text, 0, 200 ), [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'nodes' => $nodes ] );
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
