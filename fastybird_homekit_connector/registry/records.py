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
from typing import Dict, List, Optional, Set, Tuple, Union

# Library dependencies
from fastybird_devices_module.utils import normalize_value
from fastybird_metadata.types import ButtonPayload, DataType, SwitchPayload
from pyhap.accessory_driver import AccessoryDriver
from pyhap.const import (
    CATEGORY_OTHER,
    HAP_PERMISSION_READ,
    HAP_REPR_AID,
    HAP_REPR_CHARS,
    HAP_REPR_FORMAT,
    HAP_REPR_IID,
    HAP_REPR_MAX_LEN,
    HAP_REPR_PERM,
    HAP_REPR_PRIMARY,
    HAP_REPR_SERVICES,
    HAP_REPR_TYPE,
    HAP_REPR_VALID_VALUES,
    HAP_REPR_VALUE,
)
from pyhap.iid_manager import IIDManager
from pyhap.util import uuid_to_hap_type
from whistle import EventDispatcher

# Library libs
from fastybird_homekit_connector.events.events import CharacteristicCommandEvent
from fastybird_homekit_connector.transformers import DataTransformHelpers
from fastybird_homekit_connector.types import HAPDataType


class AccessoryRecord:
    """
    HomeKit accessory record

    @package        FastyBird:HomeKitConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    setter_callback = None  # To keep it compatible

    services: Set["ServiceRecord"] = set()

    __id: uuid.UUID

    __enabled: bool = False

    __hap_driver: AccessoryDriver
    __hap_iid_manager: IIDManager
    __hap_name: str

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        accessory_id: uuid.UUID,
        accessory_name: str,
        accessory_enabled: bool,
        driver: AccessoryDriver,
    ) -> None:
        self.__id = accessory_id
        self.__enabled = accessory_enabled

        self.__hap_driver = driver
        self.__hap_iid_manager = IIDManager()
        self.__hap_name = accessory_name

        self.services = set()

    # -----------------------------------------------------------------------------

    def __repr__(self) -> str:
        """Return the representation of the accessory"""
        services = [service.name for service in self.services]

        return f"<accessory display_name='{self.__hap_name}' services={services}>"

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
    def category(self) -> int:
        """Accessory HAP category group"""
        return CATEGORY_OTHER

    # -----------------------------------------------------------------------------

    @property
    def available(self) -> bool:
        """Accessory HAP availability flag"""
        return self.__enabled

    # -----------------------------------------------------------------------------

    @property
    def iid_manager(self) -> IIDManager:
        """Accessory HAP services&characteristics manager"""
        return self.__hap_iid_manager

    # -----------------------------------------------------------------------------

    @property
    def aid(self) -> int:
        """Accessory HAP unique identifier"""
        return self.id.__int__()

    # -----------------------------------------------------------------------------

    def add_service(self, service: "ServiceRecord") -> None:
        """Add the given service to this Accessory"""
        self.iid_manager.assign(service)

        self.services.add(service)

        for char in service.characteristics:
            self.iid_manager.assign(char)

    # -----------------------------------------------------------------------------

    def get_characteristic(self, aid: int, iid: int) -> Optional["CharacteristicRecord"]:
        """Get the characteristic for the given IID"""
        if aid != self.aid:
            return None

        char = self.iid_manager.get_obj(iid)

        if isinstance(char, CharacteristicRecord):
            return char

        return None

    # -----------------------------------------------------------------------------

    def publish(
        self,
        value: Union[str, int, float, bool, None],
        sender: "CharacteristicRecord",
        sender_client_address: Optional[Tuple[str, int]] = None,
        immediate: bool = False,
    ) -> None:
        """Send characteristic value to clients"""
        self.__hap_driver.publish(
            data={
                HAP_REPR_AID: self.aid,
                HAP_REPR_IID: self.iid_manager.get_iid(sender),
                HAP_REPR_VALUE: value,
            },
            sender_client_addr=sender_client_address,
            immediate=immediate,
        )

    # -----------------------------------------------------------------------------

    async def run(self) -> None:
        """Called when the Accessory should start doing its thing"""

    # -----------------------------------------------------------------------------

    async def stop(self) -> None:
        """Called when the Accessory should stop what is doing and clean up any resources"""

    # -----------------------------------------------------------------------------

    def to_HAP(self) -> Dict:  # pylint: disable=invalid-name
        """HAP service representation builder of this Accessory"""
        return {
            HAP_REPR_AID: self.aid,
            HAP_REPR_SERVICES: [service.to_HAP() for service in self.services],
        }


class ServiceRecord:  # pylint: disable=too-many-instance-attributes
    """
    HomeKit accessory service record

    @package        FastyBird:HomeKitConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    setter_callback = None  # To keep it compatible

    characteristics: Set["CharacteristicRecord"] = set()

    __accessory: AccessoryRecord

    __id: uuid.UUID
    __identifier: str

    __hap_type_id: uuid.UUID
    __hap_name: str
    __hap_is_primary: Optional[bool] = None

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        accessory: AccessoryRecord,
        service_id: uuid.UUID,
        service_identifier: str,
        service_name: str,
        service_type_id: uuid.UUID,
    ) -> None:
        self.__accessory = accessory

        self.__id = service_id
        self.__identifier = service_identifier

        self.__hap_type_id = service_type_id
        self.__hap_name = service_name
        self.__hap_is_primary = None

        self.characteristics = set()

    # -----------------------------------------------------------------------------

    def __repr__(self) -> str:
        """Return the representation of the service"""
        characteristics = {char.name: char.get_value() for char in self.characteristics}

        return f"<service display_name={self.name} chars={characteristics}>"

    # -----------------------------------------------------------------------------

    @property
    def accessory_id(self) -> uuid.UUID:
        """Service accessory unique identifier"""
        return self.__accessory.id

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
        return self.__hap_name

    # -----------------------------------------------------------------------------

    @property
    def accessory(self) -> AccessoryRecord:
        """Characteristic HAP accessory"""
        return self.__accessory

    # -----------------------------------------------------------------------------

    def add_characteristic(self, char: "CharacteristicRecord") -> None:
        """Add the given characteristic to Service"""
        if not any(char.type_id == original_char.type_id for original_char in self.characteristics):
            self.characteristics.add(char)

    # -----------------------------------------------------------------------------

    def to_HAP(self) -> Dict:  # pylint: disable=invalid-name
        """HAP service representation builder of this Service"""
        hap = {
            HAP_REPR_IID: self.accessory.iid_manager.get_iid(self),
            HAP_REPR_TYPE: uuid_to_hap_type(self.__hap_type_id),
            HAP_REPR_CHARS: [c.to_HAP() for c in self.characteristics],
        }

        if self.__hap_is_primary is not None:
            hap[HAP_REPR_PRIMARY] = self.__hap_is_primary

        return hap


class CharacteristicRecord:  # pylint: disable=too-many-instance-attributes,too-many-public-methods
    """
    HomeKit accessory service characteristic record

    @package        FastyBird:HomeKitConnector!
    @module         registry/records

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __event_dispatcher: EventDispatcher

    __accessory: AccessoryRecord
    __service: ServiceRecord

    __id: uuid.UUID
    __identifier: str
    __data_type: DataType
    __format: Union[
        Tuple[Optional[int], Optional[int]],
        Tuple[Optional[float], Optional[float]],
        List[Union[str, Tuple[str, Optional[str], Optional[str]]]],
        None,
    ] = None
    __invalid: Union[int, float, str, None] = None
    __number_of_decimals: Optional[int] = None
    __queryable: bool = False
    __settable: bool = False

    __actual_value: Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None] = None
    __expected_value: Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None] = None

    __hap_type_id: uuid.UUID
    __hap_properties: Dict
    __hap_name: str
    __hap_data_type: HAPDataType
    __hap_valid_values: Optional[Dict[str, int]] = None
    __hap_max_length: int
    __hap_min_value: Optional[float] = None
    __hap_max_value: Optional[float] = None
    __hap_min_step: Optional[float] = None
    __hap_permissions: List[str] = []
    __hap_unit: Optional[str] = None

    PROP_FORMAT: str = "Format"
    PROP_MAX_VALUE: str = "maxValue"
    PROP_MIN_STEP: str = "minStep"
    PROP_MIN_VALUE: str = "minValue"
    PROP_PERMISSIONS: str = "Permissions"
    PROP_UNIT: str = "unit"
    PROP_VALID_VALUES: str = "ValidValues"
    PROP_MAX_LEN: str = "maxLen"

    DEFAULT_MAX_LENGTH = 64
    ABSOLUTE_MAX_LENGTH = 256

    __BUTTON = uuid.UUID("00000126-0000-1000-8000-0026BB765291")
    __PROGRAMMABLE_SWITCH = uuid.UUID("00000073-0000-1000-8000-0026BB765291")

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments,too-many-locals
        self,
        event_dispatcher: EventDispatcher,
        accessory: AccessoryRecord,
        service: ServiceRecord,
        characteristic_type_id: uuid.UUID,
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
        characteristic_invalid: Union[int, float, str, None] = None,
        characteristic_number_of_decimals: Optional[int] = None,
        characteristic_queryable: bool = False,
        characteristic_settable: bool = False,
        characteristic_value: Union[int, float, str, bool, datetime, ButtonPayload, SwitchPayload, None] = None,
        characteristic_hap_data_type: Optional[HAPDataType] = None,
        characteristic_hap_valid_values: Optional[Dict[str, int]] = None,
        characteristic_hap_max_length: Optional[int] = None,
        characteristic_hap_min_value: Optional[float] = None,
        characteristic_hap_max_value: Optional[float] = None,
        characteristic_hap_min_step: Optional[float] = None,
        characteristic_hap_permissions: Optional[List[str]] = None,
        characteristic_hap_unit: Optional[str] = None,
    ) -> None:
        self.__event_dispatcher = event_dispatcher

        self.__accessory = accessory
        self.__service = service

        self.__id = characteristic_id
        self.__identifier = characteristic_identifier
        self.__data_type = characteristic_data_type
        self.__format = characteristic_format
        self.__invalid = characteristic_invalid
        self.__number_of_decimals = characteristic_number_of_decimals

        self.__queryable = characteristic_queryable
        self.__settable = characteristic_settable

        self.__actual_value = characteristic_value
        self.__expected_value = None

        self.__hap_type_id = characteristic_type_id
        self.__hap_name = characteristic_name
        self.__hap_data_type = (
            characteristic_hap_data_type if characteristic_hap_data_type is not None else HAPDataType.STRING
        )
        self.__hap_valid_values = characteristic_hap_valid_values
        self.__hap_max_length = (
            characteristic_hap_max_length if characteristic_hap_max_length is not None else self.DEFAULT_MAX_LENGTH
        )
        self.__hap_min_value = characteristic_hap_min_value
        self.__hap_max_value = characteristic_hap_max_value
        self.__hap_min_step = characteristic_hap_min_step
        self.__hap_permissions = characteristic_hap_permissions if characteristic_hap_permissions is not None else []
        self.__hap_unit = characteristic_hap_unit

    # -----------------------------------------------------------------------------

    def __repr__(self) -> str:
        """Return the representation of the characteristic"""
        properties: Dict = {
            self.PROP_PERMISSIONS: self.__hap_permissions,
            self.PROP_FORMAT: self.__hap_data_type.value,
        }

        if self.__hap_valid_values is not None:
            properties[self.PROP_VALID_VALUES] = self.__hap_valid_values

        if self.__hap_min_step is not None:
            properties[self.PROP_MIN_STEP] = self.__hap_min_step

        if self.__hap_min_value is not None:
            properties[self.PROP_MIN_VALUE] = self.__hap_min_value

        if self.__hap_max_value is not None:
            properties[self.PROP_MAX_VALUE] = self.__hap_max_value

        if self.__hap_unit is not None:
            properties[self.PROP_UNIT] = self.__hap_unit

        return f"<characteristic display_name={self.name} value={self.get_value()} properties={properties}>"

    # -----------------------------------------------------------------------------

    @property
    def service_id(self) -> uuid.UUID:
        """Characteristic service unique identifier"""
        return self.__service.id

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
        return self.__hap_name

    # -----------------------------------------------------------------------------

    @property
    def data_type(self) -> DataType:
        """Characteristic value data type"""
        return self.__data_type

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
    def invalid(self) -> Union[int, float, str, None]:
        """Invalid value representation"""
        return self.__invalid

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
    def actual_value(self) -> Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None]:
        """Characteristic actual value"""
        return self.__actual_value

    # -----------------------------------------------------------------------------

    @actual_value.setter
    def actual_value(self, value: Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None]) -> None:
        """Set Characteristic actual value"""
        if self.actual_value != value:
            self.__actual_value = value

            if self.settable and self.accessory:
                self.__notify(value=self.actual_value)

        if self.actual_value == self.expected_value:
            self.expected_value = None

    # -----------------------------------------------------------------------------

    @property
    def expected_value(self) -> Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None]:
        """Characteristic expected value"""
        return self.__expected_value

    # -----------------------------------------------------------------------------

    @expected_value.setter
    def expected_value(self, value: Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None]) -> None:
        """Set Characteristic expected value"""
        self.__expected_value = value

    # -----------------------------------------------------------------------------

    @property
    def type_id(self) -> uuid.UUID:
        """Characteristic HAP type identifier"""
        return self.__hap_type_id

    # -----------------------------------------------------------------------------

    @property
    def accessory(self) -> AccessoryRecord:
        """Characteristic HAP accessory"""
        return self.__accessory

    # -----------------------------------------------------------------------------

    @property
    def service(self) -> Optional[ServiceRecord]:
        """Characteristic HAP service"""
        return self.__service

    # -----------------------------------------------------------------------------

    def to_HAP(self) -> Dict:  # pylint: disable=invalid-name
        """HAP service representation builder of this Characteristic"""
        hap_rep = {
            HAP_REPR_IID: self.accessory.iid_manager.get_iid(self),
            HAP_REPR_TYPE: uuid_to_hap_type(self.__hap_type_id),
            HAP_REPR_PERM: self.__hap_permissions,
            HAP_REPR_FORMAT: self.__hap_data_type.value,
        }

        if self.__hap_data_type in (
            HAPDataType.INT,
            HAPDataType.UINT8,
            HAPDataType.UINT16,
            HAPDataType.UINT32,
            HAPDataType.UINT64,
            HAPDataType.FLOAT,
        ):
            if self.__hap_max_value is not None:
                hap_rep[self.PROP_MAX_VALUE] = self.__hap_max_value

            if self.__hap_min_value is not None:
                hap_rep[self.PROP_MIN_VALUE] = self.__hap_min_value

            if self.__hap_min_step is not None:
                hap_rep[self.PROP_MIN_STEP] = self.__hap_min_step

            if self.__hap_unit is not None:
                hap_rep[self.PROP_UNIT] = self.__hap_unit

            if self.__hap_valid_values is not None:
                hap_rep[HAP_REPR_VALID_VALUES] = sorted(self.__hap_valid_values.values())

        elif self.__hap_data_type == HAPDataType.STRING:
            if self.__hap_max_length != self.DEFAULT_MAX_LENGTH:
                hap_rep[HAP_REPR_MAX_LEN] = self.__hap_max_length

        if HAP_PERMISSION_READ in self.__hap_permissions:
            if self.expected_value is not None:
                hap_rep[HAP_REPR_VALUE] = self.__value_to_accessory(value=self.expected_value)

            else:
                hap_rep[HAP_REPR_VALUE] = self.__value_to_accessory(value=self.actual_value)

        return hap_rep

    # -----------------------------------------------------------------------------

    def client_update_value(
        self,
        value: Union[str, int, float, bool, None],
        sender_client_address: Optional[Tuple[str, int]] = None,
    ) -> None:
        """HAP service callback called when value change in Home app"""
        self.expected_value = normalize_value(
            data_type=self.data_type,
            value=DataTransformHelpers.transform_from_accessory(
                data_type=self.data_type,
                value_format=self.format,
                hap_data_type=self.__hap_data_type,
                hap_valid_values=self.__hap_valid_values,
                hap_max_length=self.__hap_max_length,
                hap_min_value=self.__hap_min_value,
                hap_max_value=self.__hap_max_value,
                hap_min_step=self.__hap_min_step,
                value=value,
            ),
            value_format=self.format,
            value_invalid=self.invalid,
        )

        self.__event_dispatcher.dispatch(
            event_id=CharacteristicCommandEvent.EVENT_NAME,
            event=CharacteristicCommandEvent(characteristic_id=self.id, expected_value=self.expected_value),
        )

        if self.__is_always_null():
            self.expected_value = None

        self.__notify(value=self.expected_value, sender_client_address=sender_client_address)

    # -----------------------------------------------------------------------------

    def get_value(self) -> Union[str, int, float, bool, None]:
        """HAP service callback called when actual value reading requested"""
        if self.expected_value is not None:
            return self.__value_to_accessory(value=self.expected_value)

        return self.__value_to_accessory(value=self.actual_value)

    # -----------------------------------------------------------------------------

    def __is_always_null(self) -> bool:
        return self.__hap_type_id in {
            self.__PROGRAMMABLE_SWITCH,
        }

    # -----------------------------------------------------------------------------

    def __immediate_notify(self) -> bool:
        return self.__hap_type_id in {
            self.__BUTTON,
            self.__PROGRAMMABLE_SWITCH,
        }

    # -----------------------------------------------------------------------------

    def __notify(
        self,
        value: Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None],
        sender_client_address: Optional[Tuple[str, int]] = None,
    ) -> None:
        """Notify clients about a value change"""
        self.accessory.publish(
            value=self.__value_to_accessory(value=value),
            sender=self,
            sender_client_address=sender_client_address,
            immediate=self.__immediate_notify(),
        )

    # -----------------------------------------------------------------------------

    def __value_to_accessory(
        self,
        value: Union[str, int, float, bool, datetime, ButtonPayload, SwitchPayload, None],
    ) -> Union[str, int, float, bool, None]:
        return DataTransformHelpers.transform_for_accessory(
            data_type=self.data_type,
            value_format=self.format,
            hap_data_type=self.__hap_data_type,
            hap_valid_values=self.__hap_valid_values,
            hap_max_length=self.__hap_max_length,
            hap_min_value=self.__hap_min_value,
            hap_max_value=self.__hap_max_value,
            hap_min_step=self.__hap_min_step,
            value=value,
        )
