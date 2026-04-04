<?php
/**
 * Minimal GitHub Plugin Update Checker
 * Checks a GitHub releases API for newer versions of the plugin.
 * Compatible with WordPress automatic update system.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class NMM_GitHub_Updater {

    private $plugin_slug;
    private $plugin_file;
    private $github_user;
    private $github_repo;
    private $current_version;
    private $transient_key;

    public function __construct( $plugin_file, $github_user, $github_repo, $current_version ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_slug     = plugin_basename( $plugin_file );
        $this->github_user     = $github_user;
        $this->github_repo     = $github_repo;
        $this->current_version = $current_version;
        $this->transient_key   = 'nmm_github_update_' . md5( $github_user . $github_repo );

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 10, 3 );
        add_filter( 'upgrader_post_install',                 [ $this, 'after_install' ], 10, 3 );
    }

    private function get_release_info() {
        $cached = get_transient( $this->transient_key );
        if ( $cached ) return $cached;

        $url      = "https://api.github.com/repos/{$this->github_user}/{$this->github_repo}/releases/latest";
        $response = wp_remote_get( $url, [
            'headers' => [ 'Accept' => 'application/vnd.github.v3+json', 'User-Agent' => 'WordPress/' . get_bloginfo('version') ],
            'timeout' => 10,
        ]);

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return false;

        $data = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! $data || empty( $data->tag_name ) ) return false;

        // Find zip asset
        $zip_url = '';
        if ( ! empty( $data->assets ) ) {
            foreach ( $data->assets as $asset ) {
                if ( strpos( $asset->name, '.zip' ) !== false ) {
                    $zip_url = $asset->browser_download_url;
                    break;
                }
            }
        }
        // Fallback to source zip
        if ( ! $zip_url ) {
            $zip_url = "https://github.com/{$this->github_user}/{$this->github_repo}/archive/refs/tags/{$data->tag_name}.zip";
        }

        $release = (object) [
            'version'     => ltrim( $data->tag_name, 'v' ),
            'zip_url'     => $zip_url,
            'description' => $data->body ?? '',
            'url'         => $data->html_url,
        ];

        set_transient( $this->transient_key, $release, 6 * HOUR_IN_SECONDS );
        return $release;
    }

    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $release = $this->get_release_info();
        if ( ! $release ) return $transient;

        if ( version_compare( $release->version, $this->current_version, '>' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) [
                'slug'        => dirname( $this->plugin_slug ),
                'plugin'      => $this->plugin_slug,
                'new_version' => $release->version,
                'url'         => $release->url,
                'package'     => $release->zip_url,
            ];
        }

        return $transient;
    }

    public function plugin_info( $res, $action, $args ) {
        if ( 'plugin_information' !== $action ) return $res;
        if ( dirname( $this->plugin_slug ) !== $args->slug ) return $res;

        $release = $this->get_release_info();
        if ( ! $release ) return $res;

        return (object) [
            'name'          => 'Navigator Mind Maps',
            'slug'          => dirname( $this->plugin_slug ),
            'version'       => $release->version,
            'author'        => 'Liquid Media / Navigator Leadership Group',
            'homepage'      => "https://github.com/{$this->github_user}/{$this->github_repo}",
            'download_link' => $release->zip_url,
            'sections'      => [ 'description' => nl2br( esc_html( $release->description ) ) ],
        ];
    }

    public function after_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;
        // Rename extracted folder to match plugin slug
        $plugin_folder = WP_PLUGIN_DIR . '/' . dirname( $this->plugin_slug );
        if ( $wp_filesystem->exists( $result['destination'] ) ) {
            $wp_filesystem->move( $result['destination'], $plugin_folder );
        }
        $result['destination'] = $plugin_folder;
        activate_plugin( $this->plugin_slug );
        return $result;
    }

    // Force check — clears cache
    public static function clear_cache( $user, $repo ) {
        delete_transient( 'nmm_github_update_' . md5( $user . $repo ) );
    }
}
