# Minpaku Channel Sync

WordPress plugin for iCal(ICS) import/export functionality for stock synchronization (mini channel manager).

## 機能一覧

### ICSインポート機能
- 外部カレンダー（Airbnb、Booking.com等）からのICSファイルを定期的に取得
- 予約情報を指定されたWordPress投稿に同期
- 設定画面でURL-投稿IDマッピングを管理
- 自動同期（hourly/2hours/6hours）とマニュアル同期に対応

### ICSエクスポート機能
- WordPress投稿の予約情報をICS形式でエクスポート
- `/ics/{post_id}.ics` のエンドポイントでアクセス可能
- RFC5545準拠の75バイト行折返し対応
- インライン表示/ダウンロード選択可能

### 管理機能
- 設定画面での各種パラメータ管理
- ログ機能（WARNING/ERROR要約表示）
- 同期結果の詳細表示
- 次回同期予定時刻表示

## Local開発環境での動かし方

### 前提条件
- PHP 8.1以上
- WordPress環境
- wp-env（推奨）またはローカルWordPress環境

### セットアップ手順

1. **プラグインのインストール**
```bash
# プラグインディレクトリにクローン
cd wp-content/plugins/
git clone [repository] minpaku-channel-sync
```

2. **wp-envを使用する場合**
```bash
# プロジェクトルートで実行
npx @wordpress/env start
```

3. **プラグインの有効化**
- WordPress管理画面 > プラグイン > 「Minpaku Channel Sync」を有効化

4. **設定の確認**
- 管理画面 > 設定 > Minpaku Sync
- Target Post Type: 対象となる投稿タイプのスラッグ（デフォルト: property）

### 開発時の注意点
- rewrite rulesの変更後は必ずパーマリンク設定を保存してください
- ログ機能で動作状況を確認できます

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
- ログで同期結果を確認（Added/Updated/Skipped/Errors）

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

### 設定とログの検証

1. **設定保存の確認**
- 各設定項目が正しく保存される
- バリデーションエラーが適切に処理される
- 成功/エラーメッセージが表示される

2. **ログ機能の確認**
- INFO/WARNING/ERRORレベルのログが記録される
- 設定画面で最新ログが表示される
- WARNING/ERRORの要約が強調表示される

3. **セキュリティの確認**
- 管理者権限のないユーザーがアクセスできない
- nonce検証が正常に動作する
- 入力値のサニタイズが適切に行われる

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