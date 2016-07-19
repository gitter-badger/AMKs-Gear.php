<?php

define('BUILD_Module_Content_Begin', '/*<module>*/');
define('BUILD_Module_Content_End', '/*</module>*/');

define('BUILD_NamespaceCurrent_Content_Begin', '/*<namespace.current>*/');
define('BUILD_NamespaceCurrent_Content_End', '/*</namespace.current>*/');
define('BUILD_NamespaceUse_Content_Begin', '/*<namespace.use>*/');
define('BUILD_NamespaceUse_Content_End', '/*</namespace.use>*/');

define('BUILD_Bundles_Content_Begin', '/*<bundles>*/');
define('BUILD_Bundles_Content_End', '/*</bundles>*/');

define('BUILD_Requires_Content_Begin', '/*<requires>*/');
define('BUILD_Requires_Content_End', '/*</requires>*/');

define('BUILD_Generals_Content_Begin', '/*<generals>*/');
define('BUILD_Generals_Content_End', '/*</generals>*/');

$BUILD_rootDirectory = dirname(__FILE__);

$BUILD_root = dirname(__FILE__) . "\\src";
$BUILD_archRoute = "$BUILD_root\\gear\\arch";

$BUILD_output = "$BUILD_rootDirectory\\bin";
$BUILD_outputName = 'gear.php';
$BUILD_outputCompressedName = 'gear.c.php';

$BUILD_outputPharName = 'gear.phar';
$BUILD_outputPharCompressedName = 'gear.c.phar';

function BUILD_getAllModulesIn($path)
{
    $dI = new RecursiveDirectoryIterator($path);
    $files = array();
    foreach (new RecursiveIteratorIterator($dI) as $dir) {
        $fName = $dir->getFilename();
        $path = $dir->getPath();
        $dir = $dir->getPathname();
        //if($path=='.'&&$fName==$fileName)continue;
        if ($fName == '.' || $fName == '..') continue;
        //if($dir==$zipFile)continue;
        //$files[]=$dir;
        $files[] = $dir;
    }
    return $files;
}

function BUILD_getModuleContent($path)
{
    return file_get_contents($path);
}

function BUILD_getSection(&$content, $begin, $end)
{
    $startsAt = strpos($content, $begin);

    if (!is_numeric($startsAt) || $startsAt < 0) return '';
    $startsAt += strlen($begin);

    $endsAt = strpos($content, $end, $startsAt);

    if (is_numeric($endsAt) && $endsAt >= 0)
        $result = substr($content, $startsAt, $endsAt - $startsAt);
    else
        $result = '';

    return $result;
}


function GetFileName($p)
{
    $d = strrpos($p, '.');
    if (!is_bool($d) && $d >= 0) return ($d < strlen($p) - 1 ? substr($p, 0, $d) : '');
    return null;
}
function BUILD_expandFiles($files, $requireStart, $requireEnd)
{
    $result = [];
    foreach ($files as $file) {
        $record = [
            'path' => $file,
            'name' => GetFileName(basename($file)),
            'content' => BUILD_getModuleContent($file)
        ];

        $tempRequires = explode("\n", BUILD_getSection($record['content'], $requireStart, $requireEnd));
        $allRequires = [];
        foreach ($tempRequires as $r) {
            if ($r == '' || is_null($r) || $r == "\r" || $r == "\n") continue;
            $allRequires[] = trim(trim($r, '/'));
        }
        $record['requires'] = $allRequires;
        $result[] = $record;
    }
    return $result;
}

function BUILD_satisfiedAllRequires($includedModules, $module)
{
    foreach ($module['requires'] as $require) {
        $exists = false;
        foreach ($includedModules as $mod) {
            if ($mod['name'] == $require) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            return false;
        }
    }
    return true;
}

function BUILD_resolveDependencies($modules) {
    $BUILD_exportedModules = [];
    $totalModulesCount = count($modules);
    for ($i = 0; $i < $totalModulesCount; $i++) {
        $module = $modules[$i];
        if (!isset($module['requires']) || count($module['requires']) == 0 || $module['requires'] == null) {
            $BUILD_exportedModules[] = $module;
            unset($modules[$i]);
        }
    }
    $totalModulesCount = count($modules);
    while ($totalModulesCount > 0) {
        foreach($modules as $index => $module) {
            if (BUILD_satisfiedAllRequires($BUILD_exportedModules, $module)) {
                unset($modules[$index]);
                array_push($BUILD_exportedModules, $module);
            }
        }
        $totalModulesCount = count($modules);
        //break;
    }
    return $BUILD_exportedModules;
}

$BUILD_modules = BUILD_getAllModulesIn($BUILD_archRoute);
$BUILD_modules = BUILD_expandFiles($BUILD_modules, BUILD_Requires_Content_Begin, BUILD_Requires_Content_End);

usort($BUILD_modules,
    function ($a, $b) {
        return basename($a['path']) > basename($b['path'])
            ? 1 : -1;
    });

$BUILD_exportedModules = BUILD_resolveDependencies($BUILD_modules);


$BUILD_totalModule = '';
$BUILD_totalGenerals = '';
$BUILD_totalBundles = '';
foreach ($BUILD_exportedModules as $dir) {
    $moduleContent = $dir['content'];

    $moduleBody = BUILD_getSection($moduleContent, BUILD_Module_Content_Begin, BUILD_Module_Content_End);
    $moduleGenerals = BUILD_getSection($moduleContent, BUILD_Generals_Content_Begin, BUILD_Generals_Content_End);
    $moduleNamespaceCurrent = BUILD_getSection($moduleContent, BUILD_NamespaceCurrent_Content_Begin, BUILD_NamespaceCurrent_Content_End);
    $moduleNamespaceUse = BUILD_getSection($moduleContent, BUILD_NamespaceUse_Content_Begin, BUILD_NamespaceUse_Content_End);
    $moduleBundles = BUILD_getSection($moduleContent, BUILD_Bundles_Content_Begin, BUILD_Bundles_Content_End);

    if(trim($moduleBody) != '') $BUILD_totalModule .= "$moduleBody\n";
    if(trim($moduleGenerals) != '') $BUILD_totalGenerals .= "$moduleGenerals\n";
    if(trim($moduleBundles) != '') $BUILD_totalBundles .= "$moduleBundles\n";
}

$BUILD_totalContentNormal = "<?php\n
define('Gear_IsPackaged', true);

/* Modules: */
$BUILD_totalModule

/* Generals: */
$BUILD_totalGenerals
";

$BUILD_totalContentCompressed = "<?php\n
define('Gear_IsPackaged', true);
define('Gear_IsCompressedBundle', true);

/* Modules: */
$BUILD_totalModule

/* Generals: */
$BUILD_totalGenerals
";

file_put_contents("$BUILD_output\\$BUILD_outputName", $BUILD_totalContentNormal);
//file_put_contents("$BUILD_output\\$BUILD_outputCompressedName", $BUILD_totalContentCompressed);