<?php

	/**
	 Class Name: EInvoicePress_Design_Kit
	 Description: The design kit the operator picked for every eInvoice tool on the site, resolved
	              against the kit files one plugin actually ships.
	 Version: 1.0.0
	 Author: Ralf Fuhrmann
	 */

	declare( strict_types = 1 );

	if ( ! defined( 'ABSPATH' ) )
		exit;


	/**
	 * class: EInvoicePress_Design_Kit
	 *
	 * ============================================================================================
	 * WHAT IT DOES
	 * ============================================================================================
	 *
	 * Nine plugins of the family draw a visitor interface with the shared design kit. The operator
	 * picks the kit once, and every one of the nine reads the choice from here instead of naming a
	 * stylesheet in its own code:
	 *
	 *     $css = EInvoicePress_Design_Kit::stylesheets( SPEV_DIR . 'assets/css/', SPEV_URL . 'assets/css/', SPEV_VERSION );
	 *     // $css['tokens'] => …/spt-kit-a.tokens.css?ver=…, $css['kit'] => …/spt-kit-a.css?ver=…
	 *
	 * ⚠️ **THE CHOICE IS NOT A FIXED LIST.** It is built from the files that lie in the kit folder of
	 * the plugin that asks and in the site's upload folder. A kit counts when its `spt-kit-<x>.tokens.css`
	 * exists. The token file declares its tokens on `:root` and on `:host`; without the `:host` part a
	 * Shadow Root gets no colours at all, and every `var( --… )` stays empty without an error. A plugin
	 * that only ever loads the token file ( the Technical Details popup, the Visualizer print bar )
	 * passes `$tokens_only = true` and needs no building blocks.
	 *
	 * ⚠️ **A KIT IS ITS TOKEN FILE, AND A KIT FROM OUTSIDE IS UPLOADED ONCE FOR THE WHOLE SITE.** Since 19.10.2026
	 * a plugin ships the building blocks `spt-kit.components.css` once and one `spt-kit-<x>.tokens.css` per kit;
	 * the kits of the tool kit differ only in their tokens. An operator uploads the token file of an own kit
	 * once ( upload_folder() ), picks it in any settings page, and every tool follows. That is what the kits
	 * are for: one switch, and the whole interface keeps to the new kit ( Ralf, 19.10.2026 ).
	 *
	 * ⚠️ **ONE OPTION FOR ALL NINE PLUGINS, NOT NINE OPTIONS.** Every settings page that shows the
	 * select writes the same option `einvoice_design_kit`. Changing it on the viewer's page changes it
	 * for the validators too, and every page says so next to the select.
	 *
	 * ⚠️ **KIT H IS THE DEFAULT AND THE FALLBACK, AND THE FALLBACK IS SILENT.** When the saved kit is
	 * missing in one plugin's folder ( an older plugin version, a kit removed in an update ), that
	 * plugin draws with Kit H. It does not reset the option: the other plugins may still have the kit,
	 * and the next update of this one brings it back.
	 *
	 * ============================================================================================
	 * THIS FILE IS SHARED, SO IT IS GUARDED
	 * ============================================================================================
	 *
	 * Every plugin of the nine ships a byte-identical copy in `includes/`, and whichever loads first
	 * owns the class:
	 *
	 *     if ( ! class_exists( 'EInvoicePress_Design_Kit' ) )
	 *         require_once( SPEV_DIR . 'includes/class-einvoicepress-design-kit.php' );
	 *
	 * ⚠️ **THE COPIES MUST STAY IDENTICAL.** The class that wins the `class_exists()` race serves all
	 * nine plugins, so a newer method in one copy is invisible when an older copy loaded first.
	 * The copy in the free viewer is the original; every other plugin carries it unchanged.
	 *
	 * ⚠️ **NO TRANSLATABLE TEXT IN HERE.** Each plugin translates under its own text domain, and a
	 * shared class has none of its own. The label and the note beside the select come from the plugin
	 * that draws the field; the kit names come from the token files ( see label() ).
	 *
	 * ⚠️ PHP 7.4 COMPATIBLE. The two explorers still declare `Requires PHP: 7.4`, so no
	 * `str_starts_with()`, no union types and no `match` here.
	 *
	 * @since 1.0.0
	 */

	if ( ! class_exists( 'EInvoicePress_Design_Kit' ) ) :


	class EInvoicePress_Design_Kit
	{


		/** The version of this shared class, for diagnostics when copies drift apart. */
		const VERSION = '1.0.0';

		/** The one option every plugin of the family reads and writes. */
		const OPTION = 'einvoice_design_kit';

		/** The kit that is used when nothing else is chosen, or the chosen kit is missing. */
		const DEFAULT_KIT = 'h';

		/** The building blocks, the same for every kit; once per plugin folder. */
		const COMPONENTS = 'spt-kit.components.css';

		/** The folder under uploads/ that holds uploaded kits. */
		const UPLOAD_SUBDIR = 'einvoice-kits';

		/** The file field of the upload. */
		const UPLOAD_FIELD = 'einvoice_design_kit_upload';

		/** The largest token file accepted; the token files of the tool kit weigh 4 to 6 KB. */
		const UPLOAD_MAX = 32768;

		/** @var array<string,string> the translated texts of the upload, see render_field() */
		private static $upload_texts = array();

		/**
		 * The kit folders the plugins of this request announced through register_setting().
		 *
		 * ⚠️ sanitize() checks a submitted value against the union of these folders, not against one
		 * plugin's folder. All nine plugins register the same option, and WordPress chains every
		 * registered sanitize callback; a check against the folder of just one plugin would let an
		 * older plugin without Kit A reset the value the newer ones accept.
		 *
		 * @var array<string,bool>	folder path => tokens only
		 */
		private static $dirs = array();

		/** @var bool whether a plugin with its own settings page has shown the select already */
		private static $has_page = false;

		/** @var array<string,array<string,string>> kits per folder, cached for the request */
		private static $cache = array();


		/**
		 * The kits found in one kit folder.
		 *
		 * @param	string	$dir			the plugin's kit folder, with or without a trailing slash
		 * @param	bool	$tokens_only	true when the plugin only ever loads the token file
		 * @return	array<string,string>	slug => readable name, Kit H first, the rest in alphabetical order
		 * @since	1.0.0
		 */

		public static function kits( $dir, $tokens_only = false )
		{
			$dir = trailingslashit( (string) $dir );
			$key = $dir . ( $tokens_only ? '|t' : '|f' );

			if ( isset( self::$cache[ $key ] ) )
				return( self::$cache[ $key ] );

			$found = array();

			/*
			 * ⚠️ TWO PLACES, AND THE UPLOADED KIT WINS. The plugin's own folder brings the kits it was built
			 * with; the site's folder ( see upload_folder() ) holds the kits the operator uploaded. A kit of
			 * the same name there replaces the plugin's copy, so a newer token file reaches every tool at
			 * once instead of waiting for nine updates.
			 */
			// ⚠️ A kit is its token file. The building blocks lie once in the plugin's folder; without them a
			// plugin that loads them has no kit at all, and the select stays empty rather than offering a kit
			// that would draw unstyled.
			if ( ! $tokens_only && ! is_readable( $dir . self::COMPONENTS ) )
			{
				self::$cache[ $key ] = array();
				return( array() );
			}

			$places = array( $dir );
			$up     = self::upload_folder();

			if ( $up !== null )
				$places[] = $up[ 'dir' ];

			foreach ( $places as $place )
			{
				foreach ( (array) glob( $place . 'spt-kit-*.tokens.css' ) as $path )
				{
					// ⚠️ The pattern is anchored on both ends. `spt-kit-a.tokens.css.bak` or a stray
					// `spt-kit-A.tokens.css` would otherwise turn into a slug that sanitize() rejects
					// later, and the select would offer a kit that can never be saved.
					if ( ! preg_match( '/^spt-kit-([a-z0-9-]+)\.tokens\.css$/', basename( (string) $path ), $m ) )
						continue;

					$found[ $m[ 1 ] ] = self::label( (string) $path, $m[ 1 ] );
				}
			}

			ksort( $found );

			// The default goes first, because it is the one most sites keep.
			if ( isset( $found[ self::DEFAULT_KIT ] ) )
				$found = array( self::DEFAULT_KIT => $found[ self::DEFAULT_KIT ] ) + $found;

			self::$cache[ $key ] = $found;

			return( $found );
		}


		/**
		 * The site's folder for uploaded kits: `wp-content/uploads/einvoice-kits/`.
		 *
		 * ⚠️ **AN UPLOADED KIT LIVES OUTSIDE EVERY TOOL** ( Ralf, 19.10.2026: no second plugin, no copying by
		 * hand ). An update replaces a tool's folder completely; a kit placed there would be gone after the next
		 * update, silently, and the tools would fall back to Kit H. The uploads folder survives updates, and
		 * every tool of the site reads the same one.
		 *
		 * ⚠️ **NOT CREATED HERE**, only by the upload. A folder nobody asked for would appear on every site.
		 *
		 * @return	array{dir:string,url:string}|null	both with a trailing slash, or null when there is none
		 * @since	1.1.0
		 */

		private static function upload_folder()
		{
			if ( ! function_exists( 'wp_upload_dir' ) )
				return( null );

			$up  = wp_upload_dir( null, false );
			$dir = trailingslashit( (string) $up[ 'basedir' ] ) . self::UPLOAD_SUBDIR . '/';

			if ( ! is_dir( $dir ) )
				return( null );

			return( array( 'dir' => $dir, 'url' => trailingslashit( (string) $up[ 'baseurl' ] ) . self::UPLOAD_SUBDIR . '/' ) );
		}


		/**
		 * The readable name of a kit, taken from the comment header of its token file.
		 *
		 * The token files open with a line like `Tokens — Kit A  ·  SAP-nah, sachlich (Fiori Classic)`.
		 * The part up to the middle dot is the name, the part after it a short description.
		 *
		 * ⚠️ **THE DESCRIPTION IS GERMAN, SO IT IS ONLY SHOWN TO A GERMAN ADMIN.** The design kit is
		 * written in German, and an English settings page with a German phrase in one select would put
		 * two languages on one page. The name alone ( „Kit A" ) reads the same in every language. A
		 * header without the expected line gives „Kit " plus the slug in capitals.
		 *
		 * @param	string	$path	the token file
		 * @param	string	$slug	the kit slug
		 * @return	string
		 * @since	1.0.0
		 */

		private static function label( $path, $slug )
		{
			$fallback = 'Kit ' . strtoupper( $slug );

			/*
			 * ⚠️ WP_FILESYSTEM, NOT fopen()/fread(). Plugin Check reports every direct file call of PHP as an
			 * error ( WordPress.WP.AlternativeFunctions ), and wordpress.org rejects a plugin with an error.
			 * The token file is a few kilobytes, so reading it whole costs nothing; only its first
			 * 2 048 bytes are searched.
			 */
			global $wp_filesystem;

			if ( empty( $wp_filesystem ) && defined( 'ABSPATH' ) )
			{
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( empty( $wp_filesystem ) || ! $wp_filesystem->exists( $path ) )
				return( $fallback );

			$head = substr( (string) $wp_filesystem->get_contents( $path ), 0, 2048 );

			if ( $head === '' )
				return( $fallback );

			if ( ! preg_match( '/Tokens\s+\S+\s+(Kit\s+[A-Za-z0-9-]+)(?:[ \t]+\S[ \t]+([^\r\n*]+))?/u', $head, $m ) )
				return( $fallback );

			$name = trim( $m[ 1 ] );
			$desc = isset( $m[ 2 ] ) ? trim( $m[ 2 ] ) : '';

			$locale = function_exists( 'get_user_locale' ) ? (string) get_user_locale() : '';

			if ( $desc !== '' && strpos( $locale, 'de' ) === 0 )
				return( $name . ' · ' . $desc );

			return( $name );
		}


		/**
		 * The kit this plugin draws with.
		 *
		 * The saved option goes through the filter `einvoice_design_kit` first, so a site can set the
		 * kit in code. Whatever comes out is checked against the kits in this plugin's folder.
		 *
		 * @param	string	$dir			the plugin's kit folder
		 * @param	bool	$tokens_only	true when the plugin only loads the token file
		 * @return	string	a slug that is guaranteed to exist in $dir, or 'h'
		 * @since	1.0.0
		 */

		public static function slug( $dir, $tokens_only = false )
		{
			/**
			 * Filters the design kit of the eInvoice tools.
			 *
			 * @param	string	$slug	the saved kit, for example 'h' or 'a'
			 */
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- one filter for the whole eInvoice family on purpose: a site sets its kit once for every tool.
			$slug = apply_filters( 'einvoice_design_kit', (string) get_option( self::OPTION, self::DEFAULT_KIT ) );
			$slug = is_string( $slug ) ? strtolower( trim( $slug ) ) : '';

			$kits = self::kits( $dir, $tokens_only );

			if ( $slug !== '' && preg_match( '/^[a-z0-9-]+$/', $slug ) && isset( $kits[ $slug ] ) )
				return( $slug );

			return( self::DEFAULT_KIT );
		}


		/**
		 * The stylesheet addresses of the chosen kit for one plugin.
		 *
		 * @param	string	$dir			the plugin's kit folder on disk
		 * @param	string	$url			the same folder as an address
		 * @param	string	$ver			the version for the cache buster; an empty string leaves it off
		 * @param	bool	$tokens_only	true when the plugin only loads the token file
		 * @return	array{tokens:string,kit:string,slug:string}	`kit` is empty when $tokens_only is true
		 * @since	1.0.0
		 */

		public static function stylesheets( $dir, $url, $ver = '', $tokens_only = false )
		{
			$slug   = self::slug( $dir, $tokens_only );
			$url    = trailingslashit( (string) $url );
			$q      = ( (string) $ver !== '' ) ? '?ver=' . rawurlencode( (string) $ver ) : '';
			$tokens = $url . 'spt-kit-' . $slug . '.tokens.css' . $q;

			// ⚠️ The same order as in kits(): an uploaded kit of the same name wins over the plugin's copy. The
			// cache buster then is the file time, because the plugin version says nothing about a file the
			// operator uploaded.
			$up = self::upload_folder();

			if ( $up !== null && is_readable( $up[ 'dir' ] . 'spt-kit-' . $slug . '.tokens.css' ) )
				$tokens = $up[ 'url' ] . 'spt-kit-' . $slug . '.tokens.css?ver=' . (int) filemtime( $up[ 'dir' ] . 'spt-kit-' . $slug . '.tokens.css' );

			/*
			 * ⚠️ `kit` IS THE BUILDING BLOCKS, THE SAME FOR EVERY KIT. Since 19.10.2026 a plugin ships
			 * `spt-kit.components.css` once and one token file per kit, not one full kit per kit: the full kits
			 * of the tool kit are exactly their tokens plus these blocks ( measured on A, H and S of 1.8 ). The
			 * callers load `tokens` first and `kit` second, as before, so none of them had to change.
			 */
			return( array(
				'slug'   => $slug,
				'tokens' => $tokens,
				'kit'    => $tokens_only ? '' : $url . self::COMPONENTS . $q,
			) );
		}


		/**
		 * Cleans a submitted value before WordPress stores it.
		 *
		 * ⚠️ ONLY `[a-z0-9-]` AND ONLY A KIT THAT SOME PLUGIN OF THIS REQUEST ACTUALLY SHIPS. The value
		 * ends up in a stylesheet address; anything else would let a crafted form post write a path
		 * fragment into every page that carries one of the tools.
		 *
		 * @param	mixed	$value
		 * @return	string
		 * @since	1.0.0
		 */

		public static function sanitize( $value )
		{
			// An uploaded kit is chosen at once: whoever uploads a kit wants to see it.
			$uploaded = self::take_upload();

			if ( $uploaded !== '' )
				return( $uploaded );

			$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';

			if ( $value === '' || ! preg_match( '/^[a-z0-9-]+$/', $value ) )
				return( self::DEFAULT_KIT );

			foreach ( self::$dirs as $dir => $tokens_only )
				if ( isset( self::kits( $dir, $tokens_only )[ $value ] ) )
					return( $value );

			return( self::DEFAULT_KIT );
		}


		/**
		 * Registers the shared option in one plugin's settings group.
		 *
		 * Call it from the plugin's own `admin_init` callback, next to its other register_setting()
		 * calls. Registering the same option from several groups is fine: WordPress allows the option
		 * in each group, and the sanitize callback is the same static method every time.
		 *
		 * ⚠️ **ONLY IN A GROUP WHOSE FORM ALSO SHOWS render_field().** `options.php` saves every option
		 * of the submitted group and passes `null` for one the form did not send; sanitize() makes Kit H
		 * of that. A group that registers the option without showing the select resets the choice of
		 * all nine plugins each time its page is saved.
		 *
		 * @param	string	$group			the plugin's settings group
		 * @param	string	$dir			the plugin's kit folder
		 * @param	bool	$tokens_only	true when the plugin only loads the token file
		 * @param	bool	$own_page		false for a plugin that has no settings page of its own
		 * @param	array	$upload_texts	the translated texts of the upload, see render_field(); since 1.1.0
		 * @return	void
		 * @since	1.0.0
		 */

		public static function register_setting( $group, $dir, $tokens_only = false, $own_page = true, $upload_texts = array() )
		{
			self::$dirs[ trailingslashit( (string) $dir ) ] = (bool) $tokens_only;

			// ⚠️ The messages of the upload are needed while the form is SAVED, a request in which
			// render_field() does not run. They come in here, translated on `admin_init`.
			if ( is_array( $upload_texts ) && $upload_texts )
				self::$upload_texts = $upload_texts;

			if ( $own_page )
				self::$has_page = true;

			register_setting( (string) $group, self::OPTION, array(
				'type'              => 'string',
				'default'           => self::DEFAULT_KIT,
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			) );
		}


		/**
		 * Echoes the select for one plugin's settings page.
		 *
		 * @param	string	$dir			the plugin's kit folder
		 * @param	bool	$tokens_only	true when the plugin only loads the token file
		 * @param	string	$note			the translated note under the select, already plain text
		 * @param	array	$upload			since 1.1.0: the translated texts of the upload; empty shows no upload.
		 *									Keys: `label` ( above the file field ), `hint` ( under it ), and the
		 *									messages `failed`, `too_large`, `not_tokens`, `stored`
		 * @return	void
		 * @since	1.0.0
		 */

		public static function render_field( $dir, $tokens_only = false, $note = '', $upload = array() )
		{
			$current = self::slug( $dir, $tokens_only );

			echo '<select name="' . esc_attr( self::OPTION ) . '" id="' . esc_attr( self::OPTION ) . '">';

			foreach ( self::kits( $dir, $tokens_only ) as $slug => $name )
				echo '<option value="' . esc_attr( $slug ) . '"' . selected( $current, $slug, false ) . '>' . esc_html( $name ) . '</option>';

			echo '</select>';

			if ( (string) $note !== '' )
				echo '<p class="description">' . esc_html( (string) $note ) . '</p>';

			/*
			 * ⚠️ AN OPTIONAL PARAMETER, NOT A NEW METHOD. The copy of this class that loads first serves all
			 * tools. An older copy from a tool that was not updated yet ignores a fourth argument; a new method
			 * would end in a fatal error there.
			 *
			 * ⚠️ THE SETTINGS FORM IS NOT MULTIPART. `options.php` forms carry no `enctype`, and without it the
			 * browser sends the file name only. The line of script below sets it on the form that holds this
			 * field; without script the upload is simply not there, and the select keeps working.
			 */
			if ( ! is_array( $upload ) || empty( $upload[ 'label' ] ) )
				return;

			echo '<p><label for="' . esc_attr( self::UPLOAD_FIELD ) . '">' . esc_html( (string) $upload[ 'label' ] ) . '</label><br>';
			echo '<input type="file" accept=".css,text/css" name="' . esc_attr( self::UPLOAD_FIELD ) . '" id="' . esc_attr( self::UPLOAD_FIELD ) . '"></p>';

			if ( ! empty( $upload[ 'hint' ] ) )
				echo '<p class="description">' . esc_html( (string) $upload[ 'hint' ] ) . '</p>';

			wp_print_inline_script_tag( 'document.getElementById(' . wp_json_encode( self::UPLOAD_FIELD ) . ').form.enctype="multipart/form-data";' );
		}


		/**
		 * Takes an uploaded token file out of the request that saves the settings.
		 *
		 * ⚠️ **ONCE PER REQUEST.** Every tool registers the same option, and WordPress runs each registered
		 * sanitize callback in turn; the second run must not store the file again or report twice.
		 *
		 * ⚠️ **THE FILE IS REWRITTEN, NEVER STORED AS SENT.** clean_tokens() keeps custom properties inside
		 * `:root` and `:host` and nothing else. The file is served to every visitor of every tool; an
		 * `@import`, a `url()` or a rule on `body` in it would reach far beyond a colour.
		 *
		 * @return	string	the slug of the stored kit, or '' when nothing was uploaded or the file was refused
		 * @since	1.1.0
		 */

		private static function take_upload()
		{
			static $done = null;

			if ( $done !== null )
				return( $done );

			$done = '';

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- runs inside options.php, which checked the nonce of the settings group before calling sanitize().
			if ( empty( $_FILES[ self::UPLOAD_FIELD ] ) || ! is_array( $_FILES[ self::UPLOAD_FIELD ] ) )
				return( $done );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see above; the fields are checked one by one below.
			$file = $_FILES[ self::UPLOAD_FIELD ];

			if ( ! isset( $file[ 'error' ] ) || (int) $file[ 'error' ] === UPLOAD_ERR_NO_FILE )
				return( $done );

			if ( ! current_user_can( 'manage_options' ) )
				return( $done );

			if ( (int) $file[ 'error' ] !== UPLOAD_ERR_OK || empty( $file[ 'tmp_name' ] ) || ! is_uploaded_file( (string) $file[ 'tmp_name' ] ) )
				return( self::upload_message( 'failed' ) );

			if ( (int) $file[ 'size' ] > self::UPLOAD_MAX )
				return( self::upload_message( 'too_large' ) );

			// The slug comes from the file name: `spt-kit-firma.tokens.css`, `firma.tokens.css` and `firma.css` all give `firma`.
			$name = strtolower( sanitize_file_name( (string) $file[ 'name' ] ) );

			if ( ! preg_match( '/^(?:spt-kit-)?([a-z0-9][a-z0-9-]{0,30})(?:\.tokens)?\.css$/', $name, $m ) )
				return( self::upload_message( 'not_tokens' ) );

			$slug = $m[ 1 ];

			global $wp_filesystem;

			if ( empty( $wp_filesystem ) )
			{
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$clean = empty( $wp_filesystem ) ? null : self::clean_tokens( (string) $wp_filesystem->get_contents( (string) $file[ 'tmp_name' ] ), $slug );

			if ( $clean === null )
				return( self::upload_message( 'not_tokens' ) );

			$up  = wp_upload_dir( null, false );
			$dir = trailingslashit( (string) $up[ 'basedir' ] ) . self::UPLOAD_SUBDIR . '/';

			if ( ! wp_mkdir_p( $dir ) || ! $wp_filesystem->put_contents( $dir . 'spt-kit-' . $slug . '.tokens.css', $clean, FS_CHMOD_FILE ) )
				return( self::upload_message( 'failed' ) );

			self::$cache = array();
			self::upload_message( 'stored', 'updated' );

			$done = $slug;

			return( $done );
		}


		/**
		 * Reports an upload result on the settings page and returns ''.
		 *
		 * @param	string	$key	the key in the texts of register_setting()
		 * @param	string	$type	'error' or 'updated'
		 * @return	string
		 * @since	1.1.0
		 */

		private static function upload_message( $key, $type = 'error' )
		{
			// ⚠️ Without the texts of a plugin there is no message, rather than an English one on a German page.
			if ( ! empty( self::$upload_texts[ $key ] ) && function_exists( 'add_settings_error' ) )
				add_settings_error( self::OPTION, 'einvoice_design_kit_' . $key, (string) self::$upload_texts[ $key ], $type );

			return( '' );
		}


		/**
		 * Keeps the custom properties of a token file and rebuilds it, or returns null.
		 *
		 * Accepted are rules whose selector is `:root`, `:host`, `:root[data-theme="dark"]` or
		 * `:host([data-theme="dark"])`, alone or together, holding only declarations `--name: value`.
		 * Every `:root` part gets its `:host` twin, so the kit also reaches the Shadow Roots of the tools.
		 *
		 * ⚠️ **REFUSED, NOT REPAIRED**, when anything else stands in the file, when a value holds `url(`,
		 * `@`, an angle bracket, a backslash or `expression`, or when the two tokens every kit has
		 * ( `--bg`, `--accent` ) are missing. A half-kept file would look like a kit and draw half a page.
		 *
		 * @param	string	$css
		 * @param	string	$slug
		 * @return	string|null
		 * @since	1.1.0
		 */

		private static function clean_tokens( $css, $slug )
		{
			$css = (string) preg_replace( '~/\*.*?\*/~s', '', str_replace( "\r", '', $css ) );

			if ( ! preg_match_all( '/([^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER ) )
				return( null );

			// Nothing may stand outside the rules.
			if ( trim( (string) preg_replace( '/[^{}]*\{[^{}]*\}/', '', $css ) ) !== '' )
				return( null );

			$light = ':root,:host';
			$dark  = ':root[data-theme="dark"],:host([data-theme="dark"])';
			$parts = array( $light => array(), $dark => array() );

			foreach ( $rules as $rule )
			{
				$target = null;

				foreach ( explode( ',', (string) preg_replace( '/\s+/', '', $rule[ 1 ] ) ) as $sel )
				{
					if ( $sel === ':root' || $sel === ':host' )
						$t = $light;
					elseif ( $sel === ':root[data-theme="dark"]' || $sel === ":root[data-theme='dark']" || $sel === ':host([data-theme="dark"])' || $sel === ":host([data-theme='dark'])" )
						$t = $dark;
					else
						return( null );

					if ( $target !== null && $target !== $t )
						return( null );

					$target = $t;
				}

				foreach ( explode( ';', $rule[ 2 ] ) as $decl )
				{
					if ( trim( $decl ) === '' )
						continue;

					if ( ! preg_match( '/^\s*(--[a-zA-Z0-9-]+)\s*:\s*(.+?)\s*$/s', $decl, $d ) )
						return( null );

					if ( preg_match( '/url\s*\(|expression|@|[<>\\\\{}]|javascript:/i', $d[ 2 ] ) )
						return( null );

					$parts[ $target ][ $d[ 1 ] ] = (string) preg_replace( '/\s+/', ' ', $d[ 2 ] );
				}
			}

			if ( ! isset( $parts[ $light ][ '--bg' ], $parts[ $light ][ '--accent' ] ) )
				return( null );

			$out = '/* Tokens — Kit ' . ucfirst( $slug ) . " · uploaded; rewritten by the eInvoice tools, only custom properties kept. */\n";

			foreach ( $parts as $selector => $decls )
			{
				if ( ! $decls )
					continue;

				$out .= "\n" . $selector . "{\n";

				foreach ( $decls as $prop => $val )
					$out .= "\t" . $prop . ': ' . $val . ";\n";

				$out .= "}\n";
			}

			return( $out );
		}


		/**
		 * Shows the select on Settings → General, but only when no plugin with a page of its own did.
		 *
		 * The Technical Details, the Visualizer and the comparison have no settings page. They usually
		 * run next to the viewer or the validators, whose pages carry the select already; a second
		 * select on another page would only be a second place to look. When one of them runs alone,
		 * though, the choice would have no place at all, so it moves to the general settings.
		 *
		 * ⚠️ PRIORITY 20 ON `admin_init`, AFTER THE PLUGINS' OWN REGISTRATIONS AT 10. Only then is it
		 * known whether a plugin with its own page registered the option in this request.
		 *
		 * ⚠️ **THE TEXTS ARRIVE AS A CALLBACK, NOT AS STRINGS.** A plugin calls this from its
		 * constructor, which runs while the plugin file is included. A `__()` there loads the text domain
		 * before `init`, and WordPress 6.7 and later report that as „translation loading triggered too
		 * early". The callback runs on `admin_init`, when translating is allowed.
		 *
		 * @param	string		$dir			the plugin's kit folder
		 * @param	bool		$tokens_only	true when the plugin only loads the token file
		 * @param	callable	$texts			returns array( label, note ), both translated plain text, and since
		 *										1.1.0 optionally a third entry: the texts of the upload, see render_field()
		 * @return	void
		 * @since	1.0.0
		 */

		public static function offer_on_general_page( $dir, $tokens_only, $texts )
		{
			add_action( 'admin_init', static function () use ( $dir, $tokens_only, $texts ) {

				// The folder counts for sanitize() in any case, the field only when nobody else shows it.
				self::$dirs[ trailingslashit( (string) $dir ) ] = (bool) $tokens_only;

				if ( self::$has_page )
					return;

				/*
				 * ⚠️ **THE OPTION IS REGISTERED IN A GROUP ONLY WHERE THE FIELD IS ALSO SHOWN.** On saving,
				 * `options.php` writes every option of the group and passes `null` for one that is missing
				 * from the form. sanitize() turns `null` into Kit H, so an option registered in `general`
				 * without its field would reset the kit each time someone saved the general settings.
				 */
				$t      = is_callable( $texts ) ? (array) call_user_func( $texts ) : array();
				$label  = isset( $t[ 0 ] ) ? (string) $t[ 0 ] : 'Design kit';
				$note   = isset( $t[ 1 ] ) ? (string) $t[ 1 ] : '';
				$upload = isset( $t[ 2 ] ) && is_array( $t[ 2 ] ) ? $t[ 2 ] : array();

				self::register_setting( 'general', $dir, $tokens_only, false, $upload );

				// ⚠️ Several plugins without a page may ask in one request; the field appears once.
				self::$has_page = true;

				add_settings_field( self::OPTION, esc_html( $label ), static function () use ( $dir, $tokens_only, $note, $upload ) {
					self::render_field( $dir, $tokens_only, $note, $upload );
				}, 'general' );

			}, 20 );
		}


	}


	endif;
