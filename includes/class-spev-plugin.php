<?php
/**
 * SP E-Invoice Viewer - main orchestrator ( shortcode, assets, AJAX ).
 *
 * @package SP_EInvoice_Viewer
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) )
	exit;


class SPEV_Plugin
{


	private static $instance = null;

	/** @var bool whether the assets were enqueued / localized */
	private $enqueued = false;


	/**
	 * Singleton.
	 *
	 * @return	SPEV_Plugin
	 */

	public static function instance()
	{
		if( self::$instance === null )
			self::$instance = new self();

		return( self::$instance );
	}


	private function __construct()
	{

		// The bundled translations are registered on `init` in the main plugin file; the reason stands
		// there. ⚠️ They only reach the PHP side ( settings page, notices ); the bundle reads its own
		// tables under i18n/, see register_i18n().

		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// ⚠️ Turns the bundle into an ES module - see the reasoning at its registration.
		add_filter( 'script_loader_tag', array( $this, 'as_module' ), 10, 3 );

		// ⚠️ NO spev_nonce / spev_render / einvoice/v1/render. The shortcode reads and parses the file entirely in
		// the browser ( see assets/js/spev-uploader.js and the TreeReader/ModelAssembler/QubaTemplate pipeline
		// <einvoice-viewer> uses ), so there is no upload endpoint, no nonce for one, and no rate limit to apply
		// to it. The sibling extensions ( KoSIT validator, Exporter, Visualizer, Identifier Check, Inbound Check,
		// Technical Details ) never called such an endpoint of this plugin; each has its own AJAX action and
		// receives the model built in the browser.

		// The daily limit per role, asked by the element before it reads an invoice ( see ajax_quota() ).
		add_action( 'wp_ajax_spev_quota', array( $this, 'ajax_quota' ) );
		add_action( 'wp_ajax_nopriv_spev_quota', array( $this, 'ajax_quota' ) );

		// "Available from role" for the add-ons; an add-on asks this filter in its own AJAX handlers.
		add_filter( 'spev_extension_allowed', array( __CLASS__, 'filter_extension_allowed' ), 10, 2 );

	}


	/* ============================================================== daily limit per role */


	/** @var \SpecialPress\EInvoice\Host\RoleRateLimit|null */
	private static $ratelimit = null;


	/**
	 * The limiter of the viewer.
	 *
	 * ⚠️ THE DOMAIN IS `spev_view`, NOT `spev`. The options `spev_daily_limit` and `spev_trust_proxy` belonged to the
	 * upload brake removed with 2.12.0 and may still stand in a database ( see SPEV_Settings::register() ). Under the
	 * domain `spev` that old number would become the default of every role, and a site would be limited after an
	 * update without anybody having set it. Under `spev_view` nothing is saved yet, so every role starts unlimited.
	 *
	 * @return	\SpecialPress\EInvoice\Host\RoleRateLimit
	 * @since	2.35.0
	 */

	public static function ratelimit()
	{
		if( self::$ratelimit === null )
			self::$ratelimit = new \SpecialPress\EInvoice\Host\RoleRateLimit( 'spev_view', 0 );

		return( self::$ratelimit );
	}


	/**
	 * Does a number apply to the current visitor at all? Only then the element asks the server before each invoice.
	 *
	 * @return	bool
	 * @since	2.35.0
	 */

	public static function quota_active()
	{
		return( ! current_user_can( 'manage_options' ) && self::ratelimit()->limitForCurrentUser() !== null );
	}


	/**
	 * The refusal, by its reason.
	 *
	 * ⚠️ TWO LITERAL __() CALLS, NOT ONE WITH A VARIABLE: only a literal reaches the .pot.
	 *
	 * @param	array	$status	RoleRateLimit::status()
	 * @return	string
	 * @since	2.35.0
	 */

	public static function limit_message( array $status )
	{
		if( ( $status[ 'reason' ] ?? '' ) === 'role' )
			return( __( 'The e-invoice viewer is not available for your account.', 'einvoice-viewer-for-peppol-en16931' ) );

		return( __( 'You have reached the daily limit of invoices. Please try again tomorrow.', 'einvoice-viewer-for-peppol-en16931' ) );
	}


	/**
	 * `admin-ajax.php?action=spev_quota` - asked by the element before it reads a chosen invoice; counts one invoice.
	 *
	 * ⚠️ NOTHING OF THE INVOICE IS SENT. The request carries no file and no field; the server counts the visitor, not
	 * the document. The viewer keeps its promise that the invoice stays in the browser.
	 *
	 * ⚠️ NO NONCE. The page with the viewer is often cached for guests; a nonce in a cached page expires after a day,
	 * and every guest would then be refused. The request can only spend the caller's own counter, so a forged request
	 * from another site costs that visitor one invoice and nobody else anything.
	 *
	 * ⚠️ THE VIEWER RUNS IN THE BROWSER, SO THE LIMIT CAN BE CIRCUMVENTED by someone who changes the script there. It
	 * stops ordinary heavy use and scripts that use the page as it is; a role set to 0 is also refused when the page
	 * is built ( render_shortcode() ), which no change in the browser undoes.
	 *
	 * @return	void
	 * @since	2.35.0
	 */

	public function ajax_quota()
	{
		EInvoicePress_Request_Language::switch_to_page_language();

		$limiter = self::ratelimit();
		$status  = $limiter->status();

		if( ! $status[ 'allowed' ] )
			wp_send_json_success( array( 'allowed' => false, 'message' => self::limit_message( $status ) ) );

		$limiter->increment();

		wp_send_json_success( array( 'allowed' => true, 'remaining' => $limiter->status()[ 'remaining' ] ) );
	}


	/**
	 * The texts of the validator buttons, from the validators themselves.
	 *
	 * ⚠️ A VALIDATOR NAMES ITS OWN BUTTON. Mustang and VAMPIRE answer bridge_texts() from 1.6.0 and 1.10.0 on, and
	 * without it they get no button at all ( see enqueue_assets() ). Only for KoSIT the viewer keeps its own three texts
	 * as the fallback, because its button exists since long before bridge_texts(); a KoSIT validator without the
	 * method still gets a named button.
	 *
	 * @param	bool	$kosit
	 * @param	bool	$mustang
	 * @param	bool	$vampire
	 * @return	array<string,array{button:string,loading:string,failed:string}>
	 * @since	2.35.0
	 */

	private static function validator_texts( $kosit, $mustang, $vampire )
	{
		$out = array();

		if( $kosit )
			$out[ 'kosit' ] = ( class_exists( 'SPKO_Plugin' ) && method_exists( 'SPKO_Plugin', 'bridge_texts' ) )
				? \SPKO_Plugin::bridge_texts()
				: array(
					'button'  => __( 'Validate with KoSIT', 'einvoice-viewer-for-peppol-en16931' ),
					'loading' => __( 'Running the KoSIT validator …', 'einvoice-viewer-for-peppol-en16931' ),
					'failed'  => __( 'The validation failed. Please try again.', 'einvoice-viewer-for-peppol-en16931' ),
				);

		if( $mustang )
			$out[ 'mustang' ] = \SPMV_Plugin::bridge_texts();

		if( $vampire )
			$out[ 'vampire' ] = \SPPV_Plugin::bridge_texts();

		return( $out );
	}


	/* ============================================================== add-ons: available from role */


	/** The option that holds, per add-on, the level from which it is available ( RoleGate::LEVELS ). */
	const EXTENSION_OPTION = 'spev_extension_roles';


	/**
	 * May the current visitor use this add-on?
	 *
	 * @param	string	$slug	technical-details | extended-profiles | visualizer | identifier-check | inbound-check | exporter | kosit | mustang | vampire
	 * @return	bool
	 * @since	2.35.0
	 */

	public static function extension_allowed( $slug )
	{
		$levels = get_option( self::EXTENSION_OPTION, array() );

		return( \SpecialPress\EInvoice\Host\RoleGate::allowed( is_array( $levels ) ? (string) ( $levels[ $slug ] ?? '' ) : '' ) );
	}


	/**
	 * Filter `spev_extension_allowed`: an add-on asks in its own AJAX handler whether the visitor may use it, so a
	 * request built by hand is refused, not only the button hidden.
	 *
	 * @param	bool	$allowed	true unless an earlier filter said otherwise
	 * @param	string	$slug
	 * @return	bool
	 * @since	2.35.0
	 */

	public static function filter_extension_allowed( $allowed, $slug )
	{
		return( (bool) $allowed && self::extension_allowed( (string) $slug ) );
	}



	/**
	 * The stylesheets of the design kit the operator picked, for this plugin's kit folder.
	 *
	 * ⚠️ **ONE PLACE FOR THE KIT NAME.** The choice comes from the shared option `einvoice_design_kit`, and a kit
	 * that is missing in `assets/css/` falls back to Kit H without a word, see EInvoicePress_Design_Kit.
	 *
	 * ⚠️ `$versioned = false` FOR `wp_register_style()`. That function appends `?ver=` itself, and
	 * passing `null` as its version instead trips the wordpress.org check for a missing version.
	 *
	 * @param	bool	$versioned	true to append `?ver=` to the addresses
	 * @return	array{tokens:string,kit:string,slug:string}
	 * @since	2.26.0
	 */

	public static function kit_css( $versioned = true )
	{
		return( EInvoicePress_Design_Kit::stylesheets( SPEV_DIR . 'assets/css/', SPEV_URL . 'assets/css/', $versioned ? SPEV_VERSION : '' ) );
	}



	/**
	 * Register the shortcode.
	 *
	 * @return	void
	 */

	public function register_shortcode()
	{
		add_shortcode( SPEV_SHORTCODE, array( $this, 'render_shortcode' ) );
	}



	/**
	 * Register the assets and enqueue them early when the current post uses the shortcode.
	 *
	 * Enqueuing on wp_enqueue_scripts ( instead of lazily inside the shortcode ) is what makes the
	 * uploader work reliably with block themes such as Twenty Twenty-Five, where the_content can be
	 * rendered too late for a late enqueue to reach the footer.
	 *
	 * @return	void
	 */

	public function register_assets()
	{

		// ⚠️ ONLY THE KIT'S TOKEN FILE IN THE DOCUMENT, NEVER THE WHOLE KIT. `spev-viewer.css` also styles the
		// control shell, and that lies in the light DOM of the page; its colours come from the kit's tokens. The
		// token file carries four selectors and ZERO non-token declarations and can restyle nothing in someone
		// else's page. The full kit carries 130 unqualified single classes and twelve bare element selectors
		// ( html, body, a, button … ) - it goes into the shadow root through `data-css` alone.
		wp_register_style( 'spev-kit', self::kit_css( false )[ 'tokens' ], array(), SPEV_VERSION );
		// ⚠️ THE KIT'S BUILDING BLOCKS ONLY IN THE LIMITED FORM, `@scope ( [data-kit-scope] )`. They reach the buttons,
		// selects and messages of the add-ons, which lie in the light DOM of `<einvoice-viewer data-kit-scope>`, and
		// nothing else of the page. The file is generated by kit-sync from spt-kit.components.css.
		wp_register_style( 'spev-kit-scoped', SPEV_URL . 'assets/css/spt-kit.components.scoped.css', array( 'spev-kit' ), self::asset_ver( 'assets/css/spt-kit.components.scoped.css' ) );
		wp_register_style( 'spev-viewer', SPEV_URL . 'assets/css/spev-viewer.css', array( 'spev-kit', 'spev-kit-scoped' ), self::asset_ver( 'assets/css/spev-viewer.css' ) );

		$this->register_i18n();

		// ⚠️ NOT type="module" - spev-uploader.js stays a classic script ( its own dynamic import() works
		// identically either way, see that file's own comment ), so its enqueue/localize/dependency handling
		// needs nothing special.
		wp_register_script( 'spev-uploader', SPEV_URL . 'assets/js/spev-uploader.js', array( 'spev-i18n' ), self::asset_ver( 'assets/js/spev-uploader.js' ), true );

		if( $this->post_has_shortcode() )
			$this->enqueue_assets();

	}



	/**
	 * Registers the language table the bundle reads at runtime.
	 *
	 * ⚠️ **NOT `wp_set_script_translations()`, ON PURPOSE.** No `wp.i18n` and no `wp_set_script_translations()` for
	 * the JavaScript part: wp.org does not require it, Plugin Check only looks at translation functions in the
	 * PHP part, and own tables are no reason for rejection. Only the PHP side ( settings page, admin notices )
	 * goes through `__()` and the `.mo`.
	 *
	 * ⚠️ **THE FILE IS A SCRIPT, NOT JSON, AND THAT IS WHY IT CAN BE SHARED WITH THE APP.** The very
	 * same `i18n/<lang>.json.js` is loaded by the app from a plain folder, where `fetch()` is refused
	 * because a page opened by double-click has the origin "null". A `<script src>` is allowed there.
	 * One file, two hosts - which is the whole point of generating it from the plugin's `.po`.
	 *
	 * ⚠️ **A MISSING FILE IS NOT AN ERROR.** English has no table, because the source strings in the
	 * code are English. For any other language the bundle falls back to its own built-in table, so
	 * nothing breaks when the file is absent.
	 *
	 * @return	void
	 */

	private function register_i18n()
	{

		/*
		 * ⚠️ **NO CLASS OF A LIBRARY MAY STAND HERE.**
		 *
		 * A call such as `\SpecialPress\EInvoiceView\Context::normalize_language()` ends with "Class not found",
		 * as a **fatal error while building every page** that carries the viewer: the free viewer has no PHP
		 * reading core, no `vendor/` and no Composer dependency - see the comment in the main file.
		 *
		 * ⚠️ **IN THE DEVELOPMENT INSTALLATION IT DOES NOT SHOW.** A neighbouring plugin that ships the same class
		 * and happens to declare it first is always active there. In an installation where **only** the viewer
		 * runs, the class does not exist. The main file warns about the same trap for `einvoice-host-wp` a few
		 * lines further up ( found live on a fresh installation ); here it is the other direction.
		 *
		 * ⚠️ **NO LIST OF ALLOWED LANGUAGES IS NEEDED EITHER.** Which language this plugin can offer is said by the
		 * folder `i18n/`: if a table lies there, it works, otherwise not. The `is_readable()` below **is** the
		 * check. A fourth language is thus a file that is added, not a change to the code.
		 */
		$lang = strtolower( substr( (string) determine_locale(), 0, 2 ) );

		$index = SPEV_DIR . 'i18n/index.json.js';
		$liste = is_readable( $index );

		// The list first: it names the available languages, and the bundle decides from it whether it can offer
		// the chosen one at all.
		if( $liste )
			wp_register_script( 'spev-i18n-index', SPEV_URL . 'i18n/index.json.js', array(), self::asset_ver( 'i18n/index.json.js' ), false );

		/*
		 * ⚠️ **THE DEPENDENCY IS COMPUTED ONCE, NOT WRITTEN TWICE.** Naming `spev-i18n-index` unconditionally would
		 * hang the table on a handle that does not exist when only the list is missing - and WordPress leaves out
		 * a script with an unmet dependency **silently**. Left out would then be not the translation but, through
		 * `spev-uploader`, the whole interface.
		 */
		$needs = $liste ? array( 'spev-i18n-index' ) : array();

		$tabelle = SPEV_DIR . 'i18n/' . $lang . '.json.js';

		if( $lang !== 'en' && is_readable( $tabelle ) )
		{
			wp_register_script( 'spev-i18n', SPEV_URL . 'i18n/' . $lang . '.json.js', $needs, SPEV_VERSION, false );

			return;
		}

		/*
		 * ⚠️ The handle `spev-i18n` must be registered even without a table. The upload script names it as a
		 * dependency, and a dependency that does not exist makes WordPress silently leave out the dependent
		 * script - then the whole interface would be missing, not only a translation.
		 */

		wp_register_script( 'spev-i18n', false, $needs, SPEV_VERSION, false );

	}



	/**
	 * Does the current singular post/page contain the shortcode ?
	 *
	 * @return	bool
	 */

	private function post_has_shortcode()
	{

		if( ! is_singular() )
			return( (bool) apply_filters( 'spev_force_enqueue', false ) );

		$post = get_post();

		$has = ( $post && has_shortcode( (string) $post->post_content, SPEV_SHORTCODE ) );

		return( (bool) apply_filters( 'spev_force_enqueue', $has ) );

	}



	/**
	 * Sets `type="module"` on the bundle of the web component.
	 *
	 * ⚠️ ONLY ON THIS ONE HANDLE. A filter that touches every script silently turns the next classic script of a
	 * foreign plugin into a module - and ES modules run in strict mode, deferred and with a scope of their own.
	 * The error would then show in a foreign plugin, not here.
	 *
	 * ⚠️ **THE MARKUP IS CHANGED, NOT REWRITTEN.** A composed `<script …></script>` line would be shorter, but it
	 * would discard everything WordPress and other filters have already attached to the element - `id`,
	 * `defer`, `data-wp-strategy`, the `nonce` of a Content Security Policy. And it triggers
	 * `WordPress.WP.EnqueuedResources.NonEnqueuedScript`, which the wordpress.org check reports as an error,
	 * because a literal script element in PHP usually means someone works around `wp_enqueue_script()`. Here the
	 * script is queued properly; only its type changes.
	 *
	 * ⚠️ **AN EXISTING `type` MUST GO, OTHERWISE TWO STAND THERE.** A theme without
	 * `add_theme_support( 'html5', [ 'script' ] )` makes WordPress output `type='text/javascript'`. Two `type`
	 * attributes on one element are invalid, and which one applies is decided by the browser - for HTML the
	 * **first**. That would be exactly the wrong one.
	 *
	 * @param	string	$tag
	 * @param	string	$handle
	 * @param	string	$src
	 * @return	string
	 */

	public function as_module( $tag, $handle, $src )
	{

		if ( $handle !== 'spev-viewer-element' )
			return( $tag );

		$tag = preg_replace( '/\stype=([\'"])[^\'"]*\1/', '', $tag );

		return( preg_replace( '/<script\s/', '<script type="module" ', $tag, 1 ) );

	}



	/**
	 * Enqueue + localize the assets ( once per request ).
	 *
	 * @return	void
	 */

	private function enqueue_assets()
	{

		// ⚠️ RE-REGISTERED HERE TOO, NOT JUST IN register_assets() - FOUND LIVE on a fresh install
		// with Twenty Twenty-Five ( a Full Site Editing / block theme ). register_assets() is hooked on
		// 'wp_enqueue_scripts', which fires BEFORE the_content/shortcodes in a classic PHP theme, but NOT in a
		// block theme's "canvas" template: get_the_block_template_html() renders the ENTIRE page - including the
		// post-content block, which runs this shortcode - in ONE PASS, and wp_head() ( which fires
		// wp_enqueue_scripts ) only runs AFTERWARDS, once that already-rendered HTML is echoed into the page. So
		// render_shortcode()'s own call to enqueue_assets() below can be the FIRST call, running before
		// 'spev-uploader' is registered at all - wp_enqueue_script( $handle ) with no args on an unregistered
		// handle still queues it ( WordPress prints it once something registers the handle later ), but
		// wp_localize_script() needs the handle to ALREADY be registered to attach data, so it silently returns
		// false and the $enqueued guard below then skips the correctly-timed second attempt from
		// register_assets() entirely - the uploader script loads with the right src, but SPEV is never defined,
		// so spev-uploader.js's own `if ( typeof window.SPEV === 'undefined' ) return;` guard makes it a
		// complete, silent no-op. Registering ( idempotently - re-registering with the same arguments is
		// harmless ) right here removes the ordering assumption entirely: whichever of
		// register_assets()/render_shortcode() calls this method first, the handles are already registered by
		// the time wp_localize_script() runs.
		// ⚠️ ONLY THE KIT'S TOKEN FILE IN THE DOCUMENT, NEVER THE WHOLE KIT. `spev-viewer.css` also styles the
		// control shell, and that lies in the light DOM of the page; its colours come from the kit's tokens. The
		// token file carries four selectors and ZERO non-token declarations and can restyle nothing in someone
		// else's page. The full kit carries 130 unqualified single classes and twelve bare element selectors
		// ( html, body, a, button … ) - it goes into the shadow root through `data-css` alone.
		wp_register_style( 'spev-kit', self::kit_css( false )[ 'tokens' ], array(), SPEV_VERSION );
		// ⚠️ THE KIT'S BUILDING BLOCKS ONLY IN THE LIMITED FORM, `@scope ( [data-kit-scope] )`. They reach the buttons,
		// selects and messages of the add-ons, which lie in the light DOM of `<einvoice-viewer data-kit-scope>`, and
		// nothing else of the page. The file is generated by kit-sync from spt-kit.components.css.
		wp_register_style( 'spev-kit-scoped', SPEV_URL . 'assets/css/spt-kit.components.scoped.css', array( 'spev-kit' ), self::asset_ver( 'assets/css/spt-kit.components.scoped.css' ) );
		wp_register_style( 'spev-viewer', SPEV_URL . 'assets/css/spev-viewer.css', array( 'spev-kit', 'spev-kit-scoped' ), self::asset_ver( 'assets/css/spev-viewer.css' ) );

		/*
		 * ⚠️ **THE SAME ARGUMENTS AS IN `register_assets()`, EXACTLY THE SAME.** `wp_register_script()` ignores a second
		 * registration of the same handle completely - whoever comes first fixes the dependencies. With
		 * `array()` here instead of `array( 'spev-i18n' )`, the order of the two calls would decide whether the
		 * language table is loaded. That would be an error that shows on one page and not on the next.
		 */

		$this->register_i18n();

		wp_register_script( 'spev-uploader', SPEV_URL . 'assets/js/spev-uploader.js', array( 'spev-i18n' ), self::asset_ver( 'assets/js/spev-uploader.js' ), true );

		/**
		 * The web component that provides the whole interface.
		 *
		 * ⚠️ **IT MUST BE LOADED AS `type="module"`** - the bundle is an ES module and aborts with a syntax error as an
		 * ordinary script. `<einvoice-viewer>` would then stay an empty box, without a message. WordPress has
		 * `wp_enqueue_script_module()` for that, but only from 6.5; this plugin requires 5.8, so the filter below
		 * sets the attribute.
		 *
		 * ⚠️ AND IT IS NO DEPENDENCY OF `spev-uploader` IN THE SENSE OF `wp_register_script()`. A module loads deferred
		 * ( like `defer` ), a classic script does not; a dependency would only decide the order in the markup, not
		 * that of execution. The uploader waits for the element instead - see its own reasoning there.
		 */
		wp_register_script( 'spev-viewer-element', SPEV_URL . 'assets/js/einvoice-viewer.js', array(), self::asset_ver( 'assets/js/einvoice-viewer.js' ), true );

		wp_enqueue_style( 'spev-viewer' );
		wp_enqueue_script( 'spev-viewer-element' );
		wp_enqueue_script( 'spev-uploader' );

		// ⚠️ CHECKED HERE, NOT IN THE CONSTRUCTOR. This method runs on 'wp_enqueue_scripts', which -
		// unlike the constructor, which runs the instant THIS plugin's own main file is include()'d -
		// fires only after every active plugin's main file has already run. class_exists() checked at
		// construction time would depend on plugin load order ( folder names starting with a letter
		// ordered before this one's "einvoice-viewer-…" would already be loaded, later ones would not
		// ) - here it is safe regardless of that order. Same reasoning as the KoSIT validator's own
		// register_explainer_bridge(), one plugin over.
		//
		// ⚠️ EVERY ADD-ON BELOW ALSO ASKS extension_allowed(): "available from role" on the settings page. Below that
		// role the viewer treats the add-on as not installed, so its button, its module and its texts never reach
		// the page. The add-on's own AJAX handlers ask the filter `spev_extension_allowed` as well, so a request
		// built by hand is refused too.
		$kosit_active = class_exists( 'SPKO_Plugin' ) && self::extension_allowed( 'kosit' );

		if( $kosit_active )
			SPKO_Plugin::instance()->enqueue_bridge_script();

		// ⚠️ THE SAME BRIDGE FOR THE MUSTANG AND THE VAMPIRE VALIDATOR, each with its own button. method_exists()
		// keeps a validator older than its bridge ( Mustang before 1.6.0, VAMPIRE before 1.10.0 ) without a button
		// instead of ending in a fatal error.
		$mustang_active = class_exists( 'SPMV_Plugin' ) && method_exists( 'SPMV_Plugin', 'enqueue_bridge_script' ) && method_exists( 'SPMV_Plugin', 'bridge_texts' ) && self::extension_allowed( 'mustang' );

		if( $mustang_active )
			SPMV_Plugin::instance()->enqueue_bridge_script();

		$vampire_active = class_exists( 'SPPV_Plugin' ) && method_exists( 'SPPV_Plugin', 'enqueue_bridge_script' ) && method_exists( 'SPPV_Plugin', 'bridge_texts' ) && self::extension_allowed( 'vampire' );

		if( $vampire_active )
			SPPV_Plugin::instance()->enqueue_bridge_script();

		// ⚠️ NO enqueue_bridge_script() COUNTERPART HERE. Unlike the KoSIT validator, the exporter has
		// no interactive rendering of its own to reuse ( see einvoice-viewer-exporter/STATUS.md ) - the
		// "Export to Excel" button only needs a nonce and an admin-ajax.php action, both already
		// handled entirely inside spev-uploader.js's own fetch()/Blob/download flow. Nothing of the
		// exporter's needs to be enqueued on this page at all.
		$exporter_active = class_exists( 'SPVX_Plugin' ) && self::extension_allowed( 'exporter' );

		// ⚠️ SAME class_exists() TIMING REASONING AS ABOVE, NOT A NEW PATTERN. Technical Details needs no bridge
		// script of its own here ( unlike the KoSIT validator's enqueue_bridge_script() ) - its popup is a plain
		// `window.open()` to a URL, built entirely in spev-uploader.js from data this plugin already has
		// ( SPEV.ajaxUrl, and a context object the JS itself computes client-side via parse.mjs's
		// detectTechnicalContext(), not a server response ).
		$technical_details_active = class_exists( 'SVTD_Plugin' ) && self::extension_allowed( 'technical-details' );

		// ⚠️ SAME class_exists() TIMING REASONING, NOT A NEW PATTERN - see einvoice-viewer-visualizer's
		// own class-spvz-plugin.php for the AJAX endpoint this button calls ( `spvz_visualize`, works
		// from the ALREADY READ document model like the exporter, not the raw file - offered for a
		// PDF upload just as much as an XML one ). Only the boolean is needed here, for the JS's
		// `SPEV.hasVisualizer` - the layout <select>'s actual <option> list is built in
		// render_shortcode() instead, since that needs the labelled data at template-render time, not
		// just a flag to read later.
		$visualizer_active = class_exists( 'SPVZ_Plugin' ) && self::extension_allowed( 'visualizer' );

		/**
		 * `pdf_export_enabled()` is the gate of the paid PDF level - a filter, no licence check. method_exists()
		 * guarded so a Visualizer OLDER than 1.5.0 ( before this method existed ) still shows the plain HTML
		 * "Visualizer" button without the PDF one, instead of a fatal error.
		 */

		$visualizer_pdf_active = $visualizer_active && method_exists( 'SPVZ_Plugin', 'pdf_export_enabled' ) && \SPVZ_Plugin::pdf_export_enabled();

		// ⚠️ SAME class_exists() TIMING REASONING, NOT A NEW PATTERN. Unlike the KoSIT validator, this
		// plugin's own AJAX endpoint ( `spic_check` ) rebuilds the model itself via
		// `SPEV_Renderer::model()` ( same "one read, not a second Reader" rule every sibling in this
		// family follows ) - no bridge script needs to be enqueued here, only the boolean flag for
		// `SPEV.hasIdentifierCheck`. See einvoice-viewer-identifier-check's own class-spic-plugin.php.
		$identifier_check_active = class_exists( 'SPIC_Plugin' ) && self::extension_allowed( 'identifier-check' );

		// ⚠️ THE BUTTON ONLY FOR THE ONLINE CHECK. The Identifier Check marks every identifier at its field by
		// itself, without a button ( its assets/js/spic-annotate.js ); what the button still does is the
		// confirmation online, and that needs the add-on's switch and an API key. An add-on older than
		// online_active() keeps its button, because there the button is the only way to the local check.
		$identifier_check_online = $identifier_check_active && ( ! method_exists( 'SPIC_Plugin', 'online_active' ) || \SPIC_Plugin::online_active() );

		// ⚠️ THE INBOUND CHECK BRINGS ITS OWN BUTTON AND REPORT ( from add-on 1.3.0, ENTSCHEIDUNGEN.md, fifteenth edition,
		// F3.5 ). The viewer only asks it to queue its script, as with the validators' bridges above; an add-on older than
		// enqueue_viewer_script() gets no button, because the viewer no longer draws one for it.
		$inbound_check_active = class_exists( 'SPIV_Plugin' ) && self::extension_allowed( 'inbound-check' );

		if( $inbound_check_active && method_exists( 'SPIV_Plugin', 'enqueue_viewer_script' ) )
			SPIV_Plugin::instance()->enqueue_viewer_script();

		// ⚠️ EXTENDED PROFILES HAS NO TOOLBAR BUTTON AND NO AJAX ENDPOINT OF ITS OWN. It hooks the
		// 'spev_invoice_model' filter, which fires only INSIDE a server-side ModelBuilder::build() call; this
		// shortcode renders client-side, so that filter never fires for a normal upload, and without the module
		// below the add-on's fields would never appear ( found live, see this plugin's own
		// STATUS.md ). `svze-extend.js` is the working path: a dependency-free client-side port, called directly
		// from the main parse chain in spev-uploader.js.
		$extended_profiles_active = class_exists( 'SVZE_Plugin' ) && self::extension_allowed( 'extended-profiles' );

		if( $this->enqueued )
			return;

		// ⚠️ THE LANGUAGE OF THE PAGE, NOT THE PROFILE LANGUAGE OF A LOGGED-IN USER - see tooltip_lang().
		// The strings localized here, the table register_i18n() loads and the element's data-lang must
		// all come from the same determine_locale(), or one visitor sees two languages.

		$switched_locale = switch_to_locale( determine_locale() );

		wp_localize_script( 'spev-uploader', 'SPEV', array(
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			/**
			 * ⚠️ **NO READING ADDRESSES IN THIS OBJECT.** `cssUrl`, `maxSize`, the model bundle, pdf.js and the
			 * catalogues belong to `<einvoice-viewer>`, and the element gets its addresses as `data-*` attributes in
			 * the markup of `render_shortcode()` - not through this object.
			 *
			 * ⚠️ **THE REASON IS AN ORDER, NOT A MATTER OF TASTE.** The element reads its attributes in
			 * `connectedCallback()`, that is when the browser upgrades it; `wp_localize_script()` writes its object
			 * before the **classic** script, which explicitly waits for `customElements.whenDefined()` and so runs
			 * only **afterwards**. Set from there, the addresses would come too late.
			 *
			 * Why pdf.js is shipped and why its files are called `.js` and not `.mjs` stands at the markup in
			 * `render_shortcode()`; why pdf.js is only the fallback, in `extractPdfXml()` in
			 * `libs/einvoice-core-js/src/model/parse.mjs`.
			 */
			'lang'               => $this->tooltip_lang(),
			'hasKositValidator'  => $kosit_active,
			'hasMustangValidator' => $mustang_active,
			'hasVampireValidator' => $vampire_active,
			// ⚠️ THE TEXTS OF THE VALIDATOR BUTTONS COME FROM THE VALIDATORS ( bridge_texts() ), in their own text
			// domain; the viewer only passes them on. See validator_texts().
			'validators'         => self::validator_texts( $kosit_active, $mustang_active, $vampire_active ),
			'hasExporter'        => $exporter_active,
			'hasTechnicalDetails' => $technical_details_active,
			'hasVisualizer'      => $visualizer_active,
			'hasVisualizerPdf'   => $visualizer_pdf_active,
			'hasIdentifierCheck' => $identifier_check_online,
			// Only set when the add-on is active - its own plugin folder, not this one's, like every module URL in
			// this family pointing at its own plugin's assets/ ( an absolute URL, because import() resolves a
			// relative one against the importing script ).
			'extendedProfilesBundleUrl' => $extended_profiles_active ? self::addon_module_url( SVZE_DIR, SVZE_URL, 'svze-extend', SVZE_VERSION ) : '',
			// ⚠️ The names of the extension fields for the tooltips ( Extended Profiles 1.3.0 and later ).
			// method_exists(): an older copy of the add-on has no packages, and the tooltips then stay as
			// they are - the cards above still appear.
			//
			// ⚠️ A LIST OF ADDRESSES, NOT ONE. The add-on carries one field package per extension ( ZUGFeRD /
			// Factur-X EXTENDED, XRechnung Extension, EXTENDED-CTC-FR ), and spev-uploader.js merges them all into
			// the tooltips. Read only by spev-uploader.js.
			// ⚠️ An add-on older than fields_urls() still answers fields_url() with its one package, so the
			// BT-X names of ZUGFeRD EXTENDED keep working with it.
			'extendedProfilesFieldsUrls' => $this->extended_profiles_fields_urls( $extended_profiles_active ),
			// ⚠️ THE SAME ROOT CAUSE AND THE SAME FIX PATTERN AS extendedProfilesBundleUrl ABOVE ( found live ):
			// Technical Details' `add_filter( 'spev_code_tooltip', ... )` (class-svtd-plugin.php) fires
			// only INSIDE a server-side render; nothing calls that filter for the client-rendered path.
			// svtd-extend.js is the working path - see that add-on's own STATUS.md.
			'technicalDetailsExtendUrl' => $technical_details_active ? self::addon_module_url( SVTD_DIR, SVTD_URL, 'svtd-extend', SVTD_VERSION ) : '',
			// ⚠️ Only true when a number applies to this visitor; otherwise the element reads without asking, as before.
			'quotaActive'        => self::quota_active(),
			'i18n'               => array(
				/**
				 * ⚠️ **NO MESSAGES OF THE READING PROCESS HERE.** Reading belongs to `<einvoice-viewer>`, and the
				 * element translates its own messages through `Labels.mjs` - one table per language, generated from
				 * the same `.po` of this plugin.
				 *
				 * ⚠️ **THE MESSAGES OF THE ADD-ONS STAY.** They belong to this plugin and its add-ons, not to the tool;
				 * translating them through the core would mean putting the texts of a paid add-on into a GPL library.
				 */
				/* the additional, optional KoSIT validation - see spev-uploader.js, "validate with the KoSIT validator" */
				'kositLoading' => esc_html__( 'Running the KoSIT validator …', 'einvoice-viewer-for-peppol-en16931' ),
				'kositFailed'  => esc_html__( 'The validation failed. Please try again.', 'einvoice-viewer-for-peppol-en16931' ),
				// The button of a validator switches its report off and on once it is shown ( spev-uploader.js ).
				'hideReport'   => esc_html__( 'Hide validation report', 'einvoice-viewer-for-peppol-en16931' ),
				'showReport'   => esc_html__( 'Show validation report', 'einvoice-viewer-for-peppol-en16931' ),
				/* the optional Excel export - see spev-uploader.js, "export to Excel" */
				'exportLoading' => esc_html__( 'Preparing the Excel file …', 'einvoice-viewer-for-peppol-en16931' ),
				'exportFailed'  => esc_html__( 'The export failed. Please try again.', 'einvoice-viewer-for-peppol-en16931' ),
				/* the optional Visualizer - see spev-uploader.js, "visualize" */
				'visualizerLoading' => esc_html__( 'Preparing the printable view …', 'einvoice-viewer-for-peppol-en16931' ),
				'visualizerFailed'  => esc_html__( 'The Visualizer failed. Please try again.', 'einvoice-viewer-for-peppol-en16931' ),
				'visualizerPopupBlocked' => esc_html__( 'Your browser blocked the pop-up window. Please allow pop-ups for this site and try again.', 'einvoice-viewer-for-peppol-en16931' ),
				/* the optional Visualizer PDF export - see spev-uploader.js, "export as PDF" */
				'visualizerPdfLoading' => esc_html__( 'Preparing the PDF …', 'einvoice-viewer-for-peppol-en16931' ),
				'visualizerPdfFailed'  => esc_html__( 'The PDF export failed. Please try again.', 'einvoice-viewer-for-peppol-en16931' ),
				/* the optional Identifier Check - see spev-uploader.js, "identifier check" */
				'identifierCheckLoading' => esc_html__( 'Checking identifiers …', 'einvoice-viewer-for-peppol-en16931' ),
				'identifierCheckFailed'  => esc_html__( 'The identifier check failed. Please try again.', 'einvoice-viewer-for-peppol-en16931' ),
				'identifierCheckNone'    => esc_html__( 'No checkable identifiers were found on this invoice.', 'einvoice-viewer-for-peppol-en16931' ),
				/* translators: 1: number of identifiers the online check confirmed, 2: number it did not confirm */
				'identifierCheckOnlineDone' => esc_html__( 'Online check: %1$d confirmed, %2$d not confirmed. Not confirmed identifiers are marked at their fields.', 'einvoice-viewer-for-peppol-en16931' ),
				'identifierCheckOnlineNone' => esc_html__( 'None of the identifiers on this invoice can be checked online.', 'einvoice-viewer-for-peppol-en16931' ),
			),
		) );

		if( $switched_locale )
			restore_previous_locale();

		$this->enqueued = true;

	}



	/**
	 * Maximum allowed file size in bytes, purely a client-side sanity check - the file never reaches PHP, so
	 * wp_max_upload_size() does not apply; only the browser has to cope with it.
	 *
	 * @return	int
	 */

	private function max_filesize()
	{
		$mb = (int) get_option( 'spev_max_filesize_mb', 8 );

		return( ( $mb > 0 ? $mb : 8 ) * MB_IN_BYTES );
	}


	/**
	 * The address of an add-on's browser module, which spev-uploader.js loads with import().
	 *
	 * ⚠️ **`.js`, NOT `.mjs`, BECAUSE MANY HOSTS SERVE `.mjs` AS `text/plain`.** A browser refuses to
	 * run a module script whose response is not a JavaScript type, and import() then fails with a
	 * console error and nothing else: the tooltips stay short and the code-list check never runs, while
	 * the add-on's buttons, which PHP prints, are all there. That is how it looked on a live Apache host
	 * in October 2026, while the local server knew `.mjs` and everything worked. `.js` has a JavaScript
	 * type on every server, and a module does not need the `.mjs` ending in the browser.
	 *
	 * ⚠️ **THE OLD NAME STAYS AS A FALLBACK.** An add-on built before the change ships only the `.mjs`
	 * file. This plugin and its add-ons are updated one at a time, so the file that is actually there
	 * decides; without the fallback a new viewer would ask an older add-on for a file it does not have.
	 *
	 * @param	string	$dir		the add-on's plugin directory, with trailing slash
	 * @param	string	$url		the add-on's plugin URL, with trailing slash
	 * @param	string	$stem		the module's file name without ending, e.g. 'svtd-extend'
	 * @param	string	$version	the add-on's version, for ?ver=
	 * @return	string
	 */

	private static function addon_module_url( $dir, $url, $stem, $version )
	{
		$file = file_exists( $dir . 'assets/js/' . $stem . '.js' ) ? $stem . '.js' : $stem . '.mjs';

		return( $url . 'assets/js/' . $file . '?ver=' . $version );
	}


	/**
	 * The addresses of the "Extended Profiles" add-on's field packages in the tooltip language, for
	 * `SPEV.extendedProfilesFieldsUrls`.
	 *
	 * ⚠️ THIS PLUGIN CARRIES NO FIELD OF AN EXTENSION, AND IT MUST STAY THAT WAY. The names of BT-X-…,
	 * BT-DEX-… and EXT-FR-FE-… live only in the add-on's folder; this method passes on the add-on's own
	 * addresses and nothing else. `check-free-package.php` checks the ZIP of this plugin for such ids.
	 *
	 * ⚠️ method_exists() TWICE, BECAUSE THREE GENERATIONS OF THE ADD-ON EXIST. Before 1.3.0 it has no
	 * packages at all; from 1.3.0 it answers fields_url() with one package ( ZUGFeRD EXTENDED ); from the
	 * next release round on it answers fields_urls() with one package per extension.
	 *
	 * @param	bool	$active	whether the add-on is loaded
	 * @return	string[]
	 */

	private function extended_profiles_fields_urls( $active )
	{
		if ( ! $active )
			return( array() );

		if ( method_exists( 'SVZE_Plugin', 'fields_urls' ) )
			return( array_values( array_filter( (array) \SVZE_Plugin::fields_urls( $this->tooltip_lang() ), 'is_string' ) ) );

		if ( method_exists( 'SVZE_Plugin', 'fields_url' ) )
			return( array_values( array_filter( array( (string) \SVZE_Plugin::fields_url( $this->tooltip_lang() ) ) ) ) );

		return( array() );
	}


	/**
	 * The 2-letter language `loadTooltips()` ( parse.mjs ) should fetch business-term names/
	 * descriptions in - einvoice-reference-data's fields.json carries "de"/"en"/"fr" per field; a
	 * locale this plugin does not have one of the three for falls back to English, same as the
	 * catalogue's own per-field fallback when a specific translation is missing.
	 *
	 * @return	string	'de' | 'en' | 'fr'
	 * @since	3.4
	 */

	private function tooltip_lang()
	{
		/*
		 * ⚠️ determine_locale(), NOT get_user_locale(). register_i18n() loads the table for the language of the
		 * page. Reading the profile language of a logged-in user here would give an administrator whose profile
		 * says English, on a German page of a bilingual site, the German table and data-lang="en" - and the
		 * viewer would show English, with no switch to change it, because the plugin sets data-lang-switch="0".
		 * A visitor chose the page in their language; the viewer speaks it. In wp-admin determine_locale()
		 * returns the user's own language anyway, so the settings page keeps following the profile. A
		 * multilingual plugin such as Polylang sets the page language through the same function.
		 */
		$lang = substr( (string) determine_locale(), 0, 2 );

		return( in_array( $lang, array( 'de', 'en', 'fr' ), true ) ? $lang : 'en' );
	}



	/**
	 * Render the [spev_einvoice_viewer] shortcode.
	 *
	 * The viewer markup lives inside a <template>; the JS moves it into a Shadow DOM so the theme
	 * CSS can not influence the appearance ( encapsulated styling ).
	 *
	 * @param	array	$atts	The shortcode attributes.
	 * @return	string
	 */

	public function render_shortcode( $atts )
	{


		$atts = shortcode_atts( array( 'bt' => null, 'lite' => null ), $atts, SPEV_SHORTCODE );

		$show_bt = ( $atts[ 'bt' ] !== null )
			? in_array( strtolower( (string) $atts[ 'bt' ] ), array( '1', 'true', 'yes', 'on' ), true )
			: (bool) get_option( 'spev_show_bt' );

		/**
		 * The initial state of the "lite" switch, `[spev_einvoice_viewer lite="1"]`.
		 *
		 * ⚠️ DELIBERATELY WITHOUT AN OPTION OF ITS OWN. `spev_show_bt` exists because BT numbers are a lasting decision
		 * of the operator. "lite" is a reading aid of the visitor - switched when needed. An option for it would
		 * be a setting nobody looks for and that stands differently at the next visit anyway.
		 */
		$show_lite = ( $atts[ 'lite' ] !== null )
			&& in_array( strtolower( (string) $atts[ 'lite' ] ), array( '1', 'true', 'yes', 'on' ), true );


		/*
		 * ⚠️ A ROLE SET TO 0 GETS NO VIEWER AT ALL, ALREADY WHEN THE PAGE IS BUILT. The daily count runs in the browser
		 * and can be circumvented there ( see ajax_quota() ); "no access" cannot, because the element and its scripts
		 * never reach the page. The sentence is the same the element would show.
		 */
		$quota = self::ratelimit()->status();

		if( ( $quota[ 'reason' ] ?? '' ) === 'role' )
		{
			$switched = switch_to_locale( determine_locale() );
			$notice   = '<p class="spev-unavailable">' . esc_html( self::limit_message( $quota ) ) . '</p>';

			if( $switched )
				restore_previous_locale();

			return( $notice );
		}


		// make sure the assets are present even if the early detection missed this context

		$this->enqueue_assets();

		// ⚠️ RECOMPUTED HERE, NOT REUSED FROM enqueue_assets() - that method's own $visualizer_layouts
		// is a local variable in a DIFFERENT method call, gone by the time THIS method runs. Every
		// other plugin-active flag only needs to be a boolean the JS reads later ( SPEV.hasExporter
		// etc., localized once ), but the layout <select>'s <option> markup needs the actual labelled
		// list at TEMPLATE-RENDER time, which is here.
		$visualizer_layouts = class_exists( 'SPVZ_Plugin' ) && self::extension_allowed( 'visualizer' ) ? SPVZ_Plugin::layout_options() : array();


		// The language of the page, as in enqueue_assets() and tooltip_lang() - one source for all three.

		$switched_locale = switch_to_locale( determine_locale() );

		ob_start();
		?>
		<?php
		/**
		 * ⚠️ **THE INTERFACE COMES FROM `<einvoice-viewer>`, NOT FROM THIS `<template>`.** Drop area, message line,
		 * BT and "lite" switches, source line and invoice view are built by the web component of the shared
		 * library - the same one the stand-alone file and the embedding into a foreign CMS use. The concept treats
		 * the viewer as **one** product in two forms of delivery; a shell of its own per form would drift apart.
		 *
		 * ⚠️ **WHAT STAYS HERE ARE THE ADD-ONS.** Eight buttons and their result areas belong to this plugin and its
		 * add-ons, not to the tool. They go through two slots: `toolbar` for the buttons, `panels` for the areas
		 * between toolbar and document.
		 *
		 * ⚠️ **THEY THEREBY STAY IN THE LIGHT DOM** - and are styled by the stylesheet of the **document**, not by the
		 * one in the shadow root. That is why `enqueue_assets()` additionally loads `spev-viewer.css` normally, and
		 * why that file declares its tokens on `:root, :host` instead of only on `:host`.
		 */
		?>
		<?php
		/**
		 * ⚠️ **THE THREE CATALOGUE ADDRESSES AND THE STYLESHEET STAND IN THE MARKUP, NOT IN THE JAVASCRIPT.**
		 * `<einvoice-viewer>` reads them in its `connectedCallback()`, that is the moment the browser upgrades the
		 * element. This plugin's classic script runs only afterwards ( it explicitly waits for
		 * `customElements.whenDefined()` ) - a `dataset.ciiNodesUrl` set there would come too late, the element
		 * would already have looked for its catalogues without an address. The values are known at render time
		 * anyway.
		 *
		 * ⚠️ **`data-css` IN ADDITION TO THE NORMAL `wp_enqueue_style()`, NOT INSTEAD.** The queued stylesheet styles the
		 * slot contents that stay in the light DOM; this one styles what lies in the shadow root. A stylesheet of
		 * the document does not reach across the shadow boundary, and one in the shadow root not out below it -
		 * both are needed.
		 */
		?>
		<div class="spev-host">
			<?php
			// ⚠️ `data-kit-scope` OPENS THE LIMITED KIT ( spt-kit.components.scoped.css ) for the light-DOM content of the
			// element: the add-on buttons are `.btn`, the layout select is `.sel`, the messages are `.note`. Without the
			// attribute they would stand as plain browser controls.
			?>
			<einvoice-viewer
				data-kit-scope
				data-bt="<?php echo $show_bt ? '1' : '0'; ?>"
				data-lite="<?php echo $show_lite ? '1' : '0'; ?>"
				data-lang="<?php echo esc_attr( $this->tooltip_lang() ); ?>"
				data-lang-switch="0"
				<?php
				/**
				 * ⚠️ **SEVERAL STYLESHEETS, AND THE ORDER IS PART OF THE MATTER.** The invoice view takes its look from the
				 * design kit; its own file carries only what the kit does not cover - the reset for the shadow root, the
				 * switch for the field numbers, the scroll area above a wide table and the control shell.
				 *
				 * ⚠️ **THE OWN FILE FIRST, THE KIT AFTER IT.** That file begins with `:host { all: initial }`. With the kit
				 * before it, its whole token set would depend on how a browser applies `all` to custom properties -
				 * after it, its tokens come after the reset in any case. The reasoning is written out in the header of
				 * `spev-viewer.css`.
				 *
				 * ⚠️ **THE KIT DOES NOT BELONG IN THE DOCUMENT.** It carries 130 unqualified single classes and twelve
				 * bare element selectors, among them `html`, `body`, `a` and `button`. In the shadow root nothing of it
				 * leaks out; registered in the document it would restyle the page's theme.
				 *
				 * ⚠️ **THE TOKEN FILE STANDS IN THE MIDDLE, AND IT IS NO DUPLICATION.** The full kit declares its 76 tokens
				 * exclusively on `:root` - a selector that matches nothing in a shadow root. Without this address the
				 * invoice view would have colours only because `enqueue_assets()` additionally registers the token
				 * file in the document and the host inherits it from there. Every situation in which that stylesheet
				 * does not reach the page - a cache, a page builder, a preview without `wp_head()` - would take colours,
				 * surfaces and font sizes from the whole view at once, and without an error message: everything
				 * loads, and an unknown token simply delivers nothing. With this third address the shadow root
				 * carries itself.
				 */
				?>
				data-css="<?php
					$spev_kit = self::kit_css();
					echo esc_url( SPEV_URL . 'assets/css/spev-viewer.css?ver=' . self::asset_ver( 'assets/css/spev-viewer.css' ) )
						. ' ' . esc_url( $spev_kit[ 'tokens' ] )
						. ' ' . esc_url( $spev_kit[ 'kit' ] );
				?>"
				<?php
				/**
				 * ⚠️ **THE SIZE LIMIT BELONGS HERE, BECAUSE THE ADD-ONS SEND THE DOCUMENT TO THE SERVER.** Reading itself
				 * would need none - it takes place in the browser. But KoSIT check, Excel export and Visualizer send
				 * the file to `admin-ajax.php`, and what PHP cannot accept there at all the user should not upload in
				 * the first place.
				 */
				?>
				data-max-size="<?php echo (int) $this->max_filesize(); ?>"
				<?php
				/**
				 * ⚠️ THE STORE OF THE SETTINGS DIALOG IS THE SITE, NOT THE BROWSER. Without these two attributes the element
				 * would keep the settings of the add-ons in the visitor's browser, as the app does; here they belong to the
				 * site and its rights ( SPEV_Settings_REST ). The nonce lets WordPress accept the login cookie for a write.
				 * A guest gets the attributes too and receives an empty, read-only dialog from the routes.
				 */
				?>
				data-settings-url="<?php echo esc_url( rest_url( SPEV_Settings_REST::NAMESPACE_V1 . '/settings' ) ); ?>"
				data-settings-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
				data-cii-nodes-url="<?php echo esc_url( SPEV_URL . 'assets/data/cii-nodes.json?ver=' . self::asset_ver( 'assets/data/cii-nodes.json' ) ); ?>"
				data-ubl-nodes-url="<?php echo esc_url( SPEV_URL . 'assets/data/ubl-nodes.json?ver=' . self::asset_ver( 'assets/data/ubl-nodes.json' ) ); ?>"
				data-fields-url="<?php echo esc_url( SPEV_URL . 'assets/data/en16931-fields.json?ver=' . self::asset_ver( 'assets/data/en16931-fields.json' ) ); ?>"
				<?php
				/**
				 * ⚠️ **pdf.js IS THE FALLBACK HERE, NOT THE WAY.** The element reads the embedded XML of a ZUGFeRD /
				 * Factur-X PDF itself; these two addresses only allow it to load 448 KB plus a 1.2 MB worker for a PDF
				 * the narrow way does not open. The plugin ships pdf.js, so it may name them - the stand-alone file
				 * cannot and does not.
				 *
				 * ⚠️ SHIPPED, NOT LOADED FROM cdnjs ( Plugin Check finding
				 * PluginCheck.CodeAnalysis.Offloading.OffloadedContent ). An address on
				 * cdnjs.cloudflare.com is what the wordpress.org check objects to as "Offloading … to your servers or
				 * any remote service is disallowed" - the same reason the fonts lie here themselves. Both files are
				 * copied word for word from `pdfjs-dist@6.3.289`'s `build/` to `assets/js/pdfjs/`, with the licence
				 * next to them ( Apache-2.0, redistribution allowed ).
				 *
				 * ⚠️ **THEY ARE CALLED `.js`, NOT `.mjs` AS IN pdf.js ITSELF** - found live on a real web host ( einvoice4s.de ):
				 * "Failed to fetch dynamically imported module". That server knows no MIME mapping for
				 * the newer extension `.mjs` and delivered the file with a Content-Type `import()` refuses. The
				 * **content** is an ES module in both cases - `import()` decides by the Content-Type of the answer
				 * alone, never by the extension of the address -, and pdf.js creates its worker explicitly with
				 * `type:"module"` anyway, regardless of the file name. The renaming avoids the whole class of
				 * host-dependent MIME problems, instead of having every installation fix its server.
				 */
				?>
				data-pdfjs-url="<?php echo esc_url( SPEV_URL . 'assets/js/pdfjs/pdf.min.js?ver=' . self::asset_ver( 'assets/js/pdfjs/pdf.min.js' ) ); ?>"
				data-pdfjs-worker-url="<?php echo esc_url( SPEV_URL . 'assets/js/pdfjs/pdf.worker.min.js?ver=' . self::asset_ver( 'assets/js/pdfjs/pdf.worker.min.js' ) ); ?>"
			>
				<?php
				// ⚠️ TOOLBAR ABOVE .spev-result, ON PURPOSE: action buttons after the rendered invoice would mean
				// scrolling past the entire document ( several screens for ZUGFeRD Extended ) just to reach them.
				//
				// ⚠️ `slot="toolbar"` ON EVERY SINGLE BUTTON, not on a wrapper around them: the element puts its own
				// switches into the same toolbar, and an additional wrapper would be a box in the row instead of
				// buttons in the row.
				//
				// ⚠️ FIELD AND RULE BROWSER STAND IN `slot="toolbar-tools"`, the second row next to "Reset". They look
				// into the document instead of doing something with it; the first row thereby stays free for the
				// buttons of the extensions.
				?>
				<button type="button" slot="toolbar" class="spev-kosit-btn btn" hidden><?php echo esc_html( self::validator_texts( true, false, false )['kosit']['button'] ); ?></button>
				<?php if ( class_exists( 'SPMV_Plugin' ) && method_exists( 'SPMV_Plugin', 'bridge_texts' ) ) : ?><button type="button" slot="toolbar" class="spev-mustang-btn btn" hidden><?php echo esc_html( \SPMV_Plugin::bridge_texts()['button'] ); ?></button><?php endif; ?>
				<?php if ( class_exists( 'SPPV_Plugin' ) && method_exists( 'SPPV_Plugin', 'bridge_texts' ) ) : ?><button type="button" slot="toolbar" class="spev-vampire-btn btn" hidden><?php echo esc_html( \SPPV_Plugin::bridge_texts()['button'] ); ?></button><?php endif; ?>
				<button type="button" slot="toolbar" class="spev-export-btn btn" hidden><?php echo esc_html__( 'Export to Excel', 'einvoice-viewer-for-peppol-en16931' ); ?></button>
				<button type="button" slot="toolbar" class="spev-identifier-check-btn btn" hidden><?php echo esc_html__( 'Check identifiers online', 'einvoice-viewer-for-peppol-en16931' ); ?></button>
				<button type="button" slot="toolbar-tools" class="spev-fieldbrowser-btn btn" hidden><?php echo esc_html__( 'Field Browser', 'einvoice-viewer-for-peppol-en16931' ); ?></button>
				<button type="button" slot="toolbar-tools" class="spev-rulebrowser-btn btn" hidden><?php echo esc_html__( 'Rule Browser', 'einvoice-viewer-for-peppol-en16931' ); ?></button>
				<?php
				// ⚠️ THE ONE EXCEPTION TO "slot ON EVERY SINGLE BUTTON": the layout select and the two
				// Visualizer buttons go into the toolbar as ONE slotted group. The select decides the
				// layout for BOTH buttons, so the three must never be split across two toolbar rows -
				// as single flex items they were ( measured at 1024 px with
				// Technical Details active: select and "Visualizer" in row 1, "Download as PDF" in row 2 ).
				// The group has no border and no background ( spev-viewer.css ), so it still reads as
				// three controls in the row, not as a box.
				//
				// ⚠️ The group is only rendered when the Visualizer is active ( $visualizer_layouts is
				// empty otherwise ). spev-uploader.js treats the missing elements as "not installed",
				// exactly like it treated the hidden ones before.
				//
				// ⚠️ The label points at the select with for/id, as in the kit's `.field`. The id comes from wp_unique_id(),
				// because the shortcode may stand twice on one page and a fixed id would then exist twice.
				?>
				<?php if ( $visualizer_layouts ) : ?>
				<span slot="toolbar" class="spev-visualizer-group" role="group" aria-label="<?php echo esc_attr__( 'Visualizer', 'einvoice-viewer-for-peppol-en16931' ); ?>" hidden>
					<?php $spev_layout_id = wp_unique_id( 'spev-layout-' ); ?>
					<span class="field spev-visualizer-field">
						<label for="<?php echo esc_attr( $spev_layout_id ); ?>"><?php echo esc_html__( 'Layout', 'einvoice-viewer-for-peppol-en16931' ); ?></label>
						<select id="<?php echo esc_attr( $spev_layout_id ); ?>" class="sel spev-visualizer-layout spev-toolbar-select" hidden>
							<?php foreach ( $visualizer_layouts as $layout_key => $layout_label ) : ?>
								<option value="<?php echo esc_attr( $layout_key ); ?>"><?php echo esc_html( $layout_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</span>
					<button type="button" class="spev-visualizer-btn btn" hidden><?php echo esc_html__( 'Visualizer', 'einvoice-viewer-for-peppol-en16931' ); ?></button>
					<button type="button" class="spev-visualizer-pdf-btn btn" hidden><?php echo esc_html__( 'Download as PDF', 'einvoice-viewer-for-peppol-en16931' ); ?></button>
				</span>
				<?php endif; ?>
				<?php
				// ⚠️ `slot="panels"` PUTS THESE AREAS BETWEEN TOOLBAR AND DOCUMENT - where the reader expects them:
				// the reader has just clicked a button and should not look for the answer behind the whole invoice.
				?>
				<div slot="panels" class="spev-kosit-check" hidden>
					<div class="spev-kosit-message" role="status" aria-live="polite" hidden></div>
					<div class="spev-kosit-result"></div>
				</div>
				<div slot="panels" class="spev-export-check" hidden>
					<div class="spev-export-message" role="status" aria-live="polite" hidden></div>
				</div>
				<div slot="panels" class="spev-visualizer-check" hidden>
					<div class="spev-visualizer-message" role="status" aria-live="polite" hidden></div>
				</div>
				<?php
				// ⚠️ NO TABLE. The Identifier Check marks every identifier at its field ( its
				// assets/js/spic-annotate.js ), also the result of the online check; this panel only carries the
				// one-line message of that online check.
				?>
				<div slot="panels" class="spev-identifier-check" hidden>
					<div class="spev-identifier-check-message" role="status" aria-live="polite" hidden></div>
				</div>
			</einvoice-viewer>
		</div>
		<?php

		if( $switched_locale )
			restore_previous_locale();

		return( (string) ob_get_clean() );


	}





	/**
	 * The cache buster of an own asset: the plugin version plus the modification time of the file.
	 *
	 * ⚠️ THE VERSION ALONE IS NOT ENOUGH WHILE A FASSUNG IS STILL IN TEST. An undelivered ZIP is rebuilt under the same
	 * number, and a browser that had the page open kept the old stylesheet and script: on 18.10.2026 Ralf saw the
	 * filled buttons and "Show invoice" in the title bar after both had long been changed. The file time changes with
	 * every installed copy of a changed file.
	 *
	 * @param	string	$rel	path below the plugin folder
	 * @return	string
	 * @since	18.10.2026
	 */

	private static function asset_ver( $rel )
	{
		$time = @filemtime( SPEV_DIR . $rel );

		return( SPEV_VERSION . ( $time ? '.' . $time : '' ) );
	}

}
