<?php
/**
 * 管理画面ヘッダ＋タブナビ。
 *
 * @package KashiwazakiSeoControlPanel
 * @var string $tab 現在のタブ。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kscp_tabs = array(
	'overview'    => __( '更新一覧', 'kashiwazaki-seo-control-panel' ),
	'news'        => __( 'ニュース', 'kashiwazaki-seo-control-panel' ),
	'settings'    => __( '通知・設定', 'kashiwazaki-seo-control-panel' ),
	'diagnostics' => __( '診断', 'kashiwazaki-seo-control-panel' ),
);

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 表示のみ。
$kscp_notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
?>
<div class="wrap kscp-wrap">
	<h1 class="kscp-title">
		<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
		<?php echo esc_html__( 'Kashiwazaki SEO ControlPanel', 'kashiwazaki-seo-control-panel' ); ?>
		<span class="kscp-ver">v<?php echo esc_html( KSCP_VERSION ); ?></span>
	</h1>

	<p class="kscp-lead">
		<?php echo esc_html__( '柏崎剛が公開する GitHub 上の wp- プラグイン/テーマの最新版を監視し、更新情報をお届けするプラグインです。インストール済みのバージョンが古くなると、この画面とメールでお知らせします。', 'kashiwazaki-seo-control-panel' ); ?>
	</p>

	<?php
	if ( $kscp_notice ) {
		KSCP_Admin::render_notice( $kscp_notice );
	}
	?>

	<h2 class="nav-tab-wrapper kscp-tabs">
		<?php foreach ( $kscp_tabs as $kscp_key => $kscp_label ) : ?>
			<a href="<?php echo KSCP_Admin::tab_url( $kscp_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tab_url は esc_url 済み。 ?>"
				class="nav-tab <?php echo ( $tab === $kscp_key ) ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $kscp_label ); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<div class="kscp-tab-content">
