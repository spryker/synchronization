<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Synchronization;

use Spryker\Shared\Queue\QueueConfig;
use Spryker\Shared\Queue\QueueConstants;
use Spryker\Shared\Synchronization\SynchronizationConstants;
use Spryker\Zed\Kernel\AbstractBundleConfig;

class SynchronizationConfig extends AbstractBundleConfig
{
    /**
     * @api
     *
     * @param string $queueName
     *
     * @return int
     */
    public function getQueueWorkerNumber($queueName)
    {
        $numberOfWorker = 1;
        $queueAdapterConfigs = $this->get(QueueConstants::QUEUE_ADAPTER_CONFIGURATION);

        if (!isset($queueAdapterConfigs[$queueName])) {
            return $numberOfWorker;
        }

        $queueAdapterConfig = $queueAdapterConfigs[$queueName];
        if (isset($queueAdapterConfig[QueueConfig::CONFIG_MAX_WORKER_NUMBER])) {
            $numberOfWorker = $queueAdapterConfig[QueueConfig::CONFIG_MAX_WORKER_NUMBER];
        }

        return $numberOfWorker;
    }

    /**
     * @api
     *
     * @return int
     */
    public function getSyncStorageQueueMessageChunkSize()
    {
        return $this->get(SynchronizationConstants::DEFAULT_SYNC_STORAGE_QUEUE_MESSAGE_CHUNK_SIZE, 10000);
    }

    /**
     * @api
     *
     * @return int
     */
    public function getSyncSearchQueueMessageChunkSize()
    {
        return $this->get(SynchronizationConstants::DEFAULT_SYNC_SEARCH_QUEUE_MESSAGE_CHUNK_SIZE, 10000);
    }

    /**
     * @api
     *
     * @return int
     */
    public function getSyncExportChunkSize()
    {
        return $this->get(SynchronizationConstants::EXPORT_MESSAGE_CHUNK_SIZE, 100);
    }

    /**
     * Specification:
     * - Enables keyset pagination for query container synchronization export if set to true.
     * - Rows are read in chunks by primary key greater than the last exported primary key, ordered by primary key.
     * - Applies to query container plugins whose table has a single integer primary key; other plugins use the query expander strategy plugin.
     * - The query expander strategy plugin is not used for plugins exported with keyset pagination.
     *
     * @api
     */
    public function isQueryContainerSyncExportKeysetPaginationEnabled(): bool
    {
        return false;
    }

    /**
     * Specification:
     * - Disables Propel Instance Pooling for repository synchronization export if set to true.
     *
     * @api
     *
     * @return bool
     */
    public function isRepositorySyncExportPropelInstancePoolingDisabled(): bool
    {
        return false;
    }
}
