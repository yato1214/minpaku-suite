<?php
/**
 * Property Custom Post Type Registration
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register property custom post type
 */
function mcs_register_property_cpt(): void {
    $labels = [
        'name'                  => __('Properties', 'minpaku-suite'),
        'singular_name'         => __('Property', 'minpaku-suite'),
        'menu_name'             => __('Properties', 'minpaku-suite'),
        'name_admin_bar'        => __('Property', 'minpaku-suite'),
        'archives'              => __('Property Archives', 'minpaku-suite'),
        'attributes'            => __('Property Attributes', 'minpaku-suite'),
        'parent_item_colon'     => __('Parent Property:', 'minpaku-suite'),
        'all_items'             => __('All Properties', 'minpaku-suite'),
        'add_new_item'          => __('Add New Property', 'minpaku-suite'),
        'add_new'               => __('Add New', 'minpaku-suite'),
        'new_item'              => __('New Property', 'minpaku-suite'),
        'edit_item'             => __('Edit Property', 'minpaku-suite'),
        'update_item'           => __('Update Property', 'minpaku-suite'),
        'view_item'             => __('View Property', 'minpaku-suite'),
        'view_items'            => __('View Properties', 'minpaku-suite'),
        'search_items'          => __('Search Properties', 'minpaku-suite'),
        'not_found'             => __('Not found', 'minpaku-suite'),
        'not_found_in_trash'    => __('Not found in Trash', 'minpaku-suite'),
        'featured_image'        => __('Featured Image', 'minpaku-suite'),
        'set_featured_image'    => __('Set featured image', 'minpaku-suite'),
        'remove_featured_image' => __('Remove featured image', 'minpaku-suite'),
        'use_featured_image'    => __('Use as featured image', 'minpaku-suite'),
        'insert_into_item'      => __('Insert into property', 'minpaku-suite'),
        'uploaded_to_this_item' => __('Uploaded to this property', 'minpaku-suite'),
        'items_list'            => __('Properties list', 'minpaku-suite'),
        'items_list_navigation' => __('Properties list navigation', 'minpaku-suite'),
        'filter_items_list'     => __('Filter properties list', 'minpaku-suite'),
    ];

    $args = [
        'label'                 => __('Properties', 'minpaku-suite'),
        'description'           => __('Minpaku properties management', 'minpaku-suite'),
        'labels'                => $labels,
        'supports'              => ['title', 'editor', 'thumbnail'],
        'taxonomies'            => [],
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => false,
        'menu_position'         => 20,
        'menu_icon'             => 'dashicons-admin-home',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
        'rest_base'             => 'properties',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
        'rewrite'               => ['slug' => 'stays'],
    ];

    register_post_type('mcs_property', $args);
}

/**
 * Register booking custom post type
 */
function mcs_register_booking_cpt(): void {
    $labels = [
        'name'                  => __('Bookings', 'minpaku-suite'),
        'singular_name'         => __('Booking', 'minpaku-suite'),
        'menu_name'             => __('Bookings', 'minpaku-suite'),
        'name_admin_bar'        => __('Booking', 'minpaku-suite'),
        'archives'              => __('Booking Archives', 'minpaku-suite'),
        'attributes'            => __('Booking Attributes', 'minpaku-suite'),
        'parent_item_colon'     => __('Parent Booking:', 'minpaku-suite'),
        'all_items'             => __('All Bookings', 'minpaku-suite'),
        'add_new_item'          => __('Add New Booking', 'minpaku-suite'),
        'add_new'               => __('Add New', 'minpaku-suite'),
        'new_item'              => __('New Booking', 'minpaku-suite'),
        'edit_item'             => __('Edit Booking', 'minpaku-suite'),
        'update_item'           => __('Update Booking', 'minpaku-suite'),
        'view_item'             => __('View Booking', 'minpaku-suite'),
        'view_items'            => __('View Bookings', 'minpaku-suite'),
        'search_items'          => __('Search Bookings', 'minpaku-suite'),
        'not_found'             => __('Not found', 'minpaku-suite'),
        'not_found_in_trash'    => __('Not found in Trash', 'minpaku-suite'),
        'insert_into_item'      => __('Insert into booking', 'minpaku-suite'),
        'uploaded_to_this_item' => __('Uploaded to this booking', 'minpaku-suite'),
        'items_list'            => __('Bookings list', 'minpaku-suite'),
        'items_list_navigation' => __('Bookings list navigation', 'minpaku-suite'),
        'filter_items_list'     => __('Filter bookings list', 'minpaku-suite'),
    ];

    $args = [
        'label'                 => __('Bookings', 'minpaku-suite'),
        'description'           => __('Booking management', 'minpaku-suite'),
        'labels'                => $labels,
        'supports'              => ['title'],
        'taxonomies'            => [],
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => false,
        'menu_position'         => null,
        'show_in_admin_bar'     => false,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
        'rest_base'             => 'bookings',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
    ];

    register_post_type('mcs_booking', $args);
}

// Register the custom post types (called by bootstrap)
mcs_register_property_cpt();
mcs_register_booking_cpt();