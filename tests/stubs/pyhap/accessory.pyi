from typing import Optional

from pyhap.accessory_driver import AccessoryDriver


class Bridge(object):
    def __init__(self, display_name: str, driver: AccessoryDriver) -> None: ...

    def add_accessory(self, acc: object) -> None: ...

    def set_info_service(
        self,
        firmware_revision: Optional[str] = None,
        manufacturer: Optional[str] = None,
        model: Optional[str] = None,
        serial_number: Optional[str] = None,
    ) -> None: ...