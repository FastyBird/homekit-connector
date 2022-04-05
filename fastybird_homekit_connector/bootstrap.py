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
HomeKit connector DI container
"""

# pylint: disable=no-value-for-parameter

# Python base dependencies
import logging
import os
import pathlib
import re
from asyncio import AbstractEventLoop
from os import path
from typing import Optional

# Library dependencies
from kink import di, inject
from pyhap.accessory import Bridge
from pyhap.accessory_driver import AccessoryDriver
from whistle import EventDispatcher

# Library libs
from fastybird_homekit_connector.connector import HomeKitConnector
from fastybird_homekit_connector.entities import HomeKitConnectorEntity
from fastybird_homekit_connector.events.listeners import EventsListener
from fastybird_homekit_connector.logger import Logger
from fastybird_homekit_connector.registry.model import (
    AccessoriesRegistry,
    CharacteristicsRegistry,
    ServicesRegistry,
)
from fastybird_homekit_connector.utils import KeyHashHelpers


def find_version(version_file_path: str) -> str:
    """Read connector version"""
    with open(version_file_path, "r", encoding="utf8") as file:
        version_match = re.search(r"^__version__ = ['\"]([^'\"]*)['\"]", file.read(), re.M)

        if version_match:
            return version_match.group(1)

    return "0.0.0"


@inject(
    bind={
        "loop": AbstractEventLoop,
    },
)
def create_connector(
    connector: HomeKitConnectorEntity,
    logger: logging.Logger = logging.getLogger("dummy"),
    loop: Optional[AbstractEventLoop] = None,
) -> HomeKitConnector:
    """Create HomeKit connector services"""
    if isinstance(logger, logging.Logger):
        connector_logger = Logger(connector_id=connector.id, logger=logger)

        di[Logger] = connector_logger
        di["homekit-connector_logger"] = di[Logger]

    else:
        connector_logger = logger

    di[EventDispatcher] = EventDispatcher()
    di["homekit-connector_events-dispatcher"] = di[EventDispatcher]

    # HomeKit core services
    di[AccessoryDriver] = AccessoryDriver(
        port=connector.port,
        pincode=connector.pincode,
        loop=loop,
        persist_file=("/var/miniserver-gateway/miniserver_gateway/homekit.accessory.state".replace("/", path.sep)),
    )
    di["homekit-connector_accessory-driver"] = di[AccessoryDriver]

    bridge = Bridge(
        driver=di[AccessoryDriver], display_name=connector.name if connector.name is not None else "Virtual gateway"
    )
    bridge.set_info_service(
        firmware_revision=find_version(os.path.join(pathlib.Path(__file__).parent.resolve(), "__init__.py")),
        manufacturer="FastyBird",
        model="Virtual gateway",
        serial_number=KeyHashHelpers.encode(connector.id.__int__()),
    )
    di[Bridge] = bridge
    di["homekit-connector_accessory-bridge"] = di[Bridge]

    di[AccessoryDriver].add_accessory(accessory=di[Bridge])

    # Registers
    di[CharacteristicsRegistry] = CharacteristicsRegistry(  # type: ignore[call-arg]
        event_dispatcher=di[EventDispatcher],
    )
    di["homekit-connector_characteristics-registry"] = di[CharacteristicsRegistry]
    di[ServicesRegistry] = ServicesRegistry(characteristics_registry=di[CharacteristicsRegistry])
    di["homekit-connector_services-registry"] = di[ServicesRegistry]
    di[AccessoriesRegistry] = AccessoriesRegistry(services_registry=di[ServicesRegistry])
    di["homekit-connector_accessories-registry"] = di[AccessoriesRegistry]

    # Inner events system
    di[EventsListener] = EventsListener(  # type: ignore[call-arg]
        characteristics_registry=di[CharacteristicsRegistry],
        event_dispatcher=di[EventDispatcher],
        logger=connector_logger,
    )
    di["homekit-connector_events-listener"] = di[EventsListener]

    # Main connector service
    connector_service = HomeKitConnector(  # type: ignore[call-arg]
        connector_id=connector.id,
        accessory_driver=di[AccessoryDriver],
        accessory_bridge=di[Bridge],
        accessories_registry=di[AccessoriesRegistry],
        services_registry=di[ServicesRegistry],
        characteristics_registry=di[CharacteristicsRegistry],
        events_listener=di[EventsListener],
        logger=connector_logger,
    )
    di[HomeKitConnector] = connector_service
    di["homekit-connector_connector"] = connector_service

    return connector_service
