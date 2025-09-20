<?php
/**
 * Booking Repository
 * Handles persistence of Booking entities using WordPress posts
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/Booking.php';

class BookingRepository {

    /**
     * Post type for bookings
     */
    const POST_TYPE = 'minpaku_booking';

    /**
     * Meta key prefix
     */
    const META_PREFIX = '_minpaku_booking_';

    /**
     * Constructor - register post type
     */
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
    }

    /**
     * Register the booking post type
     */
    public function register_post_type() {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Bookings', 'minpaku-suite'),
                'singular_name' => __('Booking', 'minpaku-suite'),
                'menu_name' => __('Bookings', 'minpaku-suite'),
                'all_items' => __('All Bookings', 'minpaku-suite'),
                'add_new' => __('Add New', 'minpaku-suite'),
                'add_new_item' => __('Add New Booking', 'minpaku-suite'),
                'edit_item' => __('Edit Booking', 'minpaku-suite'),
                'new_item' => __('New Booking', 'minpaku-suite'),
                'view_item' => __('View Booking', 'minpaku-suite'),
                'search_items' => __('Search Bookings', 'minpaku-suite'),
                'not_found' => __('No bookings found', 'minpaku-suite'),
                'not_found_in_trash' => __('No bookings found in trash', 'minpaku-suite'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=property',
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'manage_minpaku',
                'edit_posts' => 'manage_minpaku',
                'edit_others_posts' => 'manage_minpaku',
                'publish_posts' => 'manage_minpaku',
                'read_private_posts' => 'manage_minpaku',
                'delete_posts' => 'manage_minpaku',
                'delete_private_posts' => 'manage_minpaku',
                'delete_published_posts' => 'manage_minpaku',
                'delete_others_posts' => 'manage_minpaku',
                'edit_private_posts' => 'manage_minpaku',
                'edit_published_posts' => 'manage_minpaku',
            ],
            'map_meta_cap' => true,
            'hierarchical' => false,
            'supports' => ['title', 'custom-fields'],
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
        ]);
    }

    /**
     * Find booking by ID
     *
     * @param int $id Booking ID
     * @return Booking|null
     */
    public function findById($id) {
        $post = get_post($id);

        if (!$post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        return $this->postToBooking($post);
    }

    /**
     * Save booking
     *
     * @param Booking $booking
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function save(Booking $booking) {
        $post_data = $this->bookingToPostData($booking);

        if ($booking->getId()) {
            // Update existing booking
            $post_data['ID'] = $booking->getId();
            $result = wp_update_post($post_data, true);

            if (is_wp_error($result)) {
                return $result;
            }

            $post_id = $result;
        } else {
            // Create new booking
            $result = wp_insert_post($post_data, true);

            if (is_wp_error($result)) {
                return $result;
            }

            $post_id = $result;
            $booking->setId($post_id);
        }

        // Save meta data
        $this->saveBookingMeta($post_id, $booking);

        return true;
    }

    /**
     * Delete booking
     *
     * @param int $id Booking ID
     * @param bool $force_delete Whether to force delete (bypass trash)
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete($id, $force_delete = false) {
        $booking = $this->findById($id);
        if (!$booking) {
            return new WP_Error('booking_not_found', __('Booking not found', 'minpaku-suite'));
        }

        // Only allow deletion of draft bookings unless force delete
        if (!$force_delete && $booking->getState() !== Booking::STATE_DRAFT) {
            return new WP_Error(
                'cannot_delete_booking',
                __('Only draft bookings can be deleted', 'minpaku-suite')
            );
        }

        $result = wp_delete_post($id, $force_delete);
        return $result !== false;
    }

    /**
     * List bookings by property
     *
     * @param int $property_id Property ID
     * @param array $args Query arguments
     * @return array Array of Booking objects
     */
    public function listByProperty($property_id, $args = []) {
        $defaults = [
            'state' => null,
            'date_from' => null,
            'date_to' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $meta_query = [
            [
                'key' => self::META_PREFIX . 'property_id',
                'value' => $property_id,
                'compare' => '='
            ]
        ];

        if ($args['state']) {
            $meta_query[] = [
                'key' => self::META_PREFIX . 'state',
                'value' => $args['state'],
                'compare' => '='
            ];
        }

        if ($args['date_from'] || $args['date_to']) {
            $date_query = [];

            if ($args['date_from']) {
                $date_query[] = [
                    'key' => self::META_PREFIX . 'checkin',
                    'value' => $args['date_from'],
                    'compare' => '>=',
                    'type' => 'DATE'
                ];
            }

            if ($args['date_to']) {
                $date_query[] = [
                    'key' => self::META_PREFIX . 'checkout',
                    'value' => $args['date_to'],
                    'compare' => '<=',
                    'type' => 'DATE'
                ];
            }

            if (count($date_query) > 1) {
                $date_query['relation'] = 'AND';
            }

            $meta_query = array_merge($meta_query, $date_query);
        }

        if (count($meta_query) > 1) {
            $meta_query['relation'] = 'AND';
        }

        $query_args = [
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'private'],
            'meta_query' => $meta_query,
            'posts_per_page' => $args['limit'],
            'offset' => $args['offset'],
            'orderby' => $this->mapOrderBy($args['orderby']),
            'order' => strtoupper($args['order'])
        ];

        $posts = get_posts($query_args);
        $bookings = [];

        foreach ($posts as $post) {
            $bookings[] = $this->postToBooking($post);
        }

        return $bookings;
    }

    /**
     * Count bookings by property
     *
     * @param int $property_id Property ID
     * @param array $filters Filters (state, date_from, date_to)
     * @return int
     */
    public function countByProperty($property_id, $filters = []) {
        $args = array_merge($filters, ['limit' => -1]);
        $bookings = $this->listByProperty($property_id, $args);
        return count($bookings);
    }

    /**
     * Find overlapping bookings
     *
     * @param int $property_id Property ID
     * @param string $checkin Check-in date
     * @param string $checkout Check-out date
     * @param int|null $exclude_id Booking ID to exclude from search
     * @return array Array of overlapping Booking objects
     */
    public function findOverlapping($property_id, $checkin, $checkout, $exclude_id = null) {
        $meta_query = [
            'relation' => 'AND',
            [
                'key' => self::META_PREFIX . 'property_id',
                'value' => $property_id,
                'compare' => '='
            ],
            [
                'key' => self::META_PREFIX . 'state',
                'value' => [Booking::STATE_CANCELLED],
                'compare' => 'NOT IN'
            ],
            [
                'relation' => 'OR',
                // Booking starts before checkin and ends after checkin
                [
                    'relation' => 'AND',
                    [
                        'key' => self::META_PREFIX . 'checkin',
                        'value' => $checkin,
                        'compare' => '<',
                        'type' => 'DATE'
                    ],
                    [
                        'key' => self::META_PREFIX . 'checkout',
                        'value' => $checkin,
                        'compare' => '>',
                        'type' => 'DATE'
                    ]
                ],
                // Booking starts before checkout and ends after checkout
                [
                    'relation' => 'AND',
                    [
                        'key' => self::META_PREFIX . 'checkin',
                        'value' => $checkout,
                        'compare' => '<',
                        'type' => 'DATE'
                    ],
                    [
                        'key' => self::META_PREFIX . 'checkout',
                        'value' => $checkout,
                        'compare' => '>',
                        'type' => 'DATE'
                    ]
                ],
                // Booking starts within the period
                [
                    'relation' => 'AND',
                    [
                        'key' => self::META_PREFIX . 'checkin',
                        'value' => $checkin,
                        'compare' => '>=',
                        'type' => 'DATE'
                    ],
                    [
                        'key' => self::META_PREFIX . 'checkin',
                        'value' => $checkout,
                        'compare' => '<',
                        'type' => 'DATE'
                    ]
                ]
            ]
        ];

        $query_args = [
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'private'],
            'meta_query' => $meta_query,
            'posts_per_page' => -1
        ];

        if ($exclude_id) {
            $query_args['post__not_in'] = [$exclude_id];
        }

        $posts = get_posts($query_args);
        $bookings = [];

        foreach ($posts as $post) {
            $bookings[] = $this->postToBooking($post);
        }

        return $bookings;
    }

    /**
     * Convert WP_Post to Booking object
     *
     * @param WP_Post $post
     * @return Booking
     */
    private function postToBooking(WP_Post $post) {
        $meta_data = [];
        $post_meta = get_post_meta($post->ID);

        // Extract booking-specific meta
        foreach ($post_meta as $key => $values) {
            if (strpos($key, self::META_PREFIX) === 0) {
                $clean_key = str_replace(self::META_PREFIX, '', $key);
                $meta_data[$clean_key] = maybe_unserialize($values[0]);
            }
        }

        // Extract additional meta data
        $additional_meta = maybe_unserialize(get_post_meta($post->ID, self::META_PREFIX . 'meta_data', true)) ?: [];

        return new Booking([
            'id' => $post->ID,
            'property_id' => $meta_data['property_id'] ?? null,
            'checkin' => $meta_data['checkin'] ?? null,
            'checkout' => $meta_data['checkout'] ?? null,
            'adults' => $meta_data['adults'] ?? 1,
            'children' => $meta_data['children'] ?? 0,
            'state' => $meta_data['state'] ?? Booking::STATE_DRAFT,
            'created_at' => $post->post_date,
            'updated_at' => $post->post_modified,
            'meta_data' => $additional_meta
        ]);
    }

    /**
     * Convert Booking to post data array
     *
     * @param Booking $booking
     * @return array
     */
    private function bookingToPostData(Booking $booking) {
        $title = sprintf(
            __('Booking #%s - %s (%s to %s)', 'minpaku-suite'),
            $booking->getId() ?: 'NEW',
            get_the_title($booking->getPropertyId()) ?: sprintf(__('Property #%d', 'minpaku-suite'), $booking->getPropertyId()),
            $booking->getCheckin(),
            $booking->getCheckout()
        );

        $post_data = [
            'post_type' => self::POST_TYPE,
            'post_title' => $title,
            'post_status' => $this->getPostStatusFromState($booking->getState()),
            'post_content' => '', // Could store additional details here
        ];

        if ($booking->getCreatedAt()) {
            $post_data['post_date'] = $booking->getCreatedAt();
        }

        if ($booking->getUpdatedAt()) {
            $post_data['post_modified'] = $booking->getUpdatedAt();
        }

        return $post_data;
    }

    /**
     * Save booking meta data
     *
     * @param int $post_id
     * @param Booking $booking
     */
    private function saveBookingMeta($post_id, Booking $booking) {
        $meta_fields = [
            'property_id' => $booking->getPropertyId(),
            'checkin' => $booking->getCheckin(),
            'checkout' => $booking->getCheckout(),
            'adults' => $booking->getAdults(),
            'children' => $booking->getChildren(),
            'state' => $booking->getState(),
        ];

        foreach ($meta_fields as $key => $value) {
            update_post_meta($post_id, self::META_PREFIX . $key, $value);
        }

        // Save additional meta data
        update_post_meta($post_id, self::META_PREFIX . 'meta_data', $booking->getMetaData());
    }

    /**
     * Map orderby parameter to WordPress orderby
     *
     * @param string $orderby
     * @return string
     */
    private function mapOrderBy($orderby) {
        $mapping = [
            'created_at' => 'date',
            'updated_at' => 'modified',
            'checkin' => 'meta_value',
            'checkout' => 'meta_value',
            'state' => 'meta_value'
        ];

        return $mapping[$orderby] ?? 'date';
    }

    /**
     * Get WordPress post status from booking state
     *
     * @param string $state
     * @return string
     */
    private function getPostStatusFromState($state) {
        $mapping = [
            Booking::STATE_DRAFT => 'draft',
            Booking::STATE_PENDING => 'private',
            Booking::STATE_CONFIRMED => 'publish',
            Booking::STATE_CANCELLED => 'private',
            Booking::STATE_COMPLETED => 'private'
        ];

        return $mapping[$state] ?? 'draft';
    }

    /**
     * Get booking state display label
     *
     * @param string $state
     * @return string
     */
    public static function getStateLabel($state) {
        $labels = [
            Booking::STATE_DRAFT => __('Draft', 'minpaku-suite'),
            Booking::STATE_PENDING => __('Pending', 'minpaku-suite'),
            Booking::STATE_CONFIRMED => __('Confirmed', 'minpaku-suite'),
            Booking::STATE_CANCELLED => __('Cancelled', 'minpaku-suite'),
            Booking::STATE_COMPLETED => __('Completed', 'minpaku-suite')
        ];

        return $labels[$state] ?? $state;
    }

    /**
     * Get booking state CSS class for styling
     *
     * @param string $state
     * @return string
     */
    public static function getStateClass($state) {
        $classes = [
            Booking::STATE_DRAFT => 'booking-state-draft',
            Booking::STATE_PENDING => 'booking-state-pending',
            Booking::STATE_CONFIRMED => 'booking-state-confirmed',
            Booking::STATE_CANCELLED => 'booking-state-cancelled',
            Booking::STATE_COMPLETED => 'booking-state-completed'
        ];

        return $classes[$state] ?? 'booking-state-unknown';
    }
}