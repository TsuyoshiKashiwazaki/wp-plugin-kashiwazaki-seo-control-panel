<?php
/**
 * 設定の保存・取得・サニタイズを担うクラス。
 *
 * 権限・サニタイズ、マニフェスト URL／キャッシュ、プライバシートグル等の
 * 設定値を一元管理する。
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSCP_Settings {

	/** @var array|null キャッシュした設定配列 */
	private $cache = null;

	/**
	 * デフォルト設定。
	 *
	 * @return array
	 */
	public function defaults() {
		return array(
			'cache_ttl'              => 3 * HOUR_IN_SECONDS, // マニフェストキャッシュ秒数
			'cron_interval'          => 'daily',            // hourly|kscp_sixhours|daily|kscp_weekly
			'email_enabled'          => 1,                  // メール通知 ON/OFF
			'email_recipient'        => get_option( 'admin_email' ),
			'email_security_instant' => 1,                  // セキュリティ更新は即時送信
			'manifest_url'           => '',                 // マニフェスト URL（空なら既定定数）
			'news_enabled'           => 1,                  // 作者ニュース取得 ON/OFF（D26）
			'extra_targets'          => array(),            // ユーザー追加の監視対象（slug => meta）
			'excluded_slugs'         => array(),            // 監視除外する slug
		);
	}

	/**
	 * 設定全体を取得。
	 *
	 * @return array
	 */
	public function all() {
		if ( null !== $this->cache ) {
			return $this->cache;
		}
		$stored = get_option( KSCP_OPT_SETTINGS, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$this->cache = wp_parse_args( $stored, $this->defaults() );
		return $this->cache;
	}

	/**
	 * 単一設定値の取得。
	 *
	 * @param string $key     キー。
	 * @param mixed  $default 既定値。
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$all = $this->all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * 初回有効化時にデフォルトをセット（既存値は保持）。
	 */
	public function maybe_set_defaults() {
		$stored = get_option( KSCP_OPT_SETTINGS, false );
		if ( false === $stored ) {
			add_option( KSCP_OPT_SETTINGS, $this->defaults() );
		}
	}

	/**
	 * 設定フォームの入力をサニタイズして保存。
	 *
	 * @param array $input $_POST 由来の生入力。
	 * @return array 保存した設定。
	 */
	public function save( array $input ) {
		$current = $this->all();
		$out     = $current;

		if ( isset( $input['cache_ttl'] ) ) {
			$ttl = absint( $input['cache_ttl'] );
			$out['cache_ttl'] = max( HOUR_IN_SECONDS, min( $ttl, DAY_IN_SECONDS ) );
		}

		if ( isset( $input['cron_interval'] ) ) {
			$out['cron_interval'] = $this->sanitize_interval( $input['cron_interval'] );
		}

		$out['email_enabled']          = ! empty( $input['email_enabled'] ) ? 1 : 0;
		$out['email_security_instant'] = ! empty( $input['email_security_instant'] ) ? 1 : 0;
		$out['news_enabled']           = ! empty( $input['news_enabled'] ) ? 1 : 0;

		if ( isset( $input['email_recipient'] ) ) {
			$email = sanitize_email( $input['email_recipient'] );
			$out['email_recipient'] = ( $email && is_email( $email ) ) ? $email : get_option( 'admin_email' );
		}

		if ( isset( $input['manifest_url'] ) ) {
			// 更新チェーンの起点なので https のみ許可（http は空になり既定 URL にフォールバック）。
			$out['manifest_url'] = esc_url_raw( trim( (string) $input['manifest_url'] ), array( 'https' ) );
		}

		// 監視除外 slug（カンマ/改行区切り）。
		if ( isset( $input['excluded_slugs'] ) ) {
			$slugs = preg_split( '/[\s,]+/', (string) $input['excluded_slugs'], -1, PREG_SPLIT_NO_EMPTY );
			$out['excluded_slugs'] = array_values( array_unique( array_map( array( $this, 'sanitize_slug' ), (array) $slugs ) ) );
		}

		$this->cache = null;
		update_option( KSCP_OPT_SETTINGS, $out );
		$this->cache = $out;
		return $out;
	}

	/**
	 * cron 間隔のサニタイズ。
	 *
	 * @param string $value 入力。
	 * @return string
	 */
	public function sanitize_interval( $value ) {
		$value = sanitize_key( $value );
		// 旧キー / 非自前キーを自前スケジュールへ移行。
		$migrate = array(
			'weekly'   => 'kscp_weekly',
			'sixhours' => 'kscp_sixhours',
		);
		if ( isset( $migrate[ $value ] ) ) {
			$value = $migrate[ $value ];
		}
		$allowed = array( 'hourly', 'kscp_sixhours', 'daily', 'kscp_weekly' );
		return in_array( $value, $allowed, true ) ? $value : 'daily';
	}

	/**
	 * slug サニタイズ（ディレクトリ名/スラッグ用）。
	 *
	 * 大文字コピペ（リポジトリ名・表示名由来）でも除外照合が効くよう小文字に正規化する。
	 *
	 * @param string $slug 入力。
	 * @return string
	 */
	public function sanitize_slug( $slug ) {
		$slug = strtolower( (string) $slug );
		$slug = preg_replace( '/[^a-z0-9._\-\/]/', '', $slug );
		return $slug;
	}
}
