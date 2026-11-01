(function($){'use strict';$(function(){var l10n=window.wpTopInfoBarAdmin||{},$box=$('.wp-top-info-bar-admin');
		var COLOR_PICK = '.wp-top-info-bar-color-pick';
		var colorI18n = $.extend( {}, l10n.colorPicker || {} );
		// The palette's colors and names (TinyMCE's map: "RRGGBB", name, …).
		var colorSwatches = [];
		var colorNames = [];
		( $.isArray( l10n.textColors ) ? l10n.textColors : [] ).forEach( function ( item, i, map ) {
			if ( i % 2 === 0 && /^[0-9a-f]{6}$/i.test( item ) ) {
				colorSwatches.push( '#' + item.toLowerCase() );
				colorNames.push( String( map[ i + 1 ] || '' ) );
			}
		} );

		// "#rrggbb" / "rgba(r,g,b,a)" as { hex, alpha }; null for anything else.
		function parseColor( value ) {
			value = String( value || '' ).replace( /\s+/g, '' ).toLowerCase();
			var m = /^rgba\((\d{1,3}),(\d{1,3}),(\d{1,3}),(\d*\.?\d+)\)$/.exec( value );
			if ( m ) {
				return {
					hex: '#' + [ m[ 1 ], m[ 2 ], m[ 3 ] ].map( function ( channel ) {
						return ( '0' + Math.min( 255, parseInt( channel, 10 ) ).toString( 16 ) ).slice( -2 );
					} ).join( '' ),
					alpha: Math.round( Math.min( 1, parseFloat( m[ 4 ] ) ) * 100 )
				};
			}
			if ( /^#[0-9a-f]{3}$/.test( value ) ) {
				value = '#' + value[ 1 ] + value[ 1 ] + value[ 2 ] + value[ 2 ] + value[ 3 ] + value[ 3 ];
			}
			return /^#[0-9a-f]{6}$/.test( value ) ? { hex: value, alpha: 100 } : null;
		}

		function colorValue( hex, alpha ) {
			if ( alpha >= 100 ) {
				return hex;
			}
			return 'rgba(' + [ 1, 3, 5 ].map( function ( i ) {
				return parseInt( hex.substr( i, 2 ), 16 );
			} ).join( ',' ) + ',' + ( alpha / 100 ) + ')';
		}

		// What Default goes back to.
		function pickDefault( $pick ) {
			return parseColor( $pick.attr( 'data-default' ) ) || { hex: '#000000', alpha: 100 };
		}

		// The swatch says what is chosen (over its checkerboard, admin.css).
		function paintColor( $pick ) {
			$pick.find( '.wp-top-info-bar-color-pick__chip' ).css( 'box-shadow', 'inset 0 0 0 60px ' + colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) ) );
		}

		// Into Pickr (no event of its own), in the format it shows.
		function pickrShow( $pick ) {
			var pickr = $pick.data( 'pickr' );
			if ( ! pickr || ! $pick.data( 'pickrReady' ) ) {
				return;
			}
			var shown = pickr.getColorRepresentation() || 'HEXA';
			// Its format buttons say "change" too: not a new color.
			$pick.data( 'pickrSync', true );
			pickr.setColor( colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) ), true );
			pickr.setColorRepresentation( shown );
			$pick.data( 'pickrSync', false );
			// What Cancel goes back to.
			pickr.applyColor( true );
			pickr.getRoot().app.style.setProperty( '--wtib-opacity-color', $pick.data( 'hex' ) );
		}

		function setColor( $pick, hex, alpha ) {
			$pick.data( { hex: hex, alpha: alpha } );
			var pickr = $pick.data( 'pickr' );
			if ( pickr ) {
				pickr.getRoot().app.style.setProperty( '--wtib-opacity-color', hex );
			}
			paintColor( $pick );
		}

		// A setting's field: what is chosen into its input (the preview and
		// the "unsaved" warning take it as any other change).
		function colorPicked( $pick ) {
			var $input = $pick.find( '.wp-top-info-bar-color-pick__value' );
			var value = colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) );
			if ( $input.length && $input.val() !== value ) {
				$input.val( value ).trigger( 'change' );
			}
		}

		// From Pickr: the color it shows, as the field keeps it. Only a new
		// one counts (Pickr says "change" for a format button too).
		function fromPickr( $pick, color ) {
			if ( ! color || $pick.data( 'pickrSync' ) ) {
				return;
			}
			var rgba = color.toRGBA();
			var hex = '#' + [ 0, 1, 2 ].map( function ( i ) {
				return ( '0' + Math.max( 0, Math.min( 255, Math.round( rgba[ i ] ) ) ).toString( 16 ) ).slice( -2 );
			} ).join( '' );
			var alpha = $pick.attr( 'data-alpha' ) === '1' ? Math.max( 0, Math.min( 100, Math.round( rgba[ 3 ] * 100 ) ) ) : 100;
			if ( hex === $pick.data( 'hex' ) && alpha === $pick.data( 'alpha' ) ) {
				return;
			}
			setColor( $pick, hex, alpha );
			colorPicked( $pick );
		}

		function closeColorPickers() {
			$( COLOR_PICK ).each( function () {
				var pickr = $( this ).data( 'pickr' );
				if ( pickr ) {
					pickr.hide();
				}
			} );
		}

		// Pickr the first time a field opens (one app each, in the body).
		function makePickr( $pick ) {
			var button = $pick.find( '.wp-top-info-bar-color-pick__swatch' )[ 0 ];
			var alpha = $pick.attr( 'data-alpha' ) === '1';
			var pickr = window.Pickr.create( {
				el: button,
				useAsButton: true,
				theme: 'classic',
				appClass: 'wp-top-info-bar-pcr',
				position: 'bottom-start',
				default: colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) ),
				defaultRepresentation: 'HEXA',
				lockOpacity: ! alpha,
				swatches: colorSwatches,
				i18n: $.extend( {}, colorI18n, { 'btn:toggle': button.getAttribute( 'aria-label' ) } ),
				components: {
					preview: false,
					opacity: alpha,
					hue: true,
					interaction: {
						hex: true,
						rgba: true,
						hsla: true,
						input: true,
						cancel: true
					}
				}
			} );
			$pick.data( 'pickr', pickr );
			pickr.on( 'init', function ( instance ) {
				var $app = $( instance.getRoot().app );
				// The palette's names on hover.
				$app.find( '.pcr-swatches > button' ).each( function ( i ) {
					if ( colorNames[ i ] ) {
						this.setAttribute( 'title', colorNames[ i ] );
						this.setAttribute( 'aria-label', colorNames[ i ] );
					}
				} );
				// Default, after Cancel: red, as Pickr's own Clear (classic theme).
				$( '<input type="button" class="pcr-clear wp-top-info-bar-pcr__default">' )
					.val( colorI18n[ 'default' ] || 'Default' )
					.attr( 'aria-label', colorI18n.defaultLabel || '' )
					.appendTo( $app.find( '.pcr-interaction' ) )
					.on( 'click', function () {
						var color = pickDefault( $pick );
						setColor( $pick, color.hex, color.alpha );
						colorPicked( $pick );
						if ( ! $pick.attr( 'data-default' ) ) {
							$pick.find( '.wp-top-info-bar-color-pick__value' ).val( '' ).trigger( 'change' );
						}
						pickrShow( $pick );
					} );
				$pick.data( 'pickrReady', true );
				instance.show();
			} );
			pickr.on( 'show', function () {
				$pick.data( 'originalValue', $pick.find( '.wp-top-info-bar-color-pick__value' ).val() );
				pickrShow( $pick );
				button.setAttribute( 'aria-expanded', 'true' );
			} );
			pickr.on( 'hide', function () {
				button.setAttribute( 'aria-expanded', 'false' );
			} );
			pickr.on( 'change', function ( color ) {
				fromPickr( $pick, color );
			} );
			pickr.on( 'cancel', function ( instance ) {
				fromPickr( $pick, instance.getColor() );
				$pick.find( '.wp-top-info-bar-color-pick__value' ).val( $pick.data( 'originalValue' ) ).trigger( 'change' );
				instance.hide();
			} );
		}

		$( document ).on( 'click', '.wp-top-info-bar-color-pick__swatch', function () {
			var $pick = $( this ).closest( COLOR_PICK );
			if ( ! $pick.data( 'pickr' ) && window.Pickr ) {
				makePickr( $pick );
			}
		} );
		$(document).on('wpTopInfoBarSetColor', COLOR_PICK, function (event, value) {
			var $pick = $(this), color = parseColor(value) || pickDefault($pick);
			setColor($pick, color.hex, color.alpha); pickrShow($pick);
		});
		// The fields show their saved color.
		$( COLOR_PICK ).each( function () {
			var $pick = $( this );
			var color = parseColor( $pick.find( '.wp-top-info-bar-color-pick__value' ).val() ) || pickDefault( $pick );
			$pick.find( '.wp-top-info-bar-color-pick__swatch' ).attr( 'aria-expanded', 'false' );
			setColor( $pick, color.hex, color.alpha );
		} );

		$box.on( 'click', '.wp-top-info-bar-admin__menu-item', closeColorPickers );
		$( document ).on( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) { closeColorPickers(); }
		} );

});})(jQuery);
