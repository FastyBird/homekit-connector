<?php declare(strict_types = 1);

/**
 * Accessory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Entities\Protocol;

use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Types;
use Nette;
use Ramsey\Uuid;
use SplObjectStorage;
use function array_filter;
use function array_map;
use function array_values;
use function sprintf;

/**
 * HAP accessory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Accessory
{

	use Nette\SmartObject;

	/** @var SplObjectStorage<Service, null> */
	protected SplObjectStorage $services;

	protected Helpers\IidManager $iidManager;

	public function __construct(
		protected string $name,
		protected int|null $aid,
		protected Types\AccessoryCategory $category,
	)
	{
		$this->services = new SplObjectStorage();

		$this->iidManager = new Helpers\IidManager();
	}

	abstract public function getId(): Uuid\UuidInterface;

	public function getName(): string
	{
		return $this->name;
	}

	public function getAid(): int|null
	{
		return $this->aid;
	}

	public function setAid(int $aid): void
	{
		$this->aid = $aid;
	}

	public function getCategory(): Types\AccessoryCategory
	{
		return $this->category;
	}

	/**
	 * @return array<Service>
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

	public function addService(Service $service): void
	{
		if (!$service->isVirtual()) {
			$this->iidManager->assign($service);

			foreach ($service->getCharacteristics() as $characteristic) {
				if (!$characteristic->isVirtual()) {
					$this->iidManager->assign($characteristic);
				}
			}
		}

		$this->services->attach($service);
	}

	public function findService(string $name): Service|null
	{
		$this->services->rewind();

		foreach ($this->services as $service) {
			if ($service->getName() === $name) {
				return $service;
			}
		}

		return null;
	}

	/**
	 * @interal
	 */
	public function recalculateValues(Service $service, Characteristic $characteristic, bool $fromDevice): void
	{
		// Used only for specific accessories
	}

	public function getIidManager(): Helpers\IidManager
	{
		return $this->iidManager;
	}

	/**
	 * Create a HAP representation of this Service
	 * Used for json serialization
	 *
	 * @return array<string, (int|array<array<string, (string|int|bool|array<array<string, (bool|float|int|array<int>|string|array<string>|null)>>|null)>>|null)>
	 */
	public function toHap(): array
	{
		return [
			Types\Representation::REPR_AID => $this->aid,
			Types\Representation::REPR_SERVICES => array_map(
				static fn (Service $service): array => $service->toHap(),
				array_values(array_filter(
					$this->getServices(),
					static fn (Service $service): bool => !$service->isVirtual()
				)),
			),
		];
	}

	/**
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
			Nette\Utils\Json::encode($services),
		);
	}

}
