<?php declare(strict_types = 1);

/**
 * Transformer.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 * @since          0.19.0
 *
 * @date           01.10.22
 */

namespace FastyBird\HomeKitConnector\Protocol;

use DateTimeInterface;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types as MetadataTypes;
use FastyBird\Metadata\ValueObjects as MetadataValueObjects;
use Nette\Utils;
use function array_filter;
use function array_values;
use function count;
use function in_array;
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
	 * @param MetadataEntities\Modules\DevicesModule\PropertyEntity|null $property
	 * @param Types\DataType $dataType
	 * @param bool|float|int|string|null $value
	 */
	public static function fromClient(
		MetadataEntities\Modules\DevicesModule\PropertyEntity|null $property,
		Types\DataType $dataType,
		bool|float|int|string|null $value,
	): bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null
	{
		$transformedValue = null;

		// HAP transformation

		if ($dataType->equalsValue(Types\DataType::DATA_TYPE_BOOLEAN)) {
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
		} elseif ($dataType->equalsValue(Types\DataType::DATA_TYPE_FLOAT)) {
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
			$dataType->equalsValue(Types\DataType::DATA_TYPE_INT)
			|| $dataType->equalsValue(Types\DataType::DATA_TYPE_UINT8)
			|| $dataType->equalsValue(Types\DataType::DATA_TYPE_UINT16)
			|| $dataType->equalsValue(Types\DataType::DATA_TYPE_UINT32)
			|| $dataType->equalsValue(Types\DataType::DATA_TYPE_UINT64)
		) {
			if (is_int($value)) {
				$transformedValue = $value;
			} elseif (is_numeric($value) && strval($value) === strval((int) $value)) {
				$transformedValue = (int) $value;
			} else {
				$transformedValue = preg_replace('~\s~', '', (string) $value);
				$transformedValue = (int) $transformedValue;
			}
		} elseif ($dataType->equalsValue(Types\DataType::DATA_TYPE_STRING)) {
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
			$property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_ENUM)
			|| $property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)
			|| $property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_BUTTON)
		) {
			if ($property->getFormat() instanceof MetadataValueObjects\StringEnumFormat) {
				$filtered = array_values(array_filter(
					$property->getFormat()->getItems(),
					static fn (string $item): bool => Utils\Strings::lower(strval($transformedValue)) === $item,
				));

				if (count($filtered) === 1) {
					if ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)) {
						return MetadataTypes\SwitchPayloadType::isValidValue(strval($transformedValue))
							? MetadataTypes\SwitchPayloadType::get(
								strval($transformedValue),
							)
							: null;
					} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_BUTTON)) {
						return MetadataTypes\ButtonPayloadType::isValidValue(strval($transformedValue))
							? MetadataTypes\ButtonPayloadType::get(
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
					if ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)) {
						return MetadataTypes\SwitchPayloadType::isValidValue(strval($filtered[0][0]->getValue()))
							? MetadataTypes\SwitchPayloadType::get(
								strval($filtered[0][0]->getValue()),
							)
							: null;
					} elseif ($property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_BUTTON)) {
						return MetadataTypes\ButtonPayloadType::isValidValue(strval($filtered[0][0]->getValue()))
							? MetadataTypes\ButtonPayloadType::get(
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
	 * @param MetadataEntities\Modules\DevicesModule\PropertyEntity|null $property
	 * @param Types\DataType $dataType
	 * @param Array<int>|null $validValues
	 * @param int|null $maxLength
	 * @param float|null $minValue
	 * @param float|null $maxValue
	 * @param float|null $minStep
	 * @param bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $value
	 */
	public static function toClient(
		MetadataEntities\Modules\DevicesModule\PropertyEntity|null $property,
		Types\DataType $dataType,
		array|null $validValues,
		int|null $maxLength,
		float|null $minValue,
		float|null $maxValue,
		float|null $minStep,
		bool|float|int|string|DateTimeInterface|MetadataTypes\ButtonPayloadType|MetadataTypes\SwitchPayloadType|null $value,
	): bool|float|int|string|null
	{
		$transformedValue = null;

		// Connector transformation

		if ($property !== null) {
			if (
				$property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_ENUM)
				|| $property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)
				|| $property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_BUTTON)
			) {
				if ($property->getFormat() instanceof MetadataValueObjects\StringEnumFormat) {
					$filtered = array_values(array_filter(
						$property->getFormat()->getItems(),
						static fn (string $item): bool => Utils\Strings::lower(strval($value)) === $item,
					));

					if (count($filtered) === 1) {
						$transformedValue = strval($value);
					}
				} elseif ($property->getFormat() instanceof MetadataValueObjects\CombinedEnumFormat) {
					$filtered = array_values(array_filter(
						$property->getFormat()->getItems(),
						static fn (array $item): bool => $item[0] !== null
							&& Utils\Strings::lower(strval($item[0]->getValue())) === Utils\Strings::lower(
								strval($value),
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
						$property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_SWITCH)
						&& $value instanceof MetadataTypes\SwitchPayloadType
					) || (
						$property->getDataType()->equalsValue(MetadataTypes\DataTypeType::DATA_TYPE_BUTTON)
						&& $value instanceof MetadataTypes\ButtonPayloadType
					)
				) {
					$transformedValue = strval($value->getValue());
				}
			}
		} else {
			$transformedValue = $value;
		}

		// HAP transformation

		if ($dataType->equalsValue(Types\DataType::DATA_TYPE_BOOLEAN)) {
			if ($transformedValue === null) {
				$transformedValue = false;
			} elseif (!is_bool($transformedValue)) {
				$transformedValue = in_array(Utils\Strings::lower(strval($transformedValue)), [
					'true',
					't',
					'yes',
					'y',
					'1',
					'on',
				], true);
			}
		} elseif ($dataType->equalsValue(Types\DataType::DATA_TYPE_FLOAT)) {
			if (!is_numeric($transformedValue)) {
				$transformedValue = str_replace([' ', ','], ['', '.'], (string) $transformedValue);

				if (!is_numeric($transformedValue)) {
					$transformedValue = 0.0;
				}
			}

			$transformedValue = (float) $transformedValue;

			if ($minStep) {
				$transformedValue = round($minStep * round($transformedValue / $minStep), 14);
			}

			$transformedValue = (float) min($maxValue ?? $transformedValue, $transformedValue);
			$transformedValue = (float) max($minValue ?? $transformedValue, $transformedValue);
		} elseif (
			$dataType->equalsValue(Types\DataType::DATA_TYPE_INT)
			|| $dataType->equalsValue(Types\DataType::DATA_TYPE_UINT8)
			|| $dataType->equalsValue(Types\DataType::DATA_TYPE_UINT16)
			|| $dataType->equalsValue(Types\DataType::DATA_TYPE_UINT32)
			|| $dataType->equalsValue(Types\DataType::DATA_TYPE_UINT64)
		) {
			if (!is_numeric($transformedValue) || strval($transformedValue) !== strval((int) $transformedValue)) {
				$transformedValue = preg_replace('~\s~', '', (string) $transformedValue);
			}

			$transformedValue = (int) $transformedValue;

			if ($minStep) {
				$transformedValue = round($minStep * round($transformedValue / $minStep), 14);
			}

			$transformedValue = (int) min($maxValue ?? $transformedValue, $transformedValue);
			$transformedValue = (int) max($minValue ?? $transformedValue, $transformedValue);
		} elseif ($dataType->equalsValue(Types\DataType::DATA_TYPE_STRING)) {
			$transformedValue = $value !== null ? substr(
				strval($value),
				0,
				($maxLength ?? strlen(strval($value))),
			) : '';
		}

		if ($validValues !== null && !in_array((int) $transformedValue, $validValues, true)) {
			$transformedValue = null;
		}

		return $transformedValue;
	}

}
