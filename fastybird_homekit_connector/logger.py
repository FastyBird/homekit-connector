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
HomeKit connector logger module
"""

# Python base dependencies
import logging
import uuid
from typing import Dict

# Library libs
from fastybird_homekit_connector.types import CONNECTOR_NAME


class Logger:
    """
    Connector logger

    @package        FastyBird:HomeKitConnector!
    @module         logger

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __connector_id: uuid.UUID

    __logger: logging.Logger

    # -----------------------------------------------------------------------------

    def __init__(
        self,
        connector_id: uuid.UUID,
        logger: logging.Logger = logging.getLogger("dummy"),
    ) -> None:
        self.__connector_id = connector_id

        self.__logger = logger

    # -----------------------------------------------------------------------------

    def set_logger(self, logger: logging.Logger) -> None:
        """Configure custom logger handler"""
        self.__logger = logger

    # -----------------------------------------------------------------------------

    @property
    def level(self) -> int:
        """Logger configured logging level"""
        return self.__logger.level

    # -----------------------------------------------------------------------------

    def debug(self, msg: str, *args, **kwargs) -> None:  # type: ignore[no-untyped-def]
        """Log debugging message"""
        extra = self.__get_connector_extra()

        if "extra" in kwargs:
            extra = {**extra, **kwargs.get("extra", {})}
            del kwargs["extra"]

        self.__logger.debug(msg, extra=extra, *args, **kwargs)

    # -----------------------------------------------------------------------------

    def info(self, msg: str, *args, **kwargs) -> None:  # type: ignore[no-untyped-def]
        """Log information message"""
        extra = self.__get_connector_extra()

        if "extra" in kwargs:
            extra = {**extra, **kwargs.get("extra", {})}
            del kwargs["extra"]

        self.__logger.info(msg, extra=extra, *args, **kwargs)

    # -----------------------------------------------------------------------------

    def warning(self, msg: str, *args, **kwargs) -> None:  # type: ignore[no-untyped-def]
        """Log warning message"""
        extra = self.__get_connector_extra()

        if "extra" in kwargs:
            extra = {**extra, **kwargs.get("extra", {})}
            del kwargs["extra"]

        self.__logger.warning(msg, extra=extra, *args, **kwargs)

    # -----------------------------------------------------------------------------

    def error(self, msg: str, *args, **kwargs) -> None:  # type: ignore[no-untyped-def]
        """Log error message"""
        extra = self.__get_connector_extra()

        if "extra" in kwargs:
            extra = {**extra, **kwargs.get("extra", {})}
            del kwargs["extra"]

        self.__logger.error(msg, extra=extra, *args, **kwargs)

    # -----------------------------------------------------------------------------

    def exception(self, msg: Exception) -> None:
        """Log thrown exception"""
        self.__logger.exception(msg)

    # -----------------------------------------------------------------------------

    def __get_connector_extra(self) -> Dict:
        return {
            "connector": {
                "type": CONNECTOR_NAME,
                "id": self.__connector_id.__str__(),
            },
        }
