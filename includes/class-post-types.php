<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NMM_Post_Types {

    public static function register() {
        // Life Map post type (the slug stays nmm_mindmap: stored maps and code use it)
        register_post_type( 'nmm_mindmap', [
            'labels' => [
                'name'          => 'Life Maps',
                'singular_name' => 'Life Map',
                'menu_name'     => 'Life Maps',
                'all_items'     => 'All Life Maps',
                'add_new'       => 'Add New Life Map',
                'add_new_item'  => 'Add New Life Map',
                'edit_item'     => 'Edit Life Map',
                'new_item'      => 'New Life Map',
                'view_item'     => 'View Life Map',
                'search_items'  => 'Search Life Maps',
                'not_found'     => 'No Life Maps found',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => true,
            'show_in_rest' => false, // the app uses nmm/v1, which checks the owner
            'supports'     => [ 'title', 'custom-fields' ],
            'menu_icon'    => 'dashicons-networking',
            'capability_type' => 'post',
        ]);

        // Island post type (reusable node clusters)
        register_post_type( 'nmm_island', [
            'labels' => [
                'name'          => 'Map Islands',
                'singular_name' => 'Map Island',
                'add_new_item'  => 'Add New Island',
                'edit_item'     => 'Edit Island',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'edit.php?post_type=nmm_mindmap',
            'show_in_rest' => false, // the app uses nmm/v1, which checks the owner
            'supports'     => [ 'title', 'custom-fields' ],
            'menu_icon'    => 'dashicons-networking',
        ]);

        // Register meta fields
        $meta_fields = [
            'nmm_nodes'      => 'string',
            'nmm_settings'   => 'string',
            'nmm_share_key'  => 'string',
            'nmm_category'   => 'string',
            'nmm_color'      => 'string',
            'nmm_islands'    => 'string', // JSON array of linked island IDs
        ];

        foreach ( $meta_fields as $key => $type ) {
            register_post_meta( 'nmm_mindmap', $key, [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => $type,
                'auth_callback' => function() { return current_user_can( 'edit_posts' ); }
            ]);
            register_post_meta( 'nmm_island', $key, [
                'show_in_rest' => true,
                'single'       => true,
                'type'         => $type,
                'auth_callback' => function() { return current_user_can( 'edit_posts' ); }
            ]);
        }
    }
}
