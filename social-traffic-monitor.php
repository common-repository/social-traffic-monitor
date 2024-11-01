<?php

/*
Plugin Name: Social Traffic Monitor
Plugin URI: http://www.chrisfinke.com/wordpress/plugins/social-traffic/
Description: Monitors and displays traffic stats related to social news and bookmarking websites.
Version: 1.3.1
Author: Christopher Finke
Author URI: http://www.chrisfinke.com/
*/

if (isset($_GET["social_traffic_chart"])){
	header("Content-type: image/png");
	
	$_GET["hours"] = max(intval($_GET["hours"]), 1);
	if (!is_array($_GET["hits"])) $_GET["hits"] = array();
	
	$hits = array_fill(0,$_GET["hours"],0);
	
	foreach ($_GET["hits"] as $hour => $hourhits){
		$hits[$hour] = $hourhits;
	}
	
	$hits = array_slice($hits, 0, $_GET["hours"]);
	$hits = array_reverse($hits);
	$total_hits = $_GET["total"];
	
	$width = $_GET["hours"] * 2;
	$height = 90;
	
	$im = imagecreate($width, $height);
	$background = imagecolorallocate($im, 255,255,255);
	$gray = imagecolorallocate($im, 160, 160, 160);
	$black = imagecolorallocate($im, 0,0,0);
	$string = $total_hits." Views";
	imagestring($im, 3, (imagesx($im) - 7.5 * strlen($string)) / 2, 7, $string, $black);
	imageline($im, 0, 25, imagesx($im) - 1, 25, $gray);
	imagerectangle($im, 0, 0, $width - 1, $height - 1, $gray);
	$high_end = max($hits);
	
	for ($i = 0; $i < count($hits); $i++){
		if ($high_end != 0) {
			$factor = ($hits[$i] / $high_end);
			
			$lineHeight = $factor * ($height - 30);
			
			if ($lineHeight > 0){
				$lineColor = imagecolorallocate($im, 100 + round(155 * $factor),170,max(100,(255 - (100 + round(155 * $factor)))));
				
				imageline($im, $i*2, $height - 2, $i*2, $height - 2 - $lineHeight, $lineColor);
			}
		}
	}
	
	imagepng($im);
	imagedestroy($im);
	exit;
}
else {
	if(!class_exists('SOCIAL_TRAFFIC_DB')) {
		class SOCIAL_TRAFFIC_DB {
			function create(){
				global $table_prefix;
				global $wpdb;
				
				$sql = "CREATE TABLE IF NOT EXISTS ".$table_prefix."socialtraffic (
					postId INT(11) NOT NULL DEFAULT 0,
					referrer VARCHAR(255) NOT NULL DEFAULT '',
					referringDomain VARCHAR(255) NOT NULL DEFAULT '',
					dateHit DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
					INDEX postId (postId) )";
				$result = $wpdb->query($sql);
				
				$version = get_option("social_traffic_version");
				
				if (!$version) {
					update_option("social_traffic_show_chart",true);
					update_option("social_traffic_hours_of_traffic",72);
					update_option("social_traffic_show_referrers",true);
					update_option("social_traffic_num_referrers",5);
					
					update_option("social_traffic_sites_digg",true);
					update_option("social_traffic_sites_netscape",true);
					update_option("social_traffic_sites_reddit",true);
					update_option("social_traffic_sites_fark",false);
					update_option("social_traffic_sites_newsvine",true);
					update_option("social_traffic_sites_slashdot",false);
					update_option("social_traffic_sites_delicious",false);
					
					update_option("social_traffic_version","1.0");
					$version = "1.0";
				}
				
				if ($version == "1.0"){
					update_option("social_traffic_version","1.1");
					$version = "1.1";
				}
				
				if ($version == "1.1"){
					update_option("social_traffic_sites_stumbleupon",true);
					update_option("social_traffic_version","1.2");
				}
			}
			
			function log_hit($postId, $url, $domain){
				global $wpdb, $table_prefix;
				
				$sql = "INSERT INTO ".$table_prefix."socialtraffic
					SET postId=".intval($postId).",
					referrer='".mysql_real_escape_string($url)."',
					referringDomain='".mysql_real_escape_string($domain)."',
					dateHit=NOW()";
				$result = $wpdb->query($sql);
			}
			
			function hit_check($postId){
				global $wpdb, $table_prefix;
				
				$sql = "SELECT * FROM ".$table_prefix."socialtraffic
					WHERE postId=".intval($postId)." LIMIT 1";
				return $wpdb->query($sql);
			}
			
			function get_hits($postId){
				global $wpdb, $table_prefix;
				
				$sql = "SELECT COUNT(*) hits, HOUR(TIMEDIFF(NOW(), dateHit)) AS hour FROM ".$table_prefix."socialtraffic WHERE postId=".intval($postId)." GROUP BY hour ORDER BY hour ASC";
				$result = $wpdb->query($sql);
				
				if ($result > 0){
					foreach ($wpdb->last_result as $row){
						$hits[] = array("hour" => $row->hour, "hits" => $row->hits);
					}
					return $hits;
				}
				else {
					return false;
				}
			}
			
			function get_referrers($postId){
				global $wpdb, $table_prefix;
				
				$limit = max(1,intval(get_option("social_traffic_num_referrers")));
				
				$sql = "SELECT COUNT(*) hits, referrer, referringDomain FROM ".$table_prefix."socialtraffic WHERE postId=".intval($postId)." AND referrer <> '' GROUP BY referrer ORDER BY hits DESC LIMIT ".$limit;
				$result = $wpdb->query($sql);
				
				if ($result > 0){
					$referrers = array();
					
					foreach ($wpdb->last_result as $row){
						if (!isset($referrers[$row->referringDomain])){
							$referrers[$row->referringDomain] = array();
						}
						
						$referrers[$row->referringDomain][] = array("url" => $row->referrer, "hits" => $row->hits);
					}
					
					return $referrers;
				}
				else {
					return array();
				}
			}
		}
		
		$socialtraffic_db = new SOCIAL_TRAFFIC_DB();
	}
	
	if(!class_exists('SOCIAL_TRAFFIC')) {
		class SOCIAL_TRAFFIC {
			function createtable() {
				global $socialtraffic_db;
				$socialtraffic_db->create();
			}
			
			function log_hit() {
				if (is_single() || is_page()){
					global $socialtraffic_db;
					global $post;
					
					// get key hit information
					if (isset($_SERVER["HTTP_REFERER"])){
						$referrer = $_SERVER["HTTP_REFERER"];
						
						$pagehiturl = $_SERVER['REDIRECT_URL'];
						
						if($post->ID > 0) {
							$domain = '';
							$domainwww = '';
							$url = '';
							$urlwww = '';
							
							// get the domain name
							$domainwww = preg_replace( "'^[^\/]*\/\/'si", "", $referrer );
							
							if( preg_match("'/'", $domainwww) )
								$tail = preg_replace("'^[^\/]*/'si", "",  $domainwww);
							
							$domainwww = preg_replace("'\/.*$'si", "", $domainwww);
							
							// get rid of any leading trippple-dubs in domain - improves presentational consistency
							$domain = trim( preg_replace("/^www\./si", "", $domainwww) );
							
							$yoursite = $_SERVER["SERVER_NAME"];
							$yoursite = preg_replace("/^www./Uis","",$yoursite);
							
							if ($yoursite == $domain){
								return;
							}
							
							$social_sites = array();
							
							if (get_option("social_traffic_sites_digg")) $social_sites[] = "digg\.com";
							if (get_option("social_traffic_sites_netscape")) $social_sites[] = "propeller\.com";
							if (get_option("social_traffic_sites_reddit")) $social_sites[] = "reddit\.com";
							if (get_option("social_traffic_sites_fark")) $social_sites[] = "fark\.com";
							if (get_option("social_traffic_sites_newsvine")) $social_sites[] = "newsvine\.com";
							if (get_option("social_traffic_sites_slashdot")) $social_sites[] = "slashdot\.org";
							if (get_option("social_traffic_sites_delicious")) $social_sites[] = "delicious\.com";
							if (get_option("social_traffic_sites_stumbleupon")) $social_sites[] = "stumbleupon\.com";
							
							if (empty($social_sites)){
								return;
							}
							else {
								$social_sites = implode("|",$social_sites);
								
								if (!preg_match("/(".$social_sites.")$/", $domain)){
									// Only store hits once someone has hit it from a social news site
									
									if (!$socialtraffic_db->hit_check($post->ID)){
										return;
									}
								}
								
								$url = "http://$domain/$tail";
								
								$socialtraffic_db->log_hit($post->ID, $url, $domain);
							}
						}
					}
				}
			}
			
			function display($postId, $formatString){
				global $socialtraffic_db;
				
				$output = '';
				
				if (get_option("social_traffic_show_chart")){
					$hits = $socialtraffic_db->get_hits($postId);
					
					if ($hits !== false){
						$hours = intval(get_option("social_traffic_hours_of_traffic"));
						$hours = max($hours, 1);
						
						if (!is_array($hits)) $hits = array();
						
						$total_hits = 0;
						
						foreach ($hits as $hit) {
							$total_hits += $hit["hits"];
						}
						
						function sort_hits($a, $b){
							if ($a["hour"] < $b["hour"]){
								return -1;
							} else if ($a["hour"] > $b["hour"]){
								return 1;
							} else {
								return 0;
							}
						}
						
						usort($hits, 'sort_hits');
						
						$hits = array_slice($hits, 0, $hours);
						
						foreach ($hits as $key => $hit) {
							if ($hit["hour"] > $hours) {
								unset($hits[$key]);
							}
						}
						
						$hits = array_reverse($hits);
						
						$output .= '<img src="/wp-content/plugins/social-traffic.php?social_traffic_chart=1&total='.$total_hits.'&hours='.$hours;
						foreach ($hits as $hit) $output .= '&hits['.$hit["hour"].']='.$hit["hits"];
						$output .= '" title="'.$total_hits.' hits since this page hit the social news world" alt="'.$total_hits.' hits since this page hit the social news world" />';
					}
				}
				
				if (get_option("social_traffic_show_referrers")){
					$referrers = $socialtraffic_db->get_referrers($postId);
					
					if (count($referrers) > 0){
						$output .= '<p>';
						
						if (get_option("social_traffic_num_referrers") == 1){
							$output .= 'Top referring page:';
						}
						else {
							$output .= 'Top '.get_option("social_traffic_num_referrers").' referring pages:';
						}
						
						$output .= '<ol>';
						
						foreach ($referrers as $domain => $refs){
							$output .= '<li>'.$domain.': ';
							foreach ($refs as $ref) {
								$output .= '<a href="'.$ref["url"].'">'.$ref["hits"].'</a>, ';
							}
							$output = substr($output, 0, -2);
							$output .= '</li>';
						}
						$output .= '</ol>';
					}
				}
				
				if ($output != '') {
					$output = str_replace('%s','<h3><a href="http://www.efinke.com/wordpress/plugins/social-traffic/">Social Traffic Stats</a></h3>'.$output, $formatString);
					echo $output;
				}
			}
			
			function add_options_menu() {
				if (function_exists('add_options_page')){
					add_options_page('Social Traffic Monitor Options','Social Traffic Monitor',8,basename(__FILE__),'social_traffic_options');
				}
			}
			
			function options() {
				if (isset($_POST["social_traffic_update"])){
					update_option("social_traffic_show_chart",isset($_POST["social_traffic_show_chart"]));
					update_option("social_traffic_hours_of_traffic",intval($_POST["social_traffic_hours_of_traffic"]));
					update_option("social_traffic_show_referrers",isset($_POST["social_traffic_show_referrers"]));
					update_option("social_traffic_num_referrers",intval($_POST["social_traffic_num_referrers"]));
					
					update_option("social_traffic_sites_digg",isset($_POST["social_traffic_sites_digg"]));
					update_option("social_traffic_sites_netscape",isset($_POST["social_traffic_sites_netscape"]));
					update_option("social_traffic_sites_reddit",isset($_POST["social_traffic_sites_reddit"]));
					update_option("social_traffic_sites_fark",isset($_POST["social_traffic_sites_fark"]));
					update_option("social_traffic_sites_newsvine",isset($_POST["social_traffic_sites_newsvine"]));
					update_option("social_traffic_sites_slashdot",isset($_POST["social_traffic_sites_slashdot"]));
					update_option("social_traffic_sites_delicious",isset($_POST["social_traffic_sites_delicious"]));
					update_option("social_traffic_sites_stumbleupon",isset($_POST["social_traffic_sites_stumbleupon"]));
				} ?>
				<div class="wrap">
				<h2>Social Traffic Monitor Options</h2>
				<form method="post">
					<fieldset class="options">
						<fieldset>
							Begin tracking page views after receiving a visitor from
							<p style="float: left; width: 25%;"><input type="checkbox" name="social_traffic_sites_digg" value="1" <? if (get_option("social_traffic_sites_digg")) { ?>checked="checked"<? } ?> /> Digg</p>
							<p style="float: left; width: 25%;"><input type="checkbox" name="social_traffic_sites_netscape" value="1" <? if (get_option("social_traffic_sites_netscape")) { ?>checked="checked"<? } ?> /> Propeller</p>
							<p style="float: left; width: 25%;"><input type="checkbox" name="social_traffic_sites_reddit" value="1" <? if (get_option("social_traffic_sites_reddit")) { ?>checked="checked"<? } ?> /> Reddit</p>
							<p style="float: left; width: 25%;"><input type="checkbox" name="social_traffic_sites_fark" value="1" <? if (get_option("social_traffic_sites_fark")) { ?>checked="checked"<? } ?> /> Fark</p>
							<p style="float: left; width: 25%;"><input type="checkbox" name="social_traffic_sites_newsvine" value="1" <? if (get_option("social_traffic_sites_newsvine")) { ?>checked="checked"<? } ?> /> Newsvine</p>
							<p style="float: left; width: 25%;"><input type="checkbox" name="social_traffic_sites_slashdot" value="1" <? if (get_option("social_traffic_sites_slashdot")) { ?>checked="checked"<? } ?> /> Slashdot</p>
							<p style="float: left; width: 25%;"><input type="checkbox" name="social_traffic_sites_delicious" value="1" <? if (get_option("social_traffic_sites_delicious")) { ?>checked="checked"<? } ?> /> delicious.com</p>
							<p style="float: left; width: 25%;"><input type="checkbox" name="social_traffic_sites_stumbleupon" value="1" <? if (get_option("social_traffic_sites_stumbleupon")) { ?>checked="checked"<? } ?> /> StumbleUpon</p>
						</fieldset>
						<p><input type="checkbox" name="social_traffic_show_chart" value="1" <? if (get_option("social_traffic_show_chart")) { ?>checked="checked"<? } ?> /> Display chart of traffic activity for the last <input type="text" size="2" name="social_traffic_hours_of_traffic" value="<?=intval(get_option("social_traffic_hours_of_traffic"))?>" /> hours.</p>
						<p><input type="checkbox" name="social_traffic_show_referrers" value="1" <? if (get_option("social_traffic_show_referrers")) { ?>checked="checked"<? } ?> /> Show top <input type="text" size="1" name="social_traffic_num_referrers" value="<?=intval(get_option("social_traffic_num_referrers"))?>" /> referring pages.</p>
					</fieldset>
					<input type="hidden" name="social_traffic_update" value="1"/>
					<p class="submit"><input type="submit" name="Submit" value="<?php _e('Update Options') ?> &raquo;" /></p>
				</div>
				<?
			}
		}
	}
	
	function social_traffic_options(){
		SOCIAL_TRAFFIC::options();
	}
	
	function social_traffic($formatString = '%s'){
		if (is_single() || is_page()){
			global $post;
			
			SOCIAL_TRAFFIC::display($post->ID, $formatString);
		}
	}
	
	add_action('wp_head', array('SOCIAL_TRAFFIC', 'createtable'));
	add_action('shutdown', array('SOCIAL_TRAFFIC', 'log_hit'));
	add_action('admin_menu', array('SOCIAL_TRAFFIC','add_options_menu'));
}

?>
