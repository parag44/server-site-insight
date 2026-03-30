<?php
/**
 * SSI_REST_API — registers REST endpoints, all protected by manage_options.
 *
 * NO endpoint returns data without authentication. Public access returns 401.
 *
 * Namespace: server-site-insight/v1
 * Routes:
 *   GET /info[?section=wordpress|server|environment|performance|security|developer]
 *   GET /health
 *   GET /score
 *   GET /history
 *
 * @package Server_Site_Insight
 * @since   2.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSI_Rest_API
 */
class SSI_Rest_API {

	/** REST namespace. */
	const NAMESPACE = 'server-site-insight/v1';

	/**
	 * Register hooks. Called once during plugins_loaded.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all REST routes.
	 */
	public static function register_routes() {
		$valid_sections = array( 'all', 'wordpress', 'server', 'environment', 'performance', 'security', 'developer' );

		// /info — full system data, optionally filtered by section.
		register_rest_route(
			self::NAMESPACE,
			'/info',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_info' ),
					'permission_callback' => array( __CLASS__, 'permission_check' ),
					'args'                => array(
						'section' => array(
							'description'       => __( 'Filter by section.', 'server-site-insight' ),
							'type'              => 'string',
							'default'           => 'all',
							'enum'              => $valid_sections,
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// /health — health check results + overall status.
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_health' ),
					'permission_callback' => array( __CLASS__, 'permission_check' ),
				),
			)
		);

		// /score — numeric health score and grade.
		register_rest_route(
			self::NAMESPACE,
			'/score',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_score' ),
					'permission_callback' => array( __CLASS__, 'permission_check' ),
				),
			)
		);

		// /history — stored snapshots.
		register_rest_route(
			self::NAMESPACE,
			'/history',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_history' ),
					'permission_callback' => array( __CLASS__, 'permission_check' ),
				),
			)
		);
	}

	// -----------------------------------------------------------------------
	// Permission — STRICT: manage_options required for ALL endpoints.
	// -----------------------------------------------------------------------

	/**
	 * Permission callback shared by all routes.
	 *
	 * Returns a WP_Error (401/403) for any unauthenticated or unauthorised
	 * request so sensitive server data is NEVER exposed publicly.
	 *
	 * @return bool|WP_Error
	 */
	public static function permission_check() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'ssi_rest_unauthenticated',
				__( 'Authentication is required.', 'server-site-insight' ),
				array( 'status' => rest_authorization_required_code() ) // 401
			);
		}

		if ( ! current_user_can( SSI_CAPABILITY ) ) {
			return new WP_Error(
				'ssi_rest_forbidden',
				__( 'You do not have permission to access this data.', 'server-site-insight' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	// -----------------------------------------------------------------------
	// Route handlers
	// -----------------------------------------------------------------------

	/**
	 * GET /info
	 *
	 * @param WP_REST_Request $request  Incoming REST request.
	 * @return WP_REST_Response
	 */
	public static function handle_info( $request ) {
		$dev  = SSI_Settings::get( 'developer_mode', false );
		$all  = SSI_System_Info::get_all( $dev );
		$sec  = sanitize_key( $request->get_param( 'section' ) );
		$data = ( 'all' !== $sec && isset( $all[ $sec ] ) ) ? array( $sec => $all[ $sec ] ) : $all;

		return self::make_response( array(
			'generated' => current_time( 'c' ),
			'data'      => $data,
		) );
	}

	/**
	 * GET /health
	 *
	 * @param WP_REST_Request $request  Unused.
	 * @return WP_REST_Response
	 */
	public static function handle_health( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$info    = SSI_System_Info::get_all();
		$checks  = SSI_Health_Check::run( $info );
		$overall = SSI_Health_Check::overall_status( $checks );
		$score   = SSI_Health_Score::calculate( $checks );

		return self::make_response( array(
			'overall_status' => $overall,
			'score'          => $score,
			'grade'          => SSI_Health_Score::grade( $score ),
			'generated'      => current_time( 'c' ),
			'checks'         => $checks,
		) );
	}

	/**
	 * GET /score
	 *
	 * @param WP_REST_Request $request  Unused.
	 * @return WP_REST_Response
	 */
	public static function handle_score( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$info   = SSI_System_Info::get_all();
		$checks = SSI_Health_Check::run( $info );
		$score  = SSI_Health_Score::calculate( $checks );

		return self::make_response( array(
			'score'       => $score,
			'grade'       => SSI_Health_Score::grade( $score ),
			'label'       => SSI_Health_Score::label( $score ),
			'description' => SSI_Health_Score::description( $score ),
		) );
	}

	/**
	 * GET /history
	 *
	 * @param WP_REST_Request $request  Unused.
	 * @return WP_REST_Response
	 */
	public static function handle_history( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return self::make_response( array(
			'history' => SSI_Tracker::get_history(),
			'changes' => SSI_Tracker::get_changes(),
		) );
	}

	// -----------------------------------------------------------------------
	// Shared response helper
	// -----------------------------------------------------------------------

	/**
	 * Build a WP_REST_Response with standard envelope and no-cache headers.
	 *
	 * @param array $data  Payload to merge into the response.
	 * @return WP_REST_Response
	 */
	private static function make_response( array $data ) {
		$response = new WP_REST_Response(
			array_merge(
				array(
					'success' => true,
					'plugin'  => 'Server & Site Insight',
					'version' => SSI_VERSION,
				),
				$data
			),
			200
		);

		// Prevent any proxy or browser from caching sensitive system data.
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}
}

// Boot.
SSI_Rest_API::init();
