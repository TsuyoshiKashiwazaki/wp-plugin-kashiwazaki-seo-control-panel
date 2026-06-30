<?php
/**
 * 管理画面コントローラ。
 *
 * D5 タブ式コントロールパネル / D6 一覧テーブル / D7 控えめウィジェット /
 * D12 ニュース / D13 設定リンク / D15 手動チェック・診断 /
 * D17 i18n・a11y / D26 プライバシートグル をまとめて担う。
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSCP_Admin {

	/** @var KSCP_Plugin */
	private $core;

	/**
	 * @param KSCP_Plugin $core メインインスタンス。
	 */
	public function __construct( KSCP_Plugin $core ) {
		$this->core = $core;
	}

	/**
	 * フック登録。
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );

		// プラグイン一覧の設定リンク（D13）。
		add_filter( 'plugin_action_links_' . KSCP_PLUGIN_BASENAME, array( $this, 'action_links' ) );

		// POST ハンドラ。
		add_action( 'admin_post_kscp_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_kscp_recheck', array( $this, 'handle_recheck' ) );
	}

	/**
	 * 管理メニュー追加（position 80・確定仕様）。
	 */
	public function add_menu() {
		$icon = 'dashicons-shield-alt';
		add_menu_page(
			__( 'Kashiwazaki SEO ControlPanel', 'kashiwazaki-seo-control-panel' ),
			__( 'Kashiwazaki SEO ControlPanel', 'kashiwazaki-seo-control-panel' ),
			'manage_options',
			KSCP_MENU_SLUG,
			array( $this, 'render_page' ),
			$icon,
			KSCP_MENU_POSITION
		);
	}

	/**
	 * プラグイン一覧の設定リンク（D13）。
	 *
	 * @param array $links 既存リンク。
	 * @return array
	 */
	public function action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . KSCP_MENU_SLUG . '&tab=settings' ) ),
			esc_html__( '設定', 'kashiwazaki-seo-control-panel' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * 管理画面アセット読み込み（本プラグインのページのみ）。
	 *
	 * @param string $hook 現在のフック。
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, KSCP_MENU_SLUG ) && 'index.php' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'kscp-admin',
			KSCP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			KSCP_VERSION
		);
	}

	/**
	 * 控えめなダッシュボードウィジェット（D7）。
	 */
	public function add_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'kscp_dashboard_widget',
			__( 'Kashiwazaki SEO ControlPanel', 'kashiwazaki-seo-control-panel' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * ダッシュボードウィジェット描画（控えめ・件数のみ）。
	 */
	public function render_dashboard_widget() {
		$summary  = $this->core->checker->summarize();
		$status   = $this->core->checker->get_status();
		$panel    = esc_url( admin_url( 'admin.php?page=' . KSCP_MENU_SLUG ) );
		$updates  = (int) $summary['updates'];
		$security = (int) $summary['security'];

		if ( empty( $status['items'] ) ) {
			$state = 'idle';
		} elseif ( $updates > 0 ) {
			$state = ( $security > 0 ) ? 'security' : 'update';
		} else {
			$state = 'ok';
		}

		echo '<div class="kscp-dw kscp-dw--' . esc_attr( $state ) . '">';

		if ( 'idle' === $state ) {
			echo '<div class="kscp-dw-hero">';
			echo '<span class="kscp-dw-icon dashicons dashicons-search"></span>';
			echo '<div class="kscp-dw-text"><span class="kscp-dw-title">' . esc_html__( 'まだチェックしていません', 'kashiwazaki-seo-control-panel' ) . '</span>';
			echo '<span class="kscp-dw-sub">' . esc_html__( '最新状況を確認しましょう', 'kashiwazaki-seo-control-panel' ) . '</span></div>';
			echo '</div>';
		} elseif ( 'ok' === $state ) {
			echo '<div class="kscp-dw-hero">';
			echo '<span class="kscp-dw-icon dashicons dashicons-yes-alt"></span>';
			echo '<div class="kscp-dw-text"><span class="kscp-dw-title">' . esc_html__( 'すべて最新です', 'kashiwazaki-seo-control-panel' ) . '</span>';
			echo '<span class="kscp-dw-sub">' . sprintf(
				/* translators: %d: number of monitored items */
				esc_html__( '監視対象 %d 件をチェック済み', 'kashiwazaki-seo-control-panel' ),
				(int) $summary['total']
			) . '</span></div>';
			echo '</div>';
		} else {
			echo '<div class="kscp-dw-hero">';
			echo '<span class="kscp-dw-num">' . esc_html( number_format_i18n( $updates ) ) . '</span>';
			echo '<div class="kscp-dw-text"><span class="kscp-dw-title">' . esc_html__( '件の更新があります', 'kashiwazaki-seo-control-panel' ) . '</span>';
			if ( $security > 0 ) {
				echo '<span class="kscp-dw-badge"><span class="dashicons dashicons-shield-alt"></span>' . sprintf(
					/* translators: %d: number of security updates */
					esc_html__( 'セキュリティ %d 件', 'kashiwazaki-seo-control-panel' ),
					$security
				) . '</span>';
			} else {
				echo '<span class="kscp-dw-sub">' . sprintf(
					/* translators: %d: number of monitored items */
					esc_html__( '監視対象 %d 件中', 'kashiwazaki-seo-control-panel' ),
					(int) $summary['total']
				) . '</span>';
			}
			echo '</div>';
			echo '</div>';

			// 更新できる項目の一覧（名前・バージョン遷移・セキュリティ印）。
			$kscp_ups = array();
			foreach ( $status['items'] as $kscp_it ) {
				if ( isset( $kscp_it['status'] ) && 'update-available' === $kscp_it['status'] ) {
					$kscp_ups[] = $kscp_it;
				}
			}
			if ( ! empty( $kscp_ups ) ) {
				$kscp_max  = 6;
				$kscp_more = count( $kscp_ups ) - $kscp_max;
				$kscp_ups  = array_slice( $kscp_ups, 0, $kscp_max );
				echo '<ul class="kscp-dw-list">';
				foreach ( $kscp_ups as $kscp_it ) {
					$kscp_sec  = ! empty( $kscp_it['is_security'] );
					$kscp_dot  = $kscp_sec ? 'kscp-dw-dot--sec' : '';
					echo '<li class="kscp-dw-item">';
					echo '<span class="kscp-dw-dot ' . esc_attr( $kscp_dot ) . '"></span>';
					echo '<span class="kscp-dw-name">' . esc_html( $kscp_it['name'] );
					if ( $kscp_sec ) {
						echo ' <span class="kscp-dw-tag">' . esc_html__( 'セキュリティ', 'kashiwazaki-seo-control-panel' ) . '</span>';
					}
					echo '</span>';
					$kscp_from = ( '' !== $kscp_it['installed_version'] ) ? $kscp_it['installed_version'] : '—';
					$kscp_to   = ( '' !== $kscp_it['latest_version'] ) ? $kscp_it['latest_version'] : '—';
					echo '<span class="kscp-dw-ver">' . esc_html( $kscp_from ) . ' &rarr; ' . esc_html( $kscp_to ) . '</span>';
					echo '</li>';
				}
				echo '</ul>';
				if ( $kscp_more > 0 ) {
					echo '<p class="kscp-dw-more">' . sprintf(
						/* translators: %d: number of additional updatable items */
						esc_html__( 'ほか %d 件', 'kashiwazaki-seo-control-panel' ),
						(int) $kscp_more
					) . '</p>';
				}
			}
		}

		echo '<a class="kscp-dw-cta" href="' . esc_url( $panel ) . '">' . esc_html__( 'コントロールパネルを開く', 'kashiwazaki-seo-control-panel' ) . ' <span class="dashicons dashicons-arrow-right-alt2"></span></a>';
		echo '</div>';
	}

	/**
	 * 設定保存ハンドラ（nonce + 権限）。
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '権限がありません。', 'kashiwazaki-seo-control-panel' ) );
		}
		check_admin_referer( 'kscp_save_settings' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- 直上で検証済み。
		$raw = isset( $_POST['kscp'] ) ? wp_unslash( $_POST['kscp'] ) : array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$this->core->settings->save( $raw );

		// 間隔変更を即時反映。
		KSCP_Cron::clear_event();
		KSCP_Cron::schedule_event( $this->core->settings->get( 'cron_interval', 'daily' ) );

		$this->redirect_with( 'settings', 'saved' );
	}

	/**
	 * 手動再チェックハンドラ（D15）。
	 */
	public function handle_recheck() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '権限がありません。', 'kashiwazaki-seo-control-panel' ) );
		}
		check_admin_referer( 'kscp_recheck' );

		// マニフェストは run_check( true ) が force 取得で最新化する。
		// ここで flush() するとフェイルオーバー用キャッシュが消え、取得失敗時に
		// 良好データを失う（データ損失）ため flush しない。ニュースのみ更新。
		$this->core->news->flush();
		$this->core->checker->run_check( true );

		$this->redirect_with( 'overview', 'rechecked' );
	}

	/**
	 * タブ・通知付きでリダイレクト。
	 *
	 * @param string $tab    タブ。
	 * @param string $notice 通知キー。
	 */
	private function redirect_with( $tab, $notice ) {
		$url = add_query_arg(
			array(
				'page'   => KSCP_MENU_SLUG,
				'tab'    => $tab,
				'notice' => $notice,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * 現在のタブを取得。
	 *
	 * @return string
	 */
	private function current_tab() {
		$allowed = array( 'overview', 'news', 'settings', 'diagnostics' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 表示のみ。
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		return in_array( $tab, $allowed, true ) ? $tab : 'overview';
	}

	/**
	 * 管理ページ描画（タブ振り分け）。
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '権限がありません。', 'kashiwazaki-seo-control-panel' ) );
		}
		$tab    = $this->current_tab();
		$core   = $this->core;
		$status = $core->checker->get_status();

		require KSCP_PLUGIN_DIR . 'admin/views/header.php';

		switch ( $tab ) {
			case 'news':
				require KSCP_PLUGIN_DIR . 'admin/views/tab-news.php';
				break;
			case 'settings':
				require KSCP_PLUGIN_DIR . 'admin/views/tab-settings.php';
				break;
			case 'diagnostics':
				require KSCP_PLUGIN_DIR . 'admin/views/tab-diagnostics.php';
				break;
			case 'overview':
			default:
				require KSCP_PLUGIN_DIR . 'admin/views/tab-overview.php';
				break;
		}

		require KSCP_PLUGIN_DIR . 'admin/views/footer.php';
	}

	/**
	 * タブ URL を生成（ビューから利用）。
	 *
	 * @param string $tab タブ。
	 * @return string
	 */
	public static function tab_url( $tab ) {
		return esc_url(
			add_query_arg(
				array(
					'page' => KSCP_MENU_SLUG,
					'tab'  => $tab,
				),
				admin_url( 'admin.php' )
			)
		);
	}

	/**
	 * 管理通知の表示。
	 *
	 * @param string $notice キー。
	 */
	public static function render_notice( $notice ) {
		$map = array(
			'saved'     => array( 'success', __( '設定を保存しました。', 'kashiwazaki-seo-control-panel' ) ),
			'rechecked' => array( 'success', __( '最新情報を再取得しました。', 'kashiwazaki-seo-control-panel' ) ),
		);
		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $map[ $notice ][0] ),
			esc_html( $map[ $notice ][1] )
		);
	}
}
