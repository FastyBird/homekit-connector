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

use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\HomeKitConnector;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;
use Nette\Utils;
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

	/** @var DevicesModuleModels\Connectors\Properties\IPropertiesRepository */
	private DevicesModuleModels\Connectors\Properties\IPropertiesRepository $propertiesEntitiesRepository;

	/** @var DevicesModuleModels\Connectors\Properties\IPropertiesManager */
	private DevicesModuleModels\Connectors\Properties\IPropertiesManager $propertiesEntitiesManagers;

	/** @var DevicesModuleModels\DataStorage\IConnectorPropertiesRepository */
	private DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesItemsRepository;

	/**
	 * @param DevicesModuleModels\Connectors\Properties\IPropertiesRepository $propertiesEntitiesRepository
	 * @param DevicesModuleModels\Connectors\Properties\IPropertiesManager $propertiesEntitiesManagers
	 * @param DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesItemsRepository
	 */
	public function __construct(
		DevicesModuleModels\Connectors\Properties\IPropertiesRepository $propertiesEntitiesRepository,
		DevicesModuleModels\Connectors\Properties\IPropertiesManager $propertiesEntitiesManagers,
		DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesItemsRepository
	) {
		$this->propertiesEntitiesRepository = $propertiesEntitiesRepository;
		$this->propertiesEntitiesManagers = $propertiesEntitiesManagers;
		$this->propertiesItemsRepository = $propertiesItemsRepository;
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
		$configuration = $this->propertiesItemsRepository->findByIdentifier($connectorId, strval($type->getValue()));

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

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param Types\ConnectorPropertyIdentifier $type
	 * @param string|int|float|bool|null $value
	 *
	 * @return void
	 */
	public function setConfiguration(
		Uuid\UuidInterface $connectorId,
		Types\ConnectorPropertyIdentifier $type,
		string|int|float|bool|null $value = null
	): void {
		$findConnectorProperty = new DevicesModuleQueries\FindConnectorPropertiesQuery();
		$findConnectorProperty->byConnectorId($connectorId);
		$findConnectorProperty->byIdentifier(strval($type->getValue()));

		$property = $this->propertiesEntitiesRepository->findOneBy(
			$findConnectorProperty,
			DevicesModuleEntities\Connectors\Properties\StaticProperty::class
		);

		if ($property === null) {
			throw new Exceptions\InvalidState('Connector property could not be loaded');
		}

		$this->propertiesEntitiesManagers->update($property, Utils\ArrayHash::from(['value' => $value]));
	}

}
