<?php declare(strict_types = 1);

/**
 * StoreChannelPropertyState.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           30.11.23
 */

namespace FastyBird\Connector\HomeKit\Entities\Messages;

use FastyBird\Library\Bootstrap\ObjectMapper as BootstrapObjectMapper;
use Orisai\ObjectMapper;
use Ramsey\Uuid;
use function is_string;

/**
 * Device status message entity
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class StoreChannelPropertyState implements Entity
{

	public function __construct(
		#[BootstrapObjectMapper\Rules\UuidValue()]
		private readonly Uuid\UuidInterface $connector,
		#[BootstrapObjectMapper\Rules\UuidValue()]
		private readonly Uuid\UuidInterface $device,
		#[BootstrapObjectMapper\Rules\UuidValue()]
		private readonly Uuid\UuidInterface $channel,
		#[ObjectMapper\Rules\AnyOf([
			new BootstrapObjectMapper\Rules\UuidValue(),
			new ObjectMapper\Rules\StringValue(notEmpty: true),
		])]
		private readonly Uuid\UuidInterface|string $property,
		#[ObjectMapper\Rules\AnyOf([
			new ObjectMapper\Rules\FloatValue(),
			new ObjectMapper\Rules\IntValue(),
			new ObjectMapper\Rules\StringValue(notEmpty: true),
			new ObjectMapper\Rules\BoolValue(),
			new ObjectMapper\Rules\NullValue(castEmptyString: true),
		])]
		private readonly float|int|string|bool|null $value,
	)
	{
	}

	public function getConnector(): Uuid\UuidInterface
	{
		return $this->connector;
	}

	public function getDevice(): Uuid\UuidInterface
	{
		return $this->device;
	}

	public function getChannel(): Uuid\UuidInterface
	{
		return $this->channel;
	}

	public function getProperty(): Uuid\UuidInterface|string
	{
		if (is_string($this->property) && Uuid\Uuid::isValid($this->property)) {
			return Uuid\Uuid::fromString($this->property);
		}

		return $this->property;
	}

	public function getValue(): float|bool|int|string|null
	{
		return $this->value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'connector' => $this->getConnector()->toString(),
			'device' => $this->getDevice()->toString(),
			'channel' => $this->getChannel()->toString(),
			'property' => is_string($this->getProperty()) ? $this->getProperty() : $this->getProperty()->toString(),
			'value' => $this->getValue(),
		];
	}

}
