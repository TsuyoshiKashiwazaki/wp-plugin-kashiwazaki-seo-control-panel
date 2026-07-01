# Kashiwazaki SEO ControlPanel

![Version](https://img.shields.io/badge/version-1.0.1-blue.svg)
![License](https://img.shields.io/badge/license-GPLv2%2B-green.svg)

柏崎剛が公開する `wp-` 付きプラグイン/テーマの最新版を**単一のマニフェスト**から取得し、インストール済みのバージョンが古い場合に管理画面とメールで通知する、作者専用のコントロールパネルです。**アクセストークンは不要**で、API レート制限の影響も受けません。本プラグイン自身の自己更新にも対応します。

## 特徴

- 単一マニフェスト（全 `wp-` プラグイン/テーマの最新版をまとめた JSON）を 1 回取得するだけ。トークン不要・レート制限なし。
- インストール済みバージョンと最新版を照合し、更新があれば管理画面・メールで通知。
- セキュリティ更新を強調表示。
- インストールディレクトリ名と公開リポジトリ名の表記揺れを自動で吸収。
- マニフェスト取得に失敗しても、直前の良好なデータを保持（一覧が壊れない）。
- タブ式コントロールパネル（更新一覧・ニュース・設定・診断）。
- 本プラグイン自身の自己更新に対応。

## 動作環境

- WordPress 5.8 以上
- PHP 7.2 以上

## インストール

1. 本リポジトリの内容を `wp-content/plugins/wp-plugin-kashiwazaki-seo-control-panel/` に配置します。
2. WordPress 管理画面の「プラグイン」から **Kashiwazaki SEO ControlPanel** を有効化します。
3. 管理メニューの **Kashiwazaki SEO ControlPanel** から各タブを利用できます。

## マニフェストについて

監視データは、別リポジトリ [`kscp-assets`](https://github.com/TsuyoshiKashiwazaki/kscp-assets) が配信する単一の `manifest.json` から取得します。このマニフェストは `kscp-assets` の GitHub Actions が 4 時間ごとに自動生成・更新します。

取得元 URL は既定で `https://raw.githubusercontent.com/TsuyoshiKashiwazaki/kscp-assets/main/manifest.json` を指しますが、プラグインの「設定」タブから変更できます。

## 使い方

- **更新一覧**: 監視対象の最新版・導入版・状態（最新/更新あり/未導入）を一覧表示します。
- **設定**: マニフェスト URL、キャッシュ保持時間、メール通知、チェック間隔、プライバシートグルを設定できます。
- **診断**: マニフェストの取得状況と次回チェック予定を確認できます。

## ライセンス

GPLv2 or later. 詳細は [LICENSE](LICENSE) を参照してください。
