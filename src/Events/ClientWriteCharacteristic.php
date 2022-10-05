<?php declare(strict_types = 1);

/**
 * ClientWriteCharacteristic.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Events
 * @since          0.19.0
 *
 * @date           01.10.22
 */

namespace FastyBird\HomeKitConnector\Events;

use FastyBird\HomeKitConnector\Entities;
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

	/**
	 * @param Entities\Protocol\Characteristic $characteristic
	 * @param bool|float|int|string|null $value
	 */
	public function __construct(
		private Entities\Protocol\Characteristic $characteristic,
		private bool|float|int|string|null $value,
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
