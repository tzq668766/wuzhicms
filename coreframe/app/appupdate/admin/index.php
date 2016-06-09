<?php
// +----------------------------------------------------------------------
// | wuzhicms [ 五指互联网站内容管理系统 ]
// | Copyright (c) 2014-2015 http://www.wuzhicms.com All rights reserved.
// | Licensed ( http://www.wuzhicms.com/licenses/ )
// | Author: wangcanjia <phpip@qq.com>
// +----------------------------------------------------------------------
defined('IN_WZ') || exit('No direct script access allowed');
/**
 * 网站后台首页
 * 每小时内密码错误次数达到5次，锁定登录。
 * 记录用户登录的历史记录
 * 记录用户登录的错误记录
 */
load_class('admin');

final class index extends WUZHI_admin
{
    private $db;
    private $filesystem;

    public function __construct()
    {
        $this->db = load_class('db');
        $this->filesystem = load_class('filesystem',$m = 'appupdate');
    }

    /**
     * 检查环境
     */
    public function checkEnvironment()
    {
        $errors = array();
        if (!class_exists('ZipArchive')) {
            $errors[] = "php_zip扩展未激活";
        }

        if (!function_exists('curl_init')) {
            $errors[] = "php_curl扩展未激活";
        }

        $downloadDirectory = DOWNLOAD_PATH;

        if (file_exists($downloadDirectory)) {
            if (!is_writeable($downloadDirectory)) {
                $errors[] = "下载目录({$downloadDirectory})无写权限";
            }
        } else {
            try {
                mkdir($downloadDirectory, 0777, true);
            } catch (\Exception $e) {
                $errors[] = "下载目录({$downloadDirectory})创建失败";
            }
        }

        $backupdDirectory = BACKUP_PATH;

        if (file_exists($backupdDirectory)) {
            if (!is_writeable($backupdDirectory)) {
                $errors[] = "备份({$backupdDirectory})无写权限";
            }
        } else {
            try {
                mkdir($backupdDirectory, 0777, true);
            } catch (\Exception $e) {
                $errors[] = "备份({$backupdDirectory})创建失败";
            }
        }

        $rootDirectory = SYSTEM_ROOT;

        if (!is_writeable("{$rootDirectory}www")) {
            $errors[] = 'www目录无写权限';
        }

        if (!is_writeable("{$rootDirectory}www/api")) {
            $errors[] = 'www/api目录无写权限';
        }

        if (!is_writeable("{$rootDirectory}www/configs")) {
            $errors[] = 'www/configs目录无写权限';
        }

        if (!is_writeable("{$rootDirectory}www/res")) {
            $errors[] = 'www/res目录无写权限';
        }

        if (!is_writeable("{$rootDirectory}coreframe")) {
            $errors[] = 'coreframe目录无写权限';
        }

        if (!is_writeable("{$rootDirectory}caches")) {
            $errors[] = 'cache目录无写权限';
        }

        if (!is_writeable("{$rootDirectory}/www/configs/web_config.php")) {
            $errors[] = 'www/configs/web_config.php文件无写权限';
        }
        $this->createJsonErrors($errors);
    }

    /**
     * 检查是否需要备份文件
     */
    public function backupFile(){
        //TODO LIST
        $errors = array();

        $this->filesystem->touch("filesystem", $mode = 0777);
        $this->filesystem->remove('filesystem');
        $this->createJsonErrors($errors);
    }

    /**
     * 检查是否需要备份数据库
     */
    public function backupDb(){
        //TODO LIST
        $errors = array();
        $this->createJsonErrors($errors);
    }
    /**
     * @param $packageId
     * @return array
     * 下载文件
     */
    function downloadPackageForUpdate($packageId)
    {
        $errors   = array();
        $unzipDir = DOWNLOAD_PATH . '/upgrade/seajs-3.0.0';
        try {
            /* $package = $this->getCenterPackageInfo($packageId); //获取url

             if (empty($package)) {
                 throw $this->createServiceException("应用包#{$packageId}不存在或网络超时，读取包信息失败");
             }*/
            //  $filepath = $this->createAppClient()->downloadPackage($packageId);
            $filepath = $this->download('http://prod.edusoho.com/MAIN_2.0.5.zip');

            // $this->unzipPackageFile($filepath, $this->makePackageFileUnzipDir($filepath));
            $this->unzipPackageFile($filepath, $unzipDir);
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }

        $this->createJsonErrors($errors);
    }

    /**
     * @param $packageId
     * @return array
     * 处理下载文件
     * $packageId, $type, $index = 0
     */
    public function beginUpgrade(){
        //TODO LIST 处理删除文件 ,处理需要覆盖的文件, 处理sql脚本
        $errors =array();
        $package = $packageDir = null;
        $packageId = isset($GLOBALS['packageId']) ? intval($GLOBALS['packageId']) : MSG(L('parameter_error'));
        $type = isset($GLOBALS['type']) ? intval($GLOBALS['type']) : MSG(L('parameter_error'));
        $index = isset($GLOBALS['index']) ? intval($GLOBALS['index']) : MSG(L('parameter_error'));


        try {
          /*  $package = $this->getCenterPackageInfo($packageId);

            if (empty($package)) {
                throw $this->createServiceException("应用包#{$packageId}不存在或网络超时，读取包信息失败");
            }
            $packageDir = $this->makePackageFileUnzipDir($package);*/
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
            goto last;
        }

        if (empty($index)) {
            try {
                $this->_deleteFilesForPackageUpdate($package, $packageDir);
            } catch (\Exception $e) {
                $errors[] = "删除文件时发生了错误：{$e->getMessage()}";
                goto last;
            }

            try {
                $this->_replaceFileForPackageUpdate($package, $packageDir);
            } catch (\Exception $e) {
                $errors[] = "复制升级文件时发生了错误：{$e->getMessage()}";
                goto last;
            }
        }


        try {
            $info = $this->_execScriptForPackageUpdate($package, $packageDir, $type, $index);

            if (isset($info['index'])) {
                goto last;
            }
        } catch (\Exception $e) {
            $errors[] = "执行升级/安装脚本时发生了错误：{$e->getMessage()}";
            goto last;
        }


        try {
            $cachePath  = $this->getKernel()->getParameter('kernel.root_dir').'/cache/'.$this->getKernel()->getEnvironment();
            $this->filesystem->remove($cachePath);

        } catch (\Exception $e) {
            $errors[] = "应用安装升级成功，但刷新缓存失败！请检查{$cachePath}的权限";
            goto last;
        }

        if (empty($errors)) {
            $this->updateAppForPackageUpdate($package, $packageDir);
        }
        last:
        $this->createJsonErrors($errors);
    }

    protected function _deleteFilesForPackageUpdate($package, $packageDir)
    {
        if (!file_exists($packageDir.'/delete')) {
            return;
        }

        $fh         = fopen($packageDir.'/delete', 'r');

        while ($filepath = fgets($fh)) {
            $fullpath = SYSTEM_ROOT.'/'.trim($filepath);

            if (file_exists($fullpath)) {
                $this->filesystem->remove($fullpath);
            }
        }

        fclose($fh);
    }

    protected function _replaceFileForPackageUpdate($package, $packageDir)
    {
        $filesystem = new Filesystem();
        $filesystem->mirror("{$packageDir}/source", $this->getPackageRootDirectory($package, $packageDir), null, array(
            'override'        => true,
            'copy_on_windows' => true
        ));
    }

    protected function updateAppForPackageUpdate($package, $packageDir)
    {
        $newApp = array(
            'code'          => $package['product']['code'],
            'name'          => $package['product']['name'],
            'description'   => $package['product']['description'],
            'icon'          => $package['product']['icon'],
            'version'       => $package['toVersion'],
            'fromVersion'   => $package['fromVersion'],
            'developerId'   => $package['product']['developerId'],
            'developerName' => $package['product']['developerName'],
            'updatedTime'   => time()
        );

        $app = $this->db->get_one('cloud_app', array('code' => $package['product']['code']));

        if (empty($app)) {
            $newApp['installedTime'] = time();
            $this->db->insert('cloud_app', $newApp);
            $app = $this->db->get_one('cloud_app', array('code' => $package['product']['code']));
        }
        $this->db->update('cloud_app', $newApp , array('id' => $app['id']));
        return $app;
    }
    private function download($url)
    {
        $filename = md5($url) . '_' . time();
        $filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        $fp       = fopen($filepath, 'w');

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_FILE, $fp);
        curl_exec($curl);
        curl_close($curl);

        fclose($fp);

        return $filepath;
    }


    private function unzipPackageFile($filepath, $unzipDir)
    {

        if (file_exists($unzipDir)) {
            $this->filesystem->remove($unzipDir);
        }

        $tmpUnzipDir = $unzipDir . '_tmp';

        if (file_exists($tmpUnzipDir)) {
            $this->filesystem->remove($tmpUnzipDir);
        }
        $this->filesystem->makedir($tmpUnzipDir);

        $zip = new \ZipArchive;

        if ($zip->open($filepath) === true) {
            $tmpUnzipFullDir = $tmpUnzipDir . '/' . $zip->getNameIndex(0);
            $zip->extractTo($tmpUnzipDir);
            $zip->close();
            $this->filesystem->rename($tmpUnzipFullDir, $unzipDir);
            $this->filesystem->remove($tmpUnzipDir);
        } else {
            throw new \Exception('无法解压缩安装包！');
        }
    }


    private function createJsonErrors($errors)
    {
        if (empty($errors)) {
            echo json_encode(array('status' => 'ok'));
        } else if (isset($errors['index'])) {
            echo json_encode($errors);
        } else {
            echo json_encode(array('status' => 'error', 'errors' => $errors));
        }

    }
}
