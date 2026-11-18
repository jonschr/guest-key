<?php
/** Local-only regression; temporary administrators and entitlements are removed. */
if (!defined('WP_CLI') || !WP_CLI || !in_array(wp_get_environment_type(), array('local','development'), true) || !Guest_Key_Access::administrator()) { throw new RuntimeException('Local administrator only.'); }
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-includes/class-wp-admin-bar.php';
$original = get_current_user_id(); $hidden = get_option(Guest_Key_License::HIDDEN, null); $users = array(); $checks = 0;
$site_options = array(); foreach (array(Guest_Key_License::SHARING, Guest_Key_License::CONTACTS) as $name) { $site_options[$name]=get_option($name,null); delete_option($name); }
$cookies = $_COOKIE; $session = null;
$assert = static function($ok, $message) use (&$checks) { if (!$ok) { throw new RuntimeException($message); } $checks++; echo "PASS: {$message}\n"; };
$record = array('kind'=>'paid','status'=>'active','site'=>trailingslashit(home_url('/')),'checked'=>time(),'expires'=>time()+DAY_IN_SECONDS,'shared_users'=>array());
try {
 foreach (array('Owner A','Owner B','Recipient') as $name) {
  $id=wp_insert_user(array('user_login'=>'gk-visible-'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(32),'role'=>'administrator','user_email'=>'gk-visible-'.wp_generate_password(10,false).'@example.com','display_name'=>$name));
  if (is_wp_error($id)) { throw new RuntimeException('Fixture creation failed.'); } $users[]=$id;
 }
 list($a,$b,$c)=$users;
 foreach (array($a,$b) as $id) { update_option(Guest_Key_License::option_name($id),$record,false); }
 wp_set_current_user($a);
 $assert(Guest_Key_License::share(array($c))===true,'First owner shares access');
 wp_set_current_user($b); $assert(Guest_Key_License::share(array($c))===true,'Second owner shares access');
 $assert((bool)Guest_Key_License::active_until($c),'Recipient has shared entitlement');
 $assert(Guest_Key_License::set_toolbar_hidden($c,true)===true,'Another administrator can hide the toolbar');
 $assert(Guest_Key_License::toolbar_hidden($c),'Visibility is site scoped');
 $assert(!Guest_Key_License::active_until($c),'Hidden recipient loses shared entitlement from all owners');
 foreach (array($a,$b) as $id) { $assert(empty(get_option(Guest_Key_License::option_name($id))['shared_users']),'Stored share removed from owner '.$id); }
 $assert(is_wp_error(Guest_Key_License::share(array($c))),'Server rejects sharing with a hidden administrator');
 $row=array_values(array_filter(Guest_Key_License::view()['administrators'],static function($row)use($c){return $row['id']===$c;}))[0];
 $assert($row['hidden'] && !$row['shared'],'UI state marks hidden administrator as not shared');
 wp_set_current_user($c); $bar=new WP_Admin_Bar(); Guest_Key_Admin::toolbar($bar);
 $assert(!$bar->get_node('guest-key-access'),'Hidden administrator has no toolbar node');
 $assert(Guest_Key_Admin::page_url()===admin_url('tools.php?page=guest-key') && Guest_Key_Access::administrator(),'Hidden administrator retains settings authorization');
 $view=Guest_Key_License::view();
 $assert($view['restricted'] && !$view['canManageAccess'] && empty($view['administrators']),'Hidden users receive only license recovery state');
 $assert($view['contactEmail']===get_userdata($b)->user_email,'Hidden user sees the administrator who last managed access');
 ob_start(); Guest_Key_Admin::render(); $html=ob_get_clean();
 $assert(strpos($html,'Guest Key access is not currently enabled for your account.')!==false && strpos($html,$view['contactEmail'])!==false && strpos($html,Guest_Key_License::CHECKOUT)!==false,'Restricted settings show the message, contact and purchase link');
 $assert(strpos($html,'guest-key-inventory')===false && strpos($html,'guest-key-duration-settings')===false && strpos($html,'data-guest-key-create')===false,'Restricted page omits full settings, diagnostics and access controls');
 $assert(is_wp_error(Guest_Key_Admin::diagnostics()) && is_wp_error(Guest_Key_Access::set_duration(60)) && is_wp_error(Guest_Key_License::share(array($a))) && is_wp_error(Guest_Key_License::set_toolbar_hidden($a,true)),'Hidden user cannot bypass restricted settings through handlers');
 $assert(is_wp_error(Guest_Key_License::email('fixture@example.com')) && is_wp_error(Guest_Key_License::verify('123456')) && is_wp_error(Guest_Key_License::claim_existing()),'Hidden users cannot use trial or legacy claiming to bypass owner control');
 update_option(Guest_Key_License::option_name($c),array_merge($record,array('kind'=>'trial')),false);
 $assert(!Guest_Key_License::active_until($c),'An existing trial cannot bypass hidden access');
 $provider = static function($reply,$args,$url) {
  if (strpos($url,'https://api.lemonsqueezy.com/v1/licenses/')!==0) { return $reply; }
  return array('response'=>array('code'=>200),'headers'=>array(),'body'=>wp_json_encode(array('valid'=>true,'activated'=>true,'license_key'=>array('expires_at'=>null),'instance'=>array('id'=>'fixture-visibility-purchase'),'meta'=>array('product_id'=>1457777,'variant_id'=>2274401))));
 };
 add_filter('pre_http_request',$provider,10,3);
 try { $activated=Guest_Key_License::activate('fixture-hidden-own-purchase'); } finally { remove_filter('pre_http_request',$provider,10); }
 $assert($activated===true,'A hidden user can activate a separate purchased license through the real activation path');
 $assert((bool)Guest_Key_License::active_until($c) && !Guest_Key_License::toolbar_hidden($c) && !Guest_Key_License::view()['restricted'],'Own purchased license overrides existing hidden preference');
 wp_set_current_user($a);
 $assert(is_wp_error(Guest_Key_License::set_toolbar_hidden($c,true)) && is_wp_error(Guest_Key_License::set_toolbar_hidden($b,true)),'An owner cannot hide another purchased-license owner');
 wp_set_current_user($c);
 $assert(is_wp_error(Guest_Key_License::set_toolbar_hidden($c,false)),'An administrator cannot change their own visibility');
 delete_option(Guest_Key_License::option_name($c));
 wp_set_current_user($a);
 $assert(Guest_Key_License::set_toolbar_hidden($c,false)===true,'Another administrator can restore visibility');
 $assert(!Guest_Key_License::toolbar_hidden($c) && empty(get_option(Guest_Key_License::option_name($a))['shared_users']),'Restoring visibility does not restore removed shares');
 wp_set_current_user($c); $bar=new WP_Admin_Bar(); Guest_Key_Admin::toolbar($bar);
 $assert((bool)$bar->get_node('guest-key-access'),'Restored toolbar node is visible');
 wp_set_current_user($a);
 $assert(Guest_Key_License::share(array($c))===true,'Sharing works again after unhiding');
 $expires=time()+HOUR_IN_SECONDS; $session=WP_Session_Tokens::get_instance($a)->create($expires);
 $login=wp_generate_auth_cookie($a,$expires,'logged_in',$session); $_COOKIE[LOGGED_IN_COOKIE]=$login;
 $http_cookies=array(new WP_Http_Cookie(array('name'=>LOGGED_IN_COOKIE,'value'=>$login)),new WP_Http_Cookie(array('name'=>SECURE_AUTH_COOKIE,'value'=>wp_generate_auth_cookie($a,$expires,'secure_auth',$session))));
 foreach (array('1','0') as $value) {
  $response=wp_remote_post(admin_url('admin-ajax.php'),array('sslverify'=>false,'redirection'=>0,'timeout'=>15,'cookies'=>$http_cookies,'body'=>array('action'=>'guest_key_license_visibility','user'=>(string)$c,'hidden'=>$value,'nonce'=>wp_create_nonce('guest_key_access'))));
  if (is_wp_error($response)) { throw new RuntimeException($response->get_error_message()); }
  $data=json_decode(wp_remote_retrieve_body($response),true);
  $assert(wp_remote_retrieve_response_code($response)===200 && !empty($data['success']),'Authenticated visibility AJAX succeeds for '.$value);
  $row=array_values(array_filter($data['data']['licensing']['administrators'],static function($row)use($c){return $row['id']===$c;}))[0];
  $assert($row['hidden']===('1'===$value) && !$row['shared'],'AJAX returns authoritative hidden/unshared state');
 }
 $response=wp_remote_post(admin_url('admin-ajax.php'),array('sslverify'=>false,'redirection'=>0,'timeout'=>15,'cookies'=>$http_cookies,'body'=>array('action'=>'guest_key_license_visibility','user'=>(string)$c,'hidden'=>'1','nonce'=>'invalid')));
 $assert(!is_wp_error($response) && wp_remote_retrieve_response_code($response)===403,'Visibility AJAX rejects an invalid nonce');
 $assert(is_wp_error(Guest_Key_License::set_toolbar_hidden(999999999,true)),'Nonexistent user rejected');
 $assert(is_wp_error(Guest_Key_License::set_toolbar_hidden($c,'true')),'Invalid visibility value rejected');
 get_userdata($c)->set_role('subscriber');
 $assert(is_wp_error(Guest_Key_License::set_toolbar_hidden($c,true)),'Non-administrator target rejected');
 wp_set_current_user($c);
 $assert(is_wp_error(Guest_Key_License::set_toolbar_hidden($a,true)),'Non-administrator actor rejected');
} finally {
 wp_set_current_user($original);
 $_COOKIE=$cookies;
 foreach($site_options as $name=>$value){ if($value===null){delete_option($name);}else{update_option($name,$value,false);} }
 if ($session) { WP_Session_Tokens::get_instance($users[0])->destroy($session); }
 foreach ($users as $id) { delete_option(Guest_Key_License::option_name($id)); wp_delete_user($id); }
 if ($hidden===null) { delete_option(Guest_Key_License::HIDDEN); } else { update_option(Guest_Key_License::HIDDEN,$hidden,false); }
}
echo "Completed {$checks} toolbar visibility checks; fixtures removed.\n";
