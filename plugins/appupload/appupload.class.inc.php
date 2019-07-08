<?php
/**
 * AppUpload
 * MODx file upload plugin.
 *
 * @version 1.1.0
 * @author mio (https://twitter.com/mio3io/)
 * @author Tomoyuki OHNO (https://twitter.com/tabimoba/)
 * @copyright 2014-2019 mio, Tomoyuki OHNO
 * @license The MIT License https://opensource.org/licenses/mit-license.php
 */

class AppUpload {
	/**
	 * 表示メッセージ
	 *
	 * @var array
	 */
	public $msg = [
		'japanese-utf8' => [
			'error_notvs' => '設定されているテンプレート変数「%s」の情報が取得できませんでした',
			'error_upload' => 'アップロードエラーです',
			'error_maxfilesize' => 'ファイルサイズが大きすぎます',
			'error_ext' => 'アップロード出来ない形式のファイルです',
			'error_overlap' => '同じ名前の画像が既に存在します',
			'error_stop' => 'ファイルのアップロードを中止しました',
			'error_delete' => 'ファイルが削除できませんでした file:%s',
			'error_mkdir' => 'リソースフォルダが作成できませんでした',
			'delete' => '削除',
		],
		'english' => [
			'error_notvs' => 'Template variable "%s" does not exist.',
			'error_upload' => 'Is upload error',
			'error_maxfilesize' => 'File size exceeds the upper limit.',
			'error_ext' => 'File format that can not be uploaded.',
			'error_overlap' => 'Image with the same name already exists.',
			'error_stop' => 'Aborted the file upload.',
			'error_delete' => 'Could not delete the file: %s',
			'error_mkdir' => 'Can not create a resource folder.',
			'delete' => 'Delete',
		]
	];

	/**
	 * フォームテンプレート
	 *
	 * @var string
	 */
	public $docFormRenderTmpl = <<< HTML
var value = \$j('#tv[+tvid+]').val();
var el_td = \$j('#tv[+tvid+]').parent('td');
el_td.empty();
el_td.append('<input type="hidden" name="tv[+tvid+]" id="tv[+tvid+]" value="[+value+]">');
el_td.append('<div style="float:left;width:100px;height:100px;overflow:hidden;margin-right:10px;text-align:center"><a href="[+imgurl+]" target="_blank""><img src="[+imgurl+]" style="max-width:160px;max-height:160px;"></a></div>');
el_td.append('<div><input type="file" name="tvimg[+tvid+]" id="tvimg[+tvid+]" value="[+value+]" class="text"></div>');
el_td.append('<div style="margin-top:5px;">[+value+]</div>');
if (value) el_td.append('<div style="margin-top:5px;"><label><input type="checkbox" name="tvdel[+tvid+]" value="1" style="vertical-align:middle;"> [+text_delete+]</label></div>');
HTML;

	/**
	 * ファイル保存先ディレクトリ
	 * （$contentStoreDir + $resDirName = ファイル保存先のルートディレクトリ）
	 *
	 * @var string
	 */
	public $resDir = 'res/';

	/**
	 * MODx オブジェクト
	 *
	 * @var object
	 */
	protected $modx;

	/**
	 * 言語
	 *
	 * @var string
	 */
	private $__lang = '';

	/**
	 * 自分自身のディレクトリパス
	 *
	 * @var string
	 */
	private $__selfDir;

	/**
	 * MODxのcontentディレクトリのパス
	 *
	 * @var string
	 */
	private $__contentsStoreDir;

	/**
	 * MODxのcontentディレクトリのURL
	 *
	 * @var string
	 */
	private $__contentsStoreUrl;

	/**
	 * エラーメッセージ
	 *
	 * @var array
	 */
	private $__errors = [];

	/**
	 * AppUpload constructor.
	 */
	public function __construct() {
		global $modx;
		$this->modx = $modx;
		$this->__lang = $this->__setLang();
		$this->__setPath();
	}

	/**
	 * プラグイン処理の実行
	 *
	 * @return void
	 */
	public function run() {
		// 対象とするテンプレート変数名を取得する
		if (! $targetTvs = $this->__getTargetTvs()) {
			return;
		}

		$id = $this->modx->event->params['id'];
		switch($this->modx->event->name) {
			// テンプレート変数削除と一緒に画像削除
			case 'OnBeforeTVFormDelete' :
				$this->__onBeforeTvFormDelete();
				break;
			// リソース保存
			case 'OnDocFormSave' :
				$this->__onDocFormSave($id, $targetTvs);
				break;
			// ゴミ箱リソース削除
			case 'OnBeforeEmptyTrash' :
				$this->__onBeforeEmptyTrash();
				break;
			// フォームをりふぉーむ
			case 'OnDocFormRender' :
				$this->__onDocFormRender($id, $targetTvs);
				break;
			default:
				break;
		}

		// エラーログの追記
		$this->__addErrorToLogEvent();
	}

	/**
	 * テンプレート変数削除前の処理
	 *
	 * @return void
	 */
	private function __onBeforeTvFormDelete() {
		// 対象とするテンプレート変数IDを取得する
		$rs = $this->modx->db->select(
			'contentid,value',
			'[+prefix+]site_tmplvar_contentvalues',
			"`tmplvarid`='" . intval($this->modx->event->params['id']) . "'"
		);

		if ($this->modx->db->getRecordCount($rs) <= 0) {
			return;
		}
		while ($row = $this->modx->db->getRow($rs)) {
			// ファイルが保存されているディレクトリの相対パスを取得する
			$dirName = $this->getDirPath($row['contentid']);

			// ファイル名をフルパスで取得する
			$delFileName = $this->__contentsStoreDir . $dirName . $this->basename($row['value']);
			if (file_exists($delFileName)) {
				// ファイルを削除する
				unlink($delFileName);
			}
		}
	}

	/**
	 * リソース保存時の処理
	 *
	 * @param $id int リソースID
	 * @param $targetTvs array 対象とするテンプレート変数
	 * @return void
	 */
	private function __onDocFormSave($id, $targetTvs) {
		if ($this->modx->config['upload_images'] != '') {
			$docobj = $this->modx->getDocumentObject('id', $id);
			foreach ($targetTvs as $targetTv) {
				$this->formSave($targetTv, $id, $docobj);
			}
		}
	}

	/**
	 * ごみ箱を空にする前の処理
	 *
	 * @return void
	 */
	private function __onBeforeEmptyTrash() {
		if ($ids = $this->modx->event->params['ids']) {
			foreach ($ids as $id) {
				$this->formDocDelete($id);
			}
		}
	}

	/**
	 * フォームレンダリング時の処理
	 *
	 * @param $id int リソースID
	 * @param $targetTvs array 対象とするテンプレート変数
	 * @return void
	 */
	private function __onDocFormRender($id, $targetTvs) {
		$output = '';
		foreach ($targetTvs as $key => $targetTv) {
			// テンプレート変数を取得する
			$rs = $this->modx->db->select(
				'id',
				'[+prefix+]site_tmplvars',
				"`name`='" . $this->modx->db->escape($targetTvs[$key]) . "'"
			);

			if (! $tvid = $this->modx->db->getValue($rs)) {
				// エラーメッセージを追加する
				$this->addError(sprintf($this->msg[$this->__lang]['error_notvs'], $targetTvs[$key]));
				continue;
			}

			// テンプレート変数のプロパティを取得する
			$tvprop = $this->__getTvProp($id, $targetTvs, $key);

			// テンプレートをセットする
			$tmpHtml = $this->docFormRenderTmpl;

			// プレースホルダーを定義する
			$ph = [
				'[+tvid+]' => $tvid,
				'[+value+]' => $tvprop['value'] ? $tvprop['value'] : '',
				'[+imgurl+]' =>
					$tvprop['value'] ?
						$this->modx->config['base_url'] . $tvprop['value'] :
						$this->modx->config['base_url'] . 'assets/plugins/appupload/noimage.png',
				'[+text_delete+]' => $this->msg[$this->__lang]['delete'],
			];

			// テンプレート変数を置換する
			$tmpHtml = str_replace(array_keys($ph), array_values($ph), $tmpHtml);
			$output .= $tmpHtml;
		}

		// 結果を出力する
		$this->modx->event->output('<script type="text/javascript">' . $output . '</script>');
	}

	/**
	 * テンプレート変数のプロパティを取得する
	 *
	 * @param $id int リソースID
	 * @param $targetTvs array|string 対象とするテンプレート変数の値（配列または文字列）
	 * @param $targetTvKey int 対象とするテンプレート変数の配列より値を取得したいキー
	 * @return array
	 */
	private function __getTvProp($id, $targetTvs, $targetTvKey = 0) {
		$docobj = $this->modx->getDocumentObject('id', $id);
		$tmplVars = $this->modx->getTemplateVars('*', '*', $id, $docobj['published']);

		$result = ['value' => ''];
		if (! empty($id)) {
			foreach ($tmplVars as $tmplVar) {
				if (is_array($targetTvs)) {
					// 配列の場合
					if ($tmplVar['name'] === $targetTvs[$targetTvKey]) {
						$result = $tmplVar;
						break;
					}
				} else {
					// 配列でない場合
					if ($tmplVar['name'] === $targetTvs) {
						$result = $tmplVar;
						break;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * リソース用ディレクトリとファイルの削除
	 *
	 * @param $id リソースID
	 * @return void
	 */
	function formDocDelete($id) {
		$dirname = $this->getDirPath($id);
		if ( !file_exists($this->__contentsStoreDir . $dirname)) {
			return;
		}
		if ($fh = opendir($this->__contentsStoreDir . $dirname)) {
			while ($filename = readdir($fh)) {
				if (preg_match("/^\./", $filename))
					continue;
				if (!@unlink($this->__contentsStoreDir . $dirname . $filename)) {
					$this->addError(
						sprintf($this->msg[$this->__lang]['error_delete'],
							$this->__contentsStoreDir . $dirname . $filename)
					);
				}
			}
		}
		@rmdir($this->__contentsStoreDir . $dirname);
	}

	/**
	 * リソース更新時のテンプレート変数の処理
	 *
	 * @param $tvname
	 * @param $id
	 * @param $docobj
	 * @return bool|void
	 */
	function formSave($tvname, $id, $docobj) {
		$tvprop = $this->__getTvProp($id, $tvname);

		if (! $tvprop['value']) {
			return;
		}


		$tvid = $tvprop['id'] ? intval($tvprop['id']) : '';
		// アップロード用パス作成
		$dirname = $this->getDirPath($id);
		// アップロードフォルダ作成
		if (!$this->mkDir($dirname)) {
			return $this->addError($this->msg[$this->__lang]['error_mkdir']);
		}
		$newtv = array();
		// 元のファイルを削除
		if ($_POST['tvdel' . $tvid] || is_uploaded_file($_FILES['tvimg' . $tvid]['tmp_name'])) {
			if ($rmname = $this->basename($tvprop['value'])) {
				@unlink($this->modx->config['rb_base_dir'] . $dirname . $this->getServerFilename($rmname));
			}
			$newtv = array('value' => '');
		}
		// @formatter:off
		$newfilename = str_replace(array("\0","\r\n","\r","\n","'","\"","\\","`"), '', $_FILES['tvimg' . $tvid]["name"]);
		// @formatter:on
		// ファイルをアップロード
		$exupload = true;
		if (is_uploaded_file($_FILES['tvimg' . $tvid]['tmp_name'])) {
			if ($_FILES['tvimg' . $tvid]['error']) {
				$exupload = $this->addError($this->msg[$this->__lang]['error_upload']);
			}
			if ($_FILES['tvimg' . $tvid]['size'] >= $this->modx->config['upload_maxsize']) {
				$exupload = $this->addError($this->msg[$this->__lang]['error_maxfilesize']);
			}
			$ext = $this->getExt($newfilename);
			if (!in_array($ext, explode(',', $this->modx->config['upload_images']))) {
				$exupload = $this->addError($this->msg[$this->__lang]['error_ext']);
			}
			if ($this->modx->event->params['rename'] == 'yes') {
				$newfilename = date('Ymd_His') . '_' . $tvid . '.' . $ext;
			}
			if (file_exists($this->modx->config['rb_base_dir'] . $dirname . $newfilename)) {
				$exupload = $this->addError($this->msg[$this->__lang]['error_overlap']);
			}
			$newtv = array('value' => $this->modx->config['rb_base_url'] . $dirname . $newfilename);
			if ($exupload == false) {
				return false;
			}
		}
		//テンプレート変数を更新
		if (count($newtv)) {
			$oldid = $this->modx->db->getValue($this->modx->db->select('id', '[+prefix+]site_tmplvar_contentvalues', "contentid='{$id}' and tmplvarid='{$tvid}'"));
			$newtv['contentid'] = $id;
			$newtv['tmplvarid'] = $tvid;
			$this->modx->db->escape($newtv);
			if ($oldid) {
				$rs = $this->modx->db->update($newtv, '[+prefix+]site_tmplvar_contentvalues', "contentid='{$id}' and tmplvarid='{$tvid}'");
			} else {
				$rs = $this->modx->db->insert($newtv, '[+prefix+]site_tmplvar_contentvalues');
			}
			// アップロード実行
			if ($rs && $exupload) {
				if (move_uploaded_file($_FILES['tvimg' . $tvid]['tmp_name'], $this->modx->config['rb_base_dir'] . $dirname . $this->getServerFilename($newfilename))) {
					chmod($this->modx->config['rb_base_dir'] . $dirname . $newfilename, 0644);
				} else {
					return $this->addError($this->msg[$this->__lang]['error_stop']);
				}
			}
		}
	}


	/**
	 * ディレクトリパスとURLを設定する
	 */
	private function __setPath() {
		$this->__selfDir = dirname(__FILE__) . '/';
		$this->__contentsStoreDir = $this->modx->config['rb_base_dir'];
		$this->__contentsStoreUrl = $this->modx->config['rb_base_url'];
		$this->__resDirFull = $this->__contentsStoreDir . '/' . $this->resDir;
	}

	/**
	 * 表示言語を設定する
	 *
	 * @return string
	 */
	private function __setLang() {
		$lang = $this->modx->config['manager_language'];
		if (! isset($this->msg[$lang])) {
			return 'english';
		}
		return $lang;
	}

	/**
	 * エラーメッセージを追加する
	 * @param $msg string エラーメッセージ
	 * @return void
	 */
	public function addError($msg) {
		$this->__errors[] = $msg;
	}

	/**
	 * 対象とするテンプレート変数名を取得する
	 *
	 * @return array|bool
	 */
	private function __getTargetTvs() {
		if (! $tvnames = $this->modx->event->params['tvnames']) {
			return false;
		}
		$targetTvs = array_map('trim', explode(',', $tvnames));
		return $targetTvs;
	}

	/**
	 * ディレクトリのパス名を作成する
	 *
	 * @param $id int リソースID
	 * @return string
	 */
	public function getDirPath($id) {
		$padid = str_pad($id, 4, 0, STR_PAD_LEFT);
		$pdir = $this->resDir . substr($padid, -3, 2) . '/' . substr($padid, -1, 1) . '/' . $id . '/';
		return $pdir;
	}

	/**
	 * ディレクトリを作成する
	 *
	 * @param $path string 作成するディレクトリのパス名
	 * @return bool
	 */
	public function mkDir($path) {
		if (file_exists($this->modx->config['rb_base_dir'] . $path)) {
			return true;
		}
		if (mkdir($this->modx->config['rb_base_dir'] . $path, $this->modx->config['new_folder_permissions'], true)) {
			return true;
		}
	}

	/**
	 * パスから拡張子を取得する
	 *
	 * @param $str string ファイルパス
	 * @return string
	 */
	// パスから拡張子を取得
	public function getExt($str) {
		return mb_substr(mb_strrchr($str, '.'), 1);
	}

	/**
	 * パスからファイル名を取得する
	 *
	 * @param $str string ファイルパス
	 * @return string
	 */
	public function basename($str) {
		return mb_substr(mb_strrchr($str, "/"), 1);
	}

	/**
	 * ディレクトリからファイル名を取得する
	 *
	 * @param $filename string ファイル名
	 * @return string
	 */
	public function getServerFilename($filename) {
		if (DIRECTORY_SEPARATOR == '\\') {
			// Windows系OSの場合の処理
			$filename = mb_convert_encoding($filename, "CP932", "UTF-8");
		}
		return $filename;
	}

	/**
	 * エラーをMODxのイベントログへ追記する
	 *
	 * @return void
	 */
	private function __addErrorToLogEvent() {
		if (count($this->__errors)) {
			$this->modx->logEvent(0, 0, implode('<br>', $this->__errors), 'AppUpload');
		}
	}
}
