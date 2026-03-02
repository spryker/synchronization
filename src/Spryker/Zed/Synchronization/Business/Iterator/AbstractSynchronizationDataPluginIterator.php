<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Synchronization\Business\Iterator;

use Iterator;
use Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataPluginInterface;

/**
 * @implements \Iterator<int, array>
 */
abstract class AbstractSynchronizationDataPluginIterator implements Iterator
{
    /**
     * @var \Spryker\Zed\SynchronizationExtension\Dependency\Plugin\SynchronizationDataPluginInterface
     */
    protected $plugin;

    /**
     * @var int
     */
    protected $index = 0;

    /**
     * @var array
     */
    protected $current = [];

    /**
     * @var int
     */
    protected $chunkSize;

    /**
     * @var int
     */
    protected $offset = 0;

    public function __construct(SynchronizationDataPluginInterface $plugin, int $chunkSize)
    {
        $this->plugin = $plugin;
        $this->chunkSize = $chunkSize;
    }

    abstract protected function updateCurrent(): void;

    public function current(): array
    {
        return $this->current;
    }

    public function next(): void
    {
        $this->offset += $this->chunkSize;
        $this->index += 1;
        $this->updateCurrent();
    }

    public function key(): int
    {
        return $this->index;
    }

    public function valid(): bool
    {
        return is_array($this->current) && $this->current !== [];
    }

    public function rewind(): void
    {
        $this->offset = 0;
        $this->index = 0;
        $this->updateCurrent();
    }
}
