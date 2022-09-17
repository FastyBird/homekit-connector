<?php declare(strict_types = 1);

/**
 * ConnectorFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     common
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\HomeKitConnector;

use FastyBird\DevicesModule\Connectors as DevicesModuleConnectors;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;

/**
 * Connector service container factory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ConnectorFactory implements DevicesModuleConnectors\IConnectorFactory
{

	use Nette\SmartObject;

	/** @var Connector\ConnectorFactory */
	private Connector\ConnectorFactory $connectorFactory;

	/**
	 * @param Connector\ConnectorFactory $connectorFactory
	 */
	public function __construct(
		Connector\ConnectorFactory $connectorFactory
	) {
		$this->connectorFactory = $connectorFactory;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getType(): string
	{
		return Entities\HomeKitConnector::CONNECTOR_TYPE;
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 *
	 * @return DevicesModuleConnectors\IConnector
	 */
	public function create(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	): DevicesModuleConnectors\IConnector {
		return $this->connectorFactory->create($connector);
	}

}
