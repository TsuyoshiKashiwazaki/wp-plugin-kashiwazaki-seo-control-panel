<?php
/**
 * 診断タブ（D15 接続診断）— マニフェスト取得状況を表示。
 *
 * @package KashiwazakiSeoControlPanel
 * @var KSCP_Plugin $core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kscp_m    = $core->manifest->get();
$kscp_next = wp_next_scheduled( KSCP_CRON_HOOK );
?>
<h2><?php echo esc_html__( '接続診断', 'kashiwazaki-seo-control-panel' ); ?></h2>
<table class="widefat striped kscp-diag">
	<tbody>
		<tr>
			<th><?php echo esc_html__( 'マニフェスト URL', 'kashiwazaki-seo-control-panel' ); ?></th>
			<td><code><?php echo esc_html( $core->manifest->get_url() ); ?></code></td>
		</tr>
		<tr>
			<th><?php echo esc_html__( 'マニフェスト取得', 'kashiwazaki-seo-control-panel' ); ?></th>
			<td>
				<?php if ( ! empty( $kscp_m['items'] ) ) : ?>
					<span class="kscp-ok">&#10003; <?php echo esc_html__( '正常', 'kashiwazaki-seo-control-panel' ); ?></span>
					<?php
					printf(
						/* translators: %d: item count */
						' ' . esc_html__( '（%d 件）', 'kashiwazaki-seo-control-panel' ),
						count( $kscp_m['items'] )
					);
					?>
				<?php else : ?>
					<span class="kscp-ng">&#10007; <?php echo esc_html__( '取得できませんでした', 'kashiwazaki-seo-control-panel' ); ?></span>
					<?php if ( ! empty( $kscp_m['error'] ) ) : ?>
						<code><?php echo esc_html( $kscp_m['error'] ); ?></code>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><?php echo esc_html__( 'マニフェスト生成日時', 'kashiwazaki-seo-control-panel' ); ?></th>
			<td>
				<?php
				echo ! empty( $kscp_m['generated_at'] )
					? esc_html( $kscp_m['generated_at'] )
					: '&mdash;';
				?>
			</td>
		</tr>
		<tr>
			<th><?php echo esc_html__( '次回自動チェック', 'kashiwazaki-seo-control-panel' ); ?></th>
			<td>
				<?php
				echo $kscp_next
					? esc_html( wp_date( 'Y-m-d H:i', (int) $kscp_next ) )
					: esc_html__( '未スケジュール', 'kashiwazaki-seo-control-panel' );
				?>
			</td>
		</tr>
	</tbody>
</table>

<p class="kscp-note">
	<?php echo esc_html__( '本プラグインは GitHub API を個別に呼び出さず、作者が公開する単一のマニフェスト（4時間ごとに自動更新）を取得して最新版を判定します。アクセストークンは不要です。', 'kashiwazaki-seo-control-panel' ); ?>
</p>

<p class="kscp-diag-actions">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="kscp_recheck" />
		<?php wp_nonce_field( 'kscp_recheck' ); ?>
		<button type="submit" class="button button-primary"><?php echo esc_html__( 'マニフェストを再取得して再チェック', 'kashiwazaki-seo-control-panel' ); ?></button>
	</form>
</p>
