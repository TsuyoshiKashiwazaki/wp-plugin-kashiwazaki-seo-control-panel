<?php
/**
 * ニュースタブ（D12 作者ニュース最新6件・サムネ付き）。
 *
 * @package KashiwazakiSeoControlPanel
 * @var KSCP_Plugin $core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kscp_news = $core->news->get_items();
?>
<div class="kscp-news-head">
	<h2><?php echo esc_html__( '柏崎剛 ニュース', 'kashiwazaki-seo-control-panel' ); ?></h2>
	<a class="button button-secondary" href="<?php echo esc_url( KSCP_NEWS_URL ); ?>" target="_blank" rel="noopener noreferrer">
		<?php echo esc_html__( 'すべてのニュースを見る', 'kashiwazaki-seo-control-panel' ); ?>
	</a>
</div>

<?php if ( empty( $kscp_news ) ) : ?>
	<?php if ( empty( $core->settings->get( 'news_enabled', 1 ) ) ) : ?>
		<p class="kscp-empty"><?php echo esc_html__( 'ニュースの取得は設定で無効化されています。', 'kashiwazaki-seo-control-panel' ); ?></p>
	<?php else : ?>
		<p class="kscp-empty"><?php echo esc_html__( 'ニュースを取得できませんでした。時間をおいて再度お試しください。', 'kashiwazaki-seo-control-panel' ); ?></p>
	<?php endif; ?>
<?php else : ?>
<div class="kscp-news-grid">
	<?php foreach ( $kscp_news as $kscp_post ) : ?>
		<article class="kscp-news-card">
			<a href="<?php echo esc_url( $kscp_post['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="kscp-news-thumb">
				<img src="<?php echo esc_url( $kscp_post['thumbnail'] ); ?>"
					alt="<?php echo esc_attr( $kscp_post['title'] ); ?>" loading="lazy" />
			</a>
			<div class="kscp-news-body">
				<?php if ( ! empty( $kscp_post['date'] ) ) : ?>
					<time class="kscp-news-date" datetime="<?php echo esc_attr( $kscp_post['date'] ); ?>">
						<?php echo esc_html( wp_date( 'Y-m-d', strtotime( $kscp_post['date'] ) ) ); ?>
					</time>
				<?php endif; ?>
				<h3 class="kscp-news-title">
					<a href="<?php echo esc_url( $kscp_post['url'] ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $kscp_post['title'] ); ?>
					</a>
				</h3>
				<?php if ( ! empty( $kscp_post['excerpt'] ) ) : ?>
					<p class="kscp-news-excerpt"><?php echo esc_html( $kscp_post['excerpt'] ); ?></p>
				<?php endif; ?>
			</div>
		</article>
	<?php endforeach; ?>
</div>
<?php endif; ?>
