/**
 * Free Analytics - dashboard: chart tooltip (hover and keyboard focus).
 */
( function () {
	'use strict';

	document.querySelectorAll( '[data-fran-chart]' ).forEach( function ( chart ) {
		var tip = chart.querySelector( '.fran-chart__tip' );
		if ( ! tip ) {
			return;
		}

		function line( value, label, key ) {
			var row = document.createElement( 'div' );
			if ( key ) {
				var k = document.createElement( 'span' );
				k.className = 'fran-tip-key';
				row.appendChild( k );
			}
			var strong = document.createElement( 'strong' );
			strong.textContent = value;
			row.appendChild( strong );
			var span = document.createElement( 'span' );
			span.className = 'fran-tip-muted';
			span.textContent = ' ' + label;
			row.appendChild( span );
			return row;
		}

		function show( slot ) {
			tip.textContent = '';
			var title = document.createElement( 'div' );
			title.className = 'fran-tip-muted';
			title.textContent = slot.dataset.title;
			tip.appendChild( title );
			tip.appendChild( line( slot.dataset.visitors, slot.dataset.visitorsLabel, true ) );
			tip.appendChild( line( slot.dataset.pageviews, slot.dataset.pageviewsLabel, false ) );
			tip.hidden = false;

			var box = chart.getBoundingClientRect();
			var s = slot.getBoundingClientRect();
			var bar = slot.querySelector( 'i' );
			var top = bar ? bar.getBoundingClientRect().top - box.top : 0;
			var x = s.left - box.left + s.width / 2;
			var half = tip.offsetWidth / 2;
			x = Math.max( half - 44, Math.min( box.width - half, x ) );
			tip.style.left = x + 'px';
			tip.style.top = Math.max( top, tip.offsetHeight + 8 ) + 'px';
		}

		function hide() {
			tip.hidden = true;
		}

		chart.querySelectorAll( '.fran-chart__slot' ).forEach( function ( slot ) {
			slot.addEventListener( 'pointerenter', function () {
				show( slot );
			} );
			slot.addEventListener( 'focus', function () {
				show( slot );
			} );
			slot.addEventListener( 'blur', hide );
		} );
		chart.addEventListener( 'pointerleave', hide );
	} );
}() );
