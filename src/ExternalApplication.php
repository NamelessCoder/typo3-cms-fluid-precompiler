<?php
namespace NamelessCoder\CmsFluidPrecompiler;

use Composer\Autoload\ClassLoader;
use TYPO3\CMS\Backend\Console\Application;
use TYPO3\CMS\Core\Core\Bootstrap;

/**
 * Entry point for the TYPO3 environment to load, but not
 * process any request. Useful as application bootstrap from
 * within third party code using TYPO3 components, when
 * those components depend on TYPO3 environment globals,
 * constants etc.
 *
 * In other words: bootstrapping-only Application for third
 * party console commands, usually vendor/bins.
 */
class ExternalApplication extends Application
{

    /**
     * Number of subdirectories where the entry script is located, relative to PATH_site
     * @var int
     */
    protected $entryPointLevel = 0;

    /**
     * Constructs an instance of the application. Does nothing else,
     * since nothing else is required. The external application will
     * be handling the request, but with TYPO3 environment at hand.
     *
     * @return void
     */
    public static function bootstrap()
    {
        $entry = $_SERVER['PHP_SELF'];
        $entryDir = dirname($entry);
        while (!file_exists($entryDir . '/vendor/autoload.php') && $entryDir !== '/') {
            $entryDir = dirname($entryDir);
        }
        $classLoader = require $entryDir . '/vendor/autoload.php';
        new static($classLoader);
    }

    /**
     * ExternalApplication constructor.
     *
     * @param ClassLoader $classLoader
     */
    public function __construct(ClassLoader $classLoader)
    {
        // Determine the entry point by scanning backwards through paths starting from
        // the location of the *actual* entry point (not this class file), until finding
        // a vendor directory with an autoload.php file inside. The first such directory
        // encountered this way is assumed to be the root folder of the TYPO3 application.
        $entry = $_SERVER['PHP_SELF'];
        $entryDir = dirname($entry);
        while (!file_exists($entryDir . '/vendor/autoload.php') && $entryDir !== '/') {
            $this->entryPointLevel++;
            $entryDir = dirname($entryDir);
        }

        $this->defineLegacyConstants();

        $this->bootstrap = Bootstrap::getInstance()
            ->initializeClassLoader($classLoader)
            ->setRequestType(TYPO3_REQUESTTYPE_BE | TYPO3_REQUESTTYPE_CLI)
            ->baseSetup($this->entryPointLevel)
            ->configure()
            ->loadExtensionTables()
            ->initializeCachingFramework();

        $this->bootstrap;
    }

}
