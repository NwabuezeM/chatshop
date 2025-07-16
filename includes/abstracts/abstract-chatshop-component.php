<?php

/**
 * Abstract Component Class
 *
 * Base class for all components in the ChatShop plugin.
 * Provides common functionality for component initialization,
 * activation, deactivation, and dependency management.
 *
 * @package ChatShop
 * @subpackage Core\Abstracts
 * @since 1.0.0
 */

namespace ChatShop\Core\Abstracts;

use ChatShop\Core\ChatShop_Logger;
use ChatShop\Core\ChatShop_Database;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract ChatShop Component
 * 
 * Base class for all plugin components
 */
abstract class Abstract_ChatShop_Component
{

    /**
     * Component ID
     *
     * @var string
     */
    protected $component_id = '';

    /**
     * Component name
     *
     * @var string
     */
    protected $component_name = '';

    /**
     * Component version
     *
     * @var string
     */
    protected $component_version = '1.0.0';

    /**
     * Component description
     *
     * @var string
     */
    protected $component_description = '';

    /**
     * Whether component is active
     *
     * @var bool
     */
    protected $is_active = false;

    /**
     * Component dependencies
     *
     * @var array
     */
    protected $dependencies = [];

    /**
     * Component hooks
     *
     * @var array
     */
    protected $hooks = [];

    /**
     * Component settings
     *
     * @var array
     */
    protected $settings = [];

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    protected $logger;

    /**
     * Database instance
     *
     * @var ChatShop_Database
     */
    protected $database;

    /**
     * Constructor
     *
     * @param array $args Component arguments
     */
    public function __construct($args = [])
    {
        $this->logger = new ChatShop_Logger($this->component_id);
        $this->database = new ChatShop_Database();

        $this->init_component($args);
        $this->load_settings();

        // Only initialize if component should be active
        if ($this->should_load()) {
            $this->init();
        }
    }

    /**
     * Initialize component
     *
     * @param array $args Component arguments
     * @return void
     */
    protected function init_component($args)
    {
        if (isset($args['component_id'])) {
            $this->component_id = $args['component_id'];
        }

        if (isset($args['component_name'])) {
            $this->component_name = $args['component_name'];
        }

        if (isset($args['component_version'])) {
            $this->component_version = $args['component_version'];
        }

        if (isset($args['component_description'])) {
            $this->component_description = $args['component_description'];
        }

        if (isset($args['dependencies'])) {
            $this->dependencies = $args['dependencies'];
        }
    }

    /**
     * Check if component should load
     * 
     * Must be implemented by child classes
     *
     * @return bool
     */
    abstract protected function should_load();

    /**
     * Initialize component
     * 
     * Must be implemented by child classes
     *
     * @return void
     */
    abstract protected function init();

    /**
     * Get component requirements
     * 
     * Must be implemented by child classes
     *
     * @return array
     */
    abstract public function get_requirements();

    /**
     * Activate component
     * 
     * Can be overridden by child classes
     *
     * @return bool|WP_Error
     */
    public function activate()
    {
        // Check dependencies
        $dependency_check = $this->check_dependencies();
        if (is_wp_error($dependency_check)) {
            return $dependency_check;
        }

        // Check requirements
        $requirements_check = $this->check_requirements();
        if (is_wp_error($requirements_check)) {
            return $requirements_check;
        }

        // Run component-specific activation
        $activation_result = $this->on_activate();
        if (is_wp_error($activation_result)) {
            return $activation_result;
        }

        // Set component as active
        $this->is_active = true;
        $this->save_activation_state();

        $this->logger->info('Component activated successfully', [
            'component_id' => $this->component_id,
        ]);

        return true;
    }

    /**
     * Deactivate component
     * 
     * Can be overridden by child classes
     *
     * @return bool|WP_Error
     */
    public function deactivate()
    {
        // Run component-specific deactivation
        $deactivation_result = $this->on_deactivate();
        if (is_wp_error($deactivation_result)) {
            return $deactivation_result;
        }

        // Set component as inactive
        $this->is_active = false;
        $this->save_activation_state();

        $this->logger->info('Component deactivated successfully', [
            'component_id' => $this->component_id,
        ]);

        return true;
    }

    /**
     * Component activation hook
     * 
     * Can be overridden by child classes
     *
     * @return bool|WP_Error
     */
    protected function on_activate()
    {
        return true;
    }

    /**
     * Component deactivation hook
     * 
     * Can be overridden by child classes
     *
     * @return bool|WP_Error
     */
    protected function on_deactivate()
    {
        return true;
    }

    /**
     * Check component dependencies
     *
     * @return bool|WP_Error
     */
    public function check_dependencies()
    {
        $missing_dependencies = [];

        foreach ($this->dependencies as $dependency) {
            if (!$this->is_dependency_met($dependency)) {
                $missing_dependencies[] = $dependency;
            }
        }

        if (!empty($missing_dependencies)) {
            return new \WP_Error(
                'missing_dependencies',
                sprintf(
                    __('Component %s is missing required dependencies: %s', 'chatshop'),
                    $this->component_name,
                    implode(', ', $missing_dependencies)
                ),
                ['missing_dependencies' => $missing_dependencies]
            );
        }

        return true;
    }

    /**
     * Check if dependency is met
     *
     * @param string $dependency Dependency identifier
     * @return bool
     */
    protected function is_dependency_met($dependency)
    {
        switch ($dependency) {
            case 'woocommerce':
                return class_exists('WooCommerce');

            case 'wordpress':
                global $wp_version;
                return version_compare($wp_version, '5.0', '>=');

            default:
                // Check if it's a component dependency
                return $this->is_component_active($dependency);
        }
    }

    /**
     * Check if another component is active
     *
     * @param string $component_id Component ID
     * @return bool
     */
    protected function is_component_active($component_id)
    {
        $active_components = get_option('chatshop_active_components', []);
        return in_array($component_id, $active_components);
    }

    /**
     * Check component requirements
     *
     * @return bool|WP_Error
     */
    public function check_requirements()
    {
        $requirements = $this->get_requirements();
        $failed_requirements = [];

        foreach ($requirements as $requirement => $details) {
            if (!$this->is_requirement_met($requirement, $details)) {
                $failed_requirements[] = $requirement;
            }
        }

        if (!empty($failed_requirements)) {
            return new \WP_Error(
                'requirements_not_met',
                sprintf(
                    __('Component %s requirements not met: %s', 'chatshop'),
                    $this->component_name,
                    implode(', ', $failed_requirements)
                ),
                ['failed_requirements' => $failed_requirements]
            );
        }

        return true;
    }

    /**
     * Check if requirement is met
     *
     * @param string $requirement Requirement name
     * @param array $details Requirement details
     * @return bool
     */
    protected function is_requirement_met($requirement, $details)
    {
        switch ($requirement) {
            case 'php_version':
                return version_compare(PHP_VERSION, $details['min_version'], '>=');

            case 'wordpress_version':
                global $wp_version;
                return version_compare($wp_version, $details['min_version'], '>=');

            case 'woocommerce_version':
                if (!class_exists('WooCommerce')) {
                    return false;
                }
                return version_compare(WC()->version, $details['min_version'], '>=');

            case 'php_extensions':
                foreach ($details['extensions'] as $extension) {
                    if (!extension_loaded($extension)) {
                        return false;
                    }
                }
                return true;

            case 'wp_capabilities':
                foreach ($details['capabilities'] as $capability) {
                    if (!current_user_can($capability)) {
                        return false;
                    }
                }
                return true;

            default:
                return true;
        }
    }

    /**
     * Load component settings
     *
     * @return void
     */
    protected function load_settings()
    {
        $option_name = 'chatshop_' . $this->component_id . '_settings';
        $this->settings = get_option($option_name, $this->get_default_settings());
    }

    /**
     * Save component settings
     *
     * @return bool
     */
    protected function save_settings()
    {
        $option_name = 'chatshop_' . $this->component_id . '_settings';
        return update_option($option_name, $this->settings);
    }

    /**
     * Get default settings
     * 
     * Can be overridden by child classes
     *
     * @return array
     */
    protected function get_default_settings()
    {
        return [];
    }

    /**
     * Get setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get_setting($key, $default = null)
    {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Set setting value
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return void
     */
    public function set_setting($key, $value)
    {
        $this->settings[$key] = $value;
    }

    /**
     * Save activation state
     *
     * @return void
     */
    protected function save_activation_state()
    {
        $active_components = get_option('chatshop_active_components', []);

        if ($this->is_active && !in_array($this->component_id, $active_components)) {
            $active_components[] = $this->component_id;
        } elseif (!$this->is_active && in_array($this->component_id, $active_components)) {
            $active_components = array_diff($active_components, [$this->component_id]);
        }

        update_option('chatshop_active_components', $active_components);
    }

    /**
     * Register hooks
     *
     * @return void
     */
    protected function register_hooks()
    {
        foreach ($this->hooks as $hook) {
            if (isset($hook['type']) && isset($hook['tag']) && isset($hook['callback'])) {
                $priority = isset($hook['priority']) ? $hook['priority'] : 10;
                $accepted_args = isset($hook['accepted_args']) ? $hook['accepted_args'] : 1;

                if ($hook['type'] === 'action') {
                    add_action($hook['tag'], $hook['callback'], $priority, $accepted_args);
                } elseif ($hook['type'] === 'filter') {
                    add_filter($hook['tag'], $hook['callback'], $priority, $accepted_args);
                }
            }
        }
    }

    /**
     * Unregister hooks
     *
     * @return void
     */
    protected function unregister_hooks()
    {
        foreach ($this->hooks as $hook) {
            if (isset($hook['type']) && isset($hook['tag']) && isset($hook['callback'])) {
                $priority = isset($hook['priority']) ? $hook['priority'] : 10;

                if ($hook['type'] === 'action') {
                    remove_action($hook['tag'], $hook['callback'], $priority);
                } elseif ($hook['type'] === 'filter') {
                    remove_filter($hook['tag'], $hook['callback'], $priority);
                }
            }
        }
    }

    /**
     * Get component ID
     *
     * @return string
     */
    public function get_component_id()
    {
        return $this->component_id;
    }

    /**
     * Get component name
     *
     * @return string
     */
    public function get_component_name()
    {
        return $this->component_name;
    }

    /**
     * Get component version
     *
     * @return string
     */
    public function get_component_version()
    {
        return $this->component_version;
    }

    /**
     * Get component description
     *
     * @return string
     */
    public function get_component_description()
    {
        return $this->component_description;
    }

    /**
     * Check if component is active
     *
     * @return bool
     */
    public function is_active()
    {
        return $this->is_active;
    }

    /**
     * Get component dependencies
     *
     * @return array
     */
    public function get_dependencies()
    {
        return $this->dependencies;
    }

    /**
     * Get component status
     *
     * @return array
     */
    public function get_status()
    {
        return [
            'id' => $this->component_id,
            'name' => $this->component_name,
            'version' => $this->component_version,
            'description' => $this->component_description,
            'is_active' => $this->is_active,
            'dependencies' => $this->dependencies,
            'dependencies_met' => !is_wp_error($this->check_dependencies()),
            'requirements_met' => !is_wp_error($this->check_requirements()),
        ];
    }

    /**
     * Install component
     * 
     * Can be overridden by child classes
     *
     * @return bool|WP_Error
     */
    public function install()
    {
        // Run component-specific installation
        $installation_result = $this->on_install();
        if (is_wp_error($installation_result)) {
            return $installation_result;
        }

        $this->logger->info('Component installed successfully', [
            'component_id' => $this->component_id,
        ]);

        return true;
    }

    /**
     * Uninstall component
     * 
     * Can be overridden by child classes
     *
     * @return bool|WP_Error
     */
    public function uninstall()
    {
        // Deactivate first
        $this->deactivate();

        // Run component-specific uninstallation
        $uninstallation_result = $this->on_uninstall();
        if (is_wp_error($uninstallation_result)) {
            return $uninstallation_result;
        }

        // Clean up settings
        $option_name = 'chatshop_' . $this->component_id . '_settings';
        delete_option($option_name);

        $this->logger->info('Component uninstalled successfully', [
            'component_id' => $this->component_id,
        ]);

        return true;
    }

    /**
     * Component installation hook
     * 
     * Can be overridden by child classes
     *
     * @return bool|WP_Error
     */
    protected function on_install()
    {
        return true;
    }

    /**
     * Component uninstallation hook
     * 
     * Can be overridden by child classes
     *
     * @return bool|WP_Error
     */
    protected function on_uninstall()
    {
        return true;
    }

    /**
     * Update component
     * 
     * Can be overridden by child classes
     *
     * @param string $old_version Previous version
     * @param string $new_version New version
     * @return bool|WP_Error
     */
    public function update($old_version, $new_version)
    {
        // Run component-specific update
        $update_result = $this->on_update($old_version, $new_version);
        if (is_wp_error($update_result)) {
            return $update_result;
        }

        // Update version
        $this->component_version = $new_version;

        $this->logger->info('Component updated successfully', [
            'component_id' => $this->component_id,
            'old_version' => $old_version,
            'new_version' => $new_version,
        ]);

        return true;
    }

    /**
     * Component update hook
     * 
     * Can be overridden by child classes
     *
     * @param string $old_version Previous version
     * @param string $new_version New version
     * @return bool|WP_Error
     */
    protected function on_update($old_version, $new_version)
    {
        return true;
    }

    /**
     * Get component info for display
     *
     * @return array
     */
    public function get_info()
    {
        return [
            'id' => $this->component_id,
            'name' => $this->component_name,
            'version' => $this->component_version,
            'description' => $this->component_description,
            'is_active' => $this->is_active,
            'dependencies' => $this->dependencies,
            'requirements' => $this->get_requirements(),
            'status' => $this->get_status(),
        ];
    }

    /**
     * Validate component configuration
     *
     * @return bool|WP_Error
     */
    public function validate_configuration()
    {
        // Check dependencies
        $dependency_check = $this->check_dependencies();
        if (is_wp_error($dependency_check)) {
            return $dependency_check;
        }

        // Check requirements
        $requirements_check = $this->check_requirements();
        if (is_wp_error($requirements_check)) {
            return $requirements_check;
        }

        // Run component-specific validation
        return $this->on_validate_configuration();
    }

    /**
     * Component configuration validation hook
     * 
     * Can be overridden by child classes
     *
     * @return bool|WP_Error
     */
    protected function on_validate_configuration()
    {
        return true;
    }
}
