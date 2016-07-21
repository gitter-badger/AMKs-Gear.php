<?php
//$SOURCE_LICENSE$

/*<requires>*/
//GearHttpNotFoundException
/*</requires>*/

/*<namespace.current>*/
namespace gear\arch\view;
/*</namespace.current>*/
/*<namespace.use>*/
use gear\arch\http\exceptions\GearHttpNotFoundException;
/*</namespace.use>*/

/*<bundles>*/
/*</bundles>*/

/*<module>*/
class GearViewFileNotFoundException extends GearHttpNotFoundException
{
    public function __construct($action)
    {
        parent::__construct($action == null
            ? "404 - View file not found."
            : "404 - View file '$action' not found.");
    }
}
/*</module>*/
?>