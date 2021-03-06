<?php declare(strict_types=1);

namespace Symplify\EasyCodingStandard\Application;

use ParseError;
use Symfony\Component\Finder\SplFileInfo;
use Symplify\EasyCodingStandard\ChangedFilesDetector\ChangedFilesDetector;
use Symplify\EasyCodingStandard\Configuration\Configuration;
use Symplify\EasyCodingStandard\Console\Style\EasyCodingStandardStyle;
use Symplify\EasyCodingStandard\Contract\Application\FileProcessorInterface;
use Symplify\EasyCodingStandard\Error\ErrorAndDiffCollector;
use Symplify\EasyCodingStandard\FileSystem\FileFilter;
use Symplify\EasyCodingStandard\Finder\SourceFinder;

final class Application
{
    /**
     * @var EasyCodingStandardStyle
     */
    private $easyCodingStandardStyle;

    /**
     * @var SourceFinder
     */
    private $sourceFinder;

    /**
     * @var ChangedFilesDetector
     */
    private $changedFilesDetector;

    /**
     * @var ErrorAndDiffCollector
     */
    private $errorAndDiffCollector;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * @var FileProcessorInterface[]
     */
    private $fileProcessors = [];

    /**
     * @var FileFilter
     */
    private $fileFilter;

    public function __construct(
        EasyCodingStandardStyle $easyCodingStandardStyle,
        SourceFinder $sourceFinder,
        ChangedFilesDetector $changedFilesDetector,
        ErrorAndDiffCollector $errorAndDiffCollector,
        Configuration $configuration,
        FileFilter $fileFilter
    ) {
        $this->easyCodingStandardStyle = $easyCodingStandardStyle;
        $this->sourceFinder = $sourceFinder;
        $this->changedFilesDetector = $changedFilesDetector;
        $this->errorAndDiffCollector = $errorAndDiffCollector;
        $this->configuration = $configuration;
        $this->fileFilter = $fileFilter;
    }

    public function addFileProcessor(FileProcessorInterface $fileProcessor): void
    {
        $this->fileProcessors[] = $fileProcessor;
    }

    public function run(): void
    {
        // 1. find files in sources
        $files = $this->sourceFinder->find($this->configuration->getSources());

        // 2. clear cache
        if ($this->configuration->shouldClearCache()) {
            $this->changedFilesDetector->clearCache();
        } else {
            $files = $this->fileFilter->filterOnlyChangedFiles($files);
        }

        // no files found
        if (! count($files)) {
            return;
        }

        // 3. start progress bar
        if ($this->configuration->showProgressBar()) {
            $this->easyCodingStandardStyle->progressStart(count($files) * ($this->isDualRunEnabled() ? 2 : 1));
        }

        // 4. process found files by each processors
        $this->processFoundFiles($files);

        // 5. process files with DualRun
        if ($this->isDualRunEnabled()) {
            $this->processFoundFilesSecondRun($files);
        }
    }

    public function getCheckerCount(): int
    {
        $checkerCount = 0;

        foreach ($this->fileProcessors as $fileProcessor) {
            $checkerCount += count($fileProcessor->getCheckers());
        }

        return $checkerCount;
    }

    /**
     * @param SplFileInfo[] $fileInfos
     */
    private function processFoundFiles(array $fileInfos): void
    {
        foreach ($fileInfos as $relativePath => $fileInfo) {
            if ($this->configuration->showProgressBar()) {
                $this->easyCodingStandardStyle->progressAdvance();
            }

            try {
                // @todo pass file content?
                foreach ($this->fileProcessors as $fileProcessor) {
                    $fileProcessor->processFile($fileInfo);
                }

                // @todo add diff here? + save just once :)
            } catch (ParseError $parseError) {
                $this->changedFilesDetector->invalidateFile($relativePath);
                $this->errorAndDiffCollector->addErrorMessage(
                    $relativePath,
                    $parseError->getLine(),
                    $parseError->getMessage(),
                    ParseError::class
                );
            }
        }
    }

    /**
     * @param SplFileInfo[] $fileInfos
     */
    private function processFoundFilesSecondRun(array $fileInfos): void
    {
        foreach ($fileInfos as $relativePath => $fileInfo) {
            if ($this->configuration->showProgressBar()) {
                $this->easyCodingStandardStyle->progressAdvance();
            }

            foreach ($this->fileProcessors as $fileProcessor) {
                $fileProcessor->processFileSecondRun($fileInfo);
            }
        }
    }

    private function isDualRunEnabled(): bool
    {
        foreach ($this->fileProcessors as $fileProcessor) {
            if ($fileProcessor->getDualRunCheckers()) {
                return true;
            }
        }

        return false;
    }
}
