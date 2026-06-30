<?php
/**
 * マニフェスト取得・解析（配布対応の中核）。
 *
 * 作者が公開する単一の manifest.json（全 wp- プラグイン/テーマの最新版・更新内容・
 * 日付・セキュリティ印）を 1 回だけ取得してキャッシュする。GitHub REST API を
 * 個別に叩かないため、トークン不要・レート制限の影響を受けない。
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSCP_Manifest {

	const TRANSIENT = 'kscp_manifest_data';

	/** @var KSCP_Settings */
	private $settings;

	/** @var array|null 最後の取得状態（診断用）。 */
	private $last_status = null;

	/**
	 * @param KSCP_Settings $settings 設定。
	 */
	public function __construct( KSCP_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * 有効なマニフェスト URL を取得（設定優先・既定は定数）。
	 *
	 * @return string
	 */
	public function get_url() {
		$url = trim( (string) $this->settings->get( 'manifest_url', '' ) );
		if ( '' === $url ) {
			$url = KSCP_MANIFEST_URL;
		}
		return $url;
	}

	/**
	 * マニフェストを取得・解析して items を返す。
	 *
	 * @param bool $force キャッシュを無視。
	 * @return array {schema, generated_at, items[], fetched(bool), url, error}
	 */
	public function get( $force = false ) {
		$url = $this->get_url();

		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT );
			if ( false !== $cached && is_array( $cached ) ) {
				$this->last_status = $cached;
				return $cached;
			}
		}

		$result = array(
			'schema'       => 0,
			'generated_at' => '',
			'items'        => array(),
			'fetched'      => false,
			'url'          => $url,
			'error'        => '',
		);

		// SSRF 対策（ユーザーが任意 URL を設定できるため）。
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			$result['error'] = 'invalid_url';
			return $this->finalize( $result, $force );
		}

		$res = wp_remote_get(
			$url,
			array(
				'timeout'            => 15,
				'redirection'        => 3,
				'reject_unsafe_urls' => true,
				'headers'            => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $res ) ) {
			$result['error'] = $res->get_error_message();
			return $this->finalize( $result, $force );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			$result['error'] = 'http_' . $code;
			return $this->finalize( $result, $force );
		}

		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			$result['error'] = 'parse_error';
			return $this->finalize( $result, $force );
		}

		$items = array();
		foreach ( $data['items'] as $raw ) {
			$entry = $this->sanitize_item( $raw );
			if ( $entry ) {
				$items[] = $entry;
			}
		}

		$result['schema']       = isset( $data['schema'] ) ? (int) $data['schema'] : 1;
		$result['generated_at'] = isset( $data['generated_at'] ) ? sanitize_text_field( $data['generated_at'] ) : '';
		$result['items']        = $items;
		$result['fetched']      = true;

		// 成功時のみキャッシュを更新（失敗で good データを壊さない）。
		set_transient( self::TRANSIENT, $result, $this->ttl() );
		$this->last_status = $result;
		return $result;
	}

	/**
	 * 取得失敗時の後処理。前回の良好キャッシュがあればそれを返す。
	 *
	 * @param array $result  失敗結果。
	 * @param bool  $force   強制取得だったか。
	 * @return array
	 */
	private function finalize( $result, $force ) {
		// 失敗時は前回の良好データ（あれば）を温存して返す。
		$cached = get_transient( self::TRANSIENT );
		if ( false !== $cached && is_array( $cached ) && ! empty( $cached['items'] ) ) {
			$cached['error'] = $result['error'];
			$cached['fetched'] = false;
			$this->last_status = $cached;
			return $cached;
		}
		$this->last_status = $result;
		return $result;
	}

	/**
	 * 手動リフレッシュ用キャッシュ削除。
	 */
	public function flush() {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * 診断用に最後の取得状態を返す。
	 *
	 * @return array
	 */
	public function status() {
		if ( null === $this->last_status ) {
			return $this->get();
		}
		return $this->last_status;
	}

	/**
	 * キャッシュ TTL（設定の cache_ttl を流用、最低 1 時間）。
	 *
	 * @return int
	 */
	private function ttl() {
		$ttl = (int) $this->settings->get( 'cache_ttl', 3 * HOUR_IN_SECONDS );
		return max( HOUR_IN_SECONDS, $ttl );
	}

	/**
	 * manifest の 1 エントリをサニタイズ。
	 *
	 * @param mixed $raw 生データ。
	 * @return array|null
	 */
	private function sanitize_item( $raw ) {
		if ( ! is_array( $raw ) || empty( $raw['slug'] ) ) {
			return null;
		}
		$slug = preg_replace( '/[^A-Za-z0-9._\-]/', '', (string) $raw['slug'] );
		if ( '' === $slug ) {
			return null;
		}
		$type = ( isset( $raw['type'] ) && 'theme' === $raw['type'] ) ? 'theme' : 'plugin';
		$entry = array(
			'slug'           => $slug,
			'repo'           => ! empty( $raw['repo'] ) ? preg_replace( '/[^A-Za-z0-9._\-\/]/', '', (string) $raw['repo'] ) : $slug,
			'type'           => $type,
			'name'           => ! empty( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : $slug,
			'latest_version' => isset( $raw['latest_version'] ) ? $this->clean_version( $raw['latest_version'] ) : '',
			'last_updated'   => isset( $raw['last_updated'] ) ? sanitize_text_field( $raw['last_updated'] ) : '',
			'changelog'      => isset( $raw['changelog'] ) ? $this->clean_text( $raw['changelog'] ) : '',
			'is_security'    => ! empty( $raw['is_security'] ),
			'html_url'       => isset( $raw['html_url'] ) ? esc_url_raw( $raw['html_url'] ) : '',
			'download_url'   => isset( $raw['download_url'] ) ? esc_url_raw( $raw['download_url'] ) : '',
		);
		return $entry;
	}

	/**
	 * バージョン文字列の許容文字限定。
	 *
	 * @param string $v 入力。
	 * @return string
	 */
	private function clean_version( $v ) {
		$v = trim( (string) $v );
		$v = preg_replace( '/^[vV]/', '', $v );
		return preg_match( '/^[0-9][0-9A-Za-z.\-]*$/', $v ) ? $v : '';
	}

	/**
	 * changelog テキストの長さ制限（保存・表示用、HTML は表示時にエスケープ）。
	 *
	 * @param string $t 入力。
	 * @return string
	 */
	private function clean_text( $t ) {
		$t = (string) $t;
		if ( strlen( $t ) > 4000 ) {
			$t = substr( $t, 0, 4000 ) . "\n…";
		}
		return $t;
	}
}
