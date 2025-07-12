<?php

/**
 * Gateway Configuration Manager
 *
 * @package ChatShop
 * @subpackage Components/Payment
 * @since 1.0.0
 */

namespace ChatShop\Components\Payment;

use ChatShop\Includes\ChatShop_Logger;
use ChatShop\Includes\ChatShop_Security;
use ChatShop\Includes\ChatShop_Validator;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Gateway Configuration Manager Class
 *
 * Manages dynamic settings for each payment gateway
 *
 * @since 1.0.0
 */
class ChatShop_Gateway_Configuration_Manager
{

    /**
     * Configuration option prefix
     *
     * @var string
     */
    private $option_prefix = 'chatshop_gateway_';

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    private $logger;

    /**
     * Security instance
     *
     * @var ChatShop_Security
     */
    private $security;

    /**
     * Validator instance
     *
     * @var ChatShop_Validator
     */
    private $validator;

    /**
     * Gateway registry
     *
     * @var ChatShop_Payment_Registry
     */
    private $registry;

    /**
     * Configuration schema
     *
     * @var array
     */
    private $configuration_schema = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = new ChatShop_Logger();
        $this->security = new ChatShop_Security();
        $this->validator = new ChatShop_Validator();
        $this->registry = ChatShop_Payment_Registry::get_instance();

        $this->init();
    }

    /**
     * Initialize configuration manager
     *
     * @return void
     */
    private function init()
    {
        // Register configuration schemas
        add_action('init', array($this, 'register_configuration_schemas'), 10);

        // Admin hooks
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_configuration_pages'));

        // AJAX handlers
        add_action('wp_ajax_chatshop_save_gateway_config', array($this, 'ajax_save_configuration'));
        add_action('wp_ajax_chatshop_test_gateway_config', array($this, 'ajax_test_configuration'));
    }

    /**
     * Register configuration schemas
     *
     * @return void
     */
    public function register_configuration_schemas()
    {
        // Paystack configuration schema
        $this->register_schema('paystack', array(
            'enabled' => array(
                'type'        => 'checkbox',
                'label'       => __('Enable Paystack', 'chatshop'),
                'description' => __('Enable Paystack payment gateway', 'chatshop'),
                'default'     => 'no',
            ),
            'test_mode' => array(
                'type'        => 'checkbox',
                'label'       => __('Test Mode', 'chatshop'),
                'description' => __('Enable test mode for Paystack', 'chatshop'),
                'default'     => 'yes',
            ),
            'test_public_key' => array(
                'type'        => 'text',
                'label'       => __('Test Public Key', 'chatshop'),
                'description' => __('Your Paystack test public key', 'chatshop'),
                'required'    => true,
                'encrypted'   => false,
            ),
            'test_secret_key' => array(
                'type'        => 'password',
                'label'       => __('Test Secret Key', 'chatshop'),
                'description' => __('Your Paystack test secret key', 'chatshop'),
                'required'    => true,
                'encrypted'   => true,
            ),
            'live_public_key' => array(
                'type'        => 'text',
                'label'       => __('Live Public Key', 'chatshop'),
                'description' => __('Your Paystack live public key', 'chatshop'),
                'required'    => true,
                'encrypted'   => false,
            ),
            'live_secret_key' => array(
                'type'        => 'password',
                'label'       => __('Live Secret Key', 'chatshop'),
                'description' => __('Your Paystack live secret key', 'chatshop'),
                'required'    => true,
                'encrypted'   => true,
            ),
            'webhook_secret' => array(
                'type'        => 'password',
                'label'       => __('Webhook Secret', 'chatshop'),
                'description' => __('Webhook secret for verifying Paystack webhooks', 'chatshop'),
                'required'    => false,
                'encrypted'   => true,
            ),
            'payment_channels' => array(
                'type'        => 'multiselect',
                'label'       => __('Payment Channels', 'chatshop'),
                'description' => __('Select payment channels to enable', 'chatshop'),
                'options'     => array(
                    'card'          => __('Card', 'chatshop'),
                    'bank'          => __('Bank Account', 'chatshop'),
                    'ussd'          => __('USSD', 'chatshop'),
                    'qr'            => __('QR Code', 'chatshop'),
                    'mobile_money'  => __('Mobile Money', 'chatshop'),
                    'bank_transfer' => __('Bank Transfer', 'chatshop'),
                ),
                'default'     => array('card', 'bank'),
            ),
            'subaccount_code' => array(
                'type'        => 'text',
                'label'       => __('Subaccount Code', 'chatshop'),
                'description' => __('Subaccount code for split payments (optional)', 'chatshop'),
                'required'    => false,
            ),
            'split_percentage' => array(
                'type'        => 'number',
                'label'       => __('Split Percentage', 'chatshop'),
                'description' => __('Percentage to split to subaccount (0-100)', 'chatshop'),
                'min'         => 0,
                'max'         => 100,
                'step'        => 0.01,
                'default'     => 0,
            ),
        ));

        // Allow other gateways to register their schemas
        do_action('chatshop_register_gateway_configuration_schemas', $this);
    }

    /**
     * Register configuration schema
     *
     * @param string $gateway_id Gateway identifier
     * @param array  $schema     Configuration schema
     * @return void
     */
    public function register_schema($gateway_id, $schema)
    {
        $this->configuration_schema[$gateway_id] = $schema;

        $this->logger->log(
            sprintf('Configuration schema registered for gateway: %s', $gateway_id),
            'debug',
            'payment'
        );
    }

    /**
     * Get configuration schema
     *
     * @param string $gateway_id Gateway identifier
     * @return array|null
     */
    public function get_schema($gateway_id)
    {
        return isset($this->configuration_schema[$gateway_id])
            ? $this->configuration_schema[$gateway_id]
            : null;
    }

    /**
     * Get gateway configuration
     *
     * @param string $gateway_id Gateway identifier
     * @return array
     */
    public function get_configuration($gateway_id)
    {
        $option_name = $this->option_prefix . $gateway_id . '_settings';
        $config = get_option($option_name, array());

        // Decrypt encrypted fields
        $schema = $this->get_schema($gateway_id);

        if ($schema) {
            foreach ($schema as $field_id => $field_config) {
                if (isset($field_config['encrypted']) && $field_config['encrypted'] && isset($config[$field_id])) {
                    $config[$field_id] = $this->security->decrypt($config[$field_id]);
                }
            }
        }

        return $config;
    }

    /**
     * Save gateway configuration
     *
     * @param string $gateway_id    Gateway identifier
     * @param array  $configuration Configuration data
     * @return bool|WP_Error
     */
    public function save_configuration($gateway_id, $configuration)
    {
        // Validate configuration
        $validation = $this->validate_configuration($gateway_id, $configuration);

        if (is_wp_error($validation)) {
            return $validation;
        }

        // Get schema
        $schema = $this->get_schema($gateway_id);

        if (!$schema) {
            return new \WP_Error(
                'invalid_gateway',
                __('Invalid gateway specified', 'chatshop')
            );
        }

        // Process configuration
        $processed_config = array();

        foreach ($schema as $field_id => $field_config) {
            if (isset($configuration[$field_id])) {
                $value = $configuration[$field_id];

                // Sanitize based on field type
                $value = $this->sanitize_field($value, $field_config);

                // Encrypt if necessary
                if (isset($field_config['encrypted']) && $field_config['encrypted']) {
                    $value = $this->security->encrypt($value);
                }

                $processed_config[$field_id] = $value;
            } elseif (isset($field_config['default'])) {
                $processed_config[$field_id] = $field_config['default'];
            }
        }

        // Save configuration
        $option_name = $this->option_prefix . $gateway_id . '_settings';
        $saved = update_option($option_name, $processed_config);

        if ($saved) {
            // Clear gateway cache
            ChatShop_Payment_Factory::get_instance()->clear_cache($gateway_id);

            // Fire event
            do_action('chatshop_gateway_configuration_saved', $gateway_id, $processed_config);

            $this->logger->log(
                sprintf('Gateway configuration saved: %s', $gateway_id),
                'info',
                'payment'
            );
        }

        return $saved;
    }

    /**
     * Validate configuration
     *
     * @param string $gateway_id    Gateway identifier
     * @param array  $configuration Configuration data
     * @return bool|WP_Error
     */
    public function validate_configuration($gateway_id, $configuration)
    {
        $schema = $this->get_schema($gateway_id);

        if (!$schema) {
            return new \WP_Error(
                'invalid_gateway',
                __('Invalid gateway specified', 'chatshop')
            );
        }

        $errors = array();

        foreach ($schema as $field_id => $field_config) {
            // Check required fields
            if (isset($field_config['required']) && $field_config['required']) {
                if (empty($configuration[$field_id])) {
                    $errors[] = sprintf(
                        __('%s is required', 'chatshop'),
                        $field_config['label']
                    );
                }
            }

            // Validate field value if present
            if (isset($configuration[$field_id]) && !empty($configuration[$field_id])) {
                $field_validation = $this->validate_field(
                    $configuration[$field_id],
                    $field_config
                );

                if (is_wp_error($field_validation)) {
                    $errors[] = $field_validation->get_error_message();
                }
            }
        }

        if (!empty($errors)) {
            return new \WP_Error(
                'validation_failed',
                implode(', ', $errors)
            );
        }

        return true;
    }

    /**
     * Validate field
     *
     * @param mixed $value        Field value
     * @param array $field_config Field configuration
     * @return bool|WP_Error
     */
    private function validate_field($value, $field_config)
    {
        switch ($field_config['type']) {
            case 'email':
                if (!is_email($value)) {
                    return new \WP_Error(
                        'invalid_email',
                        sprintf(__('%s must be a valid email', 'chatshop'), $field_config['label'])
                    );
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return new \WP_Error(
                        'invalid_url',
                        sprintf(__('%s must be a valid URL', 'chatshop'), $field_config['label'])
                    );
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    return new \WP_Error(
                        'invalid_number',
                        sprintf(__('%s must be a number', 'chatshop'), $field_config['label'])
                    );
                }

                if (isset($field_config['min']) && $value < $field_config['min']) {
                    return new \WP_Error(
                        'number_too_small',
                        sprintf(__('%s must be at least %s', 'chatshop'), $field_config['label'], $field_config['min'])
                    );
                }

                if (isset($field_config['max']) && $value > $field_config['max']) {
                    return new \WP_Error(
                        'number_too_large',
                        sprintf(__('%s must be at most %s', 'chatshop'), $field_config['label'], $field_config['max'])
                    );
                }
                break;
        }

        return true;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value        Field value
     * @param array $field_config Field configuration
     * @return mixed
     */
    private function sanitize_field($value, $field_config)
    {
        switch ($field_config['type']) {
            case 'text':
            case 'password':
                return sanitize_text_field($value);

            case 'textarea':
                return sanitize_textarea_field($value);

            case 'email':
                return sanitize_email($value);

            case 'url':
                return esc_url_raw($value);

            case 'number':
                return floatval($value);

            case 'checkbox':
                return $value === 'yes' ? 'yes' : 'no';

            case 'select':
            case 'multiselect':
                if (is_array($value)) {
                    return array_map('sanitize_text_field', $value);
                }
                return sanitize_text_field($value);

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Test gateway configuration
     *
     * @param string $gateway_id Gateway identifier
     * @return bool|WP_Error
     */
    public function test_configuration($gateway_id)
    {
        $gateway = ChatShop_Payment_Factory::get_instance()->create_gateway($gateway_id);

        if (!$gateway) {
            return new \WP_Error(
                'invalid_gateway',
                __('Invalid gateway specified', 'chatshop')
            );
        }

        // Test configuration
        $test_result = $gateway->validate_configuration();

        if (is_wp_error($test_result)) {
            return $test_result;
        }

        // Additional gateway-specific tests
        $test_result = apply_filters(
            'chatshop_test_gateway_configuration',
            $test_result,
            $gateway_id,
            $gateway
        );

        return $test_result;
    }

    /**
     * Get configuration form fields
     *
     * @param string $gateway_id Gateway identifier
     * @return array
     */
    public function get_form_fields($gateway_id)
    {
        $schema = $this->get_schema($gateway_id);

        if (!$schema) {
            return array();
        }

        $current_config = $this->get_configuration($gateway_id);
        $fields = array();

        foreach ($schema as $field_id => $field_config) {
            $field = array_merge($field_config, array(
                'id'    => $field_id,
                'name'  => 'chatshop_gateway_' . $gateway_id . '[' . $field_id . ']',
                'value' => isset($current_config[$field_id]) ? $current_config[$field_id] : '',
            ));

            $fields[$field_id] = $field;
        }

        return $fields;
    }

    /**
     * AJAX save configuration handler
     *
     * @return void
     */
    public function ajax_save_configuration()
    {
        // Verify nonce
        if (!check_ajax_referer('chatshop_gateway_config', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'chatshop'));
        }

        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'chatshop'));
        }

        $gateway_id = isset($_POST['gateway_id']) ? sanitize_key($_POST['gateway_id']) : '';
        $configuration = isset($_POST['configuration']) ? $_POST['configuration'] : array();

        if (empty($gateway_id)) {
            wp_send_json_error(__('Gateway ID is required', 'chatshop'));
        }

        // Save configuration
        $result = $this->save_configuration($gateway_id, $configuration);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Configuration saved successfully', 'chatshop'));
    }

    /**
     * AJAX test configuration handler
     *
     * @return void
     */
    public function ajax_test_configuration()
    {
        // Verify nonce
        if (!check_ajax_referer('chatshop_gateway_config', 'nonce', false)) {
            wp_send_json_error(__('Security check failed', 'chatshop'));
        }

        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'chatshop'));
        }

        $gateway_id = isset($_POST['gateway_id']) ? sanitize_key($_POST['gateway_id']) : '';

        if (empty($gateway_id)) {
            wp_send_json_error(__('Gateway ID is required', 'chatshop'));
        }

        // Test configuration
        $result = $this->test_configuration($gateway_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Configuration test passed', 'chatshop'));
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings()
    {
        $gateways = $this->registry->get_all_gateways();

        foreach ($gateways as $gateway_id => $gateway_info) {
            register_setting(
                'chatshop_gateway_' . $gateway_id,
                'chatshop_gateway_' . $gateway_id . '_settings',
                array(
                    'sanitize_callback' => array($this, 'sanitize_settings'),
                )
            );
        }
    }

    /**
     * Sanitize settings
     *
     * @param array $settings Raw settings
     * @return array
     */
    public function sanitize_settings($settings)
    {
        // Settings are sanitized in save_configuration method
        return $settings;
    }

    /**
     * Add configuration pages
     *
     * @return void
     */
    public function add_configuration_pages()
    {
        // Configuration pages are handled by the main admin class
        // This is a placeholder for gateway-specific configuration pages if needed
    }

    /**
     * Export configuration
     *
     * @param string $gateway_id Gateway identifier (null for all)
     * @return array
     */
    public function export_configuration($gateway_id = null)
    {
        $export_data = array(
            'version'    => CHATSHOP_VERSION,
            'exported_at' => current_time('mysql'),
            'gateways'    => array(),
        );

        if ($gateway_id) {
            // Export specific gateway
            $config = $this->get_configuration($gateway_id);

            // Remove sensitive data
            $schema = $this->get_schema($gateway_id);

            foreach ($schema as $field_id => $field_config) {
                if (isset($field_config['encrypted']) && $field_config['encrypted']) {
                    unset($config[$field_id]);
                }
            }

            $export_data['gateways'][$gateway_id] = $config;
        } else {
            // Export all gateways
            $gateways = $this->registry->get_all_gateways();

            foreach ($gateways as $gw_id => $gateway_info) {
                $config = $this->get_configuration($gw_id);

                // Remove sensitive data
                $schema = $this->get_schema($gw_id);

                if ($schema) {
                    foreach ($schema as $field_id => $field_config) {
                        if (isset($field_config['encrypted']) && $field_config['encrypted']) {
                            unset($config[$field_id]);
                        }
                    }
                }

                $export_data['gateways'][$gw_id] = $config;
            }
        }

        return $export_data;
    }

    /**
     * Import configuration
     *
     * @param array $import_data Import data
     * @return bool|WP_Error
     */
    public function import_configuration($import_data)
    {
        if (!isset($import_data['gateways']) || !is_array($import_data['gateways'])) {
            return new \WP_Error(
                'invalid_import_data',
                __('Invalid import data format', 'chatshop')
            );
        }

        $results = array();

        foreach ($import_data['gateways'] as $gateway_id => $config) {
            // Merge with existing config to preserve encrypted fields
            $existing_config = $this->get_configuration($gateway_id);
            $merged_config = array_merge($existing_config, $config);

            $result = $this->save_configuration($gateway_id, $merged_config);

            if (is_wp_error($result)) {
                $results['errors'][] = sprintf(
                    __('Failed to import %s: %s', 'chatshop'),
                    $gateway_id,
                    $result->get_error_message()
                );
            } else {
                $results['success'][] = $gateway_id;
            }
        }

        if (!empty($results['errors'])) {
            return new \WP_Error(
                'import_partial_failure',
                implode(', ', $results['errors'])
            );
        }

        return true;
    }

    /**
     * Get configuration status
     *
     * @param string $gateway_id Gateway identifier
     * @return array
     */
    public function get_configuration_status($gateway_id)
    {
        $status = array(
            'configured'  => false,
            'tested'      => false,
            'active'      => false,
            'missing'     => array(),
            'warnings'    => array(),
        );

        $config = $this->get_configuration($gateway_id);
        $schema = $this->get_schema($gateway_id);

        if (!$schema) {
            return $status;
        }

        // Check required fields
        foreach ($schema as $field_id => $field_config) {
            if (isset($field_config['required']) && $field_config['required']) {
                if (empty($config[$field_id])) {
                    $status['missing'][] = $field_config['label'];
                }
            }
        }

        $status['configured'] = empty($status['missing']);

        // Check if enabled
        $status['active'] = isset($config['enabled']) && $config['enabled'] === 'yes';

        // Check test status
        $test_result = get_transient('chatshop_gateway_test_' . $gateway_id);
        $status['tested'] = $test_result === true;

        // Add warnings
        if (isset($config['test_mode']) && $config['test_mode'] === 'yes') {
            $status['warnings'][] = __('Gateway is in test mode', 'chatshop');
        }

        return $status;
    }

    /**
     * Reset configuration
     *
     * @param string $gateway_id Gateway identifier
     * @return bool
     */
    public function reset_configuration($gateway_id)
    {
        $option_name = $this->option_prefix . $gateway_id . '_settings';
        $deleted = delete_option($option_name);

        if ($deleted) {
            // Clear cache
            ChatShop_Payment_Factory::get_instance()->clear_cache($gateway_id);

            // Fire event
            do_action('chatshop_gateway_configuration_reset', $gateway_id);

            $this->logger->log(
                sprintf('Gateway configuration reset: %s', $gateway_id),
                'info',
                'payment'
            );
        }

        return $deleted;
    }
}
