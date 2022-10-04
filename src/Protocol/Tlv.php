<?php declare(strict_types = 1);

/**
 * Tlv.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 * @since          0.19.0
 *
 * @date           20.09.22
 */

namespace FastyBird\HomeKitConnector\Protocol;

use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Types;
use Nette;
use function array_map;
use function array_merge;
use function array_pop;
use function array_sum;
use function array_values;
use function chr;
use function count;
use function implode;
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
	 * @param Array<int, Array<int, (int|Array<int>|string)>> $objects
	 *
	 * @return string
	 */
	public function encode(array $objects): string
	{
		$data = [];

		$cnt = 0;

		foreach ($objects as $entry) {
			if ($cnt > 0) {
				$row = pack('C1', Types\TlvCode::CODE_SEPARATOR);
				$row .= pack('C1', 0); // Length of separator is 0

				$data[] = $row;
			}

			foreach ($entry as $code => $value) {
				$row = pack('C1', $code);

				if (!Types\TlvCode::isValidValue($code)) {
					throw new Exceptions\InvalidArgument('Provided TLV code in data is not valid');
				}

				$tlvCode = Types\TlvCode::get($code);

				if (
					(
						$tlvCode->equalsValue(Types\TlvCode::CODE_METHOD)
						|| $tlvCode->equalsValue(Types\TlvCode::CODE_STATE)
						|| $tlvCode->equalsValue(Types\TlvCode::CODE_ERROR)
						|| $tlvCode->equalsValue(Types\TlvCode::CODE_RETRY_DELAY)
						|| $tlvCode->equalsValue(Types\TlvCode::CODE_PERMISSIONS)
					) && is_numeric($value)
				) {
					$row .= pack('C1', 1); // Length of integer, only short (1-byte length) integers is supported
					$row .= pack('C1', $value);

				} elseif (
					$tlvCode->equalsValue(Types\TlvCode::CODE_IDENTIFIER)
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
						$tlvCode->equalsValue(Types\TlvCode::CODE_SALT)
						|| $tlvCode->equalsValue(Types\TlvCode::CODE_PUBLIC_KEY)
						|| $tlvCode->equalsValue(Types\TlvCode::CODE_PROOF)
						|| $tlvCode->equalsValue(Types\TlvCode::CODE_ENCRYPTED_DATA)
						|| $tlvCode->equalsValue(Types\TlvCode::CODE_CERTIFICATE)
						|| $tlvCode->equalsValue(Types\TlvCode::CODE_SIGNATURE)
						|| $tlvCode->equalsValue(Types\TlvCode::CODE_FRAGMENT_DATA)
						|| $tlvCode->equalsValue(Types\TlvCode::CODE_FRAGMENT_LAST)
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
	 * @param string $data
	 *
	 * @return Array<int, Array<int, (int|Array<int>|string)>>
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

			if (!Types\TlvCode::isValidValue($tag)) {
				throw new Exceptions\InvalidArgument('Provided TLV code in data is not valid');
			}

			$tlvCode = Types\TlvCode::get($tag);

			if ($tlvCode->equalsValue(Types\TlvCode::CODE_SEPARATOR)) {
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
				$tlvCode->equalsValue(Types\TlvCode::CODE_METHOD)
				|| $tlvCode->equalsValue(Types\TlvCode::CODE_STATE)
				|| $tlvCode->equalsValue(Types\TlvCode::CODE_ERROR)
				|| $tlvCode->equalsValue(Types\TlvCode::CODE_RETRY_DELAY)
				|| $tlvCode->equalsValue(Types\TlvCode::CODE_PERMISSIONS)
			) {
				if ($length !== 1) {
					throw new Exceptions\InvalidArgument('Only short (1-byte length) integers is supported');
				}

				// Int value
				$value = unpack('C' . $length, substr($data, $position, $length));

				if ($value === false) {
					throw new Exceptions\InvalidArgument('Provided data are not valid TLV data');
				} else {
					$value = array_sum($value);
				}
			} elseif ($tlvCode->equalsValue(Types\TlvCode::CODE_IDENTIFIER)) {
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
				$tlvCode->equalsValue(Types\TlvCode::CODE_SALT)
				|| $tlvCode->equalsValue(Types\TlvCode::CODE_PUBLIC_KEY)
				|| $tlvCode->equalsValue(Types\TlvCode::CODE_PROOF)
				|| $tlvCode->equalsValue(Types\TlvCode::CODE_ENCRYPTED_DATA)
				|| $tlvCode->equalsValue(Types\TlvCode::CODE_CERTIFICATE)
				|| $tlvCode->equalsValue(Types\TlvCode::CODE_SIGNATURE)
				|| $tlvCode->equalsValue(Types\TlvCode::CODE_FRAGMENT_DATA)
				|| $tlvCode->equalsValue(Types\TlvCode::CODE_FRAGMENT_LAST)
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

			if ($previousCode !== null && $previousCode->equals($tlvCode)) {
				$entry[intval($tlvCode->getValue())] = is_array($entry[$tlvCode->getValue()]) && is_array($value)
					? array_merge(
						$entry[$tlvCode->getValue()],
						$value,
					)
					: $entry[$tlvCode->getValue()] + $value;
			} else {
				$entry[intval($tlvCode->getValue())] = $value;
			}

			$previousCode = $tlvCode;
		}

		$objects[] = $entry;

		return $objects;
	}

}
