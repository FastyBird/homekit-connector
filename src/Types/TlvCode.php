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

use Consistence;
use function intval;
use function strval;

/**
 * TLV tag code types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class TlvCode extends Consistence\Enum\Enum
{

	/**
	 * Define codes
	 */
	public const METHOD = 0x00; // method to use for pairing

	public const IDENTIFIER = 0x01; // identifier for authentication

	public const SALT = 0x02; // 16+ bytes of random salt

	public const PUBLIC_KEY = 0x03; // curve25519, srp public key or signed ed25519 key

	public const PROOF = 0x04; // ed25519 or srp proof

	public const ENCRYPTED_DATA = 0x05; // encrypted data with auth tag at end

	public const STATE = 0x06; // state of the pairing process

	public const ERROR = 0x07; // error code, must only be present if error code is not 0

	public const RETRY_DELAY = 0x08; // seconds to delay until retrying a setup code

	public const CERTIFICATE = 0x09; // x.509 certificate

	public const SIGNATURE = 0x0a; // ed25519

	public const PERMISSIONS = 0x0b; // bit value describing permissions of the controller being added, 0 - regular user, 1 - admin

	public const FRAGMENT_DATA = 0x0c; // non-last fragment of data, if length is 0, it is ack

	public const FRAGMENT_LAST = 0x0d; // last fragment data

	public const SEPARATOR = 0xff; // zero-length tlv that separates different tlvs in a list

	public function getValue(): int
	{
		return intval(parent::getValue());
	}

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
