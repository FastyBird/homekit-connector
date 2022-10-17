<?php declare(strict_types = 1);

/**
 * Representation.php
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

namespace FastyBird\Connector\HomeKit\Types;

use Consistence;
use function strval;

/**
 * HAP Representation type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Representation extends Consistence\Enum\Enum
{

	/**
	 * Define permissions identifiers
	 */
	public const REPR_ACCS = 'accessories';

	public const REPR_AID = 'aid';

	public const REPR_CHARS = 'characteristics';

	public const REPR_DESC = 'description';

	public const REPR_FORMAT = 'format';

	public const REPR_IID = 'iid';

	public const REPR_MAX_LEN = 'maxLen';

	public const REPR_PERM = 'perms';

	public const REPR_PID = 'pid';

	public const REPR_PRIMARY = 'primary';

	public const REPR_HIDDEN = 'hidden';

	public const REPR_SERVICES = 'services';

	public const REPR_LINKED = 'linked';

	public const REPR_STATUS = 'status';

	public const REPR_TTL = 'ttl';

	public const REPR_TYPE = 'type';

	public const REPR_VALUE = 'value';

	public const REPR_VALID_VALUES = 'valid-values';

	public const REPR_MAX_VALUE = 'maxValue';

	public const REPR_MIN_STEP = 'minStep';

	public const REPR_MIN_VALUE = 'minValue';

	public const REPR_UNIT = 'unit';

	public const REPR_META = 'meta';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
