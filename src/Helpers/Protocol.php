<?php declare(strict_types = 1);

/**
 * Protocol.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 * @since          1.0.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Helpers;

use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Exceptions;
use Nette\Utils;
use Ramsey\Uuid;
use Socket;
use Throwable;
use function base_convert;
use function bin2hex;
use function count;
use function explode;
use function implode;
use function intval;
use function ltrim;
use function pow;
use function rand;
use function random_bytes;
use function shuffle;
use function socket_close;
use function socket_connect;
use function socket_create;
use function socket_getsockname;
use function str_repeat;
use function str_replace;
use function strlen;
use function strval;
use const AF_INET;
use const SOCK_DGRAM;
use const SOL_UDP;

/**
 * HAP protocol utilities
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Protocol
{

	/**
	 * Convert a UUID to a HAP type
	 */
	public static function uuidToHapType(Uuid\UuidInterface $uuid): string
	{
		$longType = Utils\Strings::upper($uuid->toString());

		if (!Utils\Strings::endsWith($longType, HomeKit\Constants::BASE_UUID)) {
			return $longType;
		}

		$parts = explode('-', $uuid->toString(), 2) + [''];

		return ltrim($parts[0], '0');
	}

	/**
	 * Convert a HAP type to a UUID
	 */
	public static function hapTypeToUuid(string $type): Uuid\UuidInterface
	{
		if (Utils\Strings::contains($type, '-')) {
			return Uuid\Uuid::fromString($type);
		}

		return Uuid\Uuid::fromString(
			str_repeat('0', 8 - strlen($type)) . $type . HomeKit\Constants::BASE_UUID,
		);
	}

	public static function getXhmUri(
		string $pinCode,
		string $setupId,
		HomeKit\Types\AccessoryCategory $category,
	): string
	{
		$payload = 0;

		$payload |= 0 & 0x7; // version
		$payload <<= 4;
		$payload |= 0 & 0xF; // reserved bits

		$payload <<= 8;
		$payload |= $category->value & 0xFF; // category

		$payload <<= 4;
		$payload |= 2 & 0xF; // flags

		$payload <<= 27;
		$payload |= (intval(str_replace('-', '', $pinCode)) & 0x7FFFFFFF); // pin code

		$encodedPayload = Utils\Strings::upper(base_convert(strval($payload), 10, 36));
		$encodedPayload = Utils\Strings::substring(str_repeat('0', 9) . $encodedPayload, -9);

		return 'X-HM://' . $encodedPayload . $setupId;
	}

	public static function getLocalAddress(): string|null
	{
		$address = $sock = null;

		try {
			$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

			if ($sock === false) {
				return null;
			}

			socket_connect($sock, '8.8.8.8', 53);
			socket_getsockname($sock, $address);

		} catch (Throwable) {
			return null;
		} finally {
			if ($sock instanceof Socket) {
				socket_close($sock);
			}
		}

		return $address;
	}

	/**
	 * @throws Exceptions\InvalidState
	 */
	public static function generateSetupId(): string
	{
		try {
			$bytes = random_bytes(2);
		} catch (Throwable) {
			throw new Exceptions\InvalidState('Setup ID could not be generated');
		}

		return bin2hex($bytes);
	}

	public static function generatePinCode(): string
	{
		return rand(pow(10, 2), pow(10, 3) - 1)
			. '-' . rand(pow(10, 1), pow(10, 2) - 1)
			. '-' . rand(pow(10, 2), pow(10, 3) - 1);
	}

	/**
	 * @throws Exceptions\InvalidState
	 */
	public static function generateSignKey(): string
	{
		try {
			$bytes = random_bytes(32);
		} catch (Throwable) {
			throw new Exceptions\InvalidState('Sign key could not be generated');
		}

		return bin2hex($bytes);
	}

	public static function generateMacAddress(): string
	{
		$allowedValues = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F'];

		$mac = [];

		while (count($mac) < 7) {
			shuffle($allowedValues);

			$mac[] = $allowedValues[0] . $allowedValues[1];
		}

		return implode(':', $mac);
	}

}
