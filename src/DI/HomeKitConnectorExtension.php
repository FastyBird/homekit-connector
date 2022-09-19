<?php declare(strict_types = 1);

/**
 * HomeKitConnectorExtension.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     DI
 * @since          0.1.0
 *
 * @date           29.03.22
 */

namespace FastyBird\HomeKitConnector\DI;

use Doctrine\Persistence;
use FastyBird\DevicesModule\DI as DevicesModuleDI;
use FastyBird\HomeKitConnector\Clients;
use FastyBird\HomeKitConnector\Commands;
use FastyBird\HomeKitConnector\Connector;
use FastyBird\HomeKitConnector\Controllers;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Hydrators;
use FastyBird\HomeKitConnector\Middleware;
use FastyBird\HomeKitConnector\Router;
use FastyBird\HomeKitConnector\Schemas;
use Nette;
use Nette\DI;
use Nette\Schema;
use React\EventLoop;
use stdClass;

/**
 * HomeKit connector
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class HomeKitConnectorExtension extends DI\CompilerExtension
{

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(
		Nette\Configurator $config,
		string $extensionName = 'fbHomeKitConnector'
	): void {
		$config->onCompile[] = function (
			Nette\Configurator $config,
			DI\Compiler $compiler
		) use ($extensionName): void {
			$compiler->addExtension($extensionName, new HomeKitConnectorExtension());
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function getConfigSchema(): Schema\Schema
	{
		return Schema\Expect::structure([
			'loop' => Schema\Expect::anyOf(Schema\Expect::string(), Schema\Expect::type(DI\Definitions\Statement::class))
				->nullable(),
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		/** @var stdClass $configuration */
		$configuration = $this->getConfig();

		if ($configuration->loop === null && $builder->getByType(EventLoop\LoopInterface::class) === null) {
			$builder->addDefinition($this->prefix('client.loop'), new DI\Definitions\ServiceDefinition())
				->setType(EventLoop\LoopInterface::class)
				->setFactory('React\EventLoop\Factory::create');
		}

		// Service factory
		$builder->addFactoryDefinition($this->prefix('executor.factory'))
			->setImplement(Connector\ConnectorFactory::class)
			->addTag(
				DevicesModuleDI\DevicesModuleExtension::CONNECTOR_TYPE_TAG,
				Entities\HomeKitConnector::CONNECTOR_TYPE
			)
			->getResultDefinition()
			->setType(Connector\Connector::class);

		// Clients
		$builder->addFactoryDefinition($this->prefix('client.mdns'))
			->setImplement(Clients\MdnsFactory::class)
			->getResultDefinition()
			->setType(Clients\Mdns::class);

		$builder->addFactoryDefinition($this->prefix('client.http'))
			->setImplement(Clients\HttpFactory::class)
			->getResultDefinition()
			->setType(Clients\Http::class);

		// API schemas
		$builder->addDefinition($this->prefix('schemas.connector.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\HomeKitConnector::class);

		$builder->addDefinition($this->prefix('schemas.device.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Schemas\HomeKitDevice::class);

		// API hydrators
		$builder->addDefinition($this->prefix('hydrators.connector.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\HomeKitConnector::class);

		$builder->addDefinition($this->prefix('hydrators.device.homekit'), new DI\Definitions\ServiceDefinition())
			->setType(Hydrators\HomeKitDevice::class);

		// Helpers
		$builder->addDefinition($this->prefix('helpers.database'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Database::class);

		$builder->addDefinition($this->prefix('helpers.connector'), new DI\Definitions\ServiceDefinition())
			->setType(Helpers\Connector::class);

		// HTTP server services
		$router = $builder->addDefinition($this->prefix('http.router'), new DI\Definitions\ServiceDefinition())
			->setType(Router\Router::class)
			->setAutowired(false);

		$builder->addDefinition($this->prefix('http.middleware.router'), new DI\Definitions\ServiceDefinition())
			->setType(Middleware\RouterMiddleware::class)
			->setArguments(['router' => $router]);

		$builder->addDefinition($this->prefix('http.controllers.accessories'), new DI\Definitions\ServiceDefinition())
			->setType(Controllers\AccessoriesController::class)
			->addTag('nette.inject');

		$builder->addDefinition($this->prefix('http.controllers.characteristics'), new DI\Definitions\ServiceDefinition())
			->setType(Controllers\CharacteristicsController::class)
			->addTag('nette.inject');

		$builder->addDefinition($this->prefix('http.controllers.pairing'), new DI\Definitions\ServiceDefinition())
			->setType(Controllers\PairingController::class)
			->addTag('nette.inject');

		// Console commands
		$builder->addDefinition($this->prefix('commands.initialize'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Initialize::class);

		$builder->addDefinition($this->prefix('commands.execute'), new DI\Definitions\ServiceDefinition())
			->setType(Commands\Execute::class);
	}

	/**
	 * {@inheritDoc}
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
			$ormAnnotationDriverService->addSetup('addPaths', [[__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Entities']]);
		}

		$ormAnnotationDriverChainService = $builder->getDefinitionByType(Persistence\Mapping\Driver\MappingDriverChain::class);

		if ($ormAnnotationDriverChainService instanceof DI\Definitions\ServiceDefinition) {
			$ormAnnotationDriverChainService->addSetup('addDriver', [
				$ormAnnotationDriverService,
				'FastyBird\HomeKitConnector\Entities',
			]);
		}

		/**
		 * Routes
		 */

		$routerService = $builder->getDefinitionByType(Router\Router::class);

		if ($routerService instanceof DI\Definitions\ServiceDefinition) {
			$routerService->addSetup('?->registerRoutes()', [
				$routerService,
			]);
		}
	}

}
