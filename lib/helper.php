<?php

/**
 * @copyright  Copyright (c) 2013 The Magento Hackathon (UK)
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author Bolaji Olubajo <bolaji.tolulope@redboxdigital.com>
 * 
 * Helper Class
 */
class MageHack_Shell_Modman_Helper
{

    /**
     * Returns file relative path to Magento root directory
     * @param type $resourcePath
     * @return type
     */
    public function filterPath($resourcePath)
    {
        $baseUrl        = str_replace('modman_files.php/', '', Mage::getBaseUrl());
        $correctPath    = str_replace($baseUrl, '', $resourcePath);
        $baseDir        = str_replace('modman_files.php' . DIRECTORY_SEPARATOR, '', Mage::getBaseDir());
        $correctPath    = str_replace($baseDir, '', $correctPath);
        return preg_replace('#^/#', '', $correctPath);
    }

    /**
     * Wrapper method for array_merge
     * @param array $initial Initial array to merge.
     * @param array | string $toMerge 
     * @return array
     */
    public function mergeArray($initial, $toMerge)
    {
        if (is_array($initial) && is_array($toMerge)) {
            return array_merge($initial, $toMerge);
        } elseif (is_array($initial) && is_string($toMerge)) {
            return array_merge($initial, array($toMerge));
        }
        return $initial;
    }

    /**
     * Returns directories and files for mapping
     * @param array $files
     * @return array
     */
    public function getDirAndFiles($files)
    {
        $dirs   = array();
        $maps   = array();
        $files  = array_unique($files);
        foreach ($files as $file) {
            $dirname = dirname(Mage::getBaseDir() . DIRECTORY_SEPARATOR . $file);
            $dirs[$dirname][] = $file;
        }
        foreach ($dirs as $dirname => $filesInDir) {
            if (empty($dirname)) {
                continue;
            }
            try {
                $fi = new FilesystemIterator($dirname, FilesystemIterator::SKIP_DOTS);
            } catch (Exception $e) {
                continue;
            }
            $count = iterator_count($fi);
            if (count($filesInDir) === $count) {
                $maps = $this->mergeArray($maps, $this->filterPath($dirname));
            } else {
                $maps = $this->mergeArray($maps, $filesInDir);
            }
        }
        return $maps;
    }

}
