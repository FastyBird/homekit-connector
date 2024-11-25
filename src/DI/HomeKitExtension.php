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

use Contributte\Translation;
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
use FastyBird\Connector\HomeKit\Queue;
use FastyBird\Connector\HomeKit\Router;
use FastyBird\Connector\HomeKit\Schemas;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Subscribers;
use FastyBird\Connector\HomeKit\Writers;
use FastyBird\Core\Application\Boot as ApplicationBoot;
use FastyBird\Core\Application\DI as ApplicationDI;
use FastyBird\Core\Application\Documents as ApplicationDocuments;
use FastyBird\Core\Exchange\DI as ExchangeDI;
use FastyBird\Module\Devices\DI as DevicesDI;
use Nette\Bootstrap;
use Nette\DI;
use Nettrine\ORM as NettrineORM;
use function array_keys;
use function array_pop;
use function assert;
use const DIRECTORY_SEPARATOR;

/**
 * HomeKit connector
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class HomeKitExtension extends DI\CompilerExtension implements Translation\DI\TranslationProviderInterface
{

	public const NAME = 'fbHomeKitConnector';

	public static function register(
		ApplicationBoot\Configurator $config,
		string $extensionName = self::NAME,
	): void
	{
		$config->onCompile[] = static function (
			Bootstrap\Configurator $config,
			DI\Compiler $compiler,
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new self());
		};
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$logger = $builder->addDefinition($this->prefix('logger'), new DI\Definitions\ServiceDefinition())
			->setType(HomeKit\Logger::class)
			->setAutowired(false);

		/**
		 * WRITERS
		 */

		$builder->addFactoryDefinition($this->prefix('writers.event'))
			->setImplement(Writers\EventFactory::class)
			->getResultDefinition()
			->setType(Writers\Event::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addFactoryDefinition($this->prefix('writers.exchange'))
			->setImplement(Writers\ExchangeFactory::class)
			->getResultDefinition()
			->setType(Writers\Exchange::class)
			->setArguments([
				'logger' => $logger,
			])
			->addTag(ExchangeDI\ExchangeExtension::CONSUMER_STATE, false);

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
		 * MESSAGES QUEUE
		 */

		$builder->addDefinition(
			$this->prefix('queue.consumers.store.deviceConnectionState'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Queue\Consumers\StoreDeviceConnectionState::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addDefinition(
			$this->prefix('queue.consumers.store.devicePropertyState'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Queue\Consumers\StoreDevicePropertyState::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addDefinition(
			$this->prefix('queue.consumers.store.channelPropertyState'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Queue\Consumers\StoreChannelPropertyState::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addDefinition(
			$this->prefix('queue.consumers.write.devicePropertyState'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Queue\Consumers\WriteDevicePropertyState::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addDefinition(
			$this->prefix('queue.consumers.write.channelPropertyState'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Queue\Consumers\WriteChannelPropertyState::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addDefinition(
			$this->prefix('queue.consumers'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Queue\Consumers::class)
			->setArguments([
				'consumers' => $builder->findByType(Queue\Consumer::class),
				'logger' => $logger,
			]);

		$builder->addDefinition(
			$this->prefix('queue.queue'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Queue\Queue::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addDefinition(
			$this->prefix('queue.messageBuilder'),
			new DI\Definitions\ServiceDefinition(),
		)
			->setType(Helpers\MessageBuilder::class);

		/**
		 * SUBSCRIBERS
		 */

		$builder->addDefinition($this->prefix('subscribers.properties'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\Properties::class);

		$builder->addDefinition($this->prefix('subscribers.controls'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\Controls::class);

		$builder->addDefinition($this->prefix('subscribers.system'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\System::class);

		$builder->addDefinition($this->prefix('subscribers.entities'), new DI\Definitions\ServiceDefinition())
			->setType(Subscribers\Entities::class);

		/**
		 * JSON-API SCHEMAS
		 */

		$builder->addDefinition($this->prefix('schemas.connector.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\Connectors\Connector::class);

		$builder->addDefinition($this->prefix('schemas.device.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\Devices\Device::class);

		$builder->addDefinition($this->prefix('schemas.channel.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\Channels\Generic::class);

		$builder->addDefinition($this->prefix('schemas.channel.lightBulb'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\Channels\LightBulb::class);

		$builder->addDefinition($this->prefix('schemas.channel.battery'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\Channels\Battery::class);

		/**
		 * JSON-API HYDRATORS
		 */

		$builder->addDefinition($this->prefix('hydrators.connector.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\Connectors\Connector::class);

		$builder->addDefinition($this->prefix('hydrators.device.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\Devices\Device::class);

		$builder->addDefinition($this->prefix('hydrators.channel.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\Channels\Generic::class);

		$builder->addDefinition($this->prefix('hydrators.channel.lightBulb'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\Channels\LightBulb::class);

		$builder->addDefinition($this->prefix('hydrators.channel.battery'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\Channels\Battery::class);

		/**
		 * HELPERS
		 */

		$builder->addDefinition($this->prefix('helpers.loader'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Loader::class);

		$builder->addDefinition($this->prefix('helpers.connector'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Connector::class);

		$builder->addDefinition($this->prefix('helpers.device'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Device::class);

		$builder->addDefinition($this->prefix('helpers.channel'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Channel::class);

		/**
		 * ROUTING
		 */

		$router = $builder->addDefinition($this->prefix('http.router'), new DI\Definitions\ServiceDefinition())
			->setType(Router\Router::class)
			->setAutowired(false);

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
			->addTag('nette.inject');

		$builder->addDefinition($this->prefix('http.controllers.pairing'), new DI\Definitions\ServiceDefinition())
			->setType(Controllers\PairingController::class)
			->addSetup('setLogger', [$logger])
			->addTag('nette.inject');

		$builder->addDefinition($this->prefix('http.controllers.diagnostics'), new DI\Definitions\ServiceDefinition())
			->setType(Controllers\DiagnosticsController::class)
			->addSetup('setLogger', [$logger])
			->addTag('nette.inject');

		/**
		 * HOMEKIT
		 */

		// ACCESSORIES
		$builder->addDefinition($this->prefix('protocol.accessory.factory.bridge'))
			->setType(Protocol\Accessories\BridgeFactory::class);

		$builder->addDefinition($this->prefix('protocol.accessory.factory.generic'))
			->setType(Protocol\Accessories\GenericFactory::class);

		// SERVICES
		$builder->addDefinition($this->prefix('protocol.service.factory.generic'))
			->setType(Protocol\Services\GenericFactory::class);

		$builder->addDefinition($this->prefix('protocol.service.factory.lightBulb'))
			->setType(Protocol\Services\LightBulbFactory::class);

		$builder->addDefinition($this->prefix('protocol.service.factory.battery'))
			->setType(Protocol\Services\BatteryFactory::class);

		// CHARACTERISTICS
		$builder->addDefinition($this->prefix('protocol.characteristic.factory.generic'))
			->setType(Protocol\Characteristics\GenericFactory::class);

		$builder->addDefinition($this->prefix('protocol.characteristic.factory.dynamicProperty'))
			->setType(Protocol\Characteristics\DynamicPropertyFactory::class);

		$builder->addDefinition($this->prefix('protocol.characteristic.factory.mappedProperty'))
			->setType(Protocol\Characteristics\MappedPropertyFactory::class);

		$builder->addDefinition($this->prefix('protocol.characteristic.factory.variableProperty'))
			->setType(Protocol\Characteristics\VariablePropertyFactory::class);

		$builder->addDefinition($this->prefix('protocol.tlv'), new DI\Definitions\ServiceDefinition())
			->setType(Protocol\Tlv::class);

		$builder->addDefinition($this->prefix('protocol.accessoryLoader'))
			->setType(Protocol\Loader::class)
			->setArguments([
				'logger' => $logger,
			]);

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
			->setType(Models\Entities\Clients\ClientsManager::class);

		/**
		 * COMMANDS
		 */

		$builder->addDefinition($this->prefix('commands.execute'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Execute::class)
			->setArguments([
				'logger' => $logger,
			]);

		$builder->addDefinition($this->prefix('commands.install'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Install::class)
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
				Entities\Connectors\Connector::TYPE,
			)
			->getResultDefinition()
			->setType(Connector\Connector::class)
			->setArguments([
				'writersFactories' => $builder->findByType(Writers\WriterFactory::class),
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
		 * DOCTRINE ENTITIES
		 */

		$services = $builder->findByTag(NettrineORM\DI\OrmAttributesExtension::DRIVER_TAG);

		if ($services !== []) {
			$services = array_keys($services);
			$ormAttributeDriverServiceName = array_pop($services);

			$ormAttributeDriverService = $builder->getDefinition($ormAttributeDriverServiceName);

			if ($ormAttributeDriverService instanceof DI\Definitions\ServiceDefinition) {
				$ormAttributeDriverService->addSetup(
					'addPaths',
					[[__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Entities']],
				);

				$ormAttributeDriverChainService = $builder->getDefinitionByType(
					Persistence\Mapping\Driver\MappingDriverChain::class,
				);

				if ($ormAttributeDriverChainService instanceof DI\Definitions\ServiceDefinition) {
					$ormAttributeDriverChainService->addSetup('addDriver', [
						$ormAttributeDriverService,
						'FastyBird\Connector\HomeKit\Entities',
					]);
				}
			}
		}

		/**
		 * APPLICATION DOCUMENTS
		 */

		$services = $builder->findByTag(ApplicationDI\ApplicationExtension::DRIVER_TAG);

		if ($services !== []) {
			$services = array_keys($services);
			$documentAttributeDriverServiceName = array_pop($services);

			$documentAttributeDriverService = $builder->getDefinition($documentAttributeDriverServiceName);

			if ($documentAttributeDriverService instanceof DI\Definitions\ServiceDefinition) {
				$documentAttributeDriverService->addSetup(
					'addPaths',
					[[__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Documents']],
				);

				$documentAttributeDriverChainService = $builder->getDefinitionByType(
					ApplicationDocuments\Mapping\Driver\MappingDriverChain::class,
				);

				if ($documentAttributeDriverChainService instanceof DI\Definitions\ServiceDefinition) {
					$documentAttributeDriverChainService->addSetup('addDriver', [
						$documentAttributeDriverService,
						'FastyBird\Connector\HomeKit\Documents',
					]);
				}
			}
		}

		/**
		 * HOMEKIT
		 */

		$protocolLoaderServiceName = $builder->getByType(Protocol\Loader::class);

		if ($protocolLoaderServiceName !== null) {
			$protocolLoaderService = $builder->getDefinition($protocolLoaderServiceName);
			assert($protocolLoaderService instanceof DI\Definitions\ServiceDefinition);

			$accessoriesFactories = $builder->findByType(
				Protocol\Accessories\AccessoryFactory::class,
			);
			$servicesFactories = $builder->findByType(
				Protocol\Services\ServiceFactory::class,
			);
			$characteristicsFactories = $builder->findByType(
				Protocol\Characteristics\CharacteristicFactory::class,
			);

			$protocolLoaderService->setArgument('accessoryFactories', $accessoriesFactories);
			$protocolLoaderService->setArgument('serviceFactories', $servicesFactories);
			$protocolLoaderService->setArgument('characteristicsFactories', $characteristicsFactories);
		}
	}

	/**
	 * @return array<string>
	 */
	public function getTranslationResources(): array
	{
		return [
			__DIR__ . '/../Translations/',
		];
	}

}
