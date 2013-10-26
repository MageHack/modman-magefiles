<?php

require_once 'generator.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'abstract.php';
require dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Mage.php';

if (!Mage::isInstalled()) {
    echo "Application is not installed yet, please complete install wizard first.";
    exit(0);
}

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
        ),
      )
    );

    public function run()
    {
        $options = $this->_getOptions();
        $moduleName = $options->getModuleName();
        $configFile = file_get_contents(Mage::getModuleDir('etc', $moduleName) . DS . 'config.xml');
        $config = simplexml_load_string($configFile, Mage::getConfig()->getModelClassName('core/layout_element'));
        $frontUpdateFiles = $this->_getFrontLayoutUpdateFile($config);
        $generator = new MageHack_Shell_Modman_Generator();
        $fileMappings = array();
        if ($frontUpdateFiles) {
            foreach ($frontUpdateFiles as $node) {
                $generator->setMode('front');
                $generator->setDesignConfigXmlFile((string)$node->file);
                $frontFileMappings = $generator->getMappings();
            }
            $fileMappings[] = $frontFileMappings;
        }
        $adminUpdateFiles = $this->_getAdminLayoutUpdateFile($config);

        if ($adminUpdateFiles) {
            $generator = new MageHack_Shell_Modman_Generator();
            foreach ($adminUpdateFiles as $node) {
                $generator->setMode('admin');
                $generator->setDesignConfigXmlFile((string)$node->file);
                $adminFileMappings = $generator->getMappings(1);
            }
            $fileMappings[] = $adminFileMappings;
        }
    }

    protected function _getFrontLayoutUpdateFile(Mage_Core_Model_Layout_Element $configFile)
    {
        return (array) $configFile->frontend->layout->updates;
        
    }

    protected function _getAdminLayoutUpdateFile(Mage_Core_Model_Layout_Element $configFile)
    {
        return (array) $configFile->adminhtml->layout->updates;
    }

}

$shell = new MageHack_Shell_Modman_Files();
$shell->run();
