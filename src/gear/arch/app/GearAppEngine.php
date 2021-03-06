<?php
//$SOURCE_LICENSE$

/*<namespace.current>*/
namespace gear\arch\app;
    /*</namespace.current>*/
/*<namespace.use>*/
use \Exception;
use gear\arch\controller\GearActionExecutor;
use gear\arch\controller\GearController;
use gear\arch\core\IGearContext;
use gear\arch\core\IGearEngineFactory;
use gear\arch\GearBundle;
use gear\arch\GearAutoload;
use gear\arch\core\GearAppContext;
use gear\arch\core\GearConfiguration;
use gear\arch\core\GearInvalidOperationException;
use gear\arch\http\GearHttpRequest;
use gear\arch\http\GearHttpResponse;
use gear\arch\GearLogger;
use gear\arch\http\GearHttpContext;
use gear\arch\GearInternalServerError;

/*</namespace.use>*/

/*<bundles>*/
GearBundle::Arch('app/GearAppEngineNotFoundException');
GearBundle::Arch('-GearConstants');
/*</bundles>*/

/*<module>*/

class GearAppEngine
{
    const Mvc = 'mvc';

    public static $GearConfigCache;

    /** @var IGearContext */
    private $context;
    /** @var GearConfiguration */
    private $configuration;
    /** @var GearApplication */
    private $applicationEntry;
    /** @var IGearEngineFactory */
    private $controllerFactory;
    /** @var IGearEngineFactory */
    private $actionResolverFactory;

    private $_startExecutionTime;
    private $_createExecutionTime;

    private static
        $createInitializers = [],
        $startInitializers = [];

    private function __construct(
        $context,
        $config,
        $routeFactory,
        $controllerFactory,
        $actionResolverFactory)
    {
        $this->context = $context;
        $this->configuration = $config;
        $this->controllerFactory = $controllerFactory;
        $this->actionResolverFactory = $actionResolverFactory;
    }

    /**
     * @return IGearContext
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param IGearContext $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }

    /**
     * @return GearConfiguration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @param GearConfiguration $configuration
     */
    public function setConfiguration($configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return GearApplication
     */
    public function getApplicationEntry()
    {
        return $this->applicationEntry;
    }

    /**
     * @param GearApplication $applicationEntry
     */
    public function setApplicationEntry($applicationEntry)
    {
        $this->applicationEntry = $applicationEntry;
    }

    /**
     * @return IGearEngineFactory
     */
    public function getControllerFactory()
    {
        return $this->controllerFactory;
    }

    /**
     * @param IGearEngineFactory $controllerFactory
     */
    public function setControllerFactory($controllerFactory)
    {
        $this->controllerFactory = $controllerFactory;
    }

    /**
     * @return IGearEngineFactory
     */
    public function getActionResolverFactory()
    {
        return $this->actionResolverFactory;
    }

    /**
     * @param IGearEngineFactory $actionResolverFactory
     */
    public function setActionResolverFactory($actionResolverFactory)
    {
        $this->actionResolverFactory = $actionResolverFactory;
    }

    static $debugSession = 0;

    /**
     * @return bool
     */
    public static function isDebug()
    {
        return defined('DEBUG') || (self::$debugSession > 0);
    }

    /**
     * @return int
     */
    public static function beginDebugSession()
    {
        return ++self::$debugSession;
    }

    /**
     * @param bool|true $explicit
     */
    public static function endDebugSession($explicit = true)
    {
        if ($explicit) {
            self::$debugSession = 0;
        } else {
            --self::$debugSession;
        }
    }

    /**
     * @return double
     */
    public function getCreateExecutionTime()
    {
        return $this->_createExecutionTime;
    }

    /**
     * @return double
     */
    public function getStartExecutionTime()
    {
        return $this->_startExecutionTime;
    }

    /**
     * @param string|null $engine
     * @return null
     */
    public function start($engine = null)
    {
        $result = null;
        try {
            $rStart = microtime(true);

            if (!isset($engine)) {
                $engine = $this->configuration->getValue(Gear_Key_Engine, Gear_Section_AppEngine, 'mvc');
            }

            if ($engine == static::Mvc || is_null($engine))
                $result = self::_startMvc0();
            $this->_startExecutionTime = (microtime(true) - $rStart);

            //if ($result == null) {
                //throw new GearAppEngineNotFoundException();
            //} else {
                return $result;
            //}
        } catch (Exception $ex) {
            self::_render500Error($this, $ex);
        }
    }

    /**
     * @param GearConfiguration $config
     * @param string $engine
     * @param string $defaultFactory
     * @return IGearEngineFactory
     * @throws GearInvalidOperationException
     */
    public static function getFactory(GearConfiguration $config, $engine, $defaultFactory)
    {
        $factoryClass = $config->getValue(Gear_Key_Factory, $engine, $defaultFactory);

        $factory = new $factoryClass();
        if ($factory == null)
            throw new GearInvalidOperationException();

        return $factory;
    }

    /**
     * @param array $bundles
     */
    public static function resolveBundles($bundles)
    {
        if (isset($bundles)) {
            $modules = [];
            $directories = [];
            $bundleStrs = explode(',', $bundles);

            foreach ($bundleStrs as $bundle) {

                if ($bundle == null || $bundle == '') continue;
                $bundle = trim($bundle);
                if (substr($bundle, strlen($bundle) - 1) == '*') {
                    $directories[] = substr($bundle, 0, strlen($bundle) - 1);
                } else {
                    $modules[] = $bundle;
                }
            }

            foreach ($directories as $dir) {
                if ($dir == null || $dir == '') continue;
                GearBundle::resolveAllPackageFromDirectory($dir);
            }

            GearBundle::resolveAllPackages($modules);
        }
    }

    /**
     * @param array $dependencies
     */
    public static function resolveDependencies($dependencies)
    {
        if (isset($dependencies)) {
            $modules = [];
            $directories = [];
            $dependents = explode(',', $dependencies);

            foreach ($dependents as $depend) {

                if ($depend == null || $depend == '') continue;
                $depend = trim($depend);
                if (substr($depend, strlen($depend) - 1) == '*') {
                    $directories[] = substr($depend, 0, strlen($depend) - 1);
                } else {
                    $modules[] = $depend;
                }
            }

            foreach ($directories as $dir) {
                if ($dir == null || $dir == '') continue;
                GearBundle::resolveAllUserModuleFromDirectory($dir, true, true);
            }

            GearBundle::resolveAllUserModules($modules, true, true);
        }
    }

    /**
     * @param $callback
     * @throws \GearInvalidOperationException
     */
    public static function registerCreateInitializer($callback)
    {
        if (!is_callable($callback)) {
            throw new \GearInvalidOperationException("Callable expected.");
        }
        self::$createInitializers[] = $callback;
    }

    /**
     * @param $callback
     * @throws \GearInvalidOperationException
     */
    public static function registerStartInitializer($callback)
    {
        if (!is_callable($callback)) {
            throw new \GearInvalidOperationException("Callable expected.");
        }
        self::$startInitializers[] = $callback;
    }

    /**
     * @param IGearContext $context
     * @return GearController
     */
    public function createController($context = null)
    {
        if ($context == null) {
            $context = $this->context;
        }

        return $this->controllerFactory->createEngine($context);
    }

    /**
     * @param GearController $controller
     * @return GearActionExecutor
     */
    public function createActionExecutor($controller)
    {
        $actionResolver = $this->actionResolverFactory->createEngine($this->context);

        $context = $this->context;
        $route = $context->getRoute();
        $request = $context->getRequest();
        $mvcContext = $route->getMvcContext();

        $actionName = $mvcContext->getActionName();

        return new GearActionExecutor(
            $controller,
            $context,
            $mvcContext,
            $request,
            $actionName,
            $actionResolver
        );
    }

    private function _startMvc0()
    {
        $controller = $this->createController();

        $actionResolver = $this->actionResolverFactory->createEngine($this->context);

        $context = $this->context;
        $route = $context->getRoute();
        $request = $context->getRequest();
        $mvcContext = $route->getMvcContext();

        $actionName = $mvcContext->getActionName();

        return $actionResolver->invokeAction(
            $controller,
            $context,
            $mvcContext,
            $request,
            $actionName);
    }

    private static $initialized = false;

    /**
     * @param string|null $configPath
     * @param int $type
     * @return GearConfiguration
     * @throws GearInvalidOperationException
     */
    public static function initEnvironment($configPath = null, $type = 0)
    {
        if (self::$initialized) {
            throw new GearInvalidOperationException();
        }

        if (is_null($configPath)) {
            $configPath = Gear_Default_ConfigPath;
        }
        $config = GearConfiguration::FromFile($configPath, $type);
        self::$GearConfigCache = $config;

        $debugMode = $config->getValue(Gear_Key_DebugMode, Gear_Section_AppEngine, false);
        if (boolval($debugMode) == true && !self::isDebug()) {
            define('DEBUG', 1);
        }

        $autoLoadMode = $config->getValue(Gear_Key_AutoLoading, Gear_Section_AppEngine, null);
        if ($autoLoadMode != null) {
            GearAutoload::register($autoLoadMode);
        }

        self::resolveBundles($config->getValue(Gear_Key_Bundles, Gear_Section_AppEngine));
        self::resolveDependencies($config->getValue(Gear_Key_Dependencies, Gear_Section_AppEngine));

        self::$initialized = true;

        return $config;
    }

    /**
     * @param GearConfiguration $config
     * @return GearAppEngine|null
     */
    public static function create($config = null)
    {
        $engine = null;
        try {
            $rStart = microtime(true);

            if (!self::$initialized) {
                $config = self::initEnvironment();
            }

            if ($config == null) {
                throw new GearInvalidOperationException();
            }

            $context = new GearAppContext($config);

            GearHttpContext::setCurrent($context);

            /** @var IGearEngineFactory $routeFactory */
            $routeFactory = self::getFactory($config, Gear_Section_Router, Gear_DefaultRouterFactory);
            $route = $routeFactory->createEngine($context);
            $context->registerService(Gear_ServiceRouterEngine, $route);

            /** @var IGearEngineFactory $binderFactory */
            $binderFactory = self::getFactory($config, Gear_Section_Binder, Gear_DefaultModelBinderFactory);
            $binder = $binderFactory->createEngine($context);

            $request = new GearHttpRequest($route);
            $response = new GearHttpResponse();
            $context->setRoute($route);
            $context->setBinder($binder);
            $context->setRequest($request);
            $context->setResponse($response);

            $loggers = $config->getValue(Gear_Key_Loggers, Gear_Section_AppEngine);
            if ($loggers != null) {
                $lgs = explode(',', $loggers);
                foreach ($lgs as $logger) {
                    $logger = trim($logger);
                    $loggerInstance = new $logger();
                    GearLogger::registerLogger($loggerInstance);
                }
            }

            $controllerFactory = self::getFactory($config, Gear_Section_Controller, Gear_DefaultControllerFactory);
            $actionResolverFactory = self::getFactory($config, Gear_Section_ActionResolver, Gear_DefaultActionResolverFactory);
            $viewEngineFactory = self::getFactory($config, Gear_Section_View, Gear_DefaultViewEngineFactory);

            $context->registerService(Gear_ServiceViewEngineFactory, $viewEngineFactory);

            $engine = new self(
                $context,
                $config,
                $routeFactory,
                $controllerFactory,
                $actionResolverFactory);

            $response->setHeaderLegacy(Gear_PoweredResponseHeader);

            $applicationEntry = $config->getValue(Gear_Key_ApplicationEntry, Gear_Section_AppEngine);
            if (isset($applicationEntry)) {
                /** @var GearApplication $applicationEntryClass */
                $applicationEntryClass = new $applicationEntry();
                $engine->applicationEntry = $applicationEntryClass;

                $applicationEntryClass->appCreate($context, $engine);

                $applicationEntryClass->configRoute($context, $route, $route->getConfigurator());
            }

            $engine->_createExecutionTime = (microtime(true) - $rStart);
            return $engine;
        } catch (Exception $ex) {
            self::_render500Error($engine, $ex);
        }
    }

    static function _render500Error($engine, $ex)
    {
        if ($engine != null) {
            /** @var GearApplication $app */
            $app = $engine->applicationEntry;
            if ($app != null) {
                $app->onExceptionOccurred($ex);
            }
        }

        GearBundle::Arch(Gear_500InternalServerErrorPageName);

        GearInternalServerError::Render($ex);
    }
}

/*</module>*/
?>