<?php
/**
 * Per-user edit-mode toggle + admin settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MFME_Settings {

	const USER_META_KEY = 'mfme_enabled';

	public static function is_enabled_for_user( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		return (bool) get_user_meta( $user_id, self::USER_META_KEY, true );
	}

	public static function set_enabled_for_user( $enabled, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		update_user_meta( $user_id, self::USER_META_KEY, $enabled ? 1 : 0 );
	}

	public static function register() {
		add_action( 'admin_menu', [ __CLASS__, 'add_admin_page' ] );
		add_action( 'admin_post_mfme_toggle', [ __CLASS__, 'handle_toggle' ] );
	}

	public static function add_admin_page() {
		add_management_page(
			__( 'MAC Frontend Motion', 'mac-motion-editor' ),
			__( 'MAC Frontend Motion', 'mac-motion-editor' ),
			'edit_posts',
			'mac-frontend-motion',
			[ __CLASS__, 'render_admin_page' ]
		);
	}

	public static function handle_toggle() {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Unauthorized', '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'mfme_toggle' );

		$input = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ? $_POST : $_GET;
		$enable = isset( $input['enable'] ) && '1' === $input['enable'];
		self::set_enabled_for_user( $enable );

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'tools.php?page=mac-frontend-motion' );
		}
		wp_safe_redirect( add_query_arg( 'mfme_toggled', $enable ? 'on' : 'off', $redirect ) );
		exit;
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mac-motion-editor' ) );
		}
		$enabled = self::is_enabled_for_user();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MAC Frontend Motion Editor', 'mac-motion-editor' ); ?></h1>
			<?php if ( isset( $_GET['mfme_toggled'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Setting saved.', 'mac-motion-editor' ); ?></p>
				</div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Edit Elementor entrance animations directly on the frontend. Hover any element while browsing the site (as admin), pick an animation, and Apply — the change is written to the page\'s Elementor data natively.', 'mac-motion-editor' ); ?></p>
			<p>
				<strong><?php esc_html_e( 'Status:', 'mac-motion-editor' ); ?></strong>
				<?php echo $enabled ? '<span style="color:green;font-weight:600">ON</span>' : '<span style="color:#999">OFF</span>'; ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'mfme_toggle' ); ?>
				<input type="hidden" name="action" value="mfme_toggle">
				<p><label><input type="checkbox" name="enable" value="1" <?php checked( $enabled ); ?>> <?php esc_html_e( 'Enable frontend motion editing for my account', 'mac-motion-editor' ); ?></label></p>
				<?php submit_button( __( 'Save setting', 'mac-motion-editor' ) ); ?>
			</form>
			<p class="description"><?php esc_html_e( 'This setting applies to your account only. Other admins and visitors are unaffected.', 'mac-motion-editor' ); ?></p>
			<hr>
			<h2><?php esc_html_e( 'How to use', 'mac-motion-editor' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Turn the editor ON above.', 'mac-motion-editor' ); ?></li>
				<li><?php esc_html_e( 'Visit any Elementor-built page on the frontend while logged in.', 'mac-motion-editor' ); ?></li>
				<li><?php esc_html_e( 'Hover an element → click the "Motion" badge → pick animation, speed, delay, device.', 'mac-motion-editor' ); ?></li>
				<li><?php esc_html_e( 'Replay previews without saving. Apply writes the change via Elementor\'s document save.', 'mac-motion-editor' ); ?></li>
			</ol>
		</div>
		<?php
	}
}
