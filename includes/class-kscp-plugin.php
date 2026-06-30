<?php
/**
 * メインブートストラップクラス。
 *
 * 各コンポーネント（マニフェスト / レジストリ / 更新チェッカー /
 * 自己更新 / cron / 通知 / ニュース / 管理画面）を束ねる単一エントリ。
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KSCP_Plugin {

	/** @var KSCP_Plugin|null */
	private static $instance = null;

	/** @var KSCP_Settings */
	public $settings;

	/** @var KSCP_Manifest */
	public $manifest;

	/** @var KSCP_Registry */
	public $registry;

	/** @var KSCP_Update_Checker */
	public $checker;

	/** @var KSCP_Self_Updater */
	public $self_updater;

	/** @var KSCP_Cron */
	public $cron;

	/** @var KSCP_Notifier */
	public $notifier;

	/** @var KSCP_News */
	public $news;

	/** @var KSCP_Admin */
	public $admin;

	/**
	 * シングルトン取得。
	 *
	 * @return KSCP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * コンストラクタ。
	 */
	private function __construct() {
		$this->includes();
		$this->init_components();
		$this->hooks();
	}

	/**
	 * 依存クラスの読み込み。
	 */
	private function includes() {
		$dir = KSCP_PLUGIN_DIR . 'includes/';
		require_once $dir . 'class-kscp-settings.php';
		require_once $dir . 'class-kscp-manifest.php';
		require_once $dir . 'class-kscp-registry.php';
		require_once $dir . 'class-kscp-update-checker.php';
		require_once $dir . 'class-kscp-self-updater.php';
		require_once $dir . 'class-kscp-cron.php';
		require_once $dir . 'class-kscp-notifier.php';
		require_once $dir . 'class-kscp-news.php';

		if ( is_admin() ) {
			require_once KSCP_PLUGIN_DIR . 'admin/class-kscp-admin.php';
		}
	}

	/**
	 * コンポーネント初期化。
	 */
	private function init_components() {
		$this->settings     = new KSCP_Settings();
		$this->manifest     = new KSCP_Manifest( $this->settings );
		$this->registry     = new KSCP_Registry( $this->settings, $this->manifest );
		$this->checker      = new KSCP_Update_Checker( $this->settings, $this->registry );
		$this->self_updater = new KSCP_Self_Updater( $this->settings, $this->manifest );
		$this->cron         = new KSCP_Cron( $this->settings, $this->checker );
		$this->notifier     = new KSCP_Notifier( $this->settings );
		$this->news         = new KSCP_News( $this->settings );

		if ( is_admin() ) {
			$this->admin = new KSCP_Admin( $this );
		}
	}

	/**
	 * グローバルフック登録。
	 */
	private function hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// 各コンポーネントが自前で register() する。
		$this->self_updater->register();
		$this->cron->register();
		$this->notifier->register();

		if ( is_admin() && $this->admin ) {
			$this->admin->register();
		}
	}

	/**
	 * 翻訳ファイル読み込み。
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'kashiwazaki-seo-control-panel',
			false,
			dirname( KSCP_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * 有効化時：デフォルト設定とスケジュール作成。
	 */
	public static function on_activate() {
		// includes が必要なため最小限ロード。
		require_once KSCP_PLUGIN_DIR . 'includes/class-kscp-settings.php';
		require_once KSCP_PLUGIN_DIR . 'includes/class-kscp-cron.php';

		$settings = new KSCP_Settings();
		$settings->maybe_set_defaults();

		KSCP_Cron::schedule_event( $settings->sanitize_interval( $settings->get( 'cron_interval', 'daily' ) ) );
	}

	/**
	 * 無効化時：スケジュール解除。
	 */
	public static function on_deactivate() {
		require_once KSCP_PLUGIN_DIR . 'includes/class-kscp-cron.php';
		KSCP_Cron::clear_event();
	}
}
