<?php declare(strict_types = 1);

/**
 * Transformer.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 * @since          1.0.0
 *
 * @date           01.10.22
 */

namespace FastyBird\Connector\HomeKit\Protocol;

use DateTimeInterface;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Core\Tools\Exceptions as ToolsExceptions;
use FastyBird\Core\Tools\Formats as ToolsFormats;
use FastyBird\Core\Tools\Utilities as ToolsUtilities;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use Nette\Utils;
use TypeError;
use ValueError;
use function array_filter;
use function array_values;
use function count;
use function in_array;
use function intval;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_scalar;
use function max;
use function min;
use function preg_replace;
use function round;
use function str_replace;
use function strlen;
use function strval;
use function substr;

/**
 * Value transformers
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Transformer
{

	/**
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public static function fromClient(
		DevicesDocuments\Channels\Properties\Property|null $property,
		Types\DataType $dataType,
		bool|float|int|string|null $value,
	): bool|float|int|string|MetadataTypes\Payloads\Payload|null
	{
		$transformedValue = null;

		// HAP transformation

		if ($dataType === Types\DataType::BOOLEAN) {
			if ($value === null) {
				$transformedValue = false;
			} elseif (!is_bool($value)) {
				$transformedValue = in_array(Utils\Strings::lower(strval($value)), [
					'true',
					't',
					'yes',
					'y',
					'1',
					'on',
				], true);
			} else {
				$transformedValue = $value;
			}
		} elseif ($dataType === Types\DataType::FLOAT) {
			if (is_float($value)) {
				$transformedValue = $value;
			} elseif (is_numeric($value)) {
				$transformedValue = (float) $value;
			} else {
				$transformedValue = str_replace([' ', ','], ['', '.'], (string) $value);

				if (!is_numeric($transformedValue)) {
					$transformedValue = 0.0;
				}

				$transformedValue = (float) $transformedValue;
			}
		} elseif (
			$dataType === Types\DataType::INT
			|| $dataType === Types\DataType::UINT8
			|| $dataType === Types\DataType::UINT16
			|| $dataType === Types\DataType::UINT32
			|| $dataType === Types\DataType::UINT64
		) {
			if (is_int($value)) {
				$transformedValue = $value;
			} elseif (is_numeric($value) && strval($value) === strval((int) $value)) {
				$transformedValue = (int) $value;
			} else {
				$transformedValue = preg_replace('~\s~', '', (string) $value);
				$transformedValue = (int) $transformedValue;
			}
		} elseif ($dataType === Types\DataType::STRING) {
			$transformedValue = strval($value);
		}

		// Connector transformation

		if ($transformedValue === null) {
			return null;
		}

		if ($property === null) {
			return $transformedValue;
		}

		if (
			$property->getDataType() === MetadataTypes\DataType::ENUM
			|| $property->getDataType() === MetadataTypes\DataType::SWITCH
			|| $property->getDataType() === MetadataTypes\DataType::COVER
			|| $property->getDataType() === MetadataTypes\DataType::BUTTON
		) {
			if ($property->getFormat() instanceof ToolsFormats\StringEnum) {
				$filtered = array_values(array_filter(
					$property->getFormat()->getItems(),
					static fn (string $item): bool => Utils\Strings::lower(strval($transformedValue)) === $item,
				));

				if (count($filtered) === 1) {
					if ($property->getDataType() === MetadataTypes\DataType::SWITCH) {
						return MetadataTypes\Payloads\Switcher::from(strval($transformedValue));
					} elseif ($property->getDataType() === MetadataTypes\DataType::BUTTON) {
						return MetadataTypes\Payloads\Button::from(strval($transformedValue));
					} elseif ($property->getDataType() === MetadataTypes\DataType::COVER) {
						return MetadataTypes\Payloads\Cover::from(strval($transformedValue));
					} else {
						return strval($transformedValue);
					}
				}

				return null;
			} elseif ($property->getFormat() instanceof ToolsFormats\CombinedEnum) {
				$filtered = array_values(array_filter(
					$property->getFormat()->getItems(),
					static fn (array $item): bool => $item[1] !== null
						&& Utils\Strings::lower(ToolsUtilities\Value::toString($item[1]->getValue(), true))
							=== Utils\Strings::lower(strval($transformedValue)),
				));

				if (
					count($filtered) === 1
					&& $filtered[0][0] instanceof ToolsFormats\CombinedEnumItem
				) {
					if ($property->getDataType() === MetadataTypes\DataType::SWITCH) {
						return MetadataTypes\Payloads\Switcher::from(
							ToolsUtilities\Value::toString($filtered[0][0]->getValue(), true),
						);
					} elseif ($property->getDataType() === MetadataTypes\DataType::BUTTON) {
						return MetadataTypes\Payloads\Button::from(
							ToolsUtilities\Value::toString($filtered[0][0]->getValue(), true),
						);
					} elseif ($property->getDataType() === MetadataTypes\DataType::COVER) {
						return MetadataTypes\Payloads\Cover::from(
							ToolsUtilities\Value::toString($filtered[0][0]->getValue(), true),
						);
					} else {
						return ToolsUtilities\Value::toString($filtered[0][0]->getValue(), true);
					}
				}

				return null;
			}
		}

		return $transformedValue;
	}

	/**
	 * @param array<int>|null $validValues
	 *
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public static function toClient(
		DevicesDocuments\Channels\Properties\Property|null $property,
		Types\DataType $dataType,
		array|null $validValues,
		int|null $maxLength,
		float|null $minValue,
		float|null $maxValue,
		float|null $minStep,
		bool|float|int|string|DateTimeInterface|MetadataTypes\Payloads\Payload|null $value,
	): bool|float|int|string|null
	{
		$transformedValue = null;

		// Connector transformation

		if ($property !== null) {
			if (
				$property->getDataType() === MetadataTypes\DataType::ENUM
				|| $property->getDataType() === MetadataTypes\DataType::SWITCH
				|| $property->getDataType() === MetadataTypes\DataType::COVER
				|| $property->getDataType() === MetadataTypes\DataType::BUTTON
			) {
				if ($property->getFormat() instanceof ToolsFormats\StringEnum) {
					$filtered = array_values(array_filter(
						$property->getFormat()->getItems(),
						static fn (string $item): bool => ($value !== null ? Utils\Strings::lower(
							ToolsUtilities\Value::toString($value, true),
						) : null) === $item,
					));

					if (count($filtered) === 1) {
						$transformedValue = ToolsUtilities\Value::flattenValue($value);
					}
				} elseif ($property->getFormat() instanceof ToolsFormats\CombinedEnum) {
					$filtered = array_values(array_filter(
						$property->getFormat()->getItems(),
						static fn (array $item): bool => $item[0] !== null
							&& Utils\Strings::lower(
								ToolsUtilities\Value::toString($item[0]->getValue(), true),
							) === ($value !== null ? Utils\Strings::lower(
								ToolsUtilities\Value::toString($value, true),
							) : null),
					));

					if (
						count($filtered) === 1
						&& $filtered[0][2] instanceof ToolsFormats\CombinedEnumItem
					) {
						$transformedValue = is_scalar($filtered[0][2]->getValue())
							? $filtered[0][2]->getValue()
							: ToolsUtilities\Value::flattenValue($filtered[0][2]->getValue());
					}
				} else {
					if (
						(
							$property->getDataType() === MetadataTypes\DataType::SWITCH
							&& $value instanceof MetadataTypes\Payloads\Switcher
						) || (
							$property->getDataType() === MetadataTypes\DataType::BUTTON
							&& $value instanceof MetadataTypes\Payloads\Button
						) || (
							$property->getDataType() === MetadataTypes\DataType::COVER
							&& $value instanceof MetadataTypes\Payloads\Cover
						)
					) {
						$transformedValue = $value->value;
					}
				}
			} else {
				$transformedValue = $value;
			}
		} else {
			$transformedValue = $value;
		}

		// HAP transformation

		if ($dataType === Types\DataType::BOOLEAN) {
			if ($transformedValue === null) {
				$transformedValue = false;
			} elseif (!is_bool($transformedValue)) {
				$transformedValue = in_array(
					Utils\Strings::lower(ToolsUtilities\Value::toString($transformedValue, true)),
					[
						'true',
						't',
						'yes',
						'y',
						'1',
						'on',
					],
					true,
				);
			}
		} elseif ($dataType === Types\DataType::FLOAT) {
			if ($transformedValue === null) {
				$transformedValue = 0.0;
			} elseif (!is_numeric($transformedValue)) {
				$transformedValue = str_replace(
					[' ', ','],
					['', '.'],
					ToolsUtilities\Value::toString($transformedValue, true),
				);

				if (!is_numeric($transformedValue)) {
					$transformedValue = 0.0;
				}
			}

			$transformedValue = (float) $transformedValue;

			if ($minStep !== null) {
				$transformedValue = round($minStep * round($transformedValue / $minStep), 14);
			}

			$transformedValue = min($maxValue ?? $transformedValue, $transformedValue);
			$transformedValue = max($minValue ?? $transformedValue, $transformedValue);
		} elseif (
			$dataType === Types\DataType::INT
			|| $dataType === Types\DataType::UINT8
			|| $dataType === Types\DataType::UINT16
			|| $dataType === Types\DataType::UINT32
			|| $dataType === Types\DataType::UINT64
		) {
			if (is_bool($transformedValue)) {
				$transformedValue = $transformedValue ? 1 : 0;
			}

			if ($transformedValue === null) {
				$transformedValue = 0;
			} elseif (!is_numeric($transformedValue) || strval($transformedValue) !== strval((int) $transformedValue)) {
				$transformedValue = preg_replace(
					'~\s~',
					'',
					ToolsUtilities\Value::toString($transformedValue, true),
				);

				if (!is_numeric($transformedValue)) {
					$transformedValue = 0;
				}
			}

			$transformedValue = (int) $transformedValue;

			if ($minStep !== null) {
				$transformedValue = round($minStep * round($transformedValue / $minStep), 14);
			}

			$transformedValue = (int) min($maxValue ?? $transformedValue, $transformedValue);
			$transformedValue = (int) max($minValue ?? $transformedValue, $transformedValue);
		} elseif ($dataType === Types\DataType::STRING) {
			$transformedValue = $value !== null ? substr(
				ToolsUtilities\Value::toString($value, true),
				0,
				($maxLength ?? strlen(ToolsUtilities\Value::toString($value, true))),
			) : '';
		}

		if (
			$validValues !== null
			&& !in_array(intval(ToolsUtilities\Value::flattenValue($transformedValue)), $validValues, true)
		) {
			$transformedValue = null;
		}

		return ToolsUtilities\Value::flattenValue($transformedValue);
	}

}
