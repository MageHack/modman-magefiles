<?php

/**
 * @copyright  Copyright (c) 2013 The Magento Hackathon (UK)
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author Bolaji Olubajo <bolaji.tolulope@redboxdigital.com>
 */
class MageHack_Shell_Modman_Helper
{

    public function filterPath($resourcePath)
    {
        $baseUrl = str_replace('files.php/', '', Mage::getBaseUrl());
        $correctPath = str_replace($baseUrl, '', $resourcePath);
        $baseDir = str_replace('files.php' . DIRECTORY_SEPARATOR, '', Mage::getBaseDir());
        $correctPath = str_replace($baseDir, '', $correctPath);
        return preg_replace('#^/#', '', $correctPath);
    }

    public function mergeArray($initial, $toMerge)
    {
        if (is_array($initial) && is_array($toMerge)) {
            return array_merge($initial, $toMerge);
        }
        elseif(is_array($initial) && is_string($toMerge)){
            return array_merge($initial, array($toMerge));
        }
        return $initial;
    }

}