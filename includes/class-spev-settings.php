<?php
/**
 * SP E-Invoice Viewer - settings page ( Settings > E-Invoice Viewer ).
 *
 * @package SP_EInvoice_Viewer
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) )
	exit;


class SPEV_Settings
{


	const GROUP = 'spev_settings';
	const PAGE  = 'spev-settings';


	/**
	 * Register the settings hooks.
	 *
	 * @return	void
	 */

	public static function init()
	{
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}



	/**
	 * Add the options page.
	 *
	 * @return	void
	 */

	public static function menu()
	{
		add_options_page(
			esc_html__( 'E-Invoice Viewer', 'einvoice-viewer-for-peppol-en16931' ),
			esc_html__( 'E-Invoice Viewer', 'einvoice-viewer-for-peppol-en16931' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' )
		);
	}



	/**
	 * Register the settings, sections and fields.
	 *
	 * @return	void
	 */

	public static function register()
	{


		/**
		 * ⚠️ **NO `spev_daily_limit` AND NO `spev_trust_proxy` HERE.** Both belonged to a rate limit of an upload
		 * endpoint: "daily uploads per visitor" counted calls of `spev_render` per user or per hashed IP, and
		 * "trust proxy header" decided whether `X-Forwarded-For` counted instead of `REMOTE_ADDR`.
		 *
		 * ⚠️ **THERE IS NO UPLOAD TO SLOW DOWN.** Reading takes place entirely in the browser; there is no endpoint,
		 * no nonce and no limit for it. Input fields for the two values would promise an effect that does not
		 * exist - measured: **no** `get_option()` on either value outside its own input fields,
		 * and `RateLimit.php` does not lie in this plugin at all.
		 *
		 * ⚠️ **WHERE A LIMIT BELONGS, IT EXISTS.** The add-ons really send to `admin-ajax.php` and bring their own -
		 * `spko_daily_limit`, `spvx_daily_limit`, `sppv_daily_limit`, each through `libs/einvoice-host-wp`'s
		 * `RateLimit.php`. A limit in the free viewer would first have to invent a server call and thereby give
		 * up exactly the property this plugin advertises.
		 *
		 * Stored values stay behind as orphaned options in the database - like `spev_template` below; nothing
		 * read them that had any effect.
		 */

		register_setting( self::GROUP, 'spev_max_filesize_mb', array(
			'type'              => 'integer',
			'sanitize_callback' => array( __CLASS__, 'sanitize_int' ),
			'default'           => 8,
		) );

		register_setting( self::GROUP, 'spev_show_bt', array(
			'type'              => 'boolean',
			'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			'default'           => false,
		) );

		// ⚠️ NO `spev_template` ( "Layout template" ). It selected between server-side HTML templates
		// ( SPEV_Renderer::template_choices() ), and the shortcode renders client-side through the QUBA-only
		// `<einvoice-viewer>` web component ( libs/einvoice-core-js, einvoice-viewer.mjs imports
		// QubaTemplate.mjs unconditionally, no template switch exists on that side ). Changing the setting had
		// no visible effect for any visitor, and its PHP would fatal without SPEV_Renderer/`includes/core/`. A
		// previously-saved `spev_template` option value is simply orphaned in the database, not migrated - it
		// was never read by anything that mattered.

		add_settings_section( 'spev_main', esc_html__( 'E-Invoice Viewer', 'einvoice-viewer-for-peppol-en16931' ), array( __CLASS__, 'section_intro' ), self::PAGE );

		add_settings_field( 'spev_max_filesize_mb', esc_html__( 'Maximum file size ( MB )', 'einvoice-viewer-for-peppol-en16931' ), array( __CLASS__, 'field_max_filesize' ), self::PAGE, 'spev_main' );
		add_settings_field( 'spev_show_bt', esc_html__( 'Show BT numbers by default', 'einvoice-viewer-for-peppol-en16931' ), array( __CLASS__, 'field_show_bt' ), self::PAGE, 'spev_main' );

		/*
		 * ⚠️ **THE DESIGN KIT IS ONE OPTION FOR ALL EINVOICE TOOLS, NOT A SETTING OF THIS PLUGIN.** The
		 * same select stands on the pages of the validators and explorers and writes the same option
		 * `einvoice_design_kit`. The note under the select says so, so nobody is surprised that the
		 * validator changed too. The kits on offer are the ones found in `assets/css/`.
		 */
		EInvoicePress_Design_Kit::register_setting( self::GROUP, SPEV_DIR . 'assets/css/', false, true, self::kit_upload_texts() );
		add_settings_field( EInvoicePress_Design_Kit::OPTION, esc_html__( 'Design kit', 'einvoice-viewer-for-peppol-en16931' ), array( __CLASS__, 'field_design_kit' ), self::PAGE, 'spev_main' );


		/*
		 * The daily limit per role and "available from role" for the add-ons, in a section of their own.
		 *
		 * ⚠️ THE TABLE STARTS EMPTY, SO EVERY ROLE IS UNLIMITED AFTER AN UPDATE. A site must not lose access because
		 * of an update. See SPEV_Plugin::ratelimit() for why the options are named `spev_view_*` and not
		 * `spev_*`.
		 */
		register_setting( self::GROUP, SPEV_Plugin::ratelimit()->optionRoleLimits(), array(
			'type'              => 'array',
			'sanitize_callback' => array( 'SpecialPress\EInvoice\Host\RoleRateLimit', 'sanitizeRoleLimits' ),
		) );

		register_setting( self::GROUP, SPEV_Plugin::ratelimit()->optionTrustProxy(), array(
			'type'              => 'boolean',
			'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			'default'           => false,
		) );

		register_setting( self::GROUP, SPEV_Plugin::EXTENSION_OPTION, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_extension_roles' ),
			'default'           => array(),
		) );

		add_settings_section( 'spev_access', esc_html__( 'Access and limits', 'einvoice-viewer-for-peppol-en16931' ), array( __CLASS__, 'section_access' ), self::PAGE );
		add_settings_field( 'spev_view_role_limits', esc_html__( 'Invoices per day and role', 'einvoice-viewer-for-peppol-en16931' ), array( __CLASS__, 'field_role_limits' ), self::PAGE, 'spev_access' );
		add_settings_field( 'spev_view_trust_proxy', esc_html__( 'Trust proxy header', 'einvoice-viewer-for-peppol-en16931' ), array( __CLASS__, 'field_trust_proxy' ), self::PAGE, 'spev_access' );
		add_settings_field( SPEV_Plugin::EXTENSION_OPTION, esc_html__( 'Add-ons available from role', 'einvoice-viewer-for-peppol-en16931' ), array( __CLASS__, 'field_extension_roles' ), self::PAGE, 'spev_access' );


		/**
		 * Fires after the Viewer's own settings/sections/fields are registered, so an extension plugin
		 * (Exporter, Extended Profiles, Technical Details, Visualizer, ...) can add its OWN section to
		 * this same settings page instead of - or in addition to - a standalone options page of its
		 * own. WordPress' Settings API already renders every section registered against a given page
		 * slug regardless of which plugin added it, via the normal `add_settings_section()` /
		 * `add_settings_field()` calls - this hook only exists so an extension does not have to guess
		 * or hardcode the Viewer's group/page names.
		 *
		 * ⚠️ FIRES HERE, ON admin_init, NOT EARLIER. An extension's own listener
		 * ( `add_action( 'spev_register_settings', ... )` ) only needs to be REGISTERED before this
		 * line runs - not before the Viewer's OWN admin_init callback fires - so it is safe for an
		 * extension to add that listener from its own bootstrap/`init()`, regardless of plugin load
		 * order ( same reasoning as every other cross-plugin hook in this family - see
		 * register_explainer_bridge()'s own comment in the Validator for the general pattern ).
		 *
		 * @param	string	$group	register_setting() group name to reuse - self::GROUP.
		 * @param	string	$page	add_settings_section()/add_settings_field() page slug to reuse - self::PAGE.
		 * @since	2.10.7
		 */

		do_action( 'spev_register_settings', self::GROUP, self::PAGE );

	}



	public static function sanitize_int( $value )
	{
		$value = (int) $value;
		return( $value < 0 ? 0 : $value );
	}



	public static function sanitize_bool( $value )
	{
		return( ! empty( $value ) ? 1 : 0 );
	}



	/**
	 * The add-ons the table "Add-ons available from role" offers, with their names.
	 *
	 * ⚠️ ONLY THE INSTALLED ONES, so the table names nothing the operator cannot use. The check runs on 'admin_init',
	 * after every plugin's main file. ⚠️ Literal __() calls per name, for the .pot.
	 *
	 * @return	array<string,string>	slug => name
	 * @since	2.35.0
	 */

	public static function extensions()
	{
		$all = array(
			'technical-details' => array( 'SVTD_Plugin', __( 'Technical Details ( field and rule browser, tooltips )', 'einvoice-viewer-for-peppol-en16931' ) ),
			'extended-profiles' => array( 'SVZE_Plugin', __( 'Extended Profiles', 'einvoice-viewer-for-peppol-en16931' ) ),
			'visualizer'        => array( 'SPVZ_Plugin', __( 'Visualizer', 'einvoice-viewer-for-peppol-en16931' ) ),
			'identifier-check'  => array( 'SPIC_Plugin', __( 'Identifier Check', 'einvoice-viewer-for-peppol-en16931' ) ),
			'inbound-check'     => array( 'SPIV_Plugin', __( 'Inbound Check', 'einvoice-viewer-for-peppol-en16931' ) ),
			'exporter'          => array( 'SPVX_Plugin', __( 'Exporter ( Excel )', 'einvoice-viewer-for-peppol-en16931' ) ),
			'kosit'             => array( 'SPKO_Plugin', __( 'Validation with KoSIT', 'einvoice-viewer-for-peppol-en16931' ) ),
			// ⚠️ The name of these two rows comes from the validator, like its button ( SPEV_Plugin::validator_texts() ).
			'mustang'           => array( 'SPMV_Plugin', ( class_exists( 'SPMV_Plugin' ) && method_exists( 'SPMV_Plugin', 'bridge_texts' ) ) ? \SPMV_Plugin::bridge_texts()['button'] : 'Mustang' ),
			'vampire'           => array( 'SPPV_Plugin', ( class_exists( 'SPPV_Plugin' ) && method_exists( 'SPPV_Plugin', 'bridge_texts' ) ) ? \SPPV_Plugin::bridge_texts()['button'] : 'VAMPIRE' ),
		);

		$out = array();

		foreach( $all as $slug => $entry )
			if( class_exists( $entry[0] ) )
				$out[ $slug ] = $entry[1];

		return( $out );
	}



	/**
	 * @param	mixed	$value
	 * @return	array<string,string>	slug => RoleGate level
	 * @since	2.35.0
	 */

	public static function sanitize_extension_roles( $value )
	{
		$out = array();

		foreach( array( 'technical-details', 'extended-profiles', 'visualizer', 'identifier-check', 'inbound-check', 'exporter', 'kosit', 'mustang', 'vampire' ) as $slug )
			$out[ $slug ] = \SpecialPress\EInvoice\Host\RoleGate::sanitize( is_array( $value ) ? ( $value[ $slug ] ?? '' ) : '' );

		return( $out );
	}



	public static function section_access()
	{
		echo '<p>' . esc_html__( 'Who may use the viewer how often, and from which role each add-on is available. Administrators are never limited.', 'einvoice-viewer-for-peppol-en16931' ) . '</p>';
	}



	public static function field_role_limits()
	{
		SPEV_Plugin::ratelimit()->renderRoleLimitsField( array(
			'guest' => __( 'Guest ( not logged in )', 'einvoice-viewer-for-peppol-en16931' ),
			'role'  => __( 'Role', 'einvoice-viewer-for-peppol-en16931' ),
			'limit' => __( 'Invoices per day', 'einvoice-viewer-for-peppol-en16931' ),
			'help'  => __( 'Per calendar day. Empty = unlimited, 0 = no access. Logged-in users are counted by user, guests by IP address. A user with several roles gets the most generous value. The invoice is still read in the browser; for the count the viewer only asks the server before each invoice, without sending it.', 'einvoice-viewer-for-peppol-en16931' ),
		) );
	}



	public static function field_trust_proxy()
	{
		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( SPEV_Plugin::ratelimit()->optionTrustProxy() ),
			checked( (bool) get_option( SPEV_Plugin::ratelimit()->optionTrustProxy() ), true, false ),
			esc_html__( 'Count guests by the X-Forwarded-For header. Only switch this on behind a reverse proxy or CDN; otherwise a visitor can choose his own counter.', 'einvoice-viewer-for-peppol-en16931' )
		);
	}



	public static function field_extension_roles()
	{
		$extensions = self::extensions();

		if( $extensions === array() )
		{
			echo '<p class="description">' . esc_html__( 'No add-on of the viewer is active.', 'einvoice-viewer-for-peppol-en16931' ) . '</p>';
			return;
		}

		$levels = get_option( SPEV_Plugin::EXTENSION_OPTION, array() );
		$labels = array(
			''                  => __( 'Everyone, also guests', 'einvoice-viewer-for-peppol-en16931' ),
			'read'              => __( 'From subscriber', 'einvoice-viewer-for-peppol-en16931' ),
			'edit_posts'        => __( 'From contributor', 'einvoice-viewer-for-peppol-en16931' ),
			'publish_posts'     => __( 'From author', 'einvoice-viewer-for-peppol-en16931' ),
			'edit_others_posts' => __( 'From editor', 'einvoice-viewer-for-peppol-en16931' ),
			'manage_options'    => __( 'Administrators only', 'einvoice-viewer-for-peppol-en16931' ),
		);

		echo '<table class="widefat striped" style="max-width:40em"><tbody>';

		foreach( $extensions as $slug => $name )
		{
			echo '<tr><td>' . esc_html( $name ) . '</td><td>';
			\SpecialPress\EInvoice\Host\RoleGate::renderSelect( SPEV_Plugin::EXTENSION_OPTION . '[' . $slug . ']', is_array( $levels ) ? (string) ( $levels[ $slug ] ?? '' ) : '', $labels );
			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Below the chosen role the viewer behaves as if the add-on were not installed: no button, no fields, no requests.', 'einvoice-viewer-for-peppol-en16931' ) . '</p>';
	}



	public static function section_intro()
	{
		/*
		 * ⚠️ THE TAG DOES NOT STAND IN THE TRANSLATED SENTENCE.
		 *
		 * As part of the msgid ( "Add the [einvoice_viewer] shortcode to a page …" ) the tag would stand in every
		 * `.po`, in every `.mo` **and** in the label table of the bundle. Measured: four places in
		 * `assets/js/einvoice-viewer.js` alone. A rename of the shortcode would then demand three
		 * translations and a rebuild of the bundle - and whoever forgets that names a tag to the administrator
		 * that is not registered.
		 */
		echo '<p>' . sprintf(
			/* translators: %s is the literal shortcode tag, not translated. */
			esc_html__( 'Add the %s shortcode to a page to let visitors upload and visualize an electronic invoice.', 'einvoice-viewer-for-peppol-en16931' ),
			'<code>[' . esc_html( SPEV_SHORTCODE ) . ']</code>'
		) . '</p>';
	}



	/**
	 * ⚠️ **THE LIMIT APPLIES IN THE BROWSER, NOT ON THE SERVER** - it goes as `data-max-size` to `<einvoice-viewer>`
	 * and prevents a file that is too large from being read into memory at all. The server never sees it
	 * anyway; the number still makes sense, because the add-ons send the document to `admin-ajax.php`, and what
	 * PHP does not accept there the user should not be able to choose in the first place.
	 */

	public static function field_max_filesize()
	{
		printf(
			'<input type="number" min="1" name="spev_max_filesize_mb" value="%d" class="small-text" />',
			(int) get_option( 'spev_max_filesize_mb', 8 )
		);
	}



	public static function field_show_bt()
	{
		printf(
			'<label><input type="checkbox" name="spev_show_bt" value="1" %s /> %s</label>',
			checked( (bool) get_option( 'spev_show_bt' ), true, false ),
			esc_html__( 'Display the EN16931 Business Term ( BT ) numbers next to the fields. Visitors can still toggle this in the viewer.', 'einvoice-viewer-for-peppol-en16931' )
		);
	}



	/**
	 * The texts of the kit upload under the select, in this plugin's text domain.
	 *
	 * ⚠️ They are passed to register_setting() too, because the messages are needed while the form is
	 * saved, a request in which field_design_kit() does not run.
	 *
	 * @return	array<string,string>
	 */

	private static function kit_upload_texts()
	{
		return( array(
			'label'      => __( 'Upload a kit of your own', 'einvoice-viewer-for-peppol-en16931' ),
			'hint'       => __( 'A token file ( .css ) as the tool kit ships it, at most 32 KB. Only its custom properties under :root and :host are kept. After saving, the kit is chosen for every eInvoice tool.', 'einvoice-viewer-for-peppol-en16931' ),
			'failed'     => __( 'The kit could not be stored. Please try again.', 'einvoice-viewer-for-peppol-en16931' ),
			'too_large'  => __( 'The kit file is larger than 32 KB.', 'einvoice-viewer-for-peppol-en16931' ),
			'not_tokens' => __( 'This file is not a token file of a design kit. It may only hold custom properties under :root and :host, among them --bg and --accent.', 'einvoice-viewer-for-peppol-en16931' ),
			'stored'     => __( 'The kit was stored and is now chosen for every eInvoice tool.', 'einvoice-viewer-for-peppol-en16931' ),
		) );
	}


	/**
	 * The select of the design kit, shared by all eInvoice tools.
	 *
	 * @return	void
	 * @since	2.26.0
	 */

	public static function field_design_kit()
	{
		EInvoicePress_Design_Kit::render_field(
			SPEV_DIR . 'assets/css/',
			false,
			__( 'The choice applies to all eInvoice tools on this site. Kit H is the default.', 'einvoice-viewer-for-peppol-en16931' ),
			self::kit_upload_texts()
		);
	}



	/**
	 * Render the settings page.
	 *
	 * @return	void
	 */

	public static function page()
	{

		if( ! current_user_can( 'manage_options' ) )
			return;

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'E-Invoice Viewer', 'einvoice-viewer-for-peppol-en16931' ); ?></h1>
			<form method="post" action="options.php">
				<?php
					settings_fields( self::GROUP );
					do_settings_sections( self::PAGE );
					submit_button();
				?>
			</form>
		</div>
		<?php

	}


}
