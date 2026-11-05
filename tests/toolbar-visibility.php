<?php
/** Local-only regression; temporary administrators and entitlements are removed. */
if (!defined('WP_CLI') || !WP_CLI || !in_array(wp_get_environment_type(), array('local','development'), true) || !Guest_Key_Access::administrator()) { throw new RuntimeException('Local administrator only.'); }
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-includes/class-wp-admin-bar.php';
$original = get_current_user_id(); $hidden = get_option(Guest_Key_License::HIDDEN, null); $users = array(); $checks = 0;
$cookies = $_COOKIE; $session = null;
$assert = static function($ok, $message) use (&$checks) { if (!$ok) { throw new RuntimeException($message); } $checks++; echo "PASS: {$message}\n"; };
$record = array('kind'=>'paid','status'=>'active','site'=>trailingslashit(home_url('/')),'checked'=>time(),'expires'=>time()+DAY_IN_SECONDS,'shared_users'=>array());
try {
 foreach (array('Owner A','Owner B','Recipient') as $name) {
  $id=wp_insert_user(array('user_login'=>'gk-visible-'.wp_generate_password(12,false),'user_pass'=>wp_generate_password(32),'role'=>'administrator','display_name'=>$name));
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
 $assert(!empty(Guest_Key_License::view()['administrators']),'Unlicensed administrators can manage visibility');
 update_option(Guest_Key_License::option_name($c),$record,false);
 $assert((bool)Guest_Key_License::active_until($c),'Hiding preserves an independently owned license');
 $assert(is_wp_error(Guest_Key_License::set_toolbar_hidden($c,false)),'An administrator cannot change their own visibility');
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
 if ($session) { WP_Session_Tokens::get_instance($users[0])->destroy($session); }
 foreach ($users as $id) { delete_option(Guest_Key_License::option_name($id)); wp_delete_user($id); }
 if ($hidden===null) { delete_option(Guest_Key_License::HIDDEN); } else { update_option(Guest_Key_License::HIDDEN,$hidden,false); }
}
echo "Completed {$checks} toolbar visibility checks; fixtures removed.\n";
