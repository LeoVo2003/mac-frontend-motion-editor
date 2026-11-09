<?php
/** Isolated backend contract checks; run with: php tests/backend.php. */
namespace Elementor {
	class Control_Animation {
		public static function get_animations() { return [ 'Fading' => [ 'fadeIn' => 'Fade In' ], 'Custom' => [ 'customEffect' => 'Custom effect' ] ]; }
	}
	class Plugin { public static $instance; }
}
namespace {
	define( 'ABSPATH', __DIR__ );
	class JsonResponse extends \Exception {
		public $payload;
		public function __construct( $success, $data, $status ) { parent::__construct( '', $status ); $this->payload = [ 'success' => $success, 'data' => $data ]; }
	}
	function wp_send_json_error( $data, $status ) { throw new JsonResponse( false, $data, $status ); }
	function wp_send_json_success( $data ) { throw new JsonResponse( true, $data, 200 ); }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function wp_unslash( $value ) { return stripslashes( $value ); }
	function absint( $value ) { return abs( (int) $value ); }
	function is_wp_error( $value ) { return false; }
	function get_current_user_id() { return 7; }
	function get_user_meta( $id, $key, $single ) { return $GLOBALS['enabled']; }
	function current_user_can( $capability, $id ) { return $GLOBALS['allowed']; }
	function get_post_type( $id ) { return $GLOBALS['post_type']; }
	function check_ajax_referer( $action, $field ) {
		if ( 'mfme_42' !== $action || 'valid' !== ( $_POST[ $field ] ?? '' ) ) { wp_send_json_error( [], 403 ); }
	}
	foreach ( [ 'animations', 'element-map', 'settings', 'ajax' ] as $file ) { require __DIR__ . '/../mac-frontend-motion-editor/includes/class-mfme-' . $file . '.php'; }
	function expect( $condition, $label ) { if ( ! $condition ) { throw new \RuntimeException( $label ); } echo "PASS $label\n"; }
	class Document {
		public $elements;
		public $saves = 0;
		public $fail = false;
		public function is_built_with_elementor() { return true; }
		public function get_elements_data() { return $this->elements; }
		public function save( $data ) { if ( $this->fail ) { return false; } ++$this->saves; $this->elements = $data['elements']; return true; }
	}
	class Documents {
		public $doc;
		public function get( $id ) { return 42 === $id ? $this->doc : null; }
	}
	$doc = new Document();
	$doc->elements = [ [ 'id' => 'parent', 'elType' => 'container', 'settings' => [ 'animation' => 'fadeIn' ], 'elements' => [
		[ 'id' => 'heading', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => [ 'title' => 'Keep me', '_animation' => 'fadeIn' ], 'elements' => [] ],
		[ 'id' => 'atomic', 'elType' => 'widget', 'widgetType' => 'e-heading', 'settings' => [], 'elements' => [] ],
	] ] ];
	$documents = new Documents(); $documents->doc = $doc;
	\Elementor\Plugin::$instance = (object) [ 'documents' => $documents ];
	$GLOBALS['enabled'] = true; $GLOBALS['allowed'] = true; $GLOBALS['post_type'] = 'page';
	$_SERVER['REQUEST_METHOD'] = 'POST';
	function request( array $overrides = [] ) {
		global $doc;
		$_POST = array_merge( [ 'doc_id' => '42', 'nonce' => 'valid', 'element_id' => 'heading', 'device' => 'tablet', 'animation' => 'customEffect', 'duration' => 'fast', 'delay' => '200', 'hash' => MFME_Element_Map::hash( $doc->elements ) ], $overrides );
		try { MFME_Ajax::apply(); } catch ( JsonResponse $response ) { return $response; }
		throw new \RuntimeException( 'No JSON response' );
	}
	$response = request();
	expect( 200 === $response->getCode(), 'custom catalogue animation accepted' );
	$node = &MFME_Element_Map::find_node( $doc->elements, 'heading' );
	expect( 'customEffect' === $node['settings']['_animation_tablet'] && 'fadeIn' === $node['settings']['_animation'], 'tablet override preserves desktop' );
	expect( 200 === $node['settings']['_animation_delay'] && ! isset( $node['settings']['_animation_delay_tablet'] ), 'delay uses shared native key' );
	expect( 'Keep me' === $node['settings']['title'], 'unrelated content preserved' );
	expect( 'fadeIn' === $response->payload['data']['previous']['desktop']['animation'], 'previous motion returned for Undo' );
	request( [ 'animation' => '', 'duration' => '', 'delay' => '0' ] );
	$node = &MFME_Element_Map::find_node( $doc->elements, 'heading' );
	expect( ! isset( $node['settings']['_animation_tablet'] ) && ! isset( $node['settings']['animation_duration'] ) && ! isset( $node['settings']['_animation_delay'] ), 'clear unsets default keys' );
	request( [ 'element_id' => 'parent', 'device' => 'mobile', 'animation' => 'none' ] );
	expect( 'none' === $doc->elements[0]['settings']['animation_mobile'], 'container schema and explicit none' );
	$before = $doc->elements; $saves = $doc->saves;
	foreach ( [ [ 'hash' => 'stale' ], [ 'element_id' => 'atomic' ], [ 'element_id' => 'shared' ], [ 'animation' => 'invented' ], [ 'delay' => '-1' ], [ 'delay' => '10001' ], [ 'delay' => '1.5' ], [ 'device' => 'watch' ], [ 'duration' => 'medium' ], [ 'animation' => [] ], [ 'nonce' => 'bad' ] ] as $bad ) {
		expect( request( $bad )->getCode() >= 400, 'reject ' . json_encode( $bad ) );
	}
	$GLOBALS['enabled'] = false; expect( 403 === request()->getCode(), 'disabled user denied' ); $GLOBALS['enabled'] = true;
	$GLOBALS['allowed'] = false; expect( 403 === request()->getCode(), 'permission denied' ); $GLOBALS['allowed'] = true;
	$GLOBALS['post_type'] = 'elementor_library'; expect( 400 === request()->getCode(), 'template document denied' ); $GLOBALS['post_type'] = 'page';
	$doc->fail = true; expect( 500 === request()->getCode(), 'save failure reported' );
	expect( $saves === $doc->saves && $before === $doc->elements, 'rejected requests never persist changes' );
	echo "Backend contract checks complete (Elementor/WordPress test doubles).\n";
}
