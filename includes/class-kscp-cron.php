<?php
/**
 * wp-cron 定期チェック＋スケジュール管理（D19）。
 *
 * 背景で定期的に更新チェックを実行する。最小構成として daily を既定とし、
 * hourly / 6時間 / weekly を選択可能。WP-CLI / サーバ cron からも叩けるよう
 * フックを公開する。
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSCP_Cron {

	/** @var KSCP_Settings */
	private $settings;

	/** @var KSCP_Update_Checker */
	private $checker;

	/**
	 * @param KSCP_Settings       $settings 設定。
	 * @param KSCP_Update_Checker $checker  更新チェッカー。
	 */
	public function __construct( KSCP_Settings $settings, KSCP_Update_Checker $checker ) {
		$this->settings = $settings;
		$this->checker  = $checker;
	}

	/**
	 * フック登録。
	 */
	public function register() {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
		add_action( KSCP_CRON_HOOK, array( $this, 'run' ) );
		// ドリフト是正は管理画面でのみ十分（全フロント init での実行を避ける）。
		add_action( 'admin_init', array( $this, 'maybe_reschedule' ) );
	}

	/**
	 * カスタムスケジュール（6時間）を追加。
	 *
	 * @param array $schedules 既存。
	 * @return array
	 */
	public function add_schedules( $schedules ) {
		// 自前で名前空間付きスケジュールを登録（WP コアに weekly は無いため自己完結）。
		if ( ! isset( $schedules['kscp_sixhours'] ) ) {
			$schedules['kscp_sixhours'] = array(
				'interval' => 6 * HOUR_IN_SECONDS,
				'display'  => __( '6時間ごと', 'kashiwazaki-seo-control-panel' ),
			);
		}
		if ( ! isset( $schedules['kscp_weekly'] ) ) {
			$schedules['kscp_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( '週次', 'kashiwazaki-seo-control-panel' ),
			);
		}
		return $schedules;
	}

	/**
	 * cron 実行本体。
	 */
	public function run() {
		$this->checker->run_check( true );
	}

	/**
	 * 設定された間隔とスケジュール済み間隔が異なれば貼り直す。
	 */
	public function maybe_reschedule() {
		// 生値を sanitize/移行してから比較（旧 weekly/sixhours 値を正規キーに揃える）。
		$desired = $this->settings->sanitize_interval( $this->settings->get( 'cron_interval', 'daily' ) );
		$current = wp_get_schedule( KSCP_CRON_HOOK );
		if ( $current !== $desired ) {
			self::clear_event();
			self::schedule_event( $desired );
		}
	}

	/**
	 * スケジュール作成。
	 *
	 * @param string $interval 間隔キー。
	 */
	public static function schedule_event( $interval ) {
		$allowed = array( 'hourly', 'kscp_sixhours', 'daily', 'kscp_weekly' );
		if ( ! in_array( $interval, $allowed, true ) ) {
			$interval = 'daily';
		}
		if ( ! wp_next_scheduled( KSCP_CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $interval, KSCP_CRON_HOOK );
		}
	}

	/**
	 * スケジュール解除。
	 */
	public static function clear_event() {
		$timestamp = wp_next_scheduled( KSCP_CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, KSCP_CRON_HOOK );
			$timestamp = wp_next_scheduled( KSCP_CRON_HOOK );
		}
		wp_clear_scheduled_hook( KSCP_CRON_HOOK );
	}
}
