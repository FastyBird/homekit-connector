#!/usr/bin/python3

#     Copyright 2021. FastyBird s.r.o.
#
#     Licensed under the Apache License, Version 2.0 (the "License");
#     you may not use this file except in compliance with the License.
#     You may obtain a copy of the License at
#
#         http://www.apache.org/licenses/LICENSE-2.0
#
#     Unless required by applicable law or agreed to in writing, software
#     distributed under the License is distributed on an "AS IS" BASIS,
#     WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#     See the License for the specific language governing permissions and
#     limitations under the License.

"""
HomeKit connector helpers module
"""

# Python base dependencies
from datetime import datetime
from typing import Dict, List, Optional, Tuple, Union

# Library dependencies
from fastnumbers import fast_float, fast_int
from fastybird_metadata.types import ButtonPayload, DataType, SwitchPayload

# Library libs
from fastybird_homekit_connector.types import HAPDataType


def filter_enum_format(
    item: Union[str, Tuple[str, Optional[str], Optional[str]]],
    value: Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None],
) -> bool:
    """Filter enum format value by value"""
    if value is None:
        return False

    if isinstance(item, tuple):
        if len(item) != 3:
            return False

        item_as_list = list(item)

        return (
            str(value).lower() == item_as_list[0]
            or str(value).lower() == item_as_list[1]
            or str(value).lower() == item_as_list[2]
        )

    return str(value).lower() == item


class DataTransformHelpers:
    """
    Characteristic value transformers helper

    @package        FastyBird:HomeKitConnector!
    @module         api/transformers

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    @staticmethod
    def transform_from_accessory(  # pylint: disable=too-many-arguments,too-many-branches,too-many-statements
        data_type: DataType,
        value_format: Union[
            Tuple[Optional[int], Optional[int]],
            Tuple[Optional[float], Optional[float]],
            List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
            None,
        ],
        hap_data_type: Optional[HAPDataType],
        hap_valid_values: Optional[Dict[str, int]],
        hap_max_length: Optional[int],
        hap_min_value: Optional[float],
        hap_max_value: Optional[float],
        hap_min_step: Optional[float],
        value: Union[str, int, float, bool, None],
    ) -> Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None]:
        """Transform value received from Home app"""
        transformed_value: Union[str, int, float, bool, None] = None

        # HAP transformation

        if hap_data_type is None:
            return None

        if hap_data_type == HAPDataType.BOOLEAN:
            if value is None:
                transformed_value = False

            elif not isinstance(value, bool):
                transformed_value = str(value).lower() in ["1", "true", "on"]

        if hap_data_type == HAPDataType.FLOAT:
            if value is None:
                float_value = 0.0

            elif not isinstance(value, float):
                try:
                    float_value = (
                        value
                        if isinstance(value, float)
                        else fast_float(str(value), raise_on_invalid=True)  # type: ignore[arg-type]
                    )

                except ValueError:
                    float_value = 0.0

            else:
                float_value = value

            if float_value and hap_min_step:
                float_value = round(hap_min_step * round(float_value / hap_min_step), 14)

            float_value = min(
                hap_max_value if hap_max_value is not None else float_value,
                float_value,
            )
            float_value = max(
                hap_min_value if hap_min_value is not None else float_value,
                float_value,
            )

            transformed_value = float_value

        if hap_data_type in (
            HAPDataType.INT,
            HAPDataType.UINT8,
            HAPDataType.UINT16,
            HAPDataType.UINT32,
            HAPDataType.UINT64,
        ):
            if value is None:
                int_value = 0

            elif not isinstance(value, int):
                try:
                    int_value = (
                        value
                        if isinstance(value, int)
                        else fast_int(str(value), raise_on_invalid=True)  # type: ignore[arg-type]
                    )

                except ValueError:
                    int_value = 0

            else:
                int_value = value

            if int_value and hap_min_step:
                int_value = round(int(hap_min_step) * round(int_value / int(hap_min_step)), 14)

            int_value = min(
                int(hap_max_value) if hap_max_value is not None else int_value,
                int_value,
            )
            int_value = max(
                int(hap_min_value) if hap_min_value is not None else int_value,
                int_value,
            )

            transformed_value = int(int_value)

        if hap_data_type == HAPDataType.STRING:
            if value is None:
                transformed_value = ""

            else:
                transformed_value = str(value)[:hap_max_length]

        if hap_data_type in (
            HAPDataType.ARRAY,
            HAPDataType.DICTIONARY,
            HAPDataType.DATA,
            HAPDataType.TLV8,
        ):
            if value is None:
                transformed_value = ""

        if hap_valid_values is not None:
            remapped_valid_values = map(str, hap_valid_values.values())

            if str(transformed_value) not in remapped_valid_values:
                transformed_value = min(hap_valid_values.values())

        # Connector transformation

        if transformed_value is None:
            return None

        if data_type == DataType.ENUM:
            if format is not None and isinstance(value_format, list):
                filtered = [item for item in value_format if filter_enum_format(item=item, value=transformed_value)]

                if isinstance(filtered, list) and len(filtered) == 1:
                    if isinstance(filtered[0], tuple):
                        return (
                            str(filtered[0][0])
                            if str(filtered[0][1]).lower() == str(transformed_value).lower()
                            else None
                        )

                    return str(filtered[0])

        if data_type == DataType.SWITCH:
            if value_format is not None and isinstance(value_format, list):
                filtered = [item for item in value_format if filter_enum_format(item=item, value=transformed_value)]

                if (
                    isinstance(filtered, list)
                    and len(filtered) == 1
                    and isinstance(filtered[0], tuple)
                    and str(filtered[0][1]).lower() == str(transformed_value).lower()
                    and SwitchPayload.has_value(filtered[0][0])
                ):
                    return SwitchPayload(filtered[0][0])

        return transformed_value

    # -----------------------------------------------------------------------------

    @staticmethod
    def transform_for_accessory(  # pylint: disable=too-many-arguments,too-many-branches,too-many-statements
        data_type: DataType,
        value_format: Union[
            Tuple[Optional[int], Optional[int]],
            Tuple[Optional[float], Optional[float]],
            List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
            None,
        ],
        hap_data_type: Optional[HAPDataType],
        hap_valid_values: Optional[Dict[str, int]],
        hap_max_length: Optional[int],
        hap_min_value: Optional[float],
        hap_max_value: Optional[float],
        hap_min_step: Optional[float],
        value: Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None],
    ) -> Union[str, int, float, bool, None]:
        """Transform value to be transformed to Home app"""
        transformed_value: Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None] = value

        # Connector transformation

        if data_type == DataType.ENUM:
            if value_format is not None and isinstance(value_format, list):
                filtered = [item for item in value_format if filter_enum_format(item=item, value=value)]

                if (
                    isinstance(filtered, list)
                    and len(filtered) == 1
                    and isinstance(filtered[0], tuple)
                    and str(filtered[0][0]).lower() == str(value).lower()
                ):
                    transformed_value = str(filtered[0][2])

                elif len(filtered) == 1 and not isinstance(filtered[0], tuple):
                    transformed_value = str(filtered[0])

                else:
                    transformed_value = None

        if data_type == DataType.SWITCH:
            if value_format is not None and isinstance(value_format, list) and isinstance(value, SwitchPayload):
                filtered = [item for item in value_format if filter_enum_format(item=item, value=value)]

                if (
                    isinstance(filtered, list)
                    and len(filtered) == 1
                    and isinstance(filtered[0], tuple)
                    and str(filtered[0][0]).lower() == str(value).lower()
                ):
                    transformed_value = str(filtered[0][2])

        # HAP transformation

        if hap_data_type is None:
            return ""

        if hap_data_type == HAPDataType.BOOLEAN:
            if transformed_value is None:
                transformed_value = False

            elif not isinstance(transformed_value, bool):
                transformed_value = str(transformed_value).lower() in ["1", "true", "on"]

        if hap_data_type == HAPDataType.FLOAT:
            if transformed_value is None:
                float_value = 0.0

            elif not isinstance(transformed_value, float):
                try:
                    float_value = (
                        transformed_value
                        if isinstance(transformed_value, float)
                        else fast_float(str(transformed_value), raise_on_invalid=True)  # type: ignore[arg-type]
                    )

                except ValueError:
                    float_value = 0.0

            else:
                float_value = transformed_value

            if float_value and hap_min_step:
                float_value = round(hap_min_step * round(float_value / hap_min_step), 14)

            float_value = min(
                hap_max_value if hap_max_value is not None else float_value,
                float_value,
            )
            float_value = max(
                hap_min_value if hap_min_value is not None else float_value,
                float_value,
            )

            transformed_value = float_value

        if hap_data_type in (
            HAPDataType.INT,
            HAPDataType.UINT8,
            HAPDataType.UINT16,
            HAPDataType.UINT32,
            HAPDataType.UINT64,
        ):
            if transformed_value is None:
                int_value = 0

            elif not isinstance(transformed_value, int):
                try:
                    int_value = (
                        transformed_value
                        if isinstance(transformed_value, int)
                        else fast_int(str(transformed_value), raise_on_invalid=True)  # type: ignore[arg-type]
                    )

                except ValueError:
                    int_value = 0

            else:
                int_value = transformed_value

            if int_value and hap_min_step:
                int_value = round(int(hap_min_step) * round(int_value / int(hap_min_step)), 14)

            int_value = min(
                int(hap_max_value) if hap_max_value is not None else int_value,
                int_value,
            )
            int_value = max(
                int(hap_min_value) if hap_min_value is not None else int_value,
                int_value,
            )

            transformed_value = int(int_value)

        if hap_data_type == HAPDataType.STRING:
            if transformed_value is None:
                transformed_value = ""

            else:
                transformed_value = str(transformed_value)[:hap_max_length]

        if hap_data_type in (
            HAPDataType.ARRAY,
            HAPDataType.DICTIONARY,
            HAPDataType.DATA,
            HAPDataType.TLV8,
        ):
            if transformed_value is None:
                transformed_value = ""

        if hap_valid_values is not None:
            remapped_valid_values = map(str, hap_valid_values.values())

            if str(transformed_value) in remapped_valid_values:
                if isinstance(transformed_value, (str, int, float, bool)):
                    return transformed_value

                return ""

            return min(hap_valid_values.values())

        if isinstance(transformed_value, (str, int, float, bool)):
            return transformed_value

        return ""
