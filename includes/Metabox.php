<?php
/**
 * Metabox de vídeo no editor de post.
 *
 * @package NexoPlayer
 */

namespace NexoPlayer;

defined( 'ABSPATH' ) || exit;

class Metabox {

	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function add(): void {
		foreach ( Options::post_types() as $pt ) {
			add_meta_box( 'nexop_video', __( 'Nexo Player — Vídeo', 'nexo-player' ), array( __CLASS__, 'render' ), $pt, 'normal', 'high' );
		}
	}

	public static function assets( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'nexop-admin', NEXOP_URL . 'assets/admin.css', array(), NEXOP_VERSION );
		wp_enqueue_script( 'nexop-admin', NEXOP_URL . 'assets/admin.js', array( 'jquery' ), NEXOP_VERSION, true );
	}

	public static function render( \WP_Post $post ): void {
		wp_nonce_field( 'nexop_save', 'nexop_nonce' );
		$mp4    = get_post_meta( $post->ID, '_nexop_mp4', true );
		$embed  = get_post_meta( $post->ID, '_nexop_embed', true );
		$poster = get_post_meta( $post->ID, '_nexop_poster', true );
		$tempo  = get_post_meta( $post->ID, '_nexop_tempo', true );
		$rel    = get_post_meta( $post->ID, '_nexop_relacionados', true );
		$rel    = ( '' === $rel ) ? '1' : $rel;
		?>
		<div class="nexop-mb">
			<p class="nexop-mb__campo">
				<label><strong><?php esc_html_e( 'Link do vídeo (MP4)', 'nexo-player' ); ?></strong></label>
				<span class="nexop-mb__upload">
					<input type="url" name="nexop_mp4" id="nexop_mp4" value="<?php echo esc_attr( $mp4 ); ?>" placeholder="https://…/video.mp4" class="widefat">
					<button type="button" class="button nexop-mb__btn" data-nexop-upload="nexop_mp4" data-tipo="video"><?php esc_html_e( 'Selecionar', 'nexo-player' ); ?></button>
				</span>
			</p>

			<p class="nexop-mb__campo">
				<label><strong><?php esc_html_e( 'Código de embed (opcional)', 'nexo-player' ); ?></strong></label>
				<textarea name="nexop_embed" rows="3" class="widefat" placeholder="<?php esc_attr_e( 'Cole o <iframe> de embed do tube (Xvideos, Pornhub, etc.)', 'nexo-player' ); ?>"><?php echo esc_textarea( $embed ); ?></textarea>
				<span class="description"><?php esc_html_e( 'Se preencher os dois, o MP4 tem prioridade.', 'nexo-player' ); ?></span>
			</p>

			<p class="nexop-mb__campo">
				<label><strong><?php esc_html_e( 'Capa (poster)', 'nexo-player' ); ?></strong></label>
				<span class="nexop-mb__upload">
					<input type="url" name="nexop_poster" id="nexop_poster" value="<?php echo esc_attr( $poster ); ?>" placeholder="https://…/capa.jpg" class="widefat">
					<button type="button" class="button nexop-mb__btn" data-nexop-upload="nexop_poster" data-tipo="image"><?php esc_html_e( 'Selecionar', 'nexo-player' ); ?></button>
				</span>
				<span class="description"><?php esc_html_e( 'Vazio = usa a imagem destacada do post.', 'nexo-player' ); ?></span>
			</p>

			<p class="nexop-mb__campo nexop-mb__campo--metade">
				<label><strong><?php esc_html_e( 'Duração', 'nexo-player' ); ?></strong></label>
				<input type="text" name="nexop_tempo" value="<?php echo esc_attr( $tempo ); ?>" placeholder="12:34" class="widefat">
			</p>

			<p class="nexop-mb__campo nexop-mb__campo--metade">
				<label><strong><?php esc_html_e( 'Vídeos relacionados', 'nexo-player' ); ?></strong></label>
				<select name="nexop_relacionados" class="widefat">
					<option value="1" <?php selected( $rel, '1' ); ?>><?php esc_html_e( 'Mostrar', 'nexo-player' ); ?></option>
					<option value="0" <?php selected( $rel, '0' ); ?>><?php esc_html_e( 'Ocultar neste vídeo', 'nexo-player' ); ?></option>
				</select>
			</p>
		</div>
		<?php
	}

	public static function save( int $post_id ): void {
		if ( ! isset( $_POST['nexop_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nexop_nonce'] ), 'nexop_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_nexop_mp4', esc_url_raw( wp_unslash( $_POST['nexop_mp4'] ?? '' ) ) );
		// Embed pode conter <iframe>: permite só a tag iframe.
		$embed = wp_unslash( $_POST['nexop_embed'] ?? '' );
		update_post_meta( $post_id, '_nexop_embed', self::sanitize_embed( $embed ) );
		update_post_meta( $post_id, '_nexop_poster', esc_url_raw( wp_unslash( $_POST['nexop_poster'] ?? '' ) ) );
		update_post_meta( $post_id, '_nexop_tempo', sanitize_text_field( wp_unslash( $_POST['nexop_tempo'] ?? '' ) ) );
		update_post_meta( $post_id, '_nexop_relacionados', ( '0' === ( $_POST['nexop_relacionados'] ?? '1' ) ) ? '0' : '1' );
	}

	/** Permite apenas <iframe> (ou uma URL) no campo de embed. */
	public static function sanitize_embed( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		// URL pura vira iframe simples.
		if ( preg_match( '#^https?://\S+$#', $raw ) ) {
			return esc_url_raw( $raw );
		}
		$allowed = array(
			'iframe' => array(
				'src'             => true,
				'width'           => true,
				'height'          => true,
				'frameborder'     => true,
				'allow'           => true,
				'allowfullscreen' => true,
				'scrolling'       => true,
				'referrerpolicy'  => true,
				'loading'         => true,
				'title'           => true,
				'style'           => true,
			),
		);
		return wp_kses( $raw, $allowed );
	}
}
