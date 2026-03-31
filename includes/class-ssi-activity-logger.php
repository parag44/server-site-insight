<?php
/**
 * Activity Logger — tracks all meaningful WordPress events.
 * 
 * Records changes to posts, users, plugins, themes, and settings.
 * 
 * @package Server_Site_Insight
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SSI_Activity_Logger {

	/**
	 * Initialize all hooks.
	 */
	public static function init() {
		// --- User Activity ---
		add_action( 'wp_login', array( __CLASS__, 'log_login' ), 10, 2 );
		add_action( 'wp_logout', array( __CLASS__, 'log_logout' ) );
		add_action( 'wp_login_failed', array( __CLASS__, 'log_login_failed' ) );
		add_action( 'user_register', array( __CLASS__, 'log_user_registration' ) );
		add_action( 'delete_user', array( __CLASS__, 'log_user_deletion' ) );
		add_action( 'profile_update', array( __CLASS__, 'log_profile_update' ), 10, 2 );

		// --- Content Activity ---
		add_action( 'wp_insert_post', array( __CLASS__, 'log_post_creation' ), 10, 3 );
		add_action( 'post_updated', array( __CLASS__, 'log_post_update' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'log_post_deletion' ) );

		// --- Taxonomy Activity ---
		add_action( 'created_term', array( __CLASS__, 'log_term_creation' ), 10, 3 );
		add_action( 'edited_term', array( __CLASS__, 'log_term_edit' ), 10, 3 );
		add_action( 'delete_term', array( __CLASS__, 'log_term_deletion' ), 10, 4 );

		// --- Setup & Config Activity ---
		add_action( 'activated_plugin', array( __CLASS__, 'log_plugin_activation' ) );
		add_action( 'deactivated_plugin', array( __CLASS__, 'log_plugin_deactivation' ) );
		add_action( 'switch_theme', array( __CLASS__, 'log_theme_switch' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'log_system_update' ), 10, 2 );
		add_action( 'update_option', array( __CLASS__, 'log_option_update' ), 10, 3 );

		// --- Menu & Widgets ---
		add_action( 'wp_update_nav_menu', array( __CLASS__, 'log_menu_update' ) );
		add_action( 'update_option_sidebars_widgets', array( __CLASS__, 'log_widgets_update' ), 10, 2 );

		// --- Metadata ---
		add_action( 'updated_post_meta', array( __CLASS__, 'log_meta_update' ), 10, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'log_meta_addition' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'log_meta_deletion' ), 10, 4 );

		// --- Multisite ---
		add_action( 'wpmu_new_blog', array( __CLASS__, 'log_new_site' ) );
		add_action( 'delete_blog', array( __CLASS__, 'log_delete_site' ) );
		add_action( 'add_user_to_blog', array( __CLASS__, 'log_user_to_blog' ), 10, 3 );
		add_action( 'remove_user_from_blog', array( __CLASS__, 'log_user_from_blog' ), 10, 2 );

		// --- File System (Admin Edits) ---
		add_action( 'wp_edit_theme_record_update', array( __CLASS__, 'log_theme_file_edit' ) );
		add_action( 'wp_edit_plugin_record_update', array( __CLASS__, 'log_plugin_file_edit' ) );

		// --- Database ---
		add_filter( 'dbdelta_queries', array( __CLASS__, 'log_database_changes' ) );

		// --- WooCommerce ---
		add_action( 'woocommerce_new_order', array( __CLASS__, 'log_wc_new_order' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'log_wc_order_status' ), 10, 3 );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'log_wc_new_product' ) );

		// --- SEO (Yoast / RankMath) ---
		add_action( 'update_option_wpseo_titles', array( __CLASS__, 'log_seo_settings_update' ) );
		add_action( 'update_option_rank_math_settings_general', array( __CLASS__, 'log_seo_settings_update' ) );
	}

	// --- Authentication ---

	public static function log_login( $user_login, $user ) {
		SSI_History_Storage::add_event( 'user', 'good', sprintf( __( 'User logged in: %s', 'server-site-insight' ), $user_login ) );
	}

	public static function log_logout() {
		$user = wp_get_current_user();
		if ( $user->exists() ) {
			SSI_History_Storage::add_event( 'user', 'info', sprintf( __( 'User logged out: %s', 'server-site-insight' ), $user->user_login ) );
		}
	}

	public static function log_login_failed( $username ) {
		SSI_History_Storage::add_event( 'user', 'warning', sprintf( __( 'Failed login attempt for: %s', 'server-site-insight' ), $username ) );
	}

	// --- User Management ---

	public static function log_user_registration( $user_id ) {
		$userdata = get_userdata( $user_id );
		SSI_History_Storage::add_event( 'user', 'good', sprintf( __( 'New user registered: %s', 'server-site-insight' ), $userdata->user_login ) );
	}

	public static function log_user_deletion( $user_id ) {
		$userdata = get_userdata( $user_id );
		if ( $userdata ) {
			SSI_History_Storage::add_event( 'user', 'warning', sprintf( __( 'User deleted: %s', 'server-site-insight' ), $userdata->user_login ) );
		}
	}

	public static function log_profile_update( $user_id, $old_user_data ) {
		$user = get_userdata( $user_id );
		$diffs = array();

		if ( $user->user_email !== $old_user_data->user_email ) {
			$diffs['email'] = array( 'from' => $old_user_data->user_email, 'to' => $user->user_email );
		}
		if ( $user->display_name !== $old_user_data->display_name ) {
			$diffs['display_name'] = array( 'from' => $old_user_data->display_name, 'to' => $user->display_name );
		}
		
		$old_role = ! empty( $old_user_data->roles ) ? reset( $old_user_data->roles ) : 'none';
		$new_role = ! empty( $user->roles ) ? reset( $user->roles ) : 'none';
		if ( $old_role !== $new_role ) {
			$diffs['role'] = array( 'from' => $old_role, 'to' => $new_role );
		}

		$message = sprintf( __( 'Profile updated: %s', 'server-site-insight' ), $user->user_login );
		SSI_History_Storage::add_event( 'user', 'change', $message, array( 
			'user_id' => $user_id,
			'diffs'   => $diffs 
		) );
	}

	// --- Content ---

	public static function log_post_creation( $post_id, $post, $update ) {
		if ( $update ) return; // Handled by log_post_update
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( in_array( $post->post_type, array( 'revision', 'nav_menu_item', 'attachment' ) ) ) return;
		if ( 'auto-draft' === $post->post_status ) return;

		SSI_History_Storage::add_event( 'content', 'good', sprintf( __( 'Created %1$s: "%2$s"', 'server-site-insight' ), ucfirst( $post->post_type ), $post->post_title ), array(
			'post_id' => $post_id,
			'status'  => $post->post_status
		) );
	}

	public static function log_post_update( $post_id, $post_after, $post_before ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( in_array( $post_after->post_type, array( 'revision', 'nav_menu_item', 'attachment' ) ) ) return;
		
		$diffs = array();
		
		if ( $post_after->post_title !== $post_before->post_title ) {
			$diffs['title'] = array( 'from' => $post_before->post_title, 'to' => $post_after->post_title );
		}
		if ( $post_after->post_status !== $post_before->post_status ) {
			$diffs['status'] = array( 'from' => $post_before->post_status, 'to' => $post_after->post_status );
		}
		if ( $post_after->post_name !== $post_before->post_name ) {
			$diffs['slug'] = array( 'from' => $post_before->post_name, 'to' => $post_after->post_name );
		}

		$message = sprintf( __( 'Updated %1$s: "%2$s"', 'server-site-insight' ), ucfirst( $post_after->post_type ), $post_after->post_title );
		SSI_History_Storage::add_event( 'content', 'change', $message, array( 
			'post_id' => $post_id,
			'diffs'   => $diffs
		) );
	}

	public static function log_post_deletion( $post_id ) {
		if ( 'revision' === get_post_type( $post_id ) ) return;
		$post = get_post( $post_id );
		if ( $post ) {
			SSI_History_Storage::add_event( 'content', 'warning', sprintf( __( 'Deleted %1$s: "%2$s"', 'server-site-insight' ), ucfirst( $post->post_type ), $post->post_title ) );
		}
	}

	// --- Taxonomy ---

	public static function log_term_creation( $term_id, $tt_id, $taxonomy ) {
		$term = get_term( $term_id, $taxonomy );
		SSI_History_Storage::add_event( 'content', 'good', sprintf( __( 'Created %1$s: "%2$s"', 'server-site-insight' ), $taxonomy, $term->name ) );
	}

	public static function log_term_edit( $term_id, $tt_id, $taxonomy ) {
		$term = get_term( $term_id, $taxonomy );
		SSI_History_Storage::add_event( 'content', 'change', sprintf( __( 'Edited %1$s: "%2$s"', 'server-site-insight' ), $taxonomy, $term->name ) );
	}

	public static function log_term_deletion( $term, $tt_id, $taxonomy, $deleted_term ) {
		SSI_History_Storage::add_event( 'content', 'warning', sprintf( __( 'Deleted %1$s term: "%2$s"', 'server-site-insight' ), $taxonomy, $deleted_term->name ) );
	}

	// --- Metadata ---

	public static function log_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		$blacklist = array( '_edit_lock', '_edit_last', '_wp_page_template', '_wp_old_slug', '_pingme', '_encloseme' );
		if ( in_array( $meta_key, $blacklist, true ) || strpos( $meta_key, '_wp_attached_file' ) === 0 ) {
			return;
		}

		$post_title = get_the_title( $post_id );
		SSI_History_Storage::add_event( 'content', 'change', sprintf( __( 'Updated custom field "%1$s" on %2$s: "%3$s"', 'server-site-insight' ), $meta_key, get_post_type( $post_id ), $post_title ) );
	}

	public static function log_meta_addition( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( '_' === $meta_key[0] ) return; // Skip internal meta

		$post_title = get_the_title( $post_id );
		SSI_History_Storage::add_event( 'content', 'good', sprintf( __( 'Added custom field "%1$s" to %2$s: "%3$s"', 'server-site-insight' ), $meta_key, get_post_type( $post_id ), $post_title ) );
	}

	public static function log_meta_deletion( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( '_' === $meta_key[0] ) return;

		$post_title = get_the_title( $post_id );
		SSI_History_Storage::add_event( 'content', 'warning', sprintf( __( 'Deleted custom field "%1$s" from %2$s: "%3$s"', 'server-site-insight' ), $meta_key, get_post_type( $post_id ), $post_title ) );
	}

	public static function log_plugin_activation( $plugin ) {
		$name = self::get_plugin_name( $plugin );
		SSI_History_Storage::add_event( 'plugin', 'good', sprintf( __( 'Activated plugin: %s', 'server-site-insight' ), $name ) );
		SSI_System_Info::purge_cache();
	}

	public static function log_plugin_deactivation( $plugin ) {
		$name = self::get_plugin_name( $plugin );
		SSI_History_Storage::add_event( 'plugin', 'warning', sprintf( __( 'Deactivated plugin: %s', 'server-site-insight' ), $name ) );
		SSI_System_Info::purge_cache();
	}

	public static function log_theme_switch( $new_name ) {
		SSI_History_Storage::add_event( 'theme', 'change', sprintf( __( 'Switched theme to: %s', 'server-site-insight' ), $new_name ) );
		SSI_System_Info::purge_cache();
	}

	public static function log_system_update( $upgrader, $options ) {
		if ( 'update' === $options['action'] ) {
			$type = isset( $options['type'] ) ? $options['type'] : 'unknown';
			$items = array();

			if ( 'plugin' === $type && isset( $options['plugins'] ) ) {
				foreach ( (array) $options['plugins'] as $p ) { $items[] = self::get_plugin_name( $p ); }
			} elseif ( 'theme' === $type && isset( $options['themes'] ) ) {
				foreach ( (array) $options['themes'] as $t ) { $items[] = $t; }
			} elseif ( 'core' === $type ) {
				$items[] = 'WordPress Core';
			}

			$label = ! empty( $items ) ? implode( ', ', $items ) : $type;
			SSI_History_Storage::add_event( 'system', 'good', sprintf( __( 'Installed Update: %s', 'server-site-insight' ), $label ) );
			SSI_System_Info::purge_cache();
		}
	}

	// --- Settings ---

	public static function log_option_update( $option, $old_value, $value ) {
		// Blacklist noisy options
		$blacklist = array( 'cron', 'rewrite_rules', 'ssi_activity_timeline', 'ssi_history', 'ssi_last_state', 'ssi_last_snapshot' );
		
		// Suppress technical theme-switch settings (Handled by switch_theme hook)
		$theme_options = array( 'template', 'stylesheet', 'current_theme', 'theme_switched' );
		
		if ( in_array( $option, $blacklist, true ) || in_array( $option, $theme_options, true ) || strpos( $option, '_transient_' ) === 0 ) {
			return;
		}

		$friendly_name = self::get_friendly_option_name( $option );

		// Calculate "From -> To" message
		$from = is_scalar( $old_value ) ? (string) $old_value : '(complex)';
		$to   = is_scalar( $value ) ? (string) $value : '(complex)';
		
		if ( strlen( $from ) > 60 ) $from = substr( $from, 0, 57 ) . '...';
		if ( strlen( $to ) > 60 ) $to = substr( $to, 0, 57 ) . '...';

		SSI_History_Storage::add_event( 'system', 'change', sprintf( __( 'Updated %s', 'server-site-insight' ), $friendly_name ), array(
			'option' => $option,
			'diffs'  => array(
				$friendly_name => array( 'from' => $from, 'to' => $to )
			)
		) );

		// Invalidate system info cache for relevant options
		$diagnostic_options = array( 'blogname', 'blogdescription', 'admin_email', 'users_can_register', 'permalink_structure', 'page_on_front', 'page_for_posts' );
		if ( in_array( $option, $diagnostic_options, true ) ) {
			SSI_System_Info::purge_cache();
		}
	}

	public static function log_menu_update( $menu_id ) {
		$menu = wp_get_nav_menu_object( $menu_id );
		SSI_History_Storage::add_event( 'system', 'change', sprintf( __( 'Navigation menu updated: %s', 'server-site-insight' ), $menu->name ) );
	}

	public static function log_widgets_update( $old_value, $new_value ) {
		SSI_History_Storage::add_event( 'system', 'change', __( 'Site widgets layout updated', 'server-site-insight' ) );
	}

	// --- Multisite ---

	public static function log_new_site( $blog_id ) {
		SSI_History_Storage::add_event( 'system', 'good', sprintf( __( 'Network: New site created (ID: %d)', 'server-site-insight' ), $blog_id ) );
	}

	public static function log_delete_site( $blog_id ) {
		SSI_History_Storage::add_event( 'system', 'warning', sprintf( __( 'Network: Site deleted (ID: %d)', 'server-site-insight' ), $blog_id ) );
	}

	public static function log_user_to_blog( $user_id, $role, $blog_id ) {
		$user = get_userdata( $user_id );
		SSI_History_Storage::add_event( 'user', 'info', sprintf( __( 'User %1$s added to site %2$d with role %3$s', 'server-site-insight' ), $user->user_login, $blog_id, $role ) );
	}

	public static function log_user_from_blog( $user_id, $blog_id ) {
		$user = get_userdata( $user_id );
		SSI_History_Storage::add_event( 'user', 'warning', sprintf( __( 'User %1$s removed from site %2$d', 'server-site-insight' ), $user->user_login, $blog_id ) );
	}

	// --- Database & Files ---

	public static function log_database_changes( $queries ) {
		if ( ! empty( $queries ) ) {
			SSI_History_Storage::add_event( 'system', 'warning', __( 'Database structure modified (dbDelta)', 'server-site-insight' ), array( 'queries' => $queries ) );
		}
		return $queries;
	}

	public static function log_theme_file_edit( $file ) {
		SSI_History_Storage::add_event( 'system', 'warning', sprintf( __( 'Theme file modified via editor: %s', 'server-site-insight' ), $file ) );
	}

	public static function log_plugin_file_edit( $file ) {
		SSI_History_Storage::add_event( 'system', 'warning', sprintf( __( 'Plugin file modified via editor: %s', 'server-site-insight' ), $file ) );
	}

	// --- WooCommerce ---

	public static function log_wc_new_order( $order_id ) {
		SSI_History_Storage::add_event( 'plugin', 'good', sprintf( __( 'WooCommerce: New order received (#%d)', 'server-site-insight' ), $order_id ) );
	}

	public static function log_wc_order_status( $order_id, $from, $to ) {
		SSI_History_Storage::add_event( 'plugin', 'change', sprintf( __( 'WooCommerce: Order #%1$d status changed from %2$s to %3$s', 'server-site-insight' ), $order_id, $from, $to ) );
	}

	public static function log_wc_new_product( $product_id ) {
		SSI_History_Storage::add_event( 'content', 'good', sprintf( __( 'WooCommerce: New product created (ID: %d)', 'server-site-insight' ), $product_id ) );
	}

	// --- SEO ---

	public static function log_seo_settings_update() {
		SSI_History_Storage::add_event( 'system', 'change', __( 'SEO global settings updated', 'server-site-insight' ) );
	}

	// --- Helpers ---

	/**
	 * Map technical option keys to friendly human names.
	 */
	private static function get_friendly_option_name( $option ) {
		$map = array(
			'blogname'            => __( 'Site Title', 'server-site-insight' ),
			'blogdescription'     => __( 'Site Tagline', 'server-site-insight' ),
			'admin_email'         => __( 'Admin Email Address', 'server-site-insight' ),
			'users_can_register'  => __( 'Membership Registration Setting', 'server-site-insight' ),
			'default_role'        => __( 'New User Default Role', 'server-site-insight' ),
			'timezone_string'     => __( 'Site Timezone', 'server-site-insight' ),
			'date_format'         => __( 'Date Format', 'server-site-insight' ),
			'time_format'         => __( 'Time Format', 'server-site-insight' ),
			'start_of_week'       => __( 'Week Start Day', 'server-site-insight' ),
			'WPLANG'              => __( 'Site Language', 'server-site-insight' ),
			'page_on_front'       => __( 'Homepage Setting', 'server-site-insight' ),
			'page_for_posts'      => __( 'Posts Page Setting', 'server-site-insight' ),
			'posts_per_page'      => __( 'Blog Pages Show at Most', 'server-site-insight' ),
			'permalink_structure' => __( 'Permalink Structure', 'server-site-insight' ),
			'category_base'       => __( 'Category Base', 'server-site-insight' ),
			'tag_base'            => __( 'Tag Base', 'server-site-insight' ),
			'thumbnail_size_w'    => __( 'Thumbnail Width', 'server-site-insight' ),
			'medium_size_w'       => __( 'Medium Image Width', 'server-site-insight' ),
			'large_size_w'        => __( 'Large Image Width', 'server-site-insight' ),
			'close_comments_for_old_posts' => __( 'Auto-close Comments Setting', 'server-site-insight' ),
		);

		if ( isset( $map[ $option ] ) ) {
			return $map[ $option ];
		}

		// Fallback: sanitized Title Case (e.g., mailserver_url -> Mailserver Url)
		return ucwords( str_replace( array( '_', '-' ), ' ', $option ) );
	}

	/**
	 * Get the Nice Name of a plugin from its file path.
	 */
	private static function get_plugin_name( $plugin_path ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$full_path = WP_PLUGIN_DIR . '/' . $plugin_path;
		if ( file_exists( $full_path ) ) {
			$data = get_plugin_data( $full_path );
			if ( ! empty( $data['Name'] ) ) {
				return $data['Name'];
			}
		}

		return $plugin_path;
	}
}
