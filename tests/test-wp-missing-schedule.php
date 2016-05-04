<?php

class MissingScheduleTest extends WP_UnitTestCase {

	private $post;

	public function setUp() {
		parent::setUp();

		$this->class = WP_Missing_Schedule::get_instance();

		$this->post = $this->factory->post->create_and_get( [
			'post_title'   => 'Testing Post Title',
			'post_content' => 'Testing Post Content',
			'post_excerpt' => 'Testing Post Excerpt',
			'post_name'    => 'Testing Post Name',
			'post_author'  => 1,
			'post_date'    => '2016-04-01 10:12:00',
			'post_status'  => 'future',
		] );
	}

	public function test_wp_cron_register() {
		$this->class->schedule_publish_missing_posts();
		$schedule = wp_get_schedule( 'wp_publish_missing_schedule_posts' );
		$this->assertSame( 'quarterhourly', $schedule );
	}

	public function test_publish_missing_post() {
		$this->class->wp_missing_schedule_posts();
		$post = get_post( $this->post->ID );
		$this->assertSame( 'publish', $post->post_status );
		$flag = get_post_meta( $this->post->ID, $this->class->get_plugin_slug() );
		$this->assertFalse( false === $flag );
	}

}
