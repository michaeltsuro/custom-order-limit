<?php
/**
 * Define the WP Admin Integration
 *
 * @package BetaDev\CustomOrderLimit
 */

namespace BetaDev\CustomOrderLimit;

class Admin
{

    public function __construct(public CustomOrderLimiter $limiter)
    {
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
        $basename = plugin_basename(dirname(__DIR__) . '/custom-order-limit.php');

        add_action('admin_notices', [$this, 'admin_notice']);
        add_filter('woocommerce_get_settings_pages', [$this, 'register_settings_page']);
        add_filter(sprintf('plugin_action_links_%s', $basename), [$this, 'action_links']);
        add_filter('woocommerce_debug_tools', [$this, 'debug_tools']);
        add_action('woocommerce_delete_shop_order_transients', [$this, 'reset_limiter_on_order_delete']);
        add_action('woocommerce_system_status_report', [$this, 'system_status_report']);

        // Add the custom order restrictions hook
        add_action('woocommerce_checkout_process', [$this, 'custom_order_restrictions']);
    }

    public function action_links(array $actions): array
    {
        array_unshift($actions, sprintf(
            '<a href="%s">%s</a>',
            $this->get_settings_url(),
            _x('Settings', 'plugin action link', 'custom-order-limit')
        ));

        return $actions;
    }

    public function register_settings_page(array $pages): array
    {
        $pages[] = new Settings($this->limiter);

        return $pages;
    }

    public function admin_notice(): void
    {
        $user_id = get_current_user_id();

        if ($user_id && $this->limiter->has_reached_limit_for_user($user_id)) {
            $next_interval = $this->limiter->get_next_interval_start();
            $message       = sprintf(
                __('You have reached your order limit for the current interval. You can place a new order after %s.', 'custom-order-limit'),
                $next_interval->format('Y-m-d H:i:s')
            );

            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
        }
    }

    public function debug_tools(array $tools): array
    {
        $tools['custom_order_limit'] = [
            'name' => __('Custom Order Limit', 'custom-order-limit'),
            'data' => [
                'Orders in current interval' => $this->limiter->get_order_count_in_current_interval(),
                'Next interval start'        => $this->limiter->get_next_interval_start()->format('Y-m-d H:i:s'),
                'Seconds until next interval' => $this->limiter->get_seconds_until_next_interval(),
            ],
        ];

        return $tools;
    }

    public function reset_limiter_on_order_delete(): void
    {
        $this->limiter->regenerate_transient();
    }

    public function system_status_report(): void
    {
        ?>
        <table class="wc_status_table widefat" cellspacing="0">
            <thead>
            <tr>
                <th colspan="3" data-export-label="Custom Order Limit">
                    <h2><?php esc_html_e('Custom Order Limit', 'custom-order-limit'); ?><?php wp_kses_post(wc_help_tip(__('Current configuration for Custom Order Limit.', 'custom-order-limit'))); ?></h2>
                </th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td data-export-label="Enabled"><?php esc_html_e('Order Limiting Enabled', 'custom-order-limit'); ?>:</td>
                <td class="help"><?php wp_kses_post(wc_help_tip(__('Is order limiting currently enabled on this store?', 'custom-order-limit'))); ?></td>
                <td><?php echo $this->limiter->is_enabled() ? esc_html__('Yes') : esc_html__('No'); ?></td>
            </tr>
            <tr>
                <td data-export-label="Remaining orders"><?php esc_html_e('Remaining Orders in Current Interval', 'custom-order-limit'); ?>:</td>
                <td class="help"><?php wp_kses_post(wc_help_tip(__('How many more orders will be accepted in the current interval?', 'custom-order-limit'))); ?></td>
                <td><?php echo esc_html($this->limiter->get_remaining_orders_for_user()); ?></td>
            </tr>
            <tr>
                <td data-export-label="Interval"><?php esc_html_e('Order Limit Reset Interval', 'custom-order-limit'); ?>:</td>
                <td class="help"><?php wp_kses_post(wc_help_tip(__('How often is the order limit reset?', 'custom-order-limit'))); ?></td>
                <td><?php echo esc_html($this->limiter->get_interval()); ?></td>
            </tr>
            <tr>
                <td data-export-label="Interval start"><?php esc_html_e('Current Interval Start', 'custom-order-limit'); ?>:</td>
                <td class="help"><?php wp_kses_post(wc_help_tip(__('When the current interval began.', 'custom-order-limit'))); ?></td>
                <td><?php echo esc_html($this->limiter->get_interval_start()->format('F j, Y g:i A')); ?></td>
            </tr>
            <tr>
                <td data-export-label="Interval resets"><?php esc_html_e('Next Interval Starts', 'custom-order-limit'); ?>:</td>
                <td class="help"><?php wp_kses_post(wc_help_tip(__('When the next interval will begin.', 'custom-order-limit'))); ?></td>
                <td><?php echo esc_html($this->limiter->get_next_interval_start()->format('F j, Y g:i A')); ?></td>
            </tr>
            </tbody>
        </table>
        <?php
    }









}