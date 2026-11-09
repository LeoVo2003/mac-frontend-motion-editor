<?php
/** Frontend context, assets and admin bar. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MFME_Plugin {
	public static function init() {
		MFME_Settings::register();
		MFME_Ajax::register();
		add_action( 'admin_notices', [ __CLASS__, 'dependency_notice' ] );
		add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar' ], 100 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 100 );
	}

	public static function dependency_notice() {
		if ( ! class_exists( '\Elementor\Plugin' ) && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'MAC Frontend Motion Editor requires Elementor to be active.', 'mac-motion-editor' ) . '</p></div>';
		}
	}

	/** Context is deliberately limited to a singular main document. */
	public static function document() {
		if ( is_admin() || ! is_singular() || ! is_user_logged_in() || isset( $_GET['elementor-preview'] ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return null;
		}
		$id = get_queried_object_id();
		if ( ! current_user_can( 'edit_post', $id ) || 'elementor_library' === get_post_type( $id ) ) { return null; }
		$elementor = \Elementor\Plugin::$instance;
		if ( ! $elementor || ( $elementor->preview && $elementor->preview->is_preview_mode() ) || ( $elementor->editor && $elementor->editor->is_edit_mode() ) ) { return null; }
		$document = $elementor->documents->get( $id );
		return $document && $document->is_built_with_elementor() ? $document : null;
	}

	public static function toggle_url( $enabled ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=mfme_toggle&enable=' . ( $enabled ? '1' : '0' ) ), 'mfme_toggle' );
	}

	public static function admin_bar( $bar ) {
		if ( ! self::document() ) { return; }
		$enabled = MFME_Settings::is_enabled_for_user();
		$bar->add_node( [ 'id' => 'mfme-toggle', 'title' => '✦ Motion Edit: ' . ( $enabled ? 'ON' : 'OFF' ), 'href' => self::toggle_url( ! $enabled ) ] );
	}

	public static function enqueue() {
		if ( ! MFME_Settings::is_enabled_for_user() ) { return; }
		$document = self::document();
		if ( ! $document ) { return; }
		// Prevent a personalized editor response from entering a page cache.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		if ( ! headers_sent() ) { nocache_headers(); }
		$styles = wp_styles();
		foreach ( array_keys( $styles->registered ) as $handle ) {
			if ( 0 === strpos( $handle, 'e-animation-' ) || 'elementor-animations' === $handle ) { wp_enqueue_style( $handle ); }
		}
		wp_enqueue_style( 'mfme-editor', MFME_URL . 'assets/css/motion-editor.css', [], MFME_VERSION );
		wp_enqueue_script( 'mfme-editor', MFME_URL . 'assets/js/motion-editor.js', [], MFME_VERSION, true );
		$id = get_queried_object_id();
		$elements = $document->get_elements_data();
		$data = [
			'docId' => $id, 'nonce' => wp_create_nonce( 'mfme_' . $id ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'offUrl' => self::toggle_url( false ),
			'hash' => MFME_Element_Map::hash( $elements ), 'elements' => MFME_Element_Map::build( $elements ),
			'animations' => MFME_Animations::get_grouped_animations(),
		];
		wp_add_inline_script( 'mfme-editor', 'window.MFME_DATA = ' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
	}
}
