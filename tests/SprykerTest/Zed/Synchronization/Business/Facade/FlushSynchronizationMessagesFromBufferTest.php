<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Synchronization\Business\Facade;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SynchronizationMessageTransfer;
use Spryker\Zed\Synchronization\Business\Search\SynchronizationSearch;
use Spryker\Zed\Synchronization\Business\Storage\SynchronizationStorage;
use Spryker\Zed\Synchronization\Business\SynchronizationBusinessFactory;
use Spryker\Zed\Synchronization\Business\Synchronizer\InMemoryMessageSynchronizer;
use Spryker\Zed\Synchronization\Dependency\Client\SynchronizationToQueueClientInterface;
use SprykerTest\Zed\Synchronization\SynchronizationBusinessTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Synchronization
 * @group Business
 * @group Facade
 * @group FlushSynchronizationMessagesFromBufferTest
 * Add your own group annotations below this line
 */
class FlushSynchronizationMessagesFromBufferTest extends Unit
{
    /**
     * @var \SprykerTest\Zed\Synchronization\SynchronizationBusinessTester
     */
    protected SynchronizationBusinessTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tester->clearStaticVariable(InMemoryMessageSynchronizer::class, 'messages');
        $this->tester->clearStaticVariable(SynchronizationBusinessFactory::class, 'inMemoryMessageSynchronizer');
    }

    public function testShouldWriteBulkStorageAndSearchMessages(): void
    {
        // Assert
        $this->assertBulkMessageProcessing('write');

        // Arrange
        $synchronizationMessageTransfer = (new SynchronizationMessageTransfer())
            ->fromArray($this->tester->createFakeSynchronizationMessage('storage', 'sync.storage.product', 'write'));

        $this->tester->getFacade()->addSynchronizationMessageToBuffer($synchronizationMessageTransfer);

        $synchronizationMessageTransfer = (new SynchronizationMessageTransfer())
            ->fromArray($this->tester->createFakeSynchronizationMessage('search', 'sync.search.product', 'write'));

        $this->tester->getFacade()->addSynchronizationMessageToBuffer($synchronizationMessageTransfer);

        // Act
        $this->tester->getFacade()->flushSynchronizationMessagesFromBuffer();
    }

    public function testShouldDeleteBulkStorageAndSearchMessages(): void
    {
        // Assert
        $this->assertBulkMessageProcessing('delete');

        // Arrange
        $synchronizationMessageTransfer = (new SynchronizationMessageTransfer())
            ->fromArray($this->tester->createFakeSynchronizationMessage('storage', 'sync.storage.product', 'delete'));

        $this->tester->getFacade()->addSynchronizationMessageToBuffer($synchronizationMessageTransfer);

        $synchronizationMessageTransfer = (new SynchronizationMessageTransfer())
            ->fromArray($this->tester->createFakeSynchronizationMessage('search', 'sync.search.product', 'delete'));

        $this->tester->getFacade()->addSynchronizationMessageToBuffer($synchronizationMessageTransfer);

        // Act
        $this->tester->getFacade()->flushSynchronizationMessagesFromBuffer();
    }

    public function testShouldDeleteAndWriteBulkStorageAndSearchMessages(): void
    {
        // Assert
        $this->assertBulkMessageProcessing('write-delete');

        // Arrange
        $synchronizationMessageTransfer = (new SynchronizationMessageTransfer())
            ->fromArray($this->tester->createFakeSynchronizationMessage('storage', 'sync.storage.product', 'delete'));

        $this->tester->getFacade()->addSynchronizationMessageToBuffer($synchronizationMessageTransfer);

        $synchronizationMessageTransfer = (new SynchronizationMessageTransfer())
            ->fromArray($this->tester->createFakeSynchronizationMessage('storage', 'sync.storage.product', 'write'));

        $this->tester->getFacade()->addSynchronizationMessageToBuffer($synchronizationMessageTransfer);

        $synchronizationMessageTransfer = (new SynchronizationMessageTransfer())
            ->fromArray($this->tester->createFakeSynchronizationMessage('search', 'sync.search.product', 'delete'));

        $this->tester->getFacade()->addSynchronizationMessageToBuffer($synchronizationMessageTransfer);

        $synchronizationMessageTransfer = (new SynchronizationMessageTransfer())
            ->fromArray($this->tester->createFakeSynchronizationMessage('search', 'sync.search.product', 'write'));

        $this->tester->getFacade()->addSynchronizationMessageToBuffer($synchronizationMessageTransfer);

        // Act
        $this->tester->getFacade()->flushSynchronizationMessagesFromBuffer();
    }

    public function testShouldSkipSynchronizationWhenMessagesAreEmpty(): void
    {
        // Assert
        $this->assertBulkMessageProcessing('skip');

        // Act
        $this->tester->getFacade()->flushSynchronizationMessagesFromBuffer();
    }

    public function testShouldCatchExceptionWhenSynchronizationForDestinationTypeNotFound(): void
    {
        // Assert
        $this->assertBulkMessageProcessing('skip', true);

        // Arrange
        $synchronizationMessageTransfer = (new SynchronizationMessageTransfer())
            ->fromArray($this->tester->createFakeSynchronizationMessage('unknown', 'sync.unknown.product', 'write'));

        $this->tester->getFacade()->addSynchronizationMessageToBuffer($synchronizationMessageTransfer);

        // Act
        $this->tester->getFacade()->flushSynchronizationMessagesFromBuffer();
    }

    public function testShouldSkipSynchronizationForNotFoundOperationType(): void
    {
        // Assert
        $this->assertBulkMessageProcessing('skip');

        // Arrange
        $synchronizationMessageTransfer = (new SynchronizationMessageTransfer())
            ->fromArray($this->tester->createFakeSynchronizationMessage('storage', 'sync.storage.product', 'unknown'));

        $this->tester->getFacade()->addSynchronizationMessageToBuffer($synchronizationMessageTransfer);

        // Act
        $this->tester->getFacade()->flushSynchronizationMessagesFromBuffer();
    }

    public function testShouldNotResendMessagesWhenFlushedTwice(): void
    {
        // Arrange
        $storageManagerMock = $this->mockStorageManager();
        $storageManagerMock->expects($this->once())->method('deleteBulk');

        $this->tester->getFacade()->addSynchronizationMessageToBuffer(
            (new SynchronizationMessageTransfer())->fromArray($this->tester->createFakeSynchronizationMessage('storage', 'sync.storage.product', 'delete')),
        );

        // Act
        $this->tester->getFacade()->flushSynchronizationMessagesFromBuffer();
        $this->tester->getFacade()->flushSynchronizationMessagesFromBuffer();
    }

    public function testShouldSendOnlyMessagesAddedAfterPreviousFlush(): void
    {
        // Arrange
        $deletedData = [];
        $storageManagerMock = $this->mockStorageManager();
        $storageManagerMock->expects($this->exactly(2))
            ->method('deleteBulk')
            ->willReturnCallback(function (array $data) use (&$deletedData): void {
                $deletedData[] = $data;
            });

        $firstMessage = $this->tester->createFakeSynchronizationMessage('storage', 'sync.storage.product', 'delete');
        $secondMessage = $this->tester->createFakeSynchronizationMessage('storage', 'sync.storage.product', 'delete');
        $secondMessage['data']['key'] = 'product_concrete:en_us:322';

        // Act
        $this->tester->getFacade()->addSynchronizationMessageToBuffer((new SynchronizationMessageTransfer())->fromArray($firstMessage));
        $this->tester->getFacade()->flushSynchronizationMessagesFromBuffer();
        $this->tester->getFacade()->addSynchronizationMessageToBuffer((new SynchronizationMessageTransfer())->fromArray($secondMessage));
        $this->tester->getFacade()->flushSynchronizationMessagesFromBuffer();

        // Assert
        $this->assertSame([[$firstMessage['data']], [$secondMessage['data']]], $deletedData);
    }

    public function testShouldNotResendFailedMessagesToQueueWhenFlushedTwice(): void
    {
        // Arrange
        $this->mockStorageManager();
        $queueClientMock = $this->createMock(SynchronizationToQueueClientInterface::class);
        $queueClientMock->expects($this->once())->method('sendMessages');
        $this->tester->mockFactoryMethod('getQueueClient', $queueClientMock);

        $this->tester->getFacade()->addSynchronizationMessageToBuffer(
            (new SynchronizationMessageTransfer())->fromArray($this->tester->createFakeSynchronizationMessage('unknown', 'sync.unknown.product', 'delete')),
        );

        // Act
        $this->tester->getFacade()->flushSynchronizationMessagesFromBuffer();
        $this->tester->getFacade()->flushSynchronizationMessagesFromBuffer();
    }

    protected function mockStorageManager(): SynchronizationStorage
    {
        $storageManagerMock = $this->createMock(SynchronizationStorage::class);
        $storageManagerMock->method('isDestinationTypeApplicable')
            ->willReturnCallback(function (string $destinationType) {
                return $destinationType === 'storage';
            });

        $searchManagerMock = $this->createMock(SynchronizationSearch::class);
        $searchManagerMock->method('isDestinationTypeApplicable')->willReturn(false);

        $this->tester->mockFactoryMethod('createStorageManager', $storageManagerMock);
        $this->tester->mockFactoryMethod('createSearchManager', $searchManagerMock);
        $this->tester->mockFactoryMethod('getQueueClient', $this->createMock(SynchronizationToQueueClientInterface::class));

        return $storageManagerMock;
    }

    protected function assertBulkMessageProcessing(
        string $operationType,
        bool $expectSendToQueue = false
    ): void {
        $storageManagerMock = $this->createMock(SynchronizationStorage::class);
        $storageManagerMock->method('isDestinationTypeApplicable')
            ->willReturnCallback(function (string $destinationType) {
                return $destinationType === 'storage';
            });

        $searchManagerMock = $this->createMock(SynchronizationSearch::class);
        $searchManagerMock->method('isDestinationTypeApplicable')
            ->willReturnCallback(function (string $destinationType) {
                return $destinationType === 'search';
            });

        $queueClientMock = $this->createMock(SynchronizationToQueueClientInterface::class);

        $operationMethods = [
            'write' => ['write' => true, 'delete' => false],
            'delete' => ['write' => false, 'delete' => true],
            'write-delete' => ['write' => true, 'delete' => true],
            'skip' => ['write' => false, 'delete' => false],
        ];

        $methods = $operationMethods[$operationType];
        $storageManagerMock->expects($methods['write'] ? $this->once() : $this->never())->method('writeBulk');
        $storageManagerMock->expects($methods['delete'] ? $this->once() : $this->never())->method('deleteBulk');
        $searchManagerMock->expects($methods['write'] ? $this->once() : $this->never())->method('writeBulk');
        $searchManagerMock->expects($methods['delete'] ? $this->once() : $this->never())->method('deleteBulk');
        $queueClientMock->expects($expectSendToQueue ? $this->once() : $this->never())->method('sendMessages');

        $this->tester->mockFactoryMethod('createStorageManager', $storageManagerMock);
        $this->tester->mockFactoryMethod('createSearchManager', $searchManagerMock);
        $this->tester->mockFactoryMethod('getQueueClient', $queueClientMock);
    }
}
