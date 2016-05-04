<?php

class MissingScheduleTest extends WP_UnitTestCase {

	private $post;

	public function setUp() {
		parent::setUp();
		$this->class = WP_Missing_Schedule::get_instance();
	}

	public function test_wp_cron_register() {
		$this->class->schedule_publish_missing_posts();
		$schedule = wp_get_schedule( 'wp_publish_missing_schedule_posts' );
		$this->assertSame( 'quarterhourly', $schedule );
	}

	public function test_publish_missing_post() {
		$post = $this->factory->post->create_and_get( [
			'post_author'  => 1,
			'post_date'    => '2017-01-01 00:00:00'
		]);

		$this->assertSame('future', $post->post_status);

		global $wpdb;
		$wpdb->update( $wpdb->posts, [
			'post_date'     => '2000-01-01 10:10:10',
			'post_date_gmt' => '2000-01-01 10:10:10',
			'post_status'   => 'future',
		],[
			'ID' => $post->ID
		]);
		clean_post_cache( $post->ID );

		$this->class->wp_missing_schedule_posts();
		$updated_post = get_post($post->ID);
		$this->assertSame( 'publish', $updated_post->post_status );

		$flag = get_post_meta( $updated_post->ID, $this->class->get_plugin_slug(), true );
		$date_published = DateTime::createFromFormat( 'U', $flag );
		$this->assertInstanceOf( 'DateTime', $date_published );
	}

}
