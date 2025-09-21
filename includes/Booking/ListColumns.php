<?php
/**
 * Booking List Columns
 *
 * @package MinpakuSuite
 */

namespace MinpakuSuite\Booking;

if (!defined('ABSPATH')) {
    exit;
}

class ListColumns
{
    public static function init(): void
    {
        add_filter('manage_mcs_booking_posts_columns', [__CLASS__, 'add_columns']);
        add_action('manage_mcs_booking_posts_custom_column', [__CLASS__, 'render_column'], 10, 2);
        add_filter('manage_edit-mcs_booking_sortable_columns', [__CLASS__, 'sortable_columns']);
        add_action('pre_get_posts', [__CLASS__, 'handle_sorting']);
        add_action('restrict_manage_posts', [__CLASS__, 'add_property_filter']);
        add_action('pre_get_posts', [__CLASS__, 'handle_property_filter']);
        add_action('admin_footer', [__CLASS__, 'add_booking_link_script']);
    }

    public static function add_columns($columns): array
    {
        $new_columns = [];

        foreach ($columns as $key => $title) {
            if ($key === 'title') {
                $new_columns['title'] = $title;
                $new_columns['mcs_property'] = __('Property', 'minpaku-suite');
                $new_columns['mcs_dates'] = __('Check-in/out', 'minpaku-suite');
                $new_columns['mcs_nights'] = __('Nights', 'minpaku-suite');
                $new_columns['mcs_guests'] = __('Guests', 'minpaku-suite');
                $new_columns['mcs_status'] = __('Status', 'minpaku-suite');
            } elseif ($key !== 'date') {
                $new_columns[$key] = $title;
            }
        }

        $new_columns['date'] = $columns['date'];

        return $new_columns;
    }

    public static function render_column($column, $post_id): void
    {
        switch ($column) {
            case 'mcs_property':
                $property_id = get_post_meta($post_id, '_mcs_property_id', true);
                if ($property_id) {
                    $property = get_post($property_id);
                    if ($property) {
                        echo '<a href="' . get_edit_post_link($property_id) . '">' . esc_html($property->post_title) . '</a>';
                    } else {
                        echo '<span style="color: #d63384;">' . __('Property not found', 'minpaku-suite') . '</span>';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'mcs_dates':
                $checkin = get_post_meta($post_id, '_mcs_checkin', true);
                $checkout = get_post_meta($post_id, '_mcs_checkout', true);
                if ($checkin && $checkout) {
                    echo '<strong>' . esc_html($checkin) . '</strong><br>';
                    echo '<span style="color: #666;">to ' . esc_html($checkout) . '</span>';
                } else {
                    echo '—';
                }
                break;

            case 'mcs_nights':
                $checkin = get_post_meta($post_id, '_mcs_checkin', true);
                $checkout = get_post_meta($post_id, '_mcs_checkout', true);
                if ($checkin && $checkout) {
                    $checkin_date = \DateTime::createFromFormat('Y-m-d', $checkin);
                    $checkout_date = \DateTime::createFromFormat('Y-m-d', $checkout);
                    if ($checkin_date && $checkout_date) {
                        $nights = $checkout_date->diff($checkin_date)->days;
                        echo esc_html($nights);
                    } else {
                        echo '—';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'mcs_guests':
                $guests = get_post_meta($post_id, '_mcs_guests', true);
                echo $guests ? esc_html($guests) : '—';
                break;

            case 'mcs_status':
                $status = get_post_meta($post_id, '_mcs_status', true);
                $status_labels = [
                    'CONFIRMED' => __('Confirmed', 'minpaku-suite'),
                    'PENDING' => __('Pending', 'minpaku-suite'),
                    'CANCELLED' => __('Cancelled', 'minpaku-suite')
                ];
                $status_colors = [
                    'CONFIRMED' => '#28a745',
                    'PENDING' => '#ffc107',
                    'CANCELLED' => '#6c757d'
                ];

                if ($status && isset($status_labels[$status])) {
                    $color = $status_colors[$status] ?? '#6c757d';
                    echo '<span style="color: ' . esc_attr($color) . '; font-weight: bold;">';
                    echo esc_html($status_labels[$status]);
                    echo '</span>';
                } else {
                    echo '—';
                }
                break;
        }
    }

    public static function sortable_columns($columns): array
    {
        $columns['mcs_property'] = 'mcs_property';
        $columns['mcs_dates'] = 'mcs_checkin';
        $columns['mcs_status'] = 'mcs_status';
        return $columns;
    }

    public static function handle_sorting($query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $orderby = $query->get('orderby');

        switch ($orderby) {
            case 'mcs_property':
                $query->set('meta_key', '_mcs_property_id');
                $query->set('orderby', 'meta_value_num');
                break;

            case 'mcs_checkin':
                $query->set('meta_key', '_mcs_checkin');
                $query->set('orderby', 'meta_value');
                break;

            case 'mcs_status':
                $query->set('meta_key', '_mcs_status');
                $query->set('orderby', 'meta_value');
                break;
        }
    }

    public static function add_property_filter(): void
    {
        global $typenow;

        if ($typenow === 'mcs_booking') {
            $selected = $_GET['mcs_property_filter'] ?? '';

            $properties = get_posts([
                'post_type' => 'mcs_property',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC'
            ]);

            echo '<select name="mcs_property_filter" id="mcs_property_filter">';
            echo '<option value="">' . __('All Properties', 'minpaku-suite') . '</option>';

            foreach ($properties as $property) {
                echo '<option value="' . esc_attr($property->ID) . '" ' . selected($selected, $property->ID, false) . '>';
                echo esc_html($property->post_title);
                echo '</option>';
            }

            echo '</select>';
        }
    }

    public static function handle_property_filter($query): void
    {
        global $pagenow, $typenow;

        if (is_admin() && $pagenow === 'edit.php' && $typenow === 'mcs_booking' && !empty($_GET['mcs_property_filter'])) {
            $query->query_vars['meta_key'] = '_mcs_property_id';
            $query->query_vars['meta_value'] = intval($_GET['mcs_property_filter']);
        }
    }

    public static function add_booking_link_script(): void
    {
        global $pagenow, $typenow;

        if ($pagenow === 'post.php' && $typenow === 'mcs_property') {
            ?>
            <script>
            jQuery(document).ready(function($) {
                var postId = $('#post_ID').val();
                if (postId) {
                    var link = '<a href="<?php echo admin_url('post-new.php?post_type=mcs_booking&property_id='); ?>' + postId + '" class="button button-secondary" style="margin-left: 10px;">';
                    link += '<?php echo esc_js(__('Add Booking for this Property', 'minpaku-suite')); ?>';
                    link += '</a>';
                    $('.page-title-action').after(link);
                }
            });
            </script>
            <?php
        }
    }
}