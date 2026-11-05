<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NMM_Auth {

    // Maps a person can own. Admins and editors have no limit.
    const LIMIT_USER = 3;
    const LIMIT_HOST = 5;

    public static function register() {
        add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
        self::ensure_roles();
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

        return rest_ensure_response([
            'success'  => true,
            'user_id'  => $user->ID,
            'username' => $user->user_login,
            'email'    => $user->user_email,
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
        $invite = trim( (string) get_option( 'nmm_invite_code', '' ) );
        if ( $invite && ! hash_equals( strtolower( $invite ), strtolower( trim( (string) ( $params['invite_code'] ?? '' ) ) ) ) ) {
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

        return rest_ensure_response([ 'success' => true, 'user_id' => $user_id, 'username' => $username ]);
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
        ]);
    }
}
