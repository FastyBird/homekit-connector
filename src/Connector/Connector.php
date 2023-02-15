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

use Doctrine\Common;
use Doctrine\ORM;
use Doctrine\Persistence;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Connector\HomeKit\Writers;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Connectors as DevicesConnectors;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use Nette;
use Psr\Log;
use function assert;
use function hex2bin;
use function is_string;

/**
 * Connector service executor
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector implements DevicesConnectors\Connector, Common\EventSubscriber
{

	use Nette\SmartObject;

	/** @var array<Servers\Server> */
	private array $servers = [];

	private Log\LoggerInterface $logger;

	/**
	 * @param array<Servers\ServerFactory> $serversFactories
	 */
	public function __construct(
		private readonly DevicesEntities\Connectors\Connector $connector,
		private readonly Writers\Writer $writer,
		private readonly array $serversFactories,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	public function getSubscribedEvents(): array
	{
		return [
			ORM\Events::postPersist,
			ORM\Events::postUpdate,
		];
	}

	public function execute(): void
	{
		assert($this->connector instanceof Entities\HomeKitConnector);

		$this->logger->debug(
			'Registering bridge accessory from connector configuration',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'connector',
				'group' => 'connector',
				'connector' => [
					'id' => $this->connector->getPlainId(),
				],
			],
		);

		$this->writer->connect($this->connector);

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
				'group' => 'connector',
				'connector' => [
					'id' => $this->connector->getPlainId(),
				],
			],
		);
	}

	public function terminate(): void
	{
		assert($this->connector instanceof Entities\HomeKitConnector);

		$this->writer->disconnect($this->connector);

		foreach ($this->servers as $server) {
			$server->disconnect();
		}

		$this->logger->debug(
			'Connector has been terminated',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'connector',
				'group' => 'connector',
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

	/**
	 * @param Persistence\Event\LifecycleEventArgs<ORM\EntityManagerInterface> $eventArgs
	 *
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function postPersist(Persistence\Event\LifecycleEventArgs $eventArgs): void
	{
		// onFlush was executed before, everything already initialized
		$entity = $eventArgs->getObject();

		// Check for valid entity
		if (
			!$entity instanceof DevicesEntities\Connectors\Properties\Variable
			|| !$entity->getConnector()->getId()->equals($this->connector->getId())
		) {
			return;
		}

		$this->processConfigurationUpdate($entity);
	}

	/**
	 * @param Persistence\Event\LifecycleEventArgs<ORM\EntityManagerInterface> $eventArgs
	 *
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function postUpdate(Persistence\Event\LifecycleEventArgs $eventArgs): void
	{
		// onFlush was executed before, everything already initialized
		$entity = $eventArgs->getObject();

		// Check for valid entity
		if (
			!$entity instanceof DevicesEntities\Connectors\Properties\Variable
			|| !$entity->getConnector()->getId()->equals($this->connector->getId())
		) {
			return;
		}

		$this->processConfigurationUpdate($entity);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processConfigurationUpdate(DevicesEntities\Connectors\Properties\Variable $property): void
	{
		if (
			$property->getIdentifier() === Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED
			|| $property->getIdentifier() === Types\ConnectorPropertyIdentifier::IDENTIFIER_CONFIG_VERSION
		) {
			foreach ($this->servers as $server) {
				if ($server instanceof Servers\Mdns) {
					$server->refresh();
				}
			}
		}

		if ($property->getIdentifier() === Types\ConnectorPropertyIdentifier::IDENTIFIER_SHARED_KEY) {
			foreach ($this->servers as $server) {
				if ($server instanceof Servers\Http) {
					$server->setSharedKey(
						is_string($property->getValue()) ? (string) hex2bin($property->getValue()) : null,
					);
				}
			}
		}
	}

}
