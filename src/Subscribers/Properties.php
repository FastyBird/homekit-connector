<?php declare(strict_types = 1);

/**
 * Properties.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Subscribers
 * @since          1.0.0
 *
 * @date           12.02.23
 */

namespace FastyBird\Connector\HomeKit\Subscribers;

use Doctrine\Common;
use Doctrine\DBAL;
use Doctrine\ORM;
use Doctrine\Persistence;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Core\Tools\Exceptions as ToolsExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Types as DevicesTypes;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use IPub\DoctrineCrud\Exceptions as DoctrineCrudExceptions;
use Nette;
use Nette\Utils;
use TypeError;
use ValueError;
use function assert;

/**
 * Doctrine entities events
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Subscribers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Properties implements Common\EventSubscriber
{

	use Nette\SmartObject;

	public function __construct(
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesRepository $connectorsPropertiesRepository,
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesManager $connectorsPropertiesManager,
		private readonly DevicesModels\Entities\Devices\Properties\PropertiesRepository $devicesPropertiesRepository,
		private readonly DevicesModels\Entities\Devices\Properties\PropertiesManager $devicesPropertiesManager,
	)
	{
	}

	public function getSubscribedEvents(): array
	{
		return [
			ORM\Events::postPersist,
			ORM\Events::postUpdate,
		];
	}

	/**
	 * @param Persistence\Event\LifecycleEventArgs<ORM\EntityManagerInterface> $eventArgs
	 *
	 * @throws DBAL\Exception\UniqueConstraintViolationException
	 * @throws DoctrineCrudExceptions\EntityCreation
	 * @throws DoctrineCrudExceptions\InvalidArgument
	 * @throws DoctrineCrudExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function postPersist(Persistence\Event\LifecycleEventArgs $eventArgs): void
	{
		$entity = $eventArgs->getObject();

		// Check for valid entity
		if ($entity instanceof Entities\Connectors\Connector) {
			$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::MAC_ADDRESS);

			$macAddressProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($macAddressProperty === null) {
				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::MAC_ADDRESS->value,
					'dataType' => MetadataTypes\DataType::STRING,
					'unit' => null,
					'format' => null,
					'value' => Helpers\Protocol::generateMacAddress(),
				]));
			}

			$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::SETUP_ID);

			$setupIdProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($setupIdProperty === null) {
				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::SETUP_ID->value,
					'dataType' => MetadataTypes\DataType::STRING,
					'unit' => null,
					'format' => null,
					'value' => Helpers\Protocol::generateSetupId(),
				]));
			}

			$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::PIN_CODE);

			$pinCodeProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($pinCodeProperty === null) {
				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::PIN_CODE->value,
					'dataType' => MetadataTypes\DataType::STRING,
					'unit' => null,
					'format' => null,
					'value' => Helpers\Protocol::generatePinCode(),
				]));
			}

			$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::SERVER_SECRET);

			$serverSecretProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($serverSecretProperty === null) {
				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::SERVER_SECRET->value,
					'dataType' => MetadataTypes\DataType::STRING,
					'unit' => null,
					'format' => null,
					'value' => Helpers\Protocol::generateSignKey(),
				]));
			}

			$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::CONFIG_VERSION);

			$versionProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($versionProperty === null) {
				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::CONFIG_VERSION->value,
					'dataType' => MetadataTypes\DataType::USHORT,
					'unit' => null,
					'format' => null,
					'value' => 1,
				]));
			}

			$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::PAIRED);

			$pairedProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($pairedProperty === null) {
				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::PAIRED->value,
					'dataType' => MetadataTypes\DataType::BOOLEAN,
					'unit' => null,
					'format' => null,
					'value' => false,
				]));
			}

			$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::XHM_URI);

			$xhmUriProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($xhmUriProperty === null) {
				$xhmUri = Helpers\Protocol::getXhmUri(
					$entity->getPinCode(),
					$entity->getSetupId(),
					Types\AccessoryCategory::BRIDGE,
				);

				$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::XHM_URI->value,
					'dataType' => MetadataTypes\DataType::STRING,
					'unit' => null,
					'format' => null,
					'value' => $xhmUri,
				]));
			}
		} elseif ($entity instanceof Entities\Devices\Device) {
			$findDevicePropertyQuery = new Queries\Entities\FindDeviceProperties();
			$findDevicePropertyQuery->forDevice($entity);
			$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::STATE);

			$stateProperty = $this->devicesPropertiesRepository->findOneBy($findDevicePropertyQuery);

			if ($stateProperty !== null && !$stateProperty instanceof DevicesEntities\Devices\Properties\Dynamic) {
				$this->devicesPropertiesManager->delete($stateProperty);

				$stateProperty = null;
			}

			if ($stateProperty !== null) {
				$this->devicesPropertiesManager->update($stateProperty, Utils\ArrayHash::from([
					'dataType' => MetadataTypes\DataType::ENUM,
					'unit' => null,
					'format' => [
						DevicesTypes\ConnectionState::CONNECTED->value,
						DevicesTypes\ConnectionState::DISCONNECTED->value,
						DevicesTypes\ConnectionState::ALERT->value,
						DevicesTypes\ConnectionState::LOST->value,
						DevicesTypes\ConnectionState::UNKNOWN->value,
					],
					'settable' => false,
					'queryable' => false,
				]));
			} else {
				$this->devicesPropertiesManager->create(Utils\ArrayHash::from([
					'device' => $entity,
					'entity' => DevicesEntities\Devices\Properties\Dynamic::class,
					'identifier' => Types\DevicePropertyIdentifier::STATE->value,
					'name' => DevicesUtilities\Name::createName(Types\DevicePropertyIdentifier::STATE->value),
					'dataType' => MetadataTypes\DataType::ENUM,
					'unit' => null,
					'format' => [
						DevicesTypes\ConnectionState::CONNECTED->value,
						DevicesTypes\ConnectionState::DISCONNECTED->value,
						DevicesTypes\ConnectionState::ALERT->value,
						DevicesTypes\ConnectionState::UNKNOWN->value,
					],
					'settable' => false,
					'queryable' => false,
				]));
			}
		}
	}

	/**
	 * @param Persistence\Event\LifecycleEventArgs<ORM\EntityManagerInterface> $eventArgs
	 *
	 * @throws DBAL\Exception\UniqueConstraintViolationException
	 * @throws DoctrineCrudExceptions\EntityCreation
	 * @throws DoctrineCrudExceptions\InvalidArgument
	 * @throws DoctrineCrudExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function postUpdate(Persistence\Event\LifecycleEventArgs $eventArgs): void
	{
		$entity = $eventArgs->getObject();

		if ($entity instanceof DevicesEntities\Connectors\Properties\Variable) {
			if (
				$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::PIN_CODE->value
				|| $entity->getIdentifier() === Types\ConnectorPropertyIdentifier::SETUP_ID->value
			) {
				$connector = $entity->getConnector();
				assert($connector instanceof Entities\Connectors\Connector);

				$xhmUri = Helpers\Protocol::getXhmUri(
					$connector->getPinCode(),
					$connector->getSetupId(),
					Types\AccessoryCategory::BRIDGE,
				);

				$findConnectorPropertyQuery = new Queries\Entities\FindConnectorProperties();
				$findConnectorPropertyQuery->forConnector($connector);
				$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::XHM_URI);

				$xhmUriProperty = $this->connectorsPropertiesRepository->findOneBy($findConnectorPropertyQuery);

				if ($xhmUriProperty === null) {
					$this->connectorsPropertiesManager->create(Utils\ArrayHash::from([
						'connector' => $entity,
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::XHM_URI->value,
						'dataType' => MetadataTypes\DataType::STRING,
						'unit' => null,
						'format' => null,
						'value' => $xhmUri,
					]));
				} else {
					$this->connectorsPropertiesManager->update($entity, Utils\ArrayHash::from([
						'value' => $xhmUri,
					]));
				}
			}
		}
	}

}
