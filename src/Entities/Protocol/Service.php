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

namespace FastyBird\HomeKitConnector\Entities\Protocol;

use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;
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
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Service
{

	use Nette\SmartObject;

	public const HAP_PROTOCOL_INFORMATION_SERVICE_UUID = '000000A2-0000-1000-8000-0026BB765291';

	/** @var Uuid\UuidInterface */
	private Uuid\UuidInterface $typeId;

	/** @var string */
	private string $name;

	/** @var bool */
	private bool $primary;

	/** @var bool */
	private bool $hidden = false;

	/** @var string[] */
	private array $requiredCharacteristics;

	/** @var string[] */
	private array $optionalCharacteristics;

	/** @var SplObjectStorage<Characteristic, null> */
	private SplObjectStorage $characteristics;

	/** @var Accessory */
	private Accessory $accessory;

	/** @var MetadataEntities\Modules\DevicesModule\ChannelEntity|null */
	private ?MetadataEntities\Modules\DevicesModule\ChannelEntity $channel;

	/**
	 * @param Uuid\UuidInterface $typeId
	 * @param string $name
	 * @param Accessory $accessory
	 * @param MetadataEntities\Modules\DevicesModule\ChannelEntity|null $channel
	 * @param string[] $requiredCharacteristics
	 * @param string[] $optionalCharacteristics
	 * @param bool $primary
	 */
	public function __construct(
		Uuid\UuidInterface $typeId,
		string $name,
		Accessory $accessory,
		?MetadataEntities\Modules\DevicesModule\ChannelEntity $channel = null,
		array $requiredCharacteristics = [],
		array $optionalCharacteristics = [],
		bool $primary = false
	) {
		$this->typeId = $typeId;
		$this->name = $name;
		$this->primary = $primary;

		$this->requiredCharacteristics = $requiredCharacteristics;
		$this->optionalCharacteristics = $optionalCharacteristics;

		$this->accessory = $accessory;
		$this->channel = $channel;

		$this->characteristics = new SplObjectStorage();
	}

	/**
	 * @return Uuid\UuidInterface
	 */
	public function getTypeId(): Uuid\UuidInterface
	{
		return $this->typeId;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return Accessory
	 */
	public function getAccessory(): Accessory
	{
		return $this->accessory;
	}

	/**
	 * @return MetadataEntities\Modules\DevicesModule\ChannelEntity|null
	 */
	public function getChannel(): ?MetadataEntities\Modules\DevicesModule\ChannelEntity
	{
		return $this->channel;
	}

	/**
	 * @return string[]
	 */
	public function getAllowedCharacteristicsNames(): array
	{
		return array_merge($this->requiredCharacteristics, $this->optionalCharacteristics);
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
		if (
			!in_array($characteristic->getName(), $this->requiredCharacteristics, true)
			|| !in_array($characteristic->getName(), $this->optionalCharacteristics, true)
		) {
			throw new Exceptions\InvalidArgument(sprintf(
				'Characteristics: %s is not allowed for service: %s',
				$characteristic->getName(),
				$this->getName()
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

	/**
	 * @return bool
	 */
	public function isPrimary(): bool
	{
		return $this->primary;
	}

	/**
	 * @param bool $primary
	 */
	public function setPrimary(bool $primary): void
	{
		$this->primary = $primary;
	}

	/**
	 * @return bool
	 */
	public function isHidden(): bool
	{
		return $this->hidden;
	}

	/**
	 * @param bool $hidden
	 */
	public function setHidden(bool $hidden): void
	{
		$this->hidden = $hidden;
	}

	/**
	 * Create a HAP representation of this Service
	 * Used for json serialization
	 *
	 * @return Array<string, string|int|bool|Array<string, bool|float|int|int[]|string|string[]|null>[]|null>
	 */
	public function toHap(): array
	{
		return [
			Types\Representation::REPR_IID     => $this->accessory->getIidManager()->getIid($this),
			Types\Representation::REPR_TYPE    => Helpers\Protocol::uuidToHapType($this->getTypeId()),
			Types\Representation::REPR_CHARS   => array_map(function (Characteristic $characteristic): array {
				return $characteristic->toHap();
			}, $this->getCharacteristics()),
			Types\Representation::REPR_PRIMARY => $this->primary,
			Types\Representation::REPR_HIDDEN => $this->hidden,
		];
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
			$characteristics[$characteristic->getName()] = $characteristic->getActualValue();
		}

		return sprintf(
			'<service name=%s chars=%s>',
			$this->getName(),
			Nette\Utils\Json::encode($characteristics)
		);
	}

}
