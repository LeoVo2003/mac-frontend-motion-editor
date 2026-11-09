<?php
/**
 * Elementor data layer: element map, schema adapters, atomic detection.
 *
 * @package MAC_Frontend_Motion_Editor
 */

namespace MAC_FME;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Data {

	const HASH_ALGO = 'md5';

	/**
	 * elType values that use the legacy `animation` schema.
	 */
	const CONTAINER_TYPES = [ 'section', 'column', 'container' ];

	/**
	 * Native animation list from Elementor (includes plugin-added via filter).
	 *
	 * @return array group => [ name => label ]
	 */
	public static function animations() {
		if ( class_exists( '\Elementor\Control_Animation' ) ) {
			return \Elementor\Control_Animation::get_animations();
		}
		return [];
	}

	/**
	 * Flat list of valid animation names (for validation).
	 */
	public static function animation_names() {
		$names = [];
		foreach ( self::animations() as $group ) {
			if ( is_array( $group ) ) {
				$names = array_merge( $names, array_keys( $group ) );
			}
		}
		return array_unique( $names );
	}

	/**
	 * Registered conditional CSS handle for an animation (e-animation-{name}).
	 */
	public static function animation_style_handles() {
		return array_map( function( $name ) {
			return 'e-animation-' . $name;
		}, self::animation_names() );
	}

	/**
	 * Get the Elementor document for a post, or null.
	 *
	 * @param int $post_id
	 * @return \Elementor\Core\Base\Document|null
	 */
	public static function document( $post_id ) {
		if ( ! \Elementor\Plugin::instance() ) {
			return null;
		}
		$document = \Elementor\Plugin::instance()->documents->get( $post_id );
		return $document ?: null;
	}

	/**
	 * Load elements data for a post.
	 *
	 * @return array
	 */
	public static function elements( $post_id ) {
		$document = self::document( $post_id );
		if ( ! $document ) {
			return [];
		}
		$data = $document->get_elements_data();
		return is_array( $data ) ? $data : [];
	}

	/**
	 * Hash of the elements JSON — conflict guard.
	 */
	public static function hash( array $elements ) {
		return md5( wp_json_encode( $elements ) );
	}

	/**
	 * Is this element an Atomic / V4 element (widgetType e-* or unknown elType)?
	 */
	public static function is_atomic( array $element ) {
		$widget_type = isset( $element['widgetType'] ) ? (string) $element['widgetType'] : '';
		if ( 0 === strpos( $widget_type, 'e-' ) ) {
			return true;
		}
		$el_type = isset( $element['elType'] ) ? (string) $element['elType'] : '';
		return ! in_array( $el_type, array_merge( [ 'widget' ], self::CONTAINER_TYPES ), true );
	}

	/**
	 * Does this element type support motion editing?
	 */
	public static function is_supported( array $element ) {
		if ( self::is_atomic( $element ) ) {
			return false;
		}
		$el_type = isset( $element['elType'] ) ? (string) $element['elType'] : '';
		return in_array( $el_type, array_merge( [ 'widget' ], self::CONTAINER_TYPES ), true );
	}

	/**
	 * Setting keys for an elType.
	 *
	 * widget  -> _animation / _animation_delay (+_tablet/_mobile) / animation_duration
	 * others  -> animation  / animation_delay  (+_tablet/_mobile) / animation_duration
	 *
	 * @param string $el_type
	 * @return array { animation, delay, duration } — base keys; add _{device} suffix for responsive.
	 */
	public static function schema_keys( $el_type ) {
		if ( 'widget' === $el_type ) {
			return [
				'animation' => '_animation',
				'delay'     => '_animation_delay',
				'duration'  => 'animation_duration',
			];
		}
		return [
			'animation' => 'animation',
			'delay'     => 'animation_delay',
			'duration'  => 'animation_duration',
		];
	}

	/**
	 * Setting key for a device ('' for desktop).
	 */
	public static function device_key( $base_key, $device ) {
		return '' === $device ? $base_key : $base_key . '_' . $device;
	}

	/**
	 * Read animation settings of one element into normalized shape.
	 *
	 * @return array { animation: {desktop,tablet,mobile}, delay:{...}, duration }
	 */
	public static function read_motion( array $element ) {
		$el_type = isset( $element['elType'] ) ? $element['elType'] : 'widget';
		$keys    = self::schema_keys( $el_type );
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : [];

		$motion = [
			'animation' => [ 'desktop' => '', 'tablet' => '', 'mobile' => '' ],
			'delay'     => [ 'desktop' => 0, 'tablet' => 0, 'mobile' => 0 ],
			'duration'  => isset( $settings[ $keys['duration'] ] ) ? $settings[ $keys['duration'] ] : '',
		];

		foreach ( [ 'desktop', 'tablet', 'mobile' ] as $device ) {
			$suffix = 'desktop' === $device ? '' : '_' . $device;
			$a_key  = $keys['animation'] . $suffix;
			$d_key  = $keys['delay'] . $suffix;
			if ( isset( $settings[ $a_key ] ) ) {
				$motion['animation'][ $device ] = $settings[ $a_key ];
			}
			if ( isset( $settings[ $d_key ] ) ) {
				$motion['delay'][ $device ] = (int) $settings[ $d_key ];
			}
		}

		return $motion;
	}

	/**
	 * Build the flat element map for the JS layer, with ancestors for nesting warnings.
	 *
	 * @param int $post_id
	 * @return array { post_id, hash, elements: { id => entry } }
	 */
	public static function build_map( $post_id ) {
		$elements = self::elements( $post_id );
		$map      = [
			'post_id'  => (int) $post_id,
			'hash'     => self::hash( $elements ),
			'elements' => [],
		];

		self::walk( $elements, [], function( $element, $ancestors ) use ( &$map ) {
			if ( empty( $element['id'] ) ) {
				return;
			}
			$id      = (string) $element['id'];
			$el_type = isset( $element['elType'] ) ? $element['elType'] : '';
			$map['elements'][ $id ] = [
				'elType'    => $el_type,
				'widgetType'=> isset( $element['widgetType'] ) ? $element['widgetType'] : '',
				'supported' => self::is_supported( $element ),
				'atomic'    => self::is_atomic( $element ),
				'ancestors' => $ancestors,
				'motion'    => self::read_motion( $element ),
			];
		} );

		return $map;
	}

	/**
	 * Walk elements recursively; callback gets (element, ancestor_ids[]).
	 */
	public static function walk( array $elements, array $ancestors, callable $callback ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$callback( $element, $ancestors );
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$id       = isset( $element['id'] ) ? (string) $element['id'] : '';
				$child_as = $id ? array_merge( $ancestors, [ $id ] ) : $ancestors;
				self::walk( $element['elements'], $child_as, $callback );
			}
		}
	}

	/**
	 * Find an element (by id) inside elements tree and return it by reference,
	 * together with its parent array index path for mutation.
	 *
	 * @param array  $elements elements tree (by ref).
	 * @param string $id
	 * @return array|null [ &element, path[] ]
	 */
	public static function &find( array &$elements, $id ) {
		$found = null;
		$path  = [];

		$walker = function( array &$tree, array $trail ) use ( &$walker, &$found, &$path, $id ) {
			foreach ( $tree as $i => &$element ) {
				if ( ! is_array( $element ) ) {
					continue;
				}
				$current = array_merge( $trail, [ $i ] );
				if ( isset( $element['id'] ) && (string) $element['id'] === (string) $id ) {
					$found = &$element;
					$path  = $current;
					return;
				}
				if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
					$walker( $element['elements'], $current );
					if ( $found ) {
						return;
					}
				}
			}
		};

		$walker( $elements, [] );

		return $found;
	}

	/**
	 * Apply one motion change to an element's settings.
	 *
	 * @param array  $element element node (by ref via find()).
	 * @param string $device  '', 'tablet' or 'mobile' ('' = desktop).
	 * @param array  $values  { animation?, duration?, delay? } — missing keys untouched;
	 *                        explicit null/'clear' marker handled by caller.
	 * @return array applied { key => value }
	 */
	public static function apply_motion( array &$element, $device, array $values ) {
		$el_type = isset( $element['elType'] ) ? $element['elType'] : 'widget';
		$keys    = self::schema_keys( $el_type );

		if ( ! isset( $element['settings'] ) || ! is_array( $element['settings'] ) ) {
			$element['settings'] = [];
		}

		$applied = [];

		if ( array_key_exists( 'animation', $values ) ) {
			$key = self::device_key( $keys['animation'], $device );
			if ( null === $values['animation'] ) {
				unset( $element['settings'][ $key ] );
				$applied[ $key ] = null;
			} else {
				$element['settings'][ $key ] = $values['animation'];
				$applied[ $key ] = $values['animation'];
			}
		}

		if ( array_key_exists( 'duration', $values ) ) {
			// animation_duration is not responsive.
			$key = $keys['duration'];
			if ( null === $values['duration'] || '' === $values['duration'] ) {
				unset( $element['settings'][ $key ] );
				$applied[ $key ] = null;
			} else {
				$element['settings'][ $key ] = $values['duration'];
				$applied[ $key ] = $values['duration'];
			}
		}

		if ( array_key_exists( 'delay', $values ) ) {
			$key = self::device_key( $keys['delay'], $device );
			$delay = (int) $values['delay'];
			if ( null === $values['delay'] || $delay <= 0 ) {
				unset( $element['settings'][ $key ] );
				$applied[ $key ] = null;
			} else {
				$element['settings'][ $key ] = $delay;
				$applied[ $key ] = $delay;
			}
		}

		return $applied;
	}

	/**
	 * Post id of the Elementor document rendered inside a page.
	 * The frontend JS resolves the same id from `.elementor-{id}`.
	 */
	public static function current_document_id() {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return 0;
		}
		$document = self::document( $post_id );
		if ( ! $document || ! $document->is_built_with_elementor() ) {
			return 0;
		}
		return (int) $post_id;
	}
}
