<?php

/**
 * Abstract API Client Class
 *
 * Base class for all API clients in the ChatShop plugin.
 * Provides common functionality for HTTP requests, authentication,
 * error handling, and logging.
 *
 * @package ChatShop
 * @subpackage Core\Abstracts
 * @since 1.0.0
 */

namespace ChatShop\Core\Abstracts;

use ChatShop\Core\ChatShop_Logger;
use ChatShop\Core\ChatShop_Security;
use ChatShop\Core\ChatShop_Cache;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract ChatShop API Client
 * 
 * Provides base functionality for all API integrations
 */
abstract class Abstract_ChatShop_API_Client
{

    /**
     * API Base URL
     *
     * @var string
     */
    protected $base_url = '';

    /**
     * API Version
     *
     * @var string
     */
    protected $api_version = '';

    /**
     * API Key/Token
     *
     * @var string
     */
    protected $api_key = '';

    /**
     * API Secret
     *
     * @var string
     */
    protected $api_secret = '';

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    protected $timeout = 30;

    /**
     * Maximum number of retry attempts
     *
     * @var int
     */
    protected $max_retries = 3;

    /**
     * Retry delay in seconds
     *
     * @var int
     */
    protected $retry_delay = 1;

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    protected $logger;

    /**
     * Cache instance
     *
     * @var ChatShop_Cache
     */
    protected $cache;

    /**
     * Default request headers
     *
     * @var array
     */
    protected $default_headers = [];

    /**
     * User agent string
     *
     * @var string
     */
    protected $user_agent = '';

    /**
     * API rate limit tracking
     *
     * @var array
     */
    protected $rate_limits = [];

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct($config = [])
    {
        $this->logger = new ChatShop_Logger(static::class);
        $this->cache = new ChatShop_Cache();

        $this->init_config($config);
        $this->init_default_headers();
        $this->init_user_agent();
    }

    /**
     * Initialize configuration
     *
     * @param array $config Configuration array
     * @return void
     */
    protected function init_config($config)
    {
        if (isset($config['base_url'])) {
            $this->base_url = rtrim($config['base_url'], '/');
        }

        if (isset($config['api_version'])) {
            $this->api_version = $config['api_version'];
        }

        if (isset($config['api_key'])) {
            $this->api_key = $config['api_key'];
        }

        if (isset($config['api_secret'])) {
            $this->api_secret = $config['api_secret'];
        }

        if (isset($config['timeout'])) {
            $this->timeout = absint($config['timeout']);
        }

        if (isset($config['max_retries'])) {
            $this->max_retries = absint($config['max_retries']);
        }

        if (isset($config['retry_delay'])) {
            $this->retry_delay = absint($config['retry_delay']);
        }
    }

    /**
     * Initialize default headers
     *
     * @return void
     */
    protected function init_default_headers()
    {
        $this->default_headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Add authentication headers if available
        if (!empty($this->api_key)) {
            $this->default_headers = array_merge(
                $this->default_headers,
                $this->get_auth_headers()
            );
        }
    }

    /**
     * Initialize user agent
     *
     * @return void
     */
    protected function init_user_agent()
    {
        $this->user_agent = sprintf(
            'ChatShop/%s WordPress/%s PHP/%s',
            CHATSHOP_VERSION,
            get_bloginfo('version'),
            PHP_VERSION
        );
    }

    /**
     * Get authentication headers
     * 
     * Must be implemented by child classes
     *
     * @return array
     */
    abstract protected function get_auth_headers();

    /**
     * Validate API credentials
     * 
     * Must be implemented by child classes
     *
     * @return bool|WP_Error
     */
    abstract public function validate_credentials();

    /**
     * Get API endpoint URL
     * 
     * Must be implemented by child classes
     *
     * @param string $endpoint
     * @return string
     */
    abstract protected function get_endpoint_url($endpoint);

    /**
     * Make GET request
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    public function get($endpoint, $params = [], $headers = [])
    {
        $url = $this->get_endpoint_url($endpoint);

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $this->make_request('GET', $url, null, $headers);
    }

    /**
     * Make POST request
     *
     * @param string $endpoint API endpoint
     * @param array $data Request body data
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    public function post($endpoint, $data = [], $headers = [])
    {
        $url = $this->get_endpoint_url($endpoint);
        return $this->make_request('POST', $url, $data, $headers);
    }

    /**
     * Make PUT request
     *
     * @param string $endpoint API endpoint
     * @param array $data Request body data
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    public function put($endpoint, $data = [], $headers = [])
    {
        $url = $this->get_endpoint_url($endpoint);
        return $this->make_request('PUT', $url, $data, $headers);
    }

    /**
     * Make PATCH request
     *
     * @param string $endpoint API endpoint
     * @param array $data Request body data
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    public function patch($endpoint, $data = [], $headers = [])
    {
        $url = $this->get_endpoint_url($endpoint);
        return $this->make_request('PATCH', $url, $data, $headers);
    }

    /**
     * Make DELETE request
     *
     * @param string $endpoint API endpoint
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    public function delete($endpoint, $headers = [])
    {
        $url = $this->get_endpoint_url($endpoint);
        return $this->make_request('DELETE', $url, null, $headers);
    }

    /**
     * Make HTTP request with retry logic
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array|null $data Request body data
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    protected function make_request($method, $url, $data = null, $headers = [])
    {
        $attempt = 1;

        while ($attempt <= $this->max_retries) {
            // Check rate limits before making request
            if (!$this->check_rate_limit()) {
                return new \WP_Error(
                    'rate_limit_exceeded',
                    __('API rate limit exceeded. Please try again later.', 'chatshop')
                );
            }

            $response = $this->execute_request($method, $url, $data, $headers);

            // If successful, return response
            if (!is_wp_error($response)) {
                $this->log_successful_request($method, $url, $response);
                return $response;
            }

            // Log the error
            $this->log_failed_request($method, $url, $response, $attempt);

            // Check if error is retryable
            if (!$this->is_retryable_error($response)) {
                return $response;
            }

            // Wait before retry (exponential backoff)
            if ($attempt < $this->max_retries) {
                sleep($this->retry_delay * $attempt);
            }

            $attempt++;
        }

        return $response;
    }

    /**
     * Execute HTTP request
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array|null $data Request body data
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    protected function execute_request($method, $url, $data = null, $headers = [])
    {
        // Merge headers
        $request_headers = array_merge($this->default_headers, $headers);

        // Prepare request arguments
        $args = [
            'method' => strtoupper($method),
            'headers' => $request_headers,
            'timeout' => $this->timeout,
            'user-agent' => $this->user_agent,
            'sslverify' => true,
        ];

        // Add body data for POST, PUT, PATCH requests
        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            if (
                isset($request_headers['Content-Type']) &&
                $request_headers['Content-Type'] === 'application/json'
            ) {
                $args['body'] = wp_json_encode($data);
            } else {
                $args['body'] = $data;
            }
        }

        // Make the request
        $response = wp_remote_request($url, $args);

        // Handle WordPress errors
        if (is_wp_error($response)) {
            return $response;
        }

        // Parse response
        return $this->parse_response($response);
    }

    /**
     * Parse HTTP response
     *
     * @param array $response WordPress HTTP response
     * @return array|WP_Error
     */
    protected function parse_response($response)
    {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);

        // Update rate limit tracking
        $this->update_rate_limits($headers);

        // Check for HTTP errors
        if ($status_code >= 400) {
            return $this->handle_error_response($status_code, $body, $headers);
        }

        // Parse JSON response
        $parsed_body = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'invalid_json',
                __('Invalid JSON response from API', 'chatshop'),
                ['response_body' => $body]
            );
        }

        return [
            'status_code' => $status_code,
            'headers' => $headers,
            'body' => $parsed_body,
        ];
    }

    /**
     * Handle error response
     *
     * @param int $status_code HTTP status code
     * @param string $body Response body
     * @param array $headers Response headers
     * @return WP_Error
     */
    protected function handle_error_response($status_code, $body, $headers)
    {
        $error_data = [
            'status_code' => $status_code,
            'headers' => $headers,
            'response_body' => $body,
        ];

        // Try to parse error message from JSON
        $parsed_body = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($parsed_body['message'])) {
            $error_message = $parsed_body['message'];
        } else {
            $error_message = sprintf(
                __('API request failed with status code %d', 'chatshop'),
                $status_code
            );
        }

        // Determine error code based on status
        $error_code = $this->get_error_code_from_status($status_code);

        return new \WP_Error($error_code, $error_message, $error_data);
    }

    /**
     * Get error code from HTTP status
     *
     * @param int $status_code HTTP status code
     * @return string
     */
    protected function get_error_code_from_status($status_code)
    {
        switch ($status_code) {
            case 400:
                return 'bad_request';
            case 401:
                return 'unauthorized';
            case 403:
                return 'forbidden';
            case 404:
                return 'not_found';
            case 422:
                return 'validation_error';
            case 429:
                return 'rate_limit_exceeded';
            case 500:
                return 'internal_server_error';
            case 502:
                return 'bad_gateway';
            case 503:
                return 'service_unavailable';
            case 504:
                return 'gateway_timeout';
            default:
                return 'api_error';
        }
    }

    /**
     * Check if error is retryable
     *
     * @param WP_Error $error Error object
     * @return bool
     */
    protected function is_retryable_error($error)
    {
        if (!is_wp_error($error)) {
            return false;
        }

        $retryable_codes = [
            'rate_limit_exceeded',
            'internal_server_error',
            'bad_gateway',
            'service_unavailable',
            'gateway_timeout',
        ];

        return in_array($error->get_error_code(), $retryable_codes);
    }

    /**
     * Check rate limits
     *
     * @return bool
     */
    protected function check_rate_limit()
    {
        // Basic implementation - can be overridden by child classes
        return true;
    }

    /**
     * Update rate limit tracking
     *
     * @param array $headers Response headers
     * @return void
     */
    protected function update_rate_limits($headers)
    {
        // Can be overridden by child classes to implement specific rate limit tracking
    }

    /**
     * Log successful request
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array $response Response data
     * @return void
     */
    protected function log_successful_request($method, $url, $response)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->logger->debug('API request successful', [
                'method' => $method,
                'url' => $this->sanitize_url_for_log($url),
                'status_code' => $response['status_code'],
            ]);
        }
    }

    /**
     * Log failed request
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param WP_Error $error Error object
     * @param int $attempt Attempt number
     * @return void
     */
    protected function log_failed_request($method, $url, $error, $attempt)
    {
        $this->logger->error('API request failed', [
            'method' => $method,
            'url' => $this->sanitize_url_for_log($url),
            'error_code' => $error->get_error_code(),
            'error_message' => $error->get_error_message(),
            'attempt' => $attempt,
            'max_retries' => $this->max_retries,
        ]);
    }

    /**
     * Sanitize URL for logging (remove sensitive data)
     *
     * @param string $url URL to sanitize
     * @return string
     */
    protected function sanitize_url_for_log($url)
    {
        // Remove query parameters that might contain sensitive data
        $parsed = parse_url($url);
        $sanitized = $parsed['scheme'] . '://' . $parsed['host'];

        if (isset($parsed['port'])) {
            $sanitized .= ':' . $parsed['port'];
        }

        if (isset($parsed['path'])) {
            $sanitized .= $parsed['path'];
        }

        return $sanitized;
    }

    /**
     * Get cached response
     *
     * @param string $cache_key Cache key
     * @return mixed|false
     */
    protected function get_cached_response($cache_key)
    {
        return $this->cache->get($cache_key);
    }

    /**
     * Set cached response
     *
     * @param string $cache_key Cache key
     * @param mixed $data Data to cache
     * @param int $expiration Cache expiration in seconds
     * @return bool
     */
    protected function set_cached_response($cache_key, $data, $expiration = 300)
    {
        return $this->cache->set($cache_key, $data, $expiration);
    }

    /**
     * Generate cache key for request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return string
     */
    protected function generate_cache_key($method, $endpoint, $params = [])
    {
        $key_data = [
            'method' => $method,
            'endpoint' => $endpoint,
            'params' => $params,
            'api_version' => $this->api_version,
        ];

        return 'chatshop_api_' . md5(serialize($key_data));
    }

    /**
     * Set API credentials
     *
     * @param string $api_key API key
     * @param string $api_secret API secret (optional)
     * @return void
     */
    public function set_credentials($api_key, $api_secret = '')
    {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;

        // Reinitialize headers with new credentials
        $this->init_default_headers();
    }

    /**
     * Get API key
     *
     * @return string
     */
    public function get_api_key()
    {
        return $this->api_key;
    }

    /**
     * Get API secret
     *
     * @return string
     */
    public function get_api_secret()
    {
        return $this->api_secret;
    }

    /**
     * Set timeout
     *
     * @param int $timeout Timeout in seconds
     * @return void
     */
    public function set_timeout($timeout)
    {
        $this->timeout = absint($timeout);
    }

    /**
     * Get timeout
     *
     * @return int
     */
    public function get_timeout()
    {
        return $this->timeout;
    }

    /**
     * Set max retries
     *
     * @param int $max_retries Maximum number of retries
     * @return void
     */
    public function set_max_retries($max_retries)
    {
        $this->max_retries = absint($max_retries);
    }

    /**
     * Get max retries
     *
     * @return int
     */
    public function get_max_retries()
    {
        return $this->max_retries;
    }

    /**
     * Test API connectivity
     *
     * @return bool|WP_Error
     */
    public function test_connection()
    {
        // This method should be overridden by child classes
        // to implement API-specific connectivity tests
        return $this->validate_credentials();
    }

    /**
     * Get API status/health
     *
     * @return array|WP_Error
     */
    public function get_api_status()
    {
        // This method can be overridden by child classes
        // to implement API-specific status checks
        return [
            'status' => 'unknown',
            'message' => __('API status check not implemented', 'chatshop'),
        ];
    }
}
