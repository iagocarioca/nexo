/* Nexo Player — continuar assistindo (localStorage por vídeo) */
( function () {
	'use strict';
	var D = window.NexoPlayerData || {};

	function fmt( s ) {
		s = Math.floor( s );
		var m = Math.floor( s / 60 );
		var r = s % 60;
		return m + ':' + ( r < 10 ? '0' : '' ) + r;
	}

	/* Capa do embed: o iframe do tube só entra na página no clique —
	   antes disso, nada do site de origem aparece. */
	document.querySelectorAll( '[data-nexop-embed]' ).forEach( function ( capa ) {
		capa.addEventListener( 'click', function () {
			var src    = capa.getAttribute( 'data-nexop-embed' );
			var iframe = document.createElement( 'iframe' );
			iframe.src = src;
			iframe.setAttribute( 'allow', 'autoplay; fullscreen; encrypted-media; picture-in-picture' );
			iframe.setAttribute( 'allowfullscreen', '' );
			iframe.setAttribute( 'frameborder', '0' );
			iframe.setAttribute( 'scrolling', 'no' );
			iframe.setAttribute( 'referrerpolicy', 'origin' );
			var wrap = document.createElement( 'div' );
			wrap.className = 'nexop__embed';
			wrap.appendChild( iframe );
			capa.replaceWith( wrap );
		}, { once: true } );
	} );

	document.querySelectorAll( '[data-nexop]' ).forEach( function ( box ) {
		var video = box.querySelector( '[data-nexop-video]' );
		if ( ! video || ! D.continuar ) {
			return;
		}
		var id  = box.getAttribute( 'data-id' );
		var key = 'nexop_pos_' + id;

		// Oferece retomar se houver posição salva.
		video.addEventListener( 'loadedmetadata', function () {
			var pos = parseFloat( localStorage.getItem( key ) || '0' );
			var min = D.continuarMin || 10;
			if ( pos > min && video.duration && pos < video.duration - min ) {
				mostrarRetomar( pos );
			}
		} );

		// Salva a posição enquanto assiste (a cada ~5s).
		var ultimo = 0;
		video.addEventListener( 'timeupdate', function () {
			if ( video.currentTime - ultimo >= 5 || video.currentTime < ultimo ) {
				ultimo = video.currentTime;
				try { localStorage.setItem( key, String( video.currentTime ) ); } catch ( e ) {}
			}
		} );
		video.addEventListener( 'ended', function () {
			try { localStorage.removeItem( key ); } catch ( e ) {}
		} );

		function mostrarRetomar( pos ) {
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'nexop__retomar';
			btn.innerHTML = 'Continuar de <b>' + fmt( pos ) + '</b> ▸';
			box.querySelector( '.nexop__palco' ).appendChild( btn );
			var some = function () { if ( btn.parentNode ) { btn.remove(); } };
			btn.addEventListener( 'click', function () {
				try { video.currentTime = pos; } catch ( e ) {}
				video.play().catch( function () {} );
				some();
			} );
			// Some se o usuário começar a assistir do zero.
			video.addEventListener( 'play', some, { once: true } );
			setTimeout( some, 8000 );
		}
	} );
} )();
