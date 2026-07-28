/* Nexo Player — player próprio (controles custom) + continuar assistindo */
( function () {
	'use strict';
	var D = window.NexoPlayerData || {};

	/** Segundos -> "m:ss" (ou "h:mm:ss" quando passa de 1 hora). */
	function fmt( s ) {
		if ( ! isFinite( s ) || s < 0 ) {
			s = 0;
		}
		s = Math.floor( s );
		var h = Math.floor( s / 3600 );
		var m = Math.floor( ( s % 3600 ) / 60 );
		var r = s % 60;
		var dois = function ( n ) { return ( n < 10 ? '0' : '' ) + n; };
		return h ? h + ':' + dois( m ) + ':' + dois( r ) : m + ':' + dois( r );
	}

	var ICO = {
		play:  '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>',
		pause: '<svg viewBox="0 0 24 24"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>',
		re10:  '<svg viewBox="0 0 24 24"><path d="M12 5V1L7 6l5 5V7a6 6 0 1 1-6 6H4a8 8 0 1 0 8-8z"/><text x="12" y="16.5" font-size="7.5" font-weight="700" text-anchor="middle" fill="currentColor" stroke="none">10</text></svg>',
		av10:  '<svg viewBox="0 0 24 24"><path d="M12 5V1l5 5-5 5V7a6 6 0 1 0 6 6h2a8 8 0 1 1-8-8z"/><text x="12" y="16.5" font-size="7.5" font-weight="700" text-anchor="middle" fill="currentColor" stroke="none">10</text></svg>',
		som:   '<svg viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3a4.5 4.5 0 0 0-2.5-4v8a4.5 4.5 0 0 0 2.5-4zM14 2.2v2.1a7.5 7.5 0 0 1 0 15.4v2.1A9.5 9.5 0 0 0 14 2.2z"/></svg>',
		mudo:  '<svg viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm18.6 3-2.1-2.1-1.4 1.4 2.1 2.1-2.1 2.1 1.4 1.4 2.1-2.1 2.1 2.1 1.4-1.4-2.1-2.1 2.1-2.1-1.4-1.4-2.1 2.1z"/></svg>',
		pip:   '<svg viewBox="0 0 24 24"><path d="M19 11h-8v6h8v-6zm4 8V4.98C23 3.88 22.1 3 21 3H3c-1.1 0-2 .88-2 1.98V19c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2zm-2 .02H3V4.97h18v14.05z"/></svg>',
		full:  '<svg viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>',
		sai:   '<svg viewBox="0 0 24 24"><path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/></svg>'
	};

	var VELOCIDADES = [ 0.5, 0.75, 1, 1.25, 1.5, 2 ];

	/* ---------------------------------------------------------------
	   Capa do embed: o iframe do tube só entra na página no clique.
	   --------------------------------------------------------------- */
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

	/**
	 * Monta os controles próprios sobre um <video>.
	 * O vídeo vem com controls nativos no HTML: só troca se o JS rodar.
	 */
	function montarUI( palco, video ) {
		video.removeAttribute( 'controls' );
		palco.classList.add( 'nexop__palco--ui' );
		// Sem capa o fundo fica preto: o play central precisa de destaque próprio.
		if ( ! video.getAttribute( 'poster' ) ) {
			palco.classList.add( 'nexop__palco--semcapa' );
		}

		var el = function ( tag, cls, html ) {
			var n = document.createElement( tag );
			if ( cls ) { n.className = cls; }
			if ( html ) { n.innerHTML = html; }
			return n;
		};
		var botao = function ( cls, ico, rotulo ) {
			var b = el( 'button', 'nexop-ui__btn' + ( cls ? ' ' + cls : '' ), ico );
			b.type = 'button';
			b.setAttribute( 'aria-label', rotulo );
			b.title = rotulo;
			return b;
		};

		var sombra = el( 'div', 'nexop-ui__sombra' );
		var grande = el( 'button', 'nexop-ui__grande', ICO.play );
		grande.type = 'button';
		grande.setAttribute( 'aria-label', 'Reproduzir' );
		var carreg = el( 'div', 'nexop-ui__load' );
		carreg.hidden = true;
		var aviso  = el( 'div', 'nexop-ui__aviso' );

		// Trilha de progresso
		var trilha   = el( 'div', 'nexop-ui__trilha' );
		var buffer   = el( 'div', 'nexop-ui__buffer' );
		var preenche = el( 'div', 'nexop-ui__preenche' );
		var bolinha  = el( 'div', 'nexop-ui__bolinha' );
		var dica     = el( 'div', 'nexop-ui__dica', '0:00' );
		trilha.append( buffer, preenche, bolinha, dica );
		trilha.setAttribute( 'role', 'slider' );
		trilha.setAttribute( 'aria-label', 'Progresso do vídeo' );
		trilha.tabIndex = 0;

		// Botões
		var bPlay = botao( '', ICO.play, 'Reproduzir' );
		var bRe   = botao( 'nexop-ui__opcional', ICO.re10, 'Voltar 10 segundos' );
		var bAv   = botao( 'nexop-ui__opcional', ICO.av10, 'Avançar 10 segundos' );
		var bSom  = botao( '', ICO.som, 'Mudo' );
		var bVel  = botao( 'nexop-ui__vel nexop-ui__opcional', '1x', 'Velocidade' );
		var bPip  = botao( 'nexop-ui__opcional', ICO.pip, 'Picture-in-picture' );
		var bFull = botao( '', ICO.full, 'Tela cheia' );
		var tempo = el( 'div', 'nexop-ui__tempo', '0:00<i>/</i>0:00' );

		// Volume com barra que cresce no hover
		var volCaixa    = el( 'div', 'nexop-ui__vol' );
		var volTrilha   = el( 'div', 'nexop-ui__vol-trilha' );
		var volBarra    = el( 'div', 'nexop-ui__vol-barra' );
		var volPreenche = el( 'div', 'nexop-ui__vol-preenche' );
		volBarra.appendChild( volPreenche );
		volTrilha.appendChild( volBarra );
		volCaixa.append( bSom, volTrilha );

		// Menu de velocidade
		var menu = el( 'div', 'nexop-ui__menu', '<div class="nexop-ui__menu-tit">Velocidade</div>' );
		menu.hidden = true;
		VELOCIDADES.forEach( function ( v ) {
			var o = el( 'button', 'nexop-ui__opt', v === 1 ? 'Normal' : v + 'x' );
			o.type = 'button';
			o.setAttribute( 'role', 'menuitemradio' );
			o.setAttribute( 'aria-checked', v === 1 ? 'true' : 'false' );
			o.addEventListener( 'click', function () {
				video.playbackRate = v;
				bVel.textContent = v + 'x';
				menu.querySelectorAll( '.nexop-ui__opt' ).forEach( function ( x ) {
					x.setAttribute( 'aria-checked', x === o ? 'true' : 'false' );
				} );
				menu.hidden = true;
				mostrarAviso( v === 1 ? 'Velocidade normal' : v + 'x' );
			} );
			menu.appendChild( o );
		} );

		var linha = el( 'div', 'nexop-ui__linha' );
		linha.append( bPlay, bRe, bAv, volCaixa, tempo, el( 'div', 'nexop-ui__espaco' ), bVel, bPip, bFull );

		var barra = el( 'div', 'nexop-ui' );
		barra.append( trilha, linha );
		palco.append( sombra, grande, carreg, aviso, menu, barra );

		if ( ! document.pictureInPictureEnabled || video.disablePictureInPicture ) {
			bPip.remove();
		}

		/* ---------- aviso central rápido ---------- */
		var avisoT;
		function mostrarAviso( txt ) {
			aviso.textContent = txt;
			aviso.classList.add( 'nexop-ui__aviso--on' );
			clearTimeout( avisoT );
			avisoT = setTimeout( function () {
				aviso.classList.remove( 'nexop-ui__aviso--on' );
			}, 700 );
		}

		/* ---------- play / pause ---------- */
		function alterna() {
			if ( video.paused ) {
				video.play().catch( function () {} );
			} else {
				video.pause();
			}
		}
		function pintaPlay() {
			var t = video.paused ? 'Reproduzir' : 'Pausar';
			bPlay.innerHTML = video.paused ? ICO.play : ICO.pause;
			bPlay.setAttribute( 'aria-label', t );
			bPlay.title = t;
			grande.hidden = ! video.paused;
			palco.classList.remove( 'nexop__palco--quieto' );
		}
		bPlay.addEventListener( 'click', alterna );
		grande.addEventListener( 'click', alterna );
		video.addEventListener( 'play', pintaPlay );
		video.addEventListener( 'pause', pintaPlay );
		video.addEventListener( 'click', alterna );
		video.addEventListener( 'dblclick', function () { telaCheia(); } );

		/* ---------- carregando ---------- */
		video.addEventListener( 'waiting', function () { carreg.hidden = false; grande.hidden = true; } );
		video.addEventListener( 'playing', function () { carreg.hidden = true; } );
		video.addEventListener( 'canplay', function () { carreg.hidden = true; } );

		/* ---------- progresso ---------- */
		function pinta() {
			var d = video.duration;
			if ( ! isFinite( d ) || ! d ) {
				return;
			}
			var pct = ( video.currentTime / d ) * 100;
			preenche.style.width = pct + '%';
			bolinha.style.left   = pct + '%';
			tempo.innerHTML      = fmt( video.currentTime ) + '<i>/</i>' + fmt( d );
			trilha.setAttribute( 'aria-valuenow', Math.round( video.currentTime ) );
			trilha.setAttribute( 'aria-valuemax', Math.round( d ) );
			trilha.setAttribute( 'aria-valuetext', fmt( video.currentTime ) );
			if ( video.buffered.length ) {
				var fim = video.buffered.end( video.buffered.length - 1 );
				buffer.style.width = Math.min( 100, ( fim / d ) * 100 ) + '%';
			}
		}
		video.addEventListener( 'timeupdate', pinta );
		video.addEventListener( 'progress', pinta );
		video.addEventListener( 'loadedmetadata', pinta );
		video.addEventListener( 'durationchange', pinta );

		/* ---------- arrastar na trilha ---------- */
		function posDe( e ) {
			var r = trilha.getBoundingClientRect();
			var x = ( e.touches ? e.touches[ 0 ].clientX : e.clientX ) - r.left;
			return Math.min( 1, Math.max( 0, x / r.width ) );
		}
		function buscar( e ) {
			var d = video.duration;
			if ( isFinite( d ) && d ) {
				video.currentTime = posDe( e ) * d;
				pinta();
			}
		}
		var arrastando = false;
		function inicia( e ) {
			arrastando = true;
			trilha.classList.add( 'nexop-ui__trilha--ativa' );
			buscar( e );
			e.preventDefault();
		}
		function move( e ) {
			if ( arrastando ) { buscar( e ); }
		}
		function solta() {
			arrastando = false;
			trilha.classList.remove( 'nexop-ui__trilha--ativa' );
		}
		trilha.addEventListener( 'mousedown', inicia );
		trilha.addEventListener( 'touchstart', inicia, { passive: false } );
		document.addEventListener( 'mousemove', move );
		document.addEventListener( 'touchmove', move, { passive: true } );
		document.addEventListener( 'mouseup', solta );
		document.addEventListener( 'touchend', solta );

		// Balãozinho com o tempo sob o cursor
		trilha.addEventListener( 'mousemove', function ( e ) {
			var d = video.duration;
			if ( ! isFinite( d ) || ! d ) {
				return;
			}
			var p = posDe( e );
			dica.textContent = fmt( p * d );
			dica.style.left  = ( p * 100 ) + '%';
		} );

		/* ---------- volume ---------- */
		function pintaVol() {
			var v = video.muted ? 0 : video.volume;
			volPreenche.style.width = ( v * 100 ) + '%';
			bSom.innerHTML = v ? ICO.som : ICO.mudo;
			bSom.setAttribute( 'aria-label', v ? 'Mudo' : 'Ativar som' );
			bSom.title = v ? 'Mudo' : 'Ativar som';
		}
		bSom.addEventListener( 'click', function () {
			video.muted = ! video.muted;
			pintaVol();
			mostrarAviso( video.muted ? 'Mudo' : Math.round( video.volume * 100 ) + '%' );
		} );
		function ajustaVol( e ) {
			var r = volBarra.getBoundingClientRect();
			var x = ( e.touches ? e.touches[ 0 ].clientX : e.clientX ) - r.left;
			video.volume = Math.min( 1, Math.max( 0, x / r.width ) );
			video.muted  = ( video.volume === 0 );
			pintaVol();
		}
		var volArrasta = false;
		volTrilha.addEventListener( 'mousedown', function ( e ) {
			volArrasta = true;
			volTrilha.classList.add( 'nexop-ui__vol-trilha--ativa' );
			ajustaVol( e );
			e.preventDefault();
		} );
		document.addEventListener( 'mousemove', function ( e ) { if ( volArrasta ) { ajustaVol( e ); } } );
		document.addEventListener( 'mouseup', function () {
			volArrasta = false;
			volTrilha.classList.remove( 'nexop-ui__vol-trilha--ativa' );
		} );
		video.addEventListener( 'volumechange', pintaVol );

		/* ---------- pular 10s ---------- */
		function pula( seg ) {
			video.currentTime = Math.min( video.duration || 0, Math.max( 0, video.currentTime + seg ) );
			mostrarAviso( ( seg > 0 ? '+' : '' ) + seg + 's' );
		}
		bRe.addEventListener( 'click', function () { pula( -10 ); } );
		bAv.addEventListener( 'click', function () { pula( 10 ); } );

		/* ---------- velocidade ---------- */
		bVel.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			menu.hidden = ! menu.hidden;
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( ! menu.hidden && ! menu.contains( e.target ) && e.target !== bVel ) {
				menu.hidden = true;
			}
		} );

		/* ---------- picture-in-picture ---------- */
		bPip.addEventListener( 'click', function () {
			if ( document.pictureInPictureElement ) {
				document.exitPictureInPicture().catch( function () {} );
			} else {
				video.requestPictureInPicture().catch( function () {} );
			}
		} );

		/* ---------- tela cheia ---------- */
		function emFull() {
			return document.fullscreenElement === palco || document.webkitFullscreenElement === palco;
		}
		function telaCheia() {
			if ( emFull() ) {
				var sair = document.exitFullscreen || document.webkitExitFullscreen;
				if ( sair ) { sair.call( document ); }
			} else if ( palco.requestFullscreen ) {
				palco.requestFullscreen().catch( function () {} );
			} else if ( palco.webkitRequestFullscreen ) {
				palco.webkitRequestFullscreen();
			} else if ( video.webkitEnterFullscreen ) {
				video.webkitEnterFullscreen(); // iPhone: só o vídeo vai a full.
			}
		}
		bFull.addEventListener( 'click', telaCheia );
		document.addEventListener( 'fullscreenchange', function () {
			var f = emFull();
			bFull.innerHTML = f ? ICO.sai : ICO.full;
			bFull.setAttribute( 'aria-label', f ? 'Sair da tela cheia' : 'Tela cheia' );
			bFull.title = f ? 'Sair da tela cheia' : 'Tela cheia';
		} );

		/* ---------- esconder controles parado ---------- */
		var quietoT;
		function acorda() {
			palco.classList.remove( 'nexop__palco--quieto' );
			clearTimeout( quietoT );
			if ( ! video.paused ) {
				quietoT = setTimeout( function () {
					if ( ! video.paused && menu.hidden ) {
						palco.classList.add( 'nexop__palco--quieto' );
					}
				}, 2600 );
			}
		}
		palco.addEventListener( 'mousemove', acorda );
		palco.addEventListener( 'touchstart', acorda, { passive: true } );
		palco.addEventListener( 'mouseleave', function () {
			if ( ! video.paused && menu.hidden ) {
				palco.classList.add( 'nexop__palco--quieto' );
			}
		} );

		/* ---------- teclado ---------- */
		palco.tabIndex = 0;
		palco.addEventListener( 'keydown', function ( e ) {
			var t = e.target;
			if ( t !== palco && t !== trilha && ! t.classList.contains( 'nexop-ui__btn' ) ) {
				return;
			}
			var k = e.key;
			if ( k === ' ' || k === 'k' ) { alterna(); }
			else if ( k === 'ArrowRight' ) { pula( 10 ); }
			else if ( k === 'ArrowLeft' ) { pula( -10 ); }
			else if ( k === 'ArrowUp' ) {
				video.volume = Math.min( 1, video.volume + 0.1 );
				mostrarAviso( Math.round( video.volume * 100 ) + '%' );
			} else if ( k === 'ArrowDown' ) {
				video.volume = Math.max( 0, video.volume - 0.1 );
				mostrarAviso( Math.round( video.volume * 100 ) + '%' );
			} else if ( k === 'm' ) {
				video.muted = ! video.muted;
				pintaVol();
			} else if ( k === 'f' ) { telaCheia(); }
			else if ( k >= '0' && k <= '9' && video.duration ) {
				video.currentTime = ( parseInt( k, 10 ) / 10 ) * video.duration;
			} else { return; }
			e.preventDefault();
			acorda();
		} );

		pintaPlay();
		pintaVol();
		pinta();
	}

	/* ---------------------------------------------------------------
	   Inicializa cada player da página.
	   --------------------------------------------------------------- */
	document.querySelectorAll( '[data-nexop]' ).forEach( function ( box ) {
		var video = box.querySelector( '[data-nexop-video]' );
		if ( ! video ) {
			return;
		}
		var palco = box.querySelector( '.nexop__palco' );
		if ( palco ) {
			try {
				montarUI( palco, video );
			} catch ( e ) {
				// Deu ruim: devolve os controles nativos em vez de ficar sem nada.
				video.setAttribute( 'controls', '' );
			}
		}

		if ( ! D.continuar ) {
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
