<?php
	/**
	 Class Name: EInvoicePress_Request_Language
	 Description: Answers a request that a page of the site sends in the background in the language
	              of that page, not in the profile language of a logged-in user.
	 Version: 1.0.0
	 Author: Ralf Fuhrmann
	 */


	declare( strict_types = 1 );

	if ( ! defined( 'ABSPATH' ) )
		exit;


	/**
	 * class: EInvoicePress_Request_Language
	 *
	 * ============================================================================================
	 * WHAT IT DOES
	 * ============================================================================================
	 *
	 * The page tells its language to the script that sends the request, and the handler switches to
	 * that language before it writes a single word of its answer:
	 *
	 *     // when the page is built ( front end ):
	 *     'lang' => EInvoicePress_Request_Language::page_language(),
	 *
	 *     // in the script:
	 *     body.append( 'lang', SPXX.lang );
	 *
	 *     // first thing in the AJAX handler, after the nonce check:
	 *     EInvoicePress_Request_Language::switch_to_page_language();
	 *
	 * ============================================================================================
	 * WHY IT IS NEEDED
	 * ============================================================================================
	 *
	 * ⚠️ **A REQUEST TO `admin-ajax.php` COUNTS AS THE ADMIN AREA, EVEN WHEN A PAGE OF THE SITE SENDS
	 * IT.** `wp-admin/admin-ajax.php` defines `WP_ADMIN` as true, `is_admin()` then answers true, and
	 * `determine_locale()` returns `get_user_locale()` - the profile language of the logged-in user.
	 * The page itself is in the language of the site ( WordPress 4.7: the profile language is for the
	 * back end only ). Without this class an administrator whose profile says English saw a German
	 * page with English validation results. A visitor without a login never notices, because
	 * `get_user_locale()` then falls back to `get_locale()`.
	 *
	 * ⚠️ **ONE SWITCH IS ENOUGH FOR EVERY LATER CALL.** `switch_to_locale()` hangs the locale switcher
	 * on the filters `locale` AND `determine_locale` ( `wp-includes/class-wp-locale-switcher.php` ), and
	 * it reloads the text domains. Every `__()` and every `determine_locale()` in the handler and in the
	 * libraries it calls - rule texts, the invoice model, the KoSIT visualization - then speaks the
	 * language of the page. Nothing below the handler has to change.
	 *
	 * ⚠️ **POLYLANG READS THE SAME PARAMETER.** For a request from the front end Polylang takes its
	 * language from `lang` in the request, otherwise from its cookie `pll_language`, which every page
	 * view sets - so with two tabs in two languages the cookie names the tab loaded last. Sending `lang`
	 * makes Polylang and this class agree on the page that actually sent the request.
	 *
	 * ⚠️ **THE VALUE COMES FROM THE BROWSER AND IS ONLY EVER A KEY.** It is matched against the site
	 * language and the installed languages; anything else falls back to `get_locale()`. It never
	 * reaches a path, a query or the output.
	 *
	 * The class lies as the same file in every plugin that needs it; whoever loads first owns it for
	 * all. The source is the copy in the free viewer, and `_claude-tools/request-language-sync.php`
	 * keeps the copies equal.
	 *
	 * @since	1.0.0
	 */

	final class EInvoicePress_Request_Language
	{

		/** The request parameter. The same name as Polylang's, on purpose - see above. */
		const PARAM = 'lang';


		/**
		 * The two-letter language of the page being built, for the script's localized object.
		 *
		 * ⚠️ Only meaningful while a page of the front end is built. There `determine_locale()` is the
		 * language of the page, including the one a multilingual plugin set for it.
		 *
		 * @return	string	e.g. 'de'
		 * @since	1.0.0
		 */

		public static function page_language()
		{
			return( strtolower( substr( (string) determine_locale(), 0, 2 ) ) );
		}


		/**
		 * Switches the current request to the language the page sent along.
		 *
		 * Call it in a handler for the front end, after the nonce check and before anything that
		 * produces text. Without the parameter nothing happens, so an older script that does not
		 * send it keeps working as before.
		 *
		 * @return	bool	true when the language was switched
		 * @since	1.0.0
		 */

		public static function switch_to_page_language()
		{
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- only a key into the list of installed languages; the calling handler checks its own nonce.
			$raw = isset( $_REQUEST[ self::PARAM ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ self::PARAM ] ) ) : '';

			if ( $raw === '' )
				return( false );

			$locale = self::resolve( $raw );

			if ( $locale === '' || $locale === determine_locale() )
				return( false );

			return( switch_to_locale( $locale ) );
		}


		/**
		 * The installed locale for a language the page named.
		 *
		 * ⚠️ **THE SITE LANGUAGE FIRST.** For `de` on a site in `de_DE_formal` the answer must be the
		 * formal one; taking the first German locale from the list would pick `de_DE` or `de_CH` at
		 * random, depending on which language packs happen to be installed.
		 *
		 * @param	string	$lang	'de', 'de_DE', …
		 * @return	string	an installed locale, or '' for "no switch"
		 * @since	1.0.0
		 */

		private static function resolve( $lang )
		{
			$lang = preg_replace( '/[^A-Za-z_]/', '', (string) $lang );
			$code = strtolower( substr( $lang, 0, 2 ) );

			if ( strlen( $code ) !== 2 )
				return( '' );

			$site = (string) get_locale();

			if ( strtolower( substr( $site, 0, 2 ) ) === $code )
				return( $site );

			$installed = function_exists( 'get_available_languages' ) ? (array) get_available_languages() : array();

			if ( in_array( $lang, $installed, true ) )
				return( $lang );

			foreach ( $installed as $locale )
				if ( strtolower( substr( (string) $locale, 0, 2 ) ) === $code )
					return( (string) $locale );

			// English needs no language pack; every other language without one stays as it is.
			return( $code === 'en' ? 'en_US' : '' );
		}

	}
