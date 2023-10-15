<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Connector
 * @since          1.0.0
 *
 * @date           17.09.22
 */

namespace FastyBird\Connector\HomeKit\Connector;

use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Writers;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Connectors as DevicesConnectors;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use Nette;
use function assert;

/**
 * Connector service executor
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector implements DevicesConnectors\Connector
{

	use Nette\SmartObject;

	/** @var array<Servers\Server> */
	private array $servers = [];

	private Writers\Writer|null $writer = null;

	/**
	 * @param array<Servers\ServerFactory> $serversFactories
	 */
	public function __construct(
		private readonly DevicesEntities\Connectors\Connector $connector,
		private readonly Writers\WriterFactory $writerFactory,
		private readonly array $serversFactories,
		private readonly HomeKit\Logger $logger,
	)
	{
	}

	public function execute(): void
	{
		assert($this->connector instanceof Entities\HomeKitConnector);

		$this->logger->info(
			'Starting HomeKit connector service',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'connector',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);

		foreach ($this->serversFactories as $serverFactory) {
			$server = $serverFactory->create($this->connector);
			$server->connect();

			$this->servers[] = $server;
		}

		$this->writer = $this->writerFactory->create($this->connector);
		$this->writer->connect();

		$this->logger->info(
			'HomeKit connector service has been started',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'connector',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);
	}

	public function discover(): void
	{
		assert($this->connector instanceof Entities\HomeKitConnector);

		$this->logger->error(
			'Devices discovery is not allowed for HomeKit connector type',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'connector',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);
	}

	public function terminate(): void
	{
		assert($this->connector instanceof Entities\HomeKitConnector);

		$this->writer?->disconnect();

		foreach ($this->servers as $server) {
			$server->disconnect();
		}

		$this->logger->info(
			'HomeKit connector has been terminated',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'connector',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);
	}

	public function hasUnfinishedTasks(): bool
	{
		return false;
	}

}
