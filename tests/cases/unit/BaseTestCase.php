<?php declare(strict_types = 1);

namespace Tests\Cases\Unit;

use FastyBird\HomeKitConnector;
use Nette;
use Nette\DI;
use Ninjify\Nunjuck\TestCase\BaseMockeryTestCase;
use function file_exists;
use function md5;
use function time;

abstract class BaseTestCase extends BaseMockeryTestCase
{

	protected DI\Container $container;

	protected function setUp(): void
	{
		parent::setUp();

		$this->container = $this->createContainer();
	}

	protected function createContainer(string|null $additionalConfig = null): Nette\DI\Container
	{
		$rootDir = __DIR__ . '/../../../tests/';

		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5((string) time())]]);
		$config->addParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . '/../../common.neon');

		if ($additionalConfig && file_exists($additionalConfig)) {
			$config->addConfig($additionalConfig);
		}

		HomeKitConnector\DI\HomeKitConnectorExtension::register($config);

		return $config->createContainer();
	}

	protected function mockContainerService(
		string $serviceType,
		object $serviceMock,
	): void
	{
		$foundServiceNames = $this->container->findByType($serviceType);

		foreach ($foundServiceNames as $serviceName) {
			$this->replaceContainerService($serviceName, $serviceMock);
		}
	}

	private function replaceContainerService(string $serviceName, object $service): void
	{
		$this->container->removeService($serviceName);
		$this->container->addService($serviceName, $service);
	}

}
