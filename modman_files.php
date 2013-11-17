<?php

/**
 * @copyright  Copyright (c) 2013 The Magento Hackathon (UK)
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author Bolaji Olubajo <bolaji.tolulope@redboxdigital.com>
 */
include 'modman-magefiles' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helper.php';
include 'abstract.php';


class MageHack_Shell_Modman_Files extends Mage_Shell_Abstract
{

    /**
     * Required options
     *
     * @var     array
     */
    protected $_options = array(
      'module_name' => array(
        'required'  => true,
        'validator' => array(
          'Zend_Validate_NotEmpty',
        )),
      'prefix' => array(
        'required'  => false,
        'validator' => array(
          'Zend_Validate_NotEmpty',
        ),
      ),
      'strip' => array(
        'required'  => false,
        'validator' => array(
          'Zend_Validate_NotEmpty',
        ),
      )
    );
    protected $_helper = NULL;

    protected $options = NULL;

    public function run()
    {
        $options    = $this->_getOptions();
        if(!$options){
            echo $this->usageHelp();
            return;
        }
        $moduleName = $options->getModuleName();
        if(!$this->_moduleExist($moduleName)){
            echo $this->_cliRed(sprintf('Error: could not find specified module %s', $moduleName));
            echo $this->usageHelp();
            exit(0);
        }
        $prefix     = $options->getPrefix();
        $strip      = ($options->getStrip()) ? $options->getStrip() : '';
        $files      = $this->_getAllFiles($moduleName);
        if ($prefix) {
            $this->_outputMappings($strip, $files, $prefix);
        } else {
            $this->_outputMappings($strip, $files);
        }
    }

    /**
     * Checks if a module exist
     * @param string $moduleName should be in the form Namespace_ModuleName
     * @return boolean
     */
    protected function _moduleExist($moduleName)
    {
        $moduleConfigFile = Mage::getModuleDir('etc', $moduleName) . DS . 'config.xml';
        if(is_readable($moduleConfigFile)){
            return true;
        }
        return false;
    }

    /**
     * Retuurn 
     * @param Mage_Core_Model_Layout_Element $config
     * @return array
     */
    protected function _getFrontLayoutUpdateFile(Mage_Core_Model_Layout_Element $config)
    {
        try {
            return (array) $config->frontend->layout->updates;
        } catch (Exception $e) {
            return array();
        }
    }

    /**
     * Returns helper class
     * @return MageHack_Shell_Modman_Helper
     */
    protected function _getHelper()
    {
        if (!$this->_helper) {
            $this->_helper = new MageHack_Shell_Modman_Helper();
        }
        return $this->_helper;
    }

    /**
     * Returns module admin layout file if one exist
     * @param Mage_Core_Model_Layout_Element $config
     * @return array
     */
    protected function _getAdminLayoutUpdateFile(Mage_Core_Model_Layout_Element $config)
    {
        try {
            return (array) $config->adminhtml->layout->updates;
        } catch (Exception $e) {
            return array();
        }
    }

     /**
     * Returns module locale file if one exist
     * @param Mage_Core_Model_Layout_Element $config
     * @param string $area  Magento design area
     * @return array
     */
    protected function _getLocaleFiles(Mage_Core_Model_Layout_Element $config, $moduleName, $area = 'frontend')
    {
        if (!isset($config->$area->translate->modules)) {
            return array();
        }
        $nodes          = $config->$area->translate->modules->children();
        $baseLocaleDir  = Mage::getBaseDir('locale');
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

    /**
     * Generates all retrievable module files
     * @param string $moduleName
     * @return array
     */
    protected function _getAllFiles($moduleName)
    {
        $configFile     = file_get_contents(Mage::getModuleDir('etc', $moduleName) . DS . 'config.xml');
         $config        = simplexml_load_string(
            $configFile, Mage::getConfig()->getModelClassName('core/layout_element')
        );
        $frontUpdateFiles = $this->_getFrontLayoutUpdateFile($config);
        $generator      = new MageHack_Shell_Modman_Generator();
        $fileMappings   = $this->_getHelper()->mergeArray(array(), $this->_getDefaultMappings($config, $moduleName));
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

    /**
     * outputs module modman file mappings
     * @param array $files
     * @param string $prefix
     */
    protected function _outputMappings($strip, $files, $prefix = '')
    {
        $files = $this->_getHelper()->getDirAndFiles($files);
        foreach ($files as $actualfile) {
            $tempfile = str_replace($strip, '', $actualfile);
            $actual = $tempfile;
            if (!empty($prefix)) {
                $actual = preg_replace('#/{2,}#', '/', $prefix . DIRECTORY_SEPARATOR . $actual);
            }
            echo "$actual    $actualfile" . PHP_EOL;
        }
    }

    /**
     * Returns the trivial module files e.g locale and module core files 
     * i.e in Namespace/ModuleName/app/code/local|community
     * @param string $config
     * @param string $moduleName
     * @return array
     */
    protected function _getDefaultMappings($config, $moduleName)
    {
        $data = array("app/etc/modules/$moduleName.xml");
        $data = $this->_getHelper()->mergeArray($data, $this->_getModuleCoreFiles($moduleName));
        $data = $this->_getHelper()->mergeArray($data, $this->_getLocaleFiles($config, $moduleName));
        return $data;
    }

    /**
     * Return module core files in Namespace/ModuleName/app/code/local|community
     * @param type $moduleName
     * @return type
     */
    protected function _getModuleCoreFiles($moduleName)
    {
        $file = $this->_getHelper()->filterPath(dirname(Mage::getConfig()->getModuleDir('etc', $moduleName)));
        return $file;
    }

    protected function _isEmpty($value)
    {
        return (empty($value) || !(int) $value > 0);
    }

    /**
     * Return array of CLI options
     *
     * @return  array
     * @author  Bolaji Olubajo
     */
    protected function _getCliOptions()
    {
        $cliOptions = array();

        foreach ($_SERVER['argv'] as $arg) {
            if (substr($arg, 0, 2) == '--' && strpos($arg, '=') !== false) {
                $option = substr($arg, 2);
                $option = explode('=', $option);

                $cliOptions[$option[0]] = $option[1];
            }
        }

        return $cliOptions;
    }

    /**
     * Echo message in red colour
     *
     * @param   string $message
     * @return  string
     * @author  Bolaji Olubajo <bolaji.olubajo@redboxdigital.com>
     */
    protected function _cliRed($message)
    {
        return sprintf("\033[31m%s\033[37m", $message) . PHP_EOL;
        ;
    }

    /**
     * Echo message in green colour
     *
     * @param   string $message
     * @return  string
     * @author  Bolaji Olubajo <bolaji.olubajo@redboxdigital.com>
     */
    protected function _cliGreen($message)
    {
        return sprintf("\033[32m%s\033[37m", $message) . PHP_EOL;
        ;
    }

    /**
     * Get options
     *
     * @return  false|Varien_Object
     * @author  Bolaji Olubajo <bolaji.olubajo@redboxdigital.com>
     */
    protected function _getOptions()
    {
        if (empty($this->options)) {
            $options = new Varien_Object();
            $cliOptions = $this->_getCliOptions();

            foreach ($this->_options as $optionId => $optionConfig) {
                $option = array_key_exists($optionId, $cliOptions) ? $cliOptions[$optionId] : false;

                /* Option is required */
                if ($option === false && $optionConfig['required'] === true) {
                    echo sprintf($this->_cliRed("Option [%s] is required"), $optionId);
                    return false;
                } else if ($option !== false) {
                    foreach ($optionConfig['validator'] as $validatorClass) {
                        /* Check if validator is available */
                        if (class_exists($validatorClass)) {
                            $validator = new $validatorClass;
                        } else {
                            echo sprintf($this->_cliRed("Validator [%s] cannot be invoked"), $validatorClass);
                            return false;
                        }

                        $validate = $option;

                        /* Get file real path if option is a filename */
                        if ($validator instanceof Zend_Validate_File_Exists) {
                            $validator->setDirectory(dirname(realpath($option)));
                            $validate = basename(realpath($option));
                        }

                        /* Required option did not pass validation */
                        if (!$validator->isValid($validate)) {
                            echo sprintf($this->_cliRed("Option [%s] did not pass validation [%s] for value [%s]"), $optionId, $validatorClass, $option);
                            return false;
                        }

                        /* Get file real path if option is a filename */
                        if ($validator instanceof Zend_Validate_File_Exists) {
                            $option = realpath($option);
                        }
                    }
                }

                $this->options = $options->setData($optionId, $option);
            }
        }

        return $this->options;
    }

    protected function _prepareEnvironment()
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
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
Generates file mappings for a Modman or Composer Magento module
Usage: php -f magehack/modman_files.php -- --module_name=Mage_Catalog --prefix="mycustom_dir"
Options:
--module_name Custom module name (REQUIRED) e.g Namespace_ModuleName
--prefix  Your module module base directory from Magento root directory
--strip  string to strip out of mapping
     
USAGE;
    }

}

$shell = new MageHack_Shell_Modman_Files();
$shell->run();
