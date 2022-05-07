<?php
/*
 * Plugin Name: Bixb Gateway
 * Plugin URI: https://bix-team.info/bixb-wordpress-gateway
 * Description: Bixb payment gateway
 * Version: 1.0
 * Author: BixB Developer
 * Author URI: https://bix-team.info
 * License: Personal use
*/

require_once __DIR__ . '/src/class-gateway-bixb.php';

add_action( 'rest_api_init', function() {
    register_rest_route(
        'bixb',
        '/ipn',
        array( 
            'methods' => 'POST',
            'callback' => array(
                'WC_Bixb_Gateway',
                'webhook'
            ),
        )
    );
});