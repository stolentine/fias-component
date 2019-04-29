<?php

declare(strict_types=1);

namespace Liquetsoft\Fias\Component\Pipeline\Task;

use Liquetsoft\Fias\Component\FiasInformer\FiasInformer;
use Liquetsoft\Fias\Component\Pipeline\State\State;
use Liquetsoft\Fias\Component\Exception\TaskException;

/**
 * Задача, которая получает ссылку на архив с обновлениями ФИАС
 * относительно указанной в состоянии версии.
 */
class InformDeltaTask implements Task
{
    /**
     * @var FiasInformer
     */
    protected $informer;

    /**
     * @param FiasInformer $informer
     */
    public function __construct(FiasInformer $informer)
    {
        $this->informer = $informer;
    }

    /**
     * @inheritdoc
     */
    public function run(State $state): void
    {
        $version = (int) $state->getParameter('currentVersion');
        if (!$version) {
            throw new TaskException(
                "State parameter 'currentVersion' is required for '" . self::class . "'."
            );
        }

        $info = $this->informer->getDeltaInfo($version);
        if (!$info->hasResult()) {
            $state->complete();
        }

        $state->setAndLockParameter('fiasInfo', $info);
    }
}
