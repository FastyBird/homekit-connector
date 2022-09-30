<?php declare(strict_types = 1);

/**
 * CharacteristicPermission.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\HomeKitConnector\Types;

use Consistence;

/**
 * HAP accessory permissions type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class CharacteristicPermission extends Consistence\Enum\Enum
{

	/**
	 * Define permissions identifiers
	 */
	public const PERMISSION_HIDDEN = 'hd';
	public const PERMISSION_NOTIFY = 'ev';
	public const PERMISSION_READ = 'pr';
	public const PERMISSION_WRITE = 'pw';
	public const PERMISSION_WRITE_RESPONSE = 'wr';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
