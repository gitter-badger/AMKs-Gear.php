<?php
//$SOURCE_LICENSE$

/*<namespaces>*/
namespace gear\arch\pal\file;
/*</namespaces>*/

/*<bundles>*/
/*</bundles>*/

/*<module>*/
class PALIniFileHelper
{
    public static function ParseIniFile($path, $processSections)
    {
        return parse_ini_file($path, $processSections);
    }
}
/*</module>*/
?>