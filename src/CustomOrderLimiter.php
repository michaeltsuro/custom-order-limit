<?php
/**
 * CustomOrderLimiter
 *
 * @package BetaDev\CustomOrderLimit
 */

namespace BetaDev\CustomOrderLimit;

use DateTimeImmutable;
use WC_Order_Query;

/**
 * Class CustomOrderLimiter
 *
 * @package BetaDev\CustomOrderLimit
 */

class CustomOrderLimiter
{
    private $settings;
    private $init_action_added = false;
    const OPTION_KEY = 'limit_orders';
    const TRANSIENT_NAME = 'limit_orders_order_count';

    public function __construct(public ?DateTimeImmutable $now = null)
    {
        if (null === $this->now) {
            $this->now = new DateTimeImmutable();
        }
        $this->enable_error_reporting();
    }

    protected function enable_error_reporting(): void
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }

    public function init(): void
    {
        add_action('woocommerce_new_order', [$this, 'regenerate_transient']);
        add_action('update_option_' . self::OPTION_KEY, [$this, 'reset_limiter_on_update_for_user'], 10, 2);
        add_action('woocommerce_checkout_process', [$this, 'custom_order_restrictions']);
    }

    public function is_enabled(): bool
    {
        return (bool) get_setting( 'enabled', false);
    }

    public function get_setting(string $key, mixed $default = null): mixed
    {
        if (null === $this->settings) {
            $this->settings = get_option(self::OPTION_KEY, []);
        }
        return $this->settings[$key] ?? $default;
    }

    public function get_seconds_until_next_interval(): int
    {
        $next_interval = $this->get_next_interval_start();
        return $next_interval->getTimestamp() - $this->now->getTimestamp();
    }

    public function get_interval_start(): DateTimeImmutable {
        $interval = $this->get_setting( 'interval' );
        $start    = $this->now;

        switch ( $interval ) {
            case 'hourly':
                // Start at the top of the current hour.
                $start = $start->setTime( (int) $start->format( 'G' ), 0, 0 );
                break;

            case 'daily':
                // Start at midnight.
                $start = $start->setTime( 0, 0, 0 );
                break;

            case 'weekly':
                $start_of_week = (int) get_option( 'week_starts_on' );
                $current_dow   = (int) $start->format( 'w' );
                $diff          = $current_dow - $start_of_week;

                // Compensate for values outside of 0-6.
                if ( 0 > $diff ) {
                    $diff += 7;
                }

                // A difference of 0 means today is the start; anything else and we need to change $start.
                if ( 0 !== $diff ) {
                    $start = $start->sub( new \DateInterval( 'P' . $diff . 'D' ) );
                }

                $start = $start->setTime( 0, 0, 0 );
                break;

            case 'monthly':
                $start = $start->setDate( (int) $start->format( 'Y' ), (int) $start->format( 'm' ), 1 )
                    ->setTime( 0, 0, 0 );
                break;
        }

        /**
         * Filter the DateTime object representing the start of the current interval.
         *
         * @param DateTimeImmutable $start    The DateTimeImmutable representing the start of the current interval.
         * @param string             $interval The type of interval being calculated.
         */
        return apply_filters( 'limit_orders_interval_start', $start, $interval );
    }

    public function get_next_interval_start(): DateTimeImmutable
    {
        $interval = $this->get_setting('interval');
        $current = $this->get_interval_start();
        $start = clone $current;

        switch ($interval) {
            case 'hourly':
                $start = $start->add(new \DateInterval('PT1H'));
                break;

            case 'daily':
                $start = $start->add(new \DateInterval('P1D'));
                break;

            case 'weekly':
                $start = $start->add(new \DateInterval('P7D'));
                break;

            case 'monthly':
                $start = $start->add(new \DateInterval('P1M'));
                break;
        }

        return $start; // Add this line to return the calculated DateTimeImmutable object.
    }

    public function has_reached_limit_for_user(int $user_id): bool
    {
        $limit = $this->count_user_orders_from_database($user_id);
        $count = $this->get_user_order_limit_from_settings($user_id);
        return $count >= $limit;
    }

    public function count_user_orders_from_database(int $user_id): int
    {
        $query = new WC_Order_Query([
            'customer' => $user_id,
            'limit' => -1,
            'status' => ['completed', 'processing', 'on-hold', 'pending', 'failed'],
        ]);
        return $query->get_total();
    }

    public function get_user_order_limit_from_settings(int $user_id): int
    {
        $limit = $this->get_setting('limit');
        $user_limit = get_user_meta($user_id, 'order_limit', true);
        return $user_limit ?: $limit;
    }

    public function disable_ordering_for_user(int $user_id): void
    {
        $this->reset_limiter_for_user($user_id);
        $this->update_user_meta($user_id);
    }

    public function reset_limiter_for_user(int $user_id): void
    {
        delete_user_meta($user_id, 'order_limit');
    }

    public function update_user_meta(int $user_id): void
    {
        update_user_meta($user_id, 'order_limit', $this->get_user_order_limit_from_settings($user_id));
    }

    public function regenerate_transient(): void
    {
        delete_transient(self::TRANSIENT_NAME);
    }

    public function reset_limiter_on_update_for_user(string $option, mixed $old_value): void
    {
        if ('limit_orders' === $option) {
            $users = get_users(['fields' => 'ID']);
            foreach ($users as $user_id) {
                $this->reset_limiter_for_user($user_id);
            }
        }
    }

    public function custom_order_restrictions(): void
    {
        if ($this->is_enabled()) {
            $user_id = get_current_user_id();
            if ($user_id && $this->has_reached_limit_for_user($user_id)) {
                wc_add_notice(__('You have reached your order limit.', 'limit-orders'), 'error');
                wc_add_notice($this->get_setting('customer_notice'), 'error');
                wc()->cart->empty_cart();
            }
        }
    }

    public function has_orders_in_current_interval(): bool
    {
        $count = $this->get_orders_in_current_interval();
        return $count > 0;
    }














}