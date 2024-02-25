<?php declare(strict_types = 1);

/**
 * Tlv.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 * @since          1.0.0
 *
 * @date           20.09.22
 */

namespace FastyBird\Connector\HomeKit\Protocol;

use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Types;
use Nette;
use TypeError;
use ValueError;
use function array_map;
use function array_merge;
use function array_pop;
use function array_sum;
use function array_values;
use function chr;
use function count;
use function implode;
use function in_array;
use function intval;
use function is_array;
use function is_numeric;
use function is_string;
use function mb_check_encoding;
use function ord;
use function pack;
use function str_split;
use function strlen;
use function substr;
use function unpack;

/**
 * Apple TLV8 utilities
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Tlv
{

	use Nette\SmartObject;

	/**
	 * @param array<int, array<int, (int|array<int>|string)>> $objects
	 *
	 * @throws Exceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function encode(array $objects): string
	{
		$data = [];

		$cnt = 0;

		foreach ($objects as $entry) {
			if ($cnt > 0) {
				$row = pack('C1', Types\TlvCode::SEPARATOR->value);
				$row .= pack('C1', 0); // Length of separator is 0

				$data[] = $row;
			}

			foreach ($entry as $code => $value) {
				$row = pack('C1', $code);

				if (Types\TlvCode::tryFrom($code) === null) {
					throw new Exceptions\InvalidArgument('Provided TLV code in data is not valid');
				}

				$tlvCode = Types\TlvCode::from($code);

				if (
					(
						$tlvCode === Types\TlvCode::METHOD
						|| $tlvCode === Types\TlvCode::STATE
						|| $tlvCode === Types\TlvCode::ERROR
						|| $tlvCode === Types\TlvCode::RETRY_DELAY
						|| $tlvCode === Types\TlvCode::PERMISSIONS
					) && is_numeric($value)
				) {
					$row .= pack('C1', 1); // Length of integer, only short (1-byte length) integers is supported
					$row .= pack('C1', $value);

				} elseif (
					$tlvCode === Types\TlvCode::IDENTIFIER
					&& is_string($value)
				) {
					$chars = array_map(static fn (string $char): int => ord($char), str_split($value));

					if (count($chars) > 255) {
						$chars = array_values($chars);

						for ($i = 0; $i < count($chars); $i++) {
							if ($i === 0) {
								$row .= pack('C1', 255);
							} elseif ($i % 255 === 0) {
								$row .= pack('C1', $code);
								$row .= pack('C1', count($chars) - $i);
							}

							$row .= pack('C1', $chars[$i]);
						}
					} else {
						$row .= pack('C1', count($chars));
						$row .= pack('C*', ...$chars);
					}
				} elseif (
					(
						$tlvCode === Types\TlvCode::SALT
						|| $tlvCode === Types\TlvCode::PUBLIC_KEY
						|| $tlvCode === Types\TlvCode::PROOF
						|| $tlvCode === Types\TlvCode::ENCRYPTED_DATA
						|| $tlvCode === Types\TlvCode::CERTIFICATE
						|| $tlvCode === Types\TlvCode::SIGNATURE
						|| $tlvCode === Types\TlvCode::FRAGMENT_DATA
						|| $tlvCode === Types\TlvCode::FRAGMENT_LAST
					) && is_array($value)
				) {
					if (count($value) > 255) {
						$value = array_values($value);

						for ($i = 0; $i < count($value); $i++) {
							if ($i === 0) {
								$row .= pack('C1', 255);
							} elseif ($i % 255 === 0) {
								$row .= pack('C1', $code);
								$row .= pack('C1', count($value) - $i);
							}

							$row .= pack('C1', $value[$i]);
						}
					} else {
						$row .= pack('C1', count($value));
						$row .= pack('C*', ...$value);
					}
				} else {
					continue;
				}

				$data[] = $row;
			}

			$cnt++;
		}

		return implode('', $data);
	}

	/**
	 * @return array<int, array<int, (int|array<int>|string|null)>>
	 *
	 * @throws Exceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function decode(string $data): array
	{
		$objects = [];
		$entry = [];

		$position = 0;

		$previousCode = null;

		while ($position < strlen($data)) {
			$tag = unpack('C1', substr($data, $position, 1));

			$position++;

			if ($tag === false) {
				throw new Exceptions\InvalidArgument('Provided data are not valid TLV data');
			}

			$tag = (int) array_pop($tag);

			if (Types\TlvCode::tryFrom($tag) === null) {
				throw new Exceptions\InvalidArgument('Provided TLV code in data is not valid');
			}

			$tlvCode = Types\TlvCode::from($tag);

			if ($tlvCode === Types\TlvCode::SEPARATOR) {
				$objects[] = $entry;
				$entry = [];

				$position++; // Skip separator length info

				continue;
			}

			$length = unpack('C1', substr($data, $position, 1));

			$position++;

			if ($length === false) {
				throw new Exceptions\InvalidArgument('Provided data are not valid TLV data');
			}

			$length = (int) array_pop($length);

			$value = null;

			if (
				$tlvCode === Types\TlvCode::METHOD
				|| $tlvCode === Types\TlvCode::STATE
				|| $tlvCode === Types\TlvCode::ERROR
				|| $tlvCode === Types\TlvCode::RETRY_DELAY
				|| $tlvCode === Types\TlvCode::PERMISSIONS
			) {
				if ($length !== 1) {
					throw new Exceptions\InvalidArgument('Only short (1-byte length) integers is supported');
				}

				// Int value
				$value = unpack('C' . $length, substr($data, $position, $length));

				if ($value === false) {
					throw new Exceptions\InvalidArgument('Provided data are not valid TLV data');
				} else {
					$value = intval(array_sum($value));
				}
			} elseif ($tlvCode === Types\TlvCode::IDENTIFIER) {
				// Str value
				$value = unpack('C' . $length, substr($data, $position, $length));

				if ($value === false) {
					throw new Exceptions\InvalidArgument('Provided data are not valid TLV data');
				} else {
					$value = implode(
						'',
						array_map(
							static fn (int $item): string => mb_check_encoding(chr($item), 'UTF-8') ? chr($item) : '',
							$value,
						),
					);

					if ($value === '') {
						throw new Exceptions\InvalidArgument('Unable to decode string from bytes');
					}
				}
			} elseif (
				in_array(
					$tlvCode,
					[
						Types\TlvCode::SALT,
						Types\TlvCode::PUBLIC_KEY,
						Types\TlvCode::PROOF,
						Types\TlvCode::ENCRYPTED_DATA,
						Types\TlvCode::CERTIFICATE,
						Types\TlvCode::SIGNATURE,
						Types\TlvCode::FRAGMENT_DATA,
						Types\TlvCode::FRAGMENT_LAST,
					],
					true,
				)
			) {
				// Bytes value
				$value = unpack('C' . $length, substr($data, $position, $length));

				if ($value === false) {
					throw new Exceptions\InvalidArgument('Provided data are not valid TLV data');
				} else {
					$value = array_values($value);
				}
			}

			$position += $length;

			if ($previousCode !== null && $previousCode === $tlvCode) {
				$entry[$tlvCode->value] = is_array($entry[$tlvCode->value])
					? array_merge(
						$entry[$tlvCode->value],
						is_array($value) ? $value : [intval($value)],
					)
					: $entry[$tlvCode->value] . (is_array($value) ? implode('', $value) : $value);
			} else {
				$entry[$tlvCode->value] = $value;
			}

			$previousCode = $tlvCode;
		}

		$objects[] = $entry;

		return $objects;
	}

}
