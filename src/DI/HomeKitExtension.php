<?php declare(strict_types = 1);

/**
 * HomeKitExtension.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     DI
 * @since          1.0.0
 *
 * @date           29.03.22
 */

namespace FastyBird\Connector\HomeKit\DI;

use Doctrine\Persistence;
use FastyBird\Connector\HomeKit;
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
use FastyBird\Connector\HomeKit\Router;
use FastyBird\Connector\HomeKit\Schemas;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Subscribers;
use FastyBird\Connector\HomeKit\Writers;
use FastyBird\Library\Bootstrap\Boot as BootstrapBoot;
use FastyBird\Library\Exchange\DI as ExchangeDI;
use FastyBird\Module\Devices\DI as DevicesDI;
use IPub\DoctrineCrud;
use Nette\DI;
use Nette\PhpGenerator;
use Nette\Schema;
use stdClass;
use function assert;
use function ucfirst;
use const DIRECTORY_SEPARATOR;

/**
 * HomeKit connector
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class HomeKitExtension extends DI\CompilerExtension
{

	public const NAME = 'fbHomeKitConnector';

	public static function register(
		BootstrapBoot\Configurator $config,
		string $extensionName = self::NAME,
	): void
	{
		$config->onCompile[] = static function (
			BootstrapBoot\Configurator $config,
			DI\Compiler $compiler,
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new self());
		};
	}

	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::structure([
			'writer' => Schema\Expect::anyOf(
				Writers\Event::NAME,
				Writers\Exchange::NAME,
				Writers\Periodic::NAME,
			)->default(
				Writers\Periodic::NAME,
			),
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$configuration = $this->getConfig();
		assert($configuration instanceof stdClass);

		$logger = $builder->addDefinition($this->prefix('logger'), new DI\Definitions\ServiceDefinition())
			->setType(HomeKit\Logger::class)
			->setAutowired(false);

		/**
		 * WRITERS
		 */

		if ($configuration->writer === Writers\Event::NAME) {
			$builder->addFactoryDefinition($this->prefix('writers.event'))
				->setImplement(Writers\EventFactory::class)
				->getResultDefinition()
				->setType(Writers\Event::class)
				->setArguments([
					'logger' => $logger,
				]);
		} elseif ($configuration->writer === Writers\Exchange::NAME) {
			$builder->addFactoryDefinition($this->prefix('writers.exchange'))
				->setImplement(Writers\ExchangeFactory::class)
				->getResultDefinition()
				->setType(Writers\Exchange::class)
				->addTag(ExchangeDI\ExchangeExtension::CONSUMER_STATE, false)
				->setArguments([
					'logger' => $logger,
				]);
		} elseif ($configuration->writer === Writers\Periodic::NAME) {
			$builder->addFactoryDefinition($this->prefix('writers.periodic'))
				->setImplement(Writers\PeriodicFactory::class)
				->getResultDefinition()
				->setType(Writers\Periodic::class)
				->setArguments([
					'logger' => $logger,
				]);
		}

		/**
		 * SERVERS
		 */

		$builder->addFactoryDefinition($this->prefix('server.mdns'))
			->setImplement(Servers\MdnsFactory::class)
			->getResultDefinition()
			->setType(Servers\Mdns::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addFactoryDefinition($this->prefix('server.http'))
			->setImplement(Servers\HttpFactory::class)
			->getResultDefinition()
			->setType(Servers\Http::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addFactoryDefinition($this->prefix('server.http.secure.server'))
			->setImplement(Servers\SecureServerFactory::class)
			->getResultDefinition()
			->setType(Servers\SecureServer::class);

		$builder->addFactoryDefinition($this->prefix('server.http.secure.connection'))
			->setImplement(Servers\SecureConnectionFactory::class)
			->getResultDefinition()
			->setType(Servers\SecureConnection::class)
			->setArguments([
				'logger' => $logger,
			]);

		/**
		 * SUBSCRIBERS
		 */

		$builder->addDefinition($this->prefix('subscribers.properties'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\Properties::class);

		$builder->addDefinition($this->prefix('subscribers.controls'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\Controls::class);

		$builder->addDefinition($this->prefix('subscribers.system'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\System::class);

		/**
		 * JSON-API SCHEMAS
		 */

		$builder->addDefinition($this->prefix('schemas.connector.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\HomeKitConnector::class);

		$builder->addDefinition($this->prefix('schemas.device.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\HomeKitDevice::class);

		$builder->addDefinition($this->prefix('schemas.channel.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\HomeKitChannel::class);

		/**
		 * JSON-API HYDRATORS
		 */

		$builder->addDefinition($this->prefix('hydrators.connector.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\HomeKitConnector::class);

		$builder->addDefinition($this->prefix('hydrators.device.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\HomeKitDevice::class);

		$builder->addDefinition($this->prefix('hydrators.channel.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\HomeKitChannel::class);

		/**
		 * HELPERS
		 */

		$builder->addDefinition($this->prefix('helpers.loader'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Loader::class);

		$router = $builder->addDefinition($this->prefix('http.router'), new DI\Definitions\ServiceDefinition())
			->setType(Router\Router::class)
			->setAutowired(false);

		/**
		 * ROUTING
		 */

		$builder->addDefinition($this->prefix('http.middlewares.router'), new DI\Definitions\ServiceDefinition())
			->setType(Middleware\Router::class)
			->setArguments([
				'router' => $router,
				'logger' => $logger,
			]);

		/**
		 * CONTROLLERS
		 */

		$builder->addDefinition($this->prefix('http.controllers.accessories'), new DI\Definitions\ServiceDefinition())
			->setType(Controllers\AccessoriesController::class)
			->addSetup('setLogger', [$logger])
			->addTag('nette.inject');

		$builder->addDefinition(
			$this->prefix('http.controllers.characteristics'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Controllers\CharacteristicsController::class)
			->addSetup('setLogger', [$logger])
			->setArguments([
				'useExchange' => $configuration->writer === Writers\Exchange::NAME,
			])
			->addTag('nette.inject');

		$builder->addDefinition($this->prefix('http.controllers.pairing'), new DI\Definitions\ServiceDefinition())
			->setType(Controllers\PairingController::class)
			->addSetup('setLogger', [$logger])
			->addTag('nette.inject');

		/**
		 * ENTITIES
		 */

		$builder->addDefinition($this->prefix('entities.accessory.factory'))
			->setType(Entities\Protocol\AccessoryFactory::class);

		$builder->addDefinition($this->prefix('entities.service.factory'))
			->setType(Entities\Protocol\ServiceFactory::class);

		$builder->addDefinition($this->prefix('entities.characteristic.factory'))
			->setType(Entities\Protocol\CharacteristicsFactory::class);

		/**
		 * HOMEKIT
		 */

		$builder->addDefinition($this->prefix('protocol.tlv'), new DI\Definitions\ServiceDefinition())
			->setType(Protocol\Tlv::class);

		$builder->addDefinition($this->prefix('protocol.accessoryDriver'))
			->setType(Protocol\Driver::class);

		$builder->addDefinition($this->prefix('clients.subscriber'))
			->setType(Clients\Subscriber::class)
			->setArguments([
				'logger' => $logger,
			]);

		/**
		 * MODELS
		 */

		$builder->addDefinition($this->prefix('models.clientsRepository'), new DI\Definitions\ServiceDefinition())
			->setType(Models\Entities\Clients\ClientsRepository::class);

		$builder->addDefinition($this->prefix('models.clientsManager'), new DI\Definitions\ServiceDefinition())
			->setType(Models\Entities\Clients\ClientsManager::class)
			->setArgument('entityCrud', '__placeholder__');

		/**
		 * COMMANDS
		 */

		$builder->addDefinition($this->prefix('commands.initialize'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Initialize::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addDefinition($this->prefix('commands.execute'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Execute::class);

		$builder->addDefinition($this->prefix('commands.devices'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Devices::class)
			->setArguments([
				'logger' => $logger,
			]);

		/**
		 * CONNECTOR
		 */

		$builder->addFactoryDefinition($this->prefix('executor.factory'))
			->setImplement(Connector\ConnectorFactory::class)
			->addTag(
				DevicesDI\DevicesExtension::CONNECTOR_TYPE_TAG,
				Entities\HomeKitConnector::CONNECTOR_TYPE,
			)
			->getResultDefinition()
			->setType(Connector\Connector::class)
			->setArguments([
				'serversFactories' => $builder->findByType(Servers\ServerFactory::class),
				'logger' => $logger,
			]);
	}

	/**
	 * @throws DI\MissingServiceException
	 */
	public function beforeCompile(): void
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		/**
		 * Doctrine entities
		 */

		$ormAnnotationDriverService = $builder->getDefinition('nettrineOrmAnnotations.annotationDriver');

		if ($ormAnnotationDriverService instanceof DI\Definitions\ServiceDefinition) {
			$ormAnnotationDriverService->addSetup(
				'addPaths',
				[[__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Entities']],
			);
		}

		$ormAnnotationDriverChainService = $builder->getDefinitionByType(
			Persistence\Mapping\Driver\MappingDriverChain::class,
		);

		if ($ormAnnotationDriverChainService instanceof DI\Definitions\ServiceDefinition) {
			$ormAnnotationDriverChainService->addSetup('addDriver', [
				$ormAnnotationDriverService,
				'FastyBird\Connector\HomeKit\Entities',
			]);
		}
	}

	/**
	 * @throws DI\MissingServiceException
	 */
	public function afterCompile(PhpGenerator\ClassType $class): void
	{
		$builder = $this->getContainerBuilder();

		$entityFactoryServiceName = $builder->getByType(DoctrineCrud\Crud\IEntityCrudFactory::class, true);

		$devicesManagerService = $class->getMethod('createService' . ucfirst($this->name) . '__models__clientsManager');
		$devicesManagerService->setBody(
			'return new ' . Models\Entities\Clients\ClientsManager::class
			. '($this->getService(\'' . $entityFactoryServiceName . '\')->create(\'' . Entities\Client::class . '\'));',
		);
	}

}
