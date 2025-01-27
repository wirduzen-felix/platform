<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Shopware\Core\Framework\Event\FlowEventAware;

#[Package('core')]
interface LogAware extends FlowEventAware
{
    public function getLogData(): array;

    /**
     * @return 100|200|250|300|400|500|550|600
     */
    public function getLogLevel(): int;
}
