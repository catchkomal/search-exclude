<?php
/**
 * Plugin Name: Komal889 Content Exclusion for Algolia
 * Plugin URI:  https://github.com/catchkomal/search-exclude
 * Description: Exclude pages, posts, and CPTs from WordPress search AND remove them from your Algolia index entirely.
 * Version:     2.0.1
 * Author:      Komal Bhatt
 * License:     GPL-2.0+
 * Text Domain: komal889-content-exclusion-for-algolia-main
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SE_VERSION',     '2.0.1' );
define( 'SE_PLUGIN_FILE', __FILE__ );
define( 'SE_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SE_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

/* ═══════════════════════════════════════════════════════════════
 * 1. ACTIVATION
 * ═══════════════════════════════════════════════════════════════ */

register_activation_hook( __FILE__, 'se_activate' );
function se_activate() {
    add_option( 'se_excluded_ids',      [] );
    add_option( 'se_excluded_types',    [] );
    add_option( 'se_algolia_app_id',    '' );
    add_option( 'se_algolia_admin_key', '' );
    add_option( 'se_algolia_index',     '' );
}

/* ═══════════════════════════════════════════════════════════════
 * 2. ALGOLIA HELPER
 * ═══════════════════════════════════════════════════════════════ */

/**
 * Returns Algolia credentials — options first, then constants as fallback.
 */
function se_algolia_config() {
    return [
        'app_id'    => get_option( 'se_algolia_app_id',    defined('ALGOLIA_APP_ID')    ? ALGOLIA_APP_ID    : '' ),
        'admin_key' => get_option( 'se_algolia_admin_key', defined('ALGOLIA_ADMIN_KEY') ? ALGOLIA_ADMIN_KEY : '' ),
        'index'     => get_option( 'se_algolia_index',     defined('ALGOLIA_INDEX_NAME')? ALGOLIA_INDEX_NAME: '' ),
    ];
}

/**
 * Delete one or more objectIDs from Algolia via batch REST API.
 *
 * @param  int|int[] $post_ids
 * @return array { success: bool, message: string }
 */
function se_algolia_delete_objects( $post_ids ) {
    $cfg = se_algolia_config();
    if ( empty( $cfg['app_id'] ) || empty( $cfg['admin_key'] ) || empty( $cfg['index'] ) ) {
        return [ 'success' => false, 'message' => 'Algolia credentials not configured.' ];
    }

    $post_ids = array_values( array_filter( array_map( 'intval', (array) $post_ids ) ) );
    if ( empty( $post_ids ) ) {
        return [ 'success' => true, 'message' => 'Nothing to delete.' ];
    }

    // Build delete requests for all chunks (objectID format: {post_id}-0, {post_id}-1, etc.)
    // We delete up to 10 chunks per post to handle long content split by Algolia
    $requests = [];
    foreach ( $post_ids as $id ) {
        for ( $chunk = 0; $chunk <= 10; $chunk++ ) {
            $requests[] = [
                'action' => 'deleteObject',
                'body'   => [ 'objectID' => $id . '-' . $chunk ],
            ];
        }
    }

    $url = sprintf(
        'https://%s.algolia.net/1/indexes/%s/batch',
        $cfg['app_id'],
        rawurlencode( $cfg['index'] )
    );

    $response = wp_remote_post( $url, [
        'headers' => [
            'X-Algolia-Application-Id' => $cfg['app_id'],
            'X-Algolia-API-Key'        => $cfg['admin_key'],
            'Content-Type'             => 'application/json',
        ],
        'body'    => wp_json_encode( [ 'requests' => $requests ] ),
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) {
        return [ 'success' => false, 'message' => $response->get_error_message() ];
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code !== 200 ) {
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        return [ 'success' => false, 'message' => $body['message'] ?? "Algolia returned HTTP $code." ];
    }

    return [ 'success' => true, 'message' => count( $post_ids ) . ' object(s) removed from Algolia index.' ];
}

/* ═══════════════════════════════════════════════════════════════
 * 3. BLOCK RE-INDEXING ON SAVE
 *    If an excluded post is updated, delete it from Algolia again
 *    so 3rd-party indexers can't sneak it back in.
 * ═══════════════════════════════════════════════════════════════ */

add_action( 'save_post', 'se_block_reindex_on_save', 99 );
function se_block_reindex_on_save( $post_id ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;

    $excluded_ids   = array_map( 'intval', (array) get_option( 'se_excluded_ids',   [] ) );
    $excluded_types = (array) get_option( 'se_excluded_types', [] );
    $post_type      = get_post_type( $post_id );

    if ( in_array( $post_id, $excluded_ids, true ) || in_array( $post_type, $excluded_types, true ) ) {
        se_algolia_delete_objects( [ $post_id ] );
    }
}

/* ═══════════════════════════════════════════════════════════════
 * 4. NATIVE WP SEARCH FILTER
 * ═══════════════════════════════════════════════════════════════ */

add_action( 'pre_get_posts', 'se_exclude_from_search' );
function se_exclude_from_search( WP_Query $query ) {
    if ( ! $query->is_search() || ! $query->is_main_query() || is_admin() ) return;

    $excluded_ids = array_filter( array_map( 'intval', (array) get_option( 'se_excluded_ids', [] ) ) );
    if ( ! empty( $excluded_ids ) ) {
        $query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), $excluded_ids ) );
    }

    $excluded_types = (array) get_option( 'se_excluded_types', [] );
    if ( ! empty( $excluded_types ) ) {
        $all = get_post_types( [ 'exclude_from_search' => false ] );
        $remaining = array_diff( $all, $excluded_types );
        if ( ! empty( $remaining ) ) {
            $query->set( 'post_type', array_values( $remaining ) );
        } else {
            $query->set( 'post__in', [0] );
        }
    }
}

/* ═══════════════════════════════════════════════════════════════
 * 5. ADMIN MENU
 * ═══════════════════════════════════════════════════════════════ */

add_action( 'admin_menu', 'se_register_menu' );
function se_register_menu() {
    add_options_page(
        __( 'Search Exclude', 'komal889-content-exclusion-for-algolia-main' ),
        __( 'Search Exclude', 'komal889-content-exclusion-for-algolia-main' ),
        'manage_options',
        'search-exclude',
        'se_render_admin_page'
    );
}

/* ═══════════════════════════════════════════════════════════════
 * 6. ASSETS
 * ═══════════════════════════════════════════════════════════════ */

add_action( 'admin_enqueue_scripts', 'se_enqueue_assets' );
function se_enqueue_assets( $hook ) {
    if ( $hook !== 'settings_page_search-exclude' ) return;

    wp_enqueue_style(  'se-admin', SE_PLUGIN_URL . 'admin/admin.css', [],         SE_VERSION );
    wp_enqueue_script( 'se-admin', SE_PLUGIN_URL . 'admin/admin.js',  ['jquery'], SE_VERSION, true );

    wp_localize_script( 'se-admin', 'SE', [
        'nonce'        => wp_create_nonce( 'se_ajax' ),
        'ajaxurl'      => admin_url( 'admin-ajax.php' ),
        'excluded_ids' => array_map( 'intval', (array) get_option( 'se_excluded_ids', [] ) ),
    ] );
}

/* ═══════════════════════════════════════════════════════════════
 * 7. AJAX – LIVE POST SEARCH
 * ═══════════════════════════════════════════════════════════════ */

add_action( 'wp_ajax_se_search_posts', 'se_ajax_search_posts' );
function se_ajax_search_posts() {
    check_ajax_referer( 'se_ajax', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

    $term = sanitize_text_field( wp_unslash($_GET['term'] ?? '' ));
    $type = sanitize_key( $_GET['post_type'] ?? 'any' );

    $args = [ 'post_status' => 'publish', 'posts_per_page' => 20, 'orderby' => 'title', 'order' => 'ASC' ];
    if ( $term ) $args['s'] = $term;
    $args['post_type'] = ( $type && $type !== 'any' )
        ? $type
        : array_values( get_post_types( [ 'public' => true ], 'names' ) );

    $results = [];
    foreach ( get_posts( $args ) as $p ) {
        $results[] = [
            'id'    => $p->ID,
            'title' => $p->post_title ?: '(no title)',
            'type'  => get_post_type_object( $p->post_type )->labels->singular_name ?? $p->post_type,
        ];
    }
    wp_send_json_success( $results );
}

/* ═══════════════════════════════════════════════════════════════
 * 8. AJAX – ALGOLIA SYNC (bulk remove all excluded from index)
 * ═══════════════════════════════════════════════════════════════ */

add_action( 'wp_ajax_se_algolia_sync', 'se_ajax_algolia_sync' );
function se_ajax_algolia_sync() {
    check_ajax_referer( 'se_ajax', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

    $excluded_ids   = array_values( array_filter( array_map( 'intval', (array) get_option( 'se_excluded_ids', [] ) ) ) );
    $excluded_types = (array) get_option( 'se_excluded_types', [] );

    if ( ! empty( $excluded_types ) ) {
        $type_ids = get_posts( [
            'post_type' => $excluded_types, 'post_status' => 'any',
            'posts_per_page' => -1, 'fields' => 'ids',
        ] );
        $excluded_ids = array_values( array_unique( array_merge( $excluded_ids, $type_ids ) ) );
    }

    // Delete each post individually to ensure all are processed
    $deleted = 0;
    $errors  = [];
    foreach ( $excluded_ids as $pid ) {
        $r = se_algolia_delete_objects( [ $pid ] );
        if ( $r['success'] ) {
            $deleted++;
        } else {
            $errors[] = "ID $pid: " . $r['message'];
        }
    }

    $result = empty( $errors )
        ? [ 'success' => true,  'message' => "$deleted object(s) removed from Algolia index." ]
        : [ 'success' => false, 'message' => implode( ', ', $errors ) ];
    if ( $result['success'] ) {
        wp_send_json_success( $result['message'] );
    } else {
        wp_send_json_error( $result['message'] );
    }
}

/* ═══════════════════════════════════════════════════════════════
 * 9. AJAX – TEST ALGOLIA CONNECTION
 * ═══════════════════════════════════════════════════════════════ */

add_action( 'wp_ajax_se_algolia_test', 'se_ajax_algolia_test' );
function se_ajax_algolia_test() {
    check_ajax_referer( 'se_ajax', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

    $cfg = se_algolia_config();
    if ( empty( $cfg['app_id'] ) || empty( $cfg['admin_key'] ) || empty( $cfg['index'] ) ) {
        wp_send_json_error( 'Fill in all three Algolia fields first.' );
    }

    $url = sprintf( 'https://%s.algolia.net/1/indexes/%s', $cfg['app_id'], rawurlencode( $cfg['index'] ) );
    $response = wp_remote_get( $url, [
        'headers' => [
            'X-Algolia-Application-Id' => $cfg['app_id'],
            'X-Algolia-API-Key'        => $cfg['admin_key'],
        ],
        'timeout' => 10,
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code === 200 ) {
        $nb = $body['nbRecords'] ?? $body['nbHits'] ?? '?';
        wp_send_json_success( "Connected! Index \"{$cfg['index']}\" has {$nb} records." );
    } else {
        wp_send_json_error( $body['message'] ?? "HTTP $code — check your credentials." );
    }
}

/* ═══════════════════════════════════════════════════════════════
 * 10. SAVE SETTINGS
 * ═══════════════════════════════════════════════════════════════ */

add_action( 'admin_post_se_save_settings', 'se_save_settings' );
function se_save_settings() {
    check_admin_referer( 'se_save_settings_action', 'se_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );

    // Algolia credentials
		$app_id = isset($_POST['se_algolia_app_id']) 
			? sanitize_text_field( wp_unslash( $_POST['se_algolia_app_id'] ) ) 
			: '';

		update_option( 'se_algolia_app_id', $app_id );

		$index = isset($_POST['se_algolia_index']) 
			? sanitize_text_field( wp_unslash( $_POST['se_algolia_index'] ) ) 
			: '';

		update_option( 'se_algolia_index', $index );

		$key = isset($_POST['se_algolia_admin_key']) 
			? sanitize_text_field( wp_unslash( $_POST['se_algolia_admin_key'] ) ) 
			: '';

		if ( $key !== '' ) {
			update_option( 'se_algolia_admin_key', $key );
		}
    
    // Post IDs — diff to find newly excluded
    $old_ids = array_filter( array_map( 'intval', (array) get_option( 'se_excluded_ids', [] ) ) );
    $ids_raw = '';
	if ( isset( $_POST['se_excluded_ids'] ) ) {
	    $ids_raw = sanitize_text_field( wp_unslash( $_POST['se_excluded_ids'] ) );
	}
    $new_ids = array_values( array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) ) );
    update_option( 'se_excluded_ids', $new_ids );

    // Post types — diff to find newly excluded
    $old_types  = (array) get_option( 'se_excluded_types', [] );
    $all_types  = array_keys( get_post_types( [ 'public' => true ] ) );
    $new_types  = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['se_excluded_types'] ?? [] ) ), $all_types ) );
    update_option( 'se_excluded_types', $new_types );

    // Push newly excluded content to Algolia immediately
    $new_individual = array_diff( $new_ids, $old_ids );
    $new_type_slugs = array_diff( $new_types, $old_types );
    $ids_to_delete  = array_values( $new_individual );

    if ( ! empty( $new_type_slugs ) ) {
        $type_ids      = get_posts( [ 'post_type' => $new_type_slugs, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ] );
        $ids_to_delete = array_unique( array_merge( $ids_to_delete, $type_ids ) );
    }

    if ( ! empty( $ids_to_delete ) ) {
        se_algolia_delete_objects( $ids_to_delete );
    }

    wp_safe_redirect( add_query_arg( [ 'page' => 'search-exclude', 'updated' => '1' ], admin_url( 'options-general.php' ) ) );
    exit;
}

/* ═══════════════════════════════════════════════════════════════
 * 11. RENDER ADMIN PAGE
 * ═══════════════════════════════════════════════════════════════ */

function se_render_admin_page() {
    $excluded_ids   = array_filter( array_map( 'intval', (array) get_option( 'se_excluded_ids',   [] ) ) );
    $excluded_types = (array) get_option( 'se_excluded_types', [] );
    $public_types   = get_post_types( [ 'public' => true ], 'objects' );
    $algolia_cfg    = se_algolia_config();
    $updated        = isset( $_GET['updated'] );

    $excluded_posts = [];
    if ( ! empty( $excluded_ids ) ) {
        $excluded_posts = get_posts( [
            'post__in' => $excluded_ids, 'post_type' => 'any', 'post_status' => 'any',
            'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC',
        ] );
    }

    include SE_PLUGIN_DIR . 'admin/admin-page.php';
}

/* ═══════════════════════════════════════════════════════════════
 * DEBUG – AJAX: show exactly what would be sent to Algolia
 * Remove this section once confirmed working.
 * ═══════════════════════════════════════════════════════════════ */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action( 'wp_ajax_se_debug', 'se_ajax_debug' );
}
function se_ajax_debug() {
    check_ajax_referer( 'se_ajax', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

    $cfg          = se_algolia_config();
    $excluded_ids = array_filter( array_map( 'intval', (array) get_option( 'se_excluded_ids', [] ) ) );

    // Build what we WOULD send
    $object_ids = array_map( fn( $id ) => $id . '-0', array_values( $excluded_ids ) );

    // Actually call Algolia and return the raw response
    $url = sprintf( 'https://%s.algolia.net/1/indexes/%s/batch', $cfg['app_id'], rawurlencode( $cfg['index'] ) );
    $requests = array_map( fn( $id ) => [ 'action' => 'deleteObject', 'body' => [ 'objectID' => $id ] ], $object_ids );

    $response = wp_remote_post( $url, [
        'headers' => [
            'X-Algolia-Application-Id' => $cfg['app_id'],
            'X-Algolia-API-Key'        => $cfg['admin_key'],
            'Content-Type'             => 'application/json',
        ],
        'body'    => wp_json_encode( [ 'requests' => $requests ] ),
        'timeout' => 15,
    ] );

    wp_send_json_success( [
        'app_id'        => $cfg['app_id'],
        'index'         => $cfg['index'],
        'excluded_ids'  => array_values( $excluded_ids ),
        'object_ids_sent' => $object_ids,
        'algolia_http_code' => wp_remote_retrieve_response_code( $response ),
        'algolia_response'  => json_decode( wp_remote_retrieve_body( $response ), true ),
        'wp_error'      => is_wp_error( $response ) ? $response->get_error_message() : null,
    ] );
}
