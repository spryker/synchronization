<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Synchronization\Business;

use Generated\Shared\Transfer\SynchronizationMessageTransfer;
use Spryker\Zed\Kernel\Business\AbstractFacade;

/**
 * @method \Spryker\Zed\Synchronization\Business\SynchronizationBusinessFactory getFactory()
 */
class SynchronizationFacade extends AbstractFacade implements SynchronizationFacadeInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param array<string, mixed> $data
     * @param string $queueName
     *
     * @return void
     */
    public function storageWrite(array $data, $queueName)
    {
        $this->getFactory()->createStorageManager()->write($data, $queueName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param array<string, mixed> $data
     * @param string $queueName
     *
     * @return void
     */
    public function storageDelete(array $data, $queueName)
    {
        $this->getFactory()->createStorageManager()->delete($data, $queueName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param array<string, mixed> $data
     * @param string $queueName
     *
     * @return void
     */
    public function searchWrite(array $data, $queueName)
    {
        $this->getFactory()->createSearchManager()->write($data, $queueName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param array<string, mixed> $data
     * @param string $queueName
     *
     * @return void
     */
    public function searchDelete(array $data, $queueName)
    {
        $this->getFactory()->createSearchManager()->delete($data, $queueName);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param array<\Generated\Shared\Transfer\QueueReceiveMessageTransfer> $queueMessageTransfers
     *
     * @return array<\Generated\Shared\Transfer\QueueReceiveMessageTransfer>
     */
    public function processSearchMessages(array $queueMessageTransfers): array
    {
        return $this->getFactory()
            ->createSearchQueueMessageProcessor()
            ->processMessages($queueMessageTransfers);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param array<\Generated\Shared\Transfer\QueueReceiveMessageTransfer> $queueMessageTransfers
     *
     * @return array<\Generated\Shared\Transfer\QueueReceiveMessageTransfer>
     */
    public function processStorageMessages(array $queueMessageTransfers): array
    {
        return $this->getFactory()
            ->createStorageQueueMessageProcessor()
            ->processMessages($queueMessageTransfers);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @deprecated Use {@link executeResolvedPluginsBySourcesWithIds()} instead.
     *
     * @param array<string> $resources
     *
     * @return void
     */
    public function executeResolvedPluginsBySources(array $resources)
    {
        $this->getFactory()->createExporterPluginResolver()->executeResolvedPluginsBySources($resources);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param array<string> $resources
     * @param array<int> $ids
     *
     * @return void
     */
    public function executeResolvedPluginsBySourcesWithIds(array $resources, array $ids)
    {
        $this->getFactory()->createExporterPluginResolver()->executeResolvedPluginsBySourcesWithIds($resources, $ids);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return array<string>
     */
    public function getAvailableResourceNames(): array
    {
        return $this->getFactory()->createExporterPluginResolver()->getAvailableResourceNames();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SynchronizationMessageTransfer $synchronizationMessage
     *
     * @return void
     */
    public function addSynchronizationMessageToBuffer(SynchronizationMessageTransfer $synchronizationMessage): void
    {
        $this->getFactory()->createInMemoryMessageSynchronizer()->addSynchronizationMessage($synchronizationMessage);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return void
     */
    public function flushSynchronizationMessagesFromBuffer(): void
    {
        $this->getFactory()->createInMemoryMessageSynchronizer()->flushSynchronizationMessages();
    }
}
