<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Synchronization\Business\Facade;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\QueueReceiveMessageTransfer;
use Generated\Shared\Transfer\QueueSendMessageTransfer;
use RuntimeException;
use Spryker\Shared\Synchronization\SynchronizationConfig;
use Spryker\Zed\Synchronization\Dependency\Client\SynchronizationToStorageClientInterface;
use Spryker\Zed\Synchronization\SynchronizationDependencyProvider;
use SprykerTest\Zed\Synchronization\SynchronizationBusinessTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Synchronization
 * @group Business
 * @group Facade
 * @group ProcessStorageMessagesTest
 * Add your own group annotations below this line
 */
class ProcessStorageMessagesTest extends Unit
{
    /**
     * @var string
     */
    protected const QUEUE_NAME = 'sync.storage.product';

    /**
     * @var string
     */
    protected const STORAGE_ERROR_MESSAGE = 'OOM command not allowed when used memory > \'maxmemory\'.';

    /**
     * @var \SprykerTest\Zed\Synchronization\SynchronizationBusinessTester
     */
    protected SynchronizationBusinessTester $tester;

    public function testGivenStorageWriteFailsWhenProcessingMessagesThenErrorMessageContainsExceptionClassAndQueueName(): void
    {
        // Arrange
        $this->mockStorageClientThrowingOn('setMulti');
        $queueReceiveMessageTransfer = $this->createQueueReceiveMessageTransfer(['write' => ['key' => 'product:1', 'value' => ['id' => 1]]]);

        // Act
        $queueReceiveMessageTransfers = $this->tester->getFacade()->processStorageMessages([$queueReceiveMessageTransfer]);

        // Assert
        $this->assertFailedMessageHasDescriptiveError($queueReceiveMessageTransfers[0]);
    }

    public function testGivenStorageDeleteFailsWhenProcessingMessagesThenErrorMessageContainsExceptionClassAndQueueName(): void
    {
        // Arrange
        $this->mockStorageClientThrowingOn('deleteMulti');
        $queueReceiveMessageTransfer = $this->createQueueReceiveMessageTransfer(['delete' => ['key' => 'product:1']]);

        // Act
        $queueReceiveMessageTransfers = $this->tester->getFacade()->processStorageMessages([$queueReceiveMessageTransfer]);

        // Assert
        $this->assertFailedMessageHasDescriptiveError($queueReceiveMessageTransfers[0]);
    }

    protected function mockStorageClientThrowingOn(string $methodName): void
    {
        $storageClientMock = $this->createMock(SynchronizationToStorageClientInterface::class);
        $storageClientMock->method($methodName)->willThrowException(new RuntimeException(static::STORAGE_ERROR_MESSAGE));

        $this->tester->setDependency(SynchronizationDependencyProvider::CLIENT_STORAGE, $storageClientMock);
    }

    /**
     * @param array<string, mixed> $messageBody
     *
     * @return \Generated\Shared\Transfer\QueueReceiveMessageTransfer
     */
    protected function createQueueReceiveMessageTransfer(array $messageBody): QueueReceiveMessageTransfer
    {
        return (new QueueReceiveMessageTransfer())
            ->setQueueName(static::QUEUE_NAME)
            ->setDeliveryTag('1')
            ->setQueueMessage((new QueueSendMessageTransfer())->setBody(json_encode($messageBody)));
    }

    protected function assertFailedMessageHasDescriptiveError(QueueReceiveMessageTransfer $queueReceiveMessageTransfer): void
    {
        $messageBody = json_decode($queueReceiveMessageTransfer->getQueueMessage()->getBody(), true);

        $this->assertTrue($queueReceiveMessageTransfer->getHasError());
        $this->assertSame(SynchronizationConfig::MESSAGE_ROUTING_KEY_ERROR, $queueReceiveMessageTransfer->getRoutingKey());
        $this->assertSame(
            sprintf('Synchronization: [%s] queue "%s": %s', RuntimeException::class, static::QUEUE_NAME, static::STORAGE_ERROR_MESSAGE),
            $messageBody['errorMessage'],
        );
    }
}
