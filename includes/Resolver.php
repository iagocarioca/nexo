<?php
/**
 * Extrai o MP4 direto a partir da página de embed do tube.
 *
 * Páginas de embed no padrão Xvideos/Xnxx trazem no script:
 *   html5player.setVideoUrlLow('https://.../video.mp4');
 *   html5player.setVideoUrlHigh('https://.../video.mp4');
 *
 * @package NexoPlayer
 */

namespace NexoPlayer;

defined( 'ABSPATH' ) || exit;

class Resolver {

	/** Meta onde o MP4 extraído fica guardado. */
	const META_URL  = '_nexop_mp4_auto';
	const META_TIME = '_nexop_mp4_auto_time';

	/**
	 * Quanto tempo o MP4 extraído vale antes de nova consulta (segundos).
	 *
	 * Padrão curto porque os CDNs assinam a URL com validade de poucas horas.
	 */
	public static function ttl(): int {
		return (int) apply_filters( 'nexop_extrair_ttl', 2 * HOUR_IN_SECONDS );
	}

	/**
	 * Validade real da URL assinada, quando dá para saber.
	 *
	 * Xvideos/Xnxx assinam assim: ?secure=<hash>,<timestamp-unix-de-expiracao>
	 * Devolve o timestamp de expiração ou 0 se a URL não for assinada.
	 */
	public static function expira_em( string $url ): int {
		$qs = (string) wp_parse_url( $url, PHP_URL_QUERY );
		if ( '' === $qs ) {
			return 0;
		}
		parse_str( $qs, $args );

		foreach ( array( 'secure', 'validfrom', 'expires', 'e' ) as $chave ) {
			if ( empty( $args[ $chave ] ) || ! is_string( $args[ $chave ] ) ) {
				continue;
			}
			// Pega o maior número com cara de timestamp unix dentro do valor.
			if ( ! preg_match_all( '/\d{9,11}/', $args[ $chave ], $m ) ) {
				continue;
			}
			$ts = max( array_map( 'intval', $m[0] ) );
			// Aceita qualquer data plausível (2001+), inclusive já vencida —
			// devolver 0 para uma URL vencida faria ela passar por "sem assinatura".
			if ( $ts > 1000000000 ) {
				return $ts;
			}
		}
		return 0;
	}

	/** A URL assinada já venceu (ou vence tão cedo que não vale servir)? */
	public static function vencida( string $url ): bool {
		$exp = self::expira_em( $url );
		if ( $exp ) {
			// Margem: não serve URL que morre no meio da reprodução.
			return $exp - time() < 5 * MINUTE_IN_SECONDS;
		}
		// Sem assinatura detectável: quem manda é o TTL.
		return false;
	}

	/**
	 * MP4 de um post a partir do embed, usando cache em post meta.
	 *
	 * @param int    $post_id ID do post.
	 * @param string $embed   Iframe ou URL salvos no campo de embed.
	 * @param bool   $forcar  Ignora o cache e consulta de novo.
	 */
	public static function for_post( int $post_id, string $embed, bool $forcar = false ): string {
		$src = Frontend::embed_src( $embed );
		if ( '' === $src ) {
			return '';
		}

		$cache = (string) get_post_meta( $post_id, self::META_URL, true );
		$idade = time() - (int) get_post_meta( $post_id, self::META_TIME, true );

		// URL assinada vencida não serve para nada: obriga nova consulta.
		if ( $cache && self::vencida( $cache ) ) {
			$cache = '';
		}
		if ( ! $forcar && $cache && $idade < self::ttl() ) {
			return $cache;
		}

		// Falhou há pouco: não repete a consulta a cada visita.
		$backoff = 'nexop_falha_' . md5( $src );
		if ( ! $forcar && ! $cache && get_transient( $backoff ) ) {
			return '';
		}

		$mp4 = self::resolve( $src );
		if ( '' === $mp4 ) {
			set_transient( $backoff, 1, 15 * MINUTE_IN_SECONDS );
			// Só reaproveita o anterior se ele ainda estiver válido;
			// senão devolve '' e o player cai no iframe (que funciona).
			return $cache;
		}

		delete_transient( $backoff );
		update_post_meta( $post_id, self::META_URL, $mp4 );
		update_post_meta( $post_id, self::META_TIME, time() );
		return $mp4;
	}

	/** Limpa o MP4 extraído de um post (usado quando o embed muda). */
	public static function limpar( int $post_id ): void {
		delete_post_meta( $post_id, self::META_URL );
		delete_post_meta( $post_id, self::META_TIME );
	}

	/**
	 * Baixa a página de embed e devolve o MP4 encontrado (ou '').
	 *
	 * @param string $url    URL da página de embed.
	 * @param int    $saltos Controle interno de recursão (canonical).
	 */
	public static function resolve( string $url, int $saltos = 0 ): string {
		$html = self::baixar( $url );
		if ( '' === $html ) {
			return '';
		}

		$mp4 = self::extrair( $html, $url );
		if ( '' !== $mp4 ) {
			return $mp4;
		}

		// Alguns embeds só apontam para a página do vídeo: tenta uma vez lá.
		if ( $saltos < 1 && preg_match( '/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $m ) ) {
			$canon = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
			if ( $canon !== $url && preg_match( '#^https?://#i', $canon ) ) {
				return self::resolve( $canon, $saltos + 1 );
			}
		}
		return '';
	}

	/** Procura setVideoUrlLow (e variantes) no HTML. */
	public static function extrair( string $html, string $base = '' ): string {
		$chaves = (array) apply_filters(
			'nexop_extrair_chaves',
			array( 'setVideoUrlLow', 'setVideoUrlHigh', 'setVideoURLLow', 'setVideoURLHigh' )
		);

		foreach ( $chaves as $chave ) {
			$re = '/' . preg_quote( $chave, '/' ) . '\s*\(\s*[\'"]([^\'"]+)[\'"]/i';
			if ( ! preg_match( $re, $html, $m ) ) {
				continue;
			}
			$url = self::normalizar( $m[1], $base );
			if ( '' !== $url ) {
				return $url;
			}
		}
		return '';
	}

	/** Limpa escapes de JS, resolve URL relativa e valida. */
	private static function normalizar( string $url, string $base ): string {
		$url = trim( html_entity_decode( str_replace( '\\/', '/', $url ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		} elseif ( '/' === $url[0] && $base ) {
			$p = wp_parse_url( $base );
			if ( empty( $p['host'] ) ) {
				return '';
			}
			$url = ( $p['scheme'] ?? 'https' ) . '://' . $p['host'] . $url;
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}
		return (string) esc_url_raw( $url );
	}

	/** GET na página de embed com cabeçalhos de navegador. */
	private static function baixar( string $url ): string {
		$p      = wp_parse_url( $url );
		$origem = ( ! empty( $p['host'] ) ) ? ( ( $p['scheme'] ?? 'https' ) . '://' . $p['host'] . '/' ) : '';

		$res = wp_remote_get(
			$url,
			array(
				'timeout'     => (int) apply_filters( 'nexop_extrair_timeout', 8 ),
				'redirection' => 3,
				'sslverify'   => true,
				'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
				'headers'     => array(
					'Accept'          => 'text/html,application/xhtml+xml,*/*;q=0.8',
					'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
					'Referer'         => $origem,
				),
			)
		);

		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return '';
		}
		return (string) wp_remote_retrieve_body( $res );
	}
}
