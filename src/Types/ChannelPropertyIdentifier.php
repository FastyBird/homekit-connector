<?php declare(strict_types = 1);

/**
 * ChannelPropertyIdentifier.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           06.10.22
 */

namespace FastyBird\Connector\HomeKit\Types;

use Consistence;
use function strval;

/**
 * Channel property identifier types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ChannelPropertyIdentifier extends Consistence\Enum\Enum
{

	/**
	 * Define channel properties identifiers
	 */
	public const VERSION = 'version';

	public const NAME = 'name';

	public const SERIAL_NUMBER = 'serial_number';

	public const FIRMWARE_REVISION = 'firmware_revision';

	public const MANUFACTURER = 'manufacturer';

	public const MODEL = 'model';

	public const IDENTIFY = 'identify';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
