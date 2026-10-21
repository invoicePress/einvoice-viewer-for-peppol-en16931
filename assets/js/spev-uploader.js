/* SP E-Invoice Viewer - drag & drop uploader rendered into a Shadow DOM ( theme-isolated ) */
( function () {
	'use strict';

	if ( typeof window.SPEV === 'undefined' ) {
		return;
	}

	var SPEV = window.SPEV;

	/**
	 * ⚠️ **READING THE DOCUMENT IS NOT DONE HERE.** XML parsing, PDF unpacking, model building and rendering
	 * belong to `<einvoice-viewer>`; it brings the bundle itself and loads pdf.js itself from the addresses in
	 * `data-pdfjs-url`/`data-pdfjs-worker-url`. This script reads neither `SPEV.modelBundleUrl` nor
	 * `SPEV.pdfJsUrl` nor `SPEV.pdfJsWorkerUrl`.
	 */

	/**
	 * Hooks the two extensions of this plugin into the element.
	 *
	 * ⚠️ **WITHOUT THIS CALL TWO PAID ADD-ONS FAIL SILENTLY.** "Extended Profiles" hangs on
	 * `add_filter( 'spev_invoice_model' )`, "Technical Details" on `add_filter( 'spev_code_tooltip' )`. Both
	 * filters fire only inside a **server-side** render; with the document read in the browser their fields and
	 * their tooltip lines disappear without a message ( found live on 18.09.2026 ). The two `-extend.mjs` modules
	 * are the replacement, and the element takes them through two hooks, because the reading code lives in the
	 * element.
	 *
	 * ⚠️ **THE MODULES LIE IN THE FOLDER OF THE RESPECTIVE ADD-ON, NOT IN THIS ONE.** The addresses stand in `SPEV`
	 * only when the add-on is active ( see `class-spev-plugin.php` ), otherwise they are empty - an `import('')`
	 * would be a call of the current page as a module.
	 *
	 * ⚠️ **AND BOTH LOAD LATER, THE FIRST DOCUMENT CAN BE THERE FASTER.** Hence `viewer.redraw()` after setting
	 * them: if a document already lies there, it is redrawn with the extension. Without that the extension would
	 * take effect only at the second one.
	 *
	 * @param {HTMLElement} viewer the <einvoice-viewer> element
	 * @return {void}
	 */

	function wireExtensions( viewer ) {

		/*
		 * ⚠️ THE DAILY LIMIT PER ROLE. Only when a number applies to this visitor ( `SPEV.quotaActive` ) does the
		 * element ask `spev_quota` before every invoice and read it only on a yes. Nothing of the invoice is
		 * sent, only the language for the message. If the server does not answer, the element reads anyway; see
		 * the reasoning at `beforeRead` in the element.
		 */
		if ( SPEV.quotaActive ) {

			viewer.beforeRead = function () {

				var body = new URLSearchParams();
				body.append( 'action', 'spev_quota' );
				body.append( 'lang', SPEV.lang || '' );

				return fetch( SPEV.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( answer ) { return answer && answer.data ? answer.data : null; } );

			};

		}

		if ( SPEV.extendedProfilesBundleUrl ) {

			import( SPEV.extendedProfilesBundleUrl ).then( function ( X ) {

				if ( ! X || typeof X.extendModel !== 'function' ) {
					return;
				}

				// ⚠️ `doc`, NOT `rawXml` - svze-extend.js works with `rsm:` paths on the DOM.
				// ⚠️ `lang` IS THE LANGUAGE THE ELEMENT JUST BUILT THE MODEL IN ( the fourth argument ). The add-on
				// labels its cards with it from the same tables `i18n/<lang>.json.js` this plugin loads. Without it
				// the cards would stay English.
				/*
				 * ⚠️ THE FIELD PACKAGE COMES BEFORE THE CALL, IN THE LANGUAGE OF THE MODEL. With it the extension puts
				 * every BT-X field into the card it belongs to; without it ( an older extension, package not
				 * reachable ) it appends its section with the cards of the header level instead. The element waits
				 * for the result, so `extendModel` may return a promise.
				 */
				viewer.extendModel = function ( model, doc, syntax, lang ) {
					var sprache = lang || viewer.dataset.lang || 'en';
					var laden = typeof X.loadFieldRows === 'function' ? X.loadFieldRows( sprache ) : Promise.resolve( null );
					return laden.then( function ( zeilen ) {
						return X.extendModel( model, doc, sprache, zeilen );
					} );
				};

				viewer.redraw();

			} ).catch( function () {
				// Add-on not reachable: the document stands there without its EXTENDED fields, not not at all.
			} );

		}

		/**
		 * The names of the extension fields ( BT-X-… ) in the tooltips - from the add-on's field package, the
		 * same package the app reads from `data/protected/` ( Extended Profiles 1.3.0 ).
		 *
		 * ⚠️ **ONLY WHAT IS MISSING IN THE CORE CATALOGUE.** The package also contains the 311 core fields, partly in
		 * the wording of the Factur-X source; they stay as `data-fields-url` names them.
		 *
		 * ⚠️ **ONLY IN THE LANGUAGE OF THE PAGE.** The package carries one language, and `SPEV.lang` chose it. If
		 * someone switches the language in the element, the BT-X fields lose their name in the tooltip instead of
		 * showing it in the wrong language.
		 *
		 * ⚠️ **ALL FIELD PACKAGES OF THE ADD-ON, NOT ONLY ONE.** The add-on carries one package per extension:
		 * ZUGFeRD / Factur-X EXTENDED ( BT-X-… ), the XRechnung Extension ( BG-DEX-…, BT-DEX-… ) and
		 * EXTENDED-CTC-FR ( EXT-FR-FE-… ). With only the first, the boxes of the third-party payments and the
		 * sub-lines would stay without a name in the tooltip. The first package wins on a duplicate id; the
		 * server puts ZUGFeRD first.
		 *
		 * ⚠️ **A MISSING PACKAGE COSTS ONLY ITS OWN NAMES.** Every package catches its own error and then returns an
		 * empty list, so a single broken request does not also take the names of the two other extensions with
		 * it.
		 */
		var paketAdressen = Array.isArray( SPEV.extendedProfilesFieldsUrls ) ? SPEV.extendedProfilesFieldsUrls.filter( Boolean ) : [];

		if ( paketAdressen.length > 0 && viewer.dataset.fieldsUrl ) {

			Promise.all( [
				fetch( viewer.dataset.fieldsUrl, { credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } )
			].concat( paketAdressen.map( function ( adresse ) {
				return fetch( adresse, { credentials: 'same-origin' } )
					.then( function ( r ) { return r.ok ? r.json() : null; } )
					.catch( function () { return null; } );
			} ) ) ).then( function ( alle ) {

				var kern = alle[ 0 ];

				if ( ! Array.isArray( kern ) ) {
					return;
				}

				var bekannt = {};
				var lang    = SPEV.lang || 'en';
				var dazu    = [];

				kern.forEach( function ( f ) { bekannt[ f.id ] = true; } );

				alle.slice( 1 ).forEach( function ( paket ) {

					if ( ! paket || ! Array.isArray( paket.rows ) ) {
						return;
					}

					paket.rows.forEach( function ( r ) {

						if ( ! r || ! r.id || bekannt[ r.id ] ) {
							return;
						}

						bekannt[ r.id ] = true;

						var name = {};
						var text = {};
						name[ lang ] = r.businessTerm || '';
						text[ lang ] = r.description || '';
						dazu.push( { id: r.id, businessTerm: name, description: text } );

					} );

				} );

				if ( dazu.length === 0 ) {
					return;
				}

				viewer.fields = kern.concat( dazu );
				viewer.redraw();

			} ).catch( function () {
				// Without the core catalogue the tooltips stay as they are - the cards of the extension stand there anyway.
			} );

		}

		if ( SPEV.technicalDetailsExtendUrl ) {

			import( SPEV.technicalDetailsExtendUrl ).then( function ( X ) {

				return ( X && X.loadTooltipExtender ) ? X.loadTooltipExtender() : null;

			} ).then( function ( extender ) {

				if ( typeof extender !== 'function' ) {
					return;
				}

				viewer.extendTooltip = extender;
				viewer.redraw();

			} ).catch( function () {
				// The same: the tooltips then show only term and description.
			} );

		}

	}

	/**
	 * ⚠️ 'spko_nonce' / 'spko_validate' ARE einvoice-validator-for-kosit's OWN AJAX
	 * action names ( class-spko-plugin.php ), hardcoded here the same way this plugin family already
	 * shares CSS class prefixes, shortcode names and font files literally across plugin boundaries
	 * instead of through a formal API - if that plugin ever renames its actions, this button breaks
	 * along with it. Reuses SPEV.ajaxUrl : one site has exactly one admin-ajax.php, shared by every
	 * plugin's AJAX handlers regardless of which plugin registered them.
	 */

	function fetchKositNonce() {
		var body = new URLSearchParams();
		body.append( 'action', 'spko_nonce' );

		return fetch( SPEV.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				return ( json && json.success && json.data ) ? json.data.nonce : '';
			} )
			.catch( function () { return ''; } );
	}

	/**
	 * ⚠️ 'spvx_nonce' / 'spvx_export' ARE einvoice-viewer-exporter's OWN AJAX action names ( see
	 * that plugin's class-spvx-plugin.php ) - same literal cross-plugin coupling as fetchKositNonce()
	 * above, not a formal API.
	 */

	function fetchExportNonce() {
		var body = new URLSearchParams();
		body.append( 'action', 'spvx_nonce' );

		return fetch( SPEV.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				return ( json && json.success && json.data ) ? json.data.nonce : '';
			} )
			.catch( function () { return ''; } );
	}

	/**
	 * ⚠️ 'spvz_nonce' / 'spvz_visualize' ARE einvoice-viewer-visualizer's OWN AJAX action names ( see
	 * that plugin's class-spvz-plugin.php ) - same literal cross-plugin coupling as fetchKositNonce()/
	 * fetchExportNonce() above, not a formal API.
	 */

	function fetchVisualizerNonce() {
		var body = new URLSearchParams();
		body.append( 'action', 'spvz_nonce' );

		return fetch( SPEV.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				return ( json && json.success && json.data ) ? json.data.nonce : '';
			} )
			.catch( function () { return ''; } );
	}

	/**
	 * ⚠️ 'spic_nonce' / 'spic_check' ARE einvoice-viewer-identifier-check's OWN AJAX action names
	 * ( see that plugin's class-spic-plugin.php ) - same literal cross-plugin coupling as
	 * fetchKositNonce()/fetchExportNonce()/fetchVisualizerNonce() above, not a formal API.
	 */

	function fetchIdentifierCheckNonce() {
		var body = new URLSearchParams();
		body.append( 'action', 'spic_nonce' );

		return fetch( SPEV.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				return ( json && json.success && json.data ) ? json.data.nonce : '';
			} )
			.catch( function () { return ''; } );
	}

	/**
	 * Wires the **add-ons** of this plugin with the web component.
	 *
	 * ⚠️ **`root` IS THE `<einvoice-viewer>` ELEMENT, NOT A SHADOW ROOT OF THIS SCRIPT.** Drop area, message line,
	 * BT and "lite" switches, source line and invoice view belong to the element; this script manages only the
	 * eight buttons and their result areas, which are shown there through `slot="toolbar"` and
	 * `slot="panels"`.
	 *
	 * ⚠️ **AND THOSE LIE IN THE LIGHT DOM**, so the element is accessed here with `root.querySelector()` as an
	 * ordinary container - not its shadow root. Reaching in there would bypass exactly the boundary the element
	 * is built on.
	 *
	 * @param {HTMLElement} root  the <einvoice-viewer> element
	 * @param {HTMLElement} host  the surrounding .spev-host container
	 */

	function init( root, host ) {

		var kositCheck   = root.querySelector( '.spev-kosit-check' );
		var kositBtn     = root.querySelector( '.spev-kosit-btn' );
		var kositMessage = root.querySelector( '.spev-kosit-message' );
		var kositResult  = root.querySelector( '.spev-kosit-result' );
		var mustangBtn   = root.querySelector( '.spev-mustang-btn' );
		var vampireBtn   = root.querySelector( '.spev-vampire-btn' );

		var exportCheck   = root.querySelector( '.spev-export-check' );
		var exportBtn     = root.querySelector( '.spev-export-btn' );
		var exportMessage = root.querySelector( '.spev-export-message' );

		var fieldBrowserBtn = root.querySelector( '.spev-fieldbrowser-btn' );
		var ruleBrowserBtn  = root.querySelector( '.spev-rulebrowser-btn' );

		var visualizerCheck       = root.querySelector( '.spev-visualizer-check' );
		var visualizerBtn         = root.querySelector( '.spev-visualizer-btn' );
		var visualizerPdfBtn      = root.querySelector( '.spev-visualizer-pdf-btn' );
		var visualizerLayoutSelect = root.querySelector( '.spev-visualizer-layout' );
		var visualizerMessage     = root.querySelector( '.spev-visualizer-message' );
		// ⚠️ THE GROUP AROUND SELECT AND BOTH BUTTONS NEEDS ITS OWN `hidden`, not just its children:
		// an empty but displayed flex item still takes one `gap` in the toolbar. See class-spev-plugin.php.
		var visualizerGroup       = root.querySelector( '.spev-visualizer-group' );

		var identifierCheckWrap    = root.querySelector( '.spev-identifier-check' );
		var identifierCheckBtn     = root.querySelector( '.spev-identifier-check-btn' );
		var identifierCheckMessage = root.querySelector( '.spev-identifier-check-message' );

		/**
		 * ⚠️ **FILE, MODEL AND IDENTIFIERS ARE NOT REMEMBERED BUT ASKED FOR AT USE.** As soon as the element
		 * **redraws** the document - after a language switch or because an extension was loaded later - a new model
		 * is made without a new event; a remembered version would then be outdated, and the add-ons would send it
		 * to their server. `root.file`, `root.model` and `root.identifiers` are always the current state.
		 *
		 * ⚠️ **THEY ARE ALSO THE CHECK "IS THERE A DOCUMENT AT ALL".** The element sets all three to `null` at
		 * "Reset"; a flag of its own would be a second place where the same state stands.
		 */
		function currentFile() { return root.file; }
		function currentModel() { return root.model; }
		function currentIdentifiers() { return root.identifiers; }

		/**
		 * The file a validator gets: the uploaded XML as it is, or for a ZUGFeRD / Factur-X PDF the XML the element
		 * has read out of it, under the name of the PDF with `.xml`.
		 *
		 * ⚠️ **ALL THREE VALIDATORS ACCEPT ONLY XML** ( `FrontendCore\Upload::accept()`, measured 20.10.2026 with
		 * Validator for VAMPIRE 1.12.0 ). Sending the PDF itself came back as "Please choose an XML file." for every
		 * hybrid invoice. ⚠️ What is checked is then the invoice data, not the PDF/A container around it.
		 *
		 * @return {File|null}
		 */
		function validatorFile() {

			var file = currentFile();

			if ( ! file || /\.xml$/i.test( file.name || '' ) || ! root.rawXml ) {
				return file;
			}

			return new File( [ root.rawXml ], ( file.name || 'invoice' ).replace( /\.[^.]+$/, '' ) + '.xml', { type: 'application/xml' } );

		}

		var currentIsXml   = false;
		var currentContext = null;
		var kositBusy      = false;
		var exportBusy     = false;
		var visualizerBusy = false;
		var visualizerPdfBusy = false;
		var identifierCheckBusy = false;

		/**
		 * The class of a message line: a message with a kind is the kit's `.note` ( 'error' is `err` there ), one
		 * without a kind plain muted text.
		 *
		 * ⚠️ THE KIT REACHES THESE LINES ONLY THROUGH `data-kit-scope` on the element. They lie in the light DOM
		 * ( `slot="panels"` ), where the shadow root's stylesheets do not apply; spt-kit.components.scoped.css does.
		 * An empty line is hidden, because an empty `.note` still draws its frame.
		 */
		function messageClass( base, type ) {
			var kind = { error: 'err', warn: 'warn', ok: 'ok', info: 'info' }[ type ];
			return base + ( kind ? ' note ' + kind : ' muted' );
		}

		function setKositMessage( text, type ) {
			if ( ! kositMessage ) {
				return;
			}
			kositMessage.textContent = text || '';
			kositMessage.className = messageClass( 'spev-kosit-message', type );
			kositMessage.hidden = ! text;
		}

		function setExportMessage( text, type ) {
			if ( ! exportMessage ) {
				return;
			}
			exportMessage.textContent = text || '';
			exportMessage.className = messageClass( 'spev-export-message', type );
			exportMessage.hidden = ! text;
		}

		function setVisualizerMessage( text, type ) {
			if ( ! visualizerMessage ) {
				return;
			}
			visualizerMessage.textContent = text || '';
			visualizerMessage.className = messageClass( 'spev-visualizer-message', type );
			visualizerMessage.hidden = ! text;
		}

		function setIdentifierCheckMessage( text, type ) {
			if ( ! identifierCheckMessage ) {
				return;
			}
			identifierCheckMessage.textContent = text || '';
			identifierCheckMessage.className = messageClass( 'spev-identifier-check-message', type );
			identifierCheckMessage.hidden = ! text;
		}

		/**
		 * Shows the buttons of the add-ons as soon as a document has been read.
		 *
		 * ⚠️ **EVERY BUTTON HAS ITS OWN CONDITION, AND IT IS NOT "ADD-ON ACTIVE".** The KoSIT button appears only for
		 * an XML upload, because the validator accepts nothing else ( see `class-spko-plugin.php::ajax_validate()` );
		 * a ZUGFeRD PDF would come back as "Please choose an XML file." without anything gained. All others work
		 * from the model already read and are therefore offered for a PDF as well.
		 */
		function showButtons() {

			var showKosit = ( SPEV.hasKositValidator && currentIsXml );

			// ⚠️ MUSTANG AND VAMPIRE STAND FOR EVERY INVOICE: for a ZUGFeRD PDF they get the XML read out of it, see
			// `validatorFile()`. The result area is shared by the three validators; it shows when any of their buttons does.
			var showMustang = !! SPEV.hasMustangValidator;
			var showVampire = !! SPEV.hasVampireValidator;

			if ( kositBtn )   { kositBtn.hidden   = ! showKosit; }
			if ( mustangBtn ) { mustangBtn.hidden = ! showMustang; }
			if ( vampireBtn ) { vampireBtn.hidden = ! showVampire; }
			if ( kositCheck ) { kositCheck.hidden = ! ( showKosit || showMustang || showVampire ); }

			if ( exportBtn )   { exportBtn.hidden   = ! SPEV.hasExporter; }
			if ( exportCheck ) { exportCheck.hidden = ! SPEV.hasExporter; }

			if ( fieldBrowserBtn ) { fieldBrowserBtn.hidden = ! SPEV.hasTechnicalDetails; }
			if ( ruleBrowserBtn )  { ruleBrowserBtn.hidden  = ! SPEV.hasTechnicalDetails; }

			if ( visualizerGroup )        { visualizerGroup.hidden        = ! SPEV.hasVisualizer; }
			if ( visualizerBtn )          { visualizerBtn.hidden          = ! SPEV.hasVisualizer; }
			if ( visualizerLayoutSelect ) { visualizerLayoutSelect.hidden = ! SPEV.hasVisualizer; }

			// A switch of its own next to hasVisualizer - the PDF export is a second, finer purchase level WITHIN an
			// already active Visualizer.
			if ( visualizerPdfBtn ) { visualizerPdfBtn.hidden = ! SPEV.hasVisualizerPdf; }

			if ( identifierCheckBtn ) { identifierCheckBtn.hidden = ! SPEV.hasIdentifierCheck; }

		}


		/**
		 * Hides them again - at the start and after "Reset".
		 *
		 * ⚠️ **THE RESULT AREAS TOO**, not only the buttons. A KoSIT report left standing would belong to a document
		 * nobody sees any more.
		 */
		function hideButtons() {

			[
				kositBtn, mustangBtn, vampireBtn, kositCheck, exportBtn, exportCheck, fieldBrowserBtn, ruleBrowserBtn,
				visualizerGroup, visualizerBtn, visualizerLayoutSelect, visualizerPdfBtn, visualizerCheck,
				identifierCheckBtn, identifierCheckWrap
			].forEach( function ( el ) {
				if ( el ) { el.hidden = true; }
			} );

			if ( kositResult )        { kositResult.innerHTML = ''; }
			dropBridgeButtons();

		}

		/**
		 * Re-send the CURRENTLY shown invoice's own File object to the KoSIT validator's existing
		 * `spko_validate` endpoint and render whatever it sends back - reusing
		 * window.SPKO_renderReport() rather than a second copy of the iframe-vs-explainer branch (see
		 * that function's own doc comment in spko-uploader.js for why).
		 */

		// ⚠️ The KoSIT validator names its own messages ( bridge_texts() ); the viewer's texts stay only as the fallback
		// for a validator older than that method. runValidator() below needs no fallback, see validator_texts().
		function kositText( key ) {
			var own = ( ( SPEV.validators || {} ).kosit || {} )[ key ];
			return own || SPEV.i18n[ key === 'loading' ? 'kositLoading' : 'kositFailed' ];
		}

		/**
		 * One button area for the whole tool, also with a validation report shown ( Ralf, 18.10.2026 ).
		 *
		 * ⚠️ THE REPORT'S OWN BUTTONS MOVE INTO THE VIEWER'S TOOLBAR. The validators draw their report with a button
		 * area of its own ( "Download report.xml" above, "Reset" below ). Inside the viewer that gave two areas and two
		 * "Reset" buttons. The action buttons are therefore moved into the toolbar ( `slot="toolbar"`, appended last,
		 * so they stand left of the buttons already there ), and the report's own area with its "Reset" is removed:
		 * the viewer's "Reset" clears the report as well ( hideButtons() ).
		 *
		 * ⚠️ THE BUTTON THAT STARTED THE CHECK THEN SWITCHES THE REPORT OFF AND ON, without asking the server again:
		 * a second check of the same file would only cost time and count against the daily limit.
		 *
		 * @param {HTMLElement} checkBtn the button of the validator whose report is now shown
		 */
		var reportOwner = null;

		function dropBridgeButtons() {

			Array.prototype.forEach.call( root.querySelectorAll( '.spev-bridge-btn' ), function ( b ) {
				b.parentNode.removeChild( b );
			} );

			if ( reportOwner && reportOwner.dataset.spevLabel ) {
				reportOwner.textContent = reportOwner.dataset.spevLabel;
				reportOwner.removeAttribute( 'aria-pressed' );
			}

			reportOwner = null;

			if ( kositResult ) {
				kositResult.hidden = false;
			}

		}

		function adoptReport( checkBtn ) {

			dropBridgeButtons();

			if ( ! kositResult ) {
				return;
			}

			var area = kositResult.querySelector( '[class$="-result-tools"]' );

			if ( area ) {

				Array.prototype.forEach.call( area.querySelectorAll( '[class$="-tools-actions"] button' ), function ( b ) {
					b.className = 'btn spev-bridge-btn';
					b.setAttribute( 'slot', 'toolbar' );
					root.appendChild( b );
				} );

				area.parentNode.removeChild( area );

			}

			reportOwner = checkBtn;
			checkBtn.dataset.spevLabel = checkBtn.dataset.spevLabel || checkBtn.textContent;
			checkBtn.textContent = SPEV.i18n.hideReport || 'Hide validation report';
			checkBtn.setAttribute( 'aria-pressed', 'true' );

		}

		/**
		 * @param {HTMLElement} checkBtn
		 * @return {boolean} true when the click only switched the shown report
		 */
		function toggleReport( checkBtn ) {

			if ( reportOwner !== checkBtn || ! kositResult ) {
				return false;
			}

			kositResult.hidden = ! kositResult.hidden;
			checkBtn.textContent = kositResult.hidden ? ( SPEV.i18n.showReport || 'Show validation report' ) : ( SPEV.i18n.hideReport || 'Hide validation report' );
			checkBtn.setAttribute( 'aria-pressed', kositResult.hidden ? 'false' : 'true' );

			Array.prototype.forEach.call( root.querySelectorAll( '.spev-bridge-btn' ), function ( b ) {
				b.hidden = kositResult.hidden;
			} );

			return true;

		}

		function validateWithKosit() {

			if ( toggleReport( kositBtn ) ) {
				return;
			}

			if ( kositBusy || ! currentFile() ) {
				return;
			}

			dropBridgeButtons();

			kositBusy = true;
			kositBtn.disabled = true;

			if ( kositResult ) {
				kositResult.innerHTML = '';
			}
			setKositMessage( kositText( 'loading' ), 'info' );

			fetchKositNonce().then( function ( nonce ) {

				var data = new FormData();
				data.append( 'action', 'spko_validate' );
				// The language of the page, so that the answer speaks it too ( EInvoicePress_Request_Language ).
				data.append( 'lang', SPEV.lang || '' );
				data.append( 'nonce', nonce );
				data.append( 'file', currentFile(), currentFile().name );

				return fetch( SPEV.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: data
				} );

			} ).then( function ( r ) {
				return r.json().catch( function () { return null; } );
			} ).then( function ( json ) {

				kositBusy = false;
				kositBtn.disabled = false;

				if ( ! json || ! json.success || ! json.data ) {
					setKositMessage( ( json && json.data && json.data.message ) ? json.data.message : kositText( 'failed' ), 'error' );
					return;
				}

				setKositMessage( '' );

				if ( kositResult && typeof window.SPKO_renderReport === 'function' ) {
					window.SPKO_renderReport( kositResult, json.data );
					adoptReport( kositBtn );
				}

			} ).catch( function () {
				kositBusy = false;
				kositBtn.disabled = false;
				setKositMessage( kositText( 'failed' ), 'error' );
			} );

		}

		if ( kositBtn ) {
			kositBtn.addEventListener( 'click', validateWithKosit );
		}

		/**
		 * The buttons of the Mustang and the VAMPIRE validator: the same way as KoSIT above, with their own actions
		 * ( `spmv_nonce` / `spmv_validate`, `sppv_nonce` / `sppv_validate` ) and their own drawing function
		 * ( `window.SPMV_renderReport`, `window.SPPV_renderReport` ), which their bridge script exposes.
		 *
		 * ⚠️ ONE RESULT AREA FOR ALL THREE. A second report replaces the first; three reports under each other would
		 * push the invoice out of sight, and the reader asked for the one he clicked last.
		 *
		 * ⚠️ `validatorBusy` IS SHARED WITH NOBODY BUT THESE TWO; KoSIT keeps its own flag. While one runs, its button is
		 * disabled, so a second click on it does not send the invoice twice.
		 */
		var validatorBusy = false;

		function runValidator( btn, prefix, renderName, texts ) {

			texts = texts || {};

			if ( toggleReport( btn ) ) {
				return;
			}

			if ( validatorBusy || ! currentFile() ) {
				return;
			}

			dropBridgeButtons();

			validatorBusy = true;
			btn.disabled = true;

			if ( kositResult ) {
				kositResult.innerHTML = '';
			}
			setKositMessage( texts.loading || '', 'info' );

			var nonceBody = new URLSearchParams();
			nonceBody.append( 'action', prefix + '_nonce' );

			fetch( SPEV.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: nonceBody } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( json ) {

					var data = new FormData();
					data.append( 'action', prefix + '_validate' );
					data.append( 'lang', SPEV.lang || '' );
					data.append( 'nonce', ( json && json.success && json.data ) ? json.data.nonce : '' );
					data.append( 'file', validatorFile(), validatorFile().name );

					return fetch( SPEV.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } );

				} ).then( function ( r ) {
					return r.json().catch( function () { return null; } );
				} ).then( function ( json ) {

					validatorBusy = false;
					btn.disabled = false;

					if ( ! json || ! json.success || ! json.data ) {
						setKositMessage( ( json && json.data && json.data.message ) ? json.data.message : ( texts.failed || '' ), 'error' );
						return;
					}

					setKositMessage( '' );

					if ( kositResult && typeof window[ renderName ] === 'function' ) {
						window[ renderName ]( kositResult, json.data );
						adoptReport( btn );
					}

				} ).catch( function () {
					validatorBusy = false;
					btn.disabled = false;
					setKositMessage( texts.failed || '', 'error' );
				} );

		}

		if ( mustangBtn ) {
			mustangBtn.addEventListener( 'click', function () { runValidator( mustangBtn, 'spmv', 'SPMV_renderReport', ( SPEV.validators || {} ).mustang ); } );
		}

		if ( vampireBtn ) {
			vampireBtn.addEventListener( 'click', function () { runValidator( vampireBtn, 'sppv', 'SPPV_renderReport', ( SPEV.validators || {} ).vampire ); } );
		}

		/**
		 * Send the already-built model (see the main parse chain above) to the exporter's `spvx_export`
		 * endpoint and offer the returned .xlsx as a download - the same currentModel() reuse as
		 * the Identifier Check button above.
		 *
		 * ⚠️ THE RESPONSE IS BINARY, NOT JSON - unlike every other AJAX call in this file. A failure
		 * still comes back as ordinary JSON ( see class-spvx-plugin.php::ajax_export() ), which is why
		 * this checks the Content-Type header BEFORE deciding whether to read the body as a Blob or
		 * as JSON - reading a JSON error body as a Blob would "succeed" and hand the visitor a
		 * corrupt, unopenable file instead of the error message.
		 */

		function exportInvoice() {

			if ( exportBusy || ! currentModel() ) {
				return;
			}

			exportBusy = true;
			exportBtn.disabled = true;

			setExportMessage( SPEV.i18n.exportLoading, 'info' );

			fetchExportNonce().then( function ( nonce ) {

				var data = new FormData();
				data.append( 'action', 'spvx_export' );
				data.append( 'nonce', nonce );
				data.append( 'model', JSON.stringify( currentModel() ) );
				data.append( 'filename', currentFile() ? currentFile().name : '' );
				// The Viewer's own display language selects the export template file - see
				// SPVX_Plugin::template_path(). SPEV.lang is always 'de'|'en'|'fr' (server-side fallback, see
				// SPEV_Plugin::tooltip_lang()), so no client-side validation is needed here.
				data.append( 'lang', SPEV.lang || 'de' );

				return fetch( SPEV.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: data
				} );

			} ).then( function ( r ) {

				var contentType = r.headers.get( 'Content-Type' ) || '';

				if ( ! r.ok || contentType.indexOf( 'json' ) !== -1 ) {
					return r.json().catch( function () { return null; } ).then( function ( json ) {
						throw new Error( ( json && json.data && json.data.message ) ? json.data.message : SPEV.i18n.exportFailed );
					} );
				}

				var disposition = r.headers.get( 'Content-Disposition' ) || '';
				var match       = /filename="([^"]+)"/.exec( disposition );
				var filename    = match ? match[ 1 ] : ( currentFile().name.replace( /\.[^.]+$/, '' ) + '-export.xlsx' );

				return r.blob().then( function ( blob ) {
					return { blob: blob, filename: filename };
				} );

			} ).then( function ( result ) {

				exportBusy = false;
				exportBtn.disabled = false;
				setExportMessage( '' );

				var url = URL.createObjectURL( result.blob );
				var a   = document.createElement( 'a' );

				a.href     = url;
				a.download = result.filename;

				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );

				window.setTimeout( function () { URL.revokeObjectURL( url ); }, 1000 );

			} ).catch( function ( err ) {
				exportBusy = false;
				exportBtn.disabled = false;
				setExportMessage( ( err && err.message ) ? err.message : SPEV.i18n.exportFailed, 'error' );
			} );

		}

		if ( exportBtn ) {
			exportBtn.addEventListener( 'click', exportInvoice );
		}

		/**
		 * Open one of Technical Details' three browsers in a new window, pre-filtered with whatever
		 * best-effort context class-spev-plugin.php's do_render() attached to the last successful
		 * render ( see its detect_technical_context() ) - a plain `window.open()` to
		 * einvoice-viewer-technical-details' own `svtd_popup` AJAX action, reusing SPEV.ajaxUrl since
		 * both plugins' admin-ajax.php calls land on the exact same site endpoint. Missing/unrecognised
		 * context values are simply omitted rather than sent empty - svtd.js's applyUrlFilters() only
		 * looks at params that ARE present, so leaving one out is exactly "no guess", not "guess: none".
		 *
		 * @param	string	mode	'fields' | 'rules'
		 * @return	void
		 */

		function openTechnicalDetailsPopup( mode ) {

			// 'lang': admin-ajax.php builds the popup page and would otherwise speak the profile language
			// of a logged-in user ( EInvoicePress_Request_Language ).
			var params = [ 'action=svtd_popup', 'mode=' + encodeURIComponent( mode ), 'lang=' + encodeURIComponent( SPEV.lang || '' ) ];
			var ctx     = currentContext || {};

			if ( mode === 'fields' && ctx.scope ) {
				params.push( 'svtd_scope=' + encodeURIComponent( ctx.scope ) );
			}

			if ( mode === 'rules' && ctx.standard ) {
				params.push( 'svtd_standard=' + encodeURIComponent( ctx.standard ) );
			}

			window.open( SPEV.ajaxUrl + '?' + params.join( '&' ), 'svtd_' + mode, 'width=1100,height=760' );

		}

		if ( fieldBrowserBtn ) {
			fieldBrowserBtn.addEventListener( 'click', function () { openTechnicalDetailsPopup( 'fields' ); } );
		}

		if ( ruleBrowserBtn ) {
			ruleBrowserBtn.addEventListener( 'click', function () { openTechnicalDetailsPopup( 'rules' ); } );
		}

		/**
		 * Send the already-built model to the Visualizer's `spvz_visualize` endpoint (the same currentModel()
		 * reuse as exportInvoice() above) and open the returned standalone HTML document ( one
		 * of the four print layouts, with its own "Print" button - see einvoice-viewer-visualizer's
		 * class-spvz-plugin.php ) in a new window.
		 *
		 * ⚠️ THE POP-UP IS OPENED SYNCHRONOUSLY, BEFORE THE fetch() EVEN STARTS - a browser's pop-up
		 * blocker allows window.open() only while still inside the click's own event handler; calling
		 * it only after an async fetch() resolves is exactly what gets silently blocked. The window
		 * opens on a small "loading" placeholder first, then document.write() replaces it with the
		 * real content once the response arrives - the same reason openTechnicalDetailsPopup() above
		 * gets away with a plain window.open( url ) is that IT never has to wait on a fetch() first.
		 */

		function visualizeInvoice() {

			if ( visualizerBusy || ! currentModel() ) {
				return;
			}

			var layout = visualizerLayoutSelect ? visualizerLayoutSelect.value : '';
			var popup  = window.open( '', 'spvz_visualize', 'width=900,height=1000' );

			if ( ! popup ) {
				setVisualizerMessage( SPEV.i18n.visualizerPopupBlocked, 'error' );
				return;
			}

			// ⚠️ The placeholder page has no stylesheet, so the values of Kit H stand here literally
			// ( --font, --fs-sm, --muted ). The real page replaces it within a second or two.
			popup.document.write( '<p style="font-family:Inter,-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:13.5px;color:#4a5468;padding:2em;">' + SPEV.i18n.visualizerLoading + '</p>' );
			popup.document.close();

			visualizerBusy = true;
			visualizerBtn.disabled = true;

			setVisualizerMessage( SPEV.i18n.visualizerLoading, 'info' );

			fetchVisualizerNonce().then( function ( nonce ) {

				var data = new FormData();
				data.append( 'action', 'spvz_visualize' );
				// The language of the page, so that the answer speaks it too ( EInvoicePress_Request_Language ).
				data.append( 'lang', SPEV.lang || '' );
				data.append( 'nonce', nonce );
				data.append( 'layout', layout );
				data.append( 'model', JSON.stringify( currentModel() ) );

				return fetch( SPEV.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: data
				} );

			} ).then( function ( r ) {
				return r.json().catch( function () { return null; } );
			} ).then( function ( json ) {

				visualizerBusy = false;
				visualizerBtn.disabled = false;

				if ( json && json.success && json.data && typeof json.data.html === 'string' ) {
					setVisualizerMessage( '' );
					popup.document.open();
					popup.document.write( json.data.html );
					popup.document.close();
					return;
				}

				var msg = ( json && json.data && json.data.message ) ? json.data.message : SPEV.i18n.visualizerFailed;
				setVisualizerMessage( msg, 'error' );
				popup.close();

			} ).catch( function () {
				visualizerBusy = false;
				visualizerBtn.disabled = false;
				setVisualizerMessage( SPEV.i18n.visualizerFailed, 'error' );
				popup.close();
			} );

		}

		if ( visualizerBtn ) {
			visualizerBtn.addEventListener( 'click', visualizeInvoice );
		}

		/**
		 * Renders the selected layout as an actual PDF/A-3 file, with the ORIGINAL uploaded document's XML
		 * embedded, and offers it as a download - the Visualizer's paid level, next to the free
		 * visualizeInvoice() HTML popup above.
		 *
		 * ⚠️ SENDS currentFile() TOO, NOT JUST currentModel() - unlike every other button in this file that
		 * reuses the already-built model (exportInvoice()/visualizeInvoice()/runIdentifierCheck()/
		 * the Identifier Check), this one ALSO needs the raw bytes: the server embeds the SAME xml that
		 * was originally uploaded (or, for a ZUGFeRD PDF, that PDF's own embedded xml), not a re-derived
		 * one - same `data.append('file', currentFile(), currentFile().name)` pattern
		 * validateWithKosit() already uses for the same reason (see that function's own comment).
		 *
		 * ⚠️ RESPONSE IS BINARY ON SUCCESS, JSON ON FAILURE - identical contract to exportInvoice()'s
		 * Excel export (see that function's own comment on why the Content-Type header decides how to
		 * read the body), reused verbatim here.
		 */

		function exportPdfInvoice() {

			if ( visualizerPdfBusy || ! currentModel() || ! currentFile() ) {
				return;
			}

			var layout = visualizerLayoutSelect ? visualizerLayoutSelect.value : '';

			visualizerPdfBusy = true;
			visualizerPdfBtn.disabled = true;

			setVisualizerMessage( SPEV.i18n.visualizerPdfLoading, 'info' );

			fetchVisualizerNonce().then( function ( nonce ) {

				var data = new FormData();
				data.append( 'action', 'spvz_export_pdf' );
				// The language of the page, so that the answer speaks it too ( EInvoicePress_Request_Language ).
				data.append( 'lang', SPEV.lang || '' );
				data.append( 'nonce', nonce );
				data.append( 'layout', layout );
				data.append( 'model', JSON.stringify( currentModel() ) );
				data.append( 'file', currentFile(), currentFile().name );

				return fetch( SPEV.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: data
				} );

			} ).then( function ( r ) {

				var contentType = r.headers.get( 'Content-Type' ) || '';

				if ( ! r.ok || contentType.indexOf( 'json' ) !== -1 ) {
					return r.json().catch( function () { return null; } ).then( function ( json ) {
						throw new Error( ( json && json.data && json.data.message ) ? json.data.message : SPEV.i18n.visualizerPdfFailed );
					} );
				}

				var filename = currentFile().name.replace( /\.[^.]+$/, '' ) + '-invoice.pdf';

				return r.blob().then( function ( blob ) {
					return { blob: blob, filename: filename };
				} );

			} ).then( function ( result ) {

				visualizerPdfBusy = false;
				visualizerPdfBtn.disabled = false;
				setVisualizerMessage( '' );

				var url = URL.createObjectURL( result.blob );
				var a   = document.createElement( 'a' );

				a.href     = url;
				a.download = result.filename;

				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );

				window.setTimeout( function () { URL.revokeObjectURL( url ); }, 1000 );

			} ).catch( function ( err ) {
				visualizerPdfBusy = false;
				visualizerPdfBtn.disabled = false;
				setVisualizerMessage( ( err && err.message ) ? err.message : SPEV.i18n.visualizerPdfFailed, 'error' );
			} );

		}

		if ( visualizerPdfBtn ) {
			visualizerPdfBtn.addEventListener( 'click', exportPdfInvoice );
		}

		/**
		 * Sends the identifiers of the invoice on screen to the Identifier Check plugin's `spic_check`
		 * endpoint ( see einvoice-viewer-identifier-check's class-spic-plugin.php ) for the confirmation
		 * online, and hands the answer to that plugin's module as the event `spic-online-results`.
		 *
		 * ⚠️ **NO TABLE.** The Identifier Check marks every identifier at its field by itself, without this button
		 * ( its assets/js/spic-annotate.js ). The button only exists while the online check is switched on
		 * ( `SPEV.hasIdentifierCheck`, see class-spev-plugin.php ), and its result goes to the fields as well: the
		 * module listens for the event, keeps the result for this invoice and redraws. Here only the one-line
		 * count stands, so the reader knows the click did something even when every identifier was confirmed
		 * and nothing is marked.
		 *
		 * ⚠️ **THE EVENT, NOT A FUNCTION OF THE ADD-ON.** This script imports nothing from the add-on; an event
		 * on the element reaches its module whether it was loaded before or after this script.
		 */

		function runIdentifierCheck() {

			if ( identifierCheckBusy || ! currentIdentifiers() ) {
				return;
			}

			identifierCheckBusy = true;
			identifierCheckBtn.disabled = true;

			if ( identifierCheckWrap ) {
				identifierCheckWrap.hidden = false;
			}

			setIdentifierCheckMessage( SPEV.i18n.identifierCheckLoading, 'info' );

			fetchIdentifierCheckNonce().then( function ( nonce ) {

				var data = new FormData();
				data.append( 'action', 'spic_check' );
				// The language of the page, so that the answer speaks it too ( EInvoicePress_Request_Language ).
				data.append( 'lang', SPEV.lang || '' );
				data.append( 'nonce', nonce );
				data.append( 'identifiers', JSON.stringify( currentIdentifiers() ) );

				return fetch( SPEV.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: data
				} );

			} ).then( function ( r ) {
				return r.json().catch( function () { return null; } );
			} ).then( function ( json ) {

				identifierCheckBusy = false;
				identifierCheckBtn.disabled = false;

				if ( ! json || ! json.success || ! json.data || ! Array.isArray( json.data.identifiers ) ) {
					var msg = ( json && json.data && json.data.message ) ? json.data.message : SPEV.i18n.identifierCheckFailed;
					setIdentifierCheckMessage( msg, 'error' );
					return;
				}

				var identifiers = json.data.identifiers;

				if ( identifiers.length === 0 ) {
					setIdentifierCheckMessage( SPEV.i18n.identifierCheckNone, 'info' );
					return;
				}

				var confirmed = 0;
				var notConfirmed = 0;

				identifiers.forEach( function ( item ) {
					if ( item.online && item.online.status === 'valid' ) { confirmed++; }
					if ( item.online && item.online.status === 'invalid' ) { notConfirmed++; }
				} );

				if ( confirmed + notConfirmed === 0 ) {
					setIdentifierCheckMessage( SPEV.i18n.identifierCheckOnlineNone, 'info' );
				} else {
					setIdentifierCheckMessage(
						String( SPEV.i18n.identifierCheckOnlineDone ).replace( '%1$d', confirmed ).replace( '%2$d', notConfirmed ),
						notConfirmed ? 'error' : 'info'
					);
				}

				root.dispatchEvent( new CustomEvent( 'spic-online-results', { detail: { identifiers: identifiers } } ) );

			} ).catch( function () {
				identifierCheckBusy = false;
				identifierCheckBtn.disabled = false;
				setIdentifierCheckMessage( SPEV.i18n.identifierCheckFailed, 'error' );
			} );

		}

		if ( identifierCheckBtn ) {
			identifierCheckBtn.addEventListener( 'click', runIdentifierCheck );
		}

		/**
		 * ====================================================================================
		 * THE BRIDGE TO THE WEB COMPONENT
		 * ====================================================================================
		 *
		 * ⚠️ **THE DROP ZONE AND THE WHOLE READING BELONG TO THE ELEMENT**: accepting the file, unpacking the PDF,
		 * reading the XML, building the model, rendering, operating the switches. This script learns the result
		 * through an event and takes care only of the add-ons.
		 *
		 * ⚠️ **THE EVENT ALSO DELIVERS THE ORIGINAL FILE.** The KoSIT validator checks the uploaded XML, not a version
		 * computed back from the model; without the file this script would have to ask the user for it a second
		 * time.
		 */
		root.addEventListener( 'einvoice-model-ready', function ( e ) {

			var d = e.detail || {};

			/**
			 * ⚠️ **ONLY THE CONTEXT IS REMEMBERED, NOT THE OTHER THREE.** The element hands out file, model and
			 * identifiers on request ( see above ); the recognised profile it does not, it stands only in this
			 * event.
			 */
			currentContext = d.context || null;

			/**
			 * ⚠️ BY THE FILE NAME, NOT BY THE MIME TYPE. The KoSIT validator accepts only XML ( see
			 * `class-spko-plugin.php::ajax_validate()` ); a ZUGFeRD PDF would come back as "Please choose an XML
			 * file." without anything gained. Browsers report `text/xml`, `application/xml` or nothing at all for
			 * `.xml`, depending on the system - the name is more reliable.
			 */
			currentIsXml = /\.xml$/i.test( ( currentFile() && currentFile().name ) || '' );

			showButtons();

		} );

		/**
		 * ⚠️ **"RESET" IN THE ELEMENT MUST CLEAN UP HERE TOO.** A button left standing would point at a document
		 * nobody sees any more - and would send it to the server at the click.
		 */
		root.addEventListener( 'einvoice-reset', function () {

			// The element cleans up file, model and identifiers itself - what remains here is what lies only here.
			currentContext = null;
			currentIsXml = false;

			hideButtons();

		} );

		/**
		 * ⚠️ **NO `initTooltips()` ON `einvoice-language-changed`.** The tooltips hang on the root node through event
		 * delegation, not on the badges; a second call would register the same four listeners a second time, not
		 * replace an outdated one. The element registers them itself, exactly once.
		 */

		hideButtons();

		wireExtensions( root );

	}


	/**
	 * ⚠️ **THE TOOLTIP DISPLAY LIVES IN THE CORE.** `parseTooltip()`, `buildTooltipEl()` and `initTooltips()` stand
	 * in `libs/einvoice-core-js/src/model/einvoice-viewer.mjs`.
	 *
	 * ⚠️ **WHY THEY CANNOT LIVE HERE:** they would reach into the element's shadow root while the element has a
	 * version of its own. Both would hang on the same badge, two boxes would appear - and the simplified one
	 * would not know the labelled lines of "Technical Details".
	 */

	/**
	 * ⚠️ **NO SHADOW ROOT IS BUILT BY HAND HERE.** Cloning a `<template>`, attaching a stylesheet, the fallback into
	 * the light DOM - `<einvoice-viewer>` does all of that itself, the same for all three forms of delivery.
	 *
	 * ⚠️ **NOBODY SETS THE CATALOGUE ADDRESSES AND THE STYLESHEET HERE.** They stand as `data-*` in the markup,
	 * because the element reads them in its `connectedCallback()` - that is, before this script gets its turn
	 * at all. See the reasoning at the place in `class-spev-plugin.php`.
	 *
	 * @param {HTMLElement} host .spev-host
	 * @return {void}
	 */

	function setup( host ) {

		var viewer = host.querySelector( 'einvoice-viewer' );

		if ( ! viewer ) {
			return;
		}

		init( viewer, host );

	}

	/**
	 * ⚠️ **THE ELEMENT MUST BE DEFINED BEFORE `init()` SUBSCRIBES TO ITS EVENTS.** Its bundle loads as an ES
	 * module, that is deferred - at the moment this classic script runs, `<einvoice-viewer>` can still be an
	 * unknown element. `customElements.whenDefined()` waits for it; without the wait a subscription would hang
	 * on an element that does not have its shadow root yet, and the first upload would run into nothing.
	 */
	function boot() {

		var start = function () {

			var hosts = document.querySelectorAll( '.spev-host' );

			for ( var i = 0; i < hosts.length; i++ ) {
				setup( hosts[ i ] );
			}

		};

		if ( window.customElements && customElements.whenDefined ) {
			customElements.whenDefined( 'einvoice-viewer' ).then( start );
		} else {
			start();
		}

	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

}() );
