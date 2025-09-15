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
        'name'                  => __('施設', 'minpaku-suite'),
        'singular_name'         => __('施設', 'minpaku-suite'),
        'menu_name'             => __('施設', 'minpaku-suite'),
        'name_admin_bar'        => __('施設', 'minpaku-suite'),
        'archives'              => __('施設アーカイブ', 'minpaku-suite'),
        'attributes'            => __('施設属性', 'minpaku-suite'),
        'parent_item_colon'     => __('親施設:', 'minpaku-suite'),
        'all_items'             => __('すべての施設', 'minpaku-suite'),
        'add_new_item'          => __('新しい施設を追加', 'minpaku-suite'),
        'add_new'               => __('新規追加', 'minpaku-suite'),
        'new_item'              => __('新しい施設', 'minpaku-suite'),
        'edit_item'             => __('施設を編集', 'minpaku-suite'),
        'update_item'           => __('施設を更新', 'minpaku-suite'),
        'view_item'             => __('施設を表示', 'minpaku-suite'),
        'view_items'            => __('施設を表示', 'minpaku-suite'),
        'search_items'          => __('施設を検索', 'minpaku-suite'),
        'not_found'             => __('施設が見つかりません', 'minpaku-suite'),
        'not_found_in_trash'    => __('ゴミ箱に施設が見つかりません', 'minpaku-suite'),
        'featured_image'        => __('アイキャッチ画像', 'minpaku-suite'),
        'set_featured_image'    => __('アイキャッチ画像を設定', 'minpaku-suite'),
        'remove_featured_image' => __('アイキャッチ画像を削除', 'minpaku-suite'),
        'use_featured_image'    => __('アイキャッチ画像として使用', 'minpaku-suite'),
        'insert_into_item'      => __('施設に挿入', 'minpaku-suite'),
        'uploaded_to_this_item' => __('この施設にアップロード', 'minpaku-suite'),
        'items_list'            => __('施設リスト', 'minpaku-suite'),
        'items_list_navigation' => __('施設リストナビゲーション', 'minpaku-suite'),
        'filter_items_list'     => __('施設リストをフィルタ', 'minpaku-suite'),
    ];

    $args = [
        'label'                 => __('施設', 'minpaku-suite'),
        'description'           => __('宿泊施設の管理', 'minpaku-suite'),
        'labels'                => $labels,
        'supports'              => ['title', 'editor', 'thumbnail', 'comments'],
        'taxonomies'            => [],
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
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
    ];

    register_post_type('property', $args);
}

// Register the custom post type on init
add_action('init', 'mcs_register_property_cpt');