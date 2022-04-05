#!/usr/bin/python3

#     Copyright 2022. FastyBird s.r.o.
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
HomeKit connector types
"""

# Python base dependencies
from enum import unique

# Library dependencies
from fastybird_metadata.devices_module import ConnectorPropertyName
from fastybird_metadata.enum import ExtendedEnum
from pyhap.characteristic import (
    HAP_FORMAT_ARRAY,
    HAP_FORMAT_BOOL,
    HAP_FORMAT_DATA,
    HAP_FORMAT_DICTIONARY,
    HAP_FORMAT_FLOAT,
    HAP_FORMAT_INT,
    HAP_FORMAT_STRING,
    HAP_FORMAT_TLV8,
    HAP_FORMAT_UINT8,
    HAP_FORMAT_UINT16,
    HAP_FORMAT_UINT32,
    HAP_FORMAT_UINT64,
)

CONNECTOR_NAME: str = "homekit"
DEVICE_NAME: str = "homekit"


@unique
class HAPDataType(ExtendedEnum):
    """
    HAP data type

    @package        FastyBird:HomeKitConnector!
    @module         types

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    BOOLEAN: str = HAP_FORMAT_BOOL
    INT: str = HAP_FORMAT_INT
    FLOAT: str = HAP_FORMAT_FLOAT
    STRING: str = HAP_FORMAT_STRING
    ARRAY: str = HAP_FORMAT_ARRAY
    DICTIONARY: str = HAP_FORMAT_DICTIONARY
    UINT8: str = HAP_FORMAT_UINT8
    UINT16: str = HAP_FORMAT_UINT16
    UINT32: str = HAP_FORMAT_UINT32
    UINT64: str = HAP_FORMAT_UINT64
    DATA: str = HAP_FORMAT_DATA
    TLV8: str = HAP_FORMAT_TLV8

    # -----------------------------------------------------------------------------

    def __hash__(self) -> int:
        return hash(self._name_)  # pylint: disable=no-member


@unique
class ConnectorAttribute(ExtendedEnum):
    """
    Connector attribute name

    @package        FastyBird:HomeKitConnector!
    @module         types

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    PORT: str = ConnectorPropertyName.PORT.value
    PINCODE: str = "pincode"

    # -----------------------------------------------------------------------------

    def __hash__(self) -> int:
        return hash(self._name_)  # pylint: disable=no-member
