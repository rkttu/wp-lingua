<?php
/**
 * Tests for the Lingua_REST_Controller class.
 *
 * Uses the WordPress REST API test infrastructure (WP_Test_REST_Controller_Testcase)
 * to exercise the lingua/v1 endpoints end-to-end with a real database.
 */

class Test_REST_Controller extends WP_Test_REST_TestCase {

	/**
	 * Admin user ID used for authenticated requests.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID used for permission-denied tests.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Create shared fixtures once for the whole class.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array(
			'role' => 'administrator',
		) );
		self::$subscriber_id = $factory->user->create( array(
			'role' => 'subscriber',
		) );
	}

	public function set_up() {
		parent::set_up();

		// Ensure REST routes are registered.
		do_action( 'rest_api_init' );
	}

	// ------------------------------------------------------------------
	//  Route registration
	// ------------------------------------------------------------------

	public function test_routes_are_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/lingua/v1/link', $routes );
		$this->assertArrayHasKey( '/lingua/v1/unlink/(?P<post_id>[\\d]+)', $routes );
		$this->assertArrayHasKey( '/lingua/v1/translations/(?P<post_id>[\\d]+)', $routes );
	}

	// ------------------------------------------------------------------
	//  POST /lingua/v1/link  – language-map format
	// ------------------------------------------------------------------

	public function test_link_translations_with_language_map() {
		wp_set_current_user( self::$admin_id );

		$post_ko = self::factory()->post->create( array( 'post_title' => '한국어 포스트' ) );
		$post_en = self::factory()->post->create( array( 'post_title' => 'English Post' ) );
		$post_ja = self::factory()->post->create( array( 'post_title' => '日本語の投稿' ) );

		$request = new WP_REST_Request( 'POST', '/lingua/v1/link' );
		$request->set_body_params( array(
			'post_ids' => array(
				'ko' => $post_ko,
				'en' => $post_en,
				'ja' => $post_ja,
			),
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'group_term_id', $data );
		$this->assertArrayHasKey( 'linked', $data );
		$this->assertSame( $post_ko, $data['linked']['ko'] );
		$this->assertSame( $post_en, $data['linked']['en'] );
		$this->assertSame( $post_ja, $data['linked']['ja'] );

		// Verify language meta was set.
		$this->assertSame( 'ko', get_post_meta( $post_ko, '_Lingua_language', true ) );
		$this->assertSame( 'en', get_post_meta( $post_en, '_Lingua_language', true ) );
		$this->assertSame( 'ja', get_post_meta( $post_ja, '_Lingua_language', true ) );

		// Verify all posts share the same taxonomy term.
		$terms_ko = wp_get_object_terms( $post_ko, 'Lingua_group', array( 'fields' => 'ids' ) );
		$terms_en = wp_get_object_terms( $post_en, 'Lingua_group', array( 'fields' => 'ids' ) );
		$terms_ja = wp_get_object_terms( $post_ja, 'Lingua_group', array( 'fields' => 'ids' ) );

		$this->assertSame( $terms_ko, $terms_en );
		$this->assertSame( $terms_en, $terms_ja );
	}

	// ------------------------------------------------------------------
	//  POST /lingua/v1/link  – plain array format
	// ------------------------------------------------------------------

	public function test_link_translations_with_plain_array() {
		wp_set_current_user( self::$admin_id );

		$post_ko = self::factory()->post->create();
		$post_en = self::factory()->post->create();

		// Pre-set language meta.
		update_post_meta( $post_ko, '_Lingua_language', 'ko' );
		update_post_meta( $post_en, '_Lingua_language', 'en' );

		$request = new WP_REST_Request( 'POST', '/lingua/v1/link' );
		$request->set_body_params( array(
			'post_ids' => array( $post_ko, $post_en ),
		) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( $post_ko, $data['linked']['ko'] );
		$this->assertSame( $post_en, $data['linked']['en'] );
	}

	// ------------------------------------------------------------------
	//  POST /lingua/v1/link  – error cases
	// ------------------------------------------------------------------

	public function test_link_requires_authentication() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/lingua/v1/link' );
		$request->set_body_params( array(
			'post_ids' => array( 'ko' => 1, 'en' => 2 ),
		) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	public function test_link_requires_edit_posts_capability() {
		wp_set_current_user( self::$subscriber_id );

		$post1 = self::factory()->post->create();
		$post2 = self::factory()->post->create();

		$request = new WP_REST_Request( 'POST', '/lingua/v1/link' );
		$request->set_body_params( array(
			'post_ids' => array( 'ko' => $post1, 'en' => $post2 ),
		) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_link_rejects_single_post() {
		wp_set_current_user( self::$admin_id );

		$post = self::factory()->post->create();

		$request = new WP_REST_Request( 'POST', '/lingua/v1/link' );
		$request->set_body_params( array(
			'post_ids' => array( 'ko' => $post ),
		) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
	}

	public function test_link_rejects_nonexistent_post() {
		wp_set_current_user( self::$admin_id );

		$post   = self::factory()->post->create();

		$request = new WP_REST_Request( 'POST', '/lingua/v1/link' );
		$request->set_body_params( array(
			'post_ids' => array( 'ko' => $post, 'en' => 999999 ),
		) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 404, $response->get_status() );
	}

	public function test_link_plain_array_rejects_missing_language_meta() {
		wp_set_current_user( self::$admin_id );

		$post1 = self::factory()->post->create();
		$post2 = self::factory()->post->create();
		// Deliberately not setting _Lingua_language.

		$request = new WP_REST_Request( 'POST', '/lingua/v1/link' );
		$request->set_body_params( array(
			'post_ids' => array( $post1, $post2 ),
		) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
	}

	// ------------------------------------------------------------------
	//  GET /lingua/v1/translations/{post_id}
	// ------------------------------------------------------------------

	public function test_get_translations() {
		wp_set_current_user( self::$admin_id );

		$post_ko = self::factory()->post->create( array( 'post_title' => 'KO Post', 'post_status' => 'publish' ) );
		$post_en = self::factory()->post->create( array( 'post_title' => 'EN Post', 'post_status' => 'publish' ) );

		// Link them.
		$link_req = new WP_REST_Request( 'POST', '/lingua/v1/link' );
		$link_req->set_body_params( array(
			'post_ids' => array( 'ko' => $post_ko, 'en' => $post_en ),
		) );
		rest_get_server()->dispatch( $link_req );

		// Query translations.
		$request  = new WP_REST_Request( 'GET', "/lingua/v1/translations/{$post_ko}" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'translations', $data );
		$this->assertArrayHasKey( 'ko', $data['translations'] );
		$this->assertArrayHasKey( 'en', $data['translations'] );
		$this->assertSame( $post_ko, $data['translations']['ko']['post_id'] );
		$this->assertSame( $post_en, $data['translations']['en']['post_id'] );
	}

	public function test_get_translations_nonexistent_post() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'GET', '/lingua/v1/translations/999999' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	// ------------------------------------------------------------------
	//  DELETE /lingua/v1/unlink/{post_id}
	// ------------------------------------------------------------------

	public function test_unlink_translation() {
		wp_set_current_user( self::$admin_id );

		$post_ko = self::factory()->post->create();
		$post_en = self::factory()->post->create();
		$post_ja = self::factory()->post->create();

		// Link three posts.
		$link_req = new WP_REST_Request( 'POST', '/lingua/v1/link' );
		$link_req->set_body_params( array(
			'post_ids' => array( 'ko' => $post_ko, 'en' => $post_en, 'ja' => $post_ja ),
		) );
		rest_get_server()->dispatch( $link_req );

		// Unlink the Japanese post.
		$request  = new WP_REST_Request( 'DELETE', "/lingua/v1/unlink/{$post_ja}" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $post_ja, $response->get_data()['unlinked'] );

		// The remaining two should still be linked.
		$trans_req  = new WP_REST_Request( 'GET', "/lingua/v1/translations/{$post_ko}" );
		$trans_resp = rest_get_server()->dispatch( $trans_req );
		$translations = $trans_resp->get_data()['translations'];

		$this->assertArrayHasKey( 'ko', $translations );
		$this->assertArrayHasKey( 'en', $translations );
		$this->assertArrayNotHasKey( 'ja', $translations );
	}

	public function test_unlink_requires_authentication() {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'DELETE', '/lingua/v1/unlink/1' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	public function test_unlink_nonexistent_post() {
		wp_set_current_user( self::$admin_id );

		$request  = new WP_REST_Request( 'DELETE', '/lingua/v1/unlink/999999' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	// ------------------------------------------------------------------
	//  Integration: link → get → unlink → get
	// ------------------------------------------------------------------

	public function test_full_lifecycle() {
		wp_set_current_user( self::$admin_id );

		// 1. Create posts.
		$post_ko = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$post_en = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		// 2. Link.
		$link_req = new WP_REST_Request( 'POST', '/lingua/v1/link' );
		$link_req->set_body_params( array(
			'post_ids' => array( 'ko' => $post_ko, 'en' => $post_en ),
		) );
		$link_resp = rest_get_server()->dispatch( $link_req );
		$this->assertSame( 200, $link_resp->get_status() );

		$group_term_id = $link_resp->get_data()['group_term_id'];
		$this->assertIsInt( $group_term_id );

		// 3. Verify translations from both sides.
		foreach ( array( $post_ko, $post_en ) as $pid ) {
			$req  = new WP_REST_Request( 'GET', "/lingua/v1/translations/{$pid}" );
			$resp = rest_get_server()->dispatch( $req );
			$this->assertSame( 200, $resp->get_status() );
			$this->assertCount( 2, $resp->get_data()['translations'] );
		}

		// 4. Unlink one.
		$req  = new WP_REST_Request( 'DELETE', "/lingua/v1/unlink/{$post_en}" );
		$resp = rest_get_server()->dispatch( $req );
		$this->assertSame( 200, $resp->get_status() );

		// 5. The other post's group now has only one member.
		$req  = new WP_REST_Request( 'GET', "/lingua/v1/translations/{$post_ko}" );
		$resp = rest_get_server()->dispatch( $req );
		$this->assertCount( 1, $resp->get_data()['translations'] );
	}
}
