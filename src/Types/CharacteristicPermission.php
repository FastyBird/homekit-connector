<?php declare(strict_types = 1);

/**
 * CharacteristicPermission.php
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
use function strval;

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
	public const HIDDEN = 'hd';

	public const NOTIFY = 'ev';

	public const READ = 'pr';

	public const WRITE = 'pw';

	public const WRITE_RESPONSE = 'wr';

	public const TIMED_WRITE = 'tw';

	public const ADDITIONAL_AUTHORIZATION = 'aa';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
