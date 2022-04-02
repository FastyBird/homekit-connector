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
HomeKit connector registry module models
"""

# Python base dependencies
import json
import uuid
from typing import Dict, List, Optional, Tuple, Union

# Library dependencies
from fastybird_metadata.types import DataType
from inflection import camelize, underscore
from pyhap import CHARACTERISTICS_FILE, SERVICES_FILE
from pyhap.accessory_driver import AccessoryDriver
from pyhap.util import hap_type_to_uuid

# Library libs
from fastybird_homekit_connector.exceptions import InvalidStateException
from fastybird_homekit_connector.registry.records import (
    AccessoryRecord,
    CharacteristicRecord,
    ServiceRecord,
)
from fastybird_homekit_connector.types import HAPDataType


def read_definition_file(path):
    """Read file and return a dict"""
    with open(path, "r", encoding="utf8") as file:
        return json.load(file)


class AccessoriesRegistry:
    """
    Accessories registry

    @package        FastyBird:HomeKitConnector!
    @module         registry/model

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __items: Dict[str, AccessoryRecord] = {}

    __iterator_index = 0

    __services_registry: "ServicesRegistry"

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        services_registry: "ServicesRegistry",
    ) -> None:
        self.__items = {}

        self.__services_registry = services_registry

    # -----------------------------------------------------------------------------

    def get_by_id(self, accessory_id: uuid.UUID) -> Optional[AccessoryRecord]:
        """Find accessory in registry by given unique identifier"""
        items = self.__items.copy()

        return next(
            iter([record for record in items.values() if accessory_id.__eq__(record.id)]),
            None,
        )

    # -----------------------------------------------------------------------------

    def append(
        self,
        accessory_id: uuid.UUID,
        accessory_name: str,
        accessory_enabled: bool,
        driver: AccessoryDriver,
    ) -> AccessoryRecord:
        """Append accessory record into registry"""
        accessory_record = AccessoryRecord(
            accessory_id=accessory_id,
            accessory_enabled=accessory_enabled,
            accessory_name=accessory_name,
            driver=driver,
        )

        self.__items[accessory_record.id.__str__()] = accessory_record

        return accessory_record

    # -----------------------------------------------------------------------------

    def remove(self, accessory_id: uuid.UUID) -> None:
        """Remove accessory from registry"""
        items = self.__items.copy()

        for record in items.values():
            if accessory_id.__eq__(record.id):
                try:
                    del self.__items[record.id.__str__()]

                    self.__services_registry.reset(accessory_id=record.id)

                except KeyError:
                    pass

                break

    # -----------------------------------------------------------------------------

    def reset(self) -> None:
        """Reset accessories registry to initial state"""
        items = self.__items.copy()

        for record in items.values():
            self.__services_registry.reset(accessory_id=record.id)

        self.__items = {}

    # -----------------------------------------------------------------------------

    def enable(self, accessory: AccessoryRecord) -> AccessoryRecord:
        """Enable accessory for communication"""
        accessory.enabled = True

        self.__update(accessory=accessory)

        updated_accessory = self.get_by_id(accessory.id)

        if updated_accessory is None:
            raise InvalidStateException("Accessory record could not be re-fetched from registry after update")

        return updated_accessory

    # -----------------------------------------------------------------------------

    def disable(self, accessory: AccessoryRecord) -> AccessoryRecord:
        """Enable accessory for communication"""
        accessory.enabled = False

        self.__update(accessory=accessory)

        updated_accessory = self.get_by_id(accessory.id)

        if updated_accessory is None:
            raise InvalidStateException("Accessory record could not be re-fetched from registry after update")

        return updated_accessory

    # -----------------------------------------------------------------------------

    def __update(self, accessory: AccessoryRecord) -> bool:
        items = self.__items.copy()

        for record in items.values():
            if record.id == accessory.id:
                self.__items[accessory.id.__str__()] = accessory

                return True

        return False

    # -----------------------------------------------------------------------------

    def __iter__(self) -> "AccessoriesRegistry":
        # Reset index for nex iteration
        self.__iterator_index = 0

        return self

    # -----------------------------------------------------------------------------

    def __len__(self) -> int:
        return len(self.__items.values())

    # -----------------------------------------------------------------------------

    def __next__(self) -> AccessoryRecord:
        if self.__iterator_index < len(self.__items.values()):
            items: List[AccessoryRecord] = list(self.__items.values())

            result: AccessoryRecord = items[self.__iterator_index]

            self.__iterator_index += 1

            return result

        # Reset index for nex iteration
        self.__iterator_index = 0

        # End of iteration
        raise StopIteration


class ServicesRegistry:
    """
    Services registry

    @package        FastyBird:HomeKitConnector!
    @module         registry/model

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __items: Dict[str, ServiceRecord] = {}

    __characteristics_registry: "CharacteristicsRegistry"

    __services_definitions: Dict

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        characteristics_registry: "CharacteristicsRegistry",
        services_definitions: str = SERVICES_FILE,
    ) -> None:
        self.__items = {}

        self.__characteristics_registry = characteristics_registry

        self.__services_definitions = read_definition_file(services_definitions)

    # -----------------------------------------------------------------------------

    def get_by_id(self, service_id: uuid.UUID) -> Optional[ServiceRecord]:
        """Find service in registry by given unique identifier"""
        items = self.__items.copy()

        return next(
            iter([record for record in items.values() if service_id.__eq__(record.id)]),
            None,
        )

    # -----------------------------------------------------------------------------

    def get_by_identifier(self, accessory_id: uuid.UUID, service_identifier: int) -> Optional[ServiceRecord]:
        """Find service in registry by given unique shelly identifier and accessory unique identifier"""
        items = self.__items.copy()

        return next(
            iter(
                [
                    record
                    for record in items.values()
                    if accessory_id.__eq__(record.accessory_id) and record.identifier == service_identifier
                ]
            ),
            None,
        )

    # -----------------------------------------------------------------------------

    def get_all_by_accessory(self, accessory_id: uuid.UUID) -> List[ServiceRecord]:
        """Find services in registry by accessory unique identifier"""
        items = self.__items.copy()

        return list(iter([record for record in items.values() if accessory_id.__eq__(record.accessory_id)]))

    # -----------------------------------------------------------------------------

    def append(
        self,
        accessory_id: uuid.UUID,
        service_id: uuid.UUID,
        service_identifier: str,
    ) -> ServiceRecord:
        """Append service record into registry"""
        service_name = camelize(underscore(service_identifier))

        if service_name not in self.__services_definitions:
            raise AttributeError(f"Provided invalid service name: {service_name}")

        service_config: Dict = self.__services_definitions[service_name].copy()

        if "UUID" not in service_config or not isinstance(service_config, dict):
            raise KeyError(f"Could not load service: {service_name}")

        service_record: ServiceRecord = ServiceRecord(
            accessory_id=accessory_id,
            service_id=service_id,
            service_identifier=service_identifier,
            service_name=service_name,
            service_type_id=hap_type_to_uuid(service_config.pop("UUID")),
        )

        self.__items[service_record.id.__str__()] = service_record

        return service_record

    # -----------------------------------------------------------------------------

    def remove(self, service_id: uuid.UUID) -> None:
        """Remove service from registry"""
        items = self.__items.copy()

        for record in items.values():
            if service_id.__eq__(record.id):
                try:
                    del self.__items[record.id.__str__()]

                    self.__characteristics_registry.reset(service_id=record.id)

                except KeyError:
                    pass

                break

    # -----------------------------------------------------------------------------

    def reset(self, accessory_id: Optional[uuid.UUID] = None) -> None:
        """Reset services registry to initial state"""
        items = self.__items.copy()

        if accessory_id is not None:
            for record in items.values():
                if accessory_id.__eq__(record.accessory_id):
                    self.remove(service_id=record.id)

        else:
            for record in items.values():
                self.__characteristics_registry.reset(service_id=record.id)

            self.__items = {}


class CharacteristicsRegistry:
    """
    Characteristics registry

    @package        FastyBird:HomeKitConnector!
    @module         registry/model

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __items: Dict[str, CharacteristicRecord] = {}

    __iterator_index = 0

    __characteristics_definitions: Dict

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        characteristics_definitions: str = CHARACTERISTICS_FILE,
    ) -> None:
        self.__items = {}

        self.__characteristics_definitions = read_definition_file(characteristics_definitions)

    # -----------------------------------------------------------------------------

    def get_by_id(self, characteristic_id: uuid.UUID) -> Optional[CharacteristicRecord]:
        """Find characteristic in registry by given unique identifier"""
        items = self.__items.copy()

        return next(
            iter([record for record in items.values() if characteristic_id.__eq__(record.id)]),
            None,
        )

    # -----------------------------------------------------------------------------

    def get_by_identifier(
        self, service_id: uuid.UUID, characteristic_identifier: int
    ) -> Optional[CharacteristicRecord]:
        """Find characteristic in registry by given unique shelly identifier and service unique identifier"""
        items = self.__items.copy()

        return next(
            iter(
                [
                    record
                    for record in items.values()
                    if service_id.__eq__(record.service_id) and record.identifier == characteristic_identifier
                ]
            ),
            None,
        )

    # -----------------------------------------------------------------------------

    def get_all_for_service(self, service_id: uuid.UUID) -> List[CharacteristicRecord]:
        """Find characteristic in registry by service unique identifier"""
        items = self.__items.copy()

        return [record for record in items.values() if service_id.__eq__(record.service_id)]

    # -----------------------------------------------------------------------------

    def append(  # pylint: disable=too-many-arguments
        self,
        service_id: uuid.UUID,
        characteristic_id: uuid.UUID,
        characteristic_identifier: str,
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
    ) -> CharacteristicRecord:
        """Append characteristic record into registry"""
        characteristic_name = camelize(underscore(characteristic_identifier))

        if characteristic_name not in self.__characteristics_definitions:
            raise AttributeError(f"Provided invalid characteristic name: {characteristic_name}")

        characteristic_config: Dict = self.__characteristics_definitions[characteristic_name].copy()

        if "UUID" not in characteristic_config or not isinstance(characteristic_config, dict):
            raise KeyError(f"Could not load service: {characteristic_name}")

        hap_data_type: Optional[HAPDataType] = None

        if "Format" in characteristic_config and HAPDataType.has_value(characteristic_config.get("Format")):
            hap_data_type = HAPDataType(characteristic_config.get("Format"))

        characteristic_record: CharacteristicRecord = CharacteristicRecord(
            service_id=service_id,
            characteristic_id=characteristic_id,
            characteristic_identifier=characteristic_identifier,
            characteristic_name=characteristic_name,
            characteristic_type_id=hap_type_to_uuid(characteristic_config.pop("UUID")),
            characteristic_properties=characteristic_config,
            characteristic_data_type=characteristic_data_type,
            characteristic_format=characteristic_format,
            characteristic_number_of_decimals=characteristic_number_of_decimals,
            characteristic_queryable=characteristic_queryable,
            characteristic_settable=characteristic_settable,
            characteristic_hap_data_type=hap_data_type,
        )

        self.__items[characteristic_record.id.__str__()] = characteristic_record

        return characteristic_record

    # -----------------------------------------------------------------------------

    def remove(self, characteristic_id: uuid.UUID) -> None:
        """Remove characteristic from registry"""
        items = self.__items.copy()

        for record in items.values():
            if characteristic_id.__eq__(record.id):
                try:
                    del self.__items[record.id.__str__()]

                except KeyError:
                    pass

                break

    # -----------------------------------------------------------------------------

    def reset(self, service_id: Optional[uuid.UUID] = None) -> None:
        """Reset characteristics&states registry to initial state"""
        items = self.__items.copy()

        if service_id is not None:
            for record in items.values():
                if service_id.__eq__(record.service_id):
                    self.remove(characteristic_id=record.id)

        else:
            self.__items = {}

    # -----------------------------------------------------------------------------

    def __update(self, characteristic: CharacteristicRecord) -> bool:
        items = self.__items.copy()

        for record in items.values():
            if record.id == characteristic.id:
                self.__items[characteristic.id.__str__()] = characteristic

                return True

        return False

    # -----------------------------------------------------------------------------

    def __iter__(self) -> "CharacteristicsRegistry":
        # Reset index for nex iteration
        self.__iterator_index = 0

        return self

    # -----------------------------------------------------------------------------

    def __len__(self) -> int:
        return len(self.__items.values())

    # -----------------------------------------------------------------------------

    def __next__(self) -> CharacteristicRecord:
        if self.__iterator_index < len(self.__items.values()):
            items: List[CharacteristicRecord] = list(self.__items.values())

            result: CharacteristicRecord = items[self.__iterator_index]

            self.__iterator_index += 1

            return result

        # Reset index for nex iteration
        self.__iterator_index = 0

        # End of iteration
        raise StopIteration
