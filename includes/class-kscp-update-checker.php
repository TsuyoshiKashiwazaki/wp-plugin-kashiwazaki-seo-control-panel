<?php
/**
 * 更新チェッカー（D2 バージョン差分照合 / D6 ステータス / D8 セキュリティ強調）。
 *
 * 監視対象ごとにインストール済みバージョンと GitHub 最新版を比較し、
 * ステータス配列を生成して option に保存する（一本道データフローの中核）。
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSCP_Update_Checker {

	/**
	 * セキュリティ更新と判定するキーワード（D8）。
	 *
	 * ascii: 単語境界でマッチ（'rce' が 'source'/'force' に誤マッチしないように）。
	 * mb:    日本語など単語境界を取りにくい語は部分一致。
	 */
	const SECURITY_KEYWORDS_ASCII = array( 'security', 'vulnerability', 'vulnerabilities', 'cve', 'xss', 'csrf', 'sql injection', 'rce', 'critical', 'exploit' );
	const SECURITY_KEYWORDS_MB    = array( '脆弱性', 'セキュリティ', '緊急' );

	/** @var KSCP_Settings */
	private $settings;

	/** @var KSCP_Registry */
	private $registry;

	/**
	 * @param KSCP_Settings $settings 設定。
	 * @param KSCP_Registry $registry レジストリ。
	 */
	public function __construct( KSCP_Settings $settings, KSCP_Registry $registry ) {
		$this->settings = $settings;
		$this->registry = $registry;
	}

	/**
	 * 全監視対象のステータスを取得（保存済みキャッシュ）。
	 *
	 * @return array {generated_at, items[]}
	 */
	public function get_status() {
		$stored = get_option( KSCP_OPT_STATUS, array() );
		if ( ! is_array( $stored ) || empty( $stored['items'] ) ) {
			return array(
				'generated_at' => 0,
				'items'        => array(),
			);
		}
		return $stored;
	}

	/**
	 * 全監視対象を再チェックして保存（cron / 手動から呼ばれる）。
	 *
	 * @param bool $force GitHub キャッシュを無視。
	 * @return array 生成したステータス。
	 */
	public function run_check( $force = false ) {
		$targets        = $this->registry->get_targets( $force );
		$installed_p    = $this->installed_plugins();
		$installed_t    = $this->installed_themes();
		$items          = array();

		foreach ( $targets as $t ) {
			$slug = $t['slug'];
			$type = isset( $t['type'] ) ? $t['type'] : 'plugin';
			$repo = isset( $t['repo'] ) ? $t['repo'] : $slug;

			// インストール照合は正規化コア slug で行う。
			// slug / repo 名 双方の正規化キーで導入済みを探す。
			$keys = array_unique(
				array(
					self::normalize_slug( $slug ),
					self::normalize_slug( $repo ),
				)
			);
			$installed_version = '';
			$map = ( 'theme' === $type ) ? $installed_t : $installed_p;
			foreach ( $keys as $k ) {
				if ( '' !== $k && isset( $map[ $k ] ) ) {
					$installed_version = $map[ $k ];
					break;
				}
			}
			// 自身は実行中の定数から確実に取得。
			if ( ! empty( $t['is_self'] ) ) {
				$installed_version = KSCP_VERSION;
			}

			// 最新版データはマニフェスト由来（per-repo API 呼び出しなし）。
			$latest_version = isset( $t['latest_version'] ) ? $t['latest_version'] : '';
			$changelog      = isset( $t['changelog'] ) ? $t['changelog'] : '';

			$status   = $this->resolve_status( $installed_version, $latest_version );
			$extra_kw = ( ! empty( $t['severity_keywords'] ) && is_array( $t['severity_keywords'] ) ) ? $t['severity_keywords'] : array();

			// 更新タイプ（security / bug / update）。1 更新が複数タイプを持ちうる。
			// 導入版〜最新版の間に含まれる各バージョンのタイプを合成する。
			$types = array();
			if ( 'update-available' === $status ) {
				$versions = ( isset( $t['versions'] ) && is_array( $t['versions'] ) ) ? $t['versions'] : array();
				if ( ! empty( $versions ) ) {
					if ( '' !== $installed_version ) {
						foreach ( $versions as $ver ) {
							// 導入版より新しいバージョン（＝今回の更新に含まれる）のタイプを採用。
							if ( ! empty( $ver['version'] ) && ! empty( $ver['types'] ) && is_array( $ver['types'] )
								&& version_compare( $ver['version'], $installed_version, '>' ) ) {
								$types = array_merge( $types, $ver['types'] );
							}
						}
					}
					// 導入版不明、または一致なし → 最新バージョンのタイプで代替。
					if ( empty( $types ) && ! empty( $versions[0]['types'] ) && is_array( $versions[0]['types'] ) ) {
						$types = $versions[0]['types'];
					}
				}
				// versions が無い旧マニフェスト向けフォールバック。
				if ( empty( $types ) ) {
					$fallback_sec = ! empty( $t['is_security'] )
						|| $this->is_security_update( $changelog . ' ' . $latest_version, $extra_kw );
					$types[] = $fallback_sec ? 'security' : 'update';
				}
				// 表示順を security > bug > update に正規化。
				$order = array( 'security', 'bug', 'update' );
				$types = array_values( array_intersect( $order, array_unique( $types ) ) );
			}
			$is_sec = in_array( 'security', $types, true );

			$items[] = array(
				'slug'              => $slug,
				'repo'              => $repo,
				'type'              => $type,
				'name'              => $t['name'],
				'is_self'           => ! empty( $t['is_self'] ),
				'installed_version' => $installed_version,
				'latest_version'    => $latest_version,
				'version_source'    => 'manifest',
				'status'            => $status,
				'is_security'       => $is_sec,
				'update_types'      => $types,
				'changelog'         => $this->trim_changelog( $changelog ),
				'last_updated'      => isset( $t['last_updated'] ) ? $t['last_updated'] : '',
				'html_url'          => isset( $t['html_url'] ) ? $t['html_url'] : '',
			);
		}

		$result = array(
			'generated_at' => time(),
			'items'        => $items,
		);

		// データ損失防止ガード（多重防御）: マニフェスト取得に「失敗」して対象が
		// 本体 1 件のみへ縮退した場合のみ、以前の良好な複数件ステータスを温存する。
		// 取得に成功して正当に 1 件（本体のみ公開・全除外等）になった場合は通常どおり保存し、
		// 古いデータに固着しないようにする（取得成功/失敗を last_fetch_ok で区別）。
		$only_self    = ( count( $items ) <= 1 );
		$fetch_failed = ! $this->registry->last_fetch_ok();
		if ( $only_self && $fetch_failed ) {
			$prev = get_option( KSCP_OPT_STATUS, array() );
			if ( is_array( $prev ) && ! empty( $prev['items'] ) && count( $prev['items'] ) > 1 ) {
				// 取得失敗による縮退 → 良好な前回データを維持。
				do_action( 'kscp_after_check', $prev );
				return $prev;
			}
		}

		update_option( KSCP_OPT_STATUS, $result, false );

		/**
		 * チェック完了後に発火（通知などのトリガ）。
		 *
		 * @param array $result ステータス。
		 */
		do_action( 'kscp_after_check', $result );

		return $result;
	}

	/**
	 * 更新ありの件数サマリ。
	 *
	 * @param array|null $status 省略時は保存済み。
	 * @return array {updates, security, total}
	 */
	public function summarize( $status = null ) {
		if ( null === $status ) {
			$status = $this->get_status();
		}
		$updates  = 0;
		$security = 0;
		$bug      = 0;
		$feature  = 0;
		$total    = 0;
		foreach ( $status['items'] as $it ) {
			$total++;
			if ( 'update-available' === $it['status'] ) {
				$updates++;
				$types = isset( $it['update_types'] ) && is_array( $it['update_types'] ) ? $it['update_types'] : array();
				if ( in_array( 'security', $types, true ) || ! empty( $it['is_security'] ) ) {
					$security++;
				}
				if ( in_array( 'bug', $types, true ) ) {
					$bug++;
				}
				if ( in_array( 'update', $types, true ) ) {
					$feature++;
				}
			}
		}
		return array(
			'updates'  => $updates,
			'security' => $security,
			'bug'      => $bug,
			'feature'  => $feature,
			'total'    => $total,
		);
	}

	/**
	 * 更新タイプの表示メタ（順序・ラベル・CSS 修飾子）を返す共有ヘルパー。
	 * 1 更新が複数タイプを持つ場合、security > bug > update の順で返す。
	 *
	 * @param array $item ステータス項目。
	 * @return array 各要素 {key, label, short, mod}。
	 */
	public static function update_type_badges( $item ) {
		$types = ( isset( $item['update_types'] ) && is_array( $item['update_types'] ) ) ? $item['update_types'] : array();
		if ( empty( $types ) ) {
			$types = ! empty( $item['is_security'] ) ? array( 'security' ) : array( 'update' );
		}
		$meta = array(
			'security' => array(
				'label' => __( 'セキュリティ', 'kashiwazaki-seo-control-panel' ),
				'short' => __( 'セキュリティ', 'kashiwazaki-seo-control-panel' ),
				'mod'   => 'security',
			),
			'bug'      => array(
				'label' => __( 'バグ修正', 'kashiwazaki-seo-control-panel' ),
				'short' => __( 'バグ修正', 'kashiwazaki-seo-control-panel' ),
				'mod'   => 'bug',
			),
			'update'   => array(
				'label' => __( '機能', 'kashiwazaki-seo-control-panel' ),
				'short' => __( '機能', 'kashiwazaki-seo-control-panel' ),
				'mod'   => 'update',
			),
		);
		$out = array();
		foreach ( array( 'security', 'bug', 'update' ) as $k ) {
			if ( in_array( $k, $types, true ) ) {
				$out[] = array( 'key' => $k ) + $meta[ $k ];
			}
		}
		return $out;
	}

	/**
	 * バージョン比較でステータスを決定（D2）。
	 *
	 * @param string $installed インストール済み。
	 * @param string $latest    最新版。
	 * @return string up-to-date|update-available|not-installed|unknown
	 */
	public function resolve_status( $installed, $latest ) {
		if ( '' === $latest ) {
			return 'unknown';
		}
		if ( '' === $installed ) {
			return 'not-installed';
		}
		if ( version_compare( $installed, $latest, '<' ) ) {
			return 'update-available';
		}
		return 'up-to-date';
	}

	/**
	 * セキュリティ更新か判定（D8）。
	 *
	 * @param string $text changelog + version。
	 * @return bool
	 */
	public function is_security_update( $text, $extra_keywords = array() ) {
		$text = mb_strtolower( (string) $text );

		// manifest 由来の追加キーワード。部分一致で判定。
		if ( ! empty( $extra_keywords ) && is_array( $extra_keywords ) ) {
			foreach ( $extra_keywords as $kw ) {
				$kw = mb_strtolower( trim( (string) $kw ) );
				if ( '' !== $kw && false !== mb_strpos( $text, $kw ) ) {
					return true;
				}
			}
		}

		// ascii キーワードは単語境界でマッチ（誤検知防止）。
		foreach ( self::SECURITY_KEYWORDS_ASCII as $kw ) {
			$pattern = '/\b' . preg_quote( $kw, '/' ) . '\b/u';
			if ( preg_match( $pattern, $text ) ) {
				return true;
			}
		}
		// CVE-XXXX 形式（ハイフン後に数字）も拾う。
		if ( preg_match( '/\bcve-\d/u', $text ) ) {
			return true;
		}
		// 日本語キーワードは部分一致。
		foreach ( self::SECURITY_KEYWORDS_MB as $kw ) {
			if ( '' !== $kw && false !== mb_strpos( $text, mb_strtolower( $kw ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * ディレクトリ名/リポジトリ名を正規化したコア slug に変換。
	 *
	 * `wp-plugin-` / `wp-theme-` プレフィックスと `-main` / `-master` /
	 * `-trunk` サフィックス（GitHub ZIP 展開由来）を除去し、小文字化する。
	 * これによりインストールディレクトリ名と GitHub リポジトリ名の表記揺れを吸収する。
	 *
	 * @param string $slug 入力。
	 * @return string 正規化コア slug。
	 */
	public static function normalize_slug( $slug ) {
		$slug = strtolower( trim( (string) $slug ) );
		$slug = preg_replace( '/^wp-(plugin|theme)-/', '', $slug );
		$slug = preg_replace( '/-(main|master|trunk)$/', '', $slug );
		return $slug;
	}

	/**
	 * インストール済みプラグインの 正規化slug => version マップ。
	 *
	 * @return array
	 */
	private function installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$map = array();
		foreach ( get_plugins() as $file => $data ) {
			// $file 例: "kashiwazaki-seo-link-card/foo.php" → ディレクトリ名を正規化。
			$dir = ( false !== strpos( $file, '/' ) ) ? dirname( $file ) : basename( $file, '.php' );
			$key = self::normalize_slug( $dir );
			if ( '' !== $key && ! isset( $map[ $key ] ) && ! empty( $data['Version'] ) ) {
				$map[ $key ] = $data['Version'];
			}
		}
		return $map;
	}

	/**
	 * インストール済みテーマの 正規化slug => version マップ。
	 *
	 * @return array
	 */
	private function installed_themes() {
		$map = array();
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$ver = $theme->get( 'Version' );
			$key = self::normalize_slug( $stylesheet );
			if ( $ver && '' !== $key && ! isset( $map[ $key ] ) ) {
				$map[ $key ] = $ver;
			}
		}
		return $map;
	}

	/**
	 * changelog を保存用に短縮。
	 *
	 * @param string $body 生 body。
	 * @return string
	 */
	private function trim_changelog( $body ) {
		$body = (string) $body;
		// 保存ステータスは表示に changelog を使わないため短い抜粋のみ保持する。
		// 全文（監視対象 47 件 × 数 KB）を保存すると options 行が肥大化し、
		// 共有ホストの書き込み上限超過で update_option が失敗する不具合を防ぐ。
		if ( function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $body ) > 200 ) {
				$body = mb_substr( $body, 0, 200 ) . '…';
			}
		} elseif ( strlen( $body ) > 200 ) {
			$body = substr( $body, 0, 200 ) . '…';
		}
		return $body;
	}
}
