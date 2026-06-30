<?php
/**
 * 管理画面フッタ。
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	</div><!-- .kscp-tab-content -->

	<p class="kscp-footer">
		<a href="<?php echo esc_url( 'https://www.tsuyoshikashiwazaki.jp/profile/' ); ?>" target="_blank" rel="noopener noreferrer">
			<?php echo esc_html__( '柏崎剛 (Tsuyoshi Kashiwazaki)', 'kashiwazaki-seo-control-panel' ); ?>
		</a>
		&nbsp;|&nbsp;
		<a href="<?php echo esc_url( KSCP_AUTHOR_SITE ); ?>" target="_blank" rel="noopener noreferrer">
			<?php echo esc_html( KSCP_AUTHOR_SITE ); ?>
		</a>
	</p>
</div><!-- .wrap -->
