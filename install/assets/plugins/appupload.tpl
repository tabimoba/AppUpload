<?php
/**
 * AppUpload
 *
 * リソースに添付して画像ファイルをアップロードします
 *
 * @category    plugin
 * @version     1.0.0 beta4
 * @author      Hisato
 * @internal    @events OnBeforeEmptyTrash,OnDocFormRender,OnDocFormSave,OnBeforeTVFormDelete
 * @internal    @properties &tvnames=TemplateVarNames (NotID)<br>[tvname1,tvname2,tvname3];text; &rename=Rename;list;yes,no;no &lang=Lang;list;,en;
 */
include_once($modx->config['base_path']. 'assets/plugins/appupload/appupload.class.inc.php');
$appup = new AppUpload();
$appup->run();
