<?php declare(strict_types = 1);

/**
 * ClientPermission.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Types;

use Consistence;
use function intval;
use function strval;

/**
 * HAP accessory permissions type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ClientPermission extends Consistence\Enum\Enum
{

	/**
	 * Define permissions identifiers
	 */
	public const USER = 0;

	public const ADMIN = 1;

	public function getValue(): int
	{
		return intval(parent::getValue());
	}

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
