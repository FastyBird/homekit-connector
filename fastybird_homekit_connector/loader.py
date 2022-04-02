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
HomeKit connector HAP loader
"""

# Library dependencies
from pyhap.loader import Loader as PyhapLoader
from pyhap.util import hap_type_to_uuid

from fastybird_homekit_connector.registry.records import CharacteristicRecord, ServiceRecord


class Loader(PyhapLoader):
    """
    HomeKit services loader

    @package        FastyBird:HomeKitConnector!
    @module         loader

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    def get_char(self, name: str) -> CharacteristicRecord:
        """Return new Characteristic instance"""
        char_dict = self.char_types[name].copy()

        if (
            "Format" not in char_dict
            or "Permissions" not in char_dict
            or "UUID" not in char_dict
        ):
            raise KeyError(f"Could not load char {name}!")

        type_id = hap_type_to_uuid(char_dict.pop("UUID"))

        char = CharacteristicRecord(name, type_id, properties=char_dict)

        char._loader_display_name = (  # pylint: disable=protected-access
            char.display_name
        )

        return char

    # -----------------------------------------------------------------------------

    def get_service(self, name: str) -> ServiceRecord:
        """Return new service instance"""
        service_dict = self.serv_types[name].copy()

        if "RequiredCharacteristics" not in service_dict or "UUID" not in service_dict:
            raise KeyError(f"Could not load service {name}!")

        type_id = hap_type_to_uuid(service_dict.pop("UUID"))

        service = ServiceRecord(type_id, name)

        for char_name in service_dict["RequiredCharacteristics"]:
            service.add_characteristic(self.get_char(char_name))

        return service
