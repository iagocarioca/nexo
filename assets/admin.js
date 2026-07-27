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
} );
