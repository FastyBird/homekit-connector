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
HomeKit connector registry module records
"""

# Python base dependencies
import uuid
from datetime import datetime
from typing import Dict, List, Optional, Tuple, Union

# Library dependencies
from fastybird_metadata.helpers import normalize_value
from fastybird_metadata.types import ButtonPayload, DataType, SwitchPayload
from pyhap.accessory import Accessory
from pyhap.accessory_driver import AccessoryDriver
from pyhap.characteristic import Characteristic
from pyhap.service import Service

from fastybird_homekit_connector.transformers import DataTransformHelpers
from fastybird_homekit_connector.types import HAPDataType


class AccessoryRecord(Accessory):
    """
    HomeKit accessory record

    @package        FastyBird:HomeKitConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __id: uuid.UUID

    __enabled: bool = False

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        accessory_id: uuid.UUID,
        accessory_name: str,
        accessory_enabled: bool,
        driver: AccessoryDriver,
    ) -> None:
        super().__init__(driver=driver, display_name=accessory_name, aid=accessory_id.int)

        self.__id = accessory_id
        self.__enabled = accessory_enabled

        self.set_info_service(
            firmware_revision="0.0.1",
            manufacturer="FastyBird",
            model="Custom model",
            serial_number=accessory_id.__str__(),
        )

    # -----------------------------------------------------------------------------

    @property
    def id(self) -> uuid.UUID:  # pylint: disable=invalid-name
        """Accessory unique identifier"""
        return self.__id

    # -----------------------------------------------------------------------------

    @property
    def enabled(self) -> bool:
        """Is accessory enabled?"""
        return self.__enabled

    # -----------------------------------------------------------------------------

    @enabled.setter
    def enabled(self, enabled: bool) -> None:
        """Set accessory enable state"""
        self.__enabled = enabled

    # -----------------------------------------------------------------------------

    @property
    def available_test(self) -> bool:
        """Accessory is available flag"""
        return self.__enabled


class ServiceRecord(Service):
    """
    HomeKit accessory service record

    @package        FastyBird:HomeKitConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __accessory_id: uuid.UUID

    __id: uuid.UUID
    __identifier: str

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        accessory_id: uuid.UUID,
        service_id: uuid.UUID,
        service_identifier: str,
        service_name: str,
        service_type_id: uuid.UUID,
    ) -> None:
        super().__init__(type_id=service_type_id, display_name=service_name)

        self.__accessory_id = accessory_id

        self.__id = service_id
        self.__identifier = service_identifier

    # -----------------------------------------------------------------------------

    @property
    def accessory_id(self) -> uuid.UUID:
        """Service accessory unique identifier"""
        return self.__accessory_id

    # -----------------------------------------------------------------------------

    @property
    def id(self) -> uuid.UUID:  # pylint: disable=invalid-name
        """Service unique identifier"""
        return self.__id

    # -----------------------------------------------------------------------------

    @property
    def identifier(self) -> str:
        """Service unique homekit identifier"""
        return self.__identifier

    # -----------------------------------------------------------------------------

    @property
    def name(self) -> str:
        """Service unique homekit name"""
        return self.display_name


class CharacteristicRecord(Characteristic):  # pylint: disable=too-many-instance-attributes
    """
    HomeKit accessory service characteristic record

    @package        FastyBird:HomeKitConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __service_id: uuid.UUID

    __id: uuid.UUID
    __identifier: str
    __data_type: DataType
    __format: Union[
        Tuple[Optional[int], Optional[int]],
        Tuple[Optional[float], Optional[float]],
        List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
        None,
    ] = None
    __number_of_decimals: Optional[int] = None
    __queryable: bool = False
    __settable: bool = False

    __actual_value: Union[str, int, float, bool, None] = None
    __expected_value: Union[str, int, float, bool, None] = None

    __hap_data_type: Optional[HAPDataType] = None

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        service_id: uuid.UUID,
        characteristic_type_id: uuid.UUID,
        characteristic_properties: Dict,
        characteristic_id: uuid.UUID,
        characteristic_identifier: str,
        characteristic_name: str,
        characteristic_data_type: DataType,
        characteristic_format: Union[
            Tuple[Optional[int], Optional[int]],
            Tuple[Optional[float], Optional[float]],
            List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
            None,
        ] = None,
        characteristic_number_of_decimals: Optional[int] = None,
        characteristic_queryable: bool = False,
        characteristic_settable: bool = False,
        characteristic_hap_data_type: Optional[HAPDataType] = None
    ) -> None:
        super().__init__(
            type_id=characteristic_type_id,
            display_name=characteristic_name,
            properties=characteristic_properties,
        )

        self.__service_id = service_id

        self.__id = characteristic_id
        self.__identifier = characteristic_identifier
        self.__data_type = characteristic_data_type
        self.__format = characteristic_format
        self.__number_of_decimals = characteristic_number_of_decimals

        self.__queryable = characteristic_queryable
        self.__settable = characteristic_settable

        self.__hap_data_type = characteristic_hap_data_type
        print(characteristic_hap_data_type)

        self.setter_callback = self.__set_value_callback
        # self.getter_callback = self.__get_value_callback

    # -----------------------------------------------------------------------------

    @property
    def service_id(self) -> uuid.UUID:
        """Characteristic service unique identifier"""
        return self.__service_id

    # -----------------------------------------------------------------------------

    @property
    def id(self) -> uuid.UUID:  # pylint: disable=invalid-name
        """Characteristic unique identifier"""
        return self.__id

    # -----------------------------------------------------------------------------

    @property
    def identifier(self) -> str:
        """Characteristic unique homekit identifier"""
        return self.__identifier

    # -----------------------------------------------------------------------------

    @property
    def name(self) -> str:
        """Characteristic unique homekit name"""
        return self.display_name

    # -----------------------------------------------------------------------------

    @property
    def data_type(self) -> DataType:
        """Characteristic value data type"""
        return self.__data_type

    # -----------------------------------------------------------------------------

    @property
    def hap_data_type(self) -> HAPDataType:
        """Characteristic HAP value data type"""
        return self.__hap_data_type

    # -----------------------------------------------------------------------------

    @property
    def format(
        self,
    ) -> Union[
        Tuple[Optional[int], Optional[int]],
        Tuple[Optional[float], Optional[float]],
        List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
        None,
    ]:
        """Characteristic value format"""
        return self.__format

    # -----------------------------------------------------------------------------

    @property
    def number_of_decimals(self) -> Optional[int]:
        """Number of decimals for transforming int to float"""
        return self.__number_of_decimals

    # -----------------------------------------------------------------------------

    @property
    def queryable(self) -> bool:
        """Is register queryable?"""
        return self.__queryable

    # -----------------------------------------------------------------------------

    @property
    def settable(self) -> bool:
        """Is register settable?"""
        return self.__settable

    # -----------------------------------------------------------------------------

    @property
    def actual_value(self) -> Union[str, int, float, bool, None]:
        """Characteristic actual value"""
        return self.__actual_value

    # -----------------------------------------------------------------------------

    @actual_value.setter
    def actual_value(self, value: Union[str, int, float, bool, None]) -> None:
        """Set Characteristic actual value"""
        if self.actual_value != value:
            self.__actual_value = value

            # Set value only for settable characteristic
            if self.settable:
                self.set_value(
                    value=DataTransformHelpers.transform_to_accessory(
                        data_type=self.hap_data_type,
                        value=self.actual_value,
                    ),
                )

        if self.actual_value == self.expected_value:
            self.expected_value = None

    # -----------------------------------------------------------------------------

    @property
    def expected_value(self) -> Union[str, int, float, bool, None]:
        """Characteristic expected value"""
        return self.__expected_value

    # -----------------------------------------------------------------------------

    @expected_value.setter
    def expected_value(self, value: Union[str, int, float, bool, None]) -> None:
        """Set Characteristic expected value"""
        self.__expected_value = value

    # -----------------------------------------------------------------------------

    def __set_value_callback(self, value) -> None:
        print(self.__identifier)
        print(value)

    # -----------------------------------------------------------------------------

    def __get_value_callback(self) -> None:
        return self.actual_value
