<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Synchronization\Business\Synchronizer;

use Generated\Shared\Transfer\SynchronizationMessageTransfer;

interface MessageSynchronizerInterface
{
    public function addSynchronizationMessage(SynchronizationMessageTransfer $synchronizationMessage): void;

    public function flushSynchronizationMessages(): void;
}
