<?php
declare(strict_types=1);

namespace NamelessCoder\CmsFluidPrecompiler;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Cache\FluidCacheWarmupResult;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class FluidPrecompiler
 */
class FluidPrecompiler
{
    /**
     * @param array $consoleArguments
     * @return void
     */
    public function compile(array $consoleArguments)
    {
        ob_end_flush();
        $arguments = $this->parseArguments($consoleArguments);
        if ($arguments['help']) {
            echo PHP_EOL . file_get_contents(__DIR__ . '/../README.md');
        } elseif ($arguments['extension']) {
            $this->warmupExtensionKey($arguments['extension'], $arguments);
        } else {
            $extensionKeys = $this->getInstalledExtensionKeys();
            $indent = max(array_map('strlen', $extensionKeys));
            foreach ($extensionKeys as $extensionKey) {
                $this->warmupExtensionKey($extensionKey, $arguments, $indent);
            }
        }
        if (!$arguments['silent']) {
            echo PHP_EOL;
        }
    }

    /**
     * @param string $extensionKey
     * @param array $arguments
     * @param integer $indent
     * @return void
     */
    protected function warmupExtensionKey($extensionKey, array $arguments, $indent = 0)
    {
        if (!$arguments['silent']) {
            echo PHP_EOL . str_pad($extensionKey, $indent, ' ', STR_PAD_RIGHT);
        }

        $dir = trim(shell_exec('pwd')) . '/';
        $renderingContext = $this->getRenderingContext($extensionKey);
        /** @var FluidCacheWarmupResult $result */
        $result = $renderingContext->getCache()->getCacheWarmer()->warm($renderingContext);
        $uncompiledCount = 0;
        $compiledCount = 0;
        $allCount = count($result->getResults());
        if (!$allCount) {
            if (!$arguments['silent']) {
                echo ' - no templates detected';
            }
            return;
        }
        if ($arguments['verbose']) {
            echo PHP_EOL;
        }
        foreach ($result->getResults() as $templatePathAndFilename => $templateResult) {
            if (!$templateResult[FluidCacheWarmupResult::RESULT_COMPILABLE]) {
                ++$uncompiledCount;
            } else {
                ++$compiledCount;
            }
            if ($arguments['verbose'] && !$arguments['silent']) {
                /** @var FluidCacheWarmupResult $templateResult */
                echo PHP_EOL . ' * ' . str_replace($dir, '', $templatePathAndFilename) . PHP_EOL;
                echo ' * Compilable: ' .
                    $this->getLiteralBoolean($templateResult[FluidCacheWarmupResult::RESULT_COMPILABLE]) .
                    PHP_EOL;
                if ($templateResult[FluidCacheWarmupResult::RESULT_COMPILABLE]) {
                    echo ' * Uses Layout: ' .
                        $this->getLiteralBoolean($templateResult[FluidCacheWarmupResult::RESULT_HASLAYOUT]) .
                        PHP_EOL;
                    echo ' * Compiled class: ' .
                        $templateResult[FluidCacheWarmupResult::RESULT_COMPILEDCLASS] .
                        PHP_EOL;
                } else {
                    echo ' ! Failure: ' .
                        $templateResult[FluidCacheWarmupResult::RESULT_FAILURE] .
                        PHP_EOL;
                    if (count($templateResult[FluidCacheWarmupResult::RESULT_MITIGATIONS])) {
                        echo ' ! Mitigations:' . PHP_EOL;
                        foreach ($templateResult[FluidCacheWarmupResult::RESULT_MITIGATIONS] as $mitigation) {
                            echo '   * ' . $mitigation . PHP_EOL;
                        }
                    }

                }
            }
        }
        if (!$arguments['verbose'] && !$arguments['silent']) {
            echo str_pad(
                sprintf(
                    ' - %d/%d templates compiled',
                    $compiledCount,
                    $allCount
                ),
                32,
                ' ',
                STR_PAD_RIGHT
            );
            echo sprintf(
                '(%d%%)',
                ceil($compiledCount / $allCount * 100)
            );
        }
        if ($arguments['fail'] && $uncompiledCount) {
            if (!$arguments['silent']) {
                echo sprintf(
                    '[!!] Failed to compile %d template(s) and -f was specified, exiting with error' . PHP_EOL,
                    $uncompiledCount
                );
            }
            exit(1);
        }
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function getLiteralBoolean($value): string
    {
        return $value ? 'Yes' : 'No';
    }

    /**
     * @param array $arguments
     * @return array
     */
    protected function parseArguments(array $arguments): array
    {
        return [
            'verbose' => in_array('-v', $arguments) || in_array('--verbose', $arguments),
            'fail' => in_array('-f', $arguments) || in_array('--fail', $arguments),
            'silent' => in_array('-s', $arguments) || in_array('--silent', $arguments),
            'help' => in_array('-h', $arguments) || in_array('--help', $arguments),
            'extension' => $arguments[array_search('-e', $arguments) + 1] ?? $arguments[array_search('--extension', $arguments) + 1] ?? null,
        ];
    }

    /**
     * @param string $extensionKey
     * @return RenderingContextInterface
     */
    protected function getRenderingContext($extensionKey): RenderingContextInterface
    {
        /** @var RenderingContextInterface $context */
        $context = GeneralUtility::makeInstance(ObjectManager::class)->get(RenderingContext::class);
        $context->getTemplatePaths()->fillDefaultsByPackageName($extensionKey);
        return $context;
    }

    /**
     * @return array
     */
    protected function getInstalledExtensionKeys(): array
    {
        return ExtensionManagementUtility::getLoadedExtensionListArray();
    }
}
