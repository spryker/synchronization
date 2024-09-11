<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Synchronization\Business\Storage;

use Spryker\Zed\Synchronization\Business\Synchronization\SynchronizationInterface;
use Spryker\Zed\Synchronization\Business\Validation\OutdatedValidatorInterface;
use Spryker\Zed\Synchronization\Dependency\Client\SynchronizationToStorageClientInterface;
use Spryker\Zed\Synchronization\Dependency\Service\SynchronizationToUtilEncodingServiceInterface;

class SynchronizationStorage implements SynchronizationInterface
{
    /**
     * @var string
     */
    public const KEY = 'key';

    /**
     * @var string
     */
    public const VALUE = 'value';

    /**
     * @var string
     */
    protected const DESTINATION_TYPE = 'storage';

    /**
     * @var \Spryker\Zed\Synchronization\Dependency\Client\SynchronizationToStorageClientInterface
     */
    protected $storageClient;

    /**
     * @var \Spryker\Zed\Synchronization\Dependency\Service\SynchronizationToUtilEncodingServiceInterface
     */
    protected $utilEncodingService;

    /**
     * @var \Spryker\Zed\Synchronization\Business\Validation\OutdatedValidatorInterface
     */
    protected $outdatedValidator;

    /**
     * @param \Spryker\Zed\Synchronization\Dependency\Client\SynchronizationToStorageClientInterface $storageClient
     * @param \Spryker\Zed\Synchronization\Dependency\Service\SynchronizationToUtilEncodingServiceInterface $utilEncodingService
     * @param \Spryker\Zed\Synchronization\Business\Validation\OutdatedValidatorInterface $outdatedValidator
     */
    public function __construct(
        SynchronizationToStorageClientInterface $storageClient,
        SynchronizationToUtilEncodingServiceInterface $utilEncodingService,
        OutdatedValidatorInterface $outdatedValidator
    ) {
        $this->storageClient = $storageClient;
        $this->utilEncodingService = $utilEncodingService;
        $this->outdatedValidator = $outdatedValidator;
    }

    /**
     * @param array<string, mixed> $data
     * @param string $queueName
     *
     * @return void
     */
    public function write(array $data, $queueName)
    {
        $key = $data[static::KEY];
        $value = $data[static::VALUE];
        $existingEntry = $this->get($key);

        if ($existingEntry !== null && $this->outdatedValidator->isInvalid($queueName, $value, $existingEntry)) {
            return;
        }

        $this->set($key, $value);
    }

    /**
     * @param array<string, mixed> $data
     * @param string $queueName
     *
     * @return void
     */
    public function delete(array $data, $queueName)
    {
        $key = $data[static::KEY];
        $value = $data[static::VALUE];
        $existingEntry = $this->get($key);

        if ($existingEntry !== null && $this->outdatedValidator->isInvalid($queueName, $value, $existingEntry)) {
            return;
        }

        $this->del($key);
    }

    /**
     * @param string $key
     * @param array $value
     *
     * @return void
     */
    protected function set($key, array $value)
    {
        $this->storageClient->set($key, $this->getEncodedValue($value));
    }

    /**
     * @param string $key
     *
     * @return void
     */
    protected function del($key)
    {
        $this->storageClient->delete($key);
    }

    /**
     * @param string $key
     *
     * @return array|null
     */
    protected function get($key)
    {
        return $this->storageClient->get($key);
    }

    /**
     * @param array $value
     *
     * @return string
     */
    protected function getEncodedValue($value)
    {
        return $this->utilEncodingService->encodeJson($value);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return void
     */
    public function writeBulk(array $data): void
    {
        $storageWriteMessages = [];
        foreach ($data as $message) {
            $key = $message['key'];
            $value = $message['value'];

            $storageWriteMessages[$key] = $value;
        }

        if ($storageWriteMessages === []) {
            return;
        }

        $this->storageClient->setMulti($storageWriteMessages);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return void
     */
    public function deleteBulk(array $data): void
    {
        $keysToDelete = [];

        foreach ($data as $message) {
            $keysToDelete[] = $message[static::KEY];
        }

        if ($keysToDelete === []) {
            return;
        }

        $this->storageClient->deleteMulti($keysToDelete);
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
}
