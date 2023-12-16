<?php declare(strict_types = 1);

/**
 * TlvMethod.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           20.09.22
 */

namespace FastyBird\Connector\HomeKit\Types;

use Consistence;
use function intval;
use function strval;

/**
 * TLV method value types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class TlvMethod extends Consistence\Enum\Enum
{

	/**
	 * Define methods
	 */
	public const RESERVED = 0;

	public const PAIR_SETUP = 1;

	public const PAIR_VERIFY = 2;

	public const ADD_PAIRING = 3;

	public const REMOVE_PAIRING = 4;

	public const LIST_PAIRINGS = 5;

	public function getValue(): int
	{
		return intval(parent::getValue());
	}

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
