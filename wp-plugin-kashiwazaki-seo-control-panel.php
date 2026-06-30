<?php
/**
 * Plugin Name:       Kashiwazaki SEO ControlPanel
 * Plugin URI:        https://www.tsuyoshikashiwazaki.jp
 * Description:       柏崎剛が公開する wp- 付きプラグイン/テーマの最新版を単一マニフェストから取得し、インストール済みバージョンが古い場合に管理画面とメールで通知する作者専用コントロールパネル。アクセストークン不要。本プラグイン自身の自己更新にも対応。
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI:        https://www.tsuyoshikashiwazaki.jp/profile/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kashiwazaki-seo-control-panel
 * Domain Path:       /languages
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 直接アクセス禁止
}

// ---------------------------------------------------------------------------
// 定数定義
// ---------------------------------------------------------------------------
define( 'KSCP_VERSION', '1.0.0' );
define( 'KSCP_PLUGIN_FILE', __FILE__ );
define( 'KSCP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'KSCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KSCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KSCP_PLUGIN_SLUG', 'wp-plugin-kashiwazaki-seo-control-panel' );

// 作者の GitHub アカウント / サイト
define( 'KSCP_GITHUB_OWNER', 'TsuyoshiKashiwazaki' );
define( 'KSCP_AUTHOR_SITE', 'https://www.tsuyoshikashiwazaki.jp' );
define( 'KSCP_NEWS_URL', 'https://www.tsuyoshikashiwazaki.jp/news/' );

// 監視データの単一マニフェスト（既定 URL・設定で上書き可）。
// 配布対応のため GitHub API を個別に叩かず、この 1 ファイルだけ取得する。
// 生成元は別リポジトリ（kscp-assets）で 4 時間ごとに自動更新される。
define( 'KSCP_MANIFEST_URL', 'https://raw.githubusercontent.com/TsuyoshiKashiwazaki/kscp-assets/main/manifest.json' );

// 管理メニュー位置（確定仕様）
define( 'KSCP_MENU_POSITION', 80 );
define( 'KSCP_MENU_SLUG', 'kashiwazaki-seo-control-panel' );

// オプションキー
define( 'KSCP_OPT_SETTINGS', 'kscp_settings' );
define( 'KSCP_OPT_STATUS', 'kscp_status_cache' );
define( 'KSCP_OPT_LOG', 'kscp_event_log' );

// wp-cron フック名
define( 'KSCP_CRON_HOOK', 'kscp_scheduled_check' );

// ---------------------------------------------------------------------------
// オートローダ（includes / admin のクラスを読み込む）
// ---------------------------------------------------------------------------
require_once KSCP_PLUGIN_DIR . 'includes/class-kscp-plugin.php';

/**
 * メインインスタンス取得。
 *
 * @return KSCP_Plugin
 */
function kscp() {
	return KSCP_Plugin::instance();
}

// 起動
kscp();

// ---------------------------------------------------------------------------
// 有効化 / 無効化フック
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, array( 'KSCP_Plugin', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( 'KSCP_Plugin', 'on_deactivate' ) );
