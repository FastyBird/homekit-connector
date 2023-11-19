<?php declare(strict_types = 1);

/**
 * Service.php
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

use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use Nette;
use Ramsey\Uuid;
use SplObjectStorage;
use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
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

	private const VIRTUAL_SERVICE_UID = '00000000-0000-0000-0000-000000000000';

	private bool $hidden = false;

	/** @var SplObjectStorage<Characteristic, null> */
	private SplObjectStorage $characteristics;

	/**
	 * @param array<string> $requiredCharacteristics
	 * @param array<string> $optionalCharacteristics
	 * @param array<string> $virtualCharacteristics
	 */
	public function __construct(
		private readonly Uuid\UuidInterface $typeId,
		private readonly string $name,
		private readonly Accessory $accessory,
		private readonly MetadataDocuments\DevicesModule\Channel|null $channel = null,
		private readonly array $requiredCharacteristics = [],
		private readonly array $optionalCharacteristics = [],
		private readonly array $virtualCharacteristics = [],
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

	public function getChannel(): MetadataDocuments\DevicesModule\Channel|null
	{
		return $this->channel;
	}

	/**
	 * @return array<string>
	 */
	public function getAllowedCharacteristicsNames(): array
	{
		return array_merge(
			$this->requiredCharacteristics,
			$this->optionalCharacteristics,
			$this->virtualCharacteristics,
		);
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
			&& !in_array($characteristic->getName(), $this->virtualCharacteristics, true)
		) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Characteristics: %s is not allowed for service: %s',
				$characteristic->getName(),
				$this->getName(),
			));
		}

		$this->characteristics->attach($characteristic);
	}

	public function hasCharacteristic(string $name): bool
	{
		$this->characteristics->rewind();

		foreach ($this->characteristics as $characteristic) {
			if ($characteristic->getName() === $name) {
				return true;
			}
		}

		return false;
	}

	public function findCharacteristic(string $name): Characteristic|null
	{
		$this->characteristics->rewind();

		foreach ($this->characteristics as $characteristic) {
			if ($characteristic->getName() === $name) {
				return $characteristic;
			}
		}

		return null;
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

	public function isVirtual(): bool
	{
		return $this->typeId->toString() === self::VIRTUAL_SERVICE_UID;
	}

	/**
	 * @interal
	 */
	public function recalculateValues(Characteristic $characteristic, bool $fromDevice): void
	{
		$this->accessory->recalculateValues($this, $characteristic, $fromDevice);
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
			Types\Representation::IID => $this->accessory->getIidManager()->getIid($this),
			Types\Representation::TYPE => Helpers\Protocol::uuidToHapType($this->getTypeId()),
			Types\Representation::CHARS => array_map(
				static fn (Characteristic $characteristic): array => $characteristic->toHap(),
				array_values(array_filter(
					$this->getCharacteristics(),
					static fn (Characteristic $characteristic): bool => !$characteristic->isVirtual()
				)),
			),
			Types\Representation::PRIMARY => $this->primary,
			Types\Representation::HIDDEN => $this->hidden,
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
			$characteristics[$characteristic->getName()] = $characteristic->getValue();
		}

		return sprintf(
			'<service name=%s chars=%s>',
			$this->getName(),
			Nette\Utils\Json::encode($characteristics),
		);
	}

}
