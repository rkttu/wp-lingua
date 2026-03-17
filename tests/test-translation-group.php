<?php
/**
 * Tests for the Lingua_Translation_Group class.
 *
 * Verifies the core translation-group logic independently of REST.
 */

class Test_Translation_Group extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// Make sure the taxonomy is registered.
		do_action( 'init' );
	}

	public function test_get_or_create_group_creates_new_term() {
		$post_id = self::factory()->post->create();

		$term_id = Lingua_Translation_Group::get_or_create_group( $post_id );

		$this->assertIsInt( $term_id );
		$this->assertGreaterThan( 0, $term_id );

		// Calling again should return the same term.
		$term_id_again = Lingua_Translation_Group::get_or_create_group( $post_id );
		$this->assertSame( $term_id, $term_id_again );
	}

	public function test_add_to_group() {
		$post1 = self::factory()->post->create();
		$post2 = self::factory()->post->create();

		$group = Lingua_Translation_Group::get_or_create_group( $post1 );
		$result = Lingua_Translation_Group::add_to_group( $post2, $group );

		$this->assertTrue( $result );

		// Both posts should share the same term.
		$terms1 = wp_get_object_terms( $post1, 'Lingua_group', array( 'fields' => 'ids' ) );
		$terms2 = wp_get_object_terms( $post2, 'Lingua_group', array( 'fields' => 'ids' ) );

		$this->assertSame( $terms1, $terms2 );
	}

	public function test_remove_from_group() {
		$post1 = self::factory()->post->create();
		$post2 = self::factory()->post->create();

		$group = Lingua_Translation_Group::get_or_create_group( $post1 );
		Lingua_Translation_Group::add_to_group( $post2, $group );

		Lingua_Translation_Group::remove_from_group( $post2 );

		$terms = wp_get_object_terms( $post2, 'Lingua_group', array( 'fields' => 'ids' ) );
		$this->assertEmpty( $terms );
	}

	public function test_get_translations_returns_all_linked_posts() {
		$post_ko = self::factory()->post->create();
		$post_en = self::factory()->post->create();
		$post_ja = self::factory()->post->create();

		Lingua_Post_Meta::set_language( $post_ko, 'ko' );
		Lingua_Post_Meta::set_language( $post_en, 'en' );
		Lingua_Post_Meta::set_language( $post_ja, 'ja' );

		$group = Lingua_Translation_Group::get_or_create_group( $post_ko );
		Lingua_Translation_Group::add_to_group( $post_en, $group );
		Lingua_Translation_Group::add_to_group( $post_ja, $group );

		$translations = Lingua_Translation_Group::get_translations( $post_ko );

		$this->assertCount( 3, $translations );
		$this->assertArrayHasKey( 'ko', $translations );
		$this->assertArrayHasKey( 'en', $translations );
		$this->assertArrayHasKey( 'ja', $translations );
	}

	public function test_has_translation_returns_correct_boolean() {
		$post_ko = self::factory()->post->create();
		$post_en = self::factory()->post->create();

		Lingua_Post_Meta::set_language( $post_ko, 'ko' );
		Lingua_Post_Meta::set_language( $post_en, 'en' );

		$group = Lingua_Translation_Group::get_or_create_group( $post_ko );
		Lingua_Translation_Group::add_to_group( $post_en, $group );

		$this->assertTrue( Lingua_Translation_Group::has_translation( $post_ko, 'en' ) );
		$this->assertFalse( Lingua_Translation_Group::has_translation( $post_ko, 'ja' ) );
	}

	public function test_create_translation_makes_draft_in_group() {
		$post_ko = self::factory()->post->create( array( 'post_title' => '원본 포스트' ) );
		Lingua_Post_Meta::set_language( $post_ko, 'ko' );

		$new_id = Lingua_Translation_Group::create_translation( $post_ko, 'en' );

		$this->assertIsInt( $new_id );

		$new_post = get_post( $new_id );
		$this->assertSame( 'draft', $new_post->post_status );
		$this->assertSame( 'en', Lingua_Post_Meta::get_language( $new_id ) );

		// Both should be in the same group.
		$translations = Lingua_Translation_Group::get_translations( $post_ko );
		$this->assertArrayHasKey( 'ko', $translations );
		$this->assertArrayHasKey( 'en', $translations );
	}

	public function test_create_translation_rejects_duplicate_language() {
		$post_ko = self::factory()->post->create();
		Lingua_Post_Meta::set_language( $post_ko, 'ko' );

		// First EN translation.
		Lingua_Translation_Group::create_translation( $post_ko, 'en' );

		// Second EN should fail.
		$result = Lingua_Translation_Group::create_translation( $post_ko, 'en' );
		$this->assertWPError( $result );
		$this->assertSame( 'duplicate_language', $result->get_error_code() );
	}
}
