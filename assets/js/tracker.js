/**
 * Free Analytics - beacon.
 *
 * Sends the page view, clicks on contact links and successful form submissions
 * (Bricks, Contact Form 7, Elementor Pro, WPForms, Gravity Forms, Fluent Forms).
 * No cookies, no localStorage, nothing is kept in the browser.
 *
 * Themes can report their own form or contact events:
 *   window.freeAnalytics.track( 'form', 'newsletter' );
 *   window.freeAnalytics.track( 'tel', 'tel:+359888123456' );
 * Events: tel, email, map, viber, whatsapp (target = the link) and form (target = a form ID).
 */
( function () {
	'use strict';

	var cfg = window.freeAnalyticsConfig;
	if ( ! cfg || ! cfg.u || navigator.webdriver ) {
		return;
	}

	function send( data ) {
		data.p = location.pathname;
		data.id = cfg.id || 0;
		var body = JSON.stringify( data );
		try {
			if ( navigator.sendBeacon && navigator.sendBeacon( cfg.u, new Blob( [ body ], { type: 'text/plain' } ) ) ) {
				return;
			}
		} catch ( e ) {}
		try {
			fetch( cfg.u, { method: 'POST', body: body, keepalive: true, credentials: 'omit', headers: { 'Content-Type': 'text/plain' } } );
		} catch ( e ) {}
	}

	function pageview() {
		var q = new URLSearchParams( location.search );
		var d = { e: 'pageview', r: ( document.referrer || '' ).slice( 0, 300 ), nf: cfg.nf ? 1 : 0 };
		[ 'source', 'medium', 'campaign' ].forEach( function ( k ) {
			var v = q.get( 'utm_' + k );
			if ( v ) {
				d[ 'u' + k.charAt( 0 ) ] = v.slice( 0, 100 );
			}
		} );
		if ( q.has( 'gclid' ) || q.has( 'gbraid' ) || q.has( 'wbraid' ) ) {
			d.ck = 'g';
		} else if ( q.has( 'fbclid' ) ) {
			d.ck = 'f';
		}
		send( d );
	}

	if ( document.prerendering ) {
		document.addEventListener( 'prerenderingchange', pageview, { once: true } );
	} else {
		pageview();
	}

	// Contact links.
	var links = [
		[ /^tel:/i, 'tel' ],
		[ /^mailto:/i, 'email' ],
		[ /^viber:/i, 'viber' ],
		[ /^(whatsapp:|https?:\/\/(wa\.me|(api|web)\.whatsapp\.com)\/)/i, 'whatsapp' ],
		[ /^https?:\/\/((www\.)?google\.[a-z]{2,3}(\.[a-z]{2})?\/maps|maps\.google\.[a-z]{2,3}(\.[a-z]{2})?\/|maps\.app\.goo\.gl\/|goo\.gl\/maps)/i, 'map' ]
	];

	function onClick( ev ) {
		if ( ev.type === 'auxclick' && ev.button !== 1 ) {
			return;
		}
		var a = ev.target && ev.target.closest ? ev.target.closest( 'a[href]' ) : null;
		if ( ! a ) {
			return;
		}
		var href = a.href || a.getAttribute( 'href' ) || '';
		for ( var i = 0; i < links.length; i++ ) {
			if ( links[ i ][ 0 ].test( href ) ) {
				send( { e: links[ i ][ 1 ], t: href.slice( 0, 300 ) } );
				return;
			}
		}
	}
	document.addEventListener( 'click', onClick, true );
	document.addEventListener( 'auxclick', onClick, true );

	// Successful form submissions.
	function form( id ) {
		send( { e: 'form', t: id ? String( id ) : '' } );
	}
	function formId( el ) {
		return el && el.getAttribute ? el.getAttribute( 'id' ) || el.getAttribute( 'name' ) || '' : '';
	}

	// Bricks.
	document.addEventListener( 'bricks/form/success', function ( ev ) {
		form( ev.detail && ev.detail.elementId );
	} );
	// Contact Form 7.
	document.addEventListener( 'wpcf7mailsent', function ( ev ) {
		form( ev.detail && ev.detail.contactFormId ? 'cf7-' + ev.detail.contactFormId : formId( ev.target ) );
	} );
	// jQuery-based form plugins: Elementor Pro, WPForms, Gravity Forms, Fluent Forms. Bound now,
	// or after the page has loaded when an optimiser defers or delays jQuery.
	var jqBound = false;
	function bindJQuery() {
		if ( jqBound || ! window.jQuery ) {
			return;
		}
		jqBound = true;
		window.jQuery( document )
			.on( 'submit_success', function ( ev ) {
				form( formId( ev.target ) );
			} )
			.on( 'wpformsAjaxSubmitSuccess', function ( ev ) {
				form( formId( ev.target ) );
			} )
			.on( 'gform_confirmation_loaded', function ( ev, id ) {
				form( 'gform-' + id );
			} )
			.on( 'fluentform_submission_success', function ( ev ) {
				form( formId( ev.target ) );
			} );
	}
	bindJQuery();
	window.addEventListener( 'load', bindJQuery );
	[ 'keydown', 'mousemove', 'touchstart', 'scroll' ].forEach( function ( type ) {
		window.addEventListener( type, bindJQuery, { once: true, passive: true } );
	} );

	// Public API for themes and other plugins.
	var allowed = [ 'tel', 'email', 'map', 'viber', 'whatsapp', 'form' ];
	window.freeAnalytics = {
		track: function ( event, target ) {
			if ( allowed.indexOf( event ) === -1 ) {
				return false;
			}
			send( { e: event, t: target ? String( target ) : '' } );
			return true;
		}
	};
}() );
