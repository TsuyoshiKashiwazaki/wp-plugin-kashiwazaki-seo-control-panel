<?php
/**
 * 更新一覧タブ（D6 ステータステーブル / D2 / D8）。
 *
 * @package KashiwazakiSeoControlPanel
 * @var KSCP_Plugin $core
 * @var array       $status
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kscp_summary = $core->checker->summarize( $status );
$kscp_items   = isset( $status['items'] ) ? $status['items'] : array();

$kscp_status_label = array(
	'update-available' => __( '更新あり', 'kashiwazaki-seo-control-panel' ),
	'up-to-date'       => __( '最新', 'kashiwazaki-seo-control-panel' ),
	'not-installed'    => __( '未インストール', 'kashiwazaki-seo-control-panel' ),
	'unknown'          => __( '取得失敗', 'kashiwazaki-seo-control-panel' ),
);
?>

<div class="kscp-summary-bar">
	<span class="kscp-chip kscp-chip-update"><?php printf( esc_html__( '更新あり: %d', 'kashiwazaki-seo-control-panel' ), (int) $kscp_summary['updates'] ); ?></span>
	<span class="kscp-chip kscp-chip-security"><?php printf( esc_html__( 'セキュリティ: %d', 'kashiwazaki-seo-control-panel' ), (int) $kscp_summary['security'] ); ?></span>
	<span class="kscp-chip"><?php printf( esc_html__( '監視対象: %d', 'kashiwazaki-seo-control-panel' ), (int) $kscp_summary['total'] ); ?></span>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kscp-recheck-form">
		<input type="hidden" name="action" value="kscp_recheck" />
		<?php wp_nonce_field( 'kscp_recheck' ); ?>
		<button type="submit" class="button button-secondary"><?php echo esc_html__( '今すぐチェック', 'kashiwazaki-seo-control-panel' ); ?></button>
	</form>

	<?php if ( ! empty( $status['generated_at'] ) ) : ?>
		<span class="kscp-lastcheck">
			<?php
			printf(
				/* translators: %s: datetime */
				esc_html__( '最終チェック: %s', 'kashiwazaki-seo-control-panel' ),
				esc_html( wp_date( 'Y-m-d H:i', (int) $status['generated_at'] ) )
			);
			?>
		</span>
	<?php endif; ?>
</div>

<?php if ( empty( $kscp_items ) ) : ?>
	<p class="kscp-empty"><?php echo esc_html__( 'まだチェックを実行していません。「今すぐチェック」を押してください。', 'kashiwazaki-seo-control-panel' ); ?></p>
<?php else : ?>
<table class="wp-list-table widefat fixed striped kscp-table">
	<thead>
		<tr>
			<th scope="col"><?php echo esc_html__( '名前', 'kashiwazaki-seo-control-panel' ); ?></th>
			<th scope="col"><?php echo esc_html__( '種別', 'kashiwazaki-seo-control-panel' ); ?></th>
			<th scope="col"><?php echo esc_html__( '導入済み', 'kashiwazaki-seo-control-panel' ); ?></th>
			<th scope="col"><?php echo esc_html__( '最新版', 'kashiwazaki-seo-control-panel' ); ?></th>
			<th scope="col"><?php echo esc_html__( '最終更新日', 'kashiwazaki-seo-control-panel' ); ?></th>
			<th scope="col"><?php echo esc_html__( 'ステータス', 'kashiwazaki-seo-control-panel' ); ?></th>
			<th scope="col"><?php echo esc_html__( '詳細', 'kashiwazaki-seo-control-panel' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $kscp_items as $kscp_it ) : ?>
			<?php
			$kscp_row_class = array();
			if ( ! empty( $kscp_it['is_self'] ) ) {
				$kscp_row_class[] = 'kscp-row-self';
			}
			if ( 'update-available' === $kscp_it['status'] ) {
				$kscp_row_class[] = $kscp_it['is_security'] ? 'kscp-row-security' : 'kscp-row-update';
			}
			?>
			<tr class="<?php echo esc_attr( implode( ' ', $kscp_row_class ) ); ?>">
				<td class="kscp-cell-name">
					<strong><?php echo esc_html( $kscp_it['name'] ); ?></strong>
					<?php if ( ! empty( $kscp_it['is_self'] ) ) : ?>
						<span class="kscp-badge kscp-badge-self"><?php echo esc_html__( '本体', 'kashiwazaki-seo-control-panel' ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $kscp_it['is_security'] ) && 'update-available' === $kscp_it['status'] ) : ?>
						<span class="kscp-badge kscp-badge-security"><?php echo esc_html__( '⚠ セキュリティ', 'kashiwazaki-seo-control-panel' ); ?></span>
					<?php endif; ?>
					<div class="kscp-slug"><?php echo esc_html( $kscp_it['slug'] ); ?></div>
				</td>
				<td><?php echo 'theme' === $kscp_it['type'] ? esc_html__( 'テーマ', 'kashiwazaki-seo-control-panel' ) : esc_html__( 'プラグイン', 'kashiwazaki-seo-control-panel' ); ?></td>
				<td><?php echo '' !== $kscp_it['installed_version'] ? esc_html( $kscp_it['installed_version'] ) : '&mdash;'; ?></td>
				<td><?php echo '' !== $kscp_it['latest_version'] ? esc_html( $kscp_it['latest_version'] ) : '&mdash;'; ?></td>
				<td>
					<?php
					$kscp_ts = ( '' !== $kscp_it['last_updated'] ) ? strtotime( $kscp_it['last_updated'] ) : false;
					echo $kscp_ts ? esc_html( wp_date( 'Y-m-d', $kscp_ts ) ) : '&mdash;';
					?>
				</td>
				<td>
					<span class="kscp-status kscp-status-<?php echo esc_attr( $kscp_it['status'] ); ?>">
						<?php echo esc_html( isset( $kscp_status_label[ $kscp_it['status'] ] ) ? $kscp_status_label[ $kscp_it['status'] ] : $kscp_it['status'] ); ?>
					</span>
				</td>
				<td>
					<?php if ( ! empty( $kscp_it['html_url'] ) ) : ?>
						<a href="<?php echo esc_url( $kscp_it['html_url'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html__( 'GitHub で見る', 'kashiwazaki-seo-control-panel' ); ?>
						</a>
					<?php else : ?>
						&mdash;
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<p class="kscp-note">
	<?php echo esc_html__( '※ 本バージョンは更新の検知・通知・GitHub への誘導までを行います。監視対象の自動更新は行いません（本体 Kashiwazaki SEO ControlPanel 自身の更新を除く）。', 'kashiwazaki-seo-control-panel' ); ?>
</p>
<?php endif; ?>
