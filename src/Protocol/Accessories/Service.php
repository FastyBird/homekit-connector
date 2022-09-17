<?php declare(strict_types = 1);

/**
 * Service.php
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
use FastyBird\HomeKitConnector\Types;
use Nette;
use Ramsey\Uuid;
use SplObjectStorage;

/**
 * Represents a HAP service
 *
 * A HAP service contains multiple characteristics.
 * For example, a TemperatureSensor service has the characteristic CurrentTemperature.
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Service
{

	use Nette\SmartObject;

	/** @var Uuid\UuidInterface */
	private Uuid\UuidInterface $typeId;

	/** @var string */
	private string $name;

	/** @var bool|null */
	private ?bool $primary;

	/** @var SplObjectStorage<Characteristic, null> */
	private SplObjectStorage $characteristics;

	/** @var Accessory */
	private Accessory $accessory;

	/**
	 * @param Uuid\UuidInterface $typeId
	 * @param string $name
	 * @param Accessory $accessory
	 * @param bool|null $primary
	 */
	public function __construct(
		Uuid\UuidInterface $typeId,
		string $name,
		Accessory $accessory,
		?bool $primary = null
	) {
		$this->typeId = $typeId;
		$this->name = $name;
		$this->primary = $primary;

		$this->accessory = $accessory;

		$this->characteristics = new SplObjectStorage();
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return Uuid\UuidInterface
	 */
	public function getTypeId(): Uuid\UuidInterface
	{
		return $this->typeId;
	}

	/**
	 * @return Characteristic[]
	 */
	public function getCharacteristics(): array
	{
		$characteristics = [];

		$this->characteristics->rewind();

		foreach ($this->characteristics as $characteristic) {
			$characteristics[] = $characteristic;
		}

		return $characteristics;
	}

	/**
	 * @param Characteristic $characteristic
	 *
	 * @return void
	 */
	public function addCharacteristic(Characteristic $characteristic): void
	{
		if (!$this->characteristics->contains($characteristic)) {
			$this->characteristics->attach($characteristic);
		}
	}

	/**
	 * Create a HAP representation of this Service
	 * Used for json serialization
	 *
	 * @return Array<string, string|int|bool|Array<string, string|int|float|bool|string[]|null>[]|null>
	 */
	public function toHap(): array
	{
		$hapRepresentation = [
			Types\Representation::REPR_IID    => $this->accessory->getIidManager()->getIid($this),
			Types\Representation::REPR_TYPE   => Helpers\Protocol::uuidToHapType($this->getTypeId()),
			Types\Representation::REPR_CHARS  => array_map(function (Characteristic $characteristic): array {
				return $characteristic->toHap();
			}, $this->getCharacteristics()),
		];

		if ($this->primary !== null) {
			$hapRepresentation[Types\Representation::REPR_PRIMARY] = $this->primary;
		}

		return $hapRepresentation;
	}

	/**
	 * @return string
	 *
	 * @throws Nette\Utils\JsonException
	 */
	public function __toString(): string
	{
		$characteristics = [];

		$this->characteristics->rewind();

		foreach ($this->characteristics as $characteristic) {
			$characteristics[$characteristic->getName()] = $characteristic->getValue();
		}

		return sprintf(
			'<service display_name=%s chars=%s>',
			$this->getName(),
			Nette\Utils\Json::encode($characteristics)
		);
	}

}
