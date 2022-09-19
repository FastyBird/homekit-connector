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
use FastyBird\HomeKitConnector\Clients;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;

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

	/** @var Clients\Client[] */
	private array $clients = [];

	/** @var Clients\ClientFactory[] */
	private array $clientsFactories;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Clients\ClientFactory[] $clientsFactories
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		array $clientsFactories
	) {
		$this->connector = $connector;

		$this->clientsFactories = $clientsFactories;
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute(): void
	{
		foreach ($this->clientsFactories as $clientFactory) {
			$client = $clientFactory->create($this->connector);
			$client->connect();

			$this->clients[] = $client;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function terminate(): void
	{
		foreach ($this->clients as $client) {
			$client->disconnect();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasUnfinishedTasks(): bool
	{
		return false;
	}

}
