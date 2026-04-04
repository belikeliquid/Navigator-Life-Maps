<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NMM_Auth {

    public static function register() {
        add_action( 'rest_api_init', [ __CLASS__, 'routes' ] );
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

        $params   = $request->get_json_params();
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

        // Verify Cloudflare Turnstile if configured
        $ts_secret = get_option( 'nmm_turnstile_secret_key', '' );
        if ( $ts_secret ) {
            $ts_token = sanitize_text_field( $params['turnstile_token'] ?? '' );
            if ( ! $ts_token ) {
                return rest_ensure_response([ 'success' => false, 'message' => 'Security check required. Please complete the verification.' ]);
            }
            $ts_response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'body' => [ 'secret' => $ts_secret, 'response' => $ts_token ]
            ]);
            if ( ! is_wp_error( $ts_response ) ) {
                $ts_data = json_decode( wp_remote_retrieve_body( $ts_response ), true );
                if ( empty( $ts_data['success'] ) ) {
                    return rest_ensure_response([ 'success' => false, 'message' => 'Security verification failed. Please try again.' ]);
                }
            }
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
        ]);
    }
}
