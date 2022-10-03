<?php declare(strict_types = 1);

/**
 * Protocol.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\HomeKitConnector\Helpers;

use FastyBird\HomeKitConnector;
use FastyBird\HomeKitConnector\Exceptions;
use Nette\Utils;
use Ramsey\Uuid;
use Socket;
use Throwable;

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
	 *
	 * @param Uuid\UuidInterface $uuid
	 *
	 * @return string
	 */
	public static function uuidToHapType(Uuid\UuidInterface $uuid): string
	{
		$longType = Utils\Strings::upper($uuid->toString());

		if (!Utils\Strings::endsWith($longType, HomeKitConnector\Constants::BASE_UUID)) {
			return $longType;
		}

		return Utils\Strings::trim((explode('-', $uuid->toString(), 2) + [''])[0], '0');
	}

	/**
	 * Convert a HAP type to a UUID
	 *
	 * @param string $type
	 *
	 * @return Uuid\UuidInterface
	 */
	public static function hapTypeToUuid(string $type): Uuid\UuidInterface
	{
		if (Utils\Strings::contains($type, '-')) {
			return Uuid\Uuid::fromString($type);
		}

		return Uuid\Uuid::fromString(\str_repeat('0', 8 - strlen($type)) . $type . HomeKitConnector\Constants::BASE_UUID);
	}

	/**
	 * @return string|null
	 */
	public static function getLocalAddress(): ?string
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
	 * @return string
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

	/**
	 * @return string
	 */
	public static function generatePinCode(): string
	{
		return rand(pow(10, 2), pow(10, 3) - 1)
			. '-' . rand(pow(10, 1), pow(10, 2) - 1)
			. '-' . rand(pow(10, 2), pow(10, 3) - 1);
	}

	/**
	 * @return string
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

	/**
	 * @return string
	 */
	public static function generateMacAddress(): string
	{
		$allowedValues = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F'];

		$mac = [];

		while (\count($mac) < 7) {
			shuffle($allowedValues);

			$mac[] = $allowedValues[0] . $allowedValues[1];
		}

		return \implode(':', $mac);
	}

}
