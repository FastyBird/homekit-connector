<?php declare(strict_types = 1);

/**
 * Service.php
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

namespace FastyBird\Connector\HomeKit\Entities\Protocol;

use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use Nette;
use Ramsey\Uuid;
use SplObjectStorage;
use function array_map;
use function array_merge;
use function in_array;
use function sprintf;

/**
 * Represents a HAP service
 *
 * A HAP service contains multiple characteristics.
 * For example, a TemperatureSensor service has the characteristic CurrentTemperature.
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Service
{

	use Nette\SmartObject;

	public const HAP_PROTOCOL_INFORMATION_SERVICE_UUID = '000000A2-0000-1000-8000-0026BB765291';

	private bool $hidden = false;

	/** @var SplObjectStorage<Characteristic, null> */
	private SplObjectStorage $characteristics;

	/**
	 * @param array<string> $requiredCharacteristics
	 * @param array<string> $optionalCharacteristics
	 */
	public function __construct(
		private readonly Uuid\UuidInterface $typeId,
		private readonly string $name,
		private readonly Accessory $accessory,
		private readonly DevicesEntities\Channels\Channel|null $channel = null,
		private readonly array $requiredCharacteristics = [],
		private readonly array $optionalCharacteristics = [],
		private bool $primary = false,
	)
	{
		$this->characteristics = new SplObjectStorage();
	}

	public function getTypeId(): Uuid\UuidInterface
	{
		return $this->typeId;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getAccessory(): Accessory
	{
		return $this->accessory;
	}

	public function getChannel(): DevicesEntities\Channels\Channel|null
	{
		return $this->channel;
	}

	/**
	 * @return array<string>
	 */
	public function getAllowedCharacteristicsNames(): array
	{
		return array_merge($this->requiredCharacteristics, $this->optionalCharacteristics);
	}

	/**
	 * @return array<Characteristic>
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
	 * @throws Exceptions\InvalidArgument
	 */
	public function addCharacteristic(Characteristic $characteristic): void
	{
		if (
			!in_array($characteristic->getName(), $this->requiredCharacteristics, true)
			&& !in_array($characteristic->getName(), $this->optionalCharacteristics, true)
		) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Characteristics: %s is not allowed for service: %s',
				$characteristic->getName(),
				$this->getName(),
			));
		}

		$this->characteristics->rewind();

		foreach ($this->characteristics as $existingCharacteristic) {
			if ($existingCharacteristic->getTypeId()->equals($characteristic->getTypeId())) {
				$this->characteristics->detach($existingCharacteristic);
			}
		}

		$this->characteristics->attach($characteristic);
	}

	public function isPrimary(): bool
	{
		return $this->primary;
	}

	public function setPrimary(bool $primary): void
	{
		$this->primary = $primary;
	}

	public function isHidden(): bool
	{
		return $this->hidden;
	}

	public function setHidden(bool $hidden): void
	{
		$this->hidden = $hidden;
	}

	/**
	 * Create a HAP representation of this Service
	 * Used for json serialization
	 *
	 * @return array<string, (string|int|bool|array<array<string, (bool|float|int|array<int>|string|array<string>|null)>>|null)>
	 */
	public function toHap(): array
	{
		return [
			Types\Representation::REPR_IID => $this->accessory->getIidManager()->getIid($this),
			Types\Representation::REPR_TYPE => Helpers\Protocol::uuidToHapType($this->getTypeId()),
			Types\Representation::REPR_CHARS => array_map(
				static fn (Characteristic $characteristic): array => $characteristic->toHap(),
				$this->getCharacteristics(),
			),
			Types\Representation::REPR_PRIMARY => $this->primary,
			Types\Representation::REPR_HIDDEN => $this->hidden,
		];
	}

	/**
	 * @throws Nette\Utils\JsonException
	 */
	public function __toString(): string
	{
		$characteristics = [];

		$this->characteristics->rewind();

		foreach ($this->characteristics as $characteristic) {
			$characteristics[$characteristic->getName()] = $characteristic->getActualValue();
		}

		return sprintf(
			'<service name=%s chars=%s>',
			$this->getName(),
			Nette\Utils\Json::encode($characteristics),
		);
	}

}
