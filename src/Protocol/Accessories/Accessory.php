<?php declare(strict_types = 1);

/**
 * Accessory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 * @since          1.0.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Protocol\Accessories;

use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Core\Tools\Exceptions as ToolsExceptions;
use Nette;
use Ramsey\Uuid;
use SplObjectStorage;
use TypeError;
use ValueError;
use function array_filter;
use function array_map;
use function array_values;
use function sprintf;

/**
 * HAP accessory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Accessory
{

	use Nette\SmartObject;

	/** @var SplObjectStorage<Protocol\Services\Service, null> */
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
	 * @return array<Protocol\Services\Service>
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

	public function addService(Protocol\Services\Service $service): void
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

	/**
	 * @return array<Protocol\Services\Service>
	 */
	public function findServices(Types\ServiceType $name): array
	{
		$services = [];

		$this->services->rewind();

		foreach ($this->services as $service) {
			if ($service->getName() === $name->value) {
				$services[] = $service;
			}
		}

		return $services;
	}

	/**
	 * @interal
	 */
	public function recalculateServices(): void
	{
		// Nothing to do here
	}

	public function getIidManager(): Helpers\IidManager
	{
		return $this->iidManager;
	}

	/**
	 * Create a HAP representation of this Service
	 * Used for json serialization
	 *
	 * @return array<string, array<int, array<string, array<array<string, array<int|string>|bool|float|int|string|null>|int|null>|bool|int|string|null>>|int|null>
	 *
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function toHap(): array
	{
		return [
			Types\Representation::AID->value => $this->aid,
			Types\Representation::SERVICES->value => array_map(
				static fn (Protocol\Services\Service $service): array => $service->toHap(),
				array_values(array_filter(
					$this->getServices(),
					static fn (Protocol\Services\Service $service): bool => !$service->isVirtual(),
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

		foreach ($this->getServices() as $service) {
			$services[] = $service->getName();
		}

		return sprintf(
			'<accessory name=%s chars=%s>',
			$this->getName(),
			Nette\Utils\Json::encode($services),
		);
	}

}
