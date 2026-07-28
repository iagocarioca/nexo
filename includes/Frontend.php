<?php
/**
 * Front-end: renderiza o player no post, marca d'água, relacionados,
 * códigos extras e "continuar assistindo".
 *
 * @package NexoPlayer
 */

namespace NexoPlayer;

defined( 'ABSPATH' ) || exit;

class Frontend {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'inject' ), 8 );
		add_shortcode( 'nexo_player', array( __CLASS__, 'shortcode' ) );
	}

	public static function assets(): void {
		if ( ! is_singular( Options::post_types() ) ) {
			return;
		}
		wp_enqueue_style( 'nexop', NEXOP_URL . 'assets/player.css', array(), NEXOP_VERSION );
		wp_add_inline_style( 'nexop', ':root{--nexop-cor:' . esc_attr( Options::get( 'cor' ) ) . '}' );
		wp_enqueue_script( 'nexop', NEXOP_URL . 'assets/player.js', array(), NEXOP_VERSION, true );
		wp_localize_script(
			'nexop',
			'NexoPlayerData',
			array(
				'continuar'    => Options::is_on( 'continuar' ),
				'continuarMin' => (int) Options::get( 'continuar_min' ),
			)
		);
	}

	/** Insere o player no topo do conteúdo (se configurado). */
	public static function inject( string $content ): string {
		if ( ! is_singular( Options::post_types() ) || ! in_the_loop() || ! is_main_query() || ! Options::is_on( 'auto_content' ) ) {
			return $content;
		}
		$html = self::build( get_the_ID() );
		return $html ? $html . $content : $content;
	}

	/** Shortcode [nexo_player id="123"]. */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'nexo_player' );
		$id   = absint( $atts['id'] ) ?: get_the_ID();
		return $id ? self::build( (int) $id ) : '';
	}

	/** Monta o bloco completo do player para um post. */
	public static function build( int $post_id ): string {
		$v = Options::video( $post_id );
		if ( '' === $v['mp4'] && '' === $v['embed'] ) {
			return '';
		}

		$o = Options::all();

		// Sem MP4 próprio: tenta pegar o arquivo direto na página do embed.
		if ( '' === $v['mp4'] && '' !== $v['embed'] && ! empty( $o['embed_extrair'] ) ) {
			$v['mp4'] = Resolver::for_post( $post_id, $v['embed'] );
		}

		$classe = 'nexop' . ( $o['responsivo'] ? ' nexop--responsivo' : '' );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $classe ); ?>" data-nexop data-id="<?php echo esc_attr( $post_id ); ?>">
			<?php echo self::codigo_extra( 'ads_topo', $o ); // phpcs:ignore ?>

			<div class="nexop__palco">
				<?php if ( '' !== $v['mp4'] ) : ?>
					<video class="nexop__video" controls playsinline preload="metadata"
						<?php echo $v['poster'] ? 'poster="' . esc_url( $v['poster'] ) . '"' : ''; ?>
						data-nexop-video>
						<source src="<?php echo esc_url( $v['mp4'] ); ?>">
					</video>
				<?php elseif ( $o['embed_capa'] && $v['poster'] && ( $src = self::embed_src( $v['embed'] ) ) ) : ?>
					<?php // Capa própria: nada do site de origem aparece até o clique. ?>
					<button type="button" class="nexop__embed nexop__capa" data-nexop-embed="<?php echo esc_url( $src ); ?>"
						style="background-image:url('<?php echo esc_url( $v['poster'] ); ?>')"
						aria-label="<?php esc_attr_e( 'Reproduzir vídeo', 'nexo-player' ); ?>">
						<span class="nexop__capa-play" aria-hidden="true"><svg viewBox="0 0 24 24" fill="#fff"><path d="M8 5v14l11-7z"/></svg></span>
					</button>
				<?php else : ?>
					<div class="nexop__embed"><?php echo self::render_embed( $v['embed'] ); // phpcs:ignore ?></div>
				<?php endif; ?>

				<?php if ( $o['logo_url'] ) : ?>
					<?php $wm = 'nexop__logo nexop__logo--' . esc_attr( $o['logo_pos'] ); ?>
					<?php if ( $o['logo_link'] ) : ?>
						<a class="<?php echo $wm; // phpcs:ignore ?>" href="<?php echo esc_url( $o['logo_link'] ); ?>" target="_blank" rel="nofollow noopener" style="opacity:<?php echo esc_attr( $o['logo_opacidade'] / 100 ); ?>">
							<img src="<?php echo esc_url( $o['logo_url'] ); ?>" alt="">
						</a>
					<?php else : ?>
						<span class="<?php echo $wm; // phpcs:ignore ?>" style="opacity:<?php echo esc_attr( $o['logo_opacidade'] / 100 ); ?>"><img src="<?php echo esc_url( $o['logo_url'] ); ?>" alt=""></span>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<?php echo self::codigo_extra( 'ads_rodape', $o ); // phpcs:ignore ?>
		</div>

		<?php
		if ( $o['relacionados'] && $v['relacionados'] ) {
			echo self::relacionados( $post_id, $o ); // phpcs:ignore
		}
		return (string) ob_get_clean();
	}

	/** Extrai a URL do player de um embed salvo (iframe completo ou URL direta). */
	public static function embed_src( string $embed ): string {
		$embed = trim( $embed );
		if ( preg_match( '/<iframe[^>]+src=["\']([^"\']+)["\']/i', $embed, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '#^https?://\S+$#', $embed ) ) {
			return $embed;
		}
		return '';
	}

	/** Envolve o embed (iframe ou URL) num wrapper responsivo. */
	private static function render_embed( string $embed ): string {
		$embed = trim( $embed );
		if ( preg_match( '#^https?://\S+$#', $embed ) ) {
			return '<iframe src="' . esc_url( $embed ) . '" allowfullscreen loading="lazy" referrerpolicy="no-referrer"></iframe>';
		}
		// Já sanitizado no save (wp_kses só iframe); reforça o allowfullscreen.
		return (string) $embed;
	}

	/** Injeta um bloco de código extra respeitando mobile/desktop. */
	private static function codigo_extra( string $slot, array $o ): string {
		$out = '';
		if ( ! empty( $o[ $slot ] ) ) {
			$out .= '<div class="nexop__extra">' . $o[ $slot ] . '</div>';
		}
		if ( 'ads_topo' === $slot ) {
			if ( ! empty( $o['ads_mobile'] ) ) {
				$out .= '<div class="nexop__extra nexop__extra--mobile">' . $o['ads_mobile'] . '</div>';
			}
			if ( ! empty( $o['ads_desktop'] ) ) {
				$out .= '<div class="nexop__extra nexop__extra--desktop">' . $o['ads_desktop'] . '</div>';
			}
		}
		return $out;
	}

	/** Grade de vídeos relacionados (mesma taxonomia; completa com recentes). */
	private static function relacionados( int $post_id, array $o ): string {
		$tax   = $o['relacionados_tax'] ?: 'category';
		$qtd   = (int) $o['relacionados_qtd'];
		$terms = get_the_terms( $post_id, $tax );
		$args  = array(
			'post_type'      => get_post_type( $post_id ),
			'post_status'    => 'publish',
			'posts_per_page' => $qtd,
			'post__not_in'   => array( $post_id ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);
		if ( $terms && ! is_wp_error( $terms ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => $tax,
					'field'    => 'term_id',
					'terms'    => wp_list_pluck( $terms, 'term_id' ),
				),
			);
		}
		$q = new \WP_Query( $args );
		if ( ! $q->have_posts() ) {
			return '';
		}

		ob_start();
		echo '<div class="nexop-rel"><h3 class="nexop-rel__titulo">' . esc_html__( 'Vídeos relacionados', 'nexo-player' ) . '</h3><div class="nexop-rel__grade">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$cap = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
			$rp  = (string) ( get_post_meta( get_the_ID(), '_nexop_poster', true ) );
			$cap = $cap ?: $rp;
			printf(
				'<a class="nexop-rel__item" href="%s"><span class="nexop-rel__thumb"%s><span class="nexop-rel__play" aria-hidden="true"><svg viewBox="0 0 24 24" fill="#fff"><path d="M8 5v14l11-7z"/></svg></span></span><span class="nexop-rel__nome">%s</span></a>',
				esc_url( get_permalink() ),
				$cap ? ' style="background-image:url(\'' . esc_url( $cap ) . '\')"' : '',
				esc_html( wp_trim_words( get_the_title(), 10 ) )
			);
		}
		echo '</div></div>';
		wp_reset_postdata();
		return (string) ob_get_clean();
	}
}
