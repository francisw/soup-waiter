<?php
namespace Waiter;

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the
 * plugin admin area. This file also includes all of the dependencies used by
 * the plugin, registers the activation and deactivation functions, and defines
 * a function that starts the plugin.
 *
 * @link              https://github.com/francisw/soup-waiter
 * @since             4.8.1
 * @package           soup_waiter
 *
 * @wordpress-plugin
 * Plugin Name:       VacationSoup plugin for Vacation Rental Owners
 * Plugin URI:        https://github.com/francisw/soup-waiter
 * Description:       Syndicate and Automate Vacation Rental Posting with Vacation Soup
 * Version:           4.8.1
 * Author:            Francis Wallinger
 * Author URI:        http://francis.wallinger.uk
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Depends:           timber-library
 * Conflicts:         pixabay-images (disable that plugin, pixabay images is incorporated (modified) within this plugin
 */
// If this file is accessed directory, then abort.
if ( ! defined( 'WPINC' ) ) {
	throw new \Exception("Cannot be accessed directly");
}
if ( ! function_exists( 'version_compare' ) || version_compare( PHP_VERSION, '5.3.0', '<' ) ) {
	throw new \Exception("PHP Version not supported");
}

require_once("inc/autoloader.php");

add_action ('init',array(SoupWaiter::single(),'init'));
