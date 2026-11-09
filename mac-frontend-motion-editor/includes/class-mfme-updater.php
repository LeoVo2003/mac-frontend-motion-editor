<?php
/** Public GitHub release updates, integrated with WordPress' native updater. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class MFME_Updater {
	const REPO_URL = 'https://github.com/LeoVo2003/mac-frontend-motion-editor';
	const MANIFEST_URL = self::REPO_URL . '/releases/latest/download/update.json';
	const CACHE_KEY = 'mfme_github_release';
	const SLUG = 'mac-frontend-motion-editor';

	public static function register() {
		add_filter( 'update_plugins_github.com', [ __CLASS__, 'update' ], 10, 4 );
		add_filter( 'plugins_api', [ __CLASS__, 'information' ], 10, 3 );
		add_filter( 'plugin_action_links_' . plugin_basename( MFME_FILE ), [ __CLASS__, 'links' ] );
		add_action( 'admin_post_mfme_check_updates', [ __CLASS__, 'check' ] );
		add_action( 'admin_notices', [ __CLASS__, 'notice' ] );
	}

	/** Cache only validated, public metadata; no GitHub credentials on the site. */
	public static function release() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) { return is_array( $cached ) ? $cached : false; }
		$response = wp_safe_remote_get( self::MANIFEST_URL, [ 'timeout' => 15, 'limit_response_size' => 32768, 'headers' => [ 'Accept' => 'application/json' ] ] );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::CACHE_KEY, 'unavailable', 120 );
			return false;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! isset( $data['version'], $data['package'] ) || ! is_string( $data['version'] ) || ! preg_match( '/^\d+\.\d+\.\d+(?:\.\d+)?$/D', $data['version'] ) || self::REPO_URL . '/releases/download/v' . $data['version'] . '/mac-frontend-motion-editor.zip' !== $data['package'] ) {
			set_transient( self::CACHE_KEY, 'unavailable', 120 );
			return false;
		}
		$release = [ 'id' => self::REPO_URL, 'slug' => self::SLUG, 'version' => $data['version'], 'url' => self::REPO_URL, 'package' => $data['package'], 'requires' => '5.8', 'requires_php' => '7.4' ];
		set_transient( self::CACHE_KEY, $release, 6 * HOUR_IN_SECONDS );
		return $release;
	}

	public static function update( $update, $plugin_data, $plugin_file, $locales ) {
		if ( plugin_basename( MFME_FILE ) !== $plugin_file ) { return $update; }
		$release = self::release();
		return $release ? $release : $update;
	}

	public static function information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || self::SLUG !== $args->slug ) { return $result; }
		$release = self::release();
		if ( ! $release ) { return $result; }
		return (object) [
			'name' => 'MAC Frontend Motion Editor', 'slug' => self::SLUG, 'version' => $release['version'],
			'author' => 'MAC', 'homepage' => self::REPO_URL, 'download_link' => $release['package'],
			'requires' => '5.8', 'requires_php' => '7.4',
			'sections' => [ 'description' => 'Edit native Elementor entrance animations directly on the frontend.', 'changelog' => '<p>Updates are built from the main branch after automated checks pass. <a href="' . esc_url( self::REPO_URL . '/releases' ) . '">View releases</a>.</p>' ],
		];
	}

	public static function links( $links ) {
		if ( current_user_can( 'update_plugins' ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=mfme_check_updates' ), 'mfme_check_updates' );
			$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check updates', 'mac-motion-editor' ) . '</a>';
		}
		return $links;
	}

	public static function check() {
		if ( ! current_user_can( 'update_plugins' ) ) { wp_die( 'Unauthorized', '', [ 'response' => 403 ] ); }
		check_admin_referer( 'mfme_check_updates' );
		delete_transient( self::CACHE_KEY );
		$release = self::release();
		wp_clean_plugins_cache( true );
		wp_update_plugins();
		$status = ! $release ? 'unavailable' : ( version_compare( $release['version'], MFME_VERSION, '>' ) ? 'available' : 'current' );
		wp_safe_redirect( add_query_arg( 'mfme_update', $status, self_admin_url( 'plugins.php' ) ) );
		exit;
	}

	public static function notice() {
		if ( ! current_user_can( 'update_plugins' ) || ! isset( $_GET['mfme_update'] ) || ! is_string( $_GET['mfme_update'] ) ) { return; }
		$messages = [
			'available' => 'MAC Motion: a new version is available. Click Update now in the plugin row.',
			'current' => 'MAC Motion: you have the latest version.',
			'unavailable' => 'MAC Motion: update check failed or the first release is not ready. Try again shortly.',
		];
		$status = $_GET['mfme_update'];
		if ( ! isset( $messages[ $status ] ) ) { return; }
		echo '<div class="notice ' . ( 'unavailable' === $status ? 'notice-warning' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $messages[ $status ] ) . '</p></div>';
	}
}
