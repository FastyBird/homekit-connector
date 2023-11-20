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
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\Utilities as MetadataUtilities;
use FastyBird\Library\Metadata\ValueObjects as MetadataValueObjects;
use Nette\Utils;
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
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public static function fromClient(
		MetadataDocuments\DevicesModule\Property|null $property,
		Types\DataType $dataType,
		bool|float|int|string|null $value,
	): bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayload|MetadataTypes\SwitchPayload|MetadataTypes\CoverPayload|null
	{
		$transformedValue = null;

		// HAP transformation

		if ($dataType->equalsValue(Types\DataType::BOOLEAN)) {
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
		} elseif ($dataType->equalsValue(Types\DataType::FLOAT)) {
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
			$dataType->equalsValue(Types\DataType::INT)
			|| $dataType->equalsValue(Types\DataType::UINT8)
			|| $dataType->equalsValue(Types\DataType::UINT16)
			|| $dataType->equalsValue(Types\DataType::UINT32)
			|| $dataType->equalsValue(Types\DataType::UINT64)
		) {
			if (is_int($value)) {
				$transformedValue = $value;
			} elseif (is_numeric($value) && strval($value) === strval((int) $value)) {
				$transformedValue = (int) $value;
			} else {
				$transformedValue = preg_replace('~\s~', '', (string) $value);
				$transformedValue = (int) $transformedValue;
			}
		} elseif ($dataType->equalsValue(Types\DataType::STRING)) {
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
			$property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_ENUM)
			|| $property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)
			|| $property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)
		) {
			if ($property->getFormat() instanceof MetadataValueObjects\StringEnumFormat) {
				$filtered = array_values(array_filter(
					$property->getFormat()->getItems(),
					static fn (string $item): bool => Utils\Strings::lower(strval($transformedValue)) === $item,
				));

				if (count($filtered) === 1) {
					if ($property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)) {
						return MetadataTypes\SwitchPayload::isValidValue(strval($transformedValue))
							? MetadataTypes\SwitchPayload::get(
								strval($transformedValue),
							)
							: null;
					} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)) {
						return MetadataTypes\ButtonPayload::isValidValue(strval($transformedValue))
							? MetadataTypes\ButtonPayload::get(
								strval($transformedValue),
							)
							: null;
					} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_COVER)) {
						return MetadataTypes\CoverPayload::isValidValue(strval($transformedValue))
							? MetadataTypes\CoverPayload::get(
								strval($transformedValue),
							)
							: null;
					} else {
						return strval($transformedValue);
					}
				}

				return null;
			} elseif ($property->getFormat() instanceof MetadataValueObjects\CombinedEnumFormat) {
				$filtered = array_values(array_filter(
					$property->getFormat()->getItems(),
					static fn (array $item): bool => $item[1] !== null
						&& Utils\Strings::lower(strval($item[1]->getValue())) === Utils\Strings::lower(
							strval($transformedValue),
						),
				));

				if (
					count($filtered) === 1
					&& $filtered[0][0] instanceof MetadataValueObjects\CombinedEnumFormatItem
				) {
					if ($property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)) {
						return MetadataTypes\SwitchPayload::isValidValue(strval($filtered[0][0]->getValue()))
							? MetadataTypes\SwitchPayload::get(
								strval($filtered[0][0]->getValue()),
							)
							: null;
					} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)) {
						return MetadataTypes\ButtonPayload::isValidValue(strval($filtered[0][0]->getValue()))
							? MetadataTypes\ButtonPayload::get(
								strval($filtered[0][0]->getValue()),
							)
							: null;
					} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_COVER)) {
						return MetadataTypes\CoverPayload::isValidValue(strval($filtered[0][0]->getValue()))
							? MetadataTypes\CoverPayload::get(
								strval($filtered[0][0]->getValue()),
							)
							: null;
					} else {
						return strval($filtered[0][0]->getValue());
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
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public static function toClient(
		MetadataDocuments\DevicesModule\Property|null $property,
		Types\DataType $dataType,
		array|null $validValues,
		int|null $maxLength,
		float|null $minValue,
		float|null $maxValue,
		float|null $minStep,
		bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayload|MetadataTypes\SwitchPayload|MetadataTypes\CoverPayload|null $value,
	): bool|float|int|string|null
	{
		$transformedValue = null;

		// Connector transformation

		if ($property !== null) {
			if (
				$property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_ENUM)
				|| $property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)
				|| $property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)
			) {
				if ($property->getFormat() instanceof MetadataValueObjects\StringEnumFormat) {
					$filtered = array_values(array_filter(
						$property->getFormat()->getItems(),
						static fn (string $item): bool => Utils\Strings::lower(
							strval(MetadataUtilities\ValueHelper::flattenValue($value)),
						) === $item,
					));

					if (count($filtered) === 1) {
						$transformedValue = strval(MetadataUtilities\ValueHelper::flattenValue($value));
					}
				} elseif ($property->getFormat() instanceof MetadataValueObjects\CombinedEnumFormat) {
					$filtered = array_values(array_filter(
						$property->getFormat()->getItems(),
						static fn (array $item): bool => $item[0] !== null
							&& Utils\Strings::lower(strval($item[0]->getValue())) === Utils\Strings::lower(
								strval(MetadataUtilities\ValueHelper::flattenValue($value)),
							),
					));

					if (
						count($filtered) === 1
						&& $filtered[0][2] instanceof MetadataValueObjects\CombinedEnumFormatItem
					) {
						$transformedValue = is_scalar($filtered[0][2]->getValue())
							? $filtered[0][2]->getValue()
							: strval(
								$filtered[0][2]->getValue(),
							);
					}
				}

				if (
					(
						$property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_SWITCH)
						&& $value instanceof MetadataTypes\SwitchPayload
					) || (
						$property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_BUTTON)
						&& $value instanceof MetadataTypes\ButtonPayload
					) || (
						$property->getDataType()->equalsValue(MetadataTypes\DataType::DATA_TYPE_COVER)
						&& $value instanceof MetadataTypes\CoverPayload
					)
				) {
					$transformedValue = strval($value->getValue());
				}
			} else {
				$transformedValue = $value;
			}
		} else {
			$transformedValue = $value;
		}

		// HAP transformation

		if ($dataType->equalsValue(Types\DataType::BOOLEAN)) {
			if ($transformedValue === null) {
				$transformedValue = false;
			} elseif (!is_bool($transformedValue)) {
				$transformedValue = in_array(
					Utils\Strings::lower(strval(MetadataUtilities\ValueHelper::flattenValue($transformedValue))),
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
		} elseif ($dataType->equalsValue(Types\DataType::FLOAT)) {
			if (!is_numeric($transformedValue)) {
				$transformedValue = str_replace(
					[' ', ','],
					['', '.'],
					strval(MetadataUtilities\ValueHelper::flattenValue($transformedValue)),
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
			$dataType->equalsValue(Types\DataType::INT)
			|| $dataType->equalsValue(Types\DataType::UINT8)
			|| $dataType->equalsValue(Types\DataType::UINT16)
			|| $dataType->equalsValue(Types\DataType::UINT32)
			|| $dataType->equalsValue(Types\DataType::UINT64)
		) {
			if (!is_numeric($transformedValue) || strval($transformedValue) !== strval((int) $transformedValue)) {
				$transformedValue = preg_replace(
					'~\s~',
					'',
					strval(MetadataUtilities\ValueHelper::flattenValue($transformedValue)),
				);
			}

			$transformedValue = (int) $transformedValue;

			if ($minStep !== null) {
				$transformedValue = round($minStep * round($transformedValue / $minStep), 14);
			}

			$transformedValue = (int) min($maxValue ?? $transformedValue, $transformedValue);
			$transformedValue = (int) max($minValue ?? $transformedValue, $transformedValue);
		} elseif ($dataType->equalsValue(Types\DataType::STRING)) {
			$transformedValue = $value !== null ? substr(
				strval(MetadataUtilities\ValueHelper::flattenValue($value)),
				0,
				($maxLength ?? strlen(strval(MetadataUtilities\ValueHelper::flattenValue($value)))),
			) : '';
		}

		if (
			$validValues !== null
			&& !in_array(intval(MetadataUtilities\ValueHelper::flattenValue($transformedValue)), $validValues, true)
		) {
			$transformedValue = null;
		}

		return MetadataUtilities\ValueHelper::flattenValue($transformedValue);
	}

}
