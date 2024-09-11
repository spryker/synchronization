<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Synchronization\Business;

use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;
use Spryker\Zed\Synchronization\Business\Export\ExporterPluginResolver;
use Spryker\Zed\Synchronization\Business\Export\ExporterPluginResolverInterface;
use Spryker\Zed\Synchronization\Business\Export\QueryContainerExporter;
use Spryker\Zed\Synchronization\Business\Export\RepositoryExporter;
use Spryker\Zed\Synchronization\Business\Message\BulkQueueMessageProcessor;
use Spryker\Zed\Synchronization\Business\Message\QueueMessageCreator;
use Spryker\Zed\Synchronization\Business\Message\QueueMessageHelper;
use Spryker\Zed\Synchronization\Business\Message\QueueMessageHelperInterface;
use Spryker\Zed\Synchronization\Business\Message\QueueMessageProcessorInterface;
use Spryker\Zed\Synchronization\Business\Search\SynchronizationSearch;
use Spryker\Zed\Synchronization\Business\Storage\SynchronizationStorage;
use Spryker\Zed\Synchronization\Business\Synchronizer\InMemoryMessageSynchronizer;
use Spryker\Zed\Synchronization\Business\Synchronizer\MessageSynchronizerInterface;
use Spryker\Zed\Synchronization\Business\Validation\OutdatedValidator;
use Spryker\Zed\Synchronization\Dependency\Facade\SynchronizationToStoreFacadeInterface;
use Spryker\Zed\Synchronization\SynchronizationDependencyProvider;
use Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryExpanderStrategyPluginInterface;

/**
 * @method \Spryker\Zed\Synchronization\SynchronizationConfig getConfig()
 */
class SynchronizationBusinessFactory extends AbstractBusinessFactory
{
    /**
     * @var \Spryker\Zed\Synchronization\Business\Synchronizer\MessageSynchronizerInterface|null
     */
    protected static $inMemoryMessageSynchronizer;

    /**
     * @return \Spryker\Zed\Synchronization\Business\Synchronization\SynchronizationInterface
     */
    public function createStorageManager()
    {
        return new SynchronizationStorage(
            $this->getStorageClient(),
            $this->getUtilEncodingService(),
            $this->createOutdatedValidator(),
        );
    }

    /**
     * @return \Spryker\Zed\Synchronization\Business\Synchronization\SynchronizationInterface
     */
    public function createSearchManager()
    {
        return new SynchronizationSearch(
            $this->getSearchClient(),
            $this->createOutdatedValidator(),
            $this->getStoreFacade(),
        );
    }

    /**
     * @return \Spryker\Zed\Synchronization\Business\Export\RepositoryExporter
     */
    public function createRepositoryExporter()
    {
        return new RepositoryExporter(
            $this->getQueueClient(),
            $this->getStoreFacade(),
            $this->createQueueMessageCreator(),
            $this->getUtilEncodingService(),
            $this->getConfig(),
        );
    }

    /**
     * @return \Spryker\Zed\Synchronization\Business\Export\QueryContainerExporter
     */
    public function createQueryContainerExporter()
    {
        return new QueryContainerExporter(
            $this->getQueueClient(),
            $this->getStoreFacade(),
            $this->createQueueMessageCreator(),
            $this->getSynchronizationDataQueryExpanderStrategyPlugin(),
            $this->getConfig()->getSyncExportChunkSize(),
        );
    }

    /**
     * @return \Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryExpanderStrategyPluginInterface
     */
    public function getSynchronizationDataQueryExpanderStrategyPlugin(): SynchronizationDataQueryExpanderStrategyPluginInterface
    {
        return $this->getProvidedDependency(SynchronizationDependencyProvider::PLUGIN_SYNCHRONIZATION_DATA_QUERY_EXPANDER_STRATEGY);
    }

    /**
     * @return \Spryker\Zed\Synchronization\Business\Export\ExporterPluginResolverInterface
     */
    public function createExporterPluginResolver(): ExporterPluginResolverInterface
    {
        return new ExporterPluginResolver(
            $this->getSynchronizationDataPlugins(),
            $this->createQueryContainerExporter(),
            $this->createRepositoryExporter(),
        );
    }

    /**
     * @return \Spryker\Zed\Synchronization\Business\Message\QueueMessageProcessorInterface
     */
    public function createSearchQueueMessageProcessor(): QueueMessageProcessorInterface
    {
        return new BulkQueueMessageProcessor(
            $this->createSearchManager(),
            $this->createQueueMessageHelper(),
        );
    }

    /**
     * @return \Spryker\Zed\Synchronization\Business\Message\QueueMessageProcessorInterface
     */
    public function createStorageQueueMessageProcessor(): QueueMessageProcessorInterface
    {
        return new BulkQueueMessageProcessor(
            $this->createStorageManager(),
            $this->createQueueMessageHelper(),
        );
    }

    /**
     * @return \Spryker\Zed\Synchronization\Business\Message\QueueMessageHelperInterface
     */
    public function createQueueMessageHelper(): QueueMessageHelperInterface
    {
        return new QueueMessageHelper(
            $this->getUtilEncodingService(),
        );
    }

    /**
     * @return \Spryker\Zed\Synchronization\Business\Validation\OutdatedValidatorInterface
     */
    protected function createOutdatedValidator()
    {
        return new OutdatedValidator(
            $this->getConfig(),
        );
    }

    /**
     * @return \Spryker\Zed\Synchronization\Business\Message\QueueMessageCreatorInterface
     */
    protected function createQueueMessageCreator()
    {
        return new QueueMessageCreator(
            $this->getUtilEncodingService(),
        );
    }

    /**
     * @return \Spryker\Zed\Synchronization\Business\Synchronizer\MessageSynchronizerInterface
     */
    public function createInMemoryMessageSynchronizer(): MessageSynchronizerInterface
    {
        if (!static::$inMemoryMessageSynchronizer) {
            static::$inMemoryMessageSynchronizer = new InMemoryMessageSynchronizer(
                $this->getQueueClient(),
                $this->createSynchronizationWriters(),
            );
        }

        return static::$inMemoryMessageSynchronizer;
    }

    /**
     * @return list<\Spryker\Zed\Synchronization\Business\Synchronization\SynchronizationInterface>
     */
    public function createSynchronizationWriters(): array
    {
        return [
            $this->createStorageManager(),
            $this->createSearchManager(),
        ];
    }

    /**
     * @return \Spryker\Zed\Synchronization\Dependency\Client\SynchronizationToStorageClientInterface
     */
    protected function getStorageClient()
    {
        return $this->getProvidedDependency(SynchronizationDependencyProvider::CLIENT_STORAGE);
    }

    /**
     * @return \Spryker\Zed\Synchronization\Dependency\Client\SynchronizationToSearchClientInterface
     */
    public function getSearchClient()
    {
        return $this->getProvidedDependency(SynchronizationDependencyProvider::CLIENT_SEARCH);
    }

    /**
     * @return \Spryker\Zed\Synchronization\Dependency\Client\SynchronizationToQueueClientInterface
     */
    public function getQueueClient()
    {
        return $this->getProvidedDependency(SynchronizationDependencyProvider::CLIENT_QUEUE);
    }

    /**
     * @return \Spryker\Zed\Synchronization\Dependency\Service\SynchronizationToUtilEncodingServiceInterface
     */
    public function getUtilEncodingService()
    {
        return $this->getProvidedDependency(SynchronizationDependencyProvider::SERVICE_UTIL_ENCODING);
    }

    /**
     * @return array<\Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataQueryContainerPluginInterface>
     */
    public function getSynchronizationDataPlugins()
    {
        return $this->getProvidedDependency(SynchronizationDependencyProvider::PLUGINS_SYNCHRONIZATION_DATA);
    }

    /**
     * @return \Spryker\Zed\Synchronization\Dependency\Facade\SynchronizationToStoreFacadeInterface
     */
    public function getStoreFacade(): SynchronizationToStoreFacadeInterface
    {
        return $this->getProvidedDependency(SynchronizationDependencyProvider::FACADE_STORE);
    }
}
