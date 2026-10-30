<?php
/** Local HTTP/command regression. Requires the temporary operations-fixture.php MU plugin. */
require_once __DIR__ . '/user-license-fixture.php';
if (!defined('WP_CLI') || !WP_CLI || 'local'!==wp_get_environment_type() || !Guest_Key_Access::administrator() || !has_action('gk_operations_fixture')) {throw new RuntimeException('Use the isolated local operations fixture.');}
require_once ABSPATH.'wp-admin/includes/user.php'; require_once ABSPATH.'wp-admin/includes/plugin.php';
$owner=get_current_user_id();$uid=null;$term=null;$checks=0;$created_hooks=array();$adapter=is_plugin_active(Guest_Key_Dependency::PLUGIN);$managed=get_option('guest_key_adapter_managed',null);
$assert=static function($ok,$message)use(&$checks){if(!$ok){throw new RuntimeException($message);}$checks++;echo "PASS: {$message}\n";};
$run=static function($command,$input=array()){return wp_get_ability('guest-key/run')->execute(array('commands'=>array(array('command'=>$command,'input'=>$input))));};
$http=static function($method,$route,$key,$body=null){$args=array('method'=>$method,'headers'=>array('Authorization'=>'Basic '.base64_encode($key['username'].':'.$key['password'])),'sslverify'=>false,'redirection'=>0,'timeout'=>20,'user-agent'=>'T3Code (user-directed AI agent; agent=Codex; model=gpt-6.1-sol)');if(null!==$body){$args['headers']['Content-Type']='application/json';$args['body']=wp_json_encode($body);}$r=wp_remote_request(rest_url($route),$args);if(is_wp_error($r)){throw new RuntimeException($r->get_error_message());}return array(wp_remote_retrieve_response_code($r),json_decode(wp_remote_retrieve_body($r),true));};
register_term_meta('category','_gk_operations_denied',array('type'=>'string','auth_callback'=>'__return_false'));
try {
 $help=wp_get_ability('guest-key/help')->execute(array());foreach(array('cron list','cron schedules','cron schedule','cron run','cron delete','theme list','theme get','plugin list','plugin activate') as $command){$assert(in_array($command,$help['commands'],true),'Help discovers '.$command);}
 $r=$run('theme list');$assert($r['ok']&&in_array(get_stylesheet(),array_column($r['results'][0]['data'],'stylesheet'),true),'Installed themes include the current theme');
 $r=$run('theme get');$assert($r['ok']&&$r['results'][0]['data']['active']&&get_stylesheet()===$r['results'][0]['data']['stylesheet'],'Theme get defaults to active theme');
 $r=$run('cron schedules');$assert($r['ok']&&isset($r['results'][0]['data']['hourly']),'Cron exposes recurrence schedules');
 $at=time()+600;$hook='gk_operations_fixture';$created_hooks[]=$hook;
 $r=$run('cron schedule',array('hook'=>$hook,'timestamp'=>$at,'args'=>array('alpha')));$a=$r['results'][0]['data']??array();$assert($r['ok']&&!empty($a['scheduled']),'Schedule a one-time cron event');
 $r=$run('cron schedule',array('hook'=>$hook,'timestamp'=>$at,'args'=>array('beta')));$b=$r['results'][0]['data'];$assert($r['ok'],'Schedule another occurrence with different arguments');
 $r=$run('cron list',array('hook'=>$hook,'due'=>false,'per_page'=>1));$assert($r['ok']&&2===$r['results'][0]['data']['total']&&1===count($r['results'][0]['data']['items']),'Cron listing paginates and includes future events');
 $r=$run('cron run',array('hook'=>$hook,'timestamp'=>$at,'signature'=>$a['signature']));$assert($r['ok']&&'alpha'===get_option('gk_operations_result')&&!wp_next_scheduled($hook,array('alpha'))&&$at===wp_next_scheduled($hook,array('beta')),'Run consumes only the selected event and executes its arguments');
 $r=$run('cron delete',array('hook'=>$hook,'timestamp'=>$at,'signature'=>$b['signature']));$assert($r['ok']&&!wp_next_scheduled($hook,array('beta')),'Delete removes the selected occurrence');
 $r=$run('cron schedule',array('hook'=>$hook,'timestamp'=>$at,'args'=>array('recurring'),'recurrence'=>'hourly'));$a=$r['results'][0]['data'];
 $r=$run('cron run',array('hook'=>$hook,'timestamp'=>$at,'signature'=>$a['signature']));$assert($r['ok']&&wp_next_scheduled($hook,array('recurring'))>$at,'Running a recurring event retains its next occurrence');
 $assert(!$run('cron schedule',array('hook'=>$hook,'timestamp'=>$at,'recurrence'=>'not-a-schedule'))['ok'],'Invalid recurrence does not schedule');
 $assert(!$run('cron run',array('hook'=>$hook,'timestamp'=>$at,'signature'=>str_repeat('0',32)))['ok'],'Missing event cannot run a callback');
 foreach(array('gk_operations_missing_fixture','gk_operations_failure_fixture') as $special){$created_hooks[]=$special;wp_schedule_single_event($at,$special);$result=$run('cron run',array('hook'=>$special,'timestamp'=>$at));$assert(!$result['ok'],'Cron reports missing/failing callback: '.$special);$assert(('gk_operations_missing_fixture'===$special)===(bool)wp_next_scheduled($special),'Failed callback handling preserves consumption state: '.$special);}
 $term=wp_insert_term('Guest Key operations fixture '.wp_generate_password(6,false),'category')['term_id'];
 foreach(array('_gk_operations_denied'=>'secretish','_unregistered'=>'one','_guest_key_fixture'=>'visible','étiquette'=>'value','empty'=>'') as $name=>$value){add_term_meta($term,$name,$value);}
 add_term_meta($term,'_unregistered','two');
 $r=$run('term meta list',array('term_id'=>$term));$values=$r['results'][0]['data']['values'];$assert($r['ok']&&array('one','two')===$values['_unregistered']&&array('secretish')===$values['_gk_operations_denied']&&isset($values['_guest_key_fixture']),'Term listing returns every stored key and all values');
 foreach(array('_gk_operations_denied','étiquette','empty','missing') as $name){$r=$run('term meta get',array('term_id'=>$term,'key'=>$name));$v=$r['results'][0]['data'];$assert($r['ok']&&$v['exists']===('missing'!==$name),'Term reads preserve protected/non-ASCII/empty/missing key semantics: '.$name);}
 $assert(!$run('term meta update',array('term_id'=>$term,'key'=>'_gk_operations_denied','value'=>'denied'))['ok'],'Term reads do not loosen write permissions');
 $uid=wp_insert_user(array('user_login'=>'gk-operations-'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(32),'role'=>'administrator','user_email'=>'gk-'.wp_generate_password(8,false).'@example.invalid'));
 wp_set_current_user($uid);$key=guest_key_test_entitled_create($uid);$assert(!is_wp_error($key),'Temporary administrator receives a test-entitled key');
 Guest_Key_Access::authenticated(get_userdata($uid),WP_Application_Passwords::get_user_application_password($uid,Guest_Key_Access::grant($uid)['uuid']));
 try {foreach(array('create_users','promote_users','promote_user','delete_user') as $cap){$assert(!current_user_can($cap,$owner),'Temporary API credentials lose native capability '.$cap);}$assert(current_user_can('edit_posts'),'Temporary API credentials retain content editing');}
 finally {Guest_Key_Access::authentication_failed();}
 foreach(array(array('POST','wp/v2/users',array('username'=>'forbidden','email'=>'forbidden@example.invalid','password'=>'not-created')),array('POST','wp/v2/users/'.$uid,array('roles'=>array('subscriber'))),array('POST','wp/v2/users/'.$uid,array('password'=>'not-changed')),array('POST','wp/v2/users/'.$uid,array('email'=>'not-changed@example.invalid')),array('DELETE','wp/v2/users/'.$uid,array('force'=>true,'reassign'=>$owner))) as $case){$r=$http($case[0],$case[1],$key,$case[2]);$assert(403===$r[0],'Direct REST denies sensitive user operation: '.$case[0].' '.implode(',',array_keys($case[2])));}
 $r=$http('POST','wp/v2/users/'.$uid,$key,array('first_name'=>'Safe profile'));wp_cache_delete($uid,'user_meta');$assert(200===$r[0]&&'Safe profile'===get_user_meta($uid,'first_name',true),'Direct REST retains basic profile edits (HTTP '. $r[0] . ', '.($r[1]['code']??'ok').')');
 $plain=add_query_arg('rest_route','/wp/v2/users/'.$uid,home_url('/'));
 $response=wp_remote_post($plain,array('headers'=>array('Authorization'=>'Basic '.base64_encode($key['username'].':'.$key['password']),'Content-Type'=>'application/json'),'body'=>wp_json_encode(array('first_name'=>'Plain permalink profile')),'sslverify'=>false));
 $assert(200===wp_remote_retrieve_response_code($response),'Profile policy permits native plain-permalink transport parameters');

 $r=$http('POST','batch/v1',$key,array('requests'=>array(array('method'=>'POST','path'=>'/wp/v2/users/'.$uid,'body'=>array('roles'=>array('subscriber'))))));$assert(403===($r[1]['responses'][0]['status']??0),'Native REST batch cannot bypass role restrictions');
 $r=$run('rest request',array('method'=>'POST','path'=>'/wp/v2/users/'.$uid,'parameters'=>array('roles'=>array('subscriber'))));$assert(!$r['ok'],'REST forwarding cannot bypass role restrictions');
 $r=$run('ability run',array('name'=>'guest-key/manage-users','input'=>array('method'=>'POST','path'=>'/wp/v2/users/'.$uid,'parameters'=>array('password'=>'denied'))));$assert(!$r['ok'],'Registered user ability cannot bypass password restrictions');
 $r=$run('user meta update',array('user_id'=>$uid,'key'=>'wp_capabilities','value'=>array('administrator'=>true)));$assert(!$r['ok'],'Raw user capability metadata stays protected');
 $r=$http('POST','guest-key/v1/run',$key,array('commands'=>array(array('command'=>'cron list','input'=>array('hook'=>$hook)),array('command'=>'theme get'))));$assert(200===$r[0]&&$r[1]['ok'],'Cron and theme commands execute through authenticated HTTP');
 $r=$http('GET','wp/v2/users/me',$key);$assert(200===$r[0]&&$uid===$r[1]['id'],'Reading user information remains available');
 $nonce=static function($url){$r=wp_remote_get($url,array('sslverify'=>false));preg_match('/name="guest_key_nonce"\s+value="([^"]+)"/',wp_remote_retrieve_body($r),$m);return $m[1]??'';};
 $login=Guest_Key_Browser::url();$response=wp_remote_post($login,array('sslverify'=>false,'redirection'=>0,'body'=>array('log'=>$key['username'],'pwd'=>$key['password'],'guest_key_nonce'=>$nonce($login))));$cookies=wp_remote_retrieve_cookies($response);$assert(302===wp_remote_retrieve_response_code($response)&&count($cookies)>0,'Guest browser sign-in returns session cookies (HTTP '.wp_remote_retrieve_response_code($response).')');
 $r=wp_remote_get(admin_url('user-new.php'),array('sslverify'=>false,'redirection'=>0,'cookies'=>$cookies));$assert(403===wp_remote_retrieve_response_code($r),'Guest browser sessions cannot open account creation (HTTP '.wp_remote_retrieve_response_code($r).', '.wp_remote_retrieve_header($r,'location').')');
 $r=wp_remote_get(admin_url('user-edit.php?user_id='.$owner),array('sslverify'=>false,'redirection'=>0,'cookies'=>$cookies));$assert(in_array(wp_remote_retrieve_response_code($r),array(403,500),true)&&false!==strpos(wp_remote_retrieve_body($r),'not allowed to edit this user'),'Guest browser sessions cannot edit administrator accounts');
 wp_set_current_user(0);$assert(is_wp_error(wp_get_ability('guest-key/manage-cron')->execute(array('action'=>'list'))),'Anonymous users cannot inspect cron');
} finally {
 wp_set_current_user($owner);foreach($created_hooks as $h){wp_unschedule_hook($h,true);}delete_option('gk_operations_result');
 if(is_int($term)){wp_delete_term($term,'category');}if(is_int($uid)){Guest_Key_Access::revoke($uid);wp_delete_user($uid);}unregister_term_meta('category','_gk_operations_denied');
 if($adapter){Guest_Key_Dependency::ensure();}elseif(is_plugin_active(Guest_Key_Dependency::PLUGIN)){deactivate_plugins(Guest_Key_Dependency::PLUGIN);}if(null===$managed){delete_option('guest_key_adapter_managed');}else{update_option('guest_key_adapter_managed',$managed,false);}
}
echo "Completed {$checks} operations-policy checks; fixtures removed.\n";
