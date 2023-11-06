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
use Doctrine\ORM;
use Doctrine\Persistence;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries;
use IPub\DoctrineCrud\Exceptions as DoctrineCrudExceptions;
use Nette;
use Nette\Utils;
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
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesRepository $propertiesRepository,
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesManager $propertiesManager,
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
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function postPersist(Persistence\Event\LifecycleEventArgs $eventArgs): void
	{
		$entity = $eventArgs->getObject();

		// Check for valid entity
		if ($entity instanceof Entities\HomeKitConnector) {
			$findConnectorPropertyQuery = new Queries\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::MAC_ADDRESS);

			$macAddressProperty = $this->propertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($macAddressProperty === null) {
				$this->propertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::MAC_ADDRESS,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'unit' => null,
					'format' => null,
					'value' => Helpers\Protocol::generateMacAddress(),
				]));
			}

			$findConnectorPropertyQuery = new Queries\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::SETUP_ID);

			$setupIdProperty = $this->propertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($setupIdProperty === null) {
				$this->propertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::SETUP_ID,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'unit' => null,
					'format' => null,
					'value' => Helpers\Protocol::generateSetupId(),
				]));
			}

			$findConnectorPropertyQuery = new Queries\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::PIN_CODE);

			$pinCodeProperty = $this->propertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($pinCodeProperty === null) {
				$this->propertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::PIN_CODE,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'unit' => null,
					'format' => null,
					'value' => Helpers\Protocol::generatePinCode(),
				]));
			}

			$findConnectorPropertyQuery = new Queries\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::SERVER_SECRET);

			$serverSecretProperty = $this->propertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($serverSecretProperty === null) {
				$this->propertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::SERVER_SECRET,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'unit' => null,
					'format' => null,
					'value' => Helpers\Protocol::generateSignKey(),
				]));
			}

			$findConnectorPropertyQuery = new Queries\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::CONFIG_VERSION);

			$versionProperty = $this->propertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($versionProperty === null) {
				$this->propertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::CONFIG_VERSION,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_USHORT),
					'unit' => null,
					'format' => null,
					'value' => 1,
				]));
			}

			$findConnectorPropertyQuery = new Queries\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::PAIRED);

			$pairedProperty = $this->propertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($pairedProperty === null) {
				$this->propertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::PAIRED,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_BOOLEAN),
					'unit' => null,
					'format' => null,
					'value' => false,
				]));
			}

			$findConnectorPropertyQuery = new Queries\FindConnectorProperties();
			$findConnectorPropertyQuery->forConnector($entity);
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::XHM_URI);

			$xhmUriProperty = $this->propertiesRepository->findOneBy($findConnectorPropertyQuery);

			if ($xhmUriProperty === null) {
				$xhmUri = Helpers\Protocol::getXhmUri(
					$entity->getPinCode(),
					$entity->getSetupId(),
					Types\AccessoryCategory::get(Types\AccessoryCategory::BRIDGE),
				);

				$this->propertiesManager->create(Utils\ArrayHash::from([
					'connector' => $entity,
					'entity' => DevicesEntities\Connectors\Properties\Variable::class,
					'identifier' => Types\ConnectorPropertyIdentifier::XHM_URI,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
					'unit' => null,
					'format' => null,
					'value' => $xhmUri,
				]));
			}
		}
	}

	/**
	 * @param Persistence\Event\LifecycleEventArgs<ORM\EntityManagerInterface> $eventArgs
	 *
	 * @throws DevicesExceptions\InvalidState
	 * @throws DoctrineCrudExceptions\InvalidArgumentException
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function postUpdate(Persistence\Event\LifecycleEventArgs $eventArgs): void
	{
		$entity = $eventArgs->getObject();

		if ($entity instanceof DevicesEntities\Connectors\Properties\Variable) {
			if (
				$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::PIN_CODE
				|| $entity->getIdentifier() === Types\ConnectorPropertyIdentifier::SETUP_ID
			) {
				$connector = $entity->getConnector();
				assert($connector instanceof Entities\HomeKitConnector);

				$xhmUri = Helpers\Protocol::getXhmUri(
					$connector->getPinCode(),
					$connector->getSetupId(),
					Types\AccessoryCategory::get(Types\AccessoryCategory::BRIDGE),
				);

				$findConnectorPropertyQuery = new Queries\FindConnectorProperties();
				$findConnectorPropertyQuery->forConnector($connector);
				$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::XHM_URI);

				$xhmUriProperty = $this->propertiesRepository->findOneBy($findConnectorPropertyQuery);

				if ($xhmUriProperty === null) {
					$this->propertiesManager->create(Utils\ArrayHash::from([
						'connector' => $entity,
						'entity' => DevicesEntities\Connectors\Properties\Variable::class,
						'identifier' => Types\ConnectorPropertyIdentifier::XHM_URI,
						'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_STRING),
						'unit' => null,
						'format' => null,
						'value' => $xhmUri,
					]));
				} else {
					$this->propertiesManager->update($entity, Utils\ArrayHash::from([
						'value' => $xhmUri,
					]));
				}
			}
		}
	}

}
