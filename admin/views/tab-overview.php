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

// 表示順: 本体 → セキュリティ更新 → その他更新 → 最新 → 未インストール/取得失敗。名前昇順。
$kscp_rank = function ( $it ) {
	if ( ! empty( $it['is_self'] ) ) {
		return 0;
	}
	if ( 'update-available' === $it['status'] ) {
		return ! empty( $it['is_security'] ) ? 1 : 2;
	}
	if ( 'up-to-date' === $it['status'] ) {
		return 3;
	}
	return 4;
};
usort(
	$kscp_items,
	function ( $a, $b ) use ( $kscp_rank ) {
		$ra = $kscp_rank( $a );
		$rb = $kscp_rank( $b );
		if ( $ra !== $rb ) {
			return $ra - $rb;
		}
		return strcasecmp( $a['name'], $b['name'] );
	}
);

$kscp_status_pill = array(
	'up-to-date'    => array( 'label' => __( '最新', 'kashiwazaki-seo-control-panel' ), 'mod' => 'ok' ),
	'not-installed' => array( 'label' => __( '未インストール', 'kashiwazaki-seo-control-panel' ), 'mod' => 'none' ),
	'unknown'       => array( 'label' => __( '取得失敗', 'kashiwazaki-seo-control-panel' ), 'mod' => 'unknown' ),
);
?>

<div class="kscp-ov">

	<div class="kscp-stats">
		<div class="kscp-stat kscp-stat--update">
			<span class="kscp-stat-num"><?php echo esc_html( number_format_i18n( (int) $kscp_summary['updates'] ) ); ?></span>
			<span class="kscp-stat-lbl"><?php echo esc_html__( '更新あり', 'kashiwazaki-seo-control-panel' ); ?></span>
		</div>
		<div class="kscp-stat kscp-stat--sec">
			<span class="kscp-stat-num"><?php echo esc_html( number_format_i18n( (int) $kscp_summary['security'] ) ); ?></span>
			<span class="kscp-stat-lbl"><?php echo esc_html__( 'セキュリティ', 'kashiwazaki-seo-control-panel' ); ?></span>
		</div>
		<div class="kscp-stat kscp-stat--total">
			<span class="kscp-stat-num"><?php echo esc_html( number_format_i18n( (int) $kscp_summary['total'] ) ); ?></span>
			<span class="kscp-stat-lbl"><?php echo esc_html__( '監視対象', 'kashiwazaki-seo-control-panel' ); ?></span>
		</div>
		<div class="kscp-stat-side">
			<?php if ( ! empty( $status['generated_at'] ) ) : ?>
				<span class="kscp-lastcheck">
					<?php
					printf(
						/* translators: %s: datetime */
						esc_html__( '最終チェック %s', 'kashiwazaki-seo-control-panel' ),
						esc_html( wp_date( 'Y-m-d H:i', (int) $status['generated_at'] ) )
					);
					?>
				</span>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kscp-recheck-form">
				<input type="hidden" name="action" value="kscp_recheck" />
				<?php wp_nonce_field( 'kscp_recheck' ); ?>
				<button type="submit" class="button button-primary">
					<span class="dashicons dashicons-update"></span>
					<?php echo esc_html__( '今すぐチェック', 'kashiwazaki-seo-control-panel' ); ?>
				</button>
			</form>
		</div>
	</div>

	<?php if ( empty( $kscp_items ) ) : ?>
		<p class="kscp-empty"><?php echo esc_html__( 'まだチェックを実行していません。「今すぐチェック」を押してください。', 'kashiwazaki-seo-control-panel' ); ?></p>
	<?php else : ?>
	<table class="kscp-tbl">
		<thead>
			<tr>
				<th scope="col" class="kscp-col-name"><?php echo esc_html__( '名前', 'kashiwazaki-seo-control-panel' ); ?></th>
				<th scope="col" class="kscp-col-ver"><?php echo esc_html__( 'バージョン', 'kashiwazaki-seo-control-panel' ); ?></th>
				<th scope="col" class="kscp-col-state"><?php echo esc_html__( '状態', 'kashiwazaki-seo-control-panel' ); ?></th>
				<th scope="col" class="kscp-col-date"><?php echo esc_html__( '最新版の公開日', 'kashiwazaki-seo-control-panel' ); ?></th>
				<th scope="col" class="kscp-col-link"></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $kscp_items as $kscp_it ) :
				$kscp_is_update = ( 'update-available' === $kscp_it['status'] );
				$kscp_row_mod   = 'other';
				if ( $kscp_is_update ) {
					$kscp_row_mod = ! empty( $kscp_it['is_security'] ) ? 'security' : 'update';
				} elseif ( 'up-to-date' === $kscp_it['status'] ) {
					$kscp_row_mod = 'ok';
				}
				?>
				<tr class="kscp-r kscp-r--<?php echo esc_attr( $kscp_row_mod ); ?><?php echo ! empty( $kscp_it['is_self'] ) ? ' kscp-r--self' : ''; ?>">
					<td class="kscp-col-name">
						<span class="kscp-ico dashicons <?php echo 'theme' === $kscp_it['type'] ? 'dashicons-admin-appearance' : 'dashicons-admin-plugins'; ?>"></span>
						<span class="kscp-name-wrap">
							<span class="kscp-name">
								<?php echo esc_html( $kscp_it['name'] ); ?>
								<?php if ( ! empty( $kscp_it['is_self'] ) ) : ?>
									<span class="kscp-badge kscp-badge-self"><?php echo esc_html__( '本体', 'kashiwazaki-seo-control-panel' ); ?></span>
								<?php endif; ?>
							</span>
							<span class="kscp-slug"><?php echo esc_html( $kscp_it['slug'] ); ?></span>
						</span>
					</td>
					<td class="kscp-col-ver">
						<?php
						$kscp_from = ( '' !== $kscp_it['installed_version'] ) ? $kscp_it['installed_version'] : '—';
						$kscp_to   = ( '' !== $kscp_it['latest_version'] ) ? $kscp_it['latest_version'] : '—';
						if ( $kscp_is_update ) :
							?>
							<span class="kscp-v-old"><?php echo esc_html( $kscp_from ); ?></span>
							<span class="kscp-v-arrow dashicons dashicons-arrow-right-alt2"></span>
							<span class="kscp-v-new"><?php echo esc_html( $kscp_to ); ?></span>
						<?php else : ?>
							<span class="kscp-v-cur"><?php echo esc_html( '—' !== $kscp_from ? $kscp_from : $kscp_to ); ?></span>
						<?php endif; ?>
					</td>
					<td class="kscp-col-state">
						<?php if ( $kscp_is_update ) : ?>
							<span class="kscp-chips">
								<?php foreach ( KSCP_Update_Checker::update_type_badges( $kscp_it ) as $kscp_badge ) : ?>
									<span class="kscp-chip kscp-chip--<?php echo esc_attr( $kscp_badge['mod'] ); ?>"><?php echo esc_html( $kscp_badge['label'] ); ?></span>
								<?php endforeach; ?>
							</span>
						<?php else : ?>
							<?php $kscp_pill = isset( $kscp_status_pill[ $kscp_it['status'] ] ) ? $kscp_status_pill[ $kscp_it['status'] ] : array( 'label' => $kscp_it['status'], 'mod' => 'none' ); ?>
							<span class="kscp-pill kscp-pill--<?php echo esc_attr( $kscp_pill['mod'] ); ?>"><?php echo esc_html( $kscp_pill['label'] ); ?></span>
						<?php endif; ?>
					</td>
					<td class="kscp-col-date">
						<?php
						$kscp_ts = ( '' !== $kscp_it['last_updated'] ) ? strtotime( $kscp_it['last_updated'] ) : false;
						echo $kscp_ts ? esc_html( wp_date( 'Y-m-d', $kscp_ts ) ) : '&mdash;';
						?>
					</td>
					<td class="kscp-col-link">
						<?php if ( ! empty( $kscp_it['html_url'] ) ) : ?>
							<a href="<?php echo esc_url( $kscp_it['html_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="kscp-gh" title="<?php echo esc_attr__( 'GitHub で見る', 'kashiwazaki-seo-control-panel' ); ?>">
								<span class="dashicons dashicons-external"></span>
							</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="kscp-note">
		<?php echo esc_html__( '※ 状態欄の色は更新の種類を表します（赤=セキュリティ / 黄=バグ修正 / 青=機能）。本バージョンは検知・通知・GitHub への誘導を行い、監視対象の自動更新は行いません（本体自身の更新を除く）。', 'kashiwazaki-seo-control-panel' ); ?>
	</p>
	<?php endif; ?>

</div>
