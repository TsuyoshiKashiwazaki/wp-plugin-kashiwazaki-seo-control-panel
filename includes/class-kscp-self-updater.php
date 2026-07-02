<?php
/**
 * KSCP 自身の自己更新（D4・最重要）。
 *
 * GitHub の最新リリースを WordPress 標準の更新フローに注入する。
 * 公式ディレクトリ未申請でも管理画面の「更新」からアップデートできるようにする。
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSCP_Self_Updater {

	/** @var KSCP_Settings */
	private $settings;

	/** @var KSCP_Manifest */
	private $manifest;

	/**
	 * @param KSCP_Settings $settings 設定。
	 * @param KSCP_Manifest $manifest マニフェスト。
	 */
	public function __construct( KSCP_Settings $settings, KSCP_Manifest $manifest ) {
		$this->settings = $settings;
		$this->manifest = $manifest;
	}

	/**
	 * マニフェストから KSCP 自身の最新版エントリを取得。
	 *
	 * @return array {version, download_url, changelog, html_url}
	 */
	private function self_latest() {
		$out  = array(
			'version'      => '',
			'download_url' => '',
			'changelog'    => '',
			'html_url'     => 'https://github.com/' . KSCP_GITHUB_OWNER . '/' . KSCP_PLUGIN_SLUG,
		);
		$data = $this->manifest->get();
		if ( empty( $data['items'] ) ) {
			return $out;
		}
		foreach ( $data['items'] as $item ) {
			if ( isset( $item['slug'] ) && KSCP_PLUGIN_SLUG === $item['slug'] ) {
				$out['version']      = isset( $item['latest_version'] ) ? $item['latest_version'] : '';
				$out['download_url'] = isset( $item['download_url'] ) ? $item['download_url'] : '';
				$out['changelog']    = isset( $item['changelog'] ) ? $item['changelog'] : '';
				$out['html_url']     = ! empty( $item['html_url'] ) ? $item['html_url'] : $out['html_url'];
				break;
			}
		}
		// 自己更新パッケージは信頼できる GitHub ホスト・https のみ許可（多重防御）。
		// マニフェストが改ざん・差し替えされても任意 URL の zip を配布インストールさせない。
		if ( '' !== $out['download_url'] && ! $this->is_trusted_package_url( $out['download_url'] ) ) {
			$out['download_url'] = '';
		}
		return $out;
	}

	/**
	 * 自己更新パッケージ URL として信頼してよいか。
	 *
	 * https + GitHub ホストに加え、URL パスが作者アカウント（KSCP_GITHUB_OWNER）の
	 * 本プラグインリポジトリを指すことまで検証する。マニフェストが改ざんされても
	 * 第三者リポジトリの zip はインストールさせない（多重防御）。
	 *
	 * @param string $url URL。
	 * @return bool
	 */
	private function is_trusted_package_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		$host  = strtolower( $parts['host'] );
		$path  = isset( $parts['path'] ) ? strtolower( $parts['path'] ) : '';
		$owner = strtolower( KSCP_GITHUB_OWNER );
		$repo  = strtolower( KSCP_PLUGIN_SLUG );

		// ホストごとの owner/repo パス位置（owner を URL から検証できないホストは許可しない）。
		$prefixes = array(
			'github.com'                => '/' . $owner . '/' . $repo . '/',
			'codeload.github.com'       => '/' . $owner . '/' . $repo . '/',
			'raw.githubusercontent.com' => '/' . $owner . '/' . $repo . '/',
			'api.github.com'            => '/repos/' . $owner . '/' . $repo . '/',
		);
		if ( ! isset( $prefixes[ $host ] ) ) {
			return false;
		}
		return 0 === strpos( $path, $prefixes[ $host ] );
	}

	/**
	 * フック登録。
	 */
	public function register() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
	}

	/**
	 * 更新トランジェントに KSCP の更新情報を注入。
	 *
	 * @param mixed $transient update_plugins トランジェント。
	 * @return mixed
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$latest = $this->self_latest();
		if ( empty( $latest['version'] ) || empty( $latest['download_url'] ) ) {
			return $transient;
		}

		// 最新版が現行より新しい場合のみ注入。
		if ( ! version_compare( KSCP_VERSION, $latest['version'], '<' ) ) {
			// 念のため no_update 側に登録（「最新です」表示の整合）。
			if ( isset( $transient->no_update ) ) {
				$transient->no_update[ KSCP_PLUGIN_BASENAME ] = $this->build_response_object( $latest, false );
			}
			return $transient;
		}

		$transient->response[ KSCP_PLUGIN_BASENAME ] = $this->build_response_object( $latest, true );
		return $transient;
	}

	/**
	 * plugins_api（詳細モーダル）に情報を供給。
	 *
	 * @param mixed  $result 既存結果。
	 * @param string $action アクション。
	 * @param object $args   引数。
	 * @return mixed
	 */
	public function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || KSCP_PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}
		$latest = $this->self_latest();

		$info = new stdClass();
		$info->name          = 'Kashiwazaki SEO ControlPanel';
		$info->slug          = KSCP_PLUGIN_SLUG;
		$info->version       = ! empty( $latest['version'] ) ? $latest['version'] : KSCP_VERSION;
		$info->author        = '<a href="' . esc_url( 'https://www.tsuyoshikashiwazaki.jp/profile/' ) . '">柏崎剛 (Tsuyoshi Kashiwazaki)</a>';
		$info->homepage      = KSCP_AUTHOR_SITE;
		$info->download_link = ! empty( $latest['download_url'] ) ? $latest['download_url'] : '';
		$info->sections      = array(
			'description' => esc_html__( '柏崎剛が公開する wp- 付きプラグイン/テーマの最新版を監視・通知する作者専用コントロールパネル。', 'kashiwazaki-seo-control-panel' ),
			'changelog'   => ! empty( $latest['changelog'] ) ? wp_kses_post( nl2br( $latest['changelog'] ) ) : '',
		);
		return $info;
	}

	/**
	 * zipball 展開時のフォルダ名を正規の slug に修正。
	 *
	 * GitHub の zipball は "Owner-repo-<sha>" 形式で展開されるため、
	 * 正しいプラグインディレクトリ名へリネームする。
	 *
	 * @param string      $source        展開元ディレクトリ。
	 * @param string      $remote_source リモートソース。
	 * @param WP_Upgrader $upgrader      アップグレーダ。
	 * @param array       $hook_extra    追加情報。
	 * @return string|WP_Error
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		// 本プラグインの更新時のみ対象。
		if ( empty( $hook_extra['plugin'] ) || KSCP_PLUGIN_BASENAME !== $hook_extra['plugin'] ) {
			return $source;
		}
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			// 通常 upgrader が初期化済みだが、未初期化なら明示的に初期化する。
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			if ( ! $wp_filesystem ) {
				// 初期化できない場合はリネームせず元のままにし、誤った slug での配置を避ける。
				return new WP_Error( 'kscp_fs_unavailable', esc_html__( 'ファイルシステムを初期化できませんでした。', 'kashiwazaki-seo-control-panel' ) );
			}
		}

		$desired = trailingslashit( $remote_source ) . KSCP_PLUGIN_SLUG;
		$source  = untrailingslashit( $source );

		if ( untrailingslashit( $desired ) === $source ) {
			return trailingslashit( $source );
		}

		if ( $wp_filesystem->move( $source, untrailingslashit( $desired ), true ) ) {
			return trailingslashit( $desired );
		}
		return new WP_Error( 'kscp_rename_failed', esc_html__( '更新ファイルのフォルダ名修正に失敗しました。', 'kashiwazaki-seo-control-panel' ) );
	}

	/**
	 * 更新レスポンスオブジェクト生成。
	 *
	 * @param array $latest     最新情報。
	 * @param bool  $has_update 更新ありか。
	 * @return object
	 */
	private function build_response_object( $latest, $has_update ) {
		$obj              = new stdClass();
		$obj->slug        = KSCP_PLUGIN_SLUG;
		$obj->plugin      = KSCP_PLUGIN_BASENAME;
		$obj->new_version = $has_update ? $latest['version'] : KSCP_VERSION;
		$obj->url         = KSCP_AUTHOR_SITE;
		$obj->package     = $has_update ? $latest['download_url'] : '';
		$obj->tested      = '';
		$obj->icons       = array();
		return $obj;
	}
}
