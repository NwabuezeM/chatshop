<?php

/**
 * ChatShop Settings Field Renderer - Complete
 *
 * File: admin/class-chatshop-settings-renderer.php
 *
 * @package ChatShop
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatShop Settings Field Renderer Class
 */
class ChatShop_Settings_Renderer
{

    /**
     * Settings instance
     *
     * @var ChatShop_Settings
     */
    private $settings;

    /**
     * Constructor
     *
     * @param ChatShop_Settings $settings
     */
    public function __construct($settings)
    {
        $this->settings = $settings;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook)
    {
        if (strpos($hook, 'chatshop') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');

        wp_localize_script('jquery', 'chatshopRenderer', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('chatshop_renderer_nonce'),
            'strings' => array(
                'testing' => __('Testing...', 'chatshop'),
                'testSuccess' => __('Test successful!', 'chatshop'),
                'testFailed' => __('Test failed:', 'chatshop'),
            ),
        ));
    }

    /**
     * Render field
     *
     * @param string $name
     * @param mixed $value
     * @param array $field
     * @param string $tab
     */
    public function render_field($name, $value, $field, $tab = '')
    {
        $field_type = isset($field['type']) ? $field['type'] : 'text';
        $depends_class = $this->get_dependency_class($field, $tab);

        echo '<div class="chatshop-field-wrapper chatshop-field-' . esc_attr($field_type) . $depends_class . '">';

        switch ($field_type) {
            case 'text':
            case 'email':
            case 'url':
                $this->render_text_field($name, $value, $field);
                break;
            case 'password':
                $this->render_password_field($name, $value, $field);
                break;
            case 'number':
                $this->render_number_field($name, $value, $field);
                break;
            case 'textarea':
                $this->render_textarea_field($name, $value, $field);
                break;
            case 'checkbox':
                $this->render_checkbox_field($name, $value, $field);
                break;
            case 'select':
                $this->render_select_field($name, $value, $field);
                break;
            case 'multiselect':
                $this->render_multiselect_field($name, $value, $field);
                break;
            case 'color':
                $this->render_color_field($name, $value, $field);
                break;
            case 'api_key':
                $this->render_api_key_field($name, $value, $field);
                break;
            default:
                $this->render_text_field($name, $value, $field);
                break;
        }

        if (!empty($field['description'])) {
            echo '<p class="description">' . wp_kses_post($field['description']) . '</p>';
        }

        echo '<div class="chatshop-field-validation" style="display: none;"></div>';
        echo '</div>';
    }

    /**
     * Get dependency class for conditional fields
     */
    private function get_dependency_class($field, $tab)
    {
        if (!isset($field['depends_on'])) {
            return '';
        }

        $dependency_value = $this->settings->get($field['depends_on'], false, $tab);
        $expected_value = isset($field['depends_value']) ? $field['depends_value'] : true;

        return ($dependency_value == $expected_value) ? '' : ' chatshop-hidden';
    }

    /**
     * Render text field
     */
    public function render_text_field($name, $value, $field)
    {
        $type = isset($field['type']) ? $field['type'] : 'text';
        $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';
        $classes = isset($field['class']) ? $field['class'] : 'regular-text';

        printf(
            '<input type="%s" name="chatshop_settings[%s]" id="%s" value="%s" placeholder="%s" class="%s" />',
            esc_attr($type),
            esc_attr($name),
            esc_attr($name),
            esc_attr($value),
            esc_attr($placeholder),
            esc_attr($classes)
        );
    }

    /**
     * Render password field
     */
    public function render_password_field($name, $value, $field)
    {
        $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';
        $classes = isset($field['class']) ? $field['class'] : 'regular-text';

        printf(
            '<input type="password" name="chatshop_settings[%s]" id="%s" value="%s" placeholder="%s" class="%s" />',
            esc_attr($name),
            esc_attr($name),
            esc_attr($value),
            esc_attr($placeholder),
            esc_attr($classes)
        );

        printf(
            '<button type="button" class="chatshop-password-toggle button button-small" data-target="%s">%s</button>',
            esc_attr($name),
            __('Show', 'chatshop')
        );
    }

    /**
     * Render number field
     */
    public function render_number_field($name, $value, $field)
    {
        $min = isset($field['min']) ? $field['min'] : '';
        $max = isset($field['max']) ? $field['max'] : '';
        $step = isset($field['step']) ? $field['step'] : '1';
        $classes = isset($field['class']) ? $field['class'] : 'small-text';

        printf(
            '<input type="number" name="chatshop_settings[%s]" id="%s" value="%s" min="%s" max="%s" step="%s" class="%s" />',
            esc_attr($name),
            esc_attr($name),
            esc_attr($value),
            esc_attr($min),
            esc_attr($max),
            esc_attr($step),
            esc_attr($classes)
        );
    }

    /**
     * Render textarea field
     */
    public function render_textarea_field($name, $value, $field)
    {
        $rows = isset($field['rows']) ? intval($field['rows']) : 5;
        $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';
        $classes = isset($field['class']) ? $field['class'] : 'large-text';

        printf(
            '<textarea name="chatshop_settings[%s]" id="%s" rows="%d" class="%s" placeholder="%s">%s</textarea>',
            esc_attr($name),
            esc_attr($name),
            $rows,
            esc_attr($classes),
            esc_attr($placeholder),
            esc_textarea($value)
        );
    }

    /**
     * Render checkbox field
     */
    public function render_checkbox_field($name, $value, $field)
    {
        $label = isset($field['label']) ? $field['label'] : '';

        printf(
            '<label for="%s"><input type="checkbox" name="chatshop_settings[%s]" id="%s" value="1"%s /> %s</label>',
            esc_attr($name),
            esc_attr($name),
            esc_attr($name),
            checked(1, $value, false),
            esc_html($label)
        );
    }

    /**
     * Render select field
     */
    public function render_select_field($name, $value, $field)
    {
        $classes = isset($field['class']) ? $field['class'] : '';

        printf('<select name="chatshop_settings[%s]" id="%s" class="%s">', esc_attr($name), esc_attr($name), esc_attr($classes));

        if (isset($field['placeholder'])) {
            printf('<option value="">%s</option>', esc_html($field['placeholder']));
        }

        if (!empty($field['options'])) {
            foreach ($field['options'] as $option_value => $option_label) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($option_value),
                    selected($value, $option_value, false),
                    esc_html($option_label)
                );
            }
        }

        echo '</select>';
    }

    /**
     * Render multiselect field
     */
    public function render_multiselect_field($name, $value, $field)
    {
        $selected_values = is_array($value) ? $value : array();
        $size = isset($field['size']) ? intval($field['size']) : 5;
        $classes = isset($field['class']) ? $field['class'] : '';

        printf(
            '<select name="chatshop_settings[%s][]" id="%s" multiple="multiple" size="%d" class="%s">',
            esc_attr($name),
            esc_attr($name),
            $size,
            esc_attr($classes)
        );

        if (!empty($field['options'])) {
            foreach ($field['options'] as $option_value => $option_label) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($option_value),
                    in_array($option_value, $selected_values) ? ' selected="selected"' : '',
                    esc_html($option_label)
                );
            }
        }

        echo '</select>';
    }

    /**
     * Render color field
     */
    public function render_color_field($name, $value, $field)
    {
        $default_color = isset($field['default']) ? $field['default'] : '#ffffff';

        printf(
            '<input type="text" name="chatshop_settings[%s]" id="%s" value="%s" class="chatshop-color-picker" data-default-color="%s" />',
            esc_attr($name),
            esc_attr($name),
            esc_attr($value),
            esc_attr($default_color)
        );

        // Initialize color picker
        echo '<script>
        jQuery(document).ready(function($) {
            if ($.fn.wpColorPicker) {
                $(".chatshop-color-picker").wpColorPicker();
            }
        });
        </script>';
    }

    /**
     * Render API key field with test functionality
     */
    public function render_api_key_field($name, $value, $field)
    {
        $test_component = isset($field['test_component']) ? $field['test_component'] : '';

        printf(
            '<input type="password" name="chatshop_settings[%s]" id="%s" value="%s" class="regular-text chatshop-api-key" />',
            esc_attr($name),
            esc_attr($name),
            esc_attr($value)
        );

        if ($test_component) {
            printf(
                '<button type="button" class="button chatshop-test-api" data-component="%s" data-field="%s">%s</button>',
                esc_attr($test_component),
                esc_attr($name),
                __('Test Connection', 'chatshop')
            );
        }

        printf(
            '<button type="button" class="chatshop-password-toggle button button-small" data-target="%s">%s</button>',
            esc_attr($name),
            __('Show', 'chatshop')
        );
    }

    /**
     * Validate field value
     */
    public function validate_field($field_type, $value, $field)
    {
        switch ($field_type) {
            case 'email':
                return $this->validate_email_field($value, $field);
            case 'url':
                return $this->validate_url_field($value, $field);
            case 'number':
                return $this->validate_number_field($value, $field);
            default:
                return $this->validate_text_field($value, $field);
        }
    }

    /**
     * Validate text field
     */
    public function validate_text_field($value, $field)
    {
        if (isset($field['required']) && $field['required'] && empty($value)) {
            return array('valid' => false, 'message' => __('This field is required.', 'chatshop'));
        }

        if (isset($field['minlength']) && strlen($value) < $field['minlength']) {
            return array('valid' => false, 'message' => sprintf(__('Minimum length is %d characters.', 'chatshop'), $field['minlength']));
        }

        if (isset($field['maxlength']) && strlen($value) > $field['maxlength']) {
            return array('valid' => false, 'message' => sprintf(__('Maximum length is %d characters.', 'chatshop'), $field['maxlength']));
        }

        return array('valid' => true, 'message' => '');
    }

    /**
     * Validate email field
     */
    public function validate_email_field($value, $field)
    {
        if (empty($value)) {
            if (isset($field['required']) && $field['required']) {
                return array('valid' => false, 'message' => __('Email address is required.', 'chatshop'));
            }
            return array('valid' => true, 'message' => '');
        }

        if (!is_email($value)) {
            return array('valid' => false, 'message' => __('Please enter a valid email address.', 'chatshop'));
        }

        return array('valid' => true, 'message' => '');
    }

    /**
     * Validate URL field
     */
    public function validate_url_field($value, $field)
    {
        if (empty($value)) {
            if (isset($field['required']) && $field['required']) {
                return array('valid' => false, 'message' => __('URL is required.', 'chatshop'));
            }
            return array('valid' => true, 'message' => '');
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return array('valid' => false, 'message' => __('Please enter a valid URL.', 'chatshop'));
        }

        return array('valid' => true, 'message' => '');
    }

    /**
     * Validate number field
     */
    public function validate_number_field($value, $field)
    {
        if (!is_numeric($value)) {
            return array('valid' => false, 'message' => __('Please enter a valid number.', 'chatshop'));
        }

        $number = floatval($value);

        if (isset($field['min']) && $number < $field['min']) {
            return array('valid' => false, 'message' => sprintf(__('Value must be at least %s.', 'chatshop'), $field['min']));
        }

        if (isset($field['max']) && $number > $field['max']) {
            return array('valid' => false, 'message' => sprintf(__('Value must not exceed %s.', 'chatshop'), $field['max']));
        }

        return array('valid' => true, 'message' => '');
    }
}
