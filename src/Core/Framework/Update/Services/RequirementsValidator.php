<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Checkers\CheckerInterface;
use Shopware\Core\Framework\Update\Struct\Version;

#[Package('system-settings')]
class RequirementsValidator
{
    /**
     * @internal
     *
     * @param CheckerInterface[] $checkers
     */
    public function __construct(private readonly iterable $checkers)
    {
    }

    public function validate(Version $version): array
    {
        $results = [];

        foreach ($version->checks as $check) {
            foreach ($this->checkers as $checker) {
                if ($checker->supports($check['type'])) {
                    $results[] = $checker->check($check['value']);
                }
            }
        }

        return $results;
    }
}
