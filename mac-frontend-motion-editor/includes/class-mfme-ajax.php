<?php
/** Only semantic motion values may cross the save boundary. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MFME_Ajax {
	public static function register() {
		add_action( 'wp_ajax_mfme_apply', [ __CLASS__, 'apply' ] );
	}

	private static function error( $message, $status ) {
		wp_send_json_error( [ 'message' => $message ], $status );
	}

	public static function apply() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { self::error( 'POST required.', 405 ); }
		$id = isset( $_POST['doc_id'] ) && is_scalar( $_POST['doc_id'] ) ? absint( $_POST['doc_id'] ) : 0;
		check_ajax_referer( 'mfme_' . $id, 'nonce' );
		if ( ! $id || ! MFME_Settings::is_enabled_for_user() || ! current_user_can( 'edit_post', $id ) ) { self::error( 'Motion editing is disabled or access was denied.', 403 ); }
		if ( ! class_exists( '\Elementor\Plugin' ) || ! \Elementor\Plugin::$instance ) { self::error( 'Elementor is unavailable.', 503 ); }
		$document = \Elementor\Plugin::$instance->documents->get( $id );
		if ( ! $document || ! $document->is_built_with_elementor() || 'elementor_library' === get_post_type( $id ) ) { self::error( 'Unsupported document.', 400 ); }
		$values = [];
		foreach ( [ 'element_id', 'device', 'animation', 'duration', 'delay', 'hash' ] as $key ) {
			if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) { self::error( 'Missing or invalid field: ' . $key, 400 ); }
			$values[ $key ] = wp_unslash( $_POST[ $key ] );
		}
		if ( ! in_array( $values['device'], [ 'desktop', 'tablet', 'mobile' ], true ) || ! MFME_Animations::is_valid_animation( $values['animation'] ) || ! MFME_Animations::is_valid_duration( $values['duration'] ) || ! preg_match( '/^\d{1,5}$/D', $values['delay'] ) || ! MFME_Animations::is_valid_delay( (int) $values['delay'] ) ) {
			self::error( 'Invalid animation, speed, device or delay (0–10000 ms).', 400 );
		}
		$elements = $document->get_elements_data();
		if ( ! hash_equals( MFME_Element_Map::hash( $elements ), $values['hash'] ) ) { self::error( 'Page changed, reload before saving.', 409 ); }
		$node = &MFME_Element_Map::find_node( $elements, $values['element_id'] );
		if ( null === $node || ! MFME_Animations::is_editable( $node ) ) { self::error( 'This element is locked or belongs to another document.', 403 ); }
		$previous = MFME_Animations::read_motion( $node );
		MFME_Animations::write_motion( $node, $values['device'], $values['animation'], $values['duration'], (int) $values['delay'] );
		try {
			$saved = $document->save( [ 'elements' => $elements ] );
		} catch ( \Throwable $e ) {
			self::error( 'Elementor could not save the page. Reload before retrying.', 500 );
		}
		if ( ! $saved || is_wp_error( $saved ) ) { self::error( 'Elementor could not save the page.', 500 ); }
		// Re-read the persisted tree, since Elementor filters may normalize it.
		$fresh = $document->get_elements_data();
		$fresh_node = &MFME_Element_Map::find_node( $fresh, $values['element_id'] );
		if ( null === $fresh_node ) { self::error( 'Page saved but element changed. Reload the page.', 409 ); }
		wp_send_json_success( [ 'hash' => MFME_Element_Map::hash( $fresh ), 'anim' => MFME_Animations::read_motion( $fresh_node ), 'previous' => $previous ] );
	}
}
