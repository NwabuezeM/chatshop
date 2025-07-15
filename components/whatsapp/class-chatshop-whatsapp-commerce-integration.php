<?php
/**
 * WhatsApp Commerce Integration
 * 
 * Enables direct product sales through WhatsApp with catalog sharing,
 * product browsing, cart management, and seamless checkout via payment links.
 * 
 * File: components/whatsapp/class-chatshop-whatsapp-commerce-integration.php
 *
 * @package ChatShop
 * @subpackage WhatsApp
 * @since 1.0.0
 */

namespace ChatShop\WhatsApp;

use ChatShop\WhatsApp\ChatShop_WhatsApp_API;
use ChatShop\WhatsApp\ChatShop_Message_Templates;
use ChatShop\WhatsApp\ChatShop_WhatsApp_Payment_Link_Generator;
use ChatShop\Database\ChatShop_WhatsApp_Commerce_Table;
use ChatShop\Helper\ChatShop_Helper;
use ChatShop\Logger\ChatShop_Logger;

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp Commerce Integration class
 */
class ChatShop_WhatsApp_Commerce_Integration {

    /**
     * WhatsApp API instance
     *
     * @var ChatShop_WhatsApp_API
     */
    private $whatsapp_api;

    /**
     * Message templates instance
     *
     * @var ChatShop_Message_Templates
     */
    private $message_templates;

    /**
     * Payment link generator instance
     *
     * @var ChatShop_WhatsApp_Payment_Link_Generator
     */
    private $link_generator;

    /**
     * Database table instance
     *
     * @var ChatShop_WhatsApp_Commerce_Table
     */
    private $db_table;

    /**
     * Logger instance
     *
     * @var ChatShop_Logger
     */
    private $logger;

    /**
     * Commerce settings
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->whatsapp_api      = new ChatShop_WhatsApp_API();
        $this->message_templates = new ChatShop_Message_Templates();
        $this->link_generator    = new ChatShop_WhatsApp_Payment_Link_Generator();
        $this->db_table         = new ChatShop_WhatsApp_Commerce_Table();
        $this->logger           = new ChatShop_Logger();
        
        $this->load_settings();
        $this->init_hooks();
    }

    /**
     * Load commerce settings
     */
    private function load_settings() {
        $this->settings = array(
            'enabled'                => get_option( 'chatshop_whatsapp_commerce_enabled', 'yes' ),
            'catalog_enabled'        => get_option( 'chatshop_whatsapp_catalog_enabled', 'yes' ),
            'cart_enabled'           => get_option( 'chatshop_whatsapp_cart_enabled', 'yes' ),
            'direct_checkout'        => get_option( 'chatshop_whatsapp_direct_checkout', 'yes' ),
            'product_images'         => get_option( 'chatshop_whatsapp_product_images', 'yes' ),
            'stock_checking'         => get_option( 'chatshop_whatsapp_stock_checking', 'yes' ),
            'price_display'          => get_option( 'chatshop_whatsapp_price_display', 'yes' ),
            'categories_enabled'     => get_option( 'chatshop_whatsapp_categories_enabled', 'yes' ),
            'search_enabled'         => get_option( 'chatshop_whatsapp_search_enabled', 'yes' ),
            'recommendations'        => get_option( 'chatshop_whatsapp_recommendations', 'yes' ),
            'order_tracking'         => get_option( 'chatshop_whatsapp_order_tracking', 'yes' ),
            'customer_support'       => get_option( 'chatshop_whatsapp_customer_support', 'yes' ),
        );
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        if ( $this->settings['enabled'] !== 'yes' ) {
            return;
        }

        // WhatsApp message handling
        add_action( 'chatshop_whatsapp_message_received', array( $this, 'handle_commerce_message' ), 10, 2 );
        add_action( 'chatshop_whatsapp_interactive_message', array( $this, 'handle_interactive_message' ), 10, 2 );

        // Product and inventory hooks
        add_action( 'woocommerce_product_set_stock', array( $this, 'handle_stock_change' ), 10, 1 );
        add_action( 'woocommerce_variation_set_stock', array( $this, 'handle_stock_change' ), 10, 1 );
        add_action( 'save_post_product', array( $this, 'handle_product_update' ), 10, 1 );

        // Order hooks
        add_action( 'woocommerce_new_order', array( $this, 'handle_new_order' ), 10, 1 );
        add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 4 );

        // Scheduled tasks
        add_action( 'chatshop_sync_whatsapp_catalog', array( $this, 'sync_product_catalog' ) );
        add_action( 'chatshop_cleanup_whatsapp_sessions', array( $this, 'cleanup_expired_sessions' ) );
    }

    /**
     * Handle incoming commerce-related messages
     *
     * @param array $message Message data
     * @param array $contact Contact data
     */
    public function handle_commerce_message( $message, $contact ) {
        $phone_number = $contact['phone'] ?? '';
        $message_text = strtolower( trim( $message['text'] ?? '' ) );

        // Skip if not a commerce-related message
        if ( ! $this->is_commerce_message( $message_text ) ) {
            return;
        }

        try {
            // Get or create customer session
            $session = $this->get_customer_session( $phone_number );

            // Parse and handle the message
            $intent = $this->parse_message_intent( $message_text, $session );
            $this->handle_message_intent( $phone_number, $intent, $session, $message );

        } catch ( Exception $e ) {
            $this->logger->error( 'Error handling commerce message', array(
                'phone' => $phone_number,
                'message' => $message_text,
                'error' => $e->getMessage(),
            ) );

            $this->send_error_message( $phone_number );
        }
    }

    /**
     * Check if message is commerce-related
     *
     * @param string $message_text Message text
     * @return bool True if commerce-related
     */
    private function is_commerce_message( $message_text ) {
        $commerce_keywords = array(
            'catalog', 'products', 'shop', 'buy', 'order', 'cart', 'checkout',
            'search', 'category', 'price', 'stock', 'available', 'add', 'remove',
            'view', 'show', 'list', 'browse', 'purchase', 'payment', 'total'
        );

        foreach ( $commerce_keywords as $keyword ) {
            if ( strpos( $message_text, $keyword ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse message intent
     *
     * @param string $message_text Message text
     * @param array  $session Customer session data
     * @return array Intent data
     */
    private function parse_message_intent( $message_text, $session ) {
        $intent = array(
            'action' => 'unknown',
            'parameters' => array(),
            'context' => $session['context'] ?? 'main',
        );

        // Catalog and browsing intents
        if ( preg_match( '/\b(catalog|products|shop|browse)\b/', $message_text ) ) {
            $intent['action'] = 'show_catalog';
        } elseif ( preg_match( '/\b(categories|category)\b/', $message_text ) ) {
            $intent['action'] = 'show_categories';
        } elseif ( preg_match( '/\bsearch\s+(.+)/', $message_text, $matches ) ) {
            $intent['action'] = 'search_products';
            $intent['parameters']['query'] = trim( $matches[1] );
        }

        // Cart management intents
        elseif ( preg_match( '/\badd\s+(.+)/', $message_text, $matches ) ) {
            $intent['action'] = 'add_to_cart';
            $intent['parameters']['product'] = trim( $matches[1] );
        } elseif ( preg_match( '/\bremove\s+(.+)/', $message_text, $matches ) ) {
            $intent['action'] = 'remove_from_cart';
            $intent['parameters']['product'] = trim( $matches[1] );
        } elseif ( preg_match( '/\b(cart|basket|bag)\b/', $message_text ) ) {
            $intent['action'] = 'show_cart';
        }

        // Checkout intents
        elseif ( preg_match( '/\b(checkout|buy|purchase|order)\b/', $message_text ) ) {
            $intent['action'] = 'checkout';
        }

        // Order tracking intents
        elseif ( preg_match( '/\b(track|status|order)\s*#?(\d+)/', $message_text, $matches ) ) {
            $intent['action'] = 'track_order';
            $intent['parameters']['order_id'] = $matches[2];
        }

        // Product details intents
        elseif ( preg_match( '/\b(show|view|details)\s+(.+)/', $message_text, $matches ) ) {
            $intent['action'] = 'show_product';
            $intent['parameters']['product'] = trim( $matches[2] );
        }

        // Help intents
        elseif ( preg_match( '/\b(help|support|assist)\b/', $message_text ) ) {
            $intent['action'] = 'show_help';
        }

        return $intent;
    }

    /**
     * Handle parsed message intent
     *
     * @param string $phone_number Customer phone number
     * @param array  $intent Intent data
     * @param array  $session Session data
     * @param array  $message Original message data
     */
    private function handle_message_intent( $phone_number, $intent, $session, $message ) {
        $this->update_session_activity( $phone_number );

        switch ( $intent['action'] ) {
            case 'show_catalog':
                $this->send_product_catalog( $phone_number, $intent['parameters'] );
                break;

            case 'show_categories':
                $this->send_product_categories( $phone_number );
                break;

            case 'search_products':
                $this->search_and_send_products( $phone_number, $intent['parameters']['query'] );
                break;

            case 'add_to_cart':
                $this->add_product_to_cart( $phone_number, $intent['parameters']['product'] );
                break;

            case 'remove_from_cart':
                $this->remove_product_from_cart( $phone_number, $intent['parameters']['product'] );
                break;

            case 'show_cart':
                $this->send_cart_contents( $phone_number );
                break;

            case 'checkout':
                $this->initiate_checkout( $phone_number );
                break;

            case 'track_order':
                $this->send_order_tracking( $phone_number, $intent['parameters']['order_id'] );
                break;

            case 'show_product':
                $this->send_product_details( $phone_number, $intent['parameters']['product'] );
                break;

            case 'show_help':
                $this->send_help_message( $phone_number );
                break;

            default:
                $this->send_default_commerce_response( $phone_number );
                break;
        }
    }

    /**
     * Send product catalog
     *
     * @param string $phone_number Customer phone number
     * @param array  $parameters Additional parameters
     */
    private function send_product_catalog( $phone_number, $parameters = array() ) {
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'meta_query'     => array(
                array(
                    'key'     => '_stock_status',
                    'value'   => 'instock',
                    'compare' => '=',
                ),
            ),
        );

        // Apply category filter if specified
        if ( ! empty( $parameters['category'] ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $parameters['category'],
                ),
            );
        }

        $products = get_posts( $args );

        if ( empty( $products ) ) {
            $message = "🛒 *Our Catalog*\n\nSorry, no products are currently available. Please check back later!";
            $this->whatsapp_api->send_message( $phone_number, $message );
            return;
        }

        $catalog_message = $this->build_catalog_message( $products );
        $this->whatsapp_api->send_message( $phone_number, $catalog_message );

        // Send individual product cards if enabled
        if ( $this->settings['product_images'] === 'yes' ) {
            $this->send_product_cards( $phone_number, array_slice( $products, 0, 5 ) );
        }
    }

    /**
     * Build catalog message
     *
     * @param array $products Product posts
     * @return string Catalog message
     */
    private function build_catalog_message( $products ) {
        $message = "🛒 *Our Product Catalog*\n\n";
        
        foreach ( $products as $index => $product_post ) {
            $product = wc_get_product( $product_post->ID );
            if ( ! $product ) {
                continue;
            }

            $price = $this->settings['price_display'] === 'yes' ? $product->get_price_html() : '';
            $stock_status = $this->get_stock_status_emoji( $product );

            $message .= sprintf(
                "%d. *%s* %s\n%s\n💰 %s\n\n",
                $index + 1,
                $product->get_name(),
                $stock_status,
                wp_trim_words( $product->get_short_description(), 15 ),
                $price
            );
        }

        $message .= "📝 To add items to cart, reply with:\n";
        $message .= "*add [product name]*\n\n";
        $message .= "🛍️ To view your cart: *cart*\n";
        $message .= "🔍 To search: *search [keyword]*";

        return $message;
    }

    /**
     * Send product cards with images
     *
     * @param string $phone_number Customer phone number
     * @param array  $products Product posts
     */
    private function send_product_cards( $phone_number, $products ) {
        foreach ( $products as $product_post ) {
            $product = wc_get_product( $product_post->ID );
            if ( ! $product ) {
                continue;
            }

            $image_url = $this->get_product_image_url( $product );
            if ( $image_url ) {
                $caption = $this->build_product_card_caption( $product );
                $this->whatsapp_api->send_image( $phone_number, $image_url, $caption );
            }

            // Small delay between messages
            usleep( 500000 ); // 0.5 seconds
        }
    }

    /**
     * Get product image URL
     *
     * @param WC_Product $product Product object
     * @return string|null Image URL
     */
    private function get_product_image_url( $product ) {
        $image_id = $product->get_image_id();
        if ( $image_id ) {
            $image_url = wp_get_attachment_image_url( $image_id, 'medium' );
            return $image_url ?: null;
        }
        return null;
    }

    /**
     * Build product card caption
     *
     * @param WC_Product $product Product object
     * @return string Caption text
     */
    private function build_product_card_caption( $product ) {
        $caption = "*{$product->get_name()}*\n\n";
        $caption .= wp_trim_words( $product->get_description(), 30 ) . "\n\n";
        
        if ( $this->settings['price_display'] === 'yes' ) {
            $caption .= "💰 *Price:* {$product->get_price_html()}\n";
        }

        if ( $this->settings['stock_checking'] === 'yes' ) {
            $stock_status = $this->get_stock_status_text( $product );
            $caption .= "📦 *Stock:* {$stock_status}\n";
        }

        $caption .= "\n🛒 To add to cart: *add {$product->get_name()}*";

        return $caption;
    }

    /**
     * Get stock status emoji
     *
     * @param WC_Product $product Product object
     * @return string Stock status emoji
     */
    private function get_stock_status_emoji( $product ) {
        if ( ! $product->is_in_stock() ) {
            return '❌';
        } elseif ( $product->is_on_backorder() ) {
            return '⏳';
        } elseif ( $product->get_stock_quantity() <= 5 && $product->get_stock_quantity() > 0 ) {
            return '⚠️';
        } else {
            return '✅';
        }
    }

    /**
     * Get stock status text
     *
     * @param WC_Product $product Product object
     * @return string Stock status text
     */
    private function get_stock_status_text( $product ) {
        if ( ! $product->is_in_stock() ) {
            return 'Out of Stock';
        } elseif ( $product->is_on_backorder() ) {
            return 'Available on Backorder';
        } elseif ( $product->get_stock_quantity() <= 5 && $product->get_stock_quantity() > 0 ) {
            return "Low Stock ({$product->get_stock_quantity()} left)";
        } else {
            return 'In Stock';
        }
    }

    /**
     * Send product categories
     *
     * @param string $phone_number Customer phone number
     */
    private function send_product_categories( $phone_number ) {
        $categories = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'parent'     => 0, // Only top-level categories
        ) );

        if ( empty( $categories ) ) {
            $message = "📂 No product categories available at the moment.";
            $this->whatsapp_api->send_message( $phone_number, $message );
            return;
        }

        $message = "📂 *Product Categories*\n\n";
        
        foreach ( $categories as $index => $category ) {
            $count = $category->count;
            $message .= sprintf(
                "%d. *%s* (%d products)\n",
                $index + 1,
                $category->name,
                $count
            );
        }

        $message .= "\n💡 To browse a category, reply with:\n";
        $message .= "*catalog [category name]*";

        $this->whatsapp_api->send_message( $phone_number, $message );
    }

    /**
     * Search and send products
     *
     * @param string $phone_number Customer phone number
     * @param string $search_query Search query
     */
    private function search_and_send_products( $phone_number, $search_query ) {
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 8,
            's'              => $search_query,
            'meta_query'     => array(
                array(
                    'key'     => '_stock_status',
                    'value'   => 'instock',
                    'compare' => '=',
                ),
            ),
        );

        $products = get_posts( $args );

        if ( empty( $products ) ) {
            $message = "🔍 *Search Results*\n\n";
            $message .= "No products found for '{$search_query}'.\n\n";
            $message .= "💡 Try:\n";
            $message .= "• Different keywords\n";
            $message .= "• *catalog* to see all products\n";
            $message .= "• *categories* to browse by category";
            
            $this->whatsapp_api->send_message( $phone_number, $message );
            return;
        }

        $message = "🔍 *Search Results for '{$search_query}'*\n\n";
        $message .= $this->build_catalog_message( $products );

        $this->whatsapp_api->send_message( $phone_number, $message );
    }

    /**
     * Add product to cart
     *
     * @param string $phone_number Customer phone number
     * @param string $product_identifier Product name or ID
     */
    private function add_product_to_cart( $phone_number, $product_identifier ) {
        $product = $this->find_product_by_identifier( $product_identifier );

        if ( ! $product ) {
            $message = "❌ Product '{$product_identifier}' not found.\n\n";
            $message .= "💡 Try:\n";
            $message .= "• *search [product name]*\n";
            $message .= "• *catalog* to see available products";
            
            $this->whatsapp_api->send_message( $phone_number, $message );
            return;
        }

        if ( ! $product->is_in_stock() ) {
            $message = "❌ Sorry, *{$product->get_name()}* is currently out of stock.";
            $this->whatsapp_api->send_message( $phone_number, $message );
            return;
        }

        // Add to session cart
        $this->add_to_session_cart( $phone_number, $product->get_id() );

        $message = "✅ *{$product->get_name()}* added to your cart!\n\n";
        $message .= "💰 Price: {$product->get_price_html()}\n\n";
        $message .= "🛍️ View cart: *cart*\n";
        $message .= "🛒 Continue shopping: *catalog*\n";
        $message .= "💳 Checkout: *checkout*";

        $this->whatsapp_api->send_message( $phone_number, $message );

        // Send recommendations if enabled
        if ( $this->settings['recommendations'] === 'yes' ) {
            $this->send_product_recommendations( $phone_number, $product );
        }
    }

    /**
     * Find product by identifier
     *
     * @param string $identifier Product name or ID
     * @return WC_Product|null Product object or null
     */
    private function find_product_by_identifier( $identifier ) {
        // Try by ID first
        if ( is_numeric( $identifier ) ) {
            $product = wc_get_product( intval( $identifier ) );
            if ( $product && $product->exists() ) {
                return $product;
            }
        }

        // Search by name
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => '_stock_status',
                    'value'   => 'instock',
                    'compare' => '=',
                ),
            ),
        );

        // Try exact title match first
        $args['title'] = $identifier;
        $products = get_posts( $args );

        if ( empty( $products ) ) {
            // Try fuzzy search
            unset( $args['title'] );
            $args['s'] = $identifier;
            $products = get_posts( $args );
        }

        if ( ! empty( $products ) ) {
            return wc_get_product( $products[0]->ID );
        }

        return null;
    }

    /**
     * Add product to session cart
     *
     * @param string $phone_number Customer phone number
     * @param int    $product_id Product ID
     * @param int    $quantity Quantity to add
     */
    private function add_to_session_cart( $phone_number, $product_id, $quantity = 1 ) {
        $session = $this->get_customer_session( $phone_number );
        
        $cart = $session['cart'] ?? array();
        
        if ( isset( $cart[$product_id] ) ) {
            $cart[$product_id]['quantity'] += $quantity;
        } else {
            $cart[$product_id] = array(
                'product_id' => $product_id,
                'quantity'   => $quantity,
                'added_at'   => current_time( 'mysql' ),
            );
        }

        $this->update_session_cart( $phone_number, $cart );
    }

    /**
     * Remove product from cart
     *
     * @param string $phone_number Customer phone number
     * @param string $product_identifier Product name or ID
     */
    private function remove_product_from_cart( $phone_number, $product_identifier ) {
        $product = $this->find_product_by_identifier( $product_identifier );

        if ( ! $product ) {
            $message = "❌ Product '{$product_identifier}' not found in your cart.";
            $this->whatsapp_api->send_message( $phone_number, $message );
            return;
        }

        $session = $this->get_customer_session( $phone_number );
        $cart = $session['cart'] ?? array();

        if ( ! isset( $cart[$product->get_id()] ) ) {
            $message = "❌ *{$product->get_name()}* is not in your cart.";
            $this->whatsapp_api->send_message( $phone_number, $message );
            return;
        }

        unset( $cart[$product->get_id()] );
        $this->update_session_cart( $phone_number, $cart );

        $message = "✅ *{$product->get_name()}* removed from your cart.\n\n";
        $message .= "🛍️ View cart: *cart*";

        $this->whatsapp_api->send_message( $phone_number, $message );
    }

    /**
     * Send cart contents
     *
     * @param string $phone_number Customer phone number
     */
    private function send_cart_contents( $phone_number ) {
        $session = $this->get_customer_session( $phone_number );
        $cart = $session['cart'] ?? array();

        if ( empty( $cart ) ) {
            $message = "🛍️ *Your Cart*\n\nYour cart is empty.\n\n";
            $message .= "🛒 Browse products: *catalog*\n";
            $message .= "🔍 Search products: *search [keyword]*";
            
            $this->whatsapp_api->send_message( $phone_number, $message );
            return;
        }

        $message = "🛍️ *Your Cart*\n\n";
        $total = 0;
        $valid_items = 0;

        foreach ( $cart as $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( ! $product || ! $product->exists() ) {
                continue;
            }

            $line_total = $product->get_price() * $item['quantity'];
            $total += $line_total;
            $valid_items++;

            $message .= sprintf(
                "• *%s* x%d\n  💰 %s\n\n",
                $product->get_name(),
                $item['quantity'],
                wc_price( $line_total )
            );
        }

        if ( $valid_items === 0 ) {
            $message = "🛍️ *Your Cart*\n\nYour cart is empty or contains invalid items.\n\n";
            $message .= "🛒 Browse products: *catalog*";
            $this->whatsapp_api->send_message( $phone_number, $message );
            return;
        }

        $message .= "💰 *Total: " . wc_price( $total ) . "*\n\n";
        $message .= "💳 Ready to checkout? Reply: *checkout*\n";
        $message .= "🛒 Continue shopping: *catalog*\n";
        $message .= "❌ Remove item: *remove [product name]*";

        $this->whatsapp_api->send_message( $phone_number, $message );
    }

    /**
     * Initiate checkout process
     *
     * @param string $phone_number Customer phone number
     */
    private function initiate_checkout( $phone_number ) {
        $session = $this->get_customer_session( $phone_number );
        $cart = $session['cart'] ?? array();

        if ( empty( $cart ) ) {
            $message = "❌ Your cart is empty. Add some products first!\n\n";
            $message .= "🛒 Browse products: *catalog*";
            $this->whatsapp_api->send_message( $phone_number, $message );
            return;
        }

        try {
            // Create WooCommerce order
            $order_id = $this->create_order_from_cart( $phone_number, $cart );
            
            if ( is_wp_error( $order_id ) ) {
                throw new Exception( $order_id->get_error_message() );
            }

            // Generate payment link
            $payment_link = $this->link_generator->generate_whatsapp_payment_link( $order_id, array(
                'customer_phone' => $phone_number,
                'source' => 'whatsapp_commerce',
            ) );

            if ( is_wp_error( $payment_link ) ) {
                throw new Exception( $payment_link->get_error_message() );
            }

            // Clear cart after successful order creation
            $this->clear_session_cart( $phone_number );

            // Send checkout confirmation
            $order = wc_get_order( $order_id );
            $message = $this->build_checkout_message( $order, $payment_link );
            
            $this->whatsapp_api->send_message( $phone_number, $message );

            $this->logger->info( 'WhatsApp commerce checkout initiated', array(
                'phone' => $phone_number,
                'order_id' => $order_id,
                'payment_link_id' => $payment_link['id'],
            ) );

        } catch ( Exception $e ) {
            $this->logger->error( 'WhatsApp commerce checkout failed', array(
                'phone' => $phone_number,
                'error' => $e->getMessage(),
            ) );

            $message = "❌ Sorry, there was an error processing your order. Please try again or contact support.";
            $this->whatsapp_api->send_message( $phone_number, $message );
        }
    }

    /**
     * Create WooCommerce order from cart
     *
     * @param string $phone_number Customer phone number
     * @param array  $cart Cart items
     * @return int|WP_Error Order ID or error
     */
    private function create_order_from_cart( $phone_number, $cart ) {
        try {
            $order = wc_create_order();

            // Add products to order
            foreach ( $cart as $item ) {
                $product = wc_get_product( $item['product_id'] );
                if ( ! $product || ! $product->exists() || ! $product->is_in_stock() ) {
                    continue;
                }

                $order->add_product( $product, $item['quantity'] );
            }

            // Set billing information
            $order->set_billing_phone( $phone_number );
            $order->set_billing_email( $this->get_customer_email( $phone_number ) );
            
            // Set customer from session if available
            $session = $this->get_customer_session( $phone_number );
            if ( ! empty( $session['customer_name'] ) ) {
                $name_parts = explode( ' ', $session['customer_name'], 2 );
                $order->set_billing_first_name( $name_parts[0] );
                $order->set_billing_last_name( $name_parts[1] ?? '' );
            }

            // Set order status and calculate totals
            $order->set_status( 'pending' );
            $order->calculate_totals();
            $order->save();

            // Add order note
            $order->add_order_note( 'Order created via WhatsApp Commerce' );

            return $order->get_id();

        } catch ( Exception $e ) {
            return new WP_Error( 'order_creation_failed', $e->getMessage() );
        }
    }

    /**
     * Build checkout confirmation message
     *
     * @param WC_Order $order Order object
     * @param array    $payment_link Payment link data
     * @return string Checkout message
     */
    private function build_checkout_message( $order, $payment_link ) {
        $message = "🎉 *Order Created Successfully!*\n\n";
        $message .= "📋 Order #: {$order->get_order_number()}\n";
        $message .= "💰 Total: {$order->get_formatted_order_total()}\n\n";

        $message .= "📦 *Items Ordered:*\n";
        foreach ( $order->get_items() as $item ) {
            $message .= "• {$item->get_name()} x{$item->get_quantity()}\n";
        }

        $message .= "\n💳 *Complete Your Payment:*\n";
        $message .= $payment_link['whatsapp']['short_url'] . "\n\n";

        $message .= "⏰ Payment link expires in 24 hours\n";
        $message .= "📱 Click the link above to pay securely\n\n";

        $message .= "❓ Need help? Just reply to this message!";

        return $message;
    }

    /**
     * Get customer session data
     *
     * @param string $phone_number Customer phone number
     * @return array Session data
     */
    private function get_customer_session( $phone_number ) {
        return $this->db_table->get_customer_session( $phone_number );
    }

    /**
     * Update session cart
     *
     * @param string $phone_number Customer phone number
     * @param array  $cart Cart data
     */
    private function update_session_cart( $phone_number, $cart ) {
        $this->db_table->update_session_cart( $phone_number, $cart );
    }

    /**
     * Clear session cart
     *
     * @param string $phone_number Customer phone number
     */
    private function clear_session_cart( $phone_number ) {
        $this->db_table->clear_session_cart( $phone_number );
    }

    /**
     * Update session activity
     *
     * @param string $phone_number Customer phone number
     */
    private function update_session_activity( $phone_number ) {
        $this->db_table->update_session_activity( $phone_number );
    }

    /**
     * Get customer email
     *
     * @param string $phone_number Customer phone number
     * @return string Customer email
     */
    private function get_customer_email( $phone_number ) {
        $session = $this->get_customer_session( $phone_number );
        return $session['email'] ?? '';
    }

    /**
     * Send product recommendations
     *
     * @param string     $phone_number Customer phone number
     * @param WC_Product $product Base product
     */
    private function send_product_recommendations( $phone_number, $product ) {
        $recommendations = $this->get_product_recommendations( $product );
        
        if ( empty( $recommendations ) ) {
            return;
        }

        $message = "💡 *You might also like:*\n\n";
        
        foreach ( $recommendations as $index => $rec_product ) {
            $message .= sprintf(
                "%d. *%s*\n   💰 %s\n\n",
                $index + 1,
                $rec_product->get_name(),
                $rec_product->get_price_html()
            );
        }

        $message .= "🛒 To add: *add [product name]*";

        $this->whatsapp_api->send_message( $phone_number, $message );
    }

    /**
     * Get product recommendations
     *
     * @param WC_Product $product Base product
     * @return array Recommended products
     */
    private function get_product_recommendations( $product ) {
        // Get related products from same category
        $categories = $product->get_category_ids();
        
        if ( empty( $categories ) ) {
            return array();
        }

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 3,
            'post__not_in'   => array( $product->get_id() ),
            'tax_query'      => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $categories[0],
                ),
            ),
            'meta_query'     => array(
                array(
                    'key'     => '_stock_status',
                    'value'   => 'instock',
                    'compare' => '=',
                ),
            ),
        );

        $related_posts = get_posts( $args );
        $recommendations = array();

        foreach ( $related_posts as $post ) {
            $rec_product = wc_get_product( $post->ID );
            if ( $rec_product && $rec_product->exists() ) {
                $recommendations[] = $rec_product;
            }
        }

        return $recommendations;
    }

    /**
     * Send help message
     *
     * @param string $phone_number Customer phone number
     */
    private function send_help_message( $phone_number ) {
        $message = "❓ *How can I help you?*\n\n";
        $message .= "🛒 *Shopping Commands:*\n";
        $message .= "• *catalog* - View all products\n";
        $message .= "• *categories* - Browse by category\n";
        $message .= "• *search [keyword]* - Find products\n";
        $message .= "• *add [product]* - Add to cart\n";
        $message .= "• *cart* - View your cart\n";
        $message .= "• *checkout* - Complete purchase\n\n";
        
        $message .= "📦 *Order Commands:*\n";
        $message .= "• *track [order number]* - Track order\n";
        $message .= "• *orders* - View recent orders\n\n";
        
        $message .= "💬 *Support:*\n";
        $message .= "Just send us a message and we'll help you!";

        $this->whatsapp_api->send_message( $phone_number, $message );
    }

    /**
     * Send default commerce response
     *
     * @param string $phone_number Customer phone number
     */
    private function send_default_commerce_response( $phone_number ) {
        $message = "🤔 I didn't understand that. Here's what you can do:\n\n";
        $message .= "🛒 *catalog* - Browse products\n";
        $message .= "🔍 *search [keyword]* - Find products\n";
        $message .= "🛍️ *cart* - View your cart\n";
        $message .= "❓ *help* - Get assistance\n\n";
        $message .= "Or just tell me what you're looking for!";

        $this->whatsapp_api->send_message( $phone_number, $message );
    }

    /**
     * Send error message
     *
     * @param string $phone_number Customer phone number
     */
    private function send_error_message( $phone_number ) {
        $message = "❌ Sorry, something went wrong. Please try again or contact support.";
        $this->whatsapp_api->send_message( $phone_number, $message );
    }

    /**
     * Cleanup expired sessions
     */
    public function cleanup_expired_sessions() {
        $deleted_count = $this->db_table->cleanup_expired_sessions();
        
        $this->logger->info( 'WhatsApp commerce sessions cleaned up', array(
            'deleted_count' => $deleted_count,
        ) );
    }

    /**
     * Sync product catalog to WhatsApp Business API
     */
    public function sync_product_catalog() {
        if ( ! $this->whatsapp_api->supports_catalog() ) {
            return;
        }

        try {
            $products = $this->get_products_for_catalog_sync();
            $result = $this->whatsapp_api->sync_product_catalog( $products );

            if ( is_wp_error( $result ) ) {
                throw new Exception( $result->get_error_message() );
            }

            $this->logger->info( 'Product catalog synced to WhatsApp', array(
                'product_count' => count( $products ),
            ) );

        } catch ( Exception $e ) {
            $this->logger->error( 'Failed to sync product catalog', array(
                'error' => $e->getMessage(),
            ) );
        }
    }

    /**
     * Get products for catalog sync
     *
     * @return array Products data for sync
     */
    private function get_products_for_catalog_sync() {
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_stock_status',
                    'value'   => 'instock',
                    'compare' => '=',
                ),
            ),
        );

        $product_posts = get_posts( $args );
        $products = array();

        foreach ( $product_posts as $post ) {
            $product = wc_get_product( $post->ID );
            if ( ! $product || ! $product->exists() ) {
                continue;
            }

            $products[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'description' => wp_trim_words( $product->get_description(), 50 ),
                'price' => $product->get_price(),
                'currency' => get_woocommerce_currency(),
                'image_url' => $this->get_product_image_url( $product ),
                'availability' => $product->is_in_stock() ? 'in_stock' : 'out_of_stock',
                'category' => $this->get_product_primary_category( $product ),
                'url' => $product->get_permalink(),
            );
        }

        return $products;
    }

    /**
     * Get product primary category
     *
     * @param WC_Product $product Product object
     * @return string Primary category name
     */
    private function get_product_primary_category( $product ) {
        $categories = $product->get_category_ids();
        
        if ( empty( $categories ) ) {
            return '';
        }

        $category = get_term( $categories[0], 'product_cat' );
        return $category && ! is_wp_error( $category ) ? $category->name : '';
    }
}