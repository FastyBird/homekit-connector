<?php declare(strict_types = 1);

/**
 * State.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queue
 * @since          1.0.0
 *
 * @date           23.01.24
 */

namespace FastyBird\Connector\HomeKit\Queue\Messages;

use DateTimeInterface;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\Utilities as MetadataUtilities;
use FastyBird\Module\Devices\States as DevicesStates;
use Orisai\ObjectMapper;
use Ramsey\Uuid;
use function is_bool;

/**
 * Property state
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queue
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
readonly class State implements Message
{

	public function __construct(
		private Uuid\UuidInterface $id,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\BoolValue(),
			new ObjectMapper\Rules\IntValue(),
			new ObjectMapper\Rules\FloatValue(),
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\DateTimeValue(format: DateTimeInterface::ATOM),
			new ObjectMapper\Rules\BackedEnumValue(class: MetadataTypes\Payloads\Button::class),
			new ObjectMapper\Rules\BackedEnumValue(class: MetadataTypes\Payloads\Switcher::class),
			new ObjectMapper\Rules\BackedEnumValue(class: MetadataTypes\Payloads\Cover::class),
			new ObjectMapper\Rules\ObjectValue(),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName(DevicesStates\Property::ACTUAL_VALUE_FIELD)]
		private bool|float|int|string|DateTimeInterface|MetadataTypes\Payloads\Payload|null $actualValue = null,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\BoolValue(),
			new ObjectMapper\Rules\IntValue(),
			new ObjectMapper\Rules\FloatValue(),
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\DateTimeValue(format: DateTimeInterface::ATOM),
			new ObjectMapper\Rules\BackedEnumValue(class: MetadataTypes\Payloads\Button::class),
			new ObjectMapper\Rules\BackedEnumValue(class: MetadataTypes\Payloads\Switcher::class),
			new ObjectMapper\Rules\BackedEnumValue(class: MetadataTypes\Payloads\Cover::class),
			new ObjectMapper\Rules\ObjectValue(),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		#[ObjectMapper\Modifiers\FieldName(DevicesStates\Property::EXPECTED_VALUE_FIELD)]
		private bool|float|int|string|DateTimeInterface|MetadataTypes\Payloads\Payload|null $expectedValue = null,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\DateTimeValue(format: DateTimeInterface::ATOM),
			new ObjectMapper\Rules\ObjectValue(),
			new ObjectMapper\Rules\BoolValue(),
		])]
		#[ObjectMapper\Modifiers\FieldName(DevicesStates\Property::PENDING_FIELD)]
		private bool|DateTimeInterface $pending = false,
		#[ObjectMapper\Rules\BoolValue(castBoolLike: true)]
		#[ObjectMapper\Modifiers\FieldName(DevicesStates\Property::VALID_FIELD)]
		private bool $valid = false,
	)
	{
	}

	public function getId(): Uuid\UuidInterface
	{
		return $this->id;
	}

	public function getActualValue(): bool|float|int|string|DateTimeInterface|MetadataTypes\Payloads\Payload|null
	{
		return $this->actualValue;
	}

	public function getExpectedValue(): bool|float|int|string|DateTimeInterface|MetadataTypes\Payloads\Payload|null
	{
		return $this->expectedValue;
	}

	public function isPending(): bool
	{
		return is_bool($this->pending) ? $this->pending : true;
	}

	public function getPending(): bool|DateTimeInterface
	{
		return $this->pending;
	}

	public function isValid(): bool
	{
		return $this->valid;
	}

	public function toArray(): array
	{
		return [
			DevicesStates\Property::ACTUAL_VALUE_FIELD => MetadataUtilities\Value::flattenValue(
				$this->getActualValue(),
			),
			DevicesStates\Property::EXPECTED_VALUE_FIELD => MetadataUtilities\Value::flattenValue(
				$this->getExpectedValue(),
			),
			DevicesStates\Property::PENDING_FIELD => $this->getPending() instanceof DateTimeInterface
				? $this->getPending()->format(DateTimeInterface::ATOM)
				: $this->getPending(),
			DevicesStates\Property::VALID_FIELD => $this->isValid(),
		];
	}

}
