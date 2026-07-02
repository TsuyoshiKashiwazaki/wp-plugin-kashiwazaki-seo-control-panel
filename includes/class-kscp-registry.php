<?php
/**
 * 監視対象レジストリ（D1）。
 *
 * 監視対象とその最新版データを単一マニフェスト（KSCP_Manifest）から取得し、
 * ユーザー設定（追加・除外）とマージする。KSCP（本プラグイン）自身を常に最上位に固定する。
 * GitHub REST API を個別に叩かない（配布対応・トークン不要）。
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSCP_Registry {

	/** @var KSCP_Settings */
	private $settings;

	/** @var KSCP_Manifest */
	private $manifest;

	/** @var bool 直近 get_targets() でのマニフェスト取得が成功したか。 */
	private $last_fetch_ok = false;

	/**
	 * @param KSCP_Settings $settings 設定。
	 * @param KSCP_Manifest $manifest マニフェスト。
	 */
	public function __construct( KSCP_Settings $settings, KSCP_Manifest $manifest ) {
		$this->settings = $settings;
		$this->manifest = $manifest;
	}

	/**
	 * 直近 get_targets() でマニフェストを正常取得できたか。
	 *
	 * @return bool 取得成功なら true、取得失敗（ネットワーク不通等）なら false。
	 */
	public function last_fetch_ok() {
		return $this->last_fetch_ok;
	}

	/**
	 * KSCP 自身のレジストリエントリ。
	 *
	 * @return array
	 */
	public function self_entry() {
		return array(
			'slug'           => KSCP_PLUGIN_SLUG,
			'repo'           => KSCP_PLUGIN_SLUG,
			'type'           => 'plugin',
			'name'           => 'Kashiwazaki SEO ControlPanel',
			'is_self'        => true,
			'latest_version' => '',
			'last_updated'   => '',
			'changelog'      => '',
			'is_security'    => false,
			'html_url'       => 'https://github.com/' . KSCP_GITHUB_OWNER . '/' . KSCP_PLUGIN_SLUG,
			'download_url'   => '',
		);
	}

	/**
	 * 監視対象一覧を確定して返す。KSCP を先頭に固定。
	 *
	 * @param bool $force マニフェスト強制再取得。
	 * @return array entry の配列（連番）。各 entry は最新版データを含む。
	 */
	public function get_targets( $force = false ) {
		$data   = $this->manifest->get( $force );
		// エラーが無ければ取得成功（キャッシュ命中も成功扱い）。失敗時のみ false。
		$this->last_fetch_ok = empty( $data['error'] );
		$merged = array();

		// マニフェスト由来。
		if ( ! empty( $data['items'] ) ) {
			foreach ( $data['items'] as $item ) {
				$item['is_self'] = ( $item['slug'] === KSCP_PLUGIN_SLUG );
				$merged[ $item['slug'] ] = $item;
			}
		}

		// ユーザー追加（任意・将来 UI 用）。
		$extra = $this->settings->get( 'extra_targets', array() );
		if ( is_array( $extra ) ) {
			foreach ( $extra as $e ) {
				$entry = $this->sanitize_entry( $e );
				if ( $entry ) {
					$merged[ $entry['slug'] ] = isset( $merged[ $entry['slug'] ] )
						? array_merge( $merged[ $entry['slug'] ], $entry )
						: $entry;
				}
			}
		}

		// 除外（本体は除外不可）。保存済み設定・マニフェスト双方の大文字小文字の揺れを吸収して照合する。
		$excluded = array_map( 'strtolower', array_map( 'strval', (array) $this->settings->get( 'excluded_slugs', array() ) ) );
		$excluded = array_diff( $excluded, array( strtolower( KSCP_PLUGIN_SLUG ) ) );
		if ( $excluded ) {
			foreach ( array_keys( $merged ) as $key ) {
				if ( in_array( strtolower( (string) $key ), $excluded, true ) ) {
					unset( $merged[ $key ] );
				}
			}
		}

		// KSCP 自身を保証 + 先頭固定。マニフェストに含まれていれば最新版データを引き継ぐ。
		$self = $this->self_entry();
		if ( isset( $merged[ KSCP_PLUGIN_SLUG ] ) ) {
			$self = array_merge( $self, $merged[ KSCP_PLUGIN_SLUG ] );
			$self['is_self'] = true;
			$self['name']    = 'Kashiwazaki SEO ControlPanel';
			unset( $merged[ KSCP_PLUGIN_SLUG ] );
		}

		// 残りは名前順。
		uasort(
			$merged,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return array_merge( array( $self ), array_values( $merged ) );
	}

	/**
	 * ユーザー追加エントリのサニタイズ。
	 *
	 * @param mixed $raw 生データ。
	 * @return array|null
	 */
	private function sanitize_entry( $raw ) {
		if ( ! is_array( $raw ) || empty( $raw['slug'] ) ) {
			return null;
		}
		$slug = preg_replace( '/[^A-Za-z0-9._\-]/', '', (string) $raw['slug'] );
		if ( '' === $slug ) {
			return null;
		}
		$type = ( isset( $raw['type'] ) && 'theme' === $raw['type'] ) ? 'theme' : 'plugin';
		return array(
			'slug'           => $slug,
			'repo'           => ! empty( $raw['repo'] ) ? preg_replace( '/[^A-Za-z0-9._\-\/]/', '', (string) $raw['repo'] ) : $slug,
			'type'           => $type,
			'name'           => ! empty( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : $slug,
			'is_self'        => ( $slug === KSCP_PLUGIN_SLUG ),
			'latest_version' => '',
			'last_updated'   => '',
			'changelog'      => '',
			'is_security'    => false,
			'html_url'       => '',
			'download_url'   => '',
		);
	}
}
