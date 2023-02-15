<?php declare(strict_types = 1);

/**
 * ClientWriteCharacteristic.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Events
 * @since          1.0.0
 *
 * @date           01.10.22
 */

namespace FastyBird\Connector\HomeKit\Events;

use FastyBird\Connector\HomeKit\Entities;
use Symfony\Contracts\EventDispatcher;

/**
 * Apple client requested characteristic write
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Events
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ClientWriteCharacteristic extends EventDispatcher\Event
{

	public function __construct(
		private readonly Entities\Protocol\Characteristic $characteristic,
		private readonly bool|float|int|string|null $value,
	)
	{
	}

	public function getCharacteristic(): Entities\Protocol\Characteristic
	{
		return $this->characteristic;
	}

	public function getValue(): float|bool|int|string|null
	{
		return $this->value;
	}

}
