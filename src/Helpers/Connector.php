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

use Doctrine\DBAL;
use Evenement;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\HomeKitConnector;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types as MetadataTypes;
use Nette;
use Nette\Utils;
use Ramsey\Uuid;
use function assert;
use function strval;

/**
 * Useful connector helpers
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector extends Evenement\EventEmitter
{

	use Nette\SmartObject;

	/**
	 * @param Database $databaseHelper
	 * @param DevicesModuleModels\Connectors\ConnectorsRepository $connectorsEntitiesRepository
	 * @param DevicesModuleModels\Connectors\Properties\IPropertiesRepository $propertiesEntitiesRepository
	 * @param DevicesModuleModels\Connectors\Properties\IPropertiesManager $propertiesEntitiesManagers
	 * @param DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesItemsRepository
	 */
	public function __construct(
		private Database $databaseHelper,
		private DevicesModuleModels\Connectors\ConnectorsRepository $connectorsEntitiesRepository,
		private DevicesModuleModels\Connectors\Properties\IPropertiesRepository $propertiesEntitiesRepository,
		private DevicesModuleModels\Connectors\Properties\IPropertiesManager $propertiesEntitiesManagers,
		private DevicesModuleModels\DataStorage\IConnectorPropertiesRepository $propertiesItemsRepository,
	) {
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param Types\ConnectorPropertyIdentifier $type
	 *
	 * @return float|bool|int|string|null
	 *
	 * @throws DBAL\Exception
	 */
	public function getConfiguration(
		Uuid\UuidInterface $connectorId,
		Types\ConnectorPropertyIdentifier $type,
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

			if (
				$type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_SERVER_SECRET
				&& ($configuration->getValue() === null || $configuration->getValue() === '')
			) {
				$serverSecret = Protocol::generateSignKey();

				$this->setConfiguration($connectorId, $type, $serverSecret);

				return $serverSecret;
			}

			return $configuration->getValue();
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_PORT) {
			return HomeKitConnector\Constants::DEFAULT_PORT;
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_SERVER_SECRET) {
			$serverSecret = Protocol::generateSignKey();

			$this->setConfiguration($connectorId, $type, $serverSecret);

			return $serverSecret;
		}

		return null;
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param Types\ConnectorPropertyIdentifier $type
	 * @param string|int|float|bool|null $value
	 *
	 * @return void
	 *
	 * @throws DBAL\Exception
	 */
	public function setConfiguration(
		Uuid\UuidInterface $connectorId,
		Types\ConnectorPropertyIdentifier $type,
		string|int|float|bool|null $value = null,
	): void {
		$property = $this->databaseHelper->query(
			function () use ($connectorId, $type): DevicesModuleEntities\Connectors\Properties\StaticProperty|null {
				$findConnectorProperty = new DevicesModuleQueries\FindConnectorPropertiesQuery();
				$findConnectorProperty->byConnectorId($connectorId);
				$findConnectorProperty->byIdentifier(strval($type->getValue()));

				$property = $this->propertiesEntitiesRepository->findOneBy(
					$findConnectorProperty,
					DevicesModuleEntities\Connectors\Properties\StaticProperty::class,
				);
				assert(
					$property instanceof DevicesModuleEntities\Connectors\Properties\StaticProperty || $property === null,
				);

				return $property;
			},
		);

		if ($property === null) {
			if (
				$type->equalsValue(Types\ConnectorPropertyIdentifier::IDENTIFIER_SERVER_SECRET)
				|| $type->equalsValue(Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_PUBLIC_KEY)
				|| $type->equalsValue(Types\ConnectorPropertyIdentifier::IDENTIFIER_SHARED_KEY)
				|| $type->equalsValue(Types\ConnectorPropertyIdentifier::IDENTIFIER_HASHING_KEY)
			) {
				$this->databaseHelper->transaction(
					function () use ($connectorId, $type, $value): void {
						$findConnectorQuery = new DevicesModuleQueries\FindConnectorsQuery();
						$findConnectorQuery->byId($connectorId);

						$connector = $this->connectorsEntitiesRepository->findOneBy(
							$findConnectorQuery,
							HomeKitConnector\Entities\HomeKitConnector::class,
						);

						if ($connector === null) {
							throw new Exceptions\InvalidState(
								'Connector for storing configuration could not be loaded',
							);
						}

						$this->propertiesEntitiesManagers->create(
							Utils\ArrayHash::from([
								'entity' => DevicesModuleEntities\Connectors\Properties\StaticProperty::class,
								'identifier' => $type->getValue(),
								'dataType' => MetadataTypes\DataTypeType::get(
									MetadataTypes\DataTypeType::DATA_TYPE_STRING,
								),
								'value' => $value,
								'connector' => $connector,
							]),
						);

						$configuration = $this->propertiesItemsRepository->findByIdentifier(
							$connectorId,
							strval($type->getValue()),
						);

						$this->emit('created', [$connectorId, $type, $configuration]);
					},
				);
			} else {
				throw new Exceptions\InvalidState('Connector property could not be loaded');
			}
		} else {
			$this->databaseHelper->transaction(
				function () use ($connectorId, $property, $type, $value): void {
					$this->propertiesEntitiesManagers->update(
						$property,
						Utils\ArrayHash::from(['value' => $value]),
					);

					$configuration = $this->propertiesItemsRepository->findByIdentifier(
						$connectorId,
						strval($type->getValue()),
					);

					$this->emit('updated', [$connectorId, $type, $configuration]);
				},
			);
		}
	}

}
