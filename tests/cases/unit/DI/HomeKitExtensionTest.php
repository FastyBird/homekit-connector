<?php declare(strict_types = 1);

namespace FastyBird\Connector\HomeKit\Tests\Cases\Unit\DI;

use Error;
use FastyBird\Connector\HomeKit\Clients;
use FastyBird\Connector\HomeKit\Commands;
use FastyBird\Connector\HomeKit\Connector;
use FastyBird\Connector\HomeKit\Controllers;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Hydrators;
use FastyBird\Connector\HomeKit\Middleware;
use FastyBird\Connector\HomeKit\Models;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Schemas;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Subscribers;
use FastyBird\Connector\HomeKit\Tests\Cases\Unit\BaseTestCase;
use FastyBird\Library\Bootstrap\Exceptions as BootstrapExceptions;
use Nette;

final class HomeKitExtensionTest extends BaseTestCase
{

	/**
	 * @throws BootstrapExceptions\InvalidArgument
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

		self::assertNotNull($container->getByType(Subscribers\Properties::class, false));
		self::assertNotNull($container->getByType(Subscribers\Controls::class, false));
		self::assertNotNull($container->getByType(Subscribers\System::class, false));

		self::assertNotNull($container->getByType(Schemas\HomeKitConnector::class, false));
		self::assertNotNull($container->getByType(Schemas\HomeKitDevice::class, false));
		self::assertNotNull($container->getByType(Schemas\HomeKitChannel::class, false));

		self::assertNotNull($container->getByType(Hydrators\HomeKitConnector::class, false));
		self::assertNotNull($container->getByType(Hydrators\HomeKitDevice::class, false));
		self::assertNotNull($container->getByType(Hydrators\HomeKitChannel::class, false));

		self::assertNotNull($container->getByType(Helpers\Loader::class, false));
		self::assertNotNull($container->getByType(Helpers\Connector::class, false));
		self::assertNotNull($container->getByType(Helpers\Device::class, false));
		self::assertNotNull($container->getByType(Helpers\Channel::class, false));

		self::assertNotNull($container->getByType(Middleware\Router::class, false));

		self::assertNotNull($container->getByType(Controllers\AccessoriesController::class, false));
		self::assertNotNull($container->getByType(Controllers\CharacteristicsController::class, false));
		self::assertNotNull($container->getByType(Controllers\PairingController::class, false));

		self::assertNotNull($container->getByType(Entities\Protocol\AccessoryFactory::class, false));
		self::assertNotNull($container->getByType(Entities\Protocol\ServiceFactory::class, false));
		self::assertNotNull($container->getByType(Entities\Protocol\CharacteristicsFactory::class, false));

		self::assertNotNull($container->getByType(Protocol\Tlv::class, false));
		self::assertNotNull($container->getByType(Protocol\Driver::class, false));
		self::assertNotNull($container->getByType(Clients\Subscriber::class, false));

		self::assertNotNull($container->getByType(Models\Entities\Clients\ClientsRepository::class, false));
		self::assertNotNull($container->getByType(Models\Entities\Clients\ClientsManager::class, false));

		self::assertNotNull($container->getByType(Commands\Initialize::class, false));
		self::assertNotNull($container->getByType(Commands\Execute::class, false));
		self::assertNotNull($container->getByType(Commands\Devices::class, false));

		self::assertNotNull($container->getByType(Connector\ConnectorFactory::class, false));
	}

}
