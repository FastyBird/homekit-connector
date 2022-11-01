<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Connector
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\Connector\HomeKit\Connector;

use FastyBird\Connector\HomeKit\Consumers;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Library\Exchange\Consumers as ExchangeConsumers;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Connectors as DevicesConnectors;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use Nette;
use Psr\Log;
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

	/** @var Array<Servers\Server> */
	private array $servers = [];

	private Log\LoggerInterface $logger;

	/**
	 * @param Array<Servers\ServerFactory> $serversFactories
	 */
	public function __construct(
		private readonly DevicesEntities\Connectors\Connector $connector,
		private readonly array $serversFactories,
		private readonly ExchangeConsumers\Container $consumer,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	public function execute(): void
	{
		assert($this->connector instanceof Entities\HomeKitConnector);

		$this->consumer->enable(Consumers\Consumer::class);

		$this->logger->debug(
			'Registering bridge accessory from connector configuration',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'connector',
				'connector' => [
					'id' => $this->connector->getPlainId(),
				],
			],
		);

		foreach ($this->serversFactories as $serverFactory) {
			$server = $serverFactory->create($this->connector);
			$server->connect();

			$this->servers[] = $server;
		}

		$this->logger->debug(
			'Connector has been started',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'connector',
				'connector' => [
					'id' => $this->connector->getPlainId(),
				],
			],
		);
	}

	public function terminate(): void
	{
		foreach ($this->servers as $server) {
			$server->disconnect();
		}

		$this->consumer->disable(Consumers\Consumer::class);

		$this->logger->debug(
			'Connector has been terminated',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'connector',
				'connector' => [
					'id' => $this->connector->getPlainId(),
				],
			],
		);
	}

	public function hasUnfinishedTasks(): bool
	{
		return false;
	}

}
