<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Synchronization\Business\Facade;

use Codeception\Test\Unit;
use Spryker\Zed\Synchronization\Communication\Plugin\Synchronization\SynchronizationDataQueryExpanderOffsetLimitStrategyPlugin;
use Spryker\Zed\Synchronization\Communication\Plugin\Synchronization\SynchronizationDataQueryExpanderWhereBetweenStrategyPlugin;
use Spryker\Zed\UrlStorage\Communication\Plugin\Synchronization\UrlSynchronizationDataPlugin;
use SprykerTest\Zed\Synchronization\Helper\SynchronizationDataExportHelper;
use SprykerTest\Zed\Synchronization\SynchronizationBusinessTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Synchronization
 * @group Business
 * @group Facade
 * @group ExecuteResolvedPluginsBySourcesWithIdsTest
 * Add your own group annotations below this line
 */
class ExecuteResolvedPluginsBySourcesWithIdsTest extends Unit
{
    protected SynchronizationBusinessTester $tester;

    public function testExportsAllRowsWhenLowestPrimaryKeyIsAboveChunkSizeWithWhereBetweenStrategy(): void
    {
        // Arrange
        $expectedKeys = $this->tester->haveOnlySynchronizationEntitiesWithPrimaryKeys(new UrlSynchronizationDataPlugin(), [1001, 1002, 1003]);

        // Act
        $sentMessageKeys = $this->tester->exportSynchronizationData(new UrlSynchronizationDataPlugin(), new SynchronizationDataQueryExpanderWhereBetweenStrategyPlugin());

        // Assert
        $this->tester->assertSynchronizationKeysExported($expectedKeys, $sentMessageKeys);
    }

    public function testExportsRowsLocatedAfterPrimaryKeyGapLargerThanChunkSizeWithWhereBetweenStrategy(): void
    {
        // Arrange
        $expectedKeys = $this->tester->haveOnlySynchronizationEntitiesWithPrimaryKeys(new UrlSynchronizationDataPlugin(), [1, 2, 500, 501]);

        // Act
        $sentMessageKeys = $this->tester->exportSynchronizationData(new UrlSynchronizationDataPlugin(), new SynchronizationDataQueryExpanderWhereBetweenStrategyPlugin());

        // Assert
        $this->tester->assertSynchronizationKeysExported($expectedKeys, $sentMessageKeys);
    }

    public function testExportsAllFilteredRowsWhenLowestPrimaryKeyIsAboveChunkSizeWithWhereBetweenStrategy(): void
    {
        // Arrange
        $expectedKeys = $this->tester->haveOnlySynchronizationEntitiesWithPrimaryKeys(new UrlSynchronizationDataPlugin(), [1001, 1002, 1003]);

        // Act
        $sentMessageKeys = $this->tester->exportSynchronizationData(new UrlSynchronizationDataPlugin(), new SynchronizationDataQueryExpanderWhereBetweenStrategyPlugin(), [1001, 1002, 1003]);

        // Assert
        $this->tester->assertSynchronizationKeysExported($expectedKeys, $sentMessageKeys);
    }

    public function testExportsRowOnChunkBoundaryOnlyOnceWithWhereBetweenStrategy(): void
    {
        // Arrange
        $this->tester->haveOnlySynchronizationEntitiesWithPrimaryKeys(new UrlSynchronizationDataPlugin(), [50, SynchronizationDataExportHelper::EXPORT_CHUNK_SIZE]);

        // Act
        $sentMessageKeys = $this->tester->exportSynchronizationData(new UrlSynchronizationDataPlugin(), new SynchronizationDataQueryExpanderWhereBetweenStrategyPlugin());

        // Assert
        $this->tester->assertSynchronizationKeysExportedOnce($sentMessageKeys);
    }

    public function testExportsAllRowsWhenLowestPrimaryKeyIsAboveChunkSizeWithKeysetPagination(): void
    {
        // Arrange
        $expectedKeys = $this->tester->haveOnlySynchronizationEntitiesWithPrimaryKeys(new UrlSynchronizationDataPlugin(), [1001, 1002, 1003]);

        // Act
        $sentMessageKeys = $this->tester->exportSynchronizationDataWithKeysetPagination(new UrlSynchronizationDataPlugin());

        // Assert
        $this->tester->assertSynchronizationKeysExported($expectedKeys, $sentMessageKeys);
    }

    public function testExportsRowsLocatedAfterPrimaryKeyGapLargerThanChunkSizeWithKeysetPagination(): void
    {
        // Arrange
        $expectedKeys = $this->tester->haveOnlySynchronizationEntitiesWithPrimaryKeys(new UrlSynchronizationDataPlugin(), [1, 2, 500, 501]);

        // Act
        $sentMessageKeys = $this->tester->exportSynchronizationDataWithKeysetPagination(new UrlSynchronizationDataPlugin());

        // Assert
        $this->tester->assertSynchronizationKeysExported($expectedKeys, $sentMessageKeys);
    }

    public function testExportsOnlyFilteredRowsWithKeysetPagination(): void
    {
        // Arrange
        $synchronizationKeys = $this->tester->haveOnlySynchronizationEntitiesWithPrimaryKeys(new UrlSynchronizationDataPlugin(), [1, 2, 1001, 1002]);

        // Act
        $sentMessageKeys = $this->tester->exportSynchronizationDataWithKeysetPagination(new UrlSynchronizationDataPlugin(), [1001, 1002]);

        // Assert
        $this->tester->assertSynchronizationKeysExported([$synchronizationKeys[1001], $synchronizationKeys[1002]], $sentMessageKeys);
    }

    public function testExportsEachRowOnceWhenRowCountIsMultipleOfChunkSizeWithKeysetPagination(): void
    {
        // Arrange
        $expectedKeys = $this->tester->haveOnlySynchronizationEntitiesWithPrimaryKeys(new UrlSynchronizationDataPlugin(), range(1, SynchronizationDataExportHelper::EXPORT_CHUNK_SIZE * 2));

        // Act
        $sentMessageKeys = $this->tester->exportSynchronizationDataWithKeysetPagination(new UrlSynchronizationDataPlugin());

        // Assert
        $this->tester->assertSynchronizationKeysExported($expectedKeys, $sentMessageKeys);
        $this->tester->assertSynchronizationKeysExportedOnce($sentMessageKeys);
    }

    public function testExportsAllRowsWhenExportedRowsAreDeletedDuringExportWithKeysetPagination(): void
    {
        // Arrange
        $expectedKeys = $this->tester->haveOnlySynchronizationEntitiesWithPrimaryKeys(new UrlSynchronizationDataPlugin(), range(1, 250));
        $this->tester->deleteSynchronizationEntitiesAfterFirstExportedChunk(range(1, 10));

        // Act
        $sentMessageKeys = $this->tester->exportSynchronizationDataWithKeysetPagination(new UrlSynchronizationDataPlugin());

        // Assert
        $this->tester->assertSynchronizationKeysExported($expectedKeys, $sentMessageKeys);
    }

    /**
     * @dataProvider smallExportChunkSizeDataProvider
     */
    public function testExportsAllRowsWithSmallChunkSizeWithWhereBetweenStrategy(int $exportChunkSize): void
    {
        // Arrange
        $expectedKeys = $this->tester->haveOnlySynchronizationEntitiesWithPrimaryKeys(new UrlSynchronizationDataPlugin(), [1, 2, 3, 7, 8]);
        $this->tester->haveSynchronizationExportChunkSize($exportChunkSize);

        // Act
        $sentMessageKeys = $this->tester->exportSynchronizationData(new UrlSynchronizationDataPlugin(), new SynchronizationDataQueryExpanderWhereBetweenStrategyPlugin());

        // Assert
        $this->tester->assertSynchronizationKeysExported($expectedKeys, $sentMessageKeys);
    }

    /**
     * @dataProvider smallExportChunkSizeDataProvider
     */
    public function testExportsAllRowsWithSmallChunkSizeWithKeysetPagination(int $exportChunkSize): void
    {
        // Arrange
        $expectedKeys = $this->tester->haveOnlySynchronizationEntitiesWithPrimaryKeys(new UrlSynchronizationDataPlugin(), [1, 2, 3, 7, 8]);
        $this->tester->haveSynchronizationExportChunkSize($exportChunkSize);

        // Act
        $sentMessageKeys = $this->tester->exportSynchronizationDataWithKeysetPagination(new UrlSynchronizationDataPlugin());

        // Assert
        $this->tester->assertSynchronizationKeysExported($expectedKeys, $sentMessageKeys);
    }

    /**
     * @return array<string, array<int>>
     */
    public static function smallExportChunkSizeDataProvider(): array
    {
        return [
            'chunk size 2' => [2],
            'chunk size 0' => [0],
        ];
    }

    public function testExportsRowsLocatedAfterPrimaryKeyGapLargerThanChunkSizeWithOffsetLimitStrategy(): void
    {
        // Arrange
        $expectedKeys = $this->tester->haveOnlySynchronizationEntitiesWithPrimaryKeys(new UrlSynchronizationDataPlugin(), [1, 2, 500, 501]);

        // Act
        $sentMessageKeys = $this->tester->exportSynchronizationData(new UrlSynchronizationDataPlugin(), new SynchronizationDataQueryExpanderOffsetLimitStrategyPlugin());

        // Assert
        $this->tester->assertSynchronizationKeysExported($expectedKeys, $sentMessageKeys);
    }
}
