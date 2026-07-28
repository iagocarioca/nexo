<?php
/**
 * Opções do plugin (com padrões) e leitura dos dados do post.
 *
 * @package NexoPlayer
 */

namespace NexoPlayer;

defined( 'ABSPATH' ) || exit;

class Options {

	const KEY = 'nexop_settings';

	/** Padrões de todas as configurações. */
	public static function defaults(): array {
		return array(
			// Campos personalizados (permite ler dados que já existem no post).
			'campo_mp4'      => 'campo_mp4',
			'campo_embed'    => 'campo_embed',
			'campo_thumb'    => 'poster',
			// Onde inserir o player.
			'post_types'     => array( 'post' ),
			'auto_content'   => 1,   // insere o player no topo do conteúdo automaticamente
			// Relacionados.
			'relacionados'   => 1,
			'relacionados_qtd' => 8,
			'relacionados_tax' => 'category',
			// Continuar assistindo.
			'continuar'      => 1,
			'continuar_min'  => 10,  // só oferece retomar se faltar mais que X segundos
			// Aparência.
			'cor'            => '#fc30b7',
			'responsivo'     => 1,
			// Embed: mostra capa própria e só carrega o iframe do tube no clique
			// (nada do site de origem aparece antes do play).
			'embed_capa'     => 1,
			// Marca d'água.
			'logo_url'       => '',
			'logo_pos'       => 'top-right', // top-left | top-right | bottom-left | bottom-right
			'logo_link'      => '',
			'logo_opacidade' => 70,
			// Códigos extras.
			'ads_topo'       => '',
			'ads_rodape'     => '',
			'ads_mobile'     => '',
			'ads_desktop'    => '',
		);
	}

	/** Todas as opções (salvas + padrões). Temas podem ajustar via filtro. */
	public static function all(): array {
		$saved = get_option( self::KEY, array() );
		$all   = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		return (array) apply_filters( 'nexop_options', $all );
	}

	/** Lê uma opção. */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	public static function is_on( string $key ): bool {
		return ! empty( self::get( $key ) );
	}

	/** Post types onde o player atua. */
	public static function post_types(): array {
		$pt = self::get( 'post_types' );
		return is_array( $pt ) && $pt ? $pt : array( 'post' );
	}

	/**
	 * Dados do vídeo de um post: procura primeiro nos campos próprios do
	 * plugin, depois nos campos personalizados configurados.
	 *
	 * @param int $post_id ID do post.
	 * @return array{mp4:string,embed:string,poster:string,tempo:string,relacionados:bool}
	 */
	public static function video( int $post_id ): array {
		$campo_mp4   = self::get( 'campo_mp4' ) ?: 'campo_mp4';
		$campo_embed = self::get( 'campo_embed' ) ?: 'campo_embed';
		$campo_thumb = self::get( 'campo_thumb' ) ?: 'poster';

		$mp4    = (string) ( get_post_meta( $post_id, '_nexop_mp4', true ) ?: get_post_meta( $post_id, $campo_mp4, true ) );
		$embed  = (string) ( get_post_meta( $post_id, '_nexop_embed', true ) ?: get_post_meta( $post_id, $campo_embed, true ) );
		$poster = (string) ( get_post_meta( $post_id, '_nexop_poster', true ) ?: get_post_meta( $post_id, $campo_thumb, true ) );
		if ( ! $poster && has_post_thumbnail( $post_id ) ) {
			$poster = (string) get_the_post_thumbnail_url( $post_id, 'large' );
		}

		$rel = get_post_meta( $post_id, '_nexop_relacionados', true );

		$data = array(
			'mp4'          => $mp4,
			'embed'        => $embed,
			'poster'       => $poster,
			'tempo'        => (string) get_post_meta( $post_id, '_nexop_tempo', true ),
			'relacionados' => ( '' === $rel ) ? true : ( '1' === (string) $rel ),
		);

		// Temas integram fontes próprias sem tocar no plugin (ex.: metas de outro tema).
		return (array) apply_filters( 'nexop_video', $data, $post_id );
	}
}
