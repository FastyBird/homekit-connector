<?php declare(strict_types = 1);

/**
 * TlvError.php
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

/**
 * TLV error value types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum TlvError: int
{

	case UNKNOWN = 0x01; // generic error to handle unexpected errors

	case AUTHENTICATION = 0x02; // setup code or signature verification failed

	case BACKOFF = 0x03; // client must look at the retry delay tlv item and wait that many seconds before retrying

	case MAX_PEERS = 0x04; // server cannot accept any more pairings

	case MAX_TRIES = 0x05; // server reached its maximum number of authentication attempts

	case UNAVAILABLE = 0x06; // server pairing method is unavailable

	case BUSY = 0x07; // server busy and cannot accept pairing request at this time

}
