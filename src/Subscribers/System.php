<?php declare(strict_types = 1);

/**
 * System.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Subscribers
 * @since          1.0.0
 *
 * @date           15.03.23
 */

namespace FastyBird\Connector\HomeKit\Subscribers;

use Doctrine\Common;
use Doctrine\ORM;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use IPub\DoctrineCrud;
use Nette;
use Nette\Utils;
use Ramsey\Uuid;
use function array_merge;
use function array_unique;
use function intval;

/**
 * Doctrine entities events
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Subscribers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class System implements Common\EventSubscriber
{

	use Nette\SmartObject;

	/** @var array<string>  */
	private array $doUpdate = [];

	public function __construct(
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesRepository $propertiesRepository,
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesManager $propertiesManager,
	)
	{
	}

	public function getSubscribedEvents(): array
	{
		return [
			ORM\Events::preFlush,
			ORM\Events::postFlush,
		];
	}

	public function preFlush(ORM\Event\PreFlushEventArgs $eventArgs): void
	{
		$manager = $eventArgs->getObjectManager();
		$uow = $manager->getUnitOfWork();

		$this->doUpdate = [];

		// Check all scheduled updates
		foreach (array_merge(
			$uow->getScheduledEntityInsertions(),
			$uow->getScheduledEntityUpdates(),
			$uow->getScheduledEntityDeletions(),
		) as $object) {
			if ($object instanceof Entities\HomeKitConnector) {
				$this->doUpdate[] = $object->getId()->toString();
			} elseif ($object instanceof Entities\HomeKitDevice) {
				$this->doUpdate[] = $object->getConnector()->getId()->toString();
			} elseif ($object instanceof DevicesEntities\Devices\Properties\Property) {
				$this->doUpdate[] = $object->getDevice()->getConnector()->getId()->toString();
			} elseif ($object instanceof DevicesEntities\Devices\Controls\Control) {
				$this->doUpdate[] = $object->getDevice()->getConnector()->getId()->toString();
			} elseif ($object instanceof Entities\HomeKitChannel) {
				$this->doUpdate[] = $object->getDevice()->getConnector()->getId()->toString();
			} elseif ($object instanceof DevicesEntities\Channels\Properties\Property) {
				$this->doUpdate[] = $object->getChannel()->getDevice()->getConnector()->getId()->toString();
			} elseif ($object instanceof DevicesEntities\Channels\Controls\Control) {
				$this->doUpdate[] = $object->getChannel()->getDevice()->getConnector()->getId()->toString();
			}
		}

		$this->doUpdate = array_unique($this->doUpdate);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws DoctrineCrud\Exceptions\InvalidArgumentException
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function postFlush(): void
	{
		foreach ($this->doUpdate as $connectorId) {
			$findConnectorPropertyQuery = new DevicesQueries\Entities\FindConnectorProperties();
			$findConnectorPropertyQuery->byConnectorId(Uuid\Uuid::fromString($connectorId));
			$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::CONFIG_VERSION);

			$property = $this->propertiesRepository->findOneBy(
				$findConnectorPropertyQuery,
				DevicesEntities\Connectors\Properties\Variable::class,
			);

			if ($property !== null) {
				$this->propertiesManager->update($property, Utils\ArrayHash::from([
					'value' => intval(DevicesUtilities\ValueHelper::flattenValue($property->getValue())) + 1,
				]));
			}
		}

		$this->doUpdate = [];
	}

}
