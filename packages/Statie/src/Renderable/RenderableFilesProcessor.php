<?php declare(strict_types=1);

namespace Symplify\Statie\Renderable;

use Symfony\Component\Finder\SplFileInfo;
use Symplify\Statie\Contract\Renderable\FileDecoratorInterface;
use Symplify\Statie\Generator\Configuration\GeneratorElement;
use Symplify\Statie\Renderable\File\AbstractFile;
use Symplify\Statie\Renderable\File\FileFactory;

final class RenderableFilesProcessor
{
    /**
     * @var FileFactory
     */
    private $fileFactory;

    /**
     * @var FileDecoratorInterface[]
     */
    private $fileDecorators = [];

    public function __construct(FileFactory $fileFactory)
    {
        $this->fileFactory = $fileFactory;
    }

    public function addFileDecorator(FileDecoratorInterface $fileDecorator): void
    {
        $this->fileDecorators[] = $fileDecorator;
    }

    /**
     * @param SplFileInfo[] $fileInfos
     * @return AbstractFile[]
     */
    public function processFileInfos(array $fileInfos): array
    {
        if (! count($fileInfos)) {
            return [];
        }

        $files = $this->fileFactory->createFromFileInfos($fileInfos);

        foreach ($this->fileDecorators as $fileDecorator) {
            $files = $fileDecorator->decorateFiles($files);
        }

        return $files;
    }

    /**
     * @param AbstractFile[] $objects
     * @return AbstractFile[]
     */
    public function processGeneratorElementObjects(array $objects, GeneratorElement $generatorElement): array
    {
        if (! count($objects)) {
            return [];
        }

        foreach ($this->fileDecorators as $fileDecorator) {
            $objects = $fileDecorator->decorateFilesWithGeneratorElement($objects, $generatorElement);
        }

        return $objects;
    }
}
