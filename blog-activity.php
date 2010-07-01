<?php
/*
Plugin Name: Blog Activity
Plugin URI: 
Description:
Author: Andrew Billits
Version: 1.1.2
Author URI:
WDP ID: 4
*/

/* 
Copyright 2007-2009 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
if ($_GET['page'] == 'blog_activity_main'){
	blog_activity_install();
	blog_activity_upgrade();
}
if ($_GET['action'] == 'blog_activity_cleanup') {
	blog_activity_cleanup();
}
add_action('admin_menu', 'blog_activity_plug_pages');
add_action('comment_post', 'blog_activity_global_db_sync');
add_action('publish_post', 'blog_activity_global_db_sync');
add_action('comment_post', 'blog_activity_comment_global_db_sync');
add_action('publish_post', 'blog_activity_post_global_db_sync');
add_action('blog_activity_cleanup_cron', 'blog_activity_cleanup');
//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//
function blog_activity_upgrade() {
	global $wpdb;
	if (get_site_option( "blog_activity_version" ) == '') {
		add_site_option( 'blog_activity_version', '0.0.0' );
	}
	
	if (get_site_option( "blog_activity_version" ) == "1.0.5") {
		// do nothing
	} else {
		//upgrade code goes here
		//update to current version
		update_site_option( "blog_activity_version", "1.0.5" );
	}
}

function blog_activity_install() {
	global $wpdb;
	if (get_site_option( "blog_activity_installed" ) == '') {
		add_site_option( 'blog_activity_installed', 'no' );
	}
	
	if (get_site_option( "blog_activity_installed" ) == "yes") {
		// do nothing
	} else {
	
		$blog_activity_table1 = "CREATE TABLE `" . $wpdb->base_prefix . "blog_activity` (
  `active_ID` bigint(20) unsigned NOT NULL auto_increment,
  `blog_ID` bigint(35) NOT NULL default '0',
  `last_active` bigint(35) NOT NULL default '0',
  PRIMARY KEY  (`active_ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=17 ;";
		$blog_activity_table2 = "CREATE TABLE `" . $wpdb->base_prefix . "post_activity` (
  `active_ID` bigint(20) unsigned NOT NULL auto_increment,
  `blog_ID` bigint(35) NOT NULL default '0',
  `user_ID` bigint(35) NOT NULL default '0',
  `stamp` bigint(35) NOT NULL default '0',
  PRIMARY KEY  (`active_ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=17 ;";
		$blog_activity_table3 = "CREATE TABLE `" . $wpdb->base_prefix . "comment_activity` (
  `active_ID` bigint(20) unsigned NOT NULL auto_increment,
  `blog_ID` bigint(35) NOT NULL default '0',
  `user_ID` bigint(35) NOT NULL default '0',
  `stamp` bigint(35) NOT NULL default '0',
  PRIMARY KEY  (`active_ID`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=17 ;";
		$wpdb->query( $blog_activity_table1 );
		$wpdb->query( $blog_activity_table2 );
		$wpdb->query( $blog_activity_table3 );
		update_site_option( "blog_activity_installed", "yes" );
	}
}

function blog_activity_post_global_db_sync() {
	global $wpdb, $wp_roles, $current_user;
	if ($wpdb->blogid == '' || $current_user->ID == ''){
		//houston... we have a problem. ABORT!!! ABORT!!! Ok, so it's not that dramatic.
	} else {
		$wpdb->query( "INSERT INTO " . $wpdb->base_prefix . "post_activity (blog_ID, user_ID, stamp) VALUES ( '" . $wpdb->blogid . "', '" . $current_user->ID . "', '" . time() . "' )" );
	}
}

function blog_activity_comment_global_db_sync() {
	global $wpdb, $wp_roles, $current_user;
	if ($wpdb->blogid == '' || $current_user->ID == ''){
		if ($current_user->ID == ''){
			if ($wpdb->blogid == ''){
				//houston... we have a problem. ABORT!!! ABORT!!! Ok, so it's not that dramatic.
			} else {
				$wpdb->query( "INSERT INTO " . $wpdb->base_prefix . "comment_activity (blog_ID, user_ID, stamp) VALUES ( '" . $wpdb->blogid . "', '0', '" . time() . "' )" );			
			}
		}
	} else {
		$wpdb->query( "INSERT INTO " . $wpdb->base_prefix . "comment_activity (blog_ID, user_ID, stamp) VALUES ( '" . $wpdb->blogid . "', '" . $current_user->ID . "', '" . time() . "' )" );
	}
}

function blog_activity_global_db_sync() {
	global $wpdb, $wp_roles, $current_user;
	if ($wpdb->blogid == ''){
		//houston... we have a problem. ABORT!!! ABORT!!! Ok, so it's not that dramatic.
	} else {
		$tmp_blog_activity_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "blog_activity WHERE blog_ID = '" . $wpdb->blogid . "'");
		if ($tmp_blog_activity_count == '0') {
				$wpdb->query( "INSERT INTO " . $wpdb->base_prefix . "blog_activity (blog_ID, last_active) VALUES ( '" . $wpdb->blogid . "', '" . time() . "' )" );
		} else {
				$wpdb->query( "UPDATE " . $wpdb->base_prefix . "blog_activity SET last_active = '" . time() . "' WHERE blog_ID = '" . $wpdb->blogid . "'" );
		}
	}
}

function blog_activity_plug_pages() {
	global $wpdb, $wp_roles, $current_user;
	if ( is_site_admin() ) {
		add_submenu_page('ms-admin.php', 'Blog Activity', 'Blog Activity', 10, 'blog_activity_main', 'blog_activity_page_main_output');
	}
}

function blog_activity_cleanup() {
	global $wpdb;
	$current_stamp = time();
	$month_ago = $current_stamp - 2678400;
	$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "blog_activity WHERE last_active < '" . $month_ago . "'" );
	$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "post_activity WHERE stamp < '" . $month_ago . "'" );
	$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "comment_activity WHERE stamp < '" . $month_ago . "'" );
}

function blog_activity_schedule_cron() {
	if ( get_option('blog_activity_cron_scheduled') != '1' ) {
		$current_stamp = time();
		$current_hour = date('G', $current_stamp);
		if ( $current_hour == '23' ) {
			$schedule_time = $current_stamp;
		} else {
			$add_hours = 23 - $current_hour;
			$add_seconds = $add_hours * 3600;
			$schedule_time = $current_stamp + $add_seconds;
		}
		wp_schedule_event($schedule_time, 'daily', 'blog_activity_cleanup_cron');
		add_option('blog_activity_cron_scheduled', '1');
	}
}

function display_blog_activity_posts($tmp_period) {
	global $wpdb, $wp_roles, $current_user;
	if ($tmp_period == '' || $tmp_period == 0){
		$tmp_period = 1;
	}
	$tmp_period = $tmp_period * 60;
	$tmp_current_stamp = time();
	$tmp_stamp = $tmp_current_stamp - $tmp_period;
	$tmp_output = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "post_activity WHERE stamp < '" . $tmp_stamp . "'");
	
	echo $tmp_output;
}

function display_blog_activity_comments($tmp_period) {
	global $wpdb, $wp_roles, $current_user;
	if ($tmp_period == '' || $tmp_period == 0){
		$tmp_period = 1;
	}
	$tmp_period = $tmp_period * 60;
	$tmp_current_stamp = time();
	$tmp_stamp = $tmp_current_stamp - $tmp_period;
	$tmp_output = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "comment_activity WHERE stamp > '" . $tmp_stamp . "'");
	
	echo $tmp_output;
}

function display_blog_activity_updated($tmp_period) {
	global $wpdb, $wp_roles, $current_user;
	if ($tmp_period == '' || $tmp_period == 0){
		$tmp_period = 1;
	}
	$tmp_period = $tmp_period * 60;
	$tmp_current_stamp = time();
	$tmp_stamp = $tmp_current_stamp - $tmp_period;
	$tmp_output = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "blog_activity WHERE last_active > '" . $tmp_stamp . "'");
	
	echo $tmp_output;
}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function blog_activity_page_main_output() {
	global $wpdb, $wp_roles, $current_user;
	/*
	if(!current_blog_can('manage_options')) {
		echo "<p>Nice Try...</p>";  //If accessed properly, this message doesn't appear.
		return;
	}
	*/
	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e('' . urldecode($_GET['updatedmsg']) . '') ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			blog_activity_schedule_cron();

			$blog_activity_current_stamp = time();

			$blog_activity_current_five_minutes = $blog_activity_current_stamp - 300;
			$blog_activity_current_hour = $blog_activity_current_stamp - 3600;
			$blog_activity_current_day = $blog_activity_current_stamp - 86400;
			$blog_activity_current_week = $blog_activity_current_stamp - 604800;
			$blog_activity_current_month = $blog_activity_current_stamp - 2592000;

			//blog			
			$blog_activity_five_minutes = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "blog_activity WHERE last_active > '" . $blog_activity_current_five_minutes . "'");
			$blog_activity_hour = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "blog_activity WHERE last_active > '" . $blog_activity_current_hour . "'");
			$blog_activity_day = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "blog_activity WHERE last_active > '" . $blog_activity_current_day . "'");
			$blog_activity_week = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "blog_activity WHERE last_active > '" . $blog_activity_current_week . "'");
			$blog_activity_month = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "blog_activity WHERE last_active > '" . $blog_activity_current_month . "'");

			//post		
			$blog_activity_post_five_minutes = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "post_activity WHERE stamp > '" . $blog_activity_current_five_minutes . "'");
			$blog_activity_post_hour = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "post_activity WHERE stamp > '" . $blog_activity_current_hour . "'");
			$blog_activity_post_day = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "post_activity WHERE stamp > '" . $blog_activity_current_day . "'");
			$blog_activity_post_week = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "post_activity WHERE stamp > '" . $blog_activity_current_week . "'");
			$blog_activity_post_month = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "post_activity WHERE stamp > '" . $blog_activity_current_month . "'");

			//comments			
			$blog_activity_comment_five_minutes = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "comment_activity WHERE stamp > '" . $blog_activity_current_five_minutes . "'");
			$blog_activity_comment_hour = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "comment_activity WHERE stamp > '" . $blog_activity_current_hour . "'");
			$blog_activity_comment_day = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "comment_activity WHERE stamp > '" . $blog_activity_current_day . "'");
			$blog_activity_comment_week = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "comment_activity WHERE stamp > '" . $blog_activity_current_week . "'");
			$blog_activity_comment_month = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "comment_activity WHERE stamp > '" . $blog_activity_current_month . "'");
			?>
			<h2><?php _e('Blog Activity') ?></h2>
			<h3>Updated blogs in the last:</h3>
			<p>Five Minutes: <?php echo $blog_activity_five_minutes; ?><br />
			Hour: <?php echo $blog_activity_hour; ?><br />
			Day: <?php echo $blog_activity_day; ?><br />
			Week: <?php echo $blog_activity_week; ?><br />
			Month*: <?php echo $blog_activity_month; ?><br />
			</p>
            <br />
            <h3>Posts in the last:</h3>
			<p>Five Minutes: <?php echo $blog_activity_post_five_minutes; ?><br />
			Hour: <?php echo $blog_activity_post_hour; ?><br />
			Day: <?php echo $blog_activity_post_day; ?><br />
			Week: <?php echo $blog_activity_post_week; ?><br />
			Month*: <?php echo $blog_activity_post_month; ?><br />
			</p>
            <br />
            <h3>Comments in the last:</h3>
			<p>Five Minutes: <?php echo $blog_activity_comment_five_minutes; ?><br />
			Hour: <?php echo $blog_activity_comment_hour; ?><br />
			Day: <?php echo $blog_activity_comment_day; ?><br />
			Week: <?php echo $blog_activity_comment_week; ?><br />
			Month*: <?php echo $blog_activity_comment_month; ?><br />
			</p>
            <br />
			<p>*Month = 30 days<br />
            Note: It will take a full thirty days for all of this data to be accurate. For example, if the plugin has been installed for only a day then only "day", "hour", and "five minutes" will contain accurate data.
            </p>
			<?php
		break;
		//---------------------------------------------------//
		case "remove":
		break;
		//---------------------------------------------------//
		case "temp":
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

?>
