<?php
/**
 * Developer Insights Module
 * Offers Query Monitor-lite capabilities such as slow queries, hooks, autoloading, and cache stats.
 *
 * @package Server_Site_Insight
 * @since   2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SSI_Developer_Insights {

	/**
	 * Init AJAX handlers.
	 */
	public static function init() {
		add_action( 'wp_ajax_ssi_lazy_queries', array( __CLASS__, 'ajax_lazy_queries' ) );
		add_action( 'wp_ajax_ssi_lazy_hooks', array( __CLASS__, 'ajax_lazy_hooks' ) );
		add_action( 'wp_ajax_ssi_lazy_scripts', array( __CLASS__, 'ajax_lazy_scripts' ) );
		add_action( 'wp_ajax_ssi_lazy_plugins', array( __CLASS__, 'ajax_lazy_plugins' ) );
		add_action( 'wp_ajax_ssi_lazy_plugin_impact', array( __CLASS__, 'ajax_lazy_plugin_impact' ) );
		add_action( 'wp_ajax_ssi_run_cron_task', array( __CLASS__, 'ajax_run_cron_task' ) );
		add_action( 'wp_ajax_ssi_verify_core_checksums', array( __CLASS__, 'ajax_verify_core_checksums' ) );
		add_action( 'wp_ajax_ssi_verify_core_checksums', array( __CLASS__, 'ajax_verify_core_checksums' ) );

		add_action( 'shutdown', array( __CLASS__, 'log_slow_queries_on_shutdown' ) );
	}

	// ── Fast Real-Time Stats (Loaded immediately) ────────────────────────────────────

	/**
	 * Autoloaded options analysis.
	 *
	 * @return array{total_size_mb: float, is_warning: bool, top_options: array}
	 */
	public static function get_autoload_stats() {
		$cached = get_transient( 'ssi_autoload_stats' );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) AS size_bytes 
			 FROM {$wpdb->options} 
			 WHERE autoload = 'yes' OR autoload = 'on' OR autoload = 1 
			 ORDER BY LENGTH(option_value) DESC 
			 LIMIT 5"
		);

		// Calculate total
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_bytes = (int) $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes' OR autoload = 'on' OR autoload = 1"
		);

		$total_mb = round( $total_bytes / 1024 / 1024, 2 );
		$is_warn  = $total_mb > 1.0;

		$top_ops = array();
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$top_ops[] = array(
					'name'    => sanitize_text_field( $r->option_name ),
					'size_kb' => round( (int) $r->size_bytes / 1024, 2 ),
				);
			}
		}

		$stats = array(
			'total_size_mb' => $total_mb,
			'is_warning'    => $is_warn,
			'top_options'   => $top_ops,
		);

		set_transient( 'ssi_autoload_stats', $stats, HOUR_IN_SECONDS );
		return $stats;
	}

	/**
	 * Detailed Object Cache detection.
	 *
	 * @return array{enabled: bool, type: string, stats: string}
	 */
	public static function get_object_cache_stats() {
		global $wp_object_cache;

		$enabled = wp_using_ext_object_cache();
		$type    = 'None (Default Transient/DB)';
		$stats   = '';

		if ( $enabled && is_object( $wp_object_cache ) ) {
			$class = get_class( $wp_object_cache );
			if ( stripos( $class, 'redis' ) !== false ) {
				$type = 'Redis';
			} elseif ( stripos( $class, 'memcache' ) !== false ) {
				$type = 'Memcached';
			} else {
				$type = $class;
			}
			
			// Some drop-ins expose cache hits/misses publicly.
			if ( isset( $wp_object_cache->cache_hits ) && isset( $wp_object_cache->cache_misses ) ) {
				$hits   = (int) $wp_object_cache->cache_hits;
				$misses = (int) $wp_object_cache->cache_misses;
				$total  = $hits + $misses;
				if ( $total > 0 ) {
					$ratio = round( ( $hits / $total ) * 100, 1 );
					$stats = sprintf( 'Hits: %s | Misses: %s (%s%%)', number_format_i18n( $hits ), number_format_i18n( $misses ), $ratio );
				}
			}
		}

		return array(
			'enabled' => $enabled,
			'type'    => $type,
			'stats'   => $stats,
		);
	}

	/**
	 * Get enriched REST API Routes.
	 *
	 * @return array
	 */
	public static function get_rest_routes_enriched() {
		$server = rest_get_server();
		$routes = $server->get_routes();
		$output = array();

		if ( is_array( $routes ) ) {
			foreach ( $routes as $route_path => $handlers ) {
				// Prevent exploding huge output; only capture non-internal or summarize.
				$methods = array();
				foreach ( $handlers as $h ) {
					if ( isset( $h['methods'] ) ) {
						$m = is_array( $h['methods'] ) ? implode( ',', $h['methods'] ) : $h['methods'];
						if ( ! in_array( $m, $methods, true ) ) {
							$methods[] = $m;
						}
					}
				}
				$output[] = array(
					'route'   => sanitize_text_field( $route_path ),
					'methods' => implode( ' | ', $methods ),
				);
			}
		}

		return $output;
	}

	/**
	 * Get expired transients stats.
	 *
	 * @return array{count: int, size_kb: float}
	 */
	public static function get_expired_transients_stats() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as cnt, SUM(LENGTH(option_value)) as size 
				 FROM {$wpdb->options} 
				 WHERE option_name LIKE %s AND option_value < %d",
				'_transient_timeout_%',
				time()
			)
		);

		return array(
			'count'   => $row ? (int) $row->cnt : 0,
			'size_kb' => $row ? round( (int) $row->size / 1024, 2 ) : 0,
		);
	}

	/**
	 * Get wp-cron scheduled tasks stats.
	 *
	 * @return array{upcoming: array, overdue: array}
	 */
	public static function get_cron_stats() {
		$cron = _get_cron_array();
		$upcoming = array();
		$overdue  = array();
		$now      = time();

		if ( is_array( $cron ) ) {
			foreach ( $cron as $timestamp => $cronhooks ) {
				foreach ( $cronhooks as $hook => $keys ) {
					foreach ( $keys as $k => $v ) {
						$task = array(
							'hook' => $hook,
							'time' => $timestamp,
							'due'  => human_time_diff( $now, $timestamp ),
							'args' => $v['args'],
						);
						if ( $timestamp < $now ) {
							$overdue[] = $task;
						} else {
							$upcoming[] = $task;
						}
					}
				}
			}
		}

		// Sort upcoming early to late
		usort( $upcoming, function($a, $b) { return $a['time'] <=> $b['time']; } );
		// Top 5 upcoming
		$upcoming = array_slice( $upcoming, 0, 5 );

		return array(
			'upcoming' => $upcoming,
			'overdue'  => $overdue,
		);
	}

	/**
	 * Hook on shutdown to persist any slow queries to a transient for deep tracking.
	 */
	public static function log_slow_queries_on_shutdown() {
		if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
			return;
		}

		global $wpdb;
		if ( empty( $wpdb->queries ) ) {
			return;
		}

		$current_log = get_transient( 'ssi_slow_queries_log' );
		if ( ! is_array( $current_log ) ) {
			$current_log = array();
		}

		$found_slow = false;
		foreach ( $wpdb->queries as $q ) {
			if ( isset( $q[1] ) && $q[1] > 0.2 ) { // > 200ms
				$found_slow = true;
				$current_log[] = array(
					'sql'   => $q[0],
					'time'  => round( $q[1] * 1000, 2 ),
					'date'  => current_time( 'mysql' ),
					'stack' => isset( $q[2] ) ? $q[2] : '',
				);
			}
		}

		if ( $found_slow ) {
			// Sort and prune to top 20
			usort( $current_log, function( $a, $b ) { return $b['time'] <=> $a['time']; } );
			$current_log = array_slice( $current_log, 0, 20 );
			set_transient( 'ssi_slow_queries_log', $current_log, 24 * HOUR_IN_SECONDS );
		}
	}

	// ── Lazy Load AJAX Endpoints ─────────────────────────────────────────────────────

	/**
	 * Securely check nonce/caps before continuing.
	 */
	private static function verify_ajax() {
		check_ajax_referer( 'ssi_tools_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'server-site-insight' ) ) );
		}
	}

	/**
	 * AJAX: Get Queries and execution times if SAVEQUERIES is active.
	 */
	public static function ajax_lazy_queries() {
		self::verify_ajax();

		global $wpdb;
		$savequeries = defined( 'SAVEQUERIES' ) && SAVEQUERIES;
		
		$total_time = 0;
		$top_slow   = array();
		
		if ( $savequeries && ! empty( $wpdb->queries ) ) {
			$q_array = $wpdb->queries;
			
			// Compute total time & map queries
			foreach ( $q_array as $k => $q ) {
				if ( isset( $q[1] ) ) {
					$time = (float) $q[1];
					$total_time += $time;
					
					$top_slow[] = array(
						'sql'   => $q[0],
						'time'  => round( $time * 1000, 2 ), // convert to ms
						'stack' => isset( $q[2] ) ? $q[2] : '',
					);
				}
			}
			
			// Sort descending by time
			usort( $top_slow, function ( $a, $b ) {
				return $b['time'] <=> $a['time']; // PHP 7+ Spaceship
			} );
			
			// Top 5 only
			$top_slow = array_slice( $top_slow, 0, 5 );
		}

		// Include Persistent Log
		$persistent_raw = get_transient( 'ssi_slow_queries_log' );
		$persistent_log = is_array( $persistent_raw ) ? $persistent_raw : array();

		wp_send_json_success( array(
			'savequeries'    => $savequeries,
			'count'          => (int) $wpdb->num_queries,
			'total_ms'       => round( $total_time * 1000, 2 ),
			'top_slow'       => $top_slow,
			'persistent_log' => $persistent_log,
		) );
	}

	/**
	 * AJAX: Get aggregate hook counts.
	 */
	public static function ajax_lazy_hooks() {
		self::verify_ajax();

		global $wp_actions, $wp_filter;

		$total_fired = 0;
		$hook_counts = array();

		if ( is_array( $wp_actions ) ) {
			foreach ( $wp_actions as $name => $count ) {
				$total_fired += $count;
				$hook_counts[] = array(
					'name'  => sanitize_text_field( $name ),
					'count' => (int) $count,
				);
			}
		}
		
		usort( $hook_counts, function( $a, $b ) {
			return $b['count'] <=> $a['count'];
		});
		
		// Return only top 50 to prevent massive memory payload
		$top_hooks = array_slice( $hook_counts, 0, 50 );
		
		$registered = 0;
		if ( is_array( $wp_filter ) ) {
			$registered = count( $wp_filter );
		}

		wp_send_json_success( array(
			'registered'  => $registered,
			'total_fired' => $total_fired,
			'top_hooks'   => $top_hooks,
		) );
	}

	/**
	 * AJAX: Get enqueued scripts and styles.
	 */
	public static function ajax_lazy_scripts() {
		self::verify_ajax();

		global $wp_scripts, $wp_styles;

		$scripts_out = array();
		if ( isset( $wp_scripts ) && $wp_scripts instanceof WP_Scripts ) {
			foreach ( $wp_scripts->queue as $handle ) {
				if ( isset( $wp_scripts->registered[ $handle ] ) ) {
					$obj = $wp_scripts->registered[ $handle ];
					$scripts_out[] = array(
						'handle' => $handle,
						'src'    => $obj->src ? esc_url( $obj->src ) : '(inline)',
						'ver'    => $obj->ver,
						'deps'   => implode( ', ', $obj->deps ),
					);
				}
			}
		}

		$styles_out = array();
		if ( isset( $wp_styles ) && $wp_styles instanceof WP_Styles ) {
			foreach ( $wp_styles->queue as $handle ) {
				if ( isset( $wp_styles->registered[ $handle ] ) ) {
					$obj = $wp_styles->registered[ $handle ];
					$styles_out[] = array(
						'handle' => $handle,
						'src'    => $obj->src ? esc_url( $obj->src ) : '(inline)',
						'ver'    => $obj->ver,
						'deps'   => implode( ', ', $obj->deps ),
					);
				}
			}
		}

		wp_send_json_success( array(
			'scripts'       => $scripts_out,
			'scripts_count' => count( $scripts_out ),
			'styles'        => $styles_out,
			'styles_count'  => count( $styles_out ),
		) );
	}

	/**
	 * AJAX: Get Plugin Performance Footprint using Reflection.
	 */
	public static function ajax_lazy_plugins() {
		self::verify_ajax();

		global $wp_filter;
		$plugin_counts = array();
		
		if ( is_array( $wp_filter ) ) {
			foreach ( $wp_filter as $target_hook => $hook_obj ) {
				if ( ! isset( $hook_obj->callbacks ) || ! is_array( $hook_obj->callbacks ) ) {
					continue;
				}
				
				foreach ( $hook_obj->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $cb ) {
						try {
							$func = $cb['function'];
							$ref = null;
							
							if ( is_array( $func ) && count( $func ) === 2 ) {
								if ( is_object( $func[0] ) ) {
									$ref = new ReflectionMethod( $func[0], $func[1] );
								} elseif ( is_string( $func[0] ) ) {
									$ref = new ReflectionMethod( $func[0], $func[1] );
								}
							} elseif ( is_string( $func ) && function_exists( $func ) ) {
								$ref = new ReflectionFunction( $func );
							} elseif ( $func instanceof Closure ) {
								$ref = new ReflectionFunction( $func );
							}
							
							if ( $ref ) {
								$file = $ref->getFileName();
								if ( $file && strpos( $file, WP_PLUGIN_DIR ) === 0 ) {
									$plugin_path = str_replace( WP_PLUGIN_DIR . '/', '', $file );
									$plugin_dir  = explode( '/', $plugin_path )[0];
									if ( ! isset( $plugin_counts[ $plugin_dir ] ) ) {
										$plugin_counts[ $plugin_dir ] = 0;
									}
									$plugin_counts[ $plugin_dir ]++;
								}
							}
						} catch ( Exception $e ) {
							continue;
						}
					}
				}
			}
		}

		$output = array();
		foreach ( $plugin_counts as $slug => $count ) {
			$output[] = array(
				'slug'  => sanitize_text_field( $slug ),
				'count' => (int) $count,
			);
		}
		
		usort( $output, function( $a, $b ) { return $b['count'] <=> $a['count']; } );

		wp_send_json_success( array(
			'plugins' => array_slice( $output, 0, 20 )
		) );
	}

	/**
	 * AJAX: Run Cron Task manually.
	 */
	public static function ajax_run_cron_task() {
		self::verify_ajax();
		
		$hook = isset( $_POST['hook'] ) ? sanitize_text_field( wp_unslash( $_POST['hook'] ) ) : '';
		if ( empty( $hook ) ) {
			wp_send_json_error( array( 'message' => 'Missing hook name.' ) );
		}

		// Fire action immediately
		do_action( $hook );
		
		// Remove from schedule if it was a single event (optional: wp-cron handles true cleanup).
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp && $timestamp < time() ) {
			wp_unschedule_event( $timestamp, $hook );
		}

		wp_send_json_success( array( 'message' => 'Cron event executed: ' . $hook ) );
	}

	/**
	 * AJAX: Clear Expired Transients.
	 */
	public static function ajax_clear_transients() {
		self::verify_ajax();

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
				'_transient_timeout_%',
				time()
			)
		);

		$deleted = 0;
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$name = str_replace( '_transient_timeout_', '', $row->option_name );
				delete_transient( $name );
				delete_site_transient( $name );
				$deleted++;
			}
		}

		wp_send_json_success( array( 'message' => sprintf( 'Deleted %d expired transients.', $deleted ) ) );
	}

	/**
	 * AJAX: Verify Core Checksums.
	 */
	public static function ajax_verify_core_checksums() {
		self::verify_ajax();

		require_once ABSPATH . 'wp-admin/includes/update.php';

		$version   = get_bloginfo( 'version' );
		$locale    = get_locale();
		$checksums = get_core_checksums( $version, $locale );

		if ( ! is_array( $checksums ) ) {
			// Fallback to en_US if locale checksums are unavailable
			$checksums = get_core_checksums( $version, 'en_US' );
		}

		if ( ! is_array( $checksums ) ) {
			wp_send_json_error( array( 'message' => 'Could not retrieve checksums from WordPress.org.' ) );
		}

		$modified = array();
		$scanned  = 0;

		foreach ( $checksums as $file => $checksum ) {
			// Only scan critical directories to prevent timeout
			if ( strpos( $file, 'wp-admin/' ) !== 0 && strpos( $file, 'wp-includes/' ) !== 0 ) {
				continue;
			}
			
			$path = ABSPATH . $file;
			if ( file_exists( $path ) ) {
				$scanned++;
				$local_md5 = md5_file( $path );
				if ( $local_md5 !== $checksum ) {
					$modified[] = array(
						'file'     => $file,
						'checksum' => $checksum,
						'local'    => $local_md5
					);
				}
			}
		}

		wp_send_json_success( array(
			'message'  => sprintf( 'Scanned %d critical core files.', $scanned ),
			'clean'    => empty( $modified ),
			'modified' => $modified,
		) );
	}

	/**
	 * AJAX: Plugin Impact Analyzer (Killer Feature)
	 */
	public static function ajax_lazy_plugin_impact() {
		self::verify_ajax();

		$plugins = array();
		
		// Helper to ensure plugin exists in array
		$init_plugin = function( $slug ) use ( &$plugins ) {
			if ( ! isset( $plugins[ $slug ] ) ) {
				$plugins[ $slug ] = array(
					'slug'     => sanitize_text_field( $slug ),
					'queries'  => 0,
					'query_ms' => 0,
					'assets'   => 0,
					'hooks'    => 0,
				);
			}
		};

		// 1. Analyze Hooks via Reflection
		global $wp_filter;
		if ( is_array( $wp_filter ) ) {
			foreach ( $wp_filter as $hook_obj ) {
				if ( ! isset( $hook_obj->callbacks ) || ! is_array( $hook_obj->callbacks ) ) {
					continue;
				}
				foreach ( $hook_obj->callbacks as $callbacks ) {
					foreach ( $callbacks as $cb ) {
						try {
							$func = $cb['function'];
							$ref = null;
							if ( is_array( $func ) && count( $func ) === 2 ) {
								if ( is_object( $func[0] ) || is_string( $func[0] ) ) {
									$ref = new ReflectionMethod( $func[0], $func[1] );
								}
							} elseif ( is_string( $func ) && function_exists( $func ) ) {
								$ref = new ReflectionFunction( $func );
							} elseif ( $func instanceof Closure ) {
								$ref = new ReflectionFunction( $func );
							}
							
							if ( $ref ) {
								$file = $ref->getFileName();
								if ( $file && strpos( $file, WP_PLUGIN_DIR ) === 0 ) {
									$plugin_dir = explode( '/', str_replace( WP_PLUGIN_DIR . '/', '', $file ) )[0];
									$init_plugin( $plugin_dir );
									$plugins[ $plugin_dir ]['hooks']++;
								}
							}
						} catch ( Exception $e ) {
							continue;
						}
					}
				}
			}
		}

		// 2. Analyze Frontend Assets
		global $wp_scripts, $wp_styles;
		$scan_assets_php = function( $wp_obj ) use ( $init_plugin, &$plugins ) {
			if ( $wp_obj && property_exists( $wp_obj, 'queue' ) && is_array( $wp_obj->queue ) && isset( $wp_obj->registered ) ) {
				foreach ( $wp_obj->queue as $handle ) {
					if ( isset( $wp_obj->registered[ $handle ]->src ) && $wp_obj->registered[ $handle ]->src ) {
						$src = $wp_obj->registered[ $handle ]->src;
						if ( stripos( $src, '/wp-content/plugins/' ) !== false ) {
							preg_match( '#/wp-content/plugins/([^/]+)/#i', $src, $matches );
							if ( ! empty( $matches[1] ) ) {
								$plugin_dir = $matches[1];
								$init_plugin( $plugin_dir );
								$plugins[ $plugin_dir ]['assets']++;
							}
						}
					}
				}
			}
		};
		$scan_assets_php( $wp_scripts );
		$scan_assets_php( $wp_styles );

		// 3. Analyze Queries (Requires SAVEQUERIES)
		global $wpdb;
		$savequeries = defined( 'SAVEQUERIES' ) && SAVEQUERIES;
		
		if ( $savequeries && ! empty( $wpdb->queries ) ) {
			foreach ( $wpdb->queries as $q ) {
				if ( isset( $q[1] ) && isset( $q[2] ) ) { // ms and stack
					$stack = $q[2];
					if ( stripos( $stack, 'wp-content/plugins/' ) !== false ) {
						preg_match( '#wp-content/plugins/([^/]+)/#i', $stack, $matches );
						if ( ! empty( $matches[1] ) ) {
							$plugin_dir = $matches[1];
							$init_plugin( $plugin_dir );
							$plugins[ $plugin_dir ]['queries']++;
							$plugins[ $plugin_dir ]['query_ms'] += round( $q[1] * 1000, 2 );
						}
					}
				}
			}
		}

		// Calculate Impact Score
		$output = array();
		foreach ( $plugins as $p ) {
			// Score weighting: 1 DB ms = 1 pt. 1 Asset = 5 pts. 1 Hook = 0.1 pts.
			$score = $p['query_ms'] + ( $p['assets'] * 5 ) + ( $p['hooks'] * 0.1 );
			$p['score'] = round( $score, 1 );
			
			// Grade
			if ( $p['score'] > 50 ) {
				$p['grade'] = 'high';
			} elseif ( $p['score'] > 10 ) {
				$p['grade'] = 'medium';
			} else {
				$p['grade'] = 'low';
			}
			$output[] = $p;
		}

		usort( $output, function( $a, $b ) { return $b['score'] <=> $a['score']; } );

		wp_send_json_success( array(
			'savequeries' => $savequeries,
			'impact'      => $output,
		) );
	}

	/**
	 * Log a slow query (placeholder logic, usually used on shutdown).
	 */
	// log_slow_queries_on_shutdown is already defined above...
}
