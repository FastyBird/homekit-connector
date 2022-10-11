<?php declare(strict_types = 1);

/**
 * TlvState.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          0.19.0
 *
 * @date           20.09.22
 */

namespace FastyBird\HomeKitConnector\Types;

use Consistence;
use function strval;

/**
 * TLV state value types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class TlvState extends Consistence\Enum\Enum
{

	/**
	 * Define states
	 */
	public const STATE_M1 = 0x01;

	public const STATE_M2 = 0x02;

	public const STATE_M3 = 0x03;

	public const STATE_M4 = 0x04;

	public const STATE_M5 = 0x05;

	public const STATE_M6 = 0x06;

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
