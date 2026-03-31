/**
 * Server & Site Insight v2 — Admin JavaScript
 * Features: dark mode, filter, explain toggles, copy, JSON export, print/PDF, tooltips.
 * Vanilla JS ES2015+. Zero external dependencies.
 *
 * @package Server_Site_Insight
 * @since   2.0.0
 */

/* global ssiData */

( function () {
	'use strict';

	var wrap = null;
	var isDark = false;

	// ── Toast ────────────────────────────────────────────────────────────
	function toast( msg, type, ms ) {
		type = type || 'success'; ms = ms || 2500;
		var old = document.querySelector( '.ssi-toast' );
		if ( old && old.parentNode ) { old.parentNode.removeChild( old ); }
		var t = document.createElement( 'div' );
		t.className = 'ssi-toast ssi-toast--' + type;
		t.textContent = msg;
		document.body.appendChild( t );
		requestAnimationFrame( function () { requestAnimationFrame( function () { t.classList.add( 'ssi-toast--show' ); } ); } );
		setTimeout( function () {
			t.classList.remove( 'ssi-toast--show' );
			setTimeout( function () { if ( t.parentNode ) { t.parentNode.removeChild( t ); } }, 360 );
		}, ms );
	}

	// ── Data island ──────────────────────────────────────────────────────
	function getData() {
		var el = document.getElementById( 'ssi-data-json' );
		if ( ! el ) { return null; }
		try { return JSON.parse( el.textContent || el.innerHTML ); } catch ( e ) { return null; }
	}

	// ── Text report ──────────────────────────────────────────────────────
	function buildReport( data ) {
		var sep = '='.repeat( 60 ), sub = '-'.repeat( 40 );
		var out = [ sep, '  SERVER & SITE INSIGHT \u2014 REPORT', '  ' + new Date().toLocaleString(), sep, '' ];
		[ 'wordpress', 'server', 'environment', 'performance', 'security', 'developer' ].forEach( function ( k ) {
			if ( ! data[ k ] ) { return; }
			out.push( '[ ' + k.toUpperCase() + ' ]\n' + sub );
			Object.keys( data[ k ] ).forEach( function ( f ) {
				var v = data[ k ][ f ];
				if ( Array.isArray( v ) ) { out.push( f + ' (' + v.length + '):' ); v.forEach( function ( i ) { out.push( '  \u2022 ' + i ); } ); }
				else { out.push( ( f + '              ' ).slice( 0, 22 ) + ': ' + v ); }
			} );
			out.push( '' );
		} );
		out.push( sep );
		return out.join( '\n' );
	}

	// ── Copy helpers ─────────────────────────────────────────────────────
	function doCopy( text ) {
		var ok_msg = ( ssiData && ssiData.i18n && ssiData.i18n.copied ) || 'Copied!';
		var fail_msg = ( ssiData && ssiData.i18n && ssiData.i18n.copyFailed ) || 'Copy failed.';
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( function () { toast( '\u2713 ' + ok_msg ); }, function () { fallbackCopy( text ); } );
		} else { fallbackCopy( text ); }
		function fallbackCopy( t ) {
			var ta = document.createElement( 'textarea' );
			ta.value = t; ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;';
			document.body.appendChild( ta ); ta.select();
			var success = false;
			try { success = document.execCommand( 'copy' ); } catch ( e ) { success = false; }
			document.body.removeChild( ta );
			success ? toast( '\u2713 ' + ok_msg ) : toast( fail_msg, 'error' );
		}
	}

	// ── Copy on double-click for table cells ─────────────────────────────
	function initCopyValue() {
		document.querySelectorAll( '.ssi-table td' ).forEach( function ( td ) {
			td.style.cursor = 'pointer';
			td.title = 'Double-click to copy';
			td.addEventListener( 'dblclick', function () { doCopy( td.textContent.trim() ); } );
		} );
	}

	// ── Copy Report button ───────────────────────────────────────────────
	function initCopyBtn() {
		var btn = document.getElementById( 'ssi-copy-btn' );
		if ( ! btn ) { return; }
		btn.addEventListener( 'click', function () {
			var d = getData();
			if ( d ) { doCopy( buildReport( d ) ); } else { toast( 'No data.', 'error' ); }
		} );
	}

	// ── Copy System Report button ────────────────────────────────────────
	function initCopyReportBtn() {
		var btn = document.getElementById( 'ssi-copy-report-btn' );
		if ( ! btn ) { return; }
		btn.addEventListener( 'click', function () {
			var d = getData();
			if ( ! d || ! d.developer ) { toast( 'No developer data available.', 'error' ); return; }
			var dev = d.developer;
			
			var out = [];
			out.push( '# Server & Site Insight \u2014 System Report\n' );
			
			out.push( '## DB Stats' );
			out.push( '- **DB Size**: ' + dev.database_size_mb + ' MB' );
			out.push( '- **DB Queries**: ' + dev.query_count );
			out.push( '- **Object Cache**: ' + dev.object_cache );
			out.push( '- **REST Routes**: ' + dev.rest_routes + '\n' );
			
			if ( dev.top_tables && dev.top_tables.length > 0 ) {
				out.push( '## Top ' + dev.top_tables.length + ' Tables' );
				out.push( '| Table Name | Size MB |' );
				out.push( '| --- | --- |' );
				dev.top_tables.forEach( function ( t ) {
					out.push( '| `' + t.name + '` | ' + t.size_mb + ' |' );
				} );
				out.push( '' );
			}
			
			if ( dev.php_extensions && dev.php_extensions.length > 0 ) {
				out.push( '## PHP Extensions (' + dev.php_extensions.length + ')' );
				out.push( dev.php_extensions.join( ', ' ) + '\n' );
			}
			
			doCopy( out.join( '\n' ) );
		} );
	}

	// ── JSON export ──────────────────────────────────────────────────────
	function initExportBtn() {
		var btn = document.getElementById( 'ssi-export-btn' );
		if ( ! btn ) { return; }
		btn.addEventListener( 'click', function () {
			var d = getData();
			if ( ! d ) { toast( 'No data.', 'error' ); return; }
			var now = new Date();
			function p( n ) { return ( n < 10 ? '0' : '' ) + n; }
			var fname  = 'server-site-insight-' + now.getFullYear() + '-' + p( now.getMonth() + 1 ) + '-' + p( now.getDate() ) + '.json';
			var payload = { plugin: 'Server & Site Insight', generated: now.toISOString(), data: d };
			var blob   = new Blob( [ JSON.stringify( payload, null, 2 ) ], { type: 'application/json' } );
			var url    = URL.createObjectURL( blob );
			var a      = document.createElement( 'a' );
			a.href = url; a.download = fname; a.style.display = 'none';
			document.body.appendChild( a ); a.click();
			setTimeout( function () { document.body.removeChild( a ); URL.revokeObjectURL( url ); }, 150 );
			toast( '\u2713 ' + fname );
		} );
	}

	// ── Print / PDF ──────────────────────────────────────────────────────
	function initPrintBtn() {
		var btn = document.getElementById( 'ssi-print-btn' );
		if ( ! btn ) { return; }
		btn.addEventListener( 'click', function () {
			// Expand all accordions before printing.
			var expanded = [];
			document.querySelectorAll( '.ssi-check-item__detail[hidden]' ).forEach( function ( el ) {
				el.removeAttribute( 'hidden' ); expanded.push( el );
			} );
			window.print();
			setTimeout( function () { expanded.forEach( function ( el ) { el.setAttribute( 'hidden', '' ); } ); }, 500 );
		} );
	}

	// ── Filter (All / Warning / Critical) ────────────────────────────────
	function initFilters() {
		var btns  = document.querySelectorAll( '.ssi-filter-btn' );
		var pills = document.querySelectorAll( '#ssi-health-strip .ssi-health-pill' );
		var items = document.querySelectorAll( '#ssi-checks-detail .ssi-check-item' );

		btns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				btns.forEach( function ( b ) { b.classList.remove( 'active' ); } );
				btn.classList.add( 'active' );
				var filter = btn.dataset.filter || 'all';
				function toggle( els ) {
					els.forEach( function ( el ) {
						( filter === 'all' || el.dataset.status === filter )
							? el.classList.remove( 'ssi-filtered-hide' )
							: el.classList.add( 'ssi-filtered-hide' );
					} );
				}
				toggle( pills ); toggle( items );
			} );
		} );
	}

	// ── Explain accordion ─────────────────────────────────────────────────
	function initExplain() {
		document.querySelectorAll( '.ssi-explain-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var expanded = btn.getAttribute( 'aria-expanded' ) === 'true';
				var target   = document.getElementById( btn.getAttribute( 'aria-controls' ) );
				btn.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
				if ( target ) { expanded ? target.setAttribute( 'hidden', '' ) : target.removeAttribute( 'hidden' ); }
			} );
		} );
	}

	// ── Custom tooltips ──────────────────────────────────────────────────
	function initTooltips() {
		var tip = document.createElement( 'div' );
		tip.style.cssText = 'position:fixed;z-index:99998;background:#1a1d2e;color:#fff;border-radius:6px;font-size:11px;max-width:260px;padding:6px 10px;pointer-events:none;opacity:0;transition:opacity .15s;line-height:1.5;display:none;word-wrap:break-word;';
		document.body.appendChild( tip );

		document.querySelectorAll( '[data-tooltip]' ).forEach( function ( el ) {
			el.addEventListener( 'mouseenter', function () { tip.textContent = el.dataset.tooltip; tip.style.display = 'block'; setTimeout( function () { tip.style.opacity = '1'; }, 10 ); } );
			el.addEventListener( 'mousemove',  function ( e ) { var x = e.clientX + 14, y = e.clientY + 14; if ( x + 270 > window.innerWidth ) { x = e.clientX - 270; } tip.style.left = x + 'px'; tip.style.top = y + 'px'; } );
			el.addEventListener( 'mouseleave', function () { tip.style.opacity = '0'; setTimeout( function () { tip.style.display = 'none'; }, 160 ); } );
		} );
	}

	// ── Sticky bar shadow on scroll ───────────────────────────────────────
	function initStickyBar() {
		var bar  = document.getElementById( 'ssi-sticky-bar' );
		var hero = document.querySelector( '.ssi-hero' );
		if ( ! bar || ! hero ) { return; }
		window.addEventListener( 'scroll', function () {
			bar.style.boxShadow = hero.getBoundingClientRect().bottom < 90 ? '0 4px 12px rgba(0,0,0,.12)' : '';
		}, { passive: true } );
	}

	// ── Notice dismiss via AJAX ───────────────────────────────────────────
	function initNoticesDismiss() {
		document.querySelectorAll( '.ssi-dismiss-notice' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var notice = btn.closest( '.ssi-admin-notice' );
				var fd = new FormData();
				fd.append( 'action', 'ssi_dismiss_notice' );
				fd.append( 'nonce', btn.dataset.nonce );
				fd.append( 'key', btn.dataset.key );
				fetch( ( ssiData && ssiData.ajaxUrl ) || '', { method: 'POST', body: fd, credentials: 'same-origin' } );
				if ( notice ) { notice.style.display = 'none'; }
			} );
		} );
	}

	// ── Tab navigation ────────────────────────────────────────────────────
	function initTabs() {
		var nav   = document.querySelector( '.ssi-tab-nav' );
		if ( ! nav ) { return; }
		var tabs   = Array.prototype.slice.call( nav.querySelectorAll( '.ssi-tab-btn' ) );
		var stored = localStorage.getItem( 'ssi_active_tab' );

		function activate( tab ) {
			tabs.forEach( function ( t ) {
				var panel = document.getElementById( t.getAttribute( 'aria-controls' ) );
				var isActive = t === tab;
				t.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				t.classList.toggle( 'ssi-tab-btn--active', isActive );
				if ( panel ) {
					if ( isActive ) {
						panel.removeAttribute( 'hidden' );
					} else {
						panel.setAttribute( 'hidden', '' );
					}
				}
			} );
			localStorage.setItem( 'ssi_active_tab', tab.id );
		}

		// Restore last active tab.
		if ( stored ) {
			var saved = nav.querySelector( '#' + stored );
			if ( saved ) { activate( saved ); }
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () { activate( tab ); } );

			// Arrow-key keyboard navigation.
			tab.addEventListener( 'keydown', function ( e ) {
				var idx = tabs.indexOf( tab );
				if ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) {
					e.preventDefault();
					tabs[ ( idx + 1 ) % tabs.length ].focus();
					activate( tabs[ ( idx + 1 ) % tabs.length ] );
				} else if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) {
					e.preventDefault();
					tabs[ ( idx - 1 + tabs.length ) % tabs.length ].focus();
					activate( tabs[ ( idx - 1 + tabs.length ) % tabs.length ] );
				} else if ( e.key === 'Home' ) {
					e.preventDefault(); tabs[ 0 ].focus(); activate( tabs[ 0 ] );
				} else if ( e.key === 'End' ) {
					e.preventDefault(); tabs[ tabs.length - 1 ].focus(); activate( tabs[ tabs.length - 1 ] );
				}
			} );
		} );
	}

	// ── Debug Log Viewer ───────────────────────────────────────────────────

	/** Safe HTML entity encoder for log line display (not using innerHTML directly). */
	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function classifyLogLine( line ) {
		if ( /PHP Fatal error/i.test( line ) )  { return 'fatal'; }
		if ( /PHP Parse error/i.test( line ) )  { return 'parse'; }
		if ( /PHP Warning/i.test( line ) )       { return 'warning'; }
		if ( /PHP Notice/i.test( line ) )        { return 'notice'; }
		if ( /PHP Deprecated/i.test( line ) )    { return 'deprecated'; }
		return 'other';
	}

	function renderLogLines( lines, filterType ) {
		var code        = document.getElementById( 'ssi-log-code' );
		var countEl     = document.getElementById( 'ssi-log-entry-count' );
		if ( ! code ) { return; }

		var filtered = filterType === 'all'
			? lines
			: lines.filter( function ( l ) {
				return classifyLogLine( l ) === filterType;
			} );

		if ( filtered.length === 0 ) {
			code.innerHTML = '<em class="ssi-log-empty">No ' + escHtml( filterType ) + ' entries found.</em>';
		} else {
			code.innerHTML = filtered.map( function ( line ) {
				var cls = 'ssi-log-line ssi-log-line--' + classifyLogLine( line );
				return '<span class="' + cls + '">' + escHtml( line ) + '</span>';
			} ).join( '\n' );
		}

		if ( countEl ) {
			countEl.textContent = filtered.length + ( filtered.length === 1 ? ' entry' : ' entries' );
		}
	}

	function updateFilterCounts( lines ) {
		var counts = { all: lines.length, warning: 0, fatal: 0, notice: 0, deprecated: 0 };
		lines.forEach( function ( l ) {
			var t = classifyLogLine( l );
			if ( counts[ t ] !== undefined ) { counts[ t ]++; }
		} );
		Object.keys( counts ).forEach( function ( key ) {
			var el = document.getElementById( 'ssi-count-' + key );
			if ( el ) { el.textContent = counts[ key ] > 0 ? counts[ key ] : ''; }
		} );
	}

	function initLogsTabs() {
		var nav = document.querySelector( '.ssi-logs-nav' );
		if ( ! nav ) return;

		var btns  = nav.querySelectorAll( '.ssi-logs-nav__btn' );
		var panes = document.querySelectorAll( '.ssi-log-view-pane' );

		btns.forEach( function( btn ) {
			btn.addEventListener( 'click', function() {
				var targetId = 'ssi-log-view-' + this.getAttribute( 'data-show' );

				// Reset nav
				btns.forEach( function( b ) { b.classList.remove( 'ssi-logs-nav__btn--active' ); } );
				this.classList.add( 'ssi-logs-nav__btn--active' );

				// Reset panes
				panes.forEach( function( p ) { p.style.display = 'none'; } );
				var target = document.getElementById( targetId );
				if ( target ) { target.style.display = 'block'; }
			});
		});
	}

	function initDebugLog() {
		var nonce      = ( ssiData && ssiData.toolsNonce ) || '';
		var allLines   = [];
		var activeType = 'all';

		var viewBtn    = document.getElementById( 'ssi-view-log' );
		var clearBtn   = document.getElementById( 'ssi-clear-log' );
		var filtersEl  = document.getElementById( 'ssi-log-filters' );
		var outputEl   = document.getElementById( 'ssi-log-output' );
		var viewLabel  = viewBtn && viewBtn.querySelector( '.ssi-view-log-label' );

		// ── View / Refresh log ────────────────────────────────────────────
		if ( viewBtn ) {
			viewBtn.addEventListener( 'click', function () {
				viewBtn.disabled = true;
				var fd = new FormData();
				fd.append( 'action', 'ssi_log_view' );
				fd.append( 'nonce', nonce );

				fetch( ( ssiData && ssiData.ajaxUrl ) || '', { method: 'POST', body: fd, credentials: 'same-origin' } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( json ) {
						viewBtn.disabled = false;
						if ( json.success ) {
							allLines = json.data.lines || [];
							updateFilterCounts( allLines );
							renderLogLines( allLines, activeType );

							// Reveal UI areas.
							if ( filtersEl ) { filtersEl.removeAttribute( 'hidden' ); }
							if ( outputEl )  { outputEl.removeAttribute( 'hidden' ); }

							// Change label to "Refresh" after first successful load.
							if ( viewLabel ) { viewLabel.textContent = 'Refresh'; }
						} else {
							var msg = ( json.data && json.data.message ) ? json.data.message : 'Error loading log.';
							toast( '\u2717 ' + msg, 'error' );
						}
					} )
					.catch( function () {
						viewBtn.disabled = false;
						toast( '\u2717 Network error loading log.', 'error' );
					} );
			} );
		}

		// ── Filter pills ──────────────────────────────────────────────────
		if ( filtersEl ) {
			filtersEl.querySelectorAll( '.ssi-log-filter' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					filtersEl.querySelectorAll( '.ssi-log-filter' ).forEach( function ( b ) {
						b.classList.remove( 'ssi-log-filter--active' );
					} );
					btn.classList.add( 'ssi-log-filter--active' );
					activeType = btn.dataset.type || 'all';
					renderLogLines( allLines, activeType );
				} );
			} );
		}

		// ── Clear log ─────────────────────────────────────────────────────
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				var msg = clearBtn.dataset.confirm || 'Clear debug log?';
				// eslint-disable-next-line no-alert
				if ( ! window.confirm( msg ) ) { return; }
				clearBtn.disabled = true;

				var fd = new FormData();
				fd.append( 'action', 'ssi_log_clear' );
				fd.append( 'nonce', nonce );

				fetch( ( ssiData && ssiData.ajaxUrl ) || '', { method: 'POST', body: fd, credentials: 'same-origin' } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( json ) {
						clearBtn.disabled = true; // stay disabled — file is empty now
						if ( json.success ) {
							allLines = [];
							updateFilterCounts( [] );
							renderLogLines( [], activeType );
							toast( '\u2713 ' + json.data.message );
						} else {
							clearBtn.disabled = false;
							toast( '\u2717 ' + ( ( json.data && json.data.message ) || 'Error' ), 'error' );
						}
					} )
					.catch( function () {
						clearBtn.disabled = false;
						toast( '\u2717 Network error.', 'error' );
					} );
			} );
		}
	}

	// ── Tools tab ──────────────────────────────────────────────────────────
	function sendToolAction( data, onSuccess, onError ) {
		var fd = new FormData();
		fd.append( 'action', 'ssi_tool_action' );
		Object.keys( data ).forEach( function ( k ) { fd.append( k, data[ k ] ); } );
		fetch( ( ssiData && ssiData.ajaxUrl ) || '', { method: 'POST', body: fd, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( json.success ) {
					if ( onSuccess ) { onSuccess( json.data ); }
				} else {
					var msg = ( json.data && json.data.message ) ? json.data.message : 'Error';
					toast( '\u2717 ' + msg, 'error' );
					if ( onError ) { onError( msg ); }
				}
			} )
			.catch( function ( e ) {
				toast( '\u2717 Network error', 'error' );
				if ( onError ) { onError( e.message ); }
			} );
	}

	function showConfigModal( snippet ) {
		var modal = document.getElementById( 'ssi-config-modal' );
		if ( ! modal ) { return; }
		var pre = modal.querySelector( '.ssi-modal__snippet' );
		if ( pre ) { pre.textContent = snippet; }
		modal.removeAttribute( 'hidden' );
		var closeBtn = modal.querySelector( '.ssi-modal__close' );
		if ( closeBtn ) { closeBtn.focus(); }
	}

	function initTools() {
		var nonce = ( ssiData && ssiData.toolsNonce ) || '';

		// ── Production Mode toggle ────────────────────────────────────────
		var prodToggle = document.getElementById( 'ssi-toggle-production-mode' );
		if ( prodToggle ) {
			prodToggle.addEventListener( 'change', function () {
				var enabled = prodToggle.checked;
				prodToggle.disabled = true;
				sendToolAction(
					{ nonce: nonce, tool: 'toggle_production_mode', enable: enabled ? '1' : '0' },
					function ( data ) {
						prodToggle.disabled = false;
						if ( data.requires_manual ) {
							prodToggle.checked = ! enabled;
							showConfigModal( data.snippet || '' );
						} else {
							toast( '\u2713 ' + data.message );
							if ( data.reload ) {
								setTimeout( function () { window.location.reload(); }, 1200 );
							}
						}
					},
					function () {
						prodToggle.disabled = false;
						prodToggle.checked = ! enabled;
					}
				);
			} );
		}

		// ── Config constant toggles ────────────────────────────────────────
		document.querySelectorAll( '.ssi-config-toggle' ).forEach( function ( toggle ) {
			toggle.addEventListener( 'change', function () {
				var constant = toggle.dataset.constant || '';
				var enabled  = toggle.checked;
				var confirm_msg = toggle.dataset.confirm || '';

				// Confirmation for potentially dangerous toggles.
				if ( confirm_msg && enabled ) {
					// eslint-disable-next-line no-alert
					if ( ! window.confirm( confirm_msg ) ) {
						toggle.checked = ! enabled;
						return;
					}
				}

				// Optimistic UI disable to prevent double-clicks.
				toggle.disabled = true;

				sendToolAction(
					{
						nonce:    nonce,
						tool:     'toggle_config_constant',
						constant: constant,
						value:    enabled ? '1' : '0',
					},
					function ( data ) {
						toggle.disabled = false;
						if ( data.requires_manual ) {
							// Revert toggle — file was not changed.
							toggle.checked = ! enabled;
							showConfigModal( data.snippet || '' );
						} else {
							toast( '\u2713 ' + data.message );
							// PHP constants can't change mid-request; reload so
							// Overview / Health Check tabs reflect the new value.
							if ( data.reload ) {
								setTimeout( function () { window.location.reload(); }, 1200 );
							}
						}
					},
					function () {
						toggle.disabled = false;
						toggle.checked  = ! enabled; // revert on error
					}
				);
			} );
		} );

		// ── XML-RPC toggle ────────────────────────────────────────────────
		var xmlrpcToggle = document.getElementById( 'ssi-toggle-xmlrpc' );
		if ( xmlrpcToggle ) {
			xmlrpcToggle.addEventListener( 'change', function () {
				var disabled = xmlrpcToggle.checked;
				xmlrpcToggle.disabled = true;
				sendToolAction(
					{ nonce: nonce, tool: 'toggle_xmlrpc', disabled: disabled ? '1' : '0' },
					function ( data ) { xmlrpcToggle.disabled = false; toast( '\u2713 ' + data.message ); },
					function () { xmlrpcToggle.disabled = false; xmlrpcToggle.checked = ! disabled; }
				);
			} );
		}

		// ── Clear transients button ───────────────────────────────────────
		var clearBtn = document.getElementById( 'ssi-clear-transients' );
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				var msg = ( ssiData && ssiData.i18n && ssiData.i18n.confirmClearTransients ) || 'Clear all transients?';
				// eslint-disable-next-line no-alert
				if ( ! window.confirm( msg ) ) { return; }
				clearBtn.disabled = true;
				sendToolAction(
					{ nonce: nonce, tool: 'clear_transients' },
					function ( data ) { clearBtn.disabled = false; toast( '\u2713 ' + data.message ); },
					function () { clearBtn.disabled = false; }
				);
			} );
		}

		// ── Flush rewrites button ─────────────────────────────────────────
		var flushBtn = document.getElementById( 'ssi-flush-rewrites' );
		if ( flushBtn ) {
			flushBtn.addEventListener( 'click', function () {
				var msg = ( ssiData && ssiData.i18n && ssiData.i18n.confirmFlushRewrites ) || 'Flush rewrite rules?';
				// eslint-disable-next-line no-alert
				if ( ! window.confirm( msg ) ) { return; }
				flushBtn.disabled = true;
				sendToolAction(
					{ nonce: nonce, tool: 'flush_rewrites' },
					function ( data ) { flushBtn.disabled = false; toast( '\u2713 ' + data.message ); },
					function () { flushBtn.disabled = false; }
				);
			} );
		}

		// ── Config modal ──────────────────────────────────────────────────
		var modal = document.getElementById( 'ssi-config-modal' );
		if ( modal ) {
			// Copy snippet button.
			var copyBtn = document.getElementById( 'ssi-modal-copy' );
			if ( copyBtn ) {
				copyBtn.addEventListener( 'click', function () {
					var pre = modal.querySelector( '.ssi-modal__snippet' );
					if ( pre ) { doCopy( pre.textContent.trim() ); }
				} );
			}
			// Close on backdrop click or close buttons.
			modal.querySelectorAll( '.ssi-modal__close, .ssi-modal__backdrop' ).forEach( function ( el ) {
				el.addEventListener( 'click', function () { modal.setAttribute( 'hidden', '' ); } );
			} );
			// Close on Escape.
			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' && ! modal.hasAttribute( 'hidden' ) ) {
					modal.setAttribute( 'hidden', '' );
				}
			} );
		}
	}

	// ── Fix Now Buttons ───────────────────────────────────────────────────
	function initFixNowButtons() {
		document.querySelectorAll( '.ssi-fix-btn[data-tool]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var item = btn.closest( '.ssi-check-item' );
				var tool = btn.dataset.tool;
				var data = { nonce: btn.dataset.nonce, tool: tool };
				
				if ( btn.dataset.constant ) { data.constant = btn.dataset.constant; }
				if ( btn.dataset.value )    { data.value = btn.dataset.value; }
				if ( btn.dataset.disabled ) { data.disabled = btn.dataset.disabled; }
				
				btn.disabled = true;
				if ( item ) { item.classList.add( 'ssi-check-item--fixing' ); }
				
				sendToolAction(
					data,
					function ( res ) {
						if ( res.requires_manual ) {
							btn.disabled = false;
							if ( item ) { item.classList.remove( 'ssi-check-item--fixing' ); }
							showConfigModal( res.snippet || '' );
						} else {
							toast( '\u2713 Success. Reloading...' );
							setTimeout( function () { window.location.reload(); }, 1200 );
						}
					},
					function () {
						btn.disabled = false;
						if ( item ) { item.classList.remove( 'ssi-check-item--fixing' ); }
					}
				);
			} );
		} );
	}

	// ── Smart Status Bar: pill navigation + overflow toggle ───────────────
	function initSmartPills() {
		var strip      = document.getElementById( 'ssi-health-strip' );
		var moreBtn    = document.getElementById( 'ssi-pill-more-btn' );
		var overflowEl = document.getElementById( 'ssi-pill-overflow' );

		if ( ! strip ) { return; }

		// ── +X more toggle ────────────────────────────────────────────────
		if ( moreBtn && overflowEl ) {
			moreBtn.addEventListener( 'click', function () {
				var expanded = moreBtn.getAttribute( 'aria-expanded' ) === 'true';
				if ( expanded ) {
					overflowEl.setAttribute( 'hidden', '' );
					moreBtn.setAttribute( 'aria-expanded', 'false' );
				} else {
					overflowEl.removeAttribute( 'hidden' );
					moreBtn.setAttribute( 'aria-expanded', 'true' );
				}
			} );
		}

		// ── Pill click: activate tab + scroll to card ─────────────────────
		strip.querySelectorAll( '.ssi-health-pill[data-tab]' ).forEach( function ( pill ) {
			pill.addEventListener( 'click', function ( e ) {
				// Ignore clicks on the "Fix" link inside the pill.
				if ( e.target && e.target.closest( '.ssi-pill-fix' ) ) { return; }

				var targetTabId = pill.dataset.tab;
				var targetCardId = pill.dataset.card;

				// Switch to the tab.
				if ( targetTabId ) {
					var tabBtn = document.querySelector( '[aria-controls="' + targetTabId + '"]' );
					if ( tabBtn && typeof tabBtn.click === 'function' ) {
						tabBtn.click();
					}
				}

				// After tab switches, scroll the target card into view.
				if ( targetCardId ) {
					setTimeout( function () {
						var card = document.getElementById( targetCardId );
						if ( ! card ) {
							// Try by heading id (e.g. ssi-ttl-debug is inside a card).
							var heading = document.getElementById( targetCardId );
							if ( heading ) { card = heading.closest( '.ssi-card' ) || heading; }
						}
						if ( card ) {
							card.scrollIntoView( { behavior: 'smooth', block: 'start' } );
							// Briefly highlight the card.
							card.classList.add( 'ssi-card--highlight' );
							setTimeout( function () { card.classList.remove( 'ssi-card--highlight' ); }, 1400 );
						}
					}, 80 ); // brief delay lets the tab panel become visible first
				}
			} );

			// Keyboard: Enter / Space activate the pill.
			pill.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					pill.click();
				}
			} );
		} );

		// Keyboard on non-navigable pills (spans).
		strip.querySelectorAll( '.ssi-health-pill:not([data-tab])' ).forEach( function ( pill ) {
			pill.setAttribute( 'tabindex', '0' );
		} );
	}

	// ── Developer Tab: Lazy Load ──────────────────────────────────────────
	function initDevLazyLoad() {
		var dash = document.getElementById('ssi-dev-dashboard');
		if (!dash) return;
		var nonce = dash.dataset.nonce;
		var ajaxUrl = window.ajaxurl || (ssiData && ssiData.ajaxUrl);

		document.querySelectorAll('.ssi-lazy-panel').forEach(function(panel) {
			panel.addEventListener('toggle', function(e) {
				if (!panel.open || panel.dataset.loaded) return;
				panel.dataset.loaded = 'true';
				var action = panel.dataset.action;
				var content = panel.querySelector('.ssi-lazy-content');
				if (!action || !content) return;
				
				var fd = new FormData();
				fd.append('action', action);
				fd.append('nonce', nonce);
				
				fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function(r) { return r.json(); })
					.then(function(res) {
						if (!res.success) { content.innerHTML = '<p class="ssi-text-muted" style="padding:10px 20px;">Failed to load data.</p>'; return; }
						renderDevLazyContent(action, res.data, content);
					})
					.catch(function() {
						content.innerHTML = '<p class="ssi-text-muted" style="padding:10px 20px;">Network error.</p>';
					});
			});
		});
	}

	function renderDevLazyContent(action, data, el) {
		var html = '';
		if (action === 'ssi_lazy_queries') {
			var warnMsg = '';
			if (!data.savequeries) {
				warnMsg = '<div class="ssi-admin-notice ssi-admin-notice--warning" style="margin:10px 20px;"><span class="dashicons dashicons-warning"></span> <strong>SAVEQUERIES is disabled.</strong> Define SAVEQUERIES as true in wp-config.php to capture query execution times and find slow queries.</div>';
			}
			
			html += warnMsg;
			html += '<table class="ssi-table"><tbody>';
			html += '<tr><th>Total Queries</th><td><strong>' + data.count + '</strong></td></tr>';
			if (data.savequeries) {
				html += '<tr><th>Total Time</th><td>' + data.total_ms + ' ms</td></tr>';
			}
			html += '</tbody></table>';
			
			if (data.savequeries && data.top_slow && data.top_slow.length > 0) {
				html += '<h4 class="ssi-subheading" style="margin:20px 0 10px;font-size:11px;text-transform:uppercase;color:var(--ssi-text-muted);font-weight:700;letter-spacing:0.5px;">Top 5 Slowest Queries</h4>';
				html += '<div style="max-height:300px;overflow-y:auto;border-top:1px solid var(--ssi-border);">';
				data.top_slow.forEach(function(q) {
					var hl = q.time > 50 ? 'color:var(--ssi-warn-text);background:var(--ssi-warn-bg);padding:2px 6px;border-radius:4px;' : 'color:var(--ssi-text-muted);';
					html += '<div style="padding:10px 20px;border-bottom:1px solid var(--ssi-border);">';
					html += '<div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-weight:600;font-size:11px;">Query Time: <span style="' + hl + '">' + q.time + ' ms</span></span></div>';
					html += '<pre style="margin:0;font-size:11px;background:var(--ssi-surface-2);padding:8px;border-radius:4px;white-space:pre-wrap;word-break:break-all;">' + escHtml(q.sql) + '</pre>';
					html += '</div>';
				});
				html += '</div>';
			}
			if (data.persistent_log && data.persistent_log.length > 0) {
				html += '<h4 class="ssi-subheading" style="margin:20px 0 10px;font-size:11px;text-transform:uppercase;color:var(--ssi-text-muted);font-weight:700;letter-spacing:0.5px;">Slow Query Log (Global 24h) <span class="ssi-badge ssi-badge--warning" style="margin-left:4px;">Persistent</span></h4>';
				html += '<div style="max-height:300px;overflow-y:auto;border-top:1px solid var(--ssi-border);background:var(--ssi-surface-2);">';
				data.persistent_log.forEach(function(q) {
					var hl = q.time > 200 ? 'color:var(--ssi-warn-text);background:var(--ssi-warn-bg);padding:2px 6px;border-radius:4px;' : 'color:var(--ssi-text-muted);';
					html += '<div style="padding:10px 20px;border-bottom:1px solid var(--ssi-border);">';
					html += '<div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-weight:600;font-size:11px;">Exceeded 200ms threshold: <span style="' + hl + '">' + q.time + ' ms</span></span><span style="font-size:10px;color:var(--ssi-text-muted);">' + escHtml(q.date) + '</span></div>';
					html += '<pre style="margin:0;font-size:11px;background:#fff;border:1px solid var(--ssi-border);padding:8px;border-radius:4px;white-space:pre-wrap;word-break:break-all;">' + escHtml(q.sql) + '</pre>';
					html += '</div>';
				});
				html += '</div>';
			}
		} 
		else if (action === 'ssi_lazy_hooks') {
			html += '<table class="ssi-table"><tbody>';
			html += '<tr><th>Registered Callbacks</th><td>' + data.registered + '</td></tr>';
			html += '<tr><th>Total Hook Fires</th><td>' + data.total_fired + '</td></tr>';
			html += '</tbody></table>';
			
			if (data.top_hooks && data.top_hooks.length > 0) {
				html += '<h4 class="ssi-subheading" style="margin:20px 0 10px;font-size:11px;text-transform:uppercase;color:var(--ssi-text-muted);font-weight:700;letter-spacing:0.5px;">Top Hooks by Usage</h4>';
				html += '<table class="ssi-table ssi-table--condensed" style="width:100%;border-collapse:collapse;"><thead><tr><th style="padding-bottom:8px;text-align:left;border:none;"><span class="ssi-dev-badge">Hook</span></th><th style="padding-bottom:8px;text-align:right;border:none;"><span class="ssi-dev-badge">Count</span></th></tr></thead><tbody>';
				data.top_hooks.forEach(function(h) {
					html += '<tr><td style="font-family:monospace;font-size:12px;padding:6px 0;border-top:1px solid var(--ssi-border);word-break:break-all;">' + escHtml(h.name) + '</td>';
					html += '<td style="text-align:right;font-size:12px;padding:6px 0;border-top:1px solid var(--ssi-border);">' + h.count + '</td></tr>';
				});
				html += '</tbody></table>';
			}
		}
		else if (action === 'ssi_lazy_scripts') {
			html += '<table class="ssi-table"><tbody>';
			html += '<tr><th>Enqueued Scripts</th><td>' + data.scripts_count + '</td></tr>';
			html += '<tr><th>Enqueued Styles</th><td>' + data.styles_count + '</td></tr>';
			html += '</tbody></table>';
			
			function renderAssets(title, arr) {
				if (!arr || arr.length === 0) return '';
				var out = '<h4 class="ssi-subheading" style="margin:20px 0 10px;font-size:11px;text-transform:uppercase;color:var(--ssi-text-muted);font-weight:700;letter-spacing:0.5px;">' + title + '</h4>';
				out += '<div style="max-height:250px;overflow-y:auto;overflow-x:hidden;padding-right:10px;">';
				arr.forEach(function(a) {
					out += '<div style="padding:10px 0;border-bottom:1px solid var(--ssi-border);display:flex;flex-direction:column;gap:6px;">';
					out += '<div style="display:flex;justify-content:space-between;align-items:center;">';
					out += '<strong style="font-family:monospace;font-size:12px;color:var(--ssi-text);">' + escHtml(a.handle) + '</strong> ';
					out += '<span class="ssi-badge" style="font-size:9px;color:var(--ssi-text-muted);">v' + (a.ver ? escHtml(a.ver) : 'none') + '</span>';
					out += '</div>';
					out += '<code style="font-size:10px;background:var(--ssi-surface-2);padding:4px 6px;color:var(--ssi-text);word-break:break-all;border-radius:3px;">' + escHtml(a.src) + '</code>';
					if (a.deps) out += '<span style="font-size:10px;color:var(--ssi-text-muted);">Requires: <span style="font-family:monospace;">' + escHtml(a.deps) + '</span></span>';
					out += '</div>';
				});
				out += '</div>';
				return out;
			}
			
			html += renderAssets('Scripts (JS)', data.scripts);
			html += renderAssets('Styles (CSS)', data.styles);
		}
		else if (action === 'ssi_lazy_plugins') {
			if (!data.plugins || data.plugins.length === 0) {
				html += '<p style="padding:12px;margin:0;font-size:13px;color:var(--ssi-text-muted);">No hooks mapped to plugins.</p>';
			} else {
				html += '<table class="ssi-table ssi-table--condensed" style="width:100%;border-collapse:collapse;"><thead><tr><th style="padding-bottom:8px;text-align:left;border:none;"><span class="ssi-dev-badge">Plugin Handle</span></th><th style="padding-bottom:8px;text-align:right;border:none;"><span class="ssi-dev-badge">Recorded Hooks</span></th></tr></thead><tbody>';
				data.plugins.forEach(function(p) {
					html += '<tr><td style="font-family:monospace;font-size:12px;padding:6px 0;border-top:1px solid var(--ssi-border);">' + escHtml(p.slug) + '</td>';
					html += '<td style="text-align:right;font-size:12px;padding:6px 0;border-top:1px solid var(--ssi-border);"><span class="ssi-badge">' + p.count + '</span></td></tr>';
				});
				html += '</tbody></table>';
			}
		}
		else if (action === 'ssi_lazy_plugin_impact') {
			if (!data.savequeries) {
				html += '<div class="ssi-admin-notice ssi-admin-notice--warning" style="margin:10px 20px;"><span class="dashicons dashicons-warning"></span> <strong>Query Metrics Unavailable:</strong> Define <code>SAVEQUERIES</code> as true in wp-config.php so the analyzer can track database queries. Hooks and Assets are still estimated.</div>';
			}
			if (!data.impact || data.impact.length === 0) {
				html += '<p style="padding:12px;margin:0;font-size:13px;color:var(--ssi-text-muted);">No plugin footprint detected.</p>';
			} else {
				html += '<div style="overflow-x:auto;">';
				html += '<table class="ssi-table ssi-table--condensed" style="width:100%;border-collapse:collapse;min-width:400px;"><thead><tr>';
				html += '<th style="padding-bottom:8px;text-align:left;border:none;"><span class="ssi-dev-badge">Plugin</span></th>';
				html += '<th style="padding-bottom:8px;text-align:left;border:none;" title="Database Queries Executed"><span class="dashicons dashicons-database" style="color:var(--ssi-text-muted);font-size:14px;"></span></th>';
				html += '<th style="padding-bottom:8px;text-align:left;border:none;" title="Query Execution Time"><span class="dashicons dashicons-performance" style="color:var(--ssi-text-muted);font-size:14px;"></span></th>';
				html += '<th style="padding-bottom:8px;text-align:left;border:none;" title="Frontend Assets (JS/CSS)"><span class="dashicons dashicons-media-code" style="color:var(--ssi-text-muted);font-size:14px;"></span></th>';
				html += '<th style="padding-bottom:8px;text-align:left;border:none;" title="Hooks Callbacks Injected"><span class="dashicons dashicons-admin-links" style="color:var(--ssi-text-muted);font-size:14px;"></span></th>';
				html += '<th style="padding-bottom:8px;text-align:left;border:none;"><span class="ssi-dev-badge">Score</span></th>';
				html += '</tr></thead><tbody>';
				data.impact.forEach(function(p) {
					var rowStyle = 'font-size:12px;padding:8px 6px;border-top:1px solid var(--ssi-border);white-space:nowrap;text-align:left;';
					var gradeBadge = '';
					if (p.grade === 'high') { gradeBadge = '<span class="ssi-badge" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;">🔥 ' + p.score + '</span>'; }
					else if (p.grade === 'medium') { gradeBadge = '<span class="ssi-badge" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;">' + p.score + '</span>'; }
					else { gradeBadge = '<span class="ssi-badge" style="background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;">' + p.score + '</span>'; }

					html += '<tr>';
					html += '<td style="' + rowStyle + ' font-family:monospace;font-weight:600;color:var(--ssi-text);">' + escHtml(p.slug) + '</td>';
					html += '<td style="' + rowStyle + ' color:var(--ssi-text-muted);">' + (p.queries > 0 ? p.queries : '-') + '</td>';
					html += '<td style="' + rowStyle + ' color:' + (p.query_ms > 20 ? 'var(--ssi-warn-text)' : 'var(--ssi-text-muted)') + ';">' + (p.query_ms > 0 ? p.query_ms + 'ms' : '-') + '</td>';
					html += '<td style="' + rowStyle + ' color:var(--ssi-text-muted);">' + (p.assets > 0 ? p.assets : '-') + '</td>';
					html += '<td style="' + rowStyle + ' color:var(--ssi-text-muted);">' + (p.hooks > 0 ? p.hooks : '-') + '</td>';
					html += '<td style="' + rowStyle + '">' + gradeBadge + '</td>';
					html += '</tr>';
				});
				html += '</tbody></table></div>';
			}
		}
		else if (action === 'ssi_lazy_activity_log') {
			if (!data.logs || data.logs.length === 0) {
				html += '<p style="padding:12px;margin:0;font-size:13px;color:var(--ssi-text-muted);">No activity recorded yet.</p>';
			} else {
				html += '<div class="ssi-api-panel__list" style="max-height:400px;overflow-y:auto;padding-right:8px;">';
				data.logs.forEach(function(log) {
					html += '<div class="ssi-endpoint" style="display:flex;flex-direction:column;gap:2px;border-bottom:1px solid var(--ssi-border);padding:10px 0;align-items:flex-start;text-align:left;">';
					html += '<div style="display:flex;flex-direction:column;align-items:flex-start;gap:2px;">';
					html += '<strong style="font-size:13px;color:var(--ssi-text);">' + escHtml(log.action) + '</strong>';
					html += '<span style="font-size:10px;color:var(--ssi-text-muted);">' + escHtml(log.time) + '</span>';
					html += '</div>';
					if (log.details) {
						html += '<div style="font-size:12px;color:var(--ssi-text-muted);margin:4px 0;">' + escHtml(log.details) + '</div>';
					}
					html += '<div style="font-size:11px;color:var(--ssi-primary);opacity:0.8;">User: ' + escHtml(log.user) + '</div>';
					html += '</div>';
				});
				html += '</div>';
			}
		}
		el.innerHTML = html;
	}

	// ── Developer Tab: Interactions ───────────────────────────────────────
	function initDevInteractions() {
		var restInput = document.getElementById('ssi-rest-filter');
		if (restInput) {
			restInput.addEventListener('input', function() {
				var term = this.value.toLowerCase();
				document.querySelectorAll('#ssi-rest-list .ssi-endpoint').forEach(function(row) {
					var url = row.querySelector('.ssi-endpoint__url').textContent.toLowerCase();
					row.style.display = url.indexOf(term) > -1 ? 'flex' : 'none';
				});
			});
		}
		
		document.querySelectorAll('.ssi-copy-route-btn').forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				doCopy(btn.dataset.clipboard);
			});
		});
		
		var dash = document.getElementById('ssi-dev-dashboard');
		var nonce = dash ? dash.dataset.nonce : '';
		var ajaxUrl = window.ajaxurl || (window.ssiData && window.ssiData.ajaxUrl);

		// Clear Transients
		var clrTransBtn = document.getElementById('ssi-clear-transients-btn');
		if (clrTransBtn) {
			clrTransBtn.addEventListener('click', function(e) {
				e.preventDefault();
				this.disabled = true;
				this.textContent = 'Clearing...';
				var fd = new FormData();
				fd.append('action', 'ssi_clear_transients');
				fd.append('nonce', nonce);
				fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function(r) { return r.json(); })
					.then(function() { window.location.reload(); });
			});
		}

		// Run Cron Task
		document.querySelectorAll('.ssi-run-cron-btn').forEach(function(b) {
			b.addEventListener('click', function(e) {
				e.preventDefault();
				var hook = this.dataset.hook;
				if (!hook) return;
				this.disabled = true;
				this.textContent = 'Running...';
				var fd = new FormData();
				fd.append('action', 'ssi_run_cron_task');
				fd.append('nonce', nonce);
				fd.append('hook', hook);
				fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function(r) { return r.json(); })
					.then(function() { window.location.reload(); });
			});
		});

		// Verify Core
		var verCoreBtn = document.getElementById('ssi-verify-core-btn');
		var verCoreRes = document.getElementById('ssi-core-results');
		if (verCoreBtn && verCoreRes) {
			verCoreBtn.addEventListener('click', function(e) {
				e.preventDefault();
				this.disabled = true;
				verCoreRes.style.display = 'block';
				verCoreRes.innerHTML = '<div class="ssi-loader" style="color:var(--ssi-text-muted);"><span class="dashicons dashicons-update dashicons-update-spin"></span> Fetching API & hashing files...</div>';
				
				var fd = new FormData();
				fd.append('action', 'ssi_verify_core_checksums');
				fd.append('nonce', nonce);
				fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function(r) { return r.json(); })
					.then(function(res) {
						if (!res.success) {
							verCoreRes.innerHTML = '<div class="ssi-admin-notice ssi-admin-notice--error">' + (res.data.message || 'Error occurred') + '</div>';
							verCoreBtn.disabled = false;
							return;
						}
						var data = res.data;
						if (data.clean) {
							verCoreRes.innerHTML = '<div class="ssi-admin-notice ssi-admin-notice--success"><span class="dashicons dashicons-yes-alt" style="margin-right:6px;"></span> ' + escHtml(data.message) + ' All files passed MD5 verification.</div>';
						} else {
							var errHtml = '<div class="ssi-admin-notice ssi-admin-notice--error" style="margin-bottom:10px;"><span class="dashicons dashicons-warning" style="margin-right:6px;"></span> <strong>SECURITY WARNING:</strong> ' + data.modified.length + ' core files do not match official WordPress checksums.</div>';
							errHtml += '<ul style="max-height:200px;overflow-y:auto;border:1px solid var(--ssi-border);padding:10px;background:#fff;border-radius:4px;font-family:monospace;font-size:12px;margin:0;">';
							data.modified.forEach(function(m) {
								errHtml += '<li style="color:#c53030;margin-bottom:4px;">' + escHtml(m.file) + '</li>';
							});
							errHtml += '</ul>';
							verCoreRes.innerHTML = errHtml;
						}
					})
					.catch(function() {
						verCoreRes.innerHTML = '<div class="ssi-admin-notice ssi-admin-notice--error">Network Failure.</div>';
						verCoreBtn.disabled = false;
					});
			});
		}

		document.querySelectorAll('.ssi-copy-section-btn').forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				var card = btn.closest('.ssi-card');
				if (!card) return;
				var title = card.querySelector('.ssi-card__title');
				var target = card.querySelector('.ssi-copy-target') || card.querySelector('.ssi-card__body');
				if (!title || !target) return;
				
				var txt = '# ' + title.textContent.trim() + '\n\n';
				target.querySelectorAll('tr').forEach(function(tr) {
					var isHeader = tr.querySelector('th') !== null;
					var cells = Array.prototype.slice.call(tr.querySelectorAll('th, td')).map(function(c) { return c.textContent.trim(); });
					txt += cells.join(isHeader ? ' | ' : ': ') + '\n';
				});
				doCopy(txt);
			});
		});
	}

	// ── Target blank fixes and basic interactions ─────────────────────────
	function initA11y() {
		// Retain hook in case external code calls it directly.
	}

	// ── Activity Audit Log Filters ─────────────────────────────────────────
	function initTimeline() {
		var filters = document.getElementById( 'ssi-timeline-filters' );
		var refresh = document.getElementById( 'ssi-refresh-history' );
		var clear   = document.getElementById( 'ssi-clear-history' );
		if ( ! filters ) return;
		
		var btns  = filters.querySelectorAll( 'button' );
		var rows  = document.querySelectorAll( '.ssi-audit-row' );
		
		btns.forEach( function( btn ) {
			btn.addEventListener( 'click', function() {
				var filter = this.getAttribute( 'data-filter' );
				
				// Update active state
				btns.forEach( function( b ) { b.classList.remove( 'ssi-log-filter--active' ); } );
				this.classList.add( 'ssi-log-filter--active' );
				
				// Filter rows
				rows.forEach( function( row ) {
					if ( filter === 'all' || row.getAttribute( 'data-type' ) === filter ) {
						row.style.display = ''; // Browser default for table-row
					} else {
						row.style.display = 'none';
					}
				});
			});
		});

		if ( refresh ) {
			refresh.addEventListener( 'click', function() {
				var url = new URL( window.location.href );
				url.searchParams.set( 'ssi_refresh', '1' );
				window.location.href = url.toString();
			});
		}

		if ( clear ) {
			clear.addEventListener( 'click', function() {
				if ( ! confirm( 'Are you EXACTLY sure you want to delete ALL audit history? This action is irreversible.' ) ) {
					return;
				}
				
				clear.disabled = true;
				var icon = clear.querySelector( '.dashicons' );
				if ( icon ) { icon.classList.add( 'dashicons-update-spin' ); }

				jQuery.ajax( {
					url: ssiData.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ssi_clear_history',
						nonce: ssiData.nonce
					},
					success: function() {
						location.href = location.href.split('#')[0] + '#ssi-tab-history';
						location.reload();
					},
					error: function() {
						alert( ssiData.i18n.loadFailed );
						clear.disabled = false;
						if ( icon ) { icon.classList.remove( 'dashicons-update-spin' ); }
					}
				} );
			});
		}
	}

	// ── Init ──────────────────────────────────────────────────────────────
	document.addEventListener( 'DOMContentLoaded', function () {
		wrap   = document.getElementById( 'ssi-dashboard' );
		isDark = wrap ? wrap.classList.contains( 'ssi-dark' ) : false;
		initCopyBtn();
		initCopyReportBtn();
		initCopyValue();
		initExportBtn();
		initPrintBtn();
		initPrintBtn();
		initTabs();
		initTools();
		initLogsTabs();
		initDebugLog();
		initSmartPills();
		initFilters();
		initExplain();
		initTooltips();
		initFixNowButtons();
		initStickyBar();
		initNoticesDismiss();
		initDevLazyLoad();
		initDevInteractions();
		initTimeline();
		initA11y();
	} );

}() );
