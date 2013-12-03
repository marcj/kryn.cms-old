<?php

namespace Kryn\CmsBundle\Controller\Admin;

use Kryn\CmsBundle\Admin\Utils;
use Kryn\CmsBundle\Core;
use Kryn\CmsBundle\Model\Base\GroupQuery;
use Kryn\CmsBundle\Model\LanguageQuery;
use Kryn\CmsBundle\Properties;
use Propel\Runtime\Map\TableMap;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class Backend extends Controller
{
    public function clearCache()
    {
        $utils = new Utils($this->getKrynCore());

        $utils->clearCache();

        return true;
    }

    /**
     * @return Core
     */
    public function getKrynCore()
    {
        return $this->get('kryn.cms');
    }

    public function getDesktop()
    {
        if ($desktop = $this->getKrynCore()->getAdminClient()->getUser()->getDesktop()) {
            return $desktop->toArray();
        } else {
            return false;
        }
    }

    public function saveDesktop($icons)
    {
        $properties = new Properties($icons);

        $this->getKrynCore()->getAdminClient()->getUser()->setDesktop($properties);

        return $this->getKrynCore()->getAdminClient()->getUser()->save() > 0;
    }

    public function getSearch($q, $lang = null)
    {
        $res = array();
//        foreach ($this->getKrynCore()->$modules as &$mod) {
//            if (method_exists($mod, 'searchAdmin')) {
//                $res = array_merge($res, $mod->searchAdmin($q));
//            }
//        }

        return $res;
    }

    public function getWidgets()
    {
        if ($widgets = $this->getKrynCore()->getAdminClient()->getUser()->getWidgets()) {
            return $widgets->toArray();
        } else {
            return false;
        }

    }

    public function saveWidgets($widgets)
    {
        $properties = new Properties($widgets);
        $this->getKrynCore()->getAdminClient()->getUser()->setWidgets($properties);
        $this->getKrynCore()->getAdminClient()->getUser()->save();

        return true;
    }

    public function saveUserSettings($settings)
    {
        $properties = new Properties($settings);

        if ($this->getKrynCore()->getAdminClient()->getUser()->getId() > 0) {
            $this->getKrynCore()->getAdminClient()->getUser()->setSettings($properties);
            $this->getKrynCore()->getAdminClient()->getUser()->save();
        }

        return true;
    }

    public function getCustomJs($bundle, $code, $onLoad)
    {
        $module = preg_replace('[^a-zA-Z0-9_-]', '', $bundle);
        $code = preg_replace('[^a-zA-Z0-9_-]', '', $code);
        $onLoad = preg_replace('[^a-zA-Z0-9_-]', '', $onLoad);

        $bundle = $this->getKrynCore()->getBundle($module);

        $file = $bundle->getPath() . '/Resources/public/admin/js/' . $code . '.js';

        header('Content-Type: text/javascript');

        if (!file_exists($file)) {
            $content = "contentCantLoaded_" . $onLoad . "('$file');\n";
        } else {
            $content = file_get_contents($file);
            $content .= "\n";
            $content .= "contentLoaded_" . $onLoad . '();' . "\n";
        }

        die($content);
    }

    /**
     * Returns a huge array with settings.
     *
     * items:
     *
     *  modules
     *  configs
     *  layouts
     *  contents
     *  navigations
     *  themes
     *  themeProperties
     *  user
     *  groups
     *  langs
     *
     *  Example: settings?keys[]=modules&keys[]=layouts
     *
     * @param  array $keys Limits the result.
     *
     * @return array
     */
    public function getSettings($keys = array())
    {
        $loadKeys = $keys;
        if (!$loadKeys) {
            $loadKeys = false;
        }

        $res = array();

        if ($loadKeys == false || in_array('modules', $loadKeys)) {
            foreach ($this->getKrynCore()->getConfigs() as $config) {
                $res['bundles'][] = $config->getBundleName();
            }
        }

        if ($loadKeys == false || in_array('configs', $loadKeys)) {
            $res['configs'] = $this->getKrynCore()->getConfigs()->toArray();
        }

        if (
            $loadKeys == false || in_array('themes', $loadKeys)
        ) {
            foreach ($this->getKrynCore()->getConfigs() as $key => $config) {
                if ($config->getThemes()) {
                    foreach ($config->getThemes() as $themeTitle => $theme) {
                        /** @var $theme \Kryn\CmsBundle\Configuration\Theme */
                        $res['themes'][$theme->getId()] = $theme->toArray();
                    }
                }
            }
        }

        if ($loadKeys == false || in_array('upload_max_filesize', $loadKeys)) {
            $v = ini_get('upload_max_filesize');
            $v2 = ini_get('post_max_size');
            $b = $this->return_bytes(($v < $v2) ? $v : $v2);
            $res['upload_max_filesize'] = $b;
        }

        if ($loadKeys == false || in_array('groups', $loadKeys)) {
            $res['groups'] = GroupQuery::create()->find()->toArray(null, null, TableMap::TYPE_STUDLYPHPNAME);
        }

        if ($loadKeys == false || in_array('user', $loadKeys)) {
            if ($settings = $this->getKrynCore()->getAdminClient()->getUser()->getSettings()) {
                if ($settings instanceof Properties) {
                    $res['user'] = $settings->toArray();
                }
            }

            if (!isset($res['user'])) {
                $res['user'] = array();
            }
        }

        if ($loadKeys == false || in_array('system', $loadKeys)) {
            $res['system'] = clone $this->getKrynCore()->getSystemConfig();
            $res['system']->setDatabase(null);
            $res['system']->setPasswordHashKey('');
        }

        if ($loadKeys == false || in_array('domains', $loadKeys)) {
            $res['domains'] = $this->getKrynCore()->getObjects()->getList('KrynCmsBundle:Domain', null, array('permissionCheck' => true));
        }

        if ($loadKeys == false || in_array('langs', $loadKeys)) {
            $tlangs = LanguageQuery::create()->filterByVisible(true)->find()->toArray(
                null,
                null,
                TableMap::TYPE_STUDLYPHPNAME
            );

            $langs = [];
            foreach ($tlangs as $lang) {
                $langs[$lang['code']] = $tlangs;
            }
            #$langs = dbToKeyIndex($tlangs, 'code');
            $res['langs'] = $langs;
        }

        return $res;
    }

    public function return_bytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    public function loadJsMap()
    {
        $this->loadJs(true);
    }

    public function loadCss()
    {
        header('Content-Type: text/css');
        $expires = 60 * 60 * 24 * 14;
        header('Pragma: public');
        header('Cache-Control: max-age=' . $expires);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');

        if (extension_loaded("zlib") && (ini_get("output_handler") != "ob_gzhandler")) {
            ini_set("zlib.output_compression", 1);
        }

        $oFile = $this->getKrynCore()->getKernel()->getRootDir(). '/../web/cache/admin.style-compiled.css';
        $md5String = '';

        $files = [];
        foreach ($this->getKrynCore()->getConfigs() as $bundleConfig) {
            foreach ($bundleConfig->getAdminAssetsPaths(false, '.*\.css', true, true) as $assetPath) {
                $path = $this->getKrynCore()->resolvePath($assetPath, 'Resources/public');
                if (file_exists($path)) {
                    $files[] = $assetPath;
                    $md5String .= ">$path<";
                }
            }
        }

        $handle = @fopen($oFile, 'r');
        $fileUpToDate = false;
        $md5Line = '/* ' . md5($md5String) . "*/\n";

        if ($handle) {
            $line = fgets($handle);
            fclose($handle);
            if ($line == $md5Line) {
                $fileUpToDate = true;
            }
        }

        if (!$fileUpToDate) {
            $content = $this->getKrynCore()->getUtils()->compressCss($files, substr($this->getKrynCore()->getSystemConfig()->getAdminUrl(), 1) . 'admin/backend/');
            $content = $md5Line . $content;
            file_put_contents($oFile, $content);
        }

        readfile($oFile);
        exit;
    }

    public function loadJs($printSourceMap = false)
    {
//        $root = getcwd();
//        $web = $this->getKrynCore()->getKernel()->getRootDir().'/../web';
//        chdir($web);
        $oFile = 'cache/admin.script-compiled.js';

        $files = array();
        $assets = array();
        $md5String = '';
        $newestMTime = 0;


//        chdir($root);
        foreach ($this->getKrynCore()->getConfigs() as $bundleConfig) {
            foreach ($bundleConfig->getAdminAssetsPaths(false, '.*\.js', true, true) as $assetPath) {
                $path = $this->getKrynCore()->resolvePath($assetPath, 'Resources/public');
                if (file_exists($path)) {
                    $assets[] = $assetPath;
                    $files[] = '--js ' . escapeshellarg($this->getKrynCore()->resolvePublicPath($assetPath));
                    $mtime = filemtime($path);
                    $newestMTime = max($newestMTime, $mtime);
                    $md5String .= ">$path.$mtime<";
                }
            }
        }
//        chdir($web);

        $ifModifiedSince = $this->getKrynCore()->getRequest()->headers->get('If-Modified-Since');
        if (isset($ifModifiedSince) && (strtotime($ifModifiedSince) == $newestMTime)) {
            // Client's cache IS current, so we just respond '304 Not Modified'.
            header('Last-Modified: '.gmdate('D, d M Y H:i:s', $newestMTime).' GMT', true, 304);
            exit;
        }

        header('Content-Type: application/x-javascript');
        $expires = 60 * 60 * 24 * 14; //2 weeks
        header('Pragma: public');
        header('Cache-Control: max-age=' . $expires);
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $newestMTime).' GMT');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');

        if (extension_loaded("zlib") && (ini_get("output_handler") != "ob_gzhandler")) {
            @ini_set("zlib.output_compression", 1);
        }

        $sourceMap = $oFile . '.map';
        $cmdTest = 'java -version';
        $closure = 'vendor/google/closure-compiler/compiler.jar';
        $compiler = escapeshellarg(realpath('../' . $closure));
        $cmd = 'java -jar ' . $compiler . ' --js_output_file ' . escapeshellarg($oFile);
        $returnVal = 0;
        $debugMode = false;

        if ($printSourceMap) {
            $content = file_get_contents($sourceMap);
            $content = str_replace('"bundles/', '"../../../bundles/', $content);
            $content = str_replace('"cache/admin.script-compiled.js', '"kryn/admin/backend/script.js', $content);
            echo $content;
            exit;
        }

        $handle = @fopen($oFile, 'r');
        $fileUpToDate = false;
        $md5Line = '//' . md5($md5String) . "\n";

        if ($handle) {
            $line = fgets($handle);
            fclose($handle);
            if ($line == $md5Line) {
                $fileUpToDate = true;
            }
        }

        if ($fileUpToDate) {
            $content = file_get_contents($oFile);
            echo substr($content, 35);
            exit;
        } else {
            if (!$debugMode) {
                system($cmdTest, $returnVal);
            }

            if (0 === $returnVal) {
                $cmd .= ' --create_source_map ' . escapeshellarg($sourceMap);
                $cmd .= ' --source_map_format=V3';

                $cmd .= ' ' . implode(' ', $files);
                $cmd .= ' 2>&1';
                $output = shell_exec($cmd);
                if (0 !== strpos($output, 'Unable to access jarfile')) {
                    if (false !== strpos($output, 'ERROR - Parse error')) {
                        echo 'alert(\'Parse Error\;);';
                        echo $output;
                        exit;
                    }
                    $content = file_get_contents($oFile);
                    $sourceMapUrl = '//@ sourceMappingURL=script-map';
                    $content = $md5Line . $content . $sourceMapUrl;
                    file_put_contents($oFile, $content);

                    echo substr($content, 35);
                    exit;
                }

            }


            foreach ($assets as $assetPath) {
                echo "/* $assetPath */\n\n";
                $path = $this->getKrynCore()->resolvePath($assetPath, 'Resources/public');
                echo file_get_contents($path);
            }
            exit;
        }

    }

    public function getMenus()
    {
        $entryPoints = array();

        foreach ($this->getKrynCore()->getConfigs() as $bundleName => $bundleConfig) {
            foreach ($bundleConfig->getAllEntryPoints() as $subEntryPoint) {
                $path = strtolower($bundleConfig->getBundleName()) . '/' . $subEntryPoint->getFullPath(true);

                if (substr_count($path, '/') <= 3) {
                    if ($subEntryPoint->isLink()) {
                        if ($this->getKrynCore()->getACL()->check('kryncmsbundle:entryPoint', '/' . $path)) {
                            $entryPoints[$path] = array(
                                'label' => $subEntryPoint->getLabel(),
                                'icon' => $subEntryPoint->getIcon(),
                                'fullPath' => $path,
                                'path' => $subEntryPoint->getPath(),
                                'type' => $subEntryPoint->getType(),
                                'system' => $subEntryPoint->getSystem(),
                                'level' => substr_count($path, '/')
                            );
                        }
                    }
                }
            }
        }

        return $entryPoints;
    }

    public function getChildMenus($code, $value)
    {
        $links = array();
        foreach ($value['children'] as $key => $value2) {

            if ($value2['children']) {

                $childs = $this->getChildMenus($code . "/$key", $value2);
                if (count($childs) == 0) {
                    //if ($this->getKrynCore()->checkUrlAccess($code . "/$key")) {
                    unset($value2['children']);
                    $links[$key] = $value2;
                    //}
                } else {
                    $value2['children'] = $childs;
                    $links[$key] = $value2;
                }

            } else {
                //if ($this->getKrynCore()->checkUrlAccess($code . "/$key")) {
                $links[$key] = $value2;
                //}
            }
            if ((!$links[$key]['type'] && !$links[$key]['children']) || $links[$key]['isLink'] === false) {
                unset($links[$key][$key]);
            }

        }

        return $links;
    }

}
