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

namespace FastyBird\Connector\HomeKit\Helpers;

use Doctrine\DBAL;
use Evenement;
use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Exceptions as MetadataExceptions;
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

	public function __construct(
		private readonly Database $databaseHelper,
		private readonly DevicesModuleModels\Connectors\ConnectorsRepository $connectorsEntitiesRepository,
		private readonly DevicesModuleModels\Connectors\Properties\PropertiesRepository $propertiesEntitiesRepository,
		private readonly DevicesModuleModels\Connectors\Properties\PropertiesManager $propertiesEntitiesManagers,
		private readonly DevicesModuleModels\DataStorage\ConnectorPropertiesRepository $propertiesItemsRepository,
	)
	{
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesModuleExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	public function getConfiguration(
		Uuid\UuidInterface $connectorId,
		Types\ConnectorPropertyIdentifier $type,
	): float|bool|int|string|null
	{
		$configuration = $this->propertiesItemsRepository->findByIdentifier($connectorId, strval($type->getValue()));

		if ($configuration instanceof MetadataEntities\DevicesModule\ConnectorVariableProperty) {
			if (
				$type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_PORT
				&& $configuration->getValue() === null
			) {
				return HomeKit\Constants::DEFAULT_PORT;
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
			return HomeKit\Constants::DEFAULT_PORT;
		}

		if ($type->getValue() === Types\ConnectorPropertyIdentifier::IDENTIFIER_SERVER_SECRET) {
			$serverSecret = Protocol::generateSignKey();

			$this->setConfiguration($connectorId, $type, $serverSecret);

			return $serverSecret;
		}

		return null;
	}

	/**
	 * @throws DBAL\Exception
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 */
	public function setConfiguration(
		Uuid\UuidInterface $connectorId,
		Types\ConnectorPropertyIdentifier $type,
		string|int|float|bool|null $value = null,
	): void
	{
		$property = $this->databaseHelper->query(
			function () use ($connectorId, $type): DevicesModuleEntities\Connectors\Properties\Variable|null {
				$findConnectorProperty = new DevicesModuleQueries\FindConnectorProperties();
				$findConnectorProperty->byConnectorId($connectorId);
				$findConnectorProperty->byIdentifier(strval($type->getValue()));

				$property = $this->propertiesEntitiesRepository->findOneBy(
					$findConnectorProperty,
					DevicesModuleEntities\Connectors\Properties\Variable::class,
				);
				assert(
					$property instanceof DevicesModuleEntities\Connectors\Properties\Variable || $property === null,
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
						$findConnectorQuery = new DevicesModuleQueries\FindConnectors();
						$findConnectorQuery->byId($connectorId);

						$connector = $this->connectorsEntitiesRepository->findOneBy(
							$findConnectorQuery,
							HomeKit\Entities\HomeKitConnector::class,
						);

						if ($connector === null) {
							throw new Exceptions\InvalidState(
								'Connector for storing configuration could not be loaded',
							);
						}

						$this->propertiesEntitiesManagers->create(
							Utils\ArrayHash::from([
								'entity' => DevicesModuleEntities\Connectors\Properties\Variable::class,
								'identifier' => $type->getValue(),
								'dataType' => MetadataTypes\DataType::get(
									MetadataTypes\DataType::DATA_TYPE_STRING,
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
