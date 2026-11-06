<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Synchronization\Business\Iterator;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\ColumnMap;
use Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryContainerPluginInterface;

class SynchronizationDataQueryContainerKeysetPluginIterator extends AbstractSynchronizationDataPluginIterator
{
    protected ?int $lastPrimaryKey = null;

    protected bool $isLastChunkFetched = false;

    /**
     * @param array<int> $filterIds
     */
    public function __construct(
        SynchronizationDataQueryContainerPluginInterface $plugin,
        protected ColumnMap $primaryKeyColumnMap,
        int $chunkSize,
        protected array $filterIds = []
    ) {
        parent::__construct($plugin, $chunkSize);
    }

    public function rewind(): void
    {
        $this->lastPrimaryKey = null;
        $this->isLastChunkFetched = false;

        parent::rewind();
    }

    protected function updateCurrent(): void
    {
        if ($this->isLastChunkFetched) {
            $this->current = [];

            return;
        }

        $this->current = $this->findChunk();
        $this->isLastChunkFetched = count($this->current) < $this->chunkSize;

        $lastEntity = end($this->current);

        if (!is_object($lastEntity) || !method_exists($lastEntity, 'getPrimaryKey')) {
            $this->isLastChunkFetched = true;

            return;
        }

        $this->lastPrimaryKey = (int)$lastEntity->getPrimaryKey();
    }

    /**
     * @return array<mixed>
     */
    protected function findChunk(): array
    {
        /** @var \Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryContainerPluginInterface $plugin */
        $plugin = $this->plugin;
        $query = $plugin->queryData($this->filterIds);

        if ($query === null) {
            return [];
        }

        $query->clearOrderByColumns();

        if ($this->lastPrimaryKey !== null) {
            $query->filterBy($this->primaryKeyColumnMap->getPhpName(), $this->lastPrimaryKey, Criteria::GREATER_THAN);
        }

        return $query
            ->addAscendingOrderByColumn($this->primaryKeyColumnMap->getFullyQualifiedName())
            ->limit($this->chunkSize)
            ->find()
            ->getData();
    }
}
