<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class NMM_Post_Types {

    public static function register() {
        // Mind Map post type
        register_post_type( 'nmm_mindmap', [
            'labels' => [
                'name'          => 'Mind Maps',
                'singular_name' => 'Mind Map',
                'add_new_item'  => 'Add New Mind Map',
                'edit_item'     => 'Edit Mind Map',
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
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
            'show_in_rest' => true,
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
