<?php
//$SOURCE_LICENSE$

/*<namespace.current>*/
namespace gear\arch\http;
/*</namespace.current>*/

/*<bundles>*/
/*</bundles>*/

/*<module>*/
interface IGearHttpRequest
{
    function getValue($name);
    function getMethod();
    function accepts();
    function getAllValues();
    function &getCurrentMethodValues();
}
/*</module>*/
?>