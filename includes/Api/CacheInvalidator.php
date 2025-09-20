<?php
/**
 * Cache Invalidator
 * Handles cache invalidation for API responses based on data changes
 *
 * @package MinpakuSuite
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/ResponseCache.php';

class CacheInvalidator {

    /**
     * Response cache instance
     */
    private $cache;

    /**
     * Constructor
     */
    public function __construct() {
        $this->cache = new ResponseCache();
        $this->initHooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks() {
        // Property updates
        add_action('save_post', [$this, 'onPropertySave'], 10, 2);
        add_action('delete_post', [$this, 'onPropertyDelete']);

        // Booking state changes
        add_action('minpaku_booking_state_changed', [$this, 'onBookingStateChange'], 10, 3);

        // iCal import/sync
        add_action('minpaku_ical_imported', [$this, 'onIcalImported'], 10, 2);
        add_action('minpaku_ical_sync_completed', [$this, 'onIcalSyncCompleted']);

        // Rate and pricing changes
        add_action('updated_post_meta', [$this, 'onPriceMetaUpdated'], 10, 4);

        // General property meta changes that affect availability/quotes
        add_action('updated_post_meta', [$this, 'onPropertyMetaUpdated'], 10, 4);
    }

    /**
     * Handle property save/update
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function onPropertySave($post_id, $post) {
        if ($post->post_type !== 'property') {
            return;
        }

        // Clear all cache entries for this property
        $this->invalidatePropertyCache($post_id);

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('DEBUG', 'API cache invalidated for property save', [
                'property_id' => $post_id,
                'property_title' => $post->post_title
            ]);
        }
    }

    /**
     * Handle property deletion
     *
     * @param int $post_id Post ID
     */
    public function onPropertyDelete($post_id) {
        if (get_post_type($post_id) !== 'property') {
            return;
        }

        // Clear all cache entries for this property
        $this->invalidatePropertyCache($post_id);

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('DEBUG', 'API cache invalidated for property deletion', [
                'property_id' => $post_id
            ]);
        }
    }

    /**
     * Handle booking state changes
     *
     * @param object $booking Booking object
     * @param string $old_state Previous state
     * @param string $new_state New state
     */
    public function onBookingStateChange($booking, $old_state, $new_state) {
        if (!isset($booking->property_id)) {
            return;
        }

        // Clear availability cache for the affected property and date range
        $this->invalidateAvailabilityCache($booking->property_id, $booking->checkin_date, $booking->checkout_date);

        // Clear quote cache for this property (dates may now be unavailable)
        $this->invalidateQuoteCache($booking->property_id);

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('DEBUG', 'API cache invalidated for booking state change', [
                'booking_id' => $booking->id ?? 'unknown',
                'property_id' => $booking->property_id,
                'old_state' => $old_state,
                'new_state' => $new_state,
                'date_range' => ($booking->checkin_date ?? '') . ':' . ($booking->checkout_date ?? '')
            ]);
        }
    }

    /**
     * Handle iCal import completion
     *
     * @param int $property_id Property ID
     * @param array $import_result Import results
     */
    public function onIcalImported($property_id, $import_result) {
        // Clear availability cache for this property
        $this->invalidateAvailabilityCache($property_id);

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('DEBUG', 'API cache invalidated for iCal import', [
                'property_id' => $property_id,
                'events_imported' => count($import_result['events'] ?? [])
            ]);
        }
    }

    /**
     * Handle iCal sync completion
     *
     * @param array $sync_results Sync results for all properties
     */
    public function onIcalSyncCompleted($sync_results) {
        foreach ($sync_results as $property_id => $result) {
            if ($result['updated'] || $result['added'] || $result['removed']) {
                $this->invalidateAvailabilityCache($property_id);
            }
        }

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('DEBUG', 'API cache invalidated for iCal sync', [
                'properties_updated' => count(array_filter($sync_results, function($r) {
                    return $r['updated'] || $r['added'] || $r['removed'];
                }))
            ]);
        }
    }

    /**
     * Handle price meta updates
     *
     * @param int $meta_id Meta ID
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @param mixed $meta_value Meta value
     */
    public function onPriceMetaUpdated($meta_id, $post_id, $meta_key, $meta_value) {
        if (get_post_type($post_id) !== 'property') {
            return;
        }

        $price_related_keys = [
            'base_price',
            'cleaning_fee',
            'extra_adult_fee',
            'extra_child_fee',
            'tax_rate',
            'seasonal_rates'
        ];

        if (in_array($meta_key, $price_related_keys)) {
            // Clear quote cache for this property
            $this->invalidateQuoteCache($post_id);

            if (class_exists('MCS_Logger')) {
                MCS_Logger::log('DEBUG', 'API cache invalidated for price meta update', [
                    'property_id' => $post_id,
                    'meta_key' => $meta_key
                ]);
            }
        }
    }

    /**
     * Handle property meta updates that affect availability/quotes
     *
     * @param int $meta_id Meta ID
     * @param int $post_id Post ID
     * @param string $meta_key Meta key
     * @param mixed $meta_value Meta value
     */
    public function onPropertyMetaUpdated($meta_id, $post_id, $meta_key, $meta_value) {
        if (get_post_type($post_id) !== 'property') {
            return;
        }

        $availability_keys = [
            'min_stay',
            'max_stay',
            'advance_booking_days',
            'instant_booking',
            'availability_rules'
        ];

        $quote_keys = [
            'guest_capacity',
            'included_adults',
            'cancellation_policy',
            'house_rules'
        ];

        if (in_array($meta_key, $availability_keys)) {
            $this->invalidateAvailabilityCache($post_id);
        }

        if (in_array($meta_key, $quote_keys)) {
            $this->invalidateQuoteCache($post_id);
        }
    }

    /**
     * Invalidate all cache entries for a property
     *
     * @param int $property_id Property ID
     */
    public function invalidatePropertyCache($property_id) {
        // Clear availability cache
        $this->invalidateAvailabilityCache($property_id);

        // Clear quote cache
        $this->invalidateQuoteCache($property_id);
    }

    /**
     * Invalidate availability cache for a property
     *
     * @param int $property_id Property ID
     * @param string|null $start_date Optional start date filter
     * @param string|null $end_date Optional end date filter
     */
    public function invalidateAvailabilityCache($property_id, $start_date = null, $end_date = null) {
        // Pattern to match availability cache keys for this property
        $patterns = [
            "availability:property:{$property_id}:*",
            "availability:property:*,{$property_id}*", // Multi-property requests
            "availability:property:{$property_id},*" // Multi-property requests
        ];

        $total_cleared = 0;
        foreach ($patterns as $pattern) {
            $cleared = $this->cache->forget($pattern);
            $total_cleared += $cleared;
        }

        return $total_cleared;
    }

    /**
     * Invalidate quote cache for a property
     *
     * @param int $property_id Property ID
     * @param string|null $start_date Optional start date filter
     * @param string|null $end_date Optional end date filter
     */
    public function invalidateQuoteCache($property_id, $start_date = null, $end_date = null) {
        // Pattern to match quote cache keys for this property
        $pattern = "quote:property:{$property_id}:*";

        return $this->cache->forget($pattern);
    }

    /**
     * Invalidate cache by date range
     *
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     */
    public function invalidateDateRange($start_date, $end_date) {
        // This is more complex as it requires checking overlapping date ranges
        // For now, we'll implement a simplified version

        $patterns = [
            "*:range:{$start_date}:*",
            "*:range:*:{$end_date}",
            "*:dates:{$start_date}:*",
            "*:dates:*:{$end_date}"
        ];

        $total_cleared = 0;
        foreach ($patterns as $pattern) {
            $cleared = $this->cache->forget($pattern);
            $total_cleared += $cleared;
        }

        return $total_cleared;
    }

    /**
     * Clear all API cache
     */
    public function clearAllCache() {
        return $this->cache->clear();
    }

    /**
     * Clear cache by type
     *
     * @param string $type Cache type (availability, quote, webhook)
     */
    public function clearCacheByType($type) {
        return $this->cache->clear($type);
    }

    /**
     * Manual cache invalidation (for admin use)
     *
     * @param array $options Invalidation options
     */
    public function manualInvalidation($options = []) {
        $cleared_count = 0;

        if (isset($options['property_ids'])) {
            foreach ($options['property_ids'] as $property_id) {
                $cleared_count += $this->invalidatePropertyCache($property_id);
            }
        }

        if (isset($options['date_range'])) {
            $cleared_count += $this->invalidateDateRange(
                $options['date_range']['start'],
                $options['date_range']['end']
            );
        }

        if (isset($options['types'])) {
            foreach ($options['types'] as $type) {
                $cleared_count += $this->clearCacheByType($type);
            }
        }

        if (empty($options) || (isset($options['clear_all']) && $options['clear_all'])) {
            $cleared_count += $this->clearAllCache();
        }

        if (class_exists('MCS_Logger')) {
            MCS_Logger::log('INFO', 'Manual API cache invalidation', [
                'options' => $options,
                'cleared_count' => $cleared_count
            ]);
        }

        return $cleared_count;
    }
}