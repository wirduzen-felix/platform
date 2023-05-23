<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginExtractor;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\PluginZipDetector;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Plugin\PluginManagementService
 */
#[Package('core')]
class PluginManagementServiceTest extends TestCase
{
    public function testRefreshesPluginsAfterDownloadingFromStore(): void
    {
        $client = $this->createClient([new Response()]);

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects(static::once())->method('refreshPlugins');

        $pluginManagementService = new PluginManagementService(
            '',
            $this->createMock(PluginZipDetector::class),
            $this->createMock(PluginExtractor::class),
            $pluginService,
            $this->createMock(Filesystem::class),
            $this->createMock(CacheClearer::class),
            $client
        );

        $pluginManagementService->downloadStorePlugin(
            $this->createPluginDownloadDataStruct('location', 'plugin'),
            Context::createDefaultContext()
        );
    }

    public function testDoesNotRefreshPluginsAfterStoreDownloadIfTypeIsNotPlugin(): void
    {
        $client = $this->createClient([new Response()]);

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects(static::never())
            ->method('refreshPlugins');

        $pluginManagementService = new PluginManagementService(
            '',
            $this->createMock(PluginZipDetector::class),
            $this->createMock(PluginExtractor::class),
            $pluginService,
            $this->createMock(Filesystem::class),
            $this->createMock(CacheClearer::class),
            $client
        );

        $pluginManagementService->downloadStorePlugin(
            $this->createPluginDownloadDataStruct('location', 'app'),
            Context::createDefaultContext()
        );
    }

    /**
     * @param Response[] $responses
     */
    private function createClient(array $responses = []): Client
    {
        $mockHandler = new MockHandler($responses);

        return new Client(['handler' => $mockHandler]);
    }

    private function createPluginDownloadDataStruct(string $location, string $type): PluginDownloadDataStruct
    {
        $pluginDownloadData = new PluginDownloadDataStruct();
        $pluginDownloadData->assign([
            'location' => $location,
            'type' => $type,
        ]);

        return $pluginDownloadData;
    }
}
