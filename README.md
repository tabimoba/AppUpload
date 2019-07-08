# AppUpload

## Image / File upload plugin for MODx Evolution and Evolution CMS.

### はじめに(About)
MODx Evolution / EvolutionCMS 用の画像/ファイルアップロードプラグインです。リソースに添付する形で画像やファイルをアップロードします。contentディレクトリの中にresというフォルダを作成してその中にリソース専用のフォルダを作成してアップロードします。

このフォルダはリソースID1234の場合content/res/23/4/1234/uploadfile.pngという感じになります。 これはIDを0で詰めて下3桁を2:1で区切った数字です。

[@mio3io](https://twitter.com/mio3io)さんが作成されたMODx Evolution用プラグインAppUpload（現在は非公開）がオリジナルになります。オリジナルの機能に加え、最新版のMODx Evolution (1.0.22J)への対応と、画像以外のファイルアップロードへの対応を行っています。

(For Non-Japanese users)

AppUpload is a Image / File upload plugin for MODx Evolution and Evolution CMS. You can attach your file(s) to MODx resource and save to under res directory.

Original version has created by [@mio3io](https://twitter.com/mio3io). This version has some enhanced features. For example, supported Latest MODx Evolution 1.0.22J (Distributed by Japanese MODx User's group.) and can upload not only images but also non-image files.

Currently, only support Japanese language. English is not fully supported.

### インストール方法(How to install)
1. MODx Evolution / Evolution CMSの管理者インターフェースへログインし、  「エレメント」-「エレメント管理」をクリックします。
2. 「プラグイン」タブへ切り替え、「プラグインを作成」をクリックします。
3. ```/install/assets/plugins/appupload.tpl``` ファイルをテキストエディタで開き、 「プラグイン コード(php)」フィールドに貼付します。
4. 「設定」タブをクリックし、「TemplateVarNames (NotID)」フィールドへ本プラグインを適用したいテンプレート変数の名前を入力します。
5. 「更新」をクリックします。
6. ```/plugins/appupload```ディレクトリをMODx Evolution / Evolution CMSの ```/assets/plugins```以下へそのまま（ファイル・ディレクトリ構成を維持した状態で）コピーします。

入力フォームはプラグインによって自動成形されますがテンプレート変数の入力フォームは画像とファイル以外に設定してください。

(For Non-Japanese users)

1. Open and login the MODx Evolution / Evolution CMS admin interface. After logged-in, toggle "Elements" tab and click "Manage Elements" link. 
2. Toggle "Plugins" tab and click "New Plugin" button.
3. Open ```/install/assets/plugins/appupload.tpl``` and paste to "Plugin code (php)" field.
4. Toggle "Configuration" tab and input your defined Template Variables (and you want to apply) to "TemplateVarNames (NotID)".
5. Click Save button.
6. Copy ```/plugins/appupload``` directory to your MODx Evolution / Evolution CMS ```/assets/plugins``` directory. (Keep same structure.)

## License

The MIT License.