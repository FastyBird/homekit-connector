<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 * @since          0.19.0
 *
 * @date           19.09.22
 */

namespace FastyBird\HomeKitConnector\Helpers;

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\HomeKitConnector;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types\DataTypeType;
use Nette;
use Ramsey\Uuid;

/**
 * Useful connector helpers
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector
{

	use Nette\SmartObject;

	/** @var DevicesModuleModels\DataStorage\IConnectorPropertiesRepository */
	private DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesRepository;

	/**
	 * @param DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesRepository
	 */
	public function __construct(
		DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesRepository
	) {
		$this->propertiesRepository = $propertiesRepository;
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param Types\ConnectorPropertyIdentifier $type
	 *
	 * @return float|bool|int|string|null
	 */
	public function getConfiguration(
		Uuid\UuidInterface $connectorId,
		Types\ConnectorPropertyIdentifier $type
	): float|bool|int|string|null {
		$configuration = $this->propertiesRepository->findByIdentifier($connectorId, strval($type->getValue()));

		if ($configuration instanceof MetadataEntities\Modules\DevicesModule\IConnectorStaticPropertyEntity) {
			if (
				$type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_PORT
				&& $configuration->getValue() === null
			) {
				return HomeKitConnector\Constants::DEFAULT_PORT;
			}

			if (
				$type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED
				&& $configuration->getValue() === null
			) {
				return false;
			}

			return $configuration->getValue();
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_PORT) {
			return HomeKitConnector\Constants::DEFAULT_PORT;
		}

		return null;
	}

	public function setConfiguration(
		Uuid\UuidInterface $connectorId,
		Types\ConnectorPropertyIdentifier $type,
		DataTypeType $dataType,
		string|int|float|bool|null $value = null
	): void {
		return;
	}

}
