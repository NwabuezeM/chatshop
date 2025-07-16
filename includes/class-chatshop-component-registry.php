<?php

/**
 * Component Registry Class
 *
 * Manages registration and access to all plugin components
 *
 * @package    ChatShop
 * @subpackage ChatShop/includes
 * @since      1.0.0
 */

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Component Registry Class
 *
 * This class maintains a registry of all active components
 * and provides methods to access them throughout the plugin.
 *
 * @since      1.0.0
 * @package    ChatShop
 * @subpackage ChatShop/includes
 */
class ChatShop_Component_Registry
{

    /**
     * The single instance of the class
     *
     * @var ChatShop_Component_Registry
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * Registered components
     *
     * @var array
     * @since 1.0.0
     */
    private $components = array();

    /**
     * Component metadata
     *
     * @var array
     * @since 1.0.0
     */
    private $component_metadata = array();

    /**
     * Component dependencies
     *
     * @var array
     * @since 1.0.0
     */
    private $dependencies = array();

    /**
     * Component status
     *
     * @var array
     * @since 1.0.0
     */
    private $component_status = array();

    /**
     * Main instance
     *
     * @return ChatShop_Component_Registry
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the registry
     */
    private function init()
    {
        // Set up default component metadata
        $this->setup_default_metadata();

        // Load component configurations
        $this->load_component_configs();

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('chatshop_register_components', array($this, 'register_core_components'), 5);
        add_filter('chatshop_component_enabled', array($this, 'filter_component_enabled'), 10, 2);
    }

    /**
     * Register a component
     *
     * @param string $component_id Unique component identifier
     * @param object $component Component instance
     * @param array $metadata Optional component metadata
     * @return bool
     */
    public function register($component_id, $component, $metadata = array())
    {
        // Validate component
        if (! $this->validate_component($component)) {
            ChatShop_Logger::log('error', 'Invalid component registration attempt: ' . $component_id);
            return false;
        }

        // Check if already registered
        if ($this->is_registered($component_id)) {
            ChatShop_Logger::log('warning', 'Component already registered: ' . $component_id);
            return false;
        }

        // Check dependencies
        if (! $this->check_dependencies($component_id, $metadata)) {
            ChatShop_Logger::log('error', 'Component dependencies not met: ' . $component_id);
            return false;
        }

        // Register the component
        $this->components[$component_id] = $component;
        $this->component_metadata[$component_id] = wp_parse_args($metadata, $this->get_default_metadata());
        $this->component_status[$component_id] = 'active';

        // Initialize component if it has an init method
        if (method_exists($component, 'init')) {
            $component->init();
        }

        // Fire registration hook
        do_action('chatshop_component_registered', $component_id, $component);

        ChatShop_Logger::log('info', 'Component registered successfully: ' . $component_id);
        return true;
    }

    /**
     * Unregister a component
     *
     * @param string $component_id Component ID
     * @return bool
     */
    public function unregister($component_id)
    {
        if (! $this->is_registered($component_id)) {
            return false;
        }

        // Check if other components depend on this one
        if ($this->has_dependents($component_id)) {
            ChatShop_Logger::log('error', 'Cannot unregister component with dependents: ' . $component_id);
            return false;
        }

        // Call cleanup method if exists
        $component = $this->get($component_id);
        if ($component && method_exists($component, 'cleanup')) {
            $component->cleanup();
        }

        // Remove from registry
        unset($this->components[$component_id]);
        unset($this->component_metadata[$component_id]);
        unset($this->component_status[$component_id]);

        // Fire unregistration hook
        do_action('chatshop_component_unregistered', $component_id);

        return true;
    }

    /**
     * Get a component
     *
     * @param string $component_id Component ID
     * @return object|null
     */
    public function get($component_id)
    {
        return isset($this->components[$component_id]) ? $this->components[$component_id] : null;
    }

    /**
     * Get all components
     *
     * @return array
     */
    public function get_all()
    {
        return $this->components;
    }

    /**
     * Get components by type
     *
     * @param string $type Component type
     * @return array
     */
    public function get_by_type($type)
    {
        $components = array();

        foreach ($this->component_metadata as $id => $metadata) {
            if (isset($metadata['type']) && $metadata['type'] === $type) {
                $components[$id] = $this->components[$id];
            }
        }

        return $components;
    }

    /**
     * Check if component is registered
     *
     * @param string $component_id Component ID
     * @return bool
     */
    public function is_registered($component_id)
    {
        return isset($this->components[$component_id]);
    }

    /**
     * Get component metadata
     *
     * @param string $component_id Component ID
     * @return array|null
     */
    public function get_metadata($component_id)
    {
        return isset($this->component_metadata[$component_id]) ? $this->component_metadata[$component_id] : null;
    }

    /**
     * Update component metadata
     *
     * @param string $component_id Component ID
     * @param array $metadata New metadata
     * @return bool
     */
    public function update_metadata($component_id, $metadata)
    {
        if (! $this->is_registered($component_id)) {
            return false;
        }

        $this->component_metadata[$component_id] = wp_parse_args($metadata, $this->component_metadata[$component_id]);
        return true;
    }

    /**
     * Get component status
     *
     * @param string $component_id Component ID
     * @return string|null
     */
    public function get_status($component_id)
    {
        return isset($this->component_status[$component_id]) ? $this->component_status[$component_id] : null;
    }

    /**
     * Set component status
     *
     * @param string $component_id Component ID
     * @param string $status New status
     * @return bool
     */
    public function set_status($component_id, $status)
    {
        if (! $this->is_registered($component_id)) {
            return false;
        }

        $allowed_statuses = array('active', 'inactive', 'error', 'loading');
        if (! in_array($status, $allowed_statuses, true)) {
            return false;
        }

        $old_status = $this->component_status[$component_id];
        $this->component_status[$component_id] = $status;

        // Fire status change hook
        do_action('chatshop_component_status_changed', $component_id, $status, $old_status);

        return true;
    }

    /**
     * Enable a component
     *
     * @param string $component_id Component ID
     * @return bool
     */
    public function enable($component_id)
    {
        if (! $this->is_registered($component_id)) {
            return false;
        }

        $component = $this->get($component_id);
        if ($component && method_exists($component, 'enable')) {
            $component->enable();
        }

        return $this->set_status($component_id, 'active');
    }

    /**
     * Disable a component
     *
     * @param string $component_id Component ID
     * @return bool
     */
    public function disable($component_id)
    {
        if (! $this->is_registered($component_id)) {
            return false;
        }

        $component = $this->get($component_id);
        if ($component && method_exists($component, 'disable')) {
            $component->disable();
        }

        return $this->set_status($component_id, 'inactive');
    }

    /**
     * Validate component
     *
     * @param object $component Component to validate
     * @return bool
     */
    private function validate_component($component)
    {
        // Check if it's an object
        if (! is_object($component)) {
            return false;
        }

        // Check if it implements the base component interface
        if (! ($component instanceof ChatShop_Component)) {
            // Allow components that don't extend the base class but have required methods
            $required_methods = array('get_id', 'get_name');
            foreach ($required_methods as $method) {
                if (! method_exists($component, $method)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check component dependencies
     *
     * @param string $component_id Component ID
     * @param array $metadata Component metadata
     * @return bool
     */
    private function check_dependencies($component_id, $metadata)
    {
        if (! isset($metadata['dependencies']) || empty($metadata['dependencies'])) {
            return true;
        }

        foreach ($metadata['dependencies'] as $dependency) {
            if (! $this->is_registered($dependency)) {
                return false;
            }

            // Check if dependency is active
            if ($this->get_status($dependency) !== 'active') {
                return false;
            }
        }

        // Store dependencies for later reference
        $this->dependencies[$component_id] = $metadata['dependencies'];

        return true;
    }

    /**
     * Check if component has dependents
     *
     * @param string $component_id Component ID
     * @return bool
     */
    private function has_dependents($component_id)
    {
        foreach ($this->dependencies as $id => $deps) {
            if (in_array($component_id, $deps, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Setup default metadata
     */
    private function setup_default_metadata()
    {
        $this->default_metadata = array(
            'version' => '1.0.0',
            'author' => 'ChatShop',
            'description' => '',
            'type' => 'general',
            'dependencies' => array(),
            'settings' => array(),
            'capabilities' => array(),
        );
    }

    /**
     * Get default metadata
     *
     * @return array
     */
    private function get_default_metadata()
    {
        return $this->default_metadata;
    }

    /**
     * Load component configurations
     */
    private function load_component_configs()
    {
        $configs = get_option('chatshop_component_configs', array());

        foreach ($configs as $component_id => $config) {
            if (isset($config['enabled']) && ! $config['enabled']) {
                $this->component_status[$component_id] = 'inactive';
            }
        }
    }

    /**
     * Register core components
     */
    public function register_core_components()
    {
        // This method will be called by the component loader
        // to register core components
    }

    /**
     * Filter component enabled status
     *
     * @param bool $enabled Current enabled status
     * @param string $component_id Component ID
     * @return bool
     */
    public function filter_component_enabled($enabled, $component_id)
    {
        // Check if component is in inactive status
        if (isset($this->component_status[$component_id]) && $this->component_status[$component_id] === 'inactive') {
            return false;
        }

        return $enabled;
    }

    /**
     * Get component dependencies
     *
     * @param string $component_id Component ID
     * @return array
     */
    public function get_dependencies($component_id)
    {
        return isset($this->dependencies[$component_id]) ? $this->dependencies[$component_id] : array();
    }

    /**
     * Get all component statuses
     *
     * @return array
     */
    public function get_all_statuses()
    {
        return $this->component_status;
    }

    /**
     * Export registry data
     *
     * @return array
     */
    public function export()
    {
        return array(
            'components' => array_keys($this->components),
            'metadata' => $this->component_metadata,
            'status' => $this->component_status,
            'dependencies' => $this->dependencies,
        );
    }

    /**
     * Get component statistics
     *
     * @return array
     */
    public function get_statistics()
    {
        $stats = array(
            'total' => count($this->components),
            'active' => 0,
            'inactive' => 0,
            'error' => 0,
            'by_type' => array(),
        );

        foreach ($this->component_status as $status) {
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        foreach ($this->component_metadata as $metadata) {
            $type = isset($metadata['type']) ? $metadata['type'] : 'general';
            if (! isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;
        }

        return $stats;
    }
}
