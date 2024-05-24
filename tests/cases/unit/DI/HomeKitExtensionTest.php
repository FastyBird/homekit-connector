<?php declare(strict_types = 1);

namespace FastyBird\Connector\HomeKit\Tests\Cases\Unit\DI;

use Error;
use FastyBird\Connector\HomeKit\Clients;
use FastyBird\Connector\HomeKit\Commands;
use FastyBird\Connector\HomeKit\Connector;
use FastyBird\Connector\HomeKit\Controllers;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Hydrators;
use FastyBird\Connector\HomeKit\Middleware;
use FastyBird\Connector\HomeKit\Models;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Queue;
use FastyBird\Connector\HomeKit\Schemas;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Subscribers;
use FastyBird\Connector\HomeKit\Tests;
use FastyBird\Library\Application\Exceptions as ApplicationExceptions;
use Nette;
use function in_array;

final class HomeKitExtensionTest extends Tests\Cases\Unit\BaseTestCase
{

	/**
	 * @throws ApplicationExceptions\InvalidArgument
	 * @throws ApplicationExceptions\InvalidState
	 * @throws Nette\DI\MissingServiceException
	 * @throws Error
	 */
	public function testServicesRegistration(): void
	{
		$container = $this->createContainer();

		self::assertNotNull($container->getByType(Servers\MdnsFactory::class, false));
		self::assertNotNull($container->getByType(Servers\HttpFactory::class, false));
		self::assertNotNull($container->getByType(Servers\SecureServerFactory::class, false));
		self::assertNotNull($container->getByType(Servers\SecureConnectionFactory::class, false));

		self::assertNotNull($container->getByType(Queue\Consumers\StoreDeviceConnectionState::class, false));
		self::assertNotNull($container->getByType(Queue\Consumers\StoreDevicePropertyState::class, false));
		self::assertNotNull($container->getByType(Queue\Consumers\StoreChannelPropertyState::class, false));
		self::assertNotNull($container->getByType(Queue\Consumers\WriteDevicePropertyState::class, false));
		self::assertNotNull($container->getByType(Queue\Consumers\WriteChannelPropertyState::class, false));
		self::assertNotNull($container->getByType(Queue\Consumers::class, false));
		self::assertNotNull($container->getByType(Queue\Queue::class, false));

		self::assertNotNull($container->getByType(Subscribers\Properties::class, false));
		self::assertNotNull($container->getByType(Subscribers\Controls::class, false));
		self::assertNotNull($container->getByType(Subscribers\System::class, false));

		self::assertNotNull($container->getByType(Schemas\Connectors\Connector::class, false));
		self::assertNotNull($container->getByType(Schemas\Devices\Device::class, false));
		foreach ($container->findByType(Schemas\Channels\Channel::class) as $serviceName) {
			$service = $container->getByName($serviceName);

			self::assertTrue(in_array(
				$service::class,
				[
					Schemas\Channels\Generic::class,
					Schemas\Channels\Battery::class,
					Schemas\Channels\LightBulb::class,
				],
				true,
			));
		}

		self::assertNotNull($container->getByType(Hydrators\Connectors\Connector::class, false));
		self::assertNotNull($container->getByType(Hydrators\Devices\Device::class, false));
		foreach ($container->findByType(Hydrators\Channels\Channel::class) as $serviceName) {
			$service = $container->getByName($serviceName);

			self::assertTrue(in_array(
				$service::class,
				[
					Hydrators\Channels\Generic::class,
					Hydrators\Channels\Battery::class,
					Hydrators\Channels\LightBulb::class,
				],
				true,
			));
		}

		self::assertNotNull($container->getByType(Helpers\MessageBuilder::class, false));
		self::assertNotNull($container->getByType(Helpers\Loader::class, false));
		self::assertNotNull($container->getByType(Helpers\Connector::class, false));
		self::assertNotNull($container->getByType(Helpers\Device::class, false));
		self::assertNotNull($container->getByType(Helpers\Channel::class, false));

		self::assertNotNull($container->getByType(Middleware\Router::class, false));

		self::assertNotNull($container->getByType(Controllers\AccessoriesController::class, false));
		self::assertNotNull($container->getByType(Controllers\CharacteristicsController::class, false));
		self::assertNotNull($container->getByType(Controllers\PairingController::class, false));

		self::assertNotNull($container->getByType(Protocol\Accessories\BridgeFactory::class, false));
		self::assertNotNull($container->getByType(Protocol\Accessories\GenericFactory::class, false));
		self::assertNotNull($container->getByType(Protocol\Services\GenericFactory::class, false));
		self::assertNotNull($container->getByType(Protocol\Services\LightBulbFactory::class, false));
		self::assertNotNull($container->getByType(Protocol\Services\BatteryFactory::class, false));
		self::assertNotNull(
			$container->getByType(Protocol\Characteristics\DynamicPropertyFactory::class, false),
		);
		self::assertNotNull(
			$container->getByType(Protocol\Characteristics\MappedPropertyFactory::class, false),
		);
		self::assertNotNull(
			$container->getByType(Protocol\Characteristics\VariablePropertyFactory::class, false),
		);

		self::assertNotNull($container->getByType(Protocol\Tlv::class, false));
		self::assertNotNull($container->getByType(Protocol\Driver::class, false));
		self::assertNotNull($container->getByType(Clients\Subscriber::class, false));

		self::assertNotNull($container->getByType(Models\Entities\Clients\ClientsRepository::class, false));
		self::assertNotNull($container->getByType(Models\Entities\Clients\ClientsManager::class, false));

		self::assertNotNull($container->getByType(Commands\Execute::class, false));
		self::assertNotNull($container->getByType(Commands\Install::class, false));

		self::assertNotNull($container->getByType(Connector\ConnectorFactory::class, false));
	}

}
