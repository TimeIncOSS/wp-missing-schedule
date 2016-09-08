<?php
/**
 * WP Missing Schedule
 *
 * Publish missing scheduled posts
 *
 * @package   WP_Missing_Schedule
 * @author    Walter Barcelos <walter.barcelos@timeinc.com>
 * @license   GPL-2.0+
 * @link      http://www.timeincuk.com/
 * @copyright 2016 Time Inc. (UK) Ltd
 *
 * @wordpress-plugin
 * Plugin Name:       WP Missing Schedule
 * Plugin URI:        http://www.timeincuk.com/
 * Description:       Publish missing scheduled posts
 * Version:           1.0.0
 * Author:            Walter Barcelos
 * Author URI:        http://walterbarcelos.com/
 * Text Domain:       wp-missing-schedule
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /lang
 * GitHub Plugin URI: https://github.com/TimeIncUK/wp-missing-schedule
 */


// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WP_Missing_Schedule {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * widget file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'wp-missing-schedule';

	/**
	 *
	 * Version number.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $version = '1.0.0';

	/**
	 * Specifies the classname and description, instantiates the widget,
	 * loads localization files, and includes necessary stylesheets and JavaScript.
	 */
	public function __construct() {

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		add_filter( 'cron_schedules', array( $this, 'cron_add_quarter_hourly' ) );
		add_action( 'wp_publish_missing_schedule_posts', array( $this, 'wp_missing_schedule_posts' ) );

	} // end constructor

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function single_activate() {
		$op = 'wp_missing_schedule_posts_dbv';
		$this->schedule_publish_missing_posts();
		update_option( $op, $this->version );
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean $network_wide       True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public function activate( $network_wide ) {

		if ( $network_wide && function_exists( 'is_multisite' ) && is_multisite() ) {
			// Get all blog ids of the current network
			$sites = get_sites( array(
				'fields' => 'ids',
			) );

			foreach ( $sites as $site ) {
				switch_to_blog( $site );
				$this->single_activate();
			}
			restore_current_blog();
		} else {
			$this->single_activate();
		}

	}

	public function single_deactivate() {
		$this->unschedule_publish_missing_posts();
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean $network_wide       True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public function deactivate( $network_wide ) {

		if ( $network_wide && function_exists( 'is_multisite' ) && is_multisite() ) {
			// Get all blog ids of the current network
			$sites = get_sites( array(
				'fields' => 'ids',
			) );

			foreach ( $sites as $site ) {
				switch_to_blog( $site );
				$this->single_deactivate();
			}
			restore_current_blog();
		} else {
			$this->single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int $blog_id ID of the new blog.
	 *
	 * @return void
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		$this->single_activate();
		restore_current_blog();

	}

	/**
	 * Creates a custom wp cron schedule interval (15min)
	 *
	 * @param array $schedules Intervals pre defined in WordPress
	 *
	 * @return array $schedules Intervals plus our custom value
	 */
	public function cron_add_quarter_hourly( $schedules ) {
		// Adds quarter hourly to the existing schedules, if it's not there.
		if ( false === array_key_exists( 'quarterhourly', $schedules ) ) {
			$schedules[ 'quarterhourly' ] = array(
				'interval' => 900,
				'display'  => __( 'Quarter Hourly' ),
			);
		}

		return $schedules;
	}

	/**
	 * Publish the missing scheduled posts
	 *
	 * @return void
	 */
	public function wp_missing_schedule_posts() {

		$post_types = get_post_types( array( 'public' => true ) );

		if ( isset( $post_types['attachment'] ) ) {
			unset( $post_types['attachment'] );
		}

		// Get the missing scheduled posts from the database
		$args = array(
			'post_status' => 'future',
			'post_type'   => array_keys( $post_types ),
			'date_query'  => array(
				array(
					'before' => date( 'Y-m-d H:i:s' ),
				),
			),
		);

		$scheduled_posts = new WP_Query( $args );

		while ( $scheduled_posts->have_posts() ) : $scheduled_posts->the_post();
			$res = update_post_meta( get_the_ID(), $this->plugin_slug, time() );
			wp_publish_post( get_post() );
		endwhile;

		wp_reset_postdata();
	}

	/**
	 * Schedule a wp cron event to publish all missing schedule posts every 15min
	 *
	 * @return void
	 */
	public function schedule_publish_missing_posts() {
		wp_schedule_event( time(), 'quarterhourly', 'wp_publish_missing_schedule_posts' );
	}

	/**
	 * Unschedule the wp cron event
	 *
	 * @return void
	 */
	public function unschedule_publish_missing_posts() {
		$op = 'wp_missing_schedule_posts_dbv';
		delete_option( $op );
		wp_clear_scheduled_hook( 'wp_publish_missing_schedule_posts' );
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return   string Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

} // end class


function wp_missing_schedule_activate( $network_wide ) {
	$object = new WP_Missing_Schedule();
	$object->activate( $network_wide );
}

register_activation_hook( __FILE__, 'wp_missing_schedule_activate' );

function wp_missing_schedule_deactivate( $network_wide ) {
	$object = new WP_Missing_Schedule();
	$object->deactivate( $network_wide );
}

register_deactivation_hook( __FILE__, 'wp_missing_schedule_deactivate' );

add_action( 'plugins_loaded', array( 'WP_Missing_Schedule', 'get_instance' ) );
