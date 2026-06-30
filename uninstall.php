<?php
/**
 * アンインストール時のクリーンアップ（D22）。
 *
 * プラグイン削除時にオプション・トランジェント・スケジュールを除去する。
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// オプション削除。
$kscp_options = array(
	'kscp_settings',
	'kscp_status_cache',
	'kscp_event_log',
	'kscp_notified_versions',
	'kscp_known_repos',
);
foreach ( $kscp_options as $kscp_opt ) {
	delete_option( $kscp_opt );
}

// スケジュール解除。
$kscp_ts = wp_next_scheduled( 'kscp_scheduled_check' );
while ( $kscp_ts ) {
	wp_unschedule_event( $kscp_ts, 'kscp_scheduled_check' );
	$kscp_ts = wp_next_scheduled( 'kscp_scheduled_check' );
}
wp_clear_scheduled_hook( 'kscp_scheduled_check' );

// 自前トランジェントの削除。
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_kscp_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_kscp_' ) . '%'
	)
);
