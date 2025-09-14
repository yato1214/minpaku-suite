# Minpaku Channel Sync

WordPress plugin for iCal(ICS) import/export functionality for stock synchronization (mini channel manager).

**Version:** 0.2.0

## 機能一覧

### ICSインポート機能
- 外部カレンダー（Airbnb、Booking.com等）からのICSファイルを定期的に取得
- 予約情報を指定されたWordPress投稿に同期
- 設定画面でURL-投稿IDマッピングを管理
- 自動同期（hourly/2hours/6hours）とマニュアル同期に対応
- **304 Not Modified最適化**: 2回目以降の同期で変更がない場合はスキップ
- **条件付きGET**: If-Modified-Since/ETagヘッダーによる効率的な取得

### ICSエクスポート機能
- WordPress投稿の予約情報をICS形式でエクスポート
- `/ics/{post_id}.ics` のエンドポイントでアクセス可能
- RFC5545準拠の75バイト行折返し対応
- インライン表示/ダウンロード選択可能

### 管理機能
- 設定画面での各種パラメータ管理
- ログ機能（WARNING/ERROR要約表示）
- 同期結果の詳細表示（Added/Updated/Skipped/Not Modified）
- HTTPキャッシュのクリア機能
- WP-CLI対応（mappings/sync/logs）

## セットアップ

### 前提条件
- PHP 8.1以上
- WordPress環境
- Local by Flywheel（推奨）またはローカルWordPress環境

### Local by Flywheel での動かし方

1. **プラグインのインストール**
```bash
# プラグインディレクトリに配置
cd /path/to/your-site/app/public/wp-content/plugins/
# プラグインファイルを配置
```

2. **プラグインの有効化**
- WordPress管理画面 > プラグイン > 「Minpaku Channel Sync」を有効化

3. **基本設定**
- 管理画面 > 設定 > Minpaku Sync
- Target Post Type: 対象となる投稿タイプのスラッグ（デフォルト: property）

### テスト用ICSファイルの配置例

uploads フォルダにテスト用ICSファイルを作成：

```bash
# Local環境のuploadsディレクトリ
cd /Users/yourname/Local Sites/your-site/app/public/wp-content/uploads/

# テスト用ICSファイルを作成
cat > test-calendar.ics << 'EOF'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//Test Calendar//EN
BEGIN:VEVENT
DTSTART:20241201T140000Z
DTEND:20241201T150000Z
SUMMARY:Test Booking 1
UID:test-123@example.com
END:VEVENT
BEGIN:VEVENT
DTSTART:20241202T100000Z
DTEND:20241202T120000Z
SUMMARY:Test Booking 2
UID:test-456@example.com
END:VEVENT
END:VCALENDAR
EOF
```

### URLマッピング例

設定画面でのマッピング設定：

| ICS URL | Post ID | 説明 |
|---------|---------|------|
| `https://your-site.local/wp-content/uploads/test-calendar.ics` | 123 | テスト用カレンダー |
| `https://example.com/airbnb-calendar.ics` | 456 | 実際のAirbnbカレンダー |

**設定手順:**
1. 管理画面 > 設定 > Minpaku Sync
2. 「ICS Import Mappings」セクションでURL と Post ID を入力
3. 「Add」ボタンで追加
4. 「変更を保存」

## WP-CLI の使用例

### マッピング一覧の確認
```bash
# テーブル形式で表示（デフォルト）
wp mcs mappings
wp mcs mappings list

# JSON形式で表示
wp mcs mappings list --format=json
```

### 同期の実行
```bash
# 全URLの同期実行
wp mcs sync
wp mcs sync --all

# 特定URLの同期
wp mcs sync --url="https://example.com/calendar.ics"

# 特定投稿IDの同期
wp mcs sync --post_id=123
```

### ログの確認
```bash
# 最新50件のログ表示
wp mcs logs

# 最新10件のログ表示
wp mcs logs --tail=10

# エラーレベルのみ表示
wp mcs logs --level=error

# 警告レベル以上を20件表示
wp mcs logs --tail=20 --level=warning
```

## 304最適化の動作

### HTTPキャッシュによる効率化

プラグインは外部ICSファイルの取得時に条件付きGETリクエストを使用して効率化を図ります：

1. **初回取得**: ICSファイルを完全取得し、ETag/Last-Modifiedをキャッシュ
2. **2回目以降**: If-None-Match/If-Modified-Sinceヘッダー付きでリクエスト
3. **304 Not Modified**: サーバーが「変更なし」を返した場合、パースをスキップ

### 同期結果での確認

同期実行後の結果で304最適化を確認できます：

```
Sync Results:
+---------------------+-------+---------+---------+------------------+--------+
| url                 | added | updated | skipped | skipped_not_modified | errors |
+---------------------+-------+---------+---------+------------------+--------+
| https://example.... | 0     | 0       | 0       | 1                | 0      |
| TOTAL               | 0     | 0       | 0       | 1                | 0      |
+---------------------+-------+---------+---------+------------------+--------+
```

- `skipped_not_modified`: 304 Not Modifiedでスキップされた回数
- 効率的な同期により、サーバー負荷とネットワーク使用量を削減

## HTTPキャッシュのクリア

### UI（設定画面）でのクリア
1. 管理画面 > 設定 > Minpaku Sync
2. 「Clear HTTP cache」ボタンをクリック
3. 確認ダイアログで「OK」
4. 成功メッセージが表示される

### コマンドラインでのクリア
```bash
# WP-CLIでオプションを直接削除
wp option delete mcs_http_cache

# 設定値の確認
wp option get mcs_http_cache
```

### キャッシュクリアが必要なケース
- 外部ICSファイルが強制的に再取得されない場合
- テスト環境でキャッシュをリセットしたい場合
- ETag/Last-Modifiedの情報に問題がある場合

## 404エラーが発生した場合の対処

ICSエンドポイント（`/ics/{post_id}.ics`）で404エラーが発生する場合：

### 1. パーマリンク設定の保存
1. WordPress管理画面 > 設定 > パーマリンク設定
2. 何も変更せずに「変更を保存」ボタンをクリック
3. これによりrewrite rulesが再生成されます

### 2. プラグイン設定での強制フラッシュ
1. 管理画面 > 設定 > Minpaku Sync
2. 「Flush Rewrite Rules」にチェックを入れて設定を保存
3. 保存後、チェックを外して再度保存

### 3. 手動でのrewrite rules確認
```php
// functions.phpまたはプラグイン内で確認
global $wp_rewrite;
var_dump($wp_rewrite->wp_rewrite_rules());
// 'ics/([0-9]+)\.ics/?$' => 'index.php?mcs_ics_post_id=$matches[1]' が含まれているか確認
```

## 取り込み・エクスポート検証手順

### インポート機能の検証

1. **テストICSファイルの準備**
```ics
BEGIN:VCALENDAR
VERSION:2.0
PRODID:Test
BEGIN:VEVENT
DTSTART:20241201T140000Z
DTEND:20241201T150000Z
SUMMARY:Test Booking
UID:test-123@example.com
END:VEVENT
END:VCALENDAR
```

2. **マッピング設定**
- 管理画面でICS URLと投稿IDのマッピングを設定
- テスト用の投稿を作成し、そのIDを使用

3. **同期実行と確認**
- 「Run manual sync now」で手動同期実行
- 投稿のカスタムフィールド `mcs_booked_slots` に予約情報が格納されているか確認
- ログで同期結果を確認（Added/Updated/Skipped/Not Modified/Errors）

### エクスポート機能の検証

1. **テストデータの投入**
```php
// 投稿にテスト用予約スロットを追加
$slots = [
    [1733068800, 1733072400, '', 'test-uid-1@example.com'], // 2024-12-01 14:00-15:00
    [1733155200, 1733158800, '', 'test-uid-2@example.com']  // 2024-12-02 14:00-15:00
];
update_post_meta($post_id, 'mcs_booked_slots', $slots);
```

2. **ICSエンドポイントのテスト**
- ブラウザで `/ics/{post_id}.ics` にアクセス
- ICSファイルがダウンロード/表示される
- 内容が正しいフォーマットで出力される

**wp-cliでのテスト例:**
```bash
# 投稿にテストデータを追加
wp post meta update 123 mcs_booked_slots '[[[1733068800,1733072400,"","test-uid-1@example.com"]]]' --format=json

# 投稿メタデータの確認
wp post meta get 123 mcs_booked_slots

# ICSエンドポイントの確認（curl使用）
curl -I "http://localhost:8888/ics/123.ics"
curl "http://localhost:8888/ics/123.ics"
```

**curlでのAPIテスト例:**
```bash
# ICSファイルのヘッダー確認
curl -I "http://localhost:8888/ics/123.ics"

# ICSファイルの内容取得
curl "http://localhost:8888/ics/123.ics"

# 外部ICSファイルの取得テスト
curl -L "https://example.com/calendar.ics" | head -20

# レスポンスタイムの計測
curl -w "@curl-format.txt" -o /dev/null -s "http://localhost:8888/ics/123.ics"
```

**curl-format.txt（レスポンス計測用）:**
```
     time_namelookup:  %{time_namelookup}\n
        time_connect:  %{time_connect}\n
     time_appconnect:  %{time_appconnect}\n
    time_pretransfer:  %{time_pretransfer}\n
       time_redirect:  %{time_redirect}\n
  time_starttransfer:  %{time_starttransfer}\n
                     ----------\n
          time_total:  %{time_total}\n
```

3. **外部カレンダーでの検証**
- Google Calendar、Outlook等でICS URLを購読
- 予約情報が正しく表示される

## 既知の制限と注意点

### 1. イベント更新の差分判定
- **UID優先マッチング**: イベントのUIDが存在する場合、UID による一意性判定を行う
- **フォールバック判定**: UIDがない場合、開始時刻と終了時刻による判定に切り替え
- **同一UID更新**: 同じUIDで開始・終了時刻が変更された場合、既存イベントが更新される
- **注意**: UIDの重複や不正な値がある場合、予期しない更新動作が発生する可能性

### 2. WordPress Cron実行の制限
- **低トラフィック問題**: 低トラフィックサイトではcronが実行されない場合があります
- **推奨対策**: サーバーcronで wp-cron.php を定期実行
- **手動実行**: `wp cron event run mcs_sync_event` でcronを手動実行可能

### 3. HTTPキャッシュの動作
- **キャッシュストレージ**: ETag/Last-Modified情報をWordPressオプションに保存
- **キャッシュサイズ**: 最大100URLまで自動制限（古いものから削除）
- **キャッシュクリア**: UI または CLI で手動クリア可能
- **304判定**: サーバー側の304 Not Modified レスポンスに依存

### 4. 大量データの処理制限
- **実行時間制限**: PHPの max_execution_time 制限に注意
- **メモリ制限**: 大量予約データは PHP memory_limit を調整
- **推奨対策**: 分割処理の検討

### 5. 外部ICSアクセスの制限
- **ファイアウォール**: HTTPS通信がブロックされる場合あり
- **SSL証明書**: 証明書検証エラーが発生する可能性
- **レート制限**: 外部サービスのAPI制限に注意
- **タイムアウト**: デフォルト30秒のHTTPタイムアウト

### 6. ICSエンドポイントのキャッシュ
- **CDN/キャッシュプラグイン**: ICSファイルがキャッシュされる可能性
- **リアルタイム要件**: キャッシュ除外設定が必要な場合あり
- **ヘッダー設定**: `nocache_headers()` は設定済み

### 7. 投稿タイプとパーマリンクの依存関係
- **公開設定**: カスタム投稿タイプの `public => true, publicly_queryable => true` が必要
- **パーマリンク**: プラグインの有効化・無効化時に再設定が推奨
- **権限**: `.htaccess` の書き込み権限がない環境では手動設定が必要

### 8. 文字エンコーディング
- **UTF-8前提**: ICSファイルはUTF-8エンコーディングを前提
- **文字化け**: 異なるエンコーディングのファイルは文字化けする可能性
- **DB設定**: WordPress の DB_CHARSET と DB_COLLATE 設定の影響

## トラブルシューティング

### よくある問題

1. **同期が実行されない**
- WordPress cronが正常に動作しているか確認
- ログで実行履歴を確認
- 手動同期で問題を切り分け

2. **ICSファイルが取得できない**
- URLが正しいか確認
- SSL証明書の問題（WordPress HTTPSサポート）
- ファイアウォール設定

3. **文字化けが発生する**
- ICSファイルの文字エンコーディング確認
- WordPressのDB_CHARSETとDB_COLLATE設定

## 開発者向け情報

### フィルターフック
```php
// ICS出力前のカスタマイズ
add_filter('mcs_ics_before_output', function($ics_content, $post_id) {
    // カスタマイズ処理
    return $ics_content;
}, 10, 2);
```

### アクションフック
```php
// 同期完了後の処理
add_action('mcs_sync_completed', function($results) {
    // 同期結果を使った処理
});
```

### カスタムフィールド仕様
- `mcs_booked_slots`: 予約スロット配列
  - フォーマット: `[[start_timestamp, end_timestamp, '', uid], ...]`
  - start_timestamp: 開始時刻（Unix timestamp）
  - end_timestamp: 終了時刻（Unix timestamp）
  - 3番目の要素: 予約元識別子（現在は空文字）
  - uid: イベント一意識別子（UID）

### HTTPキャッシュ設定
- **オプション名**: `mcs_http_cache`
- **構造**: `array( $url => array('etag' => $etag, 'last_modified' => $last_modified, 'updated_at' => $datetime) )`
- **制限**: 最大100URL、古いエントリは自動削除