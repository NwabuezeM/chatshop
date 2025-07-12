<?php

/**
 * Payment Gateway Factory
 *
 * @package ChatShop
 * @subpackage Components/Payment
 * @since 1.0.0
 */

namespace ChatShop\Components\Payment;

use ChatShop\Includes\ChatShop_Logger;
use ChatShop\Includes\Abstracts\ChatShop_Payment_Gateway;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Payment Gateway Factory Class
 *
 * Implements the Factory pattern for creating payment gateway instances
 *
 * @since 1.0.0
 */
class ChatShop_Payment_Factory
{

    /**
     * Singleton instance
     *
     * @var ChatShop_Payment_Factory
     */
    private static $instance = null;

    /**
     * Gateway instances cache
     *
     * @var array
     */
    private $gateway_instances = array();

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    private $logger;

    /**
     * Private constructor
     */
    private function __construct()
    {
        $this->logger = new ChatShop_Logger();
    }

    /**
     * Get singleton instance
     *
     * @return ChatShop_Payment_Factory
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create gateway instance
     *
     * @param string $gateway_id Gateway identifier
     * @param array  $args       Optional arguments
     * @return ChatShop_Payment_Gateway|null
     */
    public function create_gateway($gateway_id, $args = array())
    {
        // Check cache first
        if (isset($this->gateway_instances[$gateway_id]) && empty($args)) {
            return $this->gateway_instances[$gateway_id];
        }

        // Get registered gateways
        $registry = ChatShop_Payment_Registry::get_instance();
        $gateway_class = $registry->get_gateway_class($gateway_id);

        if (!$gateway_class) {
            $this->logger->log(
                sprintf('Gateway class not found for ID: %s', $gateway_id),
                'error',
                'payment'
            );
            return null;
        }

        // Validate class exists
        if (!class_exists($gateway_class)) {
            $this->logger->log(
                sprintf('Gateway class does not exist: %s', $gateway_class),
                'error',
                'payment'
            );
            return null;
        }

        // Validate inheritance
        if (!is_subclass_of($gateway_class, ChatShop_Payment_Gateway::class)) {
            $this->logger->log(
                sprintf('Gateway class must extend ChatShop_Payment_Gateway: %s', $gateway_class),
                'error',
                'payment'
            );
            return null;
        }

        try {
            // Create instance
            $gateway = new $gateway_class($args);

            // Cache if no custom args
            if (empty($args)) {
                $this->gateway_instances[$gateway_id] = $gateway;
            }

            $this->logger->log(
                sprintf('Gateway instance created: %s', $gateway_id),
                'debug',
                'payment'
            );

            return $gateway;
        } catch (\Exception $e) {
            $this->logger->log(
                sprintf('Failed to create gateway instance: %s', $e->getMessage()),
                'error',
                'payment',
                array('gateway_id' => $gateway_id)
            );
            return null;
        }
    }

    /**
     * Create multiple gateway instances
     *
     * @param array $gateway_ids Array of gateway IDs
     * @return array Array of gateway instances
     */
    public function create_gateways($gateway_ids)
    {
        $gateways = array();

        foreach ($gateway_ids as $gateway_id) {
            $gateway = $this->create_gateway($gateway_id);
            if ($gateway) {
                $gateways[$gateway_id] = $gateway;
            }
        }

        return $gateways;
    }

    /**
     * Create all available gateways
     *
     * @return array Array of all available gateway instances
     */
    public function create_all_gateways()
    {
        $registry = ChatShop_Payment_Registry::get_instance();
        $registered_gateways = $registry->get_all_gateways();

        $gateways = array();

        foreach ($registered_gateways as $gateway_id => $gateway_info) {
            $gateway = $this->create_gateway($gateway_id);
            if ($gateway && $gateway->is_available()) {
                $gateways[$gateway_id] = $gateway;
            }
        }

        return $gateways;
    }

    /**
     * Create gateway by capability
     *
     * @param string $capability Required capability (e.g., 'refunds', 'recurring')
     * @return array Array of gateways supporting the capability
     */
    public function create_by_capability($capability)
    {
        $registry = ChatShop_Payment_Registry::get_instance();
        $capable_gateways = $registry->get_gateways_by_capability($capability);

        $gateways = array();

        foreach ($capable_gateways as $gateway_id => $gateway_info) {
            $gateway = $this->create_gateway($gateway_id);
            if ($gateway && $gateway->is_available()) {
                $gateways[$gateway_id] = $gateway;
            }
        }

        return $gateways;
    }

    /**
     * Create gateway for currency
     *
     * @param string $currency Currency code
     * @return array Array of gateways supporting the currency
     */
    public function create_for_currency($currency)
    {
        $registry = ChatShop_Payment_Registry::get_instance();
        $currency_gateways = $registry->get_gateways_by_currency($currency);

        $gateways = array();

        foreach ($currency_gateways as $gateway_id => $gateway_info) {
            $gateway = $this->create_gateway($gateway_id);
            if ($gateway && $gateway->is_available()) {
                $gateways[$gateway_id] = $gateway;
            }
        }

        return $gateways;
    }

    /**
     * Clear gateway cache
     *
     * @param string $gateway_id Optional specific gateway to clear
     * @return void
     */
    public function clear_cache($gateway_id = null)
    {
        if ($gateway_id) {
            unset($this->gateway_instances[$gateway_id]);
        } else {
            $this->gateway_instances = array();
        }

        $this->logger->log(
            'Gateway cache cleared',
            'debug',
            'payment',
            array('gateway_id' => $gateway_id)
        );
    }

    /**
     * Get cached gateway instance
     *
     * @param string $gateway_id Gateway identifier
     * @return ChatShop_Payment_Gateway|null
     */
    public function get_cached_gateway($gateway_id)
    {
        return isset($this->gateway_instances[$gateway_id])
            ? $this->gateway_instances[$gateway_id]
            : null;
    }

    /**
     * Warm up cache by pre-loading gateways
     *
     * @return void
     */
    public function warm_cache()
    {
        $registry = ChatShop_Payment_Registry::get_instance();
        $registered_gateways = $registry->get_all_gateways();

        foreach ($registered_gateways as $gateway_id => $gateway_info) {
            // Only cache enabled gateways
            if (isset($gateway_info['enabled']) && $gateway_info['enabled']) {
                $this->create_gateway($gateway_id);
            }
        }

        $this->logger->log(
            sprintf('Gateway cache warmed with %d gateways', count($this->gateway_instances)),
            'debug',
            'payment'
        );
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
