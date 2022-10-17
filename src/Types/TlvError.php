<?php declare(strict_types = 1);

/**
 * TlvError.php
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

namespace FastyBird\Connector\HomeKit\Types;

use Consistence;
use function strval;

/**
 * TLV error value types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class TlvError extends Consistence\Enum\Enum
{

	/**
	 * Define errors
	 */
	public const ERROR_UNKNOWN = 0x01; // generic error to handle unexpected errors

	public const ERROR_AUTHENTICATION = 0x02; // setup code or signature verification failed

	public const ERROR_BACKOFF = 0x03; // client must look at the retry delay tlv item and wait that many seconds before retrying

	public const ERROR_MAX_PEERS = 0x04; // server cannot accept any more pairings

	public const ERROR_MAX_TRIES = 0x05; // server reached its maximum number of authentication attempts

	public const ERROR_UNAVAILABLE = 0x06; // server pairing method is unavailable

	public const ERROR_BUSY = 0x07; // server busy and cannot accept pairing request at this time

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
