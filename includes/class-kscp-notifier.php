<?php
/**
 * メール通知（D9・最小スコープ）。
 *
 * チェック完了後、更新ありを管理者宛にメール通知する。セキュリティ更新は
 * 即時送信。同一バージョンの二重通知を防ぐため通知済みバージョンを記録する。
 * 日次/週次ダイジェストは v1.1+（本バージョンは「更新検知時に送信」のみ）。
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSCP_Notifier {

	const OPT_NOTIFIED = 'kscp_notified_versions';

	/** @var KSCP_Settings */
	private $settings;

	/**
	 * @param KSCP_Settings $settings 設定。
	 */
	public function __construct( KSCP_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * フック登録。
	 */
	public function register() {
		add_action( 'kscp_after_check', array( $this, 'maybe_notify' ) );
	}

	/**
	 * 必要なら通知メールを送信。
	 *
	 * @param array $status ステータス。
	 */
	public function maybe_notify( $status ) {
		if ( empty( $this->settings->get( 'email_enabled', 1 ) ) ) {
			return;
		}
		if ( empty( $status['items'] ) ) {
			return;
		}

		$notified = get_option( self::OPT_NOTIFIED, array() );
		if ( ! is_array( $notified ) ) {
			$notified = array();
		}

		$security_items = array();
		$normal_items   = array();

		foreach ( $status['items'] as $it ) {
			if ( 'update-available' !== $it['status'] ) {
				continue;
			}
			// 既に同一バージョンを通知済みならスキップ。
			if ( isset( $notified[ $it['slug'] ] ) && $notified[ $it['slug'] ] === $it['latest_version'] ) {
				continue;
			}
			if ( ! empty( $it['is_security'] ) ) {
				$security_items[] = $it;
			} else {
				$normal_items[] = $it;
			}
		}

		if ( empty( $security_items ) && empty( $normal_items ) ) {
			return;
		}

		$instant_security = ! empty( $this->settings->get( 'email_security_instant', 1 ) );
		$updated          = false;

		// セキュリティ即時が ON かつセキュリティ更新あり → 優先メールを分離送信。
		// OFF の場合はセキュリティ・通常を 1 通にまとめて送る（トグルが挙動に反映される）。
		// 各バッチは送信成功時のみ「通知済み」に記録する。片方の送信失敗で
		// もう片方まで通知済み扱いにして欠落させない。
		if ( $instant_security && ! empty( $security_items ) ) {
			if ( $this->send( $security_items, true ) ) {
				$this->mark_notified( $notified, $security_items );
				$updated = true;
			}
			if ( ! empty( $normal_items ) && $this->send( $normal_items, false ) ) {
				$this->mark_notified( $notified, $normal_items );
				$updated = true;
			}
		} else {
			$combined     = array_merge( $security_items, $normal_items );
			$has_security = ! empty( $security_items );
			if ( $this->send( $combined, $has_security ) ) {
				$this->mark_notified( $notified, $combined );
				$updated = true;
			}
		}

		if ( $updated ) {
			update_option( self::OPT_NOTIFIED, $notified, false );
		}
	}

	/**
	 * 送信成功したアイテムを通知済みマップに記録（参照渡し）。
	 *
	 * @param array $notified 通知済みマップ（slug => version）。
	 * @param array $items    送信に成功したアイテム群。
	 */
	private function mark_notified( array &$notified, array $items ) {
		foreach ( $items as $it ) {
			$notified[ $it['slug'] ] = $it['latest_version'];
		}
	}

	/**
	 * メール送信。
	 *
	 * @param array $items        通知対象。
	 * @param bool  $has_security セキュリティ更新を含むか。
	 * @return bool
	 */
	private function send( $items, $has_security ) {
		$to = $this->settings->get( 'email_recipient', get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$count   = count( $items );
		$prefix  = $has_security ? __( '[セキュリティ] ', 'kashiwazaki-seo-control-panel' ) : '';
		$subject = $prefix . sprintf(
			/* translators: %d: number of updates */
			__( '[Kashiwazaki SEO ControlPanel] %d 件の更新があります', 'kashiwazaki-seo-control-panel' ),
			$count
		);

		$lines   = array();
		$lines[] = sprintf(
			/* translators: %s: site name */
			__( '%s で監視中のプラグイン/テーマに更新が見つかりました。', 'kashiwazaki-seo-control-panel' ),
			$site
		);
		$lines[] = '';
		$label_theme   = __( 'テーマ', 'kashiwazaki-seo-control-panel' );
		$label_plugin  = __( 'プラグイン', 'kashiwazaki-seo-control-panel' );
		$label_sec     = __( '【セキュリティ】 ', 'kashiwazaki-seo-control-panel' );
		$label_notinst = __( '未インストール', 'kashiwazaki-seo-control-panel' );
		foreach ( $items as $it ) {
			$mark    = ! empty( $it['is_security'] ) ? $label_sec : '';
			$lines[] = sprintf(
				'%s%s (%s)',
				$mark,
				$it['name'],
				'theme' === $it['type'] ? $label_theme : $label_plugin
			);
			$lines[] = sprintf(
				/* translators: 1: current version, 2: latest version */
				__( '  現在: %1$s → 最新: %2$s', 'kashiwazaki-seo-control-panel' ),
				'' !== $it['installed_version'] ? $it['installed_version'] : $label_notinst,
				$it['latest_version']
			);
			if ( ! empty( $it['html_url'] ) ) {
				$lines[] = '  ' . $it['html_url'];
			}
			$lines[] = '';
		}
		$lines[] = '----';
		$lines[] = __( '管理画面のコントロールパネル: ', 'kashiwazaki-seo-control-panel' ) . admin_url( 'admin.php?page=' . KSCP_MENU_SLUG );
		$lines[] = __( 'この通知は Kashiwazaki SEO ControlPanel の設定で停止できます。', 'kashiwazaki-seo-control-panel' );

		$body = implode( "\n", $lines );

		return wp_mail( $to, $subject, $body );
	}
}
