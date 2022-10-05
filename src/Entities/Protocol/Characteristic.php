<?php declare(strict_types = 1);

/**
 * Characteristic.php
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

use DateTimeInterface;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Protocol\Transformer;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types as MetadataTypes;
use Nette;
use Ramsey\Uuid;
use function array_merge;
use function in_array;
use function sprintf;
use function strval;

/**
 * Represents a HAP characteristic, the smallest unit of the smart home
 *
 * A HAP characteristic is some measurement or state, like battery status or
 * the current temperature. Characteristics are contained in services.
 * Each characteristic has a unique type UUID and a set of properties,
 * like format, min and max values, valid values and others.
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Characteristic
{

	use Nette\SmartObject;

	private const DEFAULT_MAX_LENGTH = 64;

	private const ABSOLUTE_MAX_LENGTH = 256;

	private const ALWAYS_NULL
		= [
			'00000073-0000-1000-8000-0026BB765291', // PROGRAMMABLE SWITCH
		];

	private const IMMEDIATE_NOTIFY
		= [
			'00000126-0000-1000-8000-0026BB765291', // BUTTON
			'00000073-0000-1000-8000-0026BB765291', // PROGRAMMABLE SWITCH
		];

	private bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $actualValue = null;

	private bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $expectedValue = null;

	/**
	 * @param Uuid\UuidInterface $typeId
	 * @param string $name
	 * @param Types\DataType $dataType
	 * @param Array<string> $permissions
	 * @param Service $service
	 * @param MetadataEntities\Modules\DevicesModule\PropertyEntity|null $property
	 * @param Array<int>|null $validValues
	 * @param int|null $maxLength
	 * @param float|null $minValue
	 * @param float|null $maxValue
	 * @param float|null $minStep
	 * @param Types\CharacteristicUnit|null $unit
	 */
	public function __construct(
		private Uuid\UuidInterface $typeId,
		private string $name,
		private Types\DataType $dataType,
		private array $permissions,
		private Service $service,
		private MetadataEntities\Modules\DevicesModule\PropertyEntity|null $property = null,
		private array|null $validValues = [],
		private int|null $maxLength = null,
		private float|null $minValue = null,
		private float|null $maxValue = null,
		private float|null $minStep = null,
		private Types\CharacteristicUnit|null $unit = null,
	)
	{
		if ($maxLength !== null && $maxLength > self::ABSOLUTE_MAX_LENGTH) {
			throw new Exceptions\InvalidArgument('Characteristic max length exceeded allowed maximum');
		}
	}

	public function getTypeId(): Uuid\UuidInterface
	{
		return $this->typeId;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getDataType(): Types\DataType
	{
		return $this->dataType;
	}

	/**
	 * @return Array<string>
	 */
	public function getPermissions(): array
	{
		return $this->permissions;
	}

	/**
	 * @return Array<int>|null
	 */
	public function getValidValues(): array|null
	{
		return $this->validValues;
	}

	public function getMinValue(): float|null
	{
		return $this->minValue;
	}

	public function getMaxValue(): float|null
	{
		return $this->maxValue;
	}

	public function getMinStep(): float|null
	{
		return $this->minStep;
	}

	public function getMaxLength(): int|null
	{
		return $this->maxLength;
	}

	public function getProperty(): MetadataEntities\Modules\DevicesModule\PropertyEntity|null
	{
		return $this->property;
	}

	/**
	 * @return bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null
	 */
	public function getActualValue(): bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null
	{
		if ($this->expectedValue !== null) {
			return $this->expectedValue;
		}

		return $this->actualValue;
	}

	/**
	 * @param bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $value
	 *
	 * @return void
	 */
	public function setActualValue(
		bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $value,
	): void
	{
		$this->actualValue = $value;

		if ($this->actualValue === $this->expectedValue) {
			$this->expectedValue = null;
		}
	}

	/**
	 * @return bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null
	 */
	public function getExpectedValue(): bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null
	{
		return $this->expectedValue;
	}

	/**
	 * @param bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $value
	 *
	 * @return void
	 */
	public function setExpectedValue(
		bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $value,
	): void
	{
		$this->expectedValue = $value;
	}

	public function isAlwaysNull(): bool
	{
		return in_array($this->typeId->toString(), self::ALWAYS_NULL, true);
	}

	public function immediateNotify(): bool
	{
		return in_array($this->typeId->toString(), self::IMMEDIATE_NOTIFY, true);
	}

	/**
	 * @return Array<string, (int|Array<int>|float|string)>
	 */
	public function getMeta(): array
	{
		$meta = [
			Types\Representation::REPR_FORMAT => strval($this->dataType->getValue()),
		];

		if (
			$this->dataType->equalsValue(Types\DataType::DATA_TYPE_INT)
			|| $this->dataType->equalsValue(Types\DataType::DATA_TYPE_UINT8)
			|| $this->dataType->equalsValue(Types\DataType::DATA_TYPE_UINT16)
			|| $this->dataType->equalsValue(Types\DataType::DATA_TYPE_UINT32)
			|| $this->dataType->equalsValue(Types\DataType::DATA_TYPE_UINT64)
			|| $this->dataType->equalsValue(Types\DataType::DATA_TYPE_FLOAT)
		) {
			if ($this->maxValue !== null) {
				$meta[Types\Representation::REPR_MAX_VALUE] = $this->maxValue;
			}

			if ($this->minValue !== null) {
				$meta[Types\Representation::REPR_MIN_VALUE] = $this->minValue;
			}

			if ($this->minStep !== null) {
				$meta[Types\Representation::REPR_MIN_STEP] = $this->minStep;
			}

			if ($this->unit !== null) {
				$meta[Types\Representation::REPR_UNIT] = strval($this->unit->getValue());
			}
		}

		if ($this->validValues !== null) {
			$meta[Types\Representation::REPR_VALID_VALUES] = $this->validValues;
		}

		if ($this->dataType->equalsValue(Types\DataType::DATA_TYPE_STRING) && $this->maxLength !== null) {
			if ($this->maxLength !== self::DEFAULT_MAX_LENGTH) {
				$meta[Types\Representation::REPR_MAX_LEN] = $this->maxLength;
			}
		}

		return $meta;
	}

	/**
	 * Create a HAP representation of this Characteristic
	 * Used for json serialization
	 *
	 * @return Array<string, (bool|float|int|Array<int>|string|Array<string>|null)>
	 */
	public function toHap(): array
	{
		$hapRepresentation = [
			Types\Representation::REPR_IID => $this->service->getAccessory()->getIidManager()->getIid($this),
			Types\Representation::REPR_TYPE => Helpers\Protocol::uuidToHapType($this->typeId),
			Types\Representation::REPR_PERM => $this->permissions,
			Types\Representation::REPR_FORMAT => strval($this->dataType->getValue()),
		];

		$hapRepresentation = array_merge($hapRepresentation, $this->getMeta());

		if (in_array(Types\CharacteristicPermission::PERMISSION_READ, $this->permissions, true)) {
			$hapRepresentation[Types\Representation::REPR_VALUE] = Transformer::toClient(
				$this->property,
				$this->dataType,
				$this->validValues,
				$this->maxLength,
				$this->minValue,
				$this->maxValue,
				$this->minStep,
				$this->getActualValue(),
			);
		}

		$hapRepresentation[Types\CharacteristicPermission::PERMISSION_NOTIFY] = in_array(
			Types\CharacteristicPermission::PERMISSION_NOTIFY,
			$this->permissions,
			true,
		);

		return $hapRepresentation;
	}

	/**
	 * @return string
	 *
	 * @throws Nette\Utils\JsonException
	 */
	public function __toString(): string
	{
		$properties = [
			'permissions' => $this->permissions,
			'format' => $this->dataType->getValue(),
		];

		if ($this->validValues !== null) {
			$properties['valid-values'] = $this->validValues;
		}

		if ($this->minStep !== null) {
			$properties['min-step'] = $this->minStep;
		}

		if ($this->minValue !== null) {
			$properties['min-value'] = $this->minValue;
		}

		if ($this->maxValue !== null) {
			$properties['max-value'] = $this->maxValue;
		}

		if ($this->unit !== null) {
			$properties['unit'] = $this->unit->getValue();
		}

		return sprintf(
			'<characteristic name=%s value=%s properties=%s>',
			$this->name,
			strval($this->getActualValue()),
			Nette\Utils\Json::encode($properties),
		);
	}

}
