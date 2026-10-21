<?php
/**
 * The store of the shared settings dialog in WordPress: `einvoice-viewer/v1/settings`.
 *
 * The dialog of the viewer is the same JavaScript in the app and here; in the app it keeps the settings in the browser,
 * here it keeps them on the site through these routes ( `RestSettingsStore` in `einvoice-core-js/src/model/Settings.mjs` ).
 *
 *   GET    /settings              every section the user may read, with the revision, and what the user may write
 *   GET    /settings/<id>         one section
 *   PUT    /settings/<id>         replace one section as a whole: { version, data, rev }
 *   GET    /settings/export       the backup file, the same format as in the app
 *   POST   /settings/import       { file, dryRun }: with dryRun the list of sections that would be replaced
 *
 * ⚠️ TWO RIGHTS PER SECTION, "MAINTAINS" AND "CHECKS". An add-on declares its section with the filter
 * `spev_settings_sections` and names a capability for each ( default for both: `manage_options` ). Reading needs
 * "checks", writing "maintains". A section nobody declared is open to administrators only.
 *
 * ⚠️ GUESTS GET NOTHING. Supplier lists hold bank details; a guest who may use the viewer must never receive them.
 * An add-on that has to compare such data for a guest offers a route of its own that answers only "known", "new" or
 * "different" ( planned for the Inbound Check as `…/match` ).
 *
 * ⚠️ THE REVISION STOPS LOST UPDATES. Every PUT sends the revision it read; when somebody else saved in between, the
 * answer is 409 and nothing is written.
 *
 * ⚠️ ONE OPTION PER SECTION, WITHOUT AUTOLOAD. A supplier list can be large and is needed only on the page of the viewer;
 * autoloaded it would be read on every request of the site.
 *
 * @package SP_EInvoice_Viewer
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) )
	exit;


class SPEV_Settings_REST
{


	const NAMESPACE_V1 = 'einvoice-viewer/v1';
	const OPTION_PREFIX = 'spev_settings_section_';

	/** The largest section in bytes of JSON; a supplier list of several thousand entries fits comfortably. */
	const MAX_BYTES = 2097152;

	/** A section id: lower-case letters, digits and hyphens, as in `Settings.mjs`. */
	const ID_PATTERN = '[a-z0-9][a-z0-9-]{0,63}';


	/**
	 * @return	void
	 */

	public static function init()
	{
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}



	/**
	 * @return	void
	 */

	public static function routes()
	{
		register_rest_route( self::NAMESPACE_V1, '/settings', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_all' ),
			'permission_callback' => '__return_true',
		) );

		// ⚠️ Registered before `/settings/<id>`, so that "export" and "import" are not read as section ids.
		register_rest_route( self::NAMESPACE_V1, '/settings/export', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'export' ),
			'permission_callback' => 'is_user_logged_in',
		) );

		register_rest_route( self::NAMESPACE_V1, '/settings/import', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'import' ),
			'permission_callback' => 'is_user_logged_in',
		) );

		register_rest_route( self::NAMESPACE_V1, '/settings/(?P<id>' . self::ID_PATTERN . ')', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_one' ),
				'permission_callback' => array( __CLASS__, 'can_read_request' ),
			),
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'put_one' ),
				'permission_callback' => array( __CLASS__, 'can_write_request' ),
			),
		) );
	}



	/**
	 * The sections the add-ons declared, with their two capabilities.
	 *
	 * @return	array<string,array{title:string,read:string,write:string}>
	 */

	public static function sections(): array
	{
		/**
		 * Lets an add-on declare its section of the settings dialog.
		 *
		 * @param	array	$sections	id => [ 'title' => string, 'read' => capability, 'write' => capability ]
		 */
		$declared = (array) apply_filters( 'spev_settings_sections', array() );
		$out      = array();

		foreach ( $declared as $id => $s )
		{
			if ( ! is_string( $id ) || ! preg_match( '/^' . self::ID_PATTERN . '$/', $id ) )
				continue;

			$s          = is_array( $s ) ? $s : array();
			$out[ $id ] = array(
				'title' => (string) ( $s['title'] ?? $id ),
				'read'  => (string) ( $s['read'] ?? 'manage_options' ),
				'write' => (string) ( $s['write'] ?? 'manage_options' ),
			);
		}

		return $out;
	}



	/**
	 * @param	string	$id
	 * @param	string	$right	'read' | 'write'
	 * @return	bool
	 */

	public static function may( string $id, string $right ): bool
	{
		if ( ! is_user_logged_in() )
			return false;

		$sections = self::sections();
		$cap      = isset( $sections[ $id ] ) ? $sections[ $id ][ $right ] : 'manage_options';

		// Whoever may maintain a section may also look at it.
		if ( $right === 'read' && isset( $sections[ $id ] ) && current_user_can( $sections[ $id ]['write'] ) )
			return true;

		return current_user_can( $cap );
	}



	/** @param \WP_REST_Request $request */
	public static function can_read_request( \WP_REST_Request $request ): bool
	{
		return self::may( (string) $request['id'], 'read' );
	}

	/** @param \WP_REST_Request $request */
	public static function can_write_request( \WP_REST_Request $request ): bool
	{
		return self::may( (string) $request['id'], 'write' );
	}



	/**
	 * @param	string	$id
	 * @return	array{version:int,data:mixed,rev:int}|null
	 */

	private static function read( string $id ): ?array
	{
		$v = get_option( self::OPTION_PREFIX . $id, null );

		if ( ! is_array( $v ) )
			return null;

		return array(
			'version' => (int) ( $v['version'] ?? 1 ),
			'data'    => $v['data'] ?? null,
			'rev'     => (int) ( $v['rev'] ?? 0 ),
		);
	}



	/**
	 * Writes one section and counts its revision up.
	 *
	 * @param	string	$id
	 * @param	int		$version
	 * @param	mixed	$data
	 * @return	int		the new revision
	 */

	private static function write( string $id, int $version, $data ): int
	{
		$old = self::read( $id );
		$rev = ( $old ? $old['rev'] : 0 ) + 1;

		// ⚠️ `update_option()` cannot switch autoload off for an existing option before WordPress 6.4; delete and add.
		delete_option( self::OPTION_PREFIX . $id );
		add_option( self::OPTION_PREFIX . $id, array( 'version' => $version, 'data' => $data, 'rev' => $rev ), '', false );

		self::remember( $id );

		return $rev;
	}



	/**
	 * Keeps the list of stored section ids, so that `get_all()` and the export find sections nobody declares any more.
	 *
	 * @param	string	$id
	 * @return	void
	 */

	private static function remember( string $id )
	{
		$ids = (array) get_option( 'spev_settings_section_ids', array() );

		if ( ! in_array( $id, $ids, true ) )
		{
			$ids[] = $id;
			update_option( 'spev_settings_section_ids', array_values( $ids ), false );
		}
	}



	/**
	 * @return	array<int,string>	every id that is declared or stored
	 */

	private static function all_ids(): array
	{
		return array_values( array_unique( array_merge( array_keys( self::sections() ), array_map( 'strval', (array) get_option( 'spev_settings_section_ids', array() ) ) ) ) );
	}



	/**
	 * @return	\WP_REST_Response
	 */

	public static function get_all(): \WP_REST_Response
	{
		$sections = array();
		$rights   = array();
		$can      = false;

		foreach ( self::all_ids() as $id )
		{
			$read  = self::may( $id, 'read' );
			$write = self::may( $id, 'write' );

			$rights[ $id ] = array( 'read' => $read, 'write' => $write );
			$can           = $can || $write;

			if ( $read && ( $entry = self::read( $id ) ) !== null )
				$sections[ $id ] = $entry;
		}

		// ⚠️ A guest gets an empty answer with 200, not 401: the dialog then simply shows nothing to change.
		return new \WP_REST_Response( array( 'sections' => (object) $sections, 'canWrite' => $can, 'rights' => (object) $rights ), 200 );
	}



	/**
	 * @param	\WP_REST_Request	$request
	 * @return	\WP_REST_Response
	 */

	public static function get_one( \WP_REST_Request $request ): \WP_REST_Response
	{
		$entry = self::read( (string) $request['id'] );

		if ( $entry === null )
			return new \WP_REST_Response( array( 'error' => 'No such section.' ), 404 );

		return new \WP_REST_Response( $entry, 200 );
	}



	/**
	 * @param	\WP_REST_Request	$request
	 * @return	\WP_REST_Response
	 */

	public static function put_one( \WP_REST_Request $request ): \WP_REST_Response
	{
		$id   = (string) $request['id'];
		$body = $request->get_json_params();

		if ( ! is_array( $body ) || ! array_key_exists( 'data', $body ) )
			return new \WP_REST_Response( array( 'error' => 'The body must be a JSON object with "data".' ), 400 );

		if ( strlen( (string) wp_json_encode( $body['data'] ) ) > self::MAX_BYTES )
			return new \WP_REST_Response( array( 'error' => 'The section is too large.' ), 413 );

		$old = self::read( $id );

		if ( $old !== null && (int) ( $body['rev'] ?? -1 ) !== $old['rev'] )
			return new \WP_REST_Response( array( 'error' => 'Somebody else has saved this section in the meantime.', 'rev' => $old['rev'] ), 409 );

		$rev = self::write( $id, max( 1, (int) ( $body['version'] ?? 1 ) ), $body['data'] );

		return new \WP_REST_Response( array( 'rev' => $rev ), 200 );
	}



	/**
	 * The backup file: every section the user may read, in the format of `buildBackup()` in `Settings.mjs`.
	 *
	 * @return	\WP_REST_Response
	 */

	public static function export(): \WP_REST_Response
	{
		$sections = array();

		foreach ( self::all_ids() as $id )
			if ( self::may( $id, 'read' ) && ( $entry = self::read( $id ) ) !== null )
				$sections[ $id ] = array( 'version' => $entry['version'], 'data' => $entry['data'] );

		return new \WP_REST_Response( array(
			'format'        => 'einvoice-settings',
			'formatVersion' => 1,
			'created'       => gmdate( 'c' ),
			'source'        => array( 'kind' => 'plugin', 'product' => 'einvoice-viewer', 'version' => SPEV_VERSION, 'api' => 4 ),
			'sections'      => (object) $sections,
		), 200 );
	}



	/**
	 * Loads a backup file: `{ file: <the JSON of the file>, dryRun: true|false }`.
	 *
	 * ⚠️ ALL OR NOTHING ON RIGHTS. When the user may not maintain one of the sections in the file, nothing is written and
	 * the answer names that section; a backup half loaded would leave the settings in a state nobody saved.
	 *
	 * @param	\WP_REST_Request	$request
	 * @return	\WP_REST_Response
	 */

	public static function import( \WP_REST_Request $request ): \WP_REST_Response
	{
		$body = $request->get_json_params();
		$file = is_array( $body ) ? ( $body['file'] ?? null ) : null;

		if ( is_string( $file ) )
			$file = json_decode( $file, true );

		if ( ! is_array( $file ) || ( $file['format'] ?? '' ) !== 'einvoice-settings' || ! is_array( $file['sections'] ?? null ) )
			return new \WP_REST_Response( array( 'error' => 'This file is not a settings backup.' ), 400 );

		if ( (int) ( $file['formatVersion'] ?? 1 ) > 1 )
			return new \WP_REST_Response( array( 'error' => 'This file was written by a newer version.' ), 400 );

		$ids = array();

		foreach ( $file['sections'] as $id => $s )
			if ( is_string( $id ) && preg_match( '/^' . self::ID_PATTERN . '$/', $id ) && is_array( $s ) )
				$ids[] = $id;

		$denied = array_values( array_filter( $ids, static function ( string $id ): bool { return ! self::may( $id, 'write' ); } ) );

		if ( $denied !== array() )
			return new \WP_REST_Response( array( 'error' => 'You may not change these sections.', 'sections' => $denied ), 403 );

		if ( ! empty( $body['dryRun'] ) )
			return new \WP_REST_Response( array( 'replace' => $ids ), 200 );

		foreach ( $ids as $id )
			self::write( $id, max( 1, (int) ( $file['sections'][ $id ]['version'] ?? 1 ) ), $file['sections'][ $id ]['data'] ?? null );

		return new \WP_REST_Response( array( 'replaced' => $ids ), 200 );
	}

}
