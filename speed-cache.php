<?php
/*
Plugin Name: Speed-Cache
Plugin URI: http://lipidity.com/web/wordpress/speed-cache/
Description: Mirror external files locally to deliver huge speed boosts to your site
Version: 0.1
Author: Ankur Kothari
Author URI: http://lipidity.com
*/

class speedy
{
	var $options = array();
	var $debug = true;

	function speedy(){
		register_activation_hook(__FILE__, array(&$this, 'activate'));
		add_action('admin_head', array(&$this, 'speedy_admin_sp'));
		add_action('admin_menu', array(&$this, 'admin_menu'));
		add_action('wp_login',array(&$this,'speed_cache'));
//		add_action('deactivate_'.$this->plugin_basename(__FILE__), array(&$this, 'deactivate'));
		if ($the_options = get_option('speed_cache'))
			$this->options = $the_options;
	}

	function activate() {
		if (!get_option('speed_cache'))
			add_option('speed_cache',$this->options,'Settings for speed cache plugin', 'no');
		return true;
	}

	function deactivate() {
		delete_option('speed_cache');
		return true;
	}

	function speedy_admin_sp(){
	?>
		<style type='text/css'>
		.sc table { text-align: center; }
		.sc input { width: 90%; }
		</style>
	<?php
	}

	function admin_menu() {
		add_options_page('Speed Cache', 'Speed Cache', 'manage_options', plugin_basename(__FILE__), array(&$this, 'options_page'));
	}

	function options_page() {
		if (isset($_POST['sc_submit']) || isset($_POST['sc_force'])) {
			update_option('sc_notify_upd', isset($_POST['sc_email_update']));
			update_option('sc_notify_err', isset($_POST['sc_email_errors']));
			$old_options = $this->options;
			$this->options = array_map( 'wp_specialchars', (array) $_POST['sc_cache'] );
			foreach($this->options as $id => $set){
				$thing = (int) $set[3];
				$this->options[$id][3] = (empty($thing)) ? 14 : $thing;
				$thing = trim($set[1]);
				if(empty($thing)) unset($this->options[$id]);
			}
			foreach($this->options as $id => $set){
				if(isset($old_options[$id][5])) $this->options[$id][5] = $old_options[$id][5];
				if(isset($old_options[$id][0])) $this->options[$id][0] = $old_options[$id][0];
				if($set[1]!=$old_options[$id][1] || $set[2]!=$old_options[$id][2])
					$this->speed_cache($id, false);
			}
			update_option('speed_cache',$this->options);
			echo '<div id="message" class="updated fade"><p>Your settings have been saved!</p></div>';
			$this->speed_cache();
		} elseif (isset($_POST['sc_reset'])) {
			$this->options = array();
			update_option('speed_cache', array());
		} elseif (isset($_REQUEST['sc_upd'])) {
			$this->speed_cache((int) $_REQUEST['sc_upd']);
		}

		echo '<div class="wrap">';
		echo '<h2>Speed Caching Options</h2>';
		echo '<p>This plugin allows you to cache external files, such as Javascript, from external sources onto your site, resulting in faster page loading times.</p>
		<form action="" method="post">
		<div class="sc">
		<table width="100%"><thead>
<tr><th>File URL</th><th>Local Mirror</th><th>Update interval (days)</th><th>Last Updated</th><th>Error</th></tr>
</thead><tbody>';
		foreach ($this->options as $key => $info){
			if( isset($info[5]) ) $atts = " style='background: red'";
			else $atts = '';
			echo '<tr'.$atts.'>
<td><input type="text" name="sc_cache['.$key.'][1]" value="'.wp_specialchars($info[1]).'" /></td>
<td><input type="text" name="sc_cache['.$key.'][2]" value="'.wp_specialchars($info[2]).'" /></td>
<td><input type="text" name="sc_cache['.$key.'][3]" value="'.wp_specialchars($info[3]).'" /></td>
<td>';
if($info[0]) echo date('j M, y',(int)$info[0]);
else echo 'Never';
echo '</td><td>';
if(isset($info[5])) echo $info[5];
else echo '-';
echo '</td>
<td><a href="';
echo clean_url(add_query_arg("sc_upd",$key));
echo '">Upd</a></td>
</tr>';
		}
	$next = (count($this->options));
	echo '<tr>
<td><input type="text" name="sc_cache['.$next.'][1]" /></td>
<td><input type="text" name="sc_cache['.$next.'][2]" /></td>
<td><input type="text" name="sc_cache['.$next.'][3]" /></td>
</tr>';
		echo '</tbody></table></div>
		<ul>
		<li><label for="email_upd"><input type="checkbox" name="sc_email_update" value="1" id="email_upd"';
		echo (get_option("sc_notify_upd")) ? ' checked="checked"':'';
		echo ' /> Send update notification by email</label></li>
		<li><label for="email_err"><input type="checkbox" name="sc_email_errors" value="1" id="email_err"';
		echo (get_option("sc_notify_err")) ? ' checked="checked"':'';
		echo ' /> Send error notification by email</label></li>
		</ul>
		<p class="submit">
		<input type="submit" name="sc_submit" value="Save" />
		<input type="submit" name="sc_force" value="Save / Update" />
		</p></form>';
		echo '</div>';
		echo '<div class="wrap">Want to know more? Need assistance? Visit the <a href="http://lipidity.com/web/wordpress/speed-cache/">Speed Cache</a> homepage.</div>';
	}

	function speed_cache($singleid = false, $resync_options = true){
		if($singleid!==false) {
			$this->_check($singleid, $this->options[$singleid], true);
		} else {
			if(isset($_REQUEST['sc_force'])) $force = true; else $force = false;
			foreach($this->options as $key => $info){
				$this->_check($key, $info, 1);
			}
		}
		if($resync_options)
			update_option('speed_cache', $this->options);
	}

	function _check($key, $info, $force=false){
		$rnow = time();
		if( $force || ($rnow-$info[0]) > ($info[3]*86400) ){
			$script_url = trim($info[1]);
			$local_path = preg_replace('#[^a-zA-Z0-9_\-\/\.]#i','',$info[2]);
			$local_path = preg_replace('#\.{2,}#','', $local_path);
			if($local_path{0} == '/') $local_path = substr($local_path, 1);
			if( !isset($local_path{2}) ){
				$this->options[$key][5] = 'Path too short';
				return false;
			} elseif( !isset($script_url{10}) ){
				$this->options[$key][5] = 'URL too short';
				return false;
			}
			if( ($failure = $this->do_update($script_url, $local_path, $info[3])) == 1 ){
				unset($this->options[$key][5]);
				$this->options[$key][0] = $rnow;
				// todo: link to options page
				if($this->debug && !$force)
					echo '<div class="wrap">The Speed Cache plugin has successfully mirrored the URL "'.$script_url.'" to the path "'.$local_path.'" on your WordPress blog.<br />The next update will be made in '.$info[3].' days time on the '.date('jS \of F, Y').'</div>';
				elseif(get_option('sc_notify_upd'))
					@wp_mail(get_option('admin_email'), 'Speed Cache Succeeded', 'The Speed Cache plugin has successfully mirrored the URL "'.$script_url.'" to the path "'.$local_path.'" on your WordPress blog.\nThe next update will be made in '.$info[3].' days time on '.date('jS \of F, Y'));
			} else {
				$this->options[$key][5] = $failure;
				if($this->debug && !$force)
					echo '<div class="wrap">The Speed Cache plugin FAILED to mirror the URL "'.$script_url.'" to the path "'.ABSPATH.$local_path.'" on your WordPress blog. The attempt was made on the '.date('jS \of F, Y \a\t H:i').'.<br />Another attempt will be made in '.$info[3].' days.</div>';
				elseif(get_option('sc_notify_err'))
					@wp_mail(get_option('admin_email'), 'Speed Cache Failed', 'The Speed Cache plugin FAILED to  mirror the URL "'.$script_url.'" to the path "'.$local_path.'" on your WordPress blog. The attempt was made on the '.date('jS \of F, Y \a\t H:i').'.\nAnother attempt will be made in '.$info[3].' days.');
			}
		}
	}

	function do_update($script_url, $local_path, $keepdays=14){
		$local_path = ABSPATH . $local_path;
		if(!$output = @wp_remote_fopen($script_url)) return "Couldn't fetch URL";
		if( is_writable($local_path) ){
			if( !$handle = fopen($local_path, 'w') ) return "Couldn't fopen path";
			$output = apply_filters('speed_cache', $output, $script_url, $local_path, $keepdays);
			if( fwrite($handle, $output) === false ){
				return 'fwrite failed';
			} else {
				fclose($handle);
				return 1;
			}
		} else {
			return (file_exists($local_path)) ? 'Path not writable' : "Path doesn't exist";
		}
		return 'Failed @ '.date('j M, y H:i');
	}
}

$speedy = new speedy();

?>