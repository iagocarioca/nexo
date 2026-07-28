/* Nexo Player — seletor da biblioteca de mídia nos campos com botão. */
jQuery( function ( $ ) {
	var frame;
	$( document ).on( 'click', '[data-nexop-upload]', function ( e ) {
		e.preventDefault();
		var alvo = '#' + $( this ).data( 'nexop-upload' );
		var tipo = $( this ).data( 'tipo' ) || 'image';
		frame = wp.media( { title: 'Selecionar', multiple: false, library: { type: tipo } } );
		frame.on( 'select', function () {
			var url = frame.state().get( 'selection' ).first().toJSON().url;
			$( alvo ).val( url );
		} );
		frame.open();
	} );

	/* Extrai o MP4 (html5player.setVideoUrlLow) da página do embed. */
	$( document ).on( 'click', '#nexop_extrair', function () {
		var cfg    = window.NexoPlayerAdmin || {};
		var botao  = $( this );
		var status = $( '#nexop_extrair_status' );
		if ( ! cfg.ajaxUrl ) {
			return;
		}
		botao.prop( 'disabled', true );
		status.text( 'Buscando…' );
		$.post( cfg.ajaxUrl, {
			action:  'nexop_extrair',
			nonce:   cfg.nonce,
			post_id: cfg.postId,
			embed:   $( '#nexop_embed' ).val()
		} ).done( function ( r ) {
			if ( r && r.success ) {
				status.text( 'MP4 encontrado: ' + r.data.mp4 );
			} else {
				status.text( ( r && r.data && r.data.msg ) || 'Não consegui extrair.' );
			}
		} ).fail( function () {
			status.text( 'Falha na consulta.' );
		} ).always( function () {
			botao.prop( 'disabled', false );
		} );
	} );
} );
