<?php

class MageHack_Shell_Modman_Generator
{

    protected $_availableModes = array('front', 'admin');
    protected $_defaultMode = 'front';
    protected $_designConfigXmlStr = NULL;

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

    public function getTemplateFiles()
    {
        $data = array();
        $regex = '#template=[\s]*"(.*)[\s]*"#';
        preg_match_all($regex, $this->_designConfigXmlStr, $matches);
        if (!isset($matches[1])) {
            return $data;
        }
        $files = $this->_getOnlyCustomFiles($matches[1]);
        foreach ($files as $file) {
            $data[] = $this->_filterPath($this->_getTemplateFile($file));
        }
        return $data;
    }

    public function _getOnlyCustomFiles($files)
    {
        return $files;
    }

    protected function _filterPath($resourcePath)
    {
        $baseUrl = str_replace('files.php/', '', Mage::getBaseUrl());
        $correctPath = str_replace($baseUrl, '', $resourcePath);
        $baseDir = str_replace('files.php' . DIRECTORY_SEPARATOR, '', Mage::getBaseDir());
        $correctPath = str_replace($baseDir, '', $correctPath);
        return preg_replace('#^/#', '', $correctPath);
    }

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

    protected function _getTemplateFile($file)
    {
        $filename = $this->_getDesign()->getTemplateFilename($file);
        return $filename;
    }

    public function setDesignConfigXmlFile($file)
    {
        $design = $this->_getDesign();
        $filename = $design->getLayoutFilename($file);
        if (!is_readable($filename)) {
            Mage::throwException(sprintf('Could not find %s'), $filename);
        }

        $this->_designConfigXmlStr = file_get_contents($filename);
    }

    public function getMappings()
    {
        $data = array();
        $data = array_merge($data, $this->getTemplateFiles());
        $data = array_merge($data, $this->getPageHeadFiles());
        return $data;
    }

    public function getPageHeadFiles()
    {
        $data = array();
        $xml = simplexml_load_string($this->_designConfigXmlStr, Mage::getConfig()->getModelClassName('core/layout_element'));
        $headNodes = $xml->xpath("//reference[@name='head']");
        $block = Mage::app()->getLayout()->createBlock('page/html_head');
        foreach ($headNodes as $headNode) {
            foreach ($headNode as $node) {
                $method = (string) $node['method'];
                $args = (array) $node->children();
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
        $js = array();
        $css = array();
        $html = $block->getCssJsHtml();
        if (empty($html)) {
            return '';
        }
        preg_match_all('#href="(.*)"#', $html, $matches1);
        if (isset($matches1[1])) {
            $css = array_map(array($this, '_filterPath'), $matches1[1]);
            $data = array_merge($data, $css);
        }

        preg_match_all('#src="(.*)"#', $html, $matches2);
        if (isset($matches2[1])) {
            $js = array_map(array($this, '_filterPath'), $matches2[1]);
            $data = array_merge($data, $js);
        }
        return $data;
    }

    public function getLocaleFiles()
    {
        
    }

    public function getLayoutUpdateFile($type = 0)
    {
        //get admin and frontend layout update file
    }

}
