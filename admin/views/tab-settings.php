<?php
/**
 * 通知・設定タブ（通知 / マニフェスト URL・キャッシュ / cron / プライバシー）。
 *
 * @package KashiwazakiSeoControlPanel
 * @var KSCP_Plugin $core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kscp_s        = $core->settings->all();
$kscp_intervals = array(
	'hourly'        => __( '毎時', 'kashiwazaki-seo-control-panel' ),
	'kscp_sixhours' => __( '6時間ごと', 'kashiwazaki-seo-control-panel' ),
	'daily'         => __( '日次', 'kashiwazaki-seo-control-panel' ),
	'kscp_weekly'   => __( '週次', 'kashiwazaki-seo-control-panel' ),
);
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kscp-settings-form">
	<input type="hidden" name="action" value="kscp_save_settings" />
	<?php wp_nonce_field( 'kscp_save_settings' ); ?>

	<h2><?php echo esc_html__( 'メール通知', 'kashiwazaki-seo-control-panel' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php echo esc_html__( 'メール通知', 'kashiwazaki-seo-control-panel' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="kscp[email_enabled]" value="1" <?php checked( $kscp_s['email_enabled'], 1 ); ?> />
					<?php echo esc_html__( '更新を検知したらメールで通知する', 'kashiwazaki-seo-control-panel' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="kscp-email"><?php echo esc_html__( '通知先メールアドレス', 'kashiwazaki-seo-control-panel' ); ?></label></th>
			<td>
				<input type="email" id="kscp-email" class="regular-text" name="kscp[email_recipient]"
					value="<?php echo esc_attr( $kscp_s['email_recipient'] ); ?>" />
			</td>
		</tr>
		<tr>
			<th scope="row"><?php echo esc_html__( 'セキュリティ更新', 'kashiwazaki-seo-control-panel' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="kscp[email_security_instant]" value="1" <?php checked( $kscp_s['email_security_instant'], 1 ); ?> />
					<?php echo esc_html__( 'セキュリティ更新は優先して即時通知する', 'kashiwazaki-seo-control-panel' ); ?>
				</label>
				<p class="description"><?php echo esc_html__( '日次・週次ダイジェストは将来バージョンで対応予定です。', 'kashiwazaki-seo-control-panel' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php echo esc_html__( 'チェック頻度', 'kashiwazaki-seo-control-panel' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="kscp-interval"><?php echo esc_html__( '自動チェック間隔', 'kashiwazaki-seo-control-panel' ); ?></label></th>
			<td>
				<select id="kscp-interval" name="kscp[cron_interval]">
					<?php foreach ( $kscp_intervals as $kscp_k => $kscp_v ) : ?>
						<option value="<?php echo esc_attr( $kscp_k ); ?>" <?php selected( $kscp_s['cron_interval'], $kscp_k ); ?>>
							<?php echo esc_html( $kscp_v ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>

	<h2><?php echo esc_html__( 'データソース', 'kashiwazaki-seo-control-panel' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="kscp-manifest"><?php echo esc_html__( 'マニフェスト URL', 'kashiwazaki-seo-control-panel' ); ?></label></th>
			<td>
				<input type="url" id="kscp-manifest" class="regular-text code" name="kscp[manifest_url]"
					value="<?php echo esc_attr( $kscp_s['manifest_url'] ); ?>" placeholder="<?php echo esc_attr( KSCP_MANIFEST_URL ); ?>" />
				<p class="description"><?php echo esc_html__( '全プラグイン/テーマの最新版情報をまとめた単一 JSON の URL。空欄なら既定（作者が公開・4時間ごとに自動更新）を使用します。アクセストークンは不要です。', 'kashiwazaki-seo-control-panel' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="kscp-ttl"><?php echo esc_html__( 'キャッシュ保持時間（秒）', 'kashiwazaki-seo-control-panel' ); ?></label></th>
			<td>
				<input type="number" id="kscp-ttl" class="small-text" name="kscp[cache_ttl]" min="3600" max="86400"
					value="<?php echo esc_attr( (int) $kscp_s['cache_ttl'] ); ?>" />
				<p class="description"><?php echo esc_html__( '3600〜86400 秒（1時間〜1日）。', 'kashiwazaki-seo-control-panel' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php echo esc_html__( 'プライバシー（外部通信）', 'kashiwazaki-seo-control-panel' ); ?></h2>
	<p class="description kscp-privacy-note">
		<?php echo esc_html__( '本プラグインは次の外部サーバへ通信します: マニフェスト配信元（既定 raw.githubusercontent.com、最新版情報の取得）、www.tsuyoshikashiwazaki.jp（ニュースの取得）。送信するのは取得リクエストのみで、サイトの個人情報やアクセストークンは送信しません。', 'kashiwazaki-seo-control-panel' ); ?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php echo esc_html__( '作者ニュース取得', 'kashiwazaki-seo-control-panel' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="kscp[news_enabled]" value="1" <?php checked( $kscp_s['news_enabled'], 1 ); ?> />
					<?php echo esc_html__( 'www.tsuyoshikashiwazaki.jp からニュースを取得する', 'kashiwazaki-seo-control-panel' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<h2><?php echo esc_html__( '監視対象の除外', 'kashiwazaki-seo-control-panel' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="kscp-excluded"><?php echo esc_html__( '除外する slug', 'kashiwazaki-seo-control-panel' ); ?></label></th>
			<td>
				<textarea id="kscp-excluded" class="large-text code" rows="3" name="kscp[excluded_slugs]" placeholder="wp-plugin-foo&#10;wp-theme-bar"><?php echo esc_textarea( implode( "\n", (array) $kscp_s['excluded_slugs'] ) ); ?></textarea>
				<p class="description"><?php echo esc_html__( '監視から外す slug を改行またはカンマ区切りで入力（本体 Kashiwazaki SEO ControlPanel は除外できません）。', 'kashiwazaki-seo-control-panel' ); ?></p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( '設定を保存', 'kashiwazaki-seo-control-panel' ) ); ?>
</form>
