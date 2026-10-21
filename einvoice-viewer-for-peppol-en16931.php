<?php
/**
 * Plugin Name: eInvoice Viewer for PEPPOL & EN16931
 * Plugin URI:  https://invoicepress.de/en/solutions/einvoice-tools/einvoice-viewer-for-peppol-en16931/
 * Description: Upload and visualize electronic invoices (ZUGFeRD / Factur-X / XRechnung, CII & UBL) via a shortcode. Inspired by the ZUGFeRD Quba viewer.
 * Version:     2.37.0
 * Author:      Ralf Fuhrmann
 * Author URI:  https://www.specialpress.de
 * Requires PHP: 8.2
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: einvoice-viewer-for-peppol-en16931
 * Domain Path: /languages
 */

declare( strict_types = 1 );

// block direct access to this file

if ( ! defined( 'ABSPATH' ) )
	exit;


/**
 * ⚠️ THIS PLUGIN REGISTERS ITS BUNDLED TRANSLATIONS ITSELF, ALTHOUGH WORDPRESS OFTEN DOES IT FOR IT.
 *
 * The source texts are English, so a German or French site shows its language only if the .mo
 * under /languages is found. Since WordPress 6.8 wp-settings.php registers the `Domain Path` folder
 * of every SITE-activated plugin, and just-in-time loading then finds the file without this call.
 * Without it the plugin stays English in these cases:
 *
 * - NETWORK-activated on a multisite. The loop for network plugins ( `wp_get_active_network_plugins()`
 *   in wp-settings.php ) registers no path; measured with the WordPress 7.1.3 core of the development
 *   site, where a network-activated copy stayed English on a German site.
 * - WordPress 6.7 and older, which `Requires at least` still allows. Their wp-settings.php registers
 *   no plugin folder, and their just-in-time loader only looks in wp-content/languages/.
 *
 * ⚠️ ON `init`, NEVER EARLIER. Since WordPress 6.7 this call only registers the folder; the file is
 * read on the first __() of the domain. A __() of this domain before `after_setup_theme` triggers the
 * notice "_load_textdomain_just_in_time was called incorrectly", and any text translated before this
 * hook stays English. Priority 0, so that texts translated on `init` itself already find the folder.
 *
 * ⚠️ Plugin Check reports load_plugin_textdomain() as discouraged ( a warning, not an error ). The
 * call does not push aside a language pack from translate.wordpress.org: WP_Textdomain_Registry
 * looks in wp-content/languages/plugins/ first and uses the registered folder only as a fallback.
 */
add_action( 'init', static function ()
{
	load_plugin_textdomain( 'einvoice-viewer-for-peppol-en16931', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}, 0 );


define( 'SPEV_VERSION', '2.37.0' );
define( 'SPEV_FILE', __FILE__ );
define( 'SPEV_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPEV_URL', plugin_dir_url( __FILE__ ) );

/**
 * The shortcode tag.
 *
 * ⚠️ **IT IS CALLED `spev_einvoice_viewer`, SINCE 2.18.0.** WordPress.org requires the plugin's own prefix for
 * everything a plugin registers globally; `einvoice_viewer` was a general word every plugin of this kind
 * could reach for.
 *
 * ⚠️ **THERE IS NO ALIAS, AND THAT HAS CONSEQUENCES.** A page that still carries `[einvoice_viewer]` shows
 * **nothing** after the update - an unknown shortcode gives no output and no error message. That is why
 * `readme.txt` carries an "Upgrade Notice" section that says exactly that.
 *
 * ⚠️ **AS A CONSTANT, NOT AS A STRING IN EVERY PLACE.** The tag is registered, checked when the files are
 * queued, passed to `shortcode_atts()` and named on the settings page.
 */
define( 'SPEV_SHORTCODE', 'spev_einvoice_viewer' );


/**
 * ⚠️ NO einvoice-host-wp.
 *
 * `Rest::init()` of that package registers the AJAX action `einvoice_nonce` and the namespace `einvoice/v1`,
 * and this plugin would gain nothing from it:
 *
 * - This plugin registers **no** route of its own ( see the ⚠️ comment in `class-spev-plugin.php`: "NO MORE
 *   spev_nonce / spev_render / einvoice/v1/render" ).
 * - The buttons of the neighbouring plugins fetch their nonce each through the plugin's **own** AJAX action -
 *   `spkv_nonce`, `spvx_nonce`, `spvz_nonce`, `spic_nonce`, see `assets/js/spev-uploader.js`. Measured:
 *   `einvoice_nonce` occurs in **no** JavaScript file of the family.
 * - Every plugin that really needs `einvoice/v1` calls `Rest::init()` itself ( explainer, both validators,
 *   exporter ) - this plugin was never the precondition for that.
 *
 * The free viewer is thus pure JavaScript apart from the WordPress shell: no `vendor/`, no Composer
 * dependency, no PHP reading core.
 */

// ⚠️ SHARED CLASS, GUARDED: the design-kit choice is one option for nine plugins, and whichever of them
// loads first owns the class. The copies are byte-identical; _claude-tools/kit-sync.php checks that.
if ( ! class_exists( 'EInvoicePress_Design_Kit' ) )
	require_once( SPEV_DIR . 'includes/class-einvoicepress-design-kit.php' );

// ⚠️ SHARED CLASS, GUARDED, like EInvoicePress_Design_Kit: whichever plugin loads first owns it. It answers a
// request from the front end in the language of the page; _claude-tools/request-language-sync.php keeps the copies equal.
if ( ! class_exists( 'EInvoicePress_Request_Language' ) )
	require_once( SPEV_DIR . 'includes/class-einvoicepress-request-language.php' );

// ⚠️ TWO CLASSES OF libs/einvoice-host-wp, NOT THE WHOLE LIBRARY. The viewer needs the daily limit per role and the
// gate "available from role" for its add-ons, nothing else of it; both classes guard themselves with class_exists(),
// so a copy another plugin of the family loaded first is used instead. They carry new names, so no older copy of
// the old RateLimit can stand in for them ( see the header of RoleRateLimit.php ).
require_once( SPEV_DIR . 'libs/einvoice-host-wp/src/RoleRateLimit.php' );
require_once( SPEV_DIR . 'libs/einvoice-host-wp/src/RoleGate.php' );

require_once( SPEV_DIR . 'includes/class-spev-settings.php' );
require_once( SPEV_DIR . 'includes/class-spev-settings-rest.php' );
require_once( SPEV_DIR . 'includes/class-spev-plugin.php' );


// ⚠️ THERE IS NO READING/RENDERING CORE IN THIS PLUGIN. ModelBuilder/Reader/EInvoice/Context, the
// horstoeko/invoicesuite dependency and the QUBA HTML template live in `libs/einvoice-model` - a shared
// package the Exporter and Visualizer plugins vendor directly, since they are the ones that still read a raw
// uploaded file server-side. This free Viewer plugin renders entirely client-side (the shortcode's own JS,
// `<einvoice-viewer>` from libs/einvoice-core-js) - see this plugin's own STATUS.md for the measurements
// (0 horstoeko references, no ModelBuilder.php anywhere in this plugin).
//
// boot

SPEV_Settings::init();
SPEV_Settings_REST::init();
SPEV_Plugin::instance();


/**
 * The link to the manual in the plugin list: "Manual" under the name, "Manual · Support" in the description line
 * ( Ralf's order F3.10 of 18.10.2026, class ManualLink in einvoice-host-wp ).
 *
 * ⚠️ IN A CLOSURE, SO THAT NO VARIABLE LANDS IN THE GLOBAL SCOPE. The class comes from this plugin's own copy of
 * einvoice-host-wp, under libs/ or vendor/; whichever copy loads first serves every plugin of the site, which is why
 * the class is only required when nobody has loaded it yet.
 */
( static function () {

	if ( ! class_exists( '\SpecialPress\EInvoice\Host\ManualLink' ) )
	{
		foreach ( array( '/libs/einvoice-host-wp/src/ManualLink.php', '/vendor/specialpress/einvoice-host-wp/src/ManualLink.php' ) as $file )
		{
			if ( is_readable( __DIR__ . $file ) )
			{
				require_once __DIR__ . $file;
				break;
			}
		}
	}

	if ( ! class_exists( '\SpecialPress\EInvoice\Host\ManualLink' ) )
		return;

	\SpecialPress\EInvoice\Host\ManualLink::register( __FILE__, static function () {
		return( array(
			'manual'  => __( 'Manual', 'einvoice-viewer-for-peppol-en16931' ),
			'support' => __( 'Support', 'einvoice-viewer-for-peppol-en16931' ),
		) );
	}, 'https://wordpress.org/support/plugin/einvoice-viewer-for-peppol-en16931/' );

} )();
