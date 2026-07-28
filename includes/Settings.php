<?php
/**
 * Página de configurações (Configurações → Nexo Player).
 *
 * @package NexoPlayer
 */

namespace NexoPlayer;

defined( 'ABSPATH' ) || exit;

class Settings {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu(): void {
		add_options_page( 'Nexo Player', 'Nexo Player', 'manage_options', 'nexo-player', array( __CLASS__, 'render' ) );
	}

	public static function assets( string $hook ): void {
		if ( 'settings_page_nexo-player' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'nexop-admin', NEXOP_URL . 'assets/admin.css', array(), NEXOP_VERSION );
		wp_enqueue_script( 'nexop-admin', NEXOP_URL . 'assets/admin.js', array( 'jquery' ), NEXOP_VERSION, true );
	}

	public static function register(): void {
		register_setting( 'nexop_group', Options::KEY, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $input ): array {
		$in    = is_array( $input ) ? $input : array();
		$clean = Options::defaults();

		foreach ( array( 'campo_mp4', 'campo_embed', 'campo_thumb', 'relacionados_tax' ) as $k ) {
			$clean[ $k ] = sanitize_key( $in[ $k ] ?? $clean[ $k ] );
		}
		$clean['post_types']       = array_values( array_filter( array_map( 'sanitize_key', (array) ( $in['post_types'] ?? array( 'post' ) ) ) ) ) ?: array( 'post' );
		$clean['auto_content']     = empty( $in['auto_content'] ) ? 0 : 1;
		$clean['relacionados']     = empty( $in['relacionados'] ) ? 0 : 1;
		$clean['relacionados_qtd'] = min( 24, max( 1, absint( $in['relacionados_qtd'] ?? 8 ) ) );
		$clean['continuar']        = empty( $in['continuar'] ) ? 0 : 1;
		$clean['continuar_min']    = min( 600, max( 3, absint( $in['continuar_min'] ?? 10 ) ) );
		$clean['responsivo']       = empty( $in['responsivo'] ) ? 0 : 1;
		$clean['embed_capa']       = empty( $in['embed_capa'] ) ? 0 : 1;
		$clean['cor']              = sanitize_hex_color( $in['cor'] ?? '' ) ?: Options::defaults()['cor'];
		$clean['logo_url']         = esc_url_raw( $in['logo_url'] ?? '' );
		$clean['logo_pos']         = in_array( $in['logo_pos'] ?? '', array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' ), true ) ? $in['logo_pos'] : 'top-right';
		$clean['logo_link']        = esc_url_raw( $in['logo_link'] ?? '' );
		$clean['logo_opacidade']   = min( 100, max( 5, absint( $in['logo_opacidade'] ?? 70 ) ) );

		foreach ( array( 'ads_topo', 'ads_rodape', 'ads_mobile', 'ads_desktop' ) as $k ) {
			// Scripts de ads/analytics: guardados como texto (só admin edita).
			$clean[ $k ] = trim( (string) ( $in[ $k ] ?? '' ) );
		}
		return $clean;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o          = Options::all();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<div class="wrap nexop-set">
			<h1><?php esc_html_e( 'Nexo Player', 'nexo-player' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'nexop_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Exibição', 'nexo-player' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Aparece em', 'nexo-player' ); ?></th>
						<td>
							<?php foreach ( $post_types as $pt ) : ?>
								<label style="margin-right:14px">
									<input type="checkbox" name="<?php echo esc_attr( Options::KEY ); ?>[post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, (array) $o['post_types'], true ) ); ?>>
									<?php echo esc_html( $pt->labels->singular_name ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Inserir no conteúdo', 'nexo-player' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Options::KEY ); ?>[auto_content]" value="1" <?php checked( $o['auto_content'] ); ?>> <?php esc_html_e( 'Colocar o player no topo do post automaticamente (senão, use o shortcode [nexo_player])', 'nexo-player' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Responsivo', 'nexo-player' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Options::KEY ); ?>[responsivo]" value="1" <?php checked( $o['responsivo'] ); ?>> <?php esc_html_e( 'Ajustar à largura da tela', 'nexo-player' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Capa no embed', 'nexo-player' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Options::KEY ); ?>[embed_capa]" value="1" <?php checked( $o['embed_capa'] ); ?>> <?php esc_html_e( 'Mostrar capa própria e só carregar o player do tube no clique (esconde a marca do site de origem antes do play; requer capa/thumbnail no post)', 'nexo-player' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="nexop_cor"><?php esc_html_e( 'Cor de destaque', 'nexo-player' ); ?></label></th>
						<td><input type="color" id="nexop_cor" name="<?php echo esc_attr( Options::KEY ); ?>[cor]" value="<?php echo esc_attr( $o['cor'] ); ?>"></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Campos personalizados', 'nexo-player' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Se seus posts já guardam o vídeo em custom fields, informe os nomes para o player reaproveitá-los.', 'nexo-player' ); ?></p>
				<table class="form-table">
					<?php
					self::linha_texto( $o, 'campo_mp4', __( 'Campo do MP4', 'nexo-player' ) );
					self::linha_texto( $o, 'campo_embed', __( 'Campo do embed', 'nexo-player' ) );
					self::linha_texto( $o, 'campo_thumb', __( 'Campo da capa', 'nexo-player' ) );
					?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Marca d\'água / logo', 'nexo-player' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Imagem do logo', 'nexo-player' ); ?></th>
						<td>
							<span class="nexop-mb__upload">
								<input type="url" name="<?php echo esc_attr( Options::KEY ); ?>[logo_url]" id="nexop_logo_url" value="<?php echo esc_attr( $o['logo_url'] ); ?>" class="regular-text" placeholder="https://…/logo.png">
								<button type="button" class="button nexop-mb__btn" data-nexop-upload="nexop_logo_url" data-tipo="image"><?php esc_html_e( 'Selecionar', 'nexo-player' ); ?></button>
							</span>
							<p class="description"><?php esc_html_e( 'Vazio = sem marca d\'água.', 'nexo-player' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Posição', 'nexo-player' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( Options::KEY ); ?>[logo_pos]">
								<?php
								foreach ( array(
									'top-left'     => __( 'Superior esquerda', 'nexo-player' ),
									'top-right'    => __( 'Superior direita', 'nexo-player' ),
									'bottom-left'  => __( 'Inferior esquerda', 'nexo-player' ),
									'bottom-right' => __( 'Inferior direita', 'nexo-player' ),
								) as $v => $l ) {
									printf( '<option value="%s" %s>%s</option>', esc_attr( $v ), selected( $o['logo_pos'], $v, false ), esc_html( $l ) );
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Opacidade (%)', 'nexo-player' ); ?></th>
						<td><input type="number" min="5" max="100" name="<?php echo esc_attr( Options::KEY ); ?>[logo_opacidade]" value="<?php echo esc_attr( $o['logo_opacidade'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Link ao clicar', 'nexo-player' ); ?></th>
						<td><input type="url" name="<?php echo esc_attr( Options::KEY ); ?>[logo_link]" value="<?php echo esc_attr( $o['logo_link'] ); ?>" class="regular-text" placeholder="https://seusite.com"></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Vídeos relacionados', 'nexo-player' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Ativar', 'nexo-player' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Options::KEY ); ?>[relacionados]" value="1" <?php checked( $o['relacionados'] ); ?>> <?php esc_html_e( 'Mostrar abaixo do player', 'nexo-player' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Quantidade', 'nexo-player' ); ?></th>
						<td><input type="number" min="1" max="24" name="<?php echo esc_attr( Options::KEY ); ?>[relacionados_qtd]" value="<?php echo esc_attr( $o['relacionados_qtd'] ); ?>" class="small-text"></td>
					</tr>
					<?php self::linha_texto( $o, 'relacionados_tax', __( 'Relacionar pela taxonomia', 'nexo-player' ), 'category' ); ?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Continuar assistindo', 'nexo-player' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Ativar', 'nexo-player' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Options::KEY ); ?>[continuar]" value="1" <?php checked( $o['continuar'] ); ?>> <?php esc_html_e( 'Retomar o vídeo de onde parou (MP4 próprio)', 'nexo-player' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Tempo mínimo (s)', 'nexo-player' ); ?></th>
						<td><input type="number" min="3" max="600" name="<?php echo esc_attr( Options::KEY ); ?>[continuar_min]" value="<?php echo esc_attr( $o['continuar_min'] ); ?>" class="small-text"></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Códigos extras (ads / analytics)', 'nexo-player' ); ?></h2>
				<table class="form-table">
					<?php
					self::linha_textarea( $o, 'ads_topo', __( 'Topo do player', 'nexo-player' ) );
					self::linha_textarea( $o, 'ads_rodape', __( 'Rodapé do player', 'nexo-player' ) );
					self::linha_textarea( $o, 'ads_mobile', __( 'Só no mobile', 'nexo-player' ) );
					self::linha_textarea( $o, 'ads_desktop', __( 'Só no desktop', 'nexo-player' ) );
					?>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private static function linha_texto( array $o, string $key, string $label, string $ph = '' ): void {
		printf(
			'<tr><th><label>%s</label></th><td><input type="text" name="%s[%s]" value="%s" class="regular-text" placeholder="%s"></td></tr>',
			esc_html( $label ),
			esc_attr( Options::KEY ),
			esc_attr( $key ),
			esc_attr( $o[ $key ] ),
			esc_attr( $ph )
		);
	}

	private static function linha_textarea( array $o, string $key, string $label ): void {
		printf(
			'<tr><th><label>%s</label></th><td><textarea name="%s[%s]" rows="3" class="large-text code">%s</textarea></td></tr>',
			esc_html( $label ),
			esc_attr( Options::KEY ),
			esc_attr( $key ),
			esc_textarea( $o[ $key ] )
		);
	}
}
