<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Synchronization;

use Spryker\Service\Kernel\AbstractService;

/**
 * @method \Spryker\Service\Synchronization\SynchronizationServiceFactory getFactory()
 */
class SynchronizationService extends AbstractService implements SynchronizationServiceInterface
{
    /**
     * @var array<string, \Spryker\Service\Synchronization\Dependency\Plugin\SynchronizationKeyGeneratorPluginInterface>
     */
    protected array $storageKeyBuilders = [];

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $resourceName
     *
     * @return \Spryker\Service\Synchronization\Dependency\Plugin\SynchronizationKeyGeneratorPluginInterface
     */
    public function getStorageKeyBuilder($resourceName)
    {
        if (!isset($this->storageKeyBuilders[$resourceName])) {
            return $this->storageKeyBuilders[$resourceName] = $this->getFactory()
                ->createSynchronizationKeyBuilder()
                ->getStorageKeyBuilder($resourceName);
        }

        return $this->storageKeyBuilders[$resourceName];
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $key
     *
     * @return string
     */
    public function escapeKey($key)
    {
        return $this->getFactory()->createKeyFilter()->escapeKey($key);
    }
}
