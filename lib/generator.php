<?php

/**
 * @copyright  Copyright (c) 2013 The Magento Hackathon (UK)
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author Bolaji Olubajo <bolaji.tolulope@redboxdigital.com>
 */
require_once 'helper.php';

class MageHack_Shell_Modman_Generator
{

    protected $_availableModes      = array('front', 'admin');
    protected $_defaultMode         = 'front';
    protected $_designConfigXmlStr  = NULL;
    protected $_helper              = NULL;

    /**
     * Return Magento design model
     * @return Mage_Core_Design_Package
     */
    protected function _getDesign()
    {
        $design = Mage::getSingleton('core/design_package');
        if ($this->_defaultMode == $this->_availableModes[1]) {
            $design->setArea('adminhtml');
        } else {
            $design->setArea('frontend');
        }
        return $design;
    }
    
    public function getDesign()
    {
        return $this->_getDesign();
    }

    /**
     * Return module template files
     * @return array
     */
    public function getTemplateFiles()
    {
        $data   = array();
        $regex  = '#template=[\s]*"(.*)[\s]*"#';
        preg_match_all($regex, $this->_designConfigXmlStr, $matches);
        if (!isset($matches[1])) {
            return $data;
        }
        $files  = $matches[1];
        foreach ($files as $file) {
            $data[] = $this->filterPath($this->_getTemplateFile($file));
        }
        return $data;
    }

    /**
     * Returns helper object
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
     * Returns path relative to Magento base directory
     * @param  $resourcePath
     * @return string
     */
    public function filterPath($resourcePath)
    {
        return $this->_getHelper()->filterPath($resourcePath);
    }
    
    /**
     * Sets front/admin mode which also sets current Magento design area
     * @param string $mode
     */
    public function setMode($mode)
    {
        if (in_array($mode, $this->_availableModes)) {
            $this->_defaultMode = $mode;
        } else {
            Mage::throwException('Mode not known');
        }
        if ($mode == $this->_availableModes[1]) {
            Mage::app(Mage_Core_Model_App_Area::AREA_ADMIN, 'store');
            Mage::app()->getLayout()->setArea('admin');
        } else {
            Mage::app(Mage_Core_Model_App_Area::AREA_FRONTEND, 'store');
            Mage::app()->getLayout()->setArea(Mage_Core_Model_App_Area::AREA_FRONTEND);
        }
    }

    /**
     * Return module template files
     * @param string $file
     * @return string
     */
    protected function _getTemplateFile($file)
    {
        $filename = $this->_getDesign()->getTemplateFilename($file);
        return $filename;
    }

    /**
     * Returns module design config file (front/admin)
     * @param type $file
     */
    public function setDesignConfigXmlFile($file)
    {
        $design     = $this->_getDesign();
        $filename   = $design->getLayoutFilename($file);
        if (!is_readable($filename)) {
            Mage::throwException(sprintf('Could not find %s'), $filename);
        }

        $this->_designConfigXmlStr = file_get_contents($filename);
    }

    /**
     * Returns the content of the design config file of the current
     * Magento area
     * @return string
     */
    public function getDesignConfigXmlFile()
    {
        return $this->_designConfigXmlStr;
    }

    /**
     * Returns module file mappings
     * @return array
     */
    public function getMappings()
    {
        $data = $this->_getHelper()->mergeArray(array(), $this->getTemplateFiles());
        $data = $this->_getHelper()->mergeArray($data, $this->getPageHeadFiles());
        return $data;
    }

    /**
     * Returns all javascript or css files used by module
     * @return string | array
     */
    public function getPageHeadFiles()
    {
        $data   = array();
        $xml    = simplexml_load_string(
            $this->_designConfigXmlStr
            , Mage::getConfig()->getModelClassName('core/layout_element')
        );
        $headNodes  = $xml->xpath("//reference[@name='head']");
        $block      = Mage::app()->getLayout()->createBlock('page/html_head');
        foreach ($headNodes as $headNode) {
            foreach ($headNode as $node) {
                $method = (string) $node['method'];
                $args   = (array) $node->children();
                unset($args['@attributes']);
                foreach ($args as $key => $arg) {
                    foreach ($arg as $subkey => $value) {
                        $arr[(string) $subkey] = $value->asArray();
                    }
                    if (!empty($arr)) {
                        $args[$key] = $arr;
                    }
                    call_user_func_array(array($block, $method), $args);
                }
            }
        }
        $js     = array();
        $css    = array();
        $html   = $block->getCssJsHtml();
        if (empty($html)) {
            return '';
        }
         preg_match_all('#href="(.*?)"#', $html, $matches1);
        if (isset($matches1[1])) {
            $css    = array_map(array($this, 'filterPath'), $matches1[1]);
            $data   = $this->_getHelper()->mergeArray($data, $css);
        }

        preg_match_all('#src="(.*)?"#', $html, $matches2);
        if (isset($matches2[1])) {
            $js     = array_map(array($this, 'filterPath'), $matches2[1]);
            $data   = $this->_getHelper()->mergeArray($data, $js);
        }
        return $data;
    }

}

