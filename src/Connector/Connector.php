<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Connector
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\HomeKitConnector\Connector;

use FastyBird\DevicesModule\Connectors as DevicesModuleConnectors;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Servers;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;
use Psr\Log;

/**
 * Connector service executor
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector implements DevicesModuleConnectors\IConnector
{

	use Nette\SmartObject;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Servers\Server[] */
	private array $servers = [];

	/** @var Servers\ServerFactory[] */
	private array $serversFactories;

	/** @var Entities\Protocol\AccessoryFactory */
	private Entities\Protocol\AccessoryFactory $accessoryFactory;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Servers\ServerFactory[] $serversFactories
	 * @param Entities\Protocol\AccessoryFactory $accessoryFactory
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		array $serversFactories,
		Entities\Protocol\AccessoryFactory $accessoryFactory,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;

		$this->serversFactories = $serversFactories;

		$this->accessoryFactory = $accessoryFactory;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): void
	{
		$this->logger->debug(
			'Registering bridge accessory from connector configuration',
			[
				'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type'      => 'connector',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			]
		);

		$this->accessoryFactory->create(
			$this->connector,
			null,
			Types\AccessoryCategory::get(Types\AccessoryCategory::CATEGORY_BRIDGE)
		);

		foreach ($this->serversFactories as $serverFactory) {
			$server = $serverFactory->create($this->connector);
			$server->connect();

			$this->servers[] = $server;
		}

		$this->logger->debug(
			'Connector has been started',
			[
				'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type'      => 'connector',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function terminate(): void
	{
		foreach ($this->servers as $server) {
			$server->disconnect();
		}

		$this->logger->debug(
			'Connector has been terminated',
			[
				'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type'      => 'connector',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasUnfinishedTasks(): bool
	{
		return false;
	}

}
