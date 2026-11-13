<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NMM_Auth {

    // Maps a person can own. Admins and editors have no limit.
    const LIMIT_USER = 3;
    const LIMIT_HOST = 5;

    public static function register() {
        add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
        add_filter( 'user_has_cap', [ __CLASS__, 'members_make_maps' ], 10, 4 );
        self::ensure_roles();
    }

    // Every signed-in member can make maps, however they joined (Life Maps signup or My Navigator).
    // The map limit and the AI allowance still apply; they keep the paid AI in check.
    public static function members_make_maps( $allcaps, $caps, $args, $user ) {
        if ( $user instanceof WP_User && $user->ID ) $allcaps['nmm_edit_maps'] = true;
        return $allcaps;
    }

    // Plugin updates don't run the activation hook, so the role is added here when missing.
    public static function ensure_roles() {
        if ( ! get_role( 'nmm_host' ) ) {
            add_role( 'nmm_host', 'Guide / Host', [ 'read' => true, 'nmm_edit_maps' => true, 'nmm_host' => true ] );
        }
    }

    // Guide / Host role, or a WordPress Author. Everyone else who signs up is a standard user.
    public static function is_host( $user ) {
        return $user->has_cap( 'nmm_host' ) || in_array( 'author', (array) $user->roles, true );
    }

    // How many maps this person may own; 0 means no limit.
    public static function map_limit( $user = null ) {
        $user = $user ?: wp_get_current_user();
        if ( $user->has_cap( 'manage_options' ) || $user->has_cap( 'edit_others_posts' ) ) return 0;
        return self::is_host( $user ) ? self::LIMIT_HOST : self::LIMIT_USER;
    }

    public static function map_count( $user = null ) {
        $user = $user ?: wp_get_current_user();
        return (int) count_user_posts( $user->ID, 'nmm_mindmap' );
    }

    // A WP_Error when the current user can't add another map, otherwise null.
    public static function limit_error() {
        $limit = self::map_limit();
        if ( $limit && self::map_count() >= $limit ) {
            return new WP_Error( 'nmm_map_limit', 'You have reached your limit of ' . $limit . ' maps. Delete a map to make room for a new one.', [ 'status' => 403 ] );
        }
        return null;
    }

    // ------------------------------------------------------------
    // AI allowance: AI actions (a map built from notes, or a Reorganize) use the site's
    // paid Anthropic key, so each person gets a few per rolling period. Admins have no
    // limit, but every action is logged so unusual use shows in Settings.
    // ------------------------------------------------------------
    const AI_MEMBER = [ 'limit' => 3, 'days' => 30, 'label' => 'month' ];
    const AI_HOST   = [ 'limit' => 3, 'days' => 7,  'label' => 'week' ];
    const AI_QUESTIONS_PER_DAY = 15; // the cheap question step, so it can't be looped either
    const AI_LOG_DAYS = 60;

    // [limit, days, label] for this person, or null when there is no limit (admins).
    public static function ai_allowance( $user = null ) {
        $user = $user ?: wp_get_current_user();
        if ( $user->has_cap( 'manage_options' ) ) return null;
        return ( self::is_host( $user ) || $user->has_cap( 'edit_others_posts' ) ) ? self::AI_HOST : self::AI_MEMBER;
    }

    // Times of this person's AI actions inside the window, oldest first.
    private static function ai_times( $user_id, $days ) {
        $since = time() - $days * DAY_IN_SECONDS;
        $times = array_filter( array_map( 'intval', (array) get_user_meta( $user_id, 'nmm_ai_log', true ) ), function ( $t ) use ( $since ) { return $t > $since; } );
        sort( $times );
        return array_values( $times );
    }

    // What the app shows: used, limit (0 = none), period label, and when the next one frees up.
    public static function ai_status( $user = null ) {
        $user  = $user ?: wp_get_current_user();
        $allow = self::ai_allowance( $user );
        if ( ! $allow ) return [ 'used' => count( self::ai_times( $user->ID, 7 ) ), 'limit' => 0, 'period' => 'week', 'next' => '' ];
        $times = self::ai_times( $user->ID, $allow['days'] );
        $next  = count( $times ) >= $allow['limit'] ? wp_date( 'F j', $times[ count( $times ) - $allow['limit'] ] + $allow['days'] * DAY_IN_SECONDS ) : '';
        return [ 'used' => count( $times ), 'limit' => $allow['limit'], 'period' => $allow['label'], 'next' => $next ];
    }

    // A WP_Error when the current user has no AI actions left, otherwise null.
    public static function ai_error() {
        $s = self::ai_status();
        if ( $s['limit'] && $s['used'] >= $s['limit'] ) {
            return new WP_Error( 'nmm_ai_limit', 'You have used your ' . $s['limit'] . ' AI actions for this ' . $s['period'] . '. Your next one is available on ' . $s['next'] . '.', [ 'status' => 429 ] );
        }
        return null;
    }

    // The question step is cheap but still paid: a daily cap per person (none for admins).
    public static function ai_questions_error() {
        if ( current_user_can( 'manage_options' ) ) return null;
        $key = 'nmm_aiq_' . get_current_user_id() . '_' . gmdate( 'Ymd' );
        $n   = (int) get_transient( $key );
        if ( $n >= self::AI_QUESTIONS_PER_DAY ) return new WP_Error( 'nmm_ai_limit', 'That is enough AI questions for today. Please try again tomorrow.', [ 'status' => 429 ] );
        set_transient( $key, $n + 1, DAY_IN_SECONDS );
        self::ai_log( 'questions', 0, false );
        return null;
    }

    // Records an AI call: against the person's allowance ($counts) and in the site log admins see.
    public static function ai_log( $kind, $map_id = 0, $counts = true ) {
        $uid = get_current_user_id();
        if ( $counts ) {
            $times   = self::ai_times( $uid, self::AI_LOG_DAYS );
            $times[] = time();
            update_user_meta( $uid, 'nmm_ai_log', $times );
        }
        $since = time() - self::AI_LOG_DAYS * DAY_IN_SECONDS;
        $log   = array_values( array_filter( (array) get_option( 'nmm_ai_usage', [] ), function ( $e ) use ( $since ) { return is_array( $e ) && (int) ( $e['t'] ?? 0 ) > $since; } ) );
        $log[] = [ 't' => time(), 'u' => $uid, 'k' => $kind, 'm' => (int) $map_id ];
        update_option( 'nmm_ai_usage', array_slice( $log, -5000 ), false );
    }

    // Site log entries from the last $days days.
    public static function ai_usage( $days = 7 ) {
        $since = time() - $days * DAY_IN_SECONDS;
        return array_values( array_filter( (array) get_option( 'nmm_ai_usage', [] ), function ( $e ) use ( $since ) { return is_array( $e ) && (int) ( $e['t'] ?? 0 ) > $since; } ) );
    }

    // Checks the Turnstile token. Returns true, or the message to show. If Cloudflare can't
    // be reached the attempt is refused: a bot could otherwise get through during an outage.
    private static function verify_turnstile( $params ) {
        $secret = get_option( 'nmm_turnstile_secret_key', '' );
        if ( ! $secret ) return true;
        $token = sanitize_text_field( $params['turnstile_token'] ?? '' );
        if ( ! $token ) return 'Security check required. Please complete the verification.';
        $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'timeout' => 10,
            'body'    => [ 'secret' => $secret, 'response' => $token ],
        ]);
        if ( is_wp_error( $response ) ) return 'We could not run the security check just now. Please try again in a moment.';
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return empty( $data['success'] ) ? 'Security verification failed. Please try again.' : true;
    }

    public static function routes() {
        $ns = 'nmm/v1';
        register_rest_route( $ns, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'login' ],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route( $ns, '/auth/register', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'register_user' ],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route( $ns, '/auth/me', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'me' ],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    public static function login( $request ) {
        $params   = $request->get_json_params();
        $username = sanitize_text_field( $params['username'] ?? '' );
        $password = $params['password'] ?? '';

        if ( ! $username || ! $password ) {
            return rest_ensure_response([ 'success' => false, 'message' => 'Username and password required.' ]);
        }

        $check = self::verify_turnstile( $params );
        if ( true !== $check ) {
            return rest_ensure_response([ 'success' => false, 'message' => $check ]);
        }

        $user = wp_authenticate( $username, $password );

        if ( is_wp_error( $user ) ) {
            return rest_ensure_response([ 'success' => false, 'message' => 'Invalid username or password.' ]);
        }

        // Set auth cookie
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );

        // Logging in from an invitation link opens the shared map
        $map_id = 0;
        if ( ! empty( $params['invite'] ) ) {
            $r = NMM_REST_API::redeem_invite( (string) $params['invite'], $user->ID );
            if ( ! is_wp_error( $r ) ) $map_id = $r;
        }

        return rest_ensure_response([
            'success'  => true,
            'user_id'  => $user->ID,
            'username' => $user->user_login,
            'email'    => $user->user_email,
            'map_id'   => $map_id,
        ]);
    }

    public static function register_user( $request ) {
        // Check if registration is open
        if ( ! get_option( 'users_can_register' ) ) {
            return rest_ensure_response([ 'success' => false, 'message' => 'User registration is currently closed.' ]);
        }

        $params = $request->get_json_params();

        // Security check and invitation first, so bots can't probe which usernames and emails exist
        $check = self::verify_turnstile( $params );
        if ( true !== $check ) {
            return rest_ensure_response([ 'success' => false, 'message' => $check ]);
        }
        // A member's invitation link stands in for the invitation code
        $invited = ! empty( $params['invite'] ) && NMM_REST_API::invite_is_valid( (string) $params['invite'] );
        $invite  = trim( (string) get_option( 'nmm_invite_code', '' ) );
        if ( $invite && ! $invited && ! hash_equals( strtolower( $invite ), strtolower( trim( (string) ( $params['invite_code'] ?? '' ) ) ) ) ) {
            return rest_ensure_response([ 'success' => false, 'message' => 'That invitation code is not right. Signup is by invitation for now.' ]);
        }

        $username = sanitize_user( $params['username'] ?? '' );
        $email    = sanitize_email( $params['email'] ?? '' );
        $password = $params['password'] ?? '';

        if ( ! $username || ! $email || ! $password ) {
            return rest_ensure_response([ 'success' => false, 'message' => 'All fields are required.' ]);
        }
        if ( ! is_email( $email ) ) {
            return rest_ensure_response([ 'success' => false, 'message' => 'Please enter a valid email address.' ]);
        }
        if ( username_exists( $username ) ) {
            return rest_ensure_response([ 'success' => false, 'message' => 'That username is already taken.' ]);
        }
        if ( email_exists( $email ) ) {
            return rest_ensure_response([ 'success' => false, 'message' => 'An account with that email already exists.' ]);
        }
        if ( strlen( $password ) < 8 ) {
            return rest_ensure_response([ 'success' => false, 'message' => 'Password must be at least 8 characters.' ]);
        }

        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            return rest_ensure_response([ 'success' => false, 'message' => $user_id->get_error_message() ]);
        }

        // Grant nmm_edit_maps capability — lets subscribers create/edit maps
        // without giving them broader WP editor/author permissions
        $user = new WP_User( $user_id );
        $user->add_cap( 'nmm_edit_maps' );

        // Auto login after registration
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );

        $map_id = 0;
        if ( $invited ) {
            $r = NMM_REST_API::redeem_invite( (string) $params['invite'], $user_id );
            if ( ! is_wp_error( $r ) ) $map_id = $r;
        }

        return rest_ensure_response([ 'success' => true, 'user_id' => $user_id, 'username' => $username, 'map_id' => $map_id ]);
    }

    public static function me( $request ) {
        $user = wp_get_current_user();
        return rest_ensure_response([
            'id'       => $user->ID,
            'username' => $user->user_login,
            'email'    => $user->user_email,
            'display'  => $user->display_name,
            'maps'     => self::map_count( $user ),
            'limit'    => self::map_limit( $user ),
            'ai'       => self::ai_status( $user ),
        ]);
    }
}
