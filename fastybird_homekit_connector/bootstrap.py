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

# Library dependencies
from kink import di
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


def create_connector(
    connector: HomeKitConnectorEntity,
    logger: logging.Logger = logging.getLogger("dummy"),
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
        accessories_registry=di[AccessoriesRegistry],
        services_registry=di[ServicesRegistry],
        characteristics_registry=di[CharacteristicsRegistry],
        events_listener=di[EventsListener],
        logger=connector_logger,
    )
    di[HomeKitConnector] = connector_service
    di["homekit-connector_connector"] = connector_service

    return connector_service
