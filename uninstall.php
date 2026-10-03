<?php
/**
 * 卸载清理脚本。
 *
 * 仅删除本插件自身创建的选项、定时任务与 LQIP 缓存文件。
 * 不删除任何文章、媒体附件或用户数据。
 *
 * @package MornRain\MornLazyLoader
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 插件设置。
delete_option( 'morn_lazy_loader_settings' );

// 统计数据。
delete_option( 'morn_lazy_loader_stats' );

// 每日清理定时任务。
$morn_ll_timestamp = wp_next_scheduled( 'morn_lazy_loader_daily_cleanup' );

if ( $morn_ll_timestamp ) {
	wp_unschedule_event( $morn_ll_timestamp, 'morn_lazy_loader_daily_cleanup' );
}

wp_clear_scheduled_hook( 'morn_lazy_loader_daily_cleanup' );

// LQIP 缓存目录。
$morn_ll_uploads = wp_get_upload_dir();

if ( empty( $morn_ll_uploads['error'] ) && ! empty( $morn_ll_uploads['basedir'] ) ) {
	$morn_ll_dir = trailingslashit( $morn_ll_uploads['basedir'] ) . 'morn-lazy-loader';

	if ( is_dir( $morn_ll_dir ) ) {
		$morn_ll_files = glob( trailingslashit( $morn_ll_dir ) . '*.txt' );

		if ( is_array( $morn_ll_files ) ) {
			foreach ( $morn_ll_files as $morn_ll_file ) {
				if ( is_file( $morn_ll_file ) ) {
					wp_delete_file( $morn_ll_file );
				}
			}
		}

		$morn_ll_index = trailingslashit( $morn_ll_dir ) . 'index.html';

		if ( file_exists( $morn_ll_index ) ) {
			wp_delete_file( $morn_ll_index );
		}

		$morn_ll_htaccess = trailingslashit( $morn_ll_dir ) . '.htaccess';

		if ( file_exists( $morn_ll_htaccess ) ) {
			wp_delete_file( $morn_ll_htaccess );
		}

		// 仅在目录为空时移除，避免误删。
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_dir -- 需要在删除后重新检查目录。
		$remain = @scandir( $morn_ll_dir );

		if ( is_array( $remain ) && 2 === count( $remain ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			@rmdir( $morn_ll_dir );
		}
	}
}
