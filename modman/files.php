<?php

/**
 * @copyright  Copyright (c) 2013 The Magento Hackathon (UK)
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author Bolaji Olubajo <bolaji.tolulope@redboxdigital.com>
 */
require_once 'generator.php';
require_once 'helper.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'abstract.php';
require dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Mage.php';

if (!Mage::isInstalled()) {
    echo "Application is not installed yet, please complete install wizard first.";
    exit(0);
}
Mage::app();

class MageHack_Shell_Modman_Files extends MageHack_Shell_Modman_Abstract
{

    /**
     * Required options
     *
     * @var     array
     */
    protected $_options = array(
      'module_name' => array(
        'required' => true,
        'validator' => array(
          'Zend_Validate_NotEmpty',
        )),
      'prefix' => array(
        'required' => false,
        'validator' => array(
          'Zend_Validate_NotEmpty',
        ),
      )
    );
    protected $_helper = NULL;

    public function run()
    {
        $options = $this->_getOptions();
        $moduleName = $options->getModuleName();
        $prefix = $options->getPrefix();
        $files = $this->_getAllFiles($moduleName);
        if ($prefix) {
            $this->_getFileMappings($files, $prefix);
        } else {
            $this->_getFileMappings($files);
        }
    }

    protected function _moduleExist()
    {
        
    }

    protected function _getFrontLayoutUpdateFile(Mage_Core_Model_Layout_Element $config)
    {
        try {
            return (array) $config->frontend->layout->updates;
        } catch (Exception $e) {
            return array();
        }
    }

    protected function _getHelper()
    {
        if (!$this->_helper) {
            $this->_helper = new MageHack_Shell_Modman_Helper();
        }
        return $this->_helper;
    }

    protected function _getAdminLayoutUpdateFile(Mage_Core_Model_Layout_Element $config)
    {
        try {
            return (array) $config->adminhtml->layout->updates;
        } catch (Exception $e) {
            return array();
        }
    }

    protected function _getLocaleFiles(Mage_Core_Model_Layout_Element $config, $moduleName, $area = 'frontend')
    {
        if (!isset($config->$area->translate->modules)) {
            return array();
        }
        $nodes = $config->$area->translate->modules->children();
        $baseLocaleDir = Mage::getBaseDir('locale');
        if (isset($nodes)) {
            foreach ($nodes as $moduleName => $info) {
                $info = $info->asArray();
                if (isset($info['files'])) {
                    $fileNames = $info['files'];
                    foreach ($fileNames as $fileName) {
                        $file = $baseLocaleDir . DS . Mage::app()->getLocale()->getLocaleCode() . DS . $fileName;
                        if (!is_readable($file)) {
                            $file = $baseLocaleDir . DS . 'en_US' . DS . $fileName;
                        }
                        return $this->_getHelper()->filterPath($file);
                    }
                }
            }
        }
        return array();
    }

    protected function _getAllFiles($moduleName)
    {
        $configFile = file_get_contents(Mage::getModuleDir('etc', $moduleName) . DS . 'config.xml');
        $config = simplexml_load_string($configFile, Mage::getConfig()->getModelClassName('core/layout_element'));
        $frontUpdateFiles = $this->_getFrontLayoutUpdateFile($config);
        $generator = new MageHack_Shell_Modman_Generator();
        $fileMappings = $this->_getHelper()->mergeArray(array(), $this->_getDefaultMappings($config, $moduleName));
        $frontFileMappings = array();
        if ($frontUpdateFiles) {
            foreach ($frontUpdateFiles as $node) {
                $generator->setMode('front');
                $generator->setDesignConfigXmlFile((string) $node->file);
                $frontFileMappings = $this->_getHelper()->mergeArray($frontFileMappings, $generator->getMappings());
            }
            $fileMappings = $this->_getHelper()->mergeArray($fileMappings, $frontFileMappings);
            $fileMappings = $this->_getHelper()->mergeArray($fileMappings, $this->_getHelper()->filterPath($this->_getLocaleFiles($config, $moduleName, 'frontend')));
        }
        $adminUpdateFiles = $this->_getAdminLayoutUpdateFile($config);

        $adminFileMappings = array();
        if ($adminUpdateFiles) {
            $generator = new MageHack_Shell_Modman_Generator();
            foreach ($adminUpdateFiles as $node) {
                $generator->setMode('admin');
                $generator->setDesignConfigXmlFile((string) $node->file);
                $adminFileMappings = $this->_getHelper()->mergeArray($adminFileMappings, $generator->getMappings(1));
            }
            $adminFileMappings = $this->_getHelper()->mergeArray(
                $adminFileMappings
                , $this->_getHelper()->filterPath($this->_getLocaleFiles($config, $moduleName, 'admin'))
            );
            $fileMappings = $this->_getHelper()->mergeArray($fileMappings, $adminFileMappings);
        }
        return $fileMappings;
    }

    protected function _getFileMappings($files, $prefix = '')
    {
        foreach ($files as $file) {
            $actual = str_replace($prefix, '', $file);
            echo $this->_cliGreen("$file    $actual");
        }
    }

    protected function _getDefaultMappings($config, $moduleName)
    {
        $data = $this->_getHelper()->mergeArray(array(), $this->_getModuleCoreFiles($moduleName));
        $data = $this->_getHelper()->mergeArray($data, $this->_getLocaleFiles($config, $moduleName));
        return $data;
    }

    protected function _getModuleCoreFiles($moduleName)
    {
        $file = $this->_getHelper()->filterPath(dirname(Mage::getConfig()->getModuleDir('etc', $moduleName)));
        return $file;
    }

    /**
     * Retrieve Usage Help Message
     *
     * @return  string
     * @author Bolaji Olubajo <bolaji.tolulope@redboxdigital.com>
     */
    public function usageHelp()
    {
        return <<<USAGE
Creates modman file mappings to be copied into a modman file
Usage: php -f magehack/modman/files.php -- --module_name=Mage_Catalog --prefix="mycustom_dir"
Options:
--module_name Custom module name (REQUIRED) e.g Namespace_Modulename
--prefix  Your module module base directory from Magento root directory
     
USAGE;
    }

}

$shell = new MageHack_Shell_Modman_Files();
$shell->run();
