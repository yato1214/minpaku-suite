# WordPress Minpaku Connector Plugin ビルドガイド

民泊コネクタプラグインのビルドスクリプト使用方法です。

## 前提条件

- Windows PowerShell 5.0以上
- プラグインソースコードが `wp-minpaku-connector` ディレクトリに配置されていること

## 基本的な使用方法

### 1. 基本ビルド
```powershell
.\build.ps1
```
これにより以下が作成されます：
- `dist/wp-minpaku-connector-v1.0.0.zip`
- `dist/wp-minpaku-connector.zip` (汎用名)

### 2. バージョンを指定してビルド
```powershell
.\build.ps1 -Version "1.0.1"
```

### 3. 出力ディレクトリを指定
```powershell
.\build.ps1 -OutputDir "release"
```

### 4. クリーンビルド（既存ファイルを削除）
```powershell
.\build.ps1 -Clean
```

### 5. すべてのオプションを組み合わせ
```powershell
.\build.ps1 -Version "1.0.2" -OutputDir "release" -Clean
```

## ビルドプロセス

1. **プラグイン構造の検証**
   - 必要なファイル・ディレクトリの存在確認
   - `wp-minpaku-connector.php`, `includes`, `assets` の確認

2. **ファイルのフィルタリング**
   - 不要なファイルを除外（開発用ファイル、ログファイルなど）
   - WordPressプラグインに必要なファイルのみを抽出

3. **バージョン更新**
   - プラグインメインファイルのVersionヘッダーを更新
   - 定数 `WP_MINPAKU_CONNECTOR_VERSION` を更新

4. **ZIP作成**
   - 最適化された圧縮でZIPファイルを作成
   - バージョン付きと汎用名の2つのファイルを生成

## 除外されるファイル

以下のファイル・ディレクトリはビルドから除外されます：
- `*.log`, `*.tmp`
- `.DS_Store`, `Thumbs.db`
- `node_modules`, `.git*`
- `*.md` (ドキュメントファイル)
- `composer.*`, `package.*`
- `webpack.config.js`, `gulpfile.js`
- `tests`, `docs`
- `*.zip`
- `build.ps1`

## 出力ファイル

### バージョン付きファイル
`wp-minpaku-connector-v{Version}.zip`
- 例: `wp-minpaku-connector-v1.0.1.zip`

### 汎用ファイル
`wp-minpaku-connector.zip`
- 常に最新ビルドで上書きされる
- デプロイメント用

## エラー対処

### エラー: Plugin directory not found
```
ERROR: Plugin directory not found: C:\path\to\wp-minpaku-connector
```
**解決方法**: `wp-minpaku-connector` ディレクトリが `build.ps1` と同じ場所にあることを確認

### エラー: Missing required files
```
ERROR: Missing required files/directories:
  - wp-minpaku-connector.php
  - includes
  - assets
```
**解決方法**: 必要なファイル・ディレクトリが存在することを確認

### PowerShell実行ポリシーエラー
```
このシステムではスクリプトの実行が無効になっています。
```
**解決方法**:
```powershell
powershell -ExecutionPolicy Bypass -File "./build.ps1"
```

## WordPressへのインストール

1. WordPress管理画面にアクセス
2. プラグイン > 新規追加 > プラグインのアップロード
3. 生成された `.zip` ファイルを選択
4. インストール後、プラグインを有効化

## 修正されたカレンダー機能

このビルドには以下の修正が含まれています：

### ✅ 修正内容
- カレンダーデザインをポータルサイトと統一
- 空き日クリック時のポータルサイト予約画面への遷移機能
- 価格表記の正確な表示
- カレンダー色分け状況説明の統一
- データ取得・表示の最適化

### 🎯 対応ショートコード
- `[minpaku_connector type="properties" limit="12" columns="3"]`
- `[minpaku_connector type="availability" property_id="123" months="2"]`
- `[minpaku_connector type="property" property_id="123"]`

### 🔗 空き日クリック機能
- 空き日（緑）をクリックすると新しいタブでポータルサイトの予約画面が開きます
- 日付、物件ID、人数などのパラメータが自動で渡されます
- ポータルサイト側でログイン・予約手続きを完了できます

## サポート

問題が発生した場合は、以下を確認してください：
1. PowerShellのバージョン
2. プラグインディレクトリ構造
3. ファイルの権限
4. ディスクの空き容量