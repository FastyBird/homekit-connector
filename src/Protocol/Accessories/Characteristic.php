<?php declare(strict_types = 1);

/**
 * Characteristic.php
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

use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Types;
use Nette;
use Ramsey\Uuid;

/**
 * Represents a HAP characteristic, the smallest unit of the smart home
 *
 * A HAP characteristic is some measurement or state, like battery status or
 * the current temperature. Characteristics are contained in services.
 * Each characteristic has a unique type UUID and a set of properties,
 * like format, min and max values, valid values and others.
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Characteristic
{

	use Nette\SmartObject;

	private const DEFAULT_MAX_LENGTH = 64;
	private const ABSOLUTE_MAX_LENGTH = 256;

	private const ALWAYS_NULL = [
		'00000073-0000-1000-8000-0026BB765291', // PROGRAMMABLE SWITCH
	];

	private const IMMEDIATE_NOTIFY = [
		'00000126-0000-1000-8000-0026BB765291', // BUTTON
		'00000073-0000-1000-8000-0026BB765291', // PROGRAMMABLE SWITCH
	];

	/** @var Uuid\UuidInterface */
	private Uuid\UuidInterface $typeId;

	/** @var string */
	private string $name;

	/** @var Types\DataType */
	private Types\DataType $dataType;

	/** @var string[]|null */
	private ?array $validValues;

	/** @var int|null */
	private ?int $maxLength;

	/** @var float|null */
	private ?float $minValue;

	/** @var float|null */
	private ?float $maxValue;

	/** @var float|null */
	private ?float $minStep;

	/** @var string|null */
	private ?string $unit;

	/** @var string[] */
	private array $permissions;

	/** @var Accessory */
	private Accessory $accessory;

	/** @var Service */
	private Service $service;

	/**
	 * @param Uuid\UuidInterface $typeId
	 * @param string $name
	 * @param Types\DataType $dataType
	 * @param string[] $permissions
	 * @param Accessory $accessory
	 * @param Service $service
	 * @param string[]|null $validValues
	 * @param int|null $maxLength
	 * @param float|null $minValue
	 * @param float|null $maxValue
	 * @param float|null $minStep
	 * @param string|null $unit
	 */
	public function __construct(
		Uuid\UuidInterface $typeId,
		string $name,
		Types\DataType $dataType,
		array $permissions,
		Accessory $accessory,
		Service $service,
		?array $validValues = [],
		?int $maxLength = null,
		?float $minValue = null,
		?float $maxValue = null,
		?float $minStep = null,
		?string $unit = null
	) {
		if ($maxLength !== null && $maxLength > self::ABSOLUTE_MAX_LENGTH) {
			throw new Exceptions\InvalidArgument('Characteristic max length exceeded allowed maximum');
		}

		$this->typeId = $typeId;
		$this->name = $name;
		$this->dataType = $dataType;
		$this->validValues = $validValues;
		$this->maxLength = $maxLength;
		$this->minValue = $minValue;
		$this->maxValue = $maxValue;
		$this->minStep = $minStep;
		$this->unit = $unit;
		$this->permissions = $permissions;

		$this->accessory = $accessory;
		$this->service = $service;
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
	 * @return int|float|string|bool
	 */
	public function getValue(): int|float|string|bool
	{
		return 'TBD';
	}

	/**
	 * @param int|float|string|bool $value
	 *
	 * @return void
	 */
	public function setValue(int|float|string|bool $value): void
	{
		# TBD
	}

	/**
	 * Create a HAP representation of this Characteristic
	 * Used for json serialization
	 *
	 * @return Array<string, string|int|float|bool|string[]|null>
	 */
	public function toHap(): array
	{
		$hapRepresentation = [
			Types\Representation::REPR_IID    => $this->accessory->getIidManager()->getIid($this),
			Types\Representation::REPR_TYPE   => Helpers\Protocol::uuidToHapType($this->typeId),
			Types\Representation::REPR_PERM   => $this->permissions,
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
				$hapRepresentation[Types\CharacteristicPropertyIdentifier::IDENTIFIER_MAX_VALUE] = $this->maxValue;
			}

			if ($this->minValue !== null) {
				$hapRepresentation[Types\CharacteristicPropertyIdentifier::IDENTIFIER_MIN_VALUE] = $this->minValue;
			}

			if ($this->minStep !== null) {
				$hapRepresentation[Types\CharacteristicPropertyIdentifier::IDENTIFIER_MIN_STEP] = $this->minStep;
			}

			if ($this->unit !== null) {
				$hapRepresentation[Types\CharacteristicPropertyIdentifier::IDENTIFIER_UNIT] = $this->unit;
			}

			if ($this->validValues !== null) {
				$hapRepresentation[Types\Representation::REPR_VALID_VALUES] = $this->validValues;
			}
		}

		if ($this->dataType->equalsValue(Types\DataType::DATA_TYPE_STRING)) {
			if ($this->maxLength !== self::DEFAULT_MAX_LENGTH) {
				$hapRepresentation[Types\Representation::REPR_MAX_LEN] = $this->maxLength;
			}
		}

		if (in_array(Types\PermissionIdentifier::PERMISSION_READ, $this->permissions, true)) {
			$hapRepresentation[Types\Representation::REPR_VALUE] = $this->getValue();
		}

		return $hapRepresentation;
	}

	/**
	 * @return bool
	 */
	private function isAlwaysNull(): bool
	{
		return in_array($this->typeId->toString(), self::ALWAYS_NULL);
	}

	/**
	 * @return bool
	 */
	private function immediateNotify(): bool
	{
		return in_array($this->typeId->toString(), self::IMMEDIATE_NOTIFY);
	}

	/**
	 * @return string
	 *
	 * @throws Nette\Utils\JsonException
	 */
	public function __toString(): string
	{
		$properties = [
			Types\CharacteristicPropertyIdentifier::IDENTIFIER_PERMISSIONS => $this->permissions,
			Types\CharacteristicPropertyIdentifier::IDENTIFIER_FORMAT => $this->dataType->getValue(),
		];

		if ($this->validValues !== null) {
			$properties[Types\CharacteristicPropertyIdentifier::IDENTIFIER_VALID_VALUES] = $this->validValues;
		}

		if ($this->minStep !== null) {
			$properties[Types\CharacteristicPropertyIdentifier::IDENTIFIER_MIN_STEP] = $this->minStep;
		}

		if ($this->minValue !== null) {
			$properties[Types\CharacteristicPropertyIdentifier::IDENTIFIER_MIN_VALUE] = $this->minValue;
		}

		if ($this->maxValue !== null) {
			$properties[Types\CharacteristicPropertyIdentifier::IDENTIFIER_MAX_VALUE] = $this->maxValue;
		}

		if ($this->unit !== null) {
			$properties[Types\CharacteristicPropertyIdentifier::IDENTIFIER_UNIT] = $this->unit;
		}

		return sprintf(
			'<characteristic display_name=%s value=%s properties=%s>',
			$this->name,
			$this->getValue(),
			Nette\Utils\Json::encode($properties)
		);
	}

}