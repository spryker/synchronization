<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Synchronization\Business\Iterator;

use Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataBulkRepositoryPluginInterface;
use Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataMaxIterationLimitPluginInterface;

class SynchronizationDataBulkRepositoryPluginIterator extends AbstractSynchronizationDataPluginIterator
{
    /**
     * @var \Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataBulkRepositoryPluginInterface
     */
    protected $plugin;

    /**
     * @var array<int>
     */
    protected array $filterIds = [];

    /**
     * @var int
     */
    protected int $iterationLimit = 0;

    /**
     * @param \Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataBulkRepositoryPluginInterface $plugin
     * @param int $chunkSize
     * @param array<int> $ids
     */
    public function __construct(SynchronizationDataBulkRepositoryPluginInterface $plugin, int $chunkSize, array $ids = [])
    {
        parent::__construct($plugin, $chunkSize);

        $this->filterIds = $ids;

        if ($plugin instanceof SynchronizationDataMaxIterationLimitPluginInterface) {
            $this->iterationLimit = $plugin->getMaxIterationLimit();
        }
    }

    protected function updateCurrent(): void
    {
        $this->current = $this->plugin->getData($this->offset, $this->chunkSize, $this->filterIds);
    }

    public function valid(): bool
    {
        $valid = parent::valid();

        if (!$valid && !$this->isIterationLimitExceeded()) {
            return true;
        }

        return $valid;
    }

    protected function isIterationLimitExceeded(): bool
    {
        return $this->offset + $this->chunkSize > $this->iterationLimit;
    }
}
