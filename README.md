##Modman MageFiles
This module helps generate <a target="_blank" href="https://github.com/colinmollenhour/modman/wiki/Tutorial">modman</a> mapping files for Magento modules.

Also useful to quickly view all the files used by a Magento module.


Usage: php -f magehack/modman_files.php -- --module_name=Mage_Catalog --prefix="mycustom_dir"

Options:
--module_name Custom module name (REQUIRED) e.g Namespace_Modulename

--prefix  Your module module base directory from Magento root directory

NOTE:
Does not support Magento shell script modules like this module :)

######@TODO
Support Library files
Support image files used in templates
Support images used in CSS files

######Author 
######Bolaji Olubajo <toluolubajo@gmail.com>