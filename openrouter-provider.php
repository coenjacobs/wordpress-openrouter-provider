<?php

declare(strict_types=1);

/**
 * Plugin Name: OpenRouter Provider
 * Description: Adds OpenRouter as an AI provider for the WordPress AI Client.
 * Version: 0.6.0
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: Coen Jacobs
 * Author URI: https://coenjacobs.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Plugin URI: https://coenjacobs.com/projects/wordpress-openrouter-provider/
 * Update URI: https://lapisense.coenjacobs.com
 */

use CoenJacobs\OpenRouterProvider\Plugin;
use CoenJacobs\OpenRouterProvider\Dependencies\Lapisense\WordPressClient\Client;

if ( ! defined( 'ABSPATH' ) ) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

Client::init( [
    'store_url'    => 'https://lapisense.coenjacobs.com',
    'product_uuid' => '98a75e22-10f1-4270-bb01-6deac6ec4dc7',
    'product_type' => 'plugin',
    'file'         => __FILE__,
] );

add_action( 'plugins_loaded', function () {
    Plugin::instance()->setup();
} );
