<?php
//$SOURCE_LICENSE$

/*<namespace.current>*/
namespace gear\fancypack\jdt;
/*</namespace.current>*/
/*<namespace.use>*/
use gear\arch\controller\GearController;
use gear\arch\helpers\GearHtmlHelper;
use gear\fancypack\jdt\http\results\GearJdtResult;
/*</namespace.use>*/

/*<bundles>*/
/*</bundles>*/

/*<generals>*/
GearController::setStaticExtensionMethod('jdtResult', function ($viewModel, $iteratable) {
    return new GearJdtResult($viewModel, $iteratable);
});
GearHtmlHelper::setStaticExtensionMethods([
    'jqueryDataTables' => function ($id, $options = null) {
        return new GearJdtResult($viewModel, $iteratable);
    }
]);
/*</generals>*/
?>