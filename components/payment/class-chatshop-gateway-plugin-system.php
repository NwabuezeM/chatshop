<?php

/**
 * Gateway Plugin System
 *
 * @package ChatShop
 * @subpackage Components/Payment
 * @since 1.0.0
 */

namespace ChatShop\Components\Payment;

use ChatShop\Includes\ChatShop_Logger;
use ChatShop\Includes\ChatShop_Component_Loader;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Gateway Plugin System Class
 *
 * Manages dynamic loading and registration of payment gateway plugins
 *
 * @since 1.0.0
 */
class ChatShop_Gateway_Plugin_System
{

    /**
     * Singleton instance
     *
     * @var ChatShop_Gateway_Plugin_System
     */
    private static $instance = null;

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    private $logger;

    /**
     * Gateway registry
     *
     * @var ChatShop_Payment_Registry
     */
    private $registry;

    /**
     * Component loader
     *
     * @var ChatShop_Component_Loader
     */
    private $component_loader;

    /**
     * Gateway plugins directory
     *
     * @var string
     */
    private $plugins_directory;

    /**
     * Loaded gateway plugins
     *
     * @var array
     */
    private $loaded_plugins = array();

    /**
     * Private constructor
     */
    private function __construct()
    {
        $this->logger = new ChatShop_Logger();
        $this->registry = ChatShop_Payment_Registry::get_instance();
        $this->plugins_directory = CHATSHOP_PLUGIN_DIR . 'components/payment/gateways/';

        $this->init();
    }

    /**
     * Get singleton instance
     *
     * @return ChatShop_Gateway_Plugin_System
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin system
     *
     * @return void
     */
    private function init()
    {
        // Load gateway plugins on init
        add_action('init', array($this, 'load_gateway_plugins'), 5);

        // Allow external gateway registration
        add_action('chatshop_register_external_gateways', array($this, 'register_external_gateways'));

        // Handle gateway activation/deactivation
        add_action('chatshop_activate_gateway', array($this, 'activate_gateway'));
        add_action('chatshop_deactivate_gateway', array($this, 'deactivate_gateway'));

        // Provide hooks for gateway developers
        add_action('chatshop_before_load_gateways', array($this, 'before_load_gateways'));
        add_action('chatshop_after_load_gateways', array($this, 'after_load_gateways'));
    }

    /**
     * Load gateway plugins
     *
     * @return void
     */
    public function load_gateway_plugins()
    {
        // Fire before load hook
        do_action('chatshop_before_load_gateways');

        // Load built-in gateways
        $this->load_builtin_gateways();

        // Load external gateways
        $this->load_external_gateways();

        // Fire after load hook
        do_action('chatshop_after_load_gateways');

        $this->logger->log(
            sprintf('Loaded %d gateway plugins', count($this->loaded_plugins)),
            'info',
            'payment'
        );
    }

    /**
     * Load built-in gateways
     *
     * @return void
     */
    private function load_builtin_gateways()
    {
        $builtin_gateways = array(
            'paystack',
            'paypal',
            'flutterwave',
            'razorpay',
        );

        foreach ($builtin_gateways as $gateway_id) {
            $gateway_path = $this->plugins_directory . $gateway_id . '/';

            if (is_dir($gateway_path)) {
                $this->load_gateway_from_directory($gateway_id, $gateway_path);
            }
        }
    }

    /**
     * Load external gateways
     *
     * @return void
     */
    private function load_external_gateways()
    {
        // Check for external gateways directory
        $external_dir = WP_CONTENT_DIR . '/chatshop-gateways/';

        if (!is_dir($external_dir)) {
            return;
        }

        $gateway_dirs = glob($external_dir . '*', GLOB_ONLYDIR);

        foreach ($gateway_dirs as $gateway_dir) {
            $gateway_id = basename($gateway_dir);
            $this->load_gateway_from_directory($gateway_id, $gateway_dir . '/');
        }

        // Also check uploads directory for user-uploaded gateways
        $upload_dir = wp_upload_dir();
        $upload_gateway_dir = $upload_dir['basedir'] . '/chatshop-gateways/';

        if (is_dir($upload_gateway_dir)) {
            $uploaded_gateways = glob($upload_gateway_dir . '*', GLOB_ONLYDIR);

            foreach ($uploaded_gateways as $gateway_dir) {
                $gateway_id = basename($gateway_dir);
                $this->load_gateway_from_directory($gateway_id, $gateway_dir . '/');
            }
        }
    }

    /**
     * Load gateway from directory
     *
     * @param string $gateway_id   Gateway identifier
     * @param string $gateway_path Gateway directory path
     * @return bool
     */
    private function load_gateway_from_directory($gateway_id, $gateway_path)
    {
        // Check for gateway manifest
        $manifest_file = $gateway_path . 'gateway-manifest.json';

        if (!file_exists($manifest_file)) {
            $this->logger->log(
                sprintf('Gateway manifest not found: %s', $gateway_id),
                'warning',
                'payment'
            );
            return false;
        }

        // Load and validate manifest
        $manifest = $this->load_gateway_manifest($manifest_file);

        if (!$manifest || !$this->validate_manifest($manifest)) {
            return false;
        }

        // Check gateway compatibility
        if (!$this->check_compatibility($manifest)) {
            $this->logger->log(
                sprintf('Gateway %s is not compatible with current ChatShop version', $gateway_id),
                'warning',
                'payment'
            );
            return false;
        }

        // Load gateway files
        $loaded = $this->load_gateway_files($gateway_id, $gateway_path, $manifest);

        if ($loaded) {
            $this->loaded_plugins[$gateway_id] = array(
                'path'     => $gateway_path,
                'manifest' => $manifest,
                'loaded_at' => current_time('timestamp'),
            );

            // Fire gateway loaded event
            do_action('chatshop_gateway_loaded', $gateway_id, $manifest);
        }

        return $loaded;
    }

    /**
     * Load gateway manifest
     *
     * @param string $manifest_file Manifest file path
     * @return array|null
     */
    private function load_gateway_manifest($manifest_file)
    {
        $manifest_content = file_get_contents($manifest_file);

        if (!$manifest_content) {
            return null;
        }

        $manifest = json_decode($manifest_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->log(
                sprintf('Invalid gateway manifest JSON: %s', $manifest_file),
                'error',
                'payment'
            );
            return null;
        }

        return $manifest;
    }

    /**
     * Validate gateway manifest
     *
     * @param array $manifest Gateway manifest
     * @return bool
     */
    private function validate_manifest($manifest)
    {
        $required_fields = array(
            'id',
            'name',
            'version',
            'author',
            'main_class',
            'supported_currencies',
            'supported_features',
        );

        foreach ($required_fields as $field) {
            if (!isset($manifest[$field])) {
                $this->logger->log(
                    sprintf('Gateway manifest missing required field: %s', $field),
                    'error',
                    'payment'
                );
                return false;
            }
        }

        return true;
    }

    /**
     * Check gateway compatibility
     *
     * @param array $manifest Gateway manifest
     * @return bool
     */
    private function check_compatibility($manifest)
    {
        // Check ChatShop version requirement
        if (isset($manifest['requires_chatshop'])) {
            if (version_compare(CHATSHOP_VERSION, $manifest['requires_chatshop'], '<')) {
                return false;
            }
        }

        // Check PHP version requirement
        if (isset($manifest['requires_php'])) {
            if (version_compare(PHP_VERSION, $manifest['requires_php'], '<')) {
                return false;
            }
        }

        // Check WordPress version requirement
        if (isset($manifest['requires_wordpress'])) {
            global $wp_version;
            if (version_compare($wp_version, $manifest['requires_wordpress'], '<')) {
                return false;
            }
        }

        // Check WooCommerce version requirement
        if (isset($manifest['requires_woocommerce'])) {
            if (!defined('WC_VERSION') || version_compare(WC_VERSION, $manifest['requires_woocommerce'], '<')) {
                return false;
            }
        }

        // Check dependencies
        if (isset($manifest['dependencies']) && is_array($manifest['dependencies'])) {
            foreach ($manifest['dependencies'] as $dependency) {
                if (!$this->check_dependency($dependency)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check dependency
     *
     * @param array $dependency Dependency configuration
     * @return bool
     */
    private function check_dependency($dependency)
    {
        if (isset($dependency['type'])) {
            switch ($dependency['type']) {
                case 'plugin':
                    return is_plugin_active($dependency['slug']);

                case 'class':
                    return class_exists($dependency['name']);

                case 'function':
                    return function_exists($dependency['name']);

                case 'extension':
                    return extension_loaded($dependency['name']);
            }
        }

        return true;
    }

    /**
     * Load gateway files
     *
     * @param string $gateway_id   Gateway identifier
     * @param string $gateway_path Gateway directory path
     * @param array  $manifest     Gateway manifest
     * @return bool
     */
    private function load_gateway_files($gateway_id, $gateway_path, $manifest)
    {
        // Load main gateway file
        $main_file = $gateway_path . $manifest['main_file'];

        if (!file_exists($main_file)) {
            $this->logger->log(
                sprintf('Gateway main file not found: %s', $main_file),
                'error',
                'payment'
            );
            return false;
        }

        // Include the file
        require_once $main_file;

        // Check if main class exists
        if (!class_exists($manifest['main_class'])) {
            $this->logger->log(
                sprintf('Gateway main class not found: %s', $manifest['main_class']),
                'error',
                'payment'
            );
            return false;
        }

        // Register the gateway
        $this->registry->register_gateway($gateway_id, array(
            'class'                 => $manifest['main_class'],
            'name'                  => $manifest['name'],
            'description'           => isset($manifest['description']) ? $manifest['description'] : '',
            'supported_currencies'  => $manifest['supported_currencies'],
            'supported_features'    => $manifest['supported_features'],
            'icon'                  => isset($manifest['icon']) ? $gateway_path . $manifest['icon'] : '',
            'priority'              => isset($manifest['priority']) ? $manifest['priority'] : 50,
        ));

        // Load additional files if specified
        if (isset($manifest['additional_files']) && is_array($manifest['additional_files'])) {
            foreach ($manifest['additional_files'] as $file) {
                $file_path = $gateway_path . $file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                }
            }
        }

        // Run gateway initialization if specified
        if (isset($manifest['init_hook'])) {
            add_action('init', $manifest['init_hook'], 15);
        }

        return true;
    }

    /**
     * Register external gateway
     *
     * @param array $gateway_config Gateway configuration
     * @return bool
     */
    public function register_external_gateway($gateway_config)
    {
        // Validate configuration
        if (!isset($gateway_config['id']) || !isset($gateway_config['class'])) {
            return false;
        }

        // Check if class exists
        if (!class_exists($gateway_config['class'])) {
            return false;
        }

        // Register with registry
        return $this->registry->register_gateway(
            $gateway_config['id'],
            $gateway_config
        );
    }

    /**
     * Activate gateway
     *
     * @param string $gateway_id Gateway identifier
     * @return bool
     */
    public function activate_gateway($gateway_id)
    {
        // Update gateway settings
        $config_manager = new ChatShop_Gateway_Configuration_Manager();
        $current_config = $config_manager->get_configuration($gateway_id);
        $current_config['enabled'] = 'yes';

        $result = $config_manager->save_configuration($gateway_id, $current_config);

        if (!is_wp_error($result)) {
            // Fire activation hook
            do_action('chatshop_gateway_activated', $gateway_id);

            $this->logger->log(
                sprintf('Gateway activated: %s', $gateway_id),
                'info',
                'payment'
            );
        }

        return !is_wp_error($result);
    }

    /**
     * Deactivate gateway
     *
     * @param string $gateway_id Gateway identifier
     * @return bool
     */
    public function deactivate_gateway($gateway_id)
    {
        // Update gateway settings
        $config_manager = new ChatShop_Gateway_Configuration_Manager();
        $current_config = $config_manager->get_configuration($gateway_id);
        $current_config['enabled'] = 'no';

        $result = $config_manager->save_configuration($gateway_id, $current_config);

        if (!is_wp_error($result)) {
            // Fire deactivation hook
            do_action('chatshop_gateway_deactivated', $gateway_id);

            $this->logger->log(
                sprintf('Gateway deactivated: %s', $gateway_id),
                'info',
                'payment'
            );
        }

        return !is_wp_error($result);
    }

    /**
     * Get loaded plugins
     *
     * @return array
     */
    public function get_loaded_plugins()
    {
        return $this->loaded_plugins;
    }

    /**
     * Check if gateway is loaded
     *
     * @param string $gateway_id Gateway identifier
     * @return bool
     */
    public function is_gateway_loaded($gateway_id)
    {
        return isset($this->loaded_plugins[$gateway_id]);
    }

    /**
     * Get gateway manifest
     *
     * @param string $gateway_id Gateway identifier
     * @return array|null
     */
    public function get_gateway_manifest($gateway_id)
    {
        if (isset($this->loaded_plugins[$gateway_id])) {
            return $this->loaded_plugins[$gateway_id]['manifest'];
        }

        return null;
    }

    /**
     * Before load gateways hook
     *
     * @return void
     */
    public function before_load_gateways()
    {
        // Allow developers to prepare for gateway loading
        $this->logger->log('Beginning gateway plugin loading', 'debug', 'payment');
    }

    /**
     * After load gateways hook
     *
     * @return void
     */
    public function after_load_gateways()
    {
        // Allow developers to perform actions after all gateways are loaded
        $this->logger->log('Completed gateway plugin loading', 'debug', 'payment');
    }

    /**
     * Register external gateways
     *
     * @return void
     */
    public function register_external_gateways()
    {
        // This hook allows external plugins to register their gateways
        // without needing to be in the ChatShop gateways directory
    }

    /**
     * Validate gateway plugin
     *
     * @param string $gateway_path Path to gateway plugin
     * @return bool|WP_Error
     */
    public function validate_gateway_plugin($gateway_path)
    {
        // Check if path exists
        if (!is_dir($gateway_path)) {
            return new \WP_Error(
                'invalid_path',
                __('Gateway plugin directory not found', 'chatshop')
            );
        }

        // Check for manifest
        $manifest_file = $gateway_path . '/gateway-manifest.json';
        if (!file_exists($manifest_file)) {
            return new \WP_Error(
                'missing_manifest',
                __('Gateway manifest file not found', 'chatshop')
            );
        }

        // Load and validate manifest
        $manifest = $this->load_gateway_manifest($manifest_file);
        if (!$manifest) {
            return new \WP_Error(
                'invalid_manifest',
                __('Gateway manifest is invalid', 'chatshop')
            );
        }

        if (!$this->validate_manifest($manifest)) {
            return new \WP_Error(
                'incomplete_manifest',
                __('Gateway manifest is missing required fields', 'chatshop')
            );
        }

        // Check main file
        $main_file = $gateway_path . '/' . $manifest['main_file'];
        if (!file_exists($main_file)) {
            return new \WP_Error(
                'missing_main_file',
                __('Gateway main file not found', 'chatshop')
            );
        }

        return true;
    }

    /**
     * Install gateway plugin
     *
     * @param string $gateway_path Path to gateway plugin
     * @return bool|WP_Error
     */
    public function install_gateway_plugin($gateway_path)
    {
        // Validate plugin
        $validation = $this->validate_gateway_plugin($gateway_path);

        if (is_wp_error($validation)) {
            return $validation;
        }

        // Load manifest
        $manifest = $this->load_gateway_manifest($gateway_path . '/gateway-manifest.json');
        $gateway_id = $manifest['id'];

        // Check if already installed
        if ($this->is_gateway_loaded($gateway_id)) {
            return new \WP_Error(
                'already_installed',
                __('Gateway is already installed', 'chatshop')
            );
        }

        // Copy to external gateways directory
        $external_dir = WP_CONTENT_DIR . '/chatshop-gateways/';

        if (!is_dir($external_dir)) {
            wp_mkdir_p($external_dir);
        }

        $target_dir = $external_dir . $gateway_id;

        // Copy files
        $copied = $this->copy_directory($gateway_path, $target_dir);

        if (!$copied) {
            return new \WP_Error(
                'copy_failed',
                __('Failed to install gateway files', 'chatshop')
            );
        }

        // Load the gateway
        $loaded = $this->load_gateway_from_directory($gateway_id, $target_dir . '/');

        if (!$loaded) {
            // Clean up on failure
            $this->remove_directory($target_dir);

            return new \WP_Error(
                'load_failed',
                __('Failed to load gateway plugin', 'chatshop')
            );
        }

        return true;
    }

    /**
     * Uninstall gateway plugin
     *
     * @param string $gateway_id Gateway identifier
     * @return bool|WP_Error
     */
    public function uninstall_gateway_plugin($gateway_id)
    {
        // Check if gateway is loaded
        if (!$this->is_gateway_loaded($gateway_id)) {
            return new \WP_Error(
                'not_installed',
                __('Gateway is not installed', 'chatshop')
            );
        }

        // Deactivate first
        $this->deactivate_gateway($gateway_id);

        // Get gateway path
        $gateway_info = $this->loaded_plugins[$gateway_id];
        $gateway_path = $gateway_info['path'];

        // Check if it's an external gateway
        if (
            strpos($gateway_path, WP_CONTENT_DIR . '/chatshop-gateways/') === 0 ||
            strpos($gateway_path, wp_upload_dir()['basedir'] . '/chatshop-gateways/') === 0
        ) {

            // Remove directory
            $removed = $this->remove_directory($gateway_path);

            if (!$removed) {
                return new \WP_Error(
                    'removal_failed',
                    __('Failed to remove gateway files', 'chatshop')
                );
            }
        }

        // Unregister from registry
        $this->registry->unregister_gateway($gateway_id);

        // Remove from loaded plugins
        unset($this->loaded_plugins[$gateway_id]);

        // Fire uninstall hook
        do_action('chatshop_gateway_uninstalled', $gateway_id);

        return true;
    }

    /**
     * Copy directory recursively
     *
     * @param string $source      Source directory
     * @param string $destination Destination directory
     * @return bool
     */
    private function copy_directory($source, $destination)
    {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . '/' . $iterator->getSubPathName();

            if ($item->isDir()) {
                mkdir($target, 0755, true);
            } else {
                copy($item, $target);
            }
        }

        return true;
    }

    /**
     * Remove directory recursively
     *
     * @param string $directory Directory to remove
     * @return bool
     */
    private function remove_directory($directory)
    {
        if (!is_dir($directory)) {
            return false;
        }

        $files = array_diff(scandir($directory), array('.', '..'));

        foreach ($files as $file) {
            $path = $directory . '/' . $file;

            if (is_dir($path)) {
                $this->remove_directory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($directory);
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
