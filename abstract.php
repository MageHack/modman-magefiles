<?php

/**
 * @copyright  Copyright (c) 2013 The Magento Hackathon (UK)
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author Bolaji Olubajo <bolaji.tolulope@redboxdigital.com>
 */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'abstract.php';

abstract class MageHack_Shell_Modman_Abstract extends Mage_Shell_Abstract
{

    protected $options = NULL;

    /**
     * Required options
     *
     * @var     array
     */
    protected $_options = array();

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

}