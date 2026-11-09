<?php
/**
 * Animation catalogue + per-element-type key adapters.
 *
 * The single source of truth for which Elementor settings are written where.
 * Frontend JS never names a setting — it sends semantic values and PHP maps
 * them onto the correct keys per element type.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MFME_Animations {

	const DURATION_NORMAL = '';
	const DURATION_SLOW   = 'slow';
	const DURATION_FAST   = 'fast';

	const DELAY_MIN = 0;
	const DELAY_MAX = 10000;

	// Element types the editor can write.
	const EDITABLE_EL_TYPES = [ 'widget', 'section', 'column', 'container' ];

	/**
	 * Flat list of valid animation slugs (entrance animations only).
	 * Delegates to Elementor so third-party animations registered via the
	 * 'elementor/controls/animations/additional_animations' filter are included.
	 *
	 * @return array{string: string} slug => label
	 */
	public static function get_animation_options() {
		$options = [];

		if ( class_exists( 'Elementor\\Control_Animation' ) ) {
			foreach ( (array) \Elementor\Control_Animation::get_animations() as $group => $animations ) {
				foreach ( (array) $animations as $slug => $label ) {
					$options[ (string) $slug ] = (string) $label;
				}
			}
		}

		return $options;
	}

	/**
	 * Grouped list for optgroup rendering: group => [ slug => label ].
	 */
	public static function get_grouped_animations() {
		if ( class_exists( 'Elementor\\Control_Animation' ) ) {
			$grouped = (array) \Elementor\Control_Animation::get_animations();
			return array_map(
				function ( $items ) {
					return array_map( 'strval', (array) $items );
				},
				$grouped
			);
		}
		return [];
	}

	public static function is_valid_animation( $animation ) {
		if ( '' === $animation || 'none' === $animation ) {
			return true;
		}
		$valid = self::get_animation_options();
		return isset( $valid[ $animation ] );
	}

	public static function is_valid_duration( $duration ) {
		return in_array( $duration, [ self::DURATION_NORMAL, self::DURATION_SLOW, self::DURATION_FAST ], true );
	}

	public static function is_valid_delay( $delay ) {
		return is_int( $delay ) && $delay >= self::DELAY_MIN && $delay <= self::DELAY_MAX;
	}

	/**
	 * Whether a settings value carries an animation worth counting/showing.
	 */
	public static function is_animation_set( $value ) {
		return is_string( $value ) && '' !== $value && 'none' !== $value;
	}

	/**
	 * True when an element may be edited by this tool.
	 * Atomic/V4 elements (widgetType e-*) are locked — they use Interactions,
	 * not the legacy entrance-animation schema.
	 *
	 * @param array $node Element node from _elementor_data.
	 */
	public static function is_editable( array $node ) {
		$el_type = $node['elType'] ?? '';

		if ( ! in_array( $el_type, self::EDITABLE_EL_TYPES, true ) ) {
			return false;
		}

		if ( 'widget' === $el_type ) {
			$widget_type = (string) ( $node['widgetType'] ?? '' );
			if ( 'global' === $widget_type || 0 === strpos( $widget_type, 'e-' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Human label for the element in the picker header.
	 */
	public static function element_label( array $node ) {
		$el_type = $node['elType'] ?? '';

		if ( 'widget' === $el_type ) {
			$widget_type = (string) ( $node['widgetType'] ?? 'widget' );
			return 'Widget: ' . $widget_type;
		}

		return ucfirst( $el_type );
	}

	/**
	 * Setting keys for a given node, resolved per schema.
	 *
	 * @param array $node Element node.
	 * @return array{animation: string, delay: string}
	 */
	public static function key_map( array $node ) {
		$el_type = $node['elType'] ?? '';

		if ( 'widget' === $el_type ) {
			return [
				'animation' => '_animation',
				'delay'     => '_animation_delay',
			];
		}

		// section / column / container share the container-style schema.
		return [
			'animation' => 'animation',
			'delay'     => 'animation_delay',
		];
	}

	/**
	 * Device-suffixed animation key. desktop = base key.
	 * Widget: _animation_tablet / _animation_mobile. Container: animation_tablet.
	 *
	 * @param array  $node   Element node (for schema selection).
	 * @param string $device desktop|tablet|mobile
	 */
	public static function animation_key( array $node, $device ) {
		$keys = self::key_map( $node );
		return 'desktop' === $device
			? $keys['animation']
			: $keys['animation'] . '_' . $device;
	}

	/**
	 * Read current animation settings for a node across all devices.
	 *
	 * @return array{desktop: array, tablet: array, mobile: array}
	 */
	public static function read_motion( array $node ) {
		$settings = $node['settings'] ?? [];
		$keys     = self::key_map( $node );
		$motion   = [];

		foreach ( [ 'desktop', 'tablet', 'mobile' ] as $device ) {
			$anim_key = self::animation_key( $node, $device );
			$motion[ $device ] = [
				'animation' => $settings[ $anim_key ] ?? '',
				'duration'  => $settings['animation_duration'] ?? '',
				'delay'     => isset( $settings[ $keys['delay'] ] ) ? (int) $settings[ $keys['delay'] ] : 0,
			];
		}

		return $motion;
	}

	/**
	 * Apply a motion change onto an element node's settings (by reference).
	 * Only animation-related keys are touched.
	 *
	 * @param array  $node       Element node (settings mutated in place).
	 * @param string $device     desktop|tablet|mobile
	 * @param string $animation  ''|'none'|valid slug
	 * @param string $duration   ''|'slow'|'fast'
	 * @param int    $delay      ms
	 */
	public static function write_motion( array &$node, $device, $animation, $duration, $delay ) {
		if ( ! isset( $node['settings'] ) || ! is_array( $node['settings'] ) ) {
			$node['settings'] = [];
		}

		$keys     = self::key_map( $node );
		$anim_key = self::animation_key( $node, $device );

		foreach ( [ $anim_key => $animation, 'animation_duration' => $duration, $keys['delay'] => $delay ] as $key => $value ) {
			if ( '' === $value || 0 === $value ) {
				unset( $node['settings'][ $key ] );
			} else {
				$node['settings'][ $key ] = $value;
			}
		}
	}
}
