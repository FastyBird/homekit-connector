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

	/** @var Entities\Protocol\Characteristic */
	private Entities\Protocol\Characteristic $characteristic;

	/** @var bool|float|int|string|null */
	private bool|float|int|string|null $value;

	/**
	 * @param Entities\Protocol\Characteristic $characteristic
	 * @param bool|float|int|string|null $value
	 */
	public function __construct(
		Entities\Protocol\Characteristic $characteristic,
		bool|float|int|string|null $value
	) {
		$this->characteristic = $characteristic;
		$this->value = $value;
	}

	/**
	 * @return Entities\Protocol\Characteristic
	 */
	public function getCharacteristic(): Entities\Protocol\Characteristic
	{
		return $this->characteristic;
	}

	/**
	 * @return bool|float|int|string|null
	 */
	public function getValue(): float|bool|int|string|null
	{
		return $this->value;
	}

}
