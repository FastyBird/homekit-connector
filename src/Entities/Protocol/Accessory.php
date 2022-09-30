<?php declare(strict_types = 1);

/**
 * Accessory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\HomeKitConnector\Entities\Protocol;

use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;
use SplObjectStorage;

/**
 * HAP accessory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Accessory
{

	use Nette\SmartObject;

	/** @var int|null */
	protected ?int $aid;

	/** @var string */
	protected string $name;

	/** @var Types\Category */
	protected Types\Category $category;

	/** @var SplObjectStorage<Service, null> */
	protected SplObjectStorage $services;

	/** @var Helpers\IidManager */
	protected Helpers\IidManager $iidManager;

	/** @var MetadataEntities\Modules\DevicesModule\ConnectorEntity|MetadataEntities\Modules\DevicesModule\DeviceEntity|null */
	protected MetadataEntities\Modules\DevicesModule\ConnectorEntity|MetadataEntities\Modules\DevicesModule\DeviceEntity|null $owner = null;

	/**
	 * @param string $name
	 * @param int|null $aid
	 * @param Types\Category $category
	 * @param MetadataEntities\Modules\DevicesModule\ConnectorEntity|MetadataEntities\Modules\DevicesModule\DeviceEntity|null $owner
	 */
	public function __construct(
		string $name,
		?int $aid,
		Types\Category $category,
		MetadataEntities\Modules\DevicesModule\ConnectorEntity|MetadataEntities\Modules\DevicesModule\DeviceEntity|null $owner = null
	) {
		$this->name = $name;
		$this->aid = $aid;
		$this->category = $category;

		$this->owner = $owner;

		$this->services = new SplObjectStorage();

		$this->iidManager = new Helpers\IidManager();
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return Types\Category
	 */
	public function getCategory(): Types\Category
	{
		return $this->category;
	}

	/**
	 * @return MetadataEntities\Modules\DevicesModule\ConnectorEntity|MetadataEntities\Modules\DevicesModule\DeviceEntity|null
	 */
	public function getOwner(): MetadataEntities\Modules\DevicesModule\ConnectorEntity|MetadataEntities\Modules\DevicesModule\DeviceEntity|null
	{
		return $this->owner;
	}

	/**
	 * @return Service[]
	 */
	public function getServices(): array
	{
		$services = [];

		$this->services->rewind();

		foreach ($this->services as $service) {
			$services[] = $service;
		}

		return $services;
	}

	/**
	 * @param Service $service
	 *
	 * @return void
	 */
	public function addService(Service $service): void
	{
		$this->services->rewind();

		foreach ($this->services as $existingService) {
			if ($existingService->getTypeId()->equals($service->getTypeId())) {
				$this->services->detach($existingService);
			}
		}

		$this->services->attach($service);
	}

	/**
	 * @return Helpers\IidManager
	 */
	public function getIidManager(): Helpers\IidManager
	{
		return $this->iidManager;
	}

	/**
	 * Create a HAP representation of this Service
	 * Used for json serialization
	 *
	 * @return Array<string, int|Array<string, string|int|bool|Array<string, bool|float|int|int[]|string|string[]|null>[]|null>[]|null>
	 */
	public function toHap(): array
	{
		return [
			Types\Representation::REPR_AID      => $this->aid,
			Types\Representation::REPR_SERVICES => array_map(function (Service $service): array {
				return $service->toHap();
			}, $this->getServices()),
		];
	}

	/**
	 * @return string
	 *
	 * @throws Nette\Utils\JsonException
	 */
	public function __toString(): string
	{
		$services = [];

		$this->services->rewind();

		foreach ($this->services as $characteristic) {
			$services[] = $characteristic->getName();
		}

		return sprintf(
			'<accessory name=%s chars=%s>',
			$this->getName(),
			Nette\Utils\Json::encode($services)
		);
	}

}