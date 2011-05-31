<?php
/*
Plugin Name: Blog Activity
Plugin URI: http://premium.wpmudev.org/project/blog-activity
Description: Collects data on how many blogs were updated in the past
Author: Andrew Billits, Ulrich Sossou
Version: 1.1.4
Network: true
Text Domain: blog_activity
Author URI: http://premium.wpmudev.org/
WDP ID: 4
*/

/*
Copyright 2007-2011 Incsub (http://incsub.com)

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

if( !is_multisite() )
	exit( 'The Blog Activity plugin is only compatible with WordPress Multisite.' );

/**
 * Plugin main class
 **/
class Blog_Activity {

	/**
	 * Current version of the plugin
	 **/
	var $current_version = '1.1.4';

	/**
	 * PHP 4 constructor
	 **/
	function Blog_Activity() {
		__construct();
	}

	/**
	 * PHP 5 constructor
	 **/
	function __construct() {
		global $wp_version;

		add_action( 'admin_init', array( &$this, 'setup' ) );
		add_action( 'comment_post', array( &$this, 'blog_global_db_sync' ) );
		add_action( 'save_post', array( &$this, 'blog_global_db_sync' ) );
		add_action( 'comment_post', array( &$this, 'comment_global_db_sync' ) );
		add_action( 'save_post', array( &$this, 'post_global_db_sync' ) );
		add_action( 'blog_activity_cleanup_cron', array( &$this, 'cleanup' ) );

		// Add the super admin page
		if( version_compare( $wp_version , '3.0.9', '>' ) )
			add_action( 'network_admin_menu', array( &$this, 'network_admin_page' ) );
		else
			add_action( 'admin_menu', array( &$this, 'pre_3_1_network_admin_page' ) );

		// load text domain
		if ( defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/blog-activity.php' ) ) {
			load_muplugin_textdomain( 'blog_activity', 'blog-activity-files/languages' );
		} else {
			load_plugin_textdomain( 'blog_activity', false, dirname( plugin_basename( __FILE__ ) ) . '/blog-activity-files/languages' );
		}
	}

	/**
	 * Plugin db setup
	 **/
	function setup() {
		global $plugin_page;

		// maybe upgrade db
		if( 'blog_activity_main' == $plugin_page ) {
			$this->install();
			$this->upgrade();
		}

		// maybe cleanup activity
		if( isset( $_GET['action'] ) && 'blog_activity_cleanup' == $_GET['action'] ) {
			$this->cleanup();
		}
	}

	/**
	 * Update plugin version in the db
	 **/
	function upgrade() {
		if( get_site_option( 'blog_activity_version' ) == '' )
			add_site_option( 'blog_activity_version', $this->current_version );

		if( get_site_option( 'blog_activity_version' ) !== $this->current_version )
			update_site_option( 'blog_activity_version', $this->current_version );
	}

	/**
	 * Create plugin tables
	 **/
	function install() {
		global $wpdb;

		if( get_site_option( 'blog_activity_installed' ) == '' )
			add_site_option( 'blog_activity_installed', 'no' );

		if( get_site_option( 'blog_activity_installed' ) !== 'yes' ) {

			if( @is_file( ABSPATH . '/wp-admin/includes/upgrade.php' ) )
				include_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
			else
				die( __( 'We have problem finding your \'/wp-admin/upgrade-functions.php\' and \'/wp-admin/includes/upgrade.php\'', 'blog_activity' ) );

			// choose correct table charset and collation
			$charset_collate = '';
			if( $wpdb->supports_collation() ) {
				if( !empty( $wpdb->charset ) ) {
					$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
				}
				if( !empty( $wpdb->collate ) ) {
					$charset_collate .= " COLLATE $wpdb->collate";
				}
			}

			$blog_activity_table = "CREATE TABLE `{$wpdb->base_prefix}blog_activity` (
				`active_ID` bigint(20) unsigned NOT NULL auto_increment,
				`blog_ID` bigint(35) NOT NULL default '0',
				`last_active` bigint(35) NOT NULL default '0',
				PRIMARY KEY  (`active_ID`)
			) $charset_collate;";

			$post_activity_table = "CREATE TABLE `{$wpdb->base_prefix}post_activity` (
				`active_ID` bigint(20) unsigned NOT NULL auto_increment,
				`blog_ID` bigint(35) NOT NULL default '0',
				`user_ID` bigint(35) NOT NULL default '0',
				`stamp` bigint(35) NOT NULL default '0',
				PRIMARY KEY  (`active_ID`)
			) $charset_collate;";

			$comment_activity_table = "CREATE TABLE `{$wpdb->base_prefix}comment_activity` (
				`active_ID` bigint(20) unsigned NOT NULL auto_increment,
				`blog_ID` bigint(35) NOT NULL default '0',
				`user_ID` bigint(35) NOT NULL default '0',
				`stamp` bigint(35) NOT NULL default '0',
				PRIMARY KEY  (`active_ID`)
			) $charset_collate;";


			maybe_create_table( "{$wpdb->base_prefix}blog_activity", $blog_activity_table );
			maybe_create_table( "{$wpdb->base_prefix}post_activity", $post_activity_table );
			maybe_create_table( "{$wpdb->base_prefix}comment_activity", $comment_activity_table );

			update_site_option( 'blog_activity_installed', 'yes' );
		}
	}

	/**
	 * Create post activity entry
	 **/
	function post_global_db_sync() {
		global $wpdb, $current_user;

		if( !( '' == $wpdb->blogid || '' == $current_user->ID ) )
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->base_prefix}post_activity ( blog_ID, user_ID, stamp ) VALUES ( '%d', '%d', '%d' )", $wpdb->blogid, $current_user->ID, time() ) );
	}

	/**
	 * Create comment activity entry
	 **/
	function comment_global_db_sync() {
		global $wpdb, $current_user;

		if( '' == $wpdb->blogid || '' == $current_user->ID ) {
			if( '' == $current_user->ID ) {
				if( '' !== $wpdb->blogid )
					$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->base_prefix}comment_activity ( blog_ID, user_ID, stamp ) VALUES ( '%d', '%d', '%d' )", $wpdb->blogid, 0, time() ) );
			}
		} else {
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->base_prefix}comment_activity ( blog_ID, user_ID, stamp ) VALUES ( '%d', '%d', '%d' )", $wpdb->blogid, $current_user->ID, time() ) );
		}
	}

	/**
	 * Create or update blog activity entry
	 **/
	function blog_global_db_sync() {
		global $wpdb, $current_user;

		if( '' !== $wpdb->blogid ) {
			$tmp_blog_activity_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}blog_activity WHERE blog_ID = '%d'", $wpdb->blogid ) );

			if( '0' == $tmp_blog_activity_count )
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->base_prefix}blog_activity ( blog_ID, last_active ) VALUES ( '%d', '%d' )", $wpdb->blogid, time() ) );
			else
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->base_prefix}blog_activity SET last_active = '%d' WHERE blog_ID = '%d'", time(), $wpdb->blogid ) );
		}
	}

	/**
	 * Add network admin page
	 **/
	function network_admin_page() {
		add_submenu_page( 'settings.php', __( 'Blog Activity', 'blog_activity' ), __( 'Blog Activity', 'blog_activity' ), 'manage_network_options', 'blog_activity_main', array( &$this, 'page_main_output' ) );
	}

	/**
	 * Add network admin page the old way
	 **/
	function pre_3_1_network_admin_page() {
		add_submenu_page( 'ms-admin.php', __( 'Blog Activity', 'blog_activity' ), __( 'Blog Activity', 'blog_activity' ), 'manage_network_options', 'blog_activity_main', array( &$this, 'page_main_output' ) );
	}

	/**
	 * Cleanup activity older than 1 month from activity tables
	 **/
	function cleanup() {
		global $wpdb;
		$current_stamp = time();
		$month_ago = $current_stamp - 2678400;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->base_prefix}blog_activity WHERE last_active < '%d'", $month_ago ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->base_prefix}post_activity WHERE last_active < '%d'", $month_ago ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->base_prefix}comment_activity WHERE last_active < '%d'", $month_ago ) );
	}

	/**
	 * Schedule cron to cleanup the db
	 **/
	function schedule_cron() {
		if( get_option( 'blog_activity_cron_scheduled' ) != '1' ) {
			$current_stamp = time();
			$current_hour = date( 'G', $current_stamp );
			if ( $current_hour == '23' ) {
				$schedule_time = $current_stamp;
			} else {
				$add_hours = 23 - $current_hour;
				$add_seconds = $add_hours * 3600;
				$schedule_time = $current_stamp + $add_seconds;
			}
			wp_schedule_event( $schedule_time, 'daily', $this->cleanup() );

			add_option( 'blog_activity_cron_scheduled', '1' );
		}
	}

	/**
	 * Get activity from db for a set period of type
	 **/
	function get_activity( $tmp_period, $type ) {
		global $wpdb;

		$tmp_period = ( $tmp_period == '' || $tmp_period == 0 ) ? 1 : $tmp_period;
		$tmp_period = $tmp_period * 60;
		$tmp_current_stamp = time();
		$tmp_stamp = $tmp_current_stamp - $tmp_period;
		$tmp_output = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}{$type}_activity WHERE stamp < '%d'", $tmp_stamp ) );

		return $tmp_output;
	}

	/**
	 * Admin page output.
	 **/
	function page_main_output() {
		global $wpdb;

		// Allow access for users with correct permissions only
		if( !current_user_can( 'manage_network_options' ) ) {
			_e( '<p>Nice Try...</p>', 'blog_activity' );
			return;
		}

		// Schedule cron if necessary
		$this->schedule_cron();

		echo '<div class="wrap">';

		$current_stamp = time();

		$current_five_minutes = $current_stamp - 300;
		$current_hour = $current_stamp - 3600;
		$current_day = $current_stamp - 86400;
		$current_week = $current_stamp - 604800;
		$current_month = $current_stamp - 2592000;

		$activity = '';
		foreach( array( 'blog', 'post', 'comment' ) as $object ) {
			$time_field = ( 'blog' == $object ) ? 'last_active' : 'stamp';

			$five_minutes = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}{$object}_activity WHERE $time_field > '$current_five_minutes'" );
			$hour = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}{$object}_activity WHERE $time_field > '$current_hour'" );
			$day = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}{$object}_activity WHERE $time_field > '$current_day'" );
			$week = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}{$object}_activity WHERE $time_field > '$current_week'" );
			$month = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}{$object}_activity WHERE $time_field > '$current_month'" );

			$activity .= '<h3>' . sprintf( __( 'Updated %ss in the last:', 'blog_activity' ), $object ) . '</h3>';
			$activity .= '<p>';
			$activity .= sprintf( __( 'Five Minutes: %d', 'blog_activity' ), $five_minutes ) . '<br />';
			$activity .= sprintf( __( 'Hour: %d', 'blog_activity' ), $hour ) . '<br />';
			$activity .= sprintf( __( 'Day: %d', 'blog_activity' ), $day ) . '<br />';
			$activity .= sprintf( __( 'Week: %d', 'blog_activity' ), $week ) . '<br />';
			$activity .= sprintf( __( 'Month: %d', 'blog_activity' ), $month ) . '<br />';
			$activity .= '</p><br />';
		}

		echo '<h2>' . __( 'Blog Activity', 'blog_activity' ) . '</h2>';
		echo $activity;
		echo '<p>' . __( '* Month = 30 days<br />Note: It will take a full thirty days for all of this data to be accurate. For example, if the plugin has been installed for only a day then only "day", "hour", and "five minutes" will contain accurate data.', 'blog_activity' ) . '</p>';
		echo '</div>';
	}

}

$blog_activity =& new Blog_Activity();

/**
 * Display updated posts for a specific period of time
 **/
function display_blog_activity_posts( $tmp_period ) {
	global $blog_activity;

	echo $blog_activity->get_activity( $tmp_period, 'post' );
}

/**
 * Display updated comments for a specific period of time
 **/
function display_blog_activity_comments( $tmp_period ) {
	global $blog_activity;

	echo $blog_activity->get_activity( $tmp_period, 'comment' );
}

/**
 * Display updated blogs for a specific period of time
 **/
function display_blog_activity_updated( $tmp_period ) {
	global $blog_activity;

	echo $blog_activity->get_activity( $tmp_period, 'blog' );
}

/**
 * Show notification if WPMUDEV Update Notifications plugin is not installed
 **/
if ( !function_exists( 'wdp_un_check' ) ) {
	add_action( 'admin_notices', 'wdp_un_check', 5 );
	add_action( 'network_admin_notices', 'wdp_un_check', 5 );

	function wdp_un_check() {
		if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
			echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
	}
}
