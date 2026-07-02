<?php
/**
 * 作者ニュースフィード（D12）。
 *
 * https://www.tsuyoshikashiwazaki.jp/news/ の最新 6 件をサムネイル付きで取得。
 * 画像はプラグインに同梱せず、作者サイト上の画像 URL を参照する。
 * 取得は WordPress REST API → RSS の順でフォールバック。結果はキャッシュ。
 *
 * @package KashiwazakiSeoControlPanel
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KSCP_News {

	const TRANSIENT   = 'kscp_news_items';
	const REST_URL    = 'https://www.tsuyoshikashiwazaki.jp/wp-json/wp/v2/news?per_page=6&_embed=1';
	const FEED_URL    = 'https://www.tsuyoshikashiwazaki.jp/news/feed/';
	const FALLBACK_IMG = 'https://www.tsuyoshikashiwazaki.jp/wp-content/uploads/logo.png';
	const COUNT       = 6;

	/** @var KSCP_Settings */
	private $settings;

	/**
	 * @param KSCP_Settings $settings 設定。
	 */
	public function __construct( KSCP_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * ニュース最新 6 件を取得。
	 *
	 * @param bool $force キャッシュ無視。
	 * @return array items（title/url/date/thumbnail/excerpt）。
	 */
	public function get_items( $force = false ) {
		if ( empty( $this->settings->get( 'news_enabled', 1 ) ) ) {
			return array();
		}
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$items = $this->fetch_via_rest();
		if ( empty( $items ) ) {
			$items = $this->fetch_via_feed();
		}

		$items = array_slice( $items, 0, self::COUNT );
		// 取得失敗（空）は一時障害の可能性が高いので短い TTL で再試行させる。
		$ttl = empty( $items ) ? 10 * MINUTE_IN_SECONDS : 6 * HOUR_IN_SECONDS;
		set_transient( self::TRANSIENT, $items, $ttl );
		return $items;
	}

	/**
	 * 手動リフレッシュ用キャッシュ削除。
	 */
	public function flush() {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * WordPress REST API から取得（_embed でサムネイル取得）。
	 *
	 * @return array
	 */
	private function fetch_via_rest() {
		$res = wp_remote_get( self::REST_URL, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return array();
		}
		$posts = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $posts ) ) {
			return array();
		}
		$items = array();
		foreach ( $posts as $p ) {
			$title = isset( $p['title']['rendered'] ) ? wp_strip_all_tags( $p['title']['rendered'] ) : '';
			$url   = isset( $p['link'] ) ? esc_url_raw( $p['link'] ) : '';
			if ( '' === $title || '' === $url ) {
				continue;
			}
			$thumb = $this->extract_rest_thumbnail( $p );
			$items[] = array(
				'title'     => $title,
				'url'       => $url,
				'date'      => isset( $p['date'] ) ? sanitize_text_field( $p['date'] ) : '',
				'thumbnail' => $thumb,
				'excerpt'   => isset( $p['excerpt']['rendered'] ) ? wp_trim_words( wp_strip_all_tags( $p['excerpt']['rendered'] ), 30 ) : '',
			);
		}
		return $items;
	}

	/**
	 * REST レスポンスからサムネイル URL を抽出。
	 *
	 * @param array $post 投稿データ。
	 * @return string
	 */
	private function extract_rest_thumbnail( $post ) {
		// _embedded.wp:featuredmedia[0].source_url（または media_details の sizes）。
		if ( ! empty( $post['_embedded']['wp:featuredmedia'][0] ) ) {
			$media = $post['_embedded']['wp:featuredmedia'][0];
			if ( ! empty( $media['media_details']['sizes']['medium']['source_url'] ) ) {
				return esc_url_raw( $media['media_details']['sizes']['medium']['source_url'] );
			}
			if ( ! empty( $media['source_url'] ) ) {
				return esc_url_raw( $media['source_url'] );
			}
		}
		// 本文中の最初の画像。
		if ( ! empty( $post['content']['rendered'] ) && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $post['content']['rendered'], $m ) ) {
			return esc_url_raw( $m[1] );
		}
		return self::FALLBACK_IMG;
	}

	/**
	 * RSS フィードから取得（REST 失敗時）。
	 *
	 * @return array
	 */
	private function fetch_via_feed() {
		if ( ! function_exists( 'fetch_feed' ) ) {
			include_once ABSPATH . WPINC . '/feed.php';
		}
		$feed = fetch_feed( self::FEED_URL );
		if ( is_wp_error( $feed ) ) {
			return array();
		}
		$max   = $feed->get_item_quantity( self::COUNT );
		$items = array();
		foreach ( $feed->get_items( 0, $max ) as $item ) {
			$thumb   = $this->pick_feed_image( $item );
			$items[] = array(
				'title'     => wp_strip_all_tags( $item->get_title() ),
				'url'       => esc_url_raw( $item->get_permalink() ),
				'date'      => $item->get_date( 'c' ),
				'thumbnail' => $thumb,
				'excerpt'   => wp_trim_words( wp_strip_all_tags( $item->get_description() ), 30 ),
			);
		}
		return $items;
	}

	/**
	 * RSS アイテムから「本物」のサムネイル画像を選ぶ。
	 *
	 * RSS の enclosure / media:thumbnail を優先し、無ければ本文中の最初の画像を使う。
	 * ただし絵文字・アイコン（s.w.org の emoji 等）や data URI は除外する。
	 *
	 * @param object $item SimplePie アイテム。
	 * @return string 画像 URL（見つからなければフォールバック）。
	 */
	private function pick_feed_image( $item ) {
		// 1) enclosure（RSS 標準の添付画像）。
		$enc = $item->get_enclosure();
		if ( $enc ) {
			$link = $enc->get_link();
			if ( $link && $this->is_real_image_url( $link ) ) {
				return esc_url_raw( $link );
			}
		}
		// 2) 本文中の img を順に走査し、最初の「本物」画像を採用。
		$content = (string) $item->get_content();
		if ( $content && preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches ) ) {
			foreach ( $matches[1] as $src ) {
				if ( $this->is_real_image_url( $src ) ) {
					return esc_url_raw( $src );
				}
			}
		}
		return self::FALLBACK_IMG;
	}

	/**
	 * 絵文字・アイコン・data URI 等を除外し、記事画像として妥当な URL か判定。
	 *
	 * @param string $url 画像 URL。
	 * @return bool
	 */
	private function is_real_image_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || 0 === stripos( $url, 'data:' ) ) {
			return false;
		}
		// WordPress 絵文字 CDN（s.w.org / wp.com の emoji）やパスに emoji を含むものを除外。
		if ( preg_match( '#//s\.w\.org/#i', $url ) || stripos( $url, '/emoji/' ) !== false ) {
			return false;
		}
		return (bool) wp_http_validate_url( $url );
	}
}
