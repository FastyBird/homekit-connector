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
HomeKit connector entities module
"""

# Library dependencies
from typing import Union

# Library dependencies
from fastybird_devices_module.entities.connector import (
    ConnectorEntity,
    ConnectorStaticPropertyEntity,
)
from fastybird_devices_module.entities.device import DeviceEntity
from fastybird_metadata.types import ConnectorSource, ModuleSource, PluginSource
from pyhap.util import generate_pincode

# Library libs
from fastybird_homekit_connector.types import (
    CONNECTOR_NAME,
    DEVICE_NAME,
    ConnectorAttribute,
)

DEFAULT_PORT: int = 51826


class HomeKitConnectorEntity(ConnectorEntity):
    """
    HomeKit connector entity

    @package        FastyBird:HomeKitConnector!
    @module         entities

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __mapper_args__ = {"polymorphic_identity": CONNECTOR_NAME}

    # -----------------------------------------------------------------------------

    @property
    def type(self) -> str:
        """Connector type"""
        return CONNECTOR_NAME

    # -----------------------------------------------------------------------------

    @property
    def source(self) -> Union[ModuleSource, ConnectorSource, PluginSource]:
        """Entity source type"""
        return ConnectorSource.HOMEKIT_CONNECTOR

    # -----------------------------------------------------------------------------

    @property
    def port(self) -> int:
        """Connector network port"""
        port_property = next(
            iter([record for record in self.properties if record.identifier == ConnectorAttribute.PORT.value]),
            None,
        )

        if (
            port_property is None
            or not isinstance(port_property, ConnectorStaticPropertyEntity)
            or not isinstance(port_property.value, int)
        ):
            return DEFAULT_PORT

        return port_property.value

    # -----------------------------------------------------------------------------

    @property
    def pincode(self) -> bytearray:
        """Connector PIN code"""
        pincode_property = next(
            iter([record for record in self.properties if record.identifier == ConnectorAttribute.PINCODE.value]),
            None,
        )

        if (
            pincode_property is None
            or not isinstance(pincode_property, ConnectorStaticPropertyEntity)
            or not isinstance(pincode_property.value, str)
        ):
            return generate_pincode()

        return bytearray(pincode_property.value.encode("ascii"))


class HomeKitDeviceEntity(DeviceEntity):  # pylint: disable=too-few-public-methods
    """
    HomeKit device entity

    @package        FastyBird:HomeKitConnector!
    @module         entities

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __mapper_args__ = {"polymorphic_identity": DEVICE_NAME}

    # -----------------------------------------------------------------------------

    @property
    def type(self) -> str:
        """Device type"""
        return DEVICE_NAME

    # -----------------------------------------------------------------------------

    @property
    def source(self) -> Union[ModuleSource, ConnectorSource, PluginSource]:
        """Entity source type"""
        return ConnectorSource.HOMEKIT_CONNECTOR
