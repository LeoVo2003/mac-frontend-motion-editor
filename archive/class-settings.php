<?php
/**
 * Settings page (Tools -> MAC Frontend Motion) + per-user toggle storage.
 *
 * @package MAC_Frontend_Motion_Editor
 */

namespace MAC_FME;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_macfme_toggle', [ $this, 'handle_form' ] );
	}

	public function menu() {
		add_submenu_page(
			'tools.php',
			__( 'MAC Frontend Motion', 'macfme' ),
			__( 'MAC Frontend Motion', 'macfme' ),
			'edit_posts',
			'macfme',
			[ $this, 'page' ]
		);
	}

	public function handle_form() {
		check_admin_referer( 'macfme_settings' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Khong du quyen.', 'macfme' ), 403 );
		}

		$enabled = ! empty( $_POST['macfme_enabled'] ) ? '1' : '';
		update_user_meta( get_current_user_id(), MAC_FME_USER_META, $enabled );

		wp_safe_redirect( add_query_arg( 'macfme_saved', '1', admin_url( 'tools.php?page=macfme' ) ) );
		exit;
	}

	public function page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Khong du quyen.', 'macfme' ), 403 );
		}

		$enabled = self::is_enabled( get_current_user_id() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MAC Frontend Motion Editor', 'macfme' ); ?></h1>

			<?php if ( ! empty( $_GET['macfme_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Da luu.', 'macfme' ); ?></p></div>
			<?php endif; ?>

			<p>
				<?php esc_html_e( 'Bat cong tac nay, roi mo trang Elementor ngoai frontend: hover vao element se hien nut "Motion", chon animation / speed / delay / device va Apply. Du lieu luu native vao _elementor_data qua Document::save().', 'macfme' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Cong tac nay chi ap dung cho tai khoan cua ban (per-user). Khach va admin khac khong thay editor.', 'macfme' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'macfme_settings' ); ?>
				<input type="hidden" name="action" value="macfme_toggle" />
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Frontend Animation Edit', 'macfme' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="macfme_enabled" value="1" <?php checked( $enabled ); ?> />
								<strong><?php echo $enabled ? 'ON' : 'OFF'; ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( 'Khi ON, admin bar ngoai frontend se co "Motion Edit: ON" va trang se load toan bo CSS entrance animation cua Elementor de preview.', 'macfme' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Luu', 'macfme' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Is edit mode on for a user?
	 */
	public static function is_enabled( $user_id = 0 ) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		return '1' === get_user_meta( $user_id, MAC_FME_USER_META, true );
	}
}
