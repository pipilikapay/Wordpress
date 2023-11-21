<?php
/*
Plugin Name: pipilikapay
Plugin URI: https://pipilikapay.com
Description: This plugin allows your customers to pay with Bkash, Rocket, Nagad, Upay via pipilikapay
Version: 1.0.0
Author: pipilikapay
Author URI: https://pipilikapay.com
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: pipilikapay-gateway
*/


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Load the plugin.
add_action( 'plugins_loaded', 'custom_pipilikapay_plugin_init' );
function custom_pipilikapay_plugin_init() {
    if ( class_exists( 'WC_Payment_Gateway' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'class-custom-pipilikapay-gateway.php';
    }
}
?>