<?php
/**
 * SwiftStream library autoloader.
 *
 * @author    Eric Mann <eric.mann@10up.com>
 * @copyright 2014 Eric Mann, 10up
 * @license   http://www.opensource.org/licenses/mit-license.html
 */
namespace TenUp\SwiftStream;

if ( version_compare( PHP_VERSION, "5.3", "<" ) ) {
	trigger_error( "SwiftStream requires PHP version 5.3.0 or higher", E_USER_ERROR );
}

// Require files
require_once __DIR__ . '/src/filters.php';

// Bootstrap
Filters\setup();