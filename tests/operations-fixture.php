<?php
/** Plugin Name: Guest Key temporary operations fixture */
defined('ABSPATH') || exit;
if ('local' !== wp_get_environment_type()) {return;}
add_action('gk_operations_fixture',static function($value){update_option('gk_operations_result',$value,false);});
// Exercise the account guard even when another plugin enables native user-route batching.
add_filter('rest_endpoints',static function($routes){foreach($routes as $path=>&$handlers){if(0===strpos($path,'/wp/v2/users/')){foreach($handlers as &$handler){if(isset($handler['methods'])){$handler['allow_batch']=array('v1'=>true);}}unset($handler);}}unset($handlers);return $routes;});

add_action('gk_operations_failure_fixture',static function(){throw new RuntimeException('Deliberate local cron callback failure.');});
