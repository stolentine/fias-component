<?php

declare(strict_types=1);

namespace Liquetsoft\Fias\Component\Pipeline\Task;

use Liquetsoft\Fias\Component\EntityManager\EntityManager;
use Liquetsoft\Fias\Component\Exception\TaskException;
use Liquetsoft\Fias\Component\Pipeline\State\State;
use Psr\Log\LogLevel;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class SelectFilesToProceedTask implements LoggableTask, Task
{
    use LoggableTaskTrait;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritDoc}
     */
    public function run(State $state): void
    {
        $folderParameter = $state->getParameter(Task::EXTRACT_TO_FOLDER_PARAM);
        $extractToFolder = $this->checkDirectory($folderParameter);

        $this->log(
            LogLevel::INFO,
            "Searching for files to proceed in '{$extractToFolder->getRealPath()}' folder."
        );

        $files = $this->getFilesForProceedFromFolder($extractToFolder);
        $state->setAndLockParameter(Task::FILES_TO_PROCEED, $files);

        $this->log(
            LogLevel::INFO,
            'Found ' . \count($files) . ' file(s) to proceed',
            [
                'files' => $files,
            ]
        );
    }

    /**
     * Проверяет, что параметр директории для поиска файлов задан верно.
     *
     * @param mixed $parameterValue
     *
     * @return SplFileInfo
     *
     * @throws TaskException
     */
    protected function checkDirectory($parameterValue): SplFileInfo
    {
        if (!($parameterValue instanceof SplFileInfo)) {
            throw new TaskException(
                "State parameter '" . Task::EXTRACT_TO_FOLDER_PARAM . "' must be an '" . SplFileInfo::class . "' instance for '" . self::class . "'."
            );
        }

        if (!$parameterValue->isDir()) {
            throw new TaskException(
                "Path '{$parameterValue->getRealPath()}' must be an existed directory."
            );
        }

        return $parameterValue;
    }

    /**
     * Возвращает список файлов для обработки из указанной директории.
     *
     * @param SplFileInfo $filesFolder
     *
     * @return string[]
     */
    protected function getFilesForProceedFromFolder(SplFileInfo $filesFolder): array
    {
        $files = [];

        $directoryIterator = new RecursiveDirectoryIterator(
            $filesFolder->getRealPath(),
            RecursiveDirectoryIterator::SKIP_DOTS
        );
        $iterator = new RecursiveIteratorIterator($directoryIterator);

        foreach ($iterator as $fileInfo) {
            if ($this->isFileAllowedToInsert($fileInfo) || $this->isFileAllowedToDelete($fileInfo)) {
                $files[] = (string) $fileInfo->getRealPath();
            }
        }

        sort($files, \SORT_STRING);

        return $files;
    }

    /**
     * Проверяет нужно ли файл обрабатывать для создания и обновления в рамках данного процесса.
     *
     * @param SplFileInfo $fileInfo
     *
     * @return bool
     */
    protected function isFileAllowedToInsert(SplFileInfo $fileInfo): bool
    {
        $descriptor = $this->entityManager->getDescriptorByInsertFile($fileInfo->getFilename());

        return !empty($descriptor) && $this->entityManager->getClassByDescriptor($descriptor);
    }

    /**
     * Проверяет нужно ли файл обрабатывать для удаления в рамках данного процесса.
     *
     * @param SplFileInfo $fileInfo
     *
     * @return bool
     */
    protected function isFileAllowedToDelete(SplFileInfo $fileInfo): bool
    {
        $descriptor = $this->entityManager->getDescriptorByDeleteFile($fileInfo->getFilename());

        return !empty($descriptor) && $this->entityManager->getClassByDescriptor($descriptor);
    }
}
