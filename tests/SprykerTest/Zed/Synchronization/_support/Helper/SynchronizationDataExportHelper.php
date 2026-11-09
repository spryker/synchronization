<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Synchronization\Helper;

use ArrayObject;
use Closure;
use Codeception\Module;
use Codeception\Stub;
use Codeception\TestInterface;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\PropelQuery;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;
use Spryker\Shared\Synchronization\SynchronizationConstants;
use Spryker\Zed\Synchronization\Business\SynchronizationBusinessFactory;
use Spryker\Zed\Synchronization\Business\SynchronizationFacade;
use Spryker\Zed\Synchronization\Business\SynchronizationFacadeInterface;
use Spryker\Zed\Synchronization\Communication\Plugin\Synchronization\SynchronizationDataQueryExpanderOffsetLimitStrategyPlugin;
use Spryker\Zed\Synchronization\Dependency\Client\SynchronizationToQueueClientInterface;
use Spryker\Zed\Synchronization\SynchronizationConfig;
use Spryker\Zed\Synchronization\SynchronizationDependencyProvider;
use Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryContainerPluginInterface;
use Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryExpanderStrategyPluginInterface;
use SprykerTest\Shared\Testify\Helper\ConfigHelperTrait;
use SprykerTest\Shared\Testify\Helper\DependencyHelperTrait;
use SprykerTest\Shared\Testify\Helper\LocatorHelperTrait;

class SynchronizationDataExportHelper extends Module
{
    use ConfigHelperTrait;
    use DependencyHelperTrait;
    use LocatorHelperTrait;

    public const int EXPORT_CHUNK_SIZE = 100;

    protected const string COLUMN_PREFIX_FOREIGN_KEY = 'fk_';

    protected const string COLUMN_URL = 'url';

    protected const string COLUMN_DATA = 'data';

    protected const string COLUMN_LOCALE = 'locale';

    protected const string COLUMN_STORE = 'store';

    protected const string COLUMN_KEY = 'key';

    protected const string MESSAGE_KEY_STORE_SEPARATOR = '|';

    protected const string URL_PATTERN = '/sync-data-pk-gap-%d';

    protected const string SYNCHRONIZATION_QUEUE_POOL_NAME = 'synchronizationPool';

    /**
     * @var array<int>
     */
    protected array $primaryKeysToDeleteAfterFirstChunk = [];

    protected int $exportChunkSize = self::EXPORT_CHUNK_SIZE;

    /**
     * @param array<int> $primaryKeys
     *
     * @return array<int, string>
     */
    public function haveOnlySynchronizationEntitiesWithPrimaryKeys(
        SynchronizationDataQueryContainerPluginInterface $synchronizationDataPlugin,
        array $primaryKeys
    ): array {
        $tableMap = $synchronizationDataPlugin->queryData()->getTableMap();
        PropelQuery::from($tableMap->getClassName())->deleteAll();

        $synchronizationKeys = [];
        foreach ($primaryKeys as $primaryKey) {
            $synchronizationKeys[$primaryKey] = $this->createSynchronizationEntityWithPrimaryKey($tableMap, $primaryKey);
        }

        return $synchronizationKeys;
    }

    /**
     * @param array<int> $ids
     *
     * @return array<string>
     */
    public function exportSynchronizationData(
        SynchronizationDataQueryContainerPluginInterface $synchronizationDataPlugin,
        SynchronizationDataQueryExpanderStrategyPluginInterface $synchronizationDataQueryExpanderStrategyPlugin,
        array $ids = []
    ): array {
        return $this->executeExport($synchronizationDataPlugin, $synchronizationDataQueryExpanderStrategyPlugin, $ids, false);
    }

    /**
     * @param array<int> $ids
     *
     * @return array<string>
     */
    public function exportSynchronizationDataWithKeysetPagination(
        SynchronizationDataQueryContainerPluginInterface $synchronizationDataPlugin,
        array $ids = []
    ): array {
        return $this->executeExport($synchronizationDataPlugin, new SynchronizationDataQueryExpanderOffsetLimitStrategyPlugin(), $ids, true);
    }

    /**
     * @param array<int> $primaryKeys
     */
    public function deleteSynchronizationEntitiesAfterFirstExportedChunk(array $primaryKeys): void
    {
        $this->primaryKeysToDeleteAfterFirstChunk = $primaryKeys;
    }

    public function haveSynchronizationExportChunkSize(int $exportChunkSize): void
    {
        $this->exportChunkSize = $exportChunkSize;
    }

    public function _before(TestInterface $test): void
    {
        $this->primaryKeysToDeleteAfterFirstChunk = [];
        $this->exportChunkSize = static::EXPORT_CHUNK_SIZE;
    }

    /**
     * @param array<int> $ids
     *
     * @return array<string>
     */
    protected function executeExport(
        SynchronizationDataQueryContainerPluginInterface $synchronizationDataPlugin,
        SynchronizationDataQueryExpanderStrategyPluginInterface $synchronizationDataQueryExpanderStrategyPlugin,
        array $ids,
        bool $isKeysetPaginationEnabled
    ): array {
        $sentMessageKeys = new ArrayObject();
        $synchronizationDataPlugin = $this->createSynchronizationDataPluginWithQueuePoolNameFallback($synchronizationDataPlugin);

        $this->getConfigHelper()->setConfig(SynchronizationConstants::EXPORT_MESSAGE_CHUNK_SIZE, $this->exportChunkSize);
        $this->setSynchronizationDependency(SynchronizationDependencyProvider::PLUGINS_SYNCHRONIZATION_DATA, [$synchronizationDataPlugin]);
        $this->setSynchronizationDependency(SynchronizationDependencyProvider::PLUGIN_SYNCHRONIZATION_DATA_QUERY_EXPANDER_STRATEGY, $synchronizationDataQueryExpanderStrategyPlugin);
        $this->setSynchronizationDependency(
            SynchronizationDependencyProvider::CLIENT_QUEUE,
            $this->createQueueClientCollectingSentMessageKeys($sentMessageKeys, $this->createDeleteAfterFirstChunkCallback($synchronizationDataPlugin)),
        );

        $this->createSynchronizationFacade($isKeysetPaginationEnabled)->executeResolvedPluginsBySourcesWithIds(
            [$synchronizationDataPlugin->getResourceName()],
            $ids,
        );

        return $sentMessageKeys->getArrayCopy();
    }

    protected function createSynchronizationFacade(bool $isKeysetPaginationEnabled): SynchronizationFacadeInterface
    {
        $synchronizationConfig = Stub::make(SynchronizationConfig::class, [
            'isQueryContainerSyncExportKeysetPaginationEnabled' => $isKeysetPaginationEnabled,
        ]);

        $synchronizationBusinessFactory = new SynchronizationBusinessFactory();
        $synchronizationBusinessFactory->setConfig($synchronizationConfig);

        $synchronizationFacade = new SynchronizationFacade();
        $synchronizationFacade->setFactory($synchronizationBusinessFactory);

        return $synchronizationFacade;
    }

    /**
     * @param array<string> $expectedSynchronizationKeys
     * @param array<string> $sentMessageKeys
     */
    public function assertSynchronizationKeysExported(array $expectedSynchronizationKeys, array $sentMessageKeys): void
    {
        $exportedSynchronizationKeys = array_values(array_unique(array_map(
            static fn (string $sentMessageKey): string => explode(static::MESSAGE_KEY_STORE_SEPARATOR, $sentMessageKey)[0],
            $sentMessageKeys,
        )));

        $expectedSynchronizationKeys = array_values($expectedSynchronizationKeys);

        sort($exportedSynchronizationKeys);
        sort($expectedSynchronizationKeys);

        $this->assertSame($expectedSynchronizationKeys, $exportedSynchronizationKeys);
    }

    /**
     * @param array<string> $sentMessageKeys
     */
    public function assertSynchronizationKeysExportedOnce(array $sentMessageKeys): void
    {
        $this->assertSame(array_values(array_unique($sentMessageKeys)), $sentMessageKeys);
    }

    protected function createSynchronizationEntityWithPrimaryKey(TableMap $tableMap, int $primaryKey): string
    {
        $entityClassName = $tableMap->getClassName();

        /** @var \Propel\Runtime\ActiveRecord\ActiveRecordInterface $synchronizationEntity */
        $synchronizationEntity = new $entityClassName();
        $columnValues = $this->getColumnValues($primaryKey);

        foreach ($tableMap->getColumns() as $columnMap) {
            $this->setColumnValue($synchronizationEntity, $columnMap, $columnValues, $primaryKey);
        }

        if (method_exists($synchronizationEntity, 'setIsSendingToQueue')) {
            $synchronizationEntity->setIsSendingToQueue(false);
        }

        $synchronizationEntity->save();

        $primaryKeyColumnMap = current($tableMap->getPrimaryKeys());
        PropelQuery::from($entityClassName)
            ->filterByPrimaryKey($synchronizationEntity->getPrimaryKey())
            ->update([$primaryKeyColumnMap->getPhpName() => $primaryKey]);

        return $synchronizationEntity->getByName(static::COLUMN_KEY);
    }

    /**
     * @param array<string, mixed> $columnValues
     */
    protected function setColumnValue(
        ActiveRecordInterface $synchronizationEntity,
        ColumnMap $columnMap,
        array $columnValues,
        int $primaryKey
    ): void {
        $columnName = $columnMap->getName();

        if ($columnMap->isPrimaryKey()) {
            return;
        }

        if (str_starts_with($columnName, static::COLUMN_PREFIX_FOREIGN_KEY) && $columnMap->isNotNull()) {
            $synchronizationEntity->setByName($columnName, $primaryKey);

            return;
        }

        if (!array_key_exists($columnName, $columnValues)) {
            return;
        }

        $synchronizationEntity->setByName($columnName, $columnValues[$columnName]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getColumnValues(int $primaryKey): array
    {
        $storeTransfer = $this->getLocator()->store()->facade()->getCurrentStore(true);

        return [
            static::COLUMN_URL => sprintf(static::URL_PATTERN, $primaryKey),
            static::COLUMN_DATA => [static::COLUMN_DATA => $primaryKey],
            static::COLUMN_LOCALE => (string)current($storeTransfer->getAvailableLocaleIsoCodes()),
            static::COLUMN_STORE => $storeTransfer->getNameOrFail(),
        ];
    }

    protected function createSynchronizationDataPluginWithQueuePoolNameFallback(
        SynchronizationDataQueryContainerPluginInterface $synchronizationDataPlugin
    ): SynchronizationDataQueryContainerPluginInterface {
        return new class ($synchronizationDataPlugin, static::SYNCHRONIZATION_QUEUE_POOL_NAME) implements SynchronizationDataQueryContainerPluginInterface {
            public function __construct(
                protected SynchronizationDataQueryContainerPluginInterface $synchronizationDataPlugin,
                protected string $fallbackSynchronizationQueuePoolName
            ) {
            }

            public function getResourceName(): string
            {
                return $this->synchronizationDataPlugin->getResourceName();
            }

            public function hasStore(): bool
            {
                return $this->synchronizationDataPlugin->hasStore();
            }

            /**
             * @return array<mixed>
             */
            public function getParams(): array
            {
                return $this->synchronizationDataPlugin->getParams();
            }

            public function getQueueName(): string
            {
                return $this->synchronizationDataPlugin->getQueueName();
            }

            public function getSynchronizationQueuePoolName(): ?string
            {
                return $this->synchronizationDataPlugin->getSynchronizationQueuePoolName() ?? $this->fallbackSynchronizationQueuePoolName;
            }

            /**
             * @param array<int> $ids
             */
            public function queryData($ids = []): ?ModelCriteria
            {
                return $this->synchronizationDataPlugin->queryData($ids);
            }
        };
    }

    protected function setSynchronizationDependency(string $key, mixed $value): void
    {
        $this->getDependencyHelper()->setDependency($key, $value, SynchronizationBusinessFactory::class);
    }

    protected function createDeleteAfterFirstChunkCallback(SynchronizationDataQueryContainerPluginInterface $synchronizationDataPlugin): Closure
    {
        $primaryKeys = $this->primaryKeysToDeleteAfterFirstChunk;
        $tableMap = $synchronizationDataPlugin->queryData()->getTableMap();

        return static function () use ($primaryKeys, $tableMap): void {
            if ($primaryKeys === []) {
                return;
            }

            PropelQuery::from($tableMap->getClassName())->filterByPrimaryKeys($primaryKeys)->delete();
        };
    }

    /**
     * @param \ArrayObject<int, string> $sentMessageKeys
     */
    protected function createQueueClientCollectingSentMessageKeys(
        ArrayObject $sentMessageKeys,
        Closure $afterFirstChunkCallback
    ): SynchronizationToQueueClientInterface {
        return new class ($sentMessageKeys, $afterFirstChunkCallback) implements SynchronizationToQueueClientInterface {
            protected bool $isFirstChunkSent = false;

            /**
             * @param \ArrayObject<int, string> $sentMessageKeys
             */
            public function __construct(protected ArrayObject $sentMessageKeys, protected Closure $afterFirstChunkCallback)
            {
            }

            /**
             * @param string $queueName
             * @param array<\Generated\Shared\Transfer\QueueSendMessageTransfer> $queueSendMessageTransfers
             */
            public function sendMessages($queueName, array $queueSendMessageTransfers): void
            {
                foreach ($queueSendMessageTransfers as $queueSendMessageTransfer) {
                    $body = json_decode($queueSendMessageTransfer->getBodyOrFail(), true);
                    $this->sentMessageKeys->append(sprintf('%s|%s', $body['write']['key'], $queueSendMessageTransfer->getStoreName()));
                }

                if ($this->isFirstChunkSent) {
                    return;
                }

                $this->isFirstChunkSent = true;
                ($this->afterFirstChunkCallback)();
            }
        };
    }
}
