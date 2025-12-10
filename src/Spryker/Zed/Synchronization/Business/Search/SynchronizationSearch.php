<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Synchronization\Business\Search;

use Generated\Shared\Transfer\ElasticsearchSearchContextTransfer;
use Generated\Shared\Transfer\SearchContextTransfer;
use Generated\Shared\Transfer\SearchDocumentTransfer;
use Spryker\Zed\Synchronization\Business\Synchronization\SynchronizationInterface;
use Spryker\Zed\Synchronization\Business\Validation\OutdatedValidatorInterface;
use Spryker\Zed\Synchronization\Dependency\Client\SynchronizationToSearchClientInterface;
use Spryker\Zed\Synchronization\Dependency\Facade\SynchronizationToStoreFacadeInterface;

class SynchronizationSearch implements SynchronizationInterface
{
    /**
     * @var string
     */
    protected const KEY = 'key';

    /**
     * @var string
     */
    protected const VALUE = 'value';

    /**
     * @var string
     */
    protected const TYPE = 'type';

    /**
     * @var string
     */
    protected const INDEX = 'index';

    /**
     * @var string
     */
    protected const STORE = 'store';

    /**
     * @var string
     */
    protected const TIMESTAMP = '_timestamp';

    /**
     * @var string
     */
    protected const DESTINATION_TYPE = 'search';

    /**
     * @param \Spryker\Zed\Synchronization\Dependency\Client\SynchronizationToSearchClientInterface $searchClient
     * @param \Spryker\Zed\Synchronization\Business\Validation\OutdatedValidatorInterface $outdatedValidator
     * @param \Spryker\Zed\Synchronization\Dependency\Facade\SynchronizationToStoreFacadeInterface $storeFacade
     */
    public function __construct(
        protected SynchronizationToSearchClientInterface $searchClient,
        protected OutdatedValidatorInterface $outdatedValidator,
        protected SynchronizationToStoreFacadeInterface $storeFacade
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param string $queueName
     *
     * @return void
     */
    public function write(array $data, $queueName)
    {
        $typeName = $this->getParam($data, static::TYPE);
        $data = $this->formatTimestamp($data);
        $storeName = null;
        /* Required by infrastructure, exists only for BC with DMS OFF mode. */
        if ($this->storeFacade->isDynamicStoreEnabled()) {
            $data = current($this->expandWithStoreNames([$data]));
            $storeName = $data[static::STORE];
        }
        $existingEntry = $this->read($data[static::KEY], $typeName, $storeName);
        if ($existingEntry !== [] && $this->outdatedValidator->isInvalid($queueName, $data[static::VALUE], $existingEntry)) {
            return;
        }

        /** @var \Generated\Shared\Transfer\SearchDocumentTransfer $searchDocumentTransfer */
        $searchDocumentTransfer = current($this->prepareSearchDocumentTransfers([$data]));
        $this->searchClient->writeDocument($searchDocumentTransfer);
    }

    /**
     * @param array<string, mixed> $data
     * @param string $queueName
     *
     * @return void
     */
    public function delete(array $data, $queueName)
    {
        $typeName = $this->getParam($data, static::TYPE);
        $data = $this->formatTimestamp($data);
        $storeName = null;
        /* Required by infrastructure, exists only for BC with DMS OFF mode. */
        if ($this->storeFacade->isDynamicStoreEnabled()) {
            $data = current($this->expandWithStoreNames([$data]));
            $storeName = $data[static::STORE];
        }
        $existingEntry = $this->read($data[static::KEY], $typeName, $storeName);
        if ($existingEntry !== [] && $this->outdatedValidator->isInvalid($queueName, $data[static::VALUE], $existingEntry)) {
            return;
        }

        /** @var \Generated\Shared\Transfer\SearchDocumentTransfer $searchDocumentTransfer */
        $searchDocumentTransfer = current($this->prepareSearchDocumentTransfers([$data]));
        $this->searchClient->deleteDocument($searchDocumentTransfer);
    }

    /**
     * @param array<string, mixed> $data
     * @param string $parameterName
     *
     * @return string
     */
    protected function getParam(array $data, $parameterName)
    {
        $value = '';
        if (isset($data['params'][$parameterName])) {
            $value = $data['params'][$parameterName];
        }

        return $value;
    }

    /**
     * @param string $key
     * @param string|null $typeName
     *
     * @return array
     */
    protected function read(string $key, ?string $typeName, ?string $storeName)
    {
        $searchDocumentTransfer = (new SearchDocumentTransfer())
            ->setId($key)
            ->setSearchContext(
                (new SearchContextTransfer())
                    ->setSourceIdentifier($typeName)
                    ->setElasticsearchContext(
                        (new ElasticsearchSearchContextTransfer())
                            ->setTypeName($typeName),
                    ),
            );
        if ($storeName !== null) {
            $searchDocumentTransfer->getSearchContext()->setStoreName($storeName);
        }

        /** @var \Generated\Shared\Transfer\SearchDocumentTransfer $searchDocumentTransfer */
        $searchDocumentTransfer = $this->searchClient->readDocument($searchDocumentTransfer);

        return $searchDocumentTransfer->getData();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array
     */
    protected function formatTimestamp(array $data)
    {
        if (!isset($data[static::VALUE][static::TIMESTAMP])) {
            return $data;
        }

        $data[static::VALUE]['timestamp'] = $data[static::VALUE][static::TIMESTAMP];
        unset($data[static::VALUE][static::TIMESTAMP]);

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return void
     */
    public function writeBulk(array $data): void
    {
        /* Required by infrastructure, exists only for BC with DMS OFF mode. */
        if ($this->storeFacade->isDynamicStoreEnabled()) {
            $data = $this->expandWithStoreNames($data);
        }
        $dataSets = $this->prepareSearchDocumentTransfers($data);

        if ($dataSets === []) {
            return;
        }

        $this->searchClient->writeDocuments($dataSets);
    }

    /**
     * @param array<int, mixed> $data
     *
     * @return array<\Generated\Shared\Transfer\SearchDocumentTransfer>
     */
    protected function prepareSearchDocumentTransfers(array $data): array
    {
        $searchDocumentTransfers = [];
        foreach ($data as $datum) {
            $typeName = $this->getParam($datum, static::TYPE);
            $indexName = $this->getParam($datum, static::INDEX);
            $key = $datum[static::KEY];
            $value = $datum[static::VALUE];
            unset($value['_timestamp']);

            $searchDocumentTransfer = new SearchDocumentTransfer();
            $searchDocumentTransfer->setId($key);
            $searchDocumentTransfer->setData($value);
            $searchDocumentTransfer->setSearchContext(
                (new SearchContextTransfer())
                    ->setSourceIdentifier($typeName)
                    ->setElasticsearchContext(
                        (new ElasticsearchSearchContextTransfer())
                            ->setIndexName($indexName)
                            ->setTypeName($typeName),
                    ),
            );

            /* Required by infrastructure, exists only for BC with DMS OFF mode. */
            if ($this->storeFacade->isDynamicStoreEnabled()) {
                $storeName = $datum[static::STORE];
                $searchDocumentTransfer->getSearchContext()->setStoreName($storeName);
            }

            $searchDocumentTransfers[] = $searchDocumentTransfer;
        }

        return $searchDocumentTransfers;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return void
     */
    public function deleteBulk(array $data): void
    {
        /* Required by infrastructure, exists only for BC with DMS OFF mode. */
        if ($this->storeFacade->isDynamicStoreEnabled()) {
            $data = $this->expandWithStoreNames($data);
        }
        $searchDocumentTransfers = $this->prepareSearchDocumentTransfers($data);

        if ($searchDocumentTransfers === []) {
            return;
        }

        $this->searchClient->deleteDocuments($searchDocumentTransfers);
    }

    /**
     * @param string $destinationType
     *
     * @return bool
     */
    public function isDestinationTypeApplicable(string $destinationType): bool
    {
        return $destinationType === static::DESTINATION_TYPE;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function expandWithStoreNames(array $data): array
    {
        $expandedData = [];
        foreach ($data as $datum) {
            if (isset($datum[static::STORE]) && $datum[static::STORE] !== '') {
                $expandedData[] = $datum;

                continue;
            }

            foreach ($this->storeFacade->getAllStores() as $storeTransfer) {
                $storeSpecificData = $datum;
                $storeSpecificData[static::STORE] = $storeTransfer->getName();
                $expandedData[] = $storeSpecificData;
            }
        }

        return $expandedData;
    }
}
