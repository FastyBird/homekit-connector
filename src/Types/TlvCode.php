<?php declare(strict_types = 1);

/**
 * TlvCode.php
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
 * TLV tag code types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum TlvCode: int
{

	case METHOD = 0x00; // method to use for pairing

	case IDENTIFIER = 0x01; // identifier for authentication

	case SALT = 0x02; // 16+ bytes of random salt

	case PUBLIC_KEY = 0x03; // curve25519, srp public key or signed ed25519 key

	case PROOF = 0x04; // ed25519 or srp proof

	case ENCRYPTED_DATA = 0x05; // encrypted data with auth tag at end

	case STATE = 0x06; // state of the pairing process

	case ERROR = 0x07; // error code, must only be present if error code is not 0

	case RETRY_DELAY = 0x08; // seconds to delay until retrying a setup code

	case CERTIFICATE = 0x09; // x.509 certificate

	case SIGNATURE = 0x0a; // ed25519

	case PERMISSIONS = 0x0b; // bit value describing permissions of the controller being added, 0 - regular user, 1 - admin

	case FRAGMENT_DATA = 0x0c; // non-last fragment of data, if length is 0, it is ack

	case FRAGMENT_LAST = 0x0d; // last fragment data

	case SEPARATOR = 0xff; // zero-length tlv that separates different tlvs in a list

}
