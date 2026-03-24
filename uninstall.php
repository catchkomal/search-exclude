<?php
/**
 * Uninstall file for Algolia Search Exclude plugin
 */

// Exit if accessed directly
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete plugin options
 */
delete_option( 'se_excluded_ids' );
delete_option( 'se_excluded_types' );
delete_option( 'se_algolia_app_id' );
delete_option( 'se_algolia_admin_key' );
delete_option( 'se_algolia_index' );

/**
 * If multisite, delete from all sites
 */
if ( is_multisite() ) {
    $sites = get_sites();

    foreach ( $sites as $site ) {
        switch_to_blog( $site->blog_id );

        delete_option( 'se_excluded_ids' );
        delete_option( 'se_excluded_types' );
        delete_option( 'se_algolia_app_id' );
        delete_option( 'se_algolia_admin_key' );
        delete_option( 'se_algolia_index' );

        restore_current_blog();
    }
}