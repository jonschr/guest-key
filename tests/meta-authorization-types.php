<?php
/** Local regression for numeric-string post IDs and native metadata authorization. */
if (!defined('WP_CLI') || !WP_CLI || !in_array(wp_get_environment_type(),array('local','development'),true) || !Guest_Key_Access::administrator()) { throw new RuntimeException('Local administrator only.'); }
error_reporting(E_ALL & ~E_DEPRECATED);
$id=null; $checks=0; $trace=array();
$assert=static function($ok,$message)use(&$checks){if(!$ok){throw new RuntimeException($message);} $checks++;echo "PASS: {$message}\n";};
$protect=static function($protected,$key,$type){return $type==='post' && $key==='file' ? true : $protected;};
$observe=static function($allowed,$key,$object,$user,$cap)use(&$trace){$trace=array('allowed'=>$allowed,'key'=>$key,'object_type'=>gettype($object),'user_type'=>gettype($user),'cap_type'=>gettype($cap));return $allowed;};
$deny='__return_false';
register_post_type('gk_doc_auth',array('supports'=>array('title','custom-fields'),'show_in_rest'=>true));
$call=static function($action,$key,$value=null)use(&$id){return wp_get_ability('guest-key/manage-post-meta')->execute(array('action'=>$action,'post_id'=>$id,'key'=>$key,'value'=>$value));};
$cache_string=static function()use(&$id){$post=get_post($id);$post->ID=(string)$id;wp_cache_set($id,(object)$post,'posts');};
try {
 $id=wp_insert_post(array('post_type'=>'gk_doc_auth','post_status'=>'draft','post_title'=>'Temporary metadata authorization fixture','post_author'=>get_current_user_id()));
 update_post_meta($id,'file',15284);update_post_meta($id,'_file','field_67e3660e17e30');
 $cache_string();$assert(gettype(get_post($id)->ID)==='string','Fixture retains numeric-string WP_Post ID');
 // Classification alone is not an explicit authorization restriction.
 add_filter('is_protected_meta',$protect,10,3); add_filter('auth_post_meta_file',$observe,100,5);
 $r=$call('update','file',15462);
 $assert(!is_wp_error($r) && (int)get_post_meta($id,'file',true)===15462,'String-ID document accepts unregistered file reference update');
 $assert(get_post_meta($id,'_file',true)==='field_67e3660e17e30','ACF field reference remains unchanged');
 $assert($trace['allowed']===true && $trace['object_type']==='integer' && $trace['user_type']==='integer' && $trace['cap_type']==='string','Baseline matches native hook argument types');
 $assert(!current_user_can('edit_post_meta',$id,'file'),'Temporary allowance does not leak outside Guest Key');
 foreach (array(PHP_INT_MIN,110,PHP_INT_MAX) as $priority) {
  add_filter('auth_post_meta_file',$deny,$priority);
  try {$r=$call('update','file',999);$assert(is_wp_error($r) && $r->get_error_data()['stage']==='metadata_authorization','Explicit field restriction is retained and explained at priority '.$priority);}
  finally {remove_filter('auth_post_meta_file',$deny,$priority);}
 }
 $assert((int)get_post_meta($id,'file',true)===15462,'Denied field write preserves existing value');
 $deny_cap=static function($caps,$cap){return $cap==='edit_post_meta' ? array('do_not_allow') : $caps;};
 add_filter('map_meta_cap',$deny_cap,100,2);
 try {$r=$call('update','file',999);$assert(is_wp_error($r) && $r->get_error_data()['stage']==='capability','Capability filter denial is distinguished from field authorization');}
 finally {remove_filter('map_meta_cap',$deny_cap,100);}
 $deny_post=static function($caps,$cap){return $cap==='edit_post' ? array('do_not_allow') : $caps;};
 add_filter('map_meta_cap',$deny_post,100,2);
 try {$r=$call('update','file',999);$assert(is_wp_error($r) && $r->get_error_data()['stage']==='post_access','Post-level denial is distinguished from field authorization');}
 finally {remove_filter('map_meta_cap',$deny_post,100);}
 $cache_string();
 $r=wp_get_ability('guest-key/save-post')->execute(array('id'=>$id,'meta'=>array('file'=>15462)));
 $assert(!is_wp_error($r),'Combined post save uses the same normalized field authorization');
 echo 'Native argument trace (no credentials): '.wp_json_encode($trace)."\n";
} finally {
 remove_filter('is_protected_meta',$protect,10);remove_filter('auth_post_meta_file',$observe,100);remove_filter('auth_post_meta_file',$deny,110);
 if($id){wp_delete_post($id,true);}unregister_post_type('gk_doc_auth');
}
echo "Completed {$checks} metadata authorization checks; fixtures removed.\n";
