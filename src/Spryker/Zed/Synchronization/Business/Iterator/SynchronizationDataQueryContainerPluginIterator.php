<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Synchronization\Business\Iterator;

use Generated\Shared\Transfer\SynchronizationDataQueryExpanderStrategyConfigurationTransfer;
use Spryker\Shared\Log\LoggerTrait;
use Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryContainerPluginInterface;
use Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryExpanderStrategyPluginInterface;

class SynchronizationDataQueryContainerPluginIterator extends AbstractSynchronizationDataPluginIterator
{
    use LoggerTrait;

    protected const string COLUMN_MAX_PRIMARY_KEY = 'maxPrimaryKey';

    protected int $totalRowCount = 0;

    protected int $fetchedRowCount = 0;

    protected int $maxPrimaryKey = 0;

    /**
     * @var \Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryContainerPluginInterface
     */
    protected $plugin;

    /**
     * @var \Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryExpanderStrategyPluginInterface
     */
    protected $synchronizationDataQueryExpanderStrategyPlugin;

    /**
     * @var array<int>
     */
    protected $filterIds;

    /**
     * @param \Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryContainerPluginInterface $plugin
     * @param \Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryExpanderStrategyPluginInterface $synchronizationDataQueryExpanderStrategyPlugin
     * @param int $chunkSize
     * @param array<int> $ids
     */
    public function __construct(
        SynchronizationDataQueryContainerPluginInterface $plugin,
        SynchronizationDataQueryExpanderStrategyPluginInterface $synchronizationDataQueryExpanderStrategyPlugin,
        int $chunkSize,
        array $ids = []
    ) {
        parent::__construct($plugin, $chunkSize);

        $this->synchronizationDataQueryExpanderStrategyPlugin = $synchronizationDataQueryExpanderStrategyPlugin;
        $this->filterIds = $ids;
    }

    public function rewind(): void
    {
        $this->fetchedRowCount = 0;
        $this->totalRowCount = $this->countRows();
        $this->maxPrimaryKey = $this->findMaxPrimaryKey();

        parent::rewind();
    }

    protected function updateCurrent(): void
    {
        $this->current = $this->findChunk();

        while ($this->current === [] && $this->hasRowsToFetch()) {
            $this->offset += $this->chunkSize;
            $this->current = $this->findChunk();
        }

        $this->fetchedRowCount += count($this->current);

        if ($this->current === [] && $this->fetchedRowCount < $this->totalRowCount) {
            $this->getLogger()->warning(sprintf(
                'Synchronization export of resource "%s" stopped after %d of %d rows (max primary key %d, offset %d).',
                $this->plugin->getResourceName(),
                $this->fetchedRowCount,
                $this->totalRowCount,
                $this->maxPrimaryKey,
                $this->offset,
            ));
        }
    }

    /**
     * @return array<mixed>
     */
    protected function findChunk(): array
    {
        $query = $this->plugin->queryData($this->filterIds);

        if ($query === null) {
            return [];
        }

        $synchronizationDataQueryExpanderStrategyConfigurationTransfer = (new SynchronizationDataQueryExpanderStrategyConfigurationTransfer())
            ->setOffset($this->offset)
            ->setChunkSize($this->chunkSize);

        return $this->synchronizationDataQueryExpanderStrategyPlugin
            ->expandQuery($query, $synchronizationDataQueryExpanderStrategyConfigurationTransfer)
            ->find()
            ->getData();
    }

    protected function hasRowsToFetch(): bool
    {
        return $this->fetchedRowCount < $this->totalRowCount
            && $this->offset + $this->chunkSize < $this->maxPrimaryKey;
    }

    protected function countRows(): int
    {
        $query = $this->plugin->queryData($this->filterIds);

        if ($query === null) {
            return 0;
        }

        return $query->clearOrderByColumns()->count();
    }

    protected function findMaxPrimaryKey(): int
    {
        $query = $this->plugin->queryData($this->filterIds);
        $tableMap = $query?->getTableMap();

        if ($query === null || $tableMap === null) {
            return 0;
        }

        $primaryKeyColumnMap = current($tableMap->getPrimaryKeys());

        if ($primaryKeyColumnMap === false) {
            return 0;
        }

        $maxPrimaryKey = $query
            ->clearOrderByColumns()
            ->withColumn(sprintf('MAX(%s)', $primaryKeyColumnMap->getFullyQualifiedName()), static::COLUMN_MAX_PRIMARY_KEY)
            ->select([static::COLUMN_MAX_PRIMARY_KEY])
            ->findOne();

        return (int)$maxPrimaryKey;
    }
}
