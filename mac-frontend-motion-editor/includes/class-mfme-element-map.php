<?php
/**
 * Build the frontend element map from a document's _elementor_data,
 * and locate/mutate nodes inside the same tree for saving.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MFME_Element_Map {

	/**
	 * Flatten _elementor_data into element_id => info map for the frontend.
	 *
	 * @param array $elements Root elements array from get_elements_data().
	 * @return array{string: array}
	 */
	public static function build( array $elements ) {
		$map = [];
		self::walk( $elements, null, $map );
		return $map;
	}

	private static function walk( array $elements, $parent_id, array &$map ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) || empty( $element['id'] ) ) {
				continue;
			}

			$id       = (string) $element['id'];
			$settings = $element['settings'] ?? [];
			$animated = false;
			foreach ( MFME_Animations::read_motion( $element ) as $device_motion ) {
				if ( MFME_Animations::is_animation_set( $device_motion['animation'] ) ) {
					$animated = true;
					break;
				}
			}

			$map[ $id ] = [
				'elType'    => (string) ( $element['elType'] ?? '' ),
				'label'     => MFME_Animations::element_label( $element ),
				'parent'    => $parent_id ? (string) $parent_id : null,
				'editable'  => MFME_Animations::is_editable( $element ),
				'animated'  => $animated,
				'motion'    => MFME_Animations::read_motion( $element ),
			];

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::walk( $element['elements'], $id, $map );
			}
		}
	}

	/**
	 * Find a node by element id inside the tree (by reference).
	 *
	 * @param array  $elements    Root elements array.
	 * @param string $element_id  Target id.
	 * @return array|null Reference to node or null.
	 */
	public static function &find_node( array &$elements, $element_id ) {
		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['id'] ) && (string) $element['id'] === (string) $element_id ) {
				return $element;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = & self::find_node( $element['elements'], $element_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		$null = null;
		return $null;
	}

	/**
	 * Hash the elements tree for optimistic-concurrency checks.
	 */
	public static function hash( array $elements ) {
		return md5( wp_json_encode( $elements ) );
	}
}
