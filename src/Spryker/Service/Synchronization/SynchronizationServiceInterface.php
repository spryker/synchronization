<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Synchronization;

interface SynchronizationServiceInterface
{
    /**
     * Specification:
     * - Returns resource KeyBuilder based on given resource name,
     *  this will provide the key generator class.
     * - The format for single key depends on SynchronizationConfig::isSingleKeyFormatNormalized().
     *
     * @api
     *
     * @param string $resourceName
     *
     * @return \Spryker\Service\Synchronization\Dependency\Plugin\SynchronizationKeyGeneratorPluginInterface
     */
    public function getStorageKeyBuilder($resourceName);

    /**
     * Specification:
     *  - Filters invalid string in given key
     *
     * @api
     *
     * @param string $key
     *
     * @return string
     */
    public function escapeKey($key);
}
