<?php declare(strict_types = 1);

/**
 * Bridge.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\HomeKitConnector\Protocol\Accessories;

use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Protocol;
use FastyBird\HomeKitConnector\Types;
use Nette;
use SplObjectStorage;

/**
 * HAP accessory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
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

	/** @var Protocol\AccessoryDriver */
	protected Protocol\AccessoryDriver $driver;

	/** @var Helpers\IidManager */
	protected Helpers\IidManager $iidManager;

	/**
	 * @param Protocol\AccessoryDriver $driver
	 * @param string $name
	 * @param int|null $aid
	 * @param Types\Category|null $category
	 */
	public function __construct(
		Protocol\AccessoryDriver $driver,
		string $name,
		?int $aid = null,
		?Types\Category $category = null,
	) {
		$this->driver = $driver;
		$this->name = $name;
		$this->aid = $aid;
		$this->category = $category ?? Types\Category::get(Types\Category::CATEGORY_OTHER);

		$this->services = new SplObjectStorage();

		// Add the required `AccessoryInformation` service
		$serviceInfo = $driver->getLoader()->getService('AccessoryInformation');
		$serviceInfo->configureCharacteristic('Name', $name);
		$serviceInfo->configureCharacteristic('SerialNumber', 'default');

		$this->addService($serviceInfo);

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
		if (!$this->services->contains($service)) {
			$this->services->attach($service);
		}
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
	 * @return Array<string, string|int|bool|Array<string, string|int|float|bool|string[]|null>[]|null>
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
			'<accessory display_name=%s chars=%s>',
			$this->getName(),
			Nette\Utils\Json::encode($services)
		);
	}

}