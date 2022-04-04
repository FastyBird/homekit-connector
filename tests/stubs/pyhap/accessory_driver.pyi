from asyncio import AbstractEventLoop
from typing import Any, Optional, Dict, Tuple

from pyhap.loader import Loader
from pyhap.state import State


class AccessoryDriver(object):
    def __init__(
        self,
        port: int,
        persist_file: str,
        pincode: bytearray,
        loop: Optional[AbstractEventLoop],
    ) -> None: ...

    @property
    def loop(self) -> AbstractEventLoop: ...

    @property
    def state(self) -> State: ...

    @property
    def loader(self) -> Loader: ...

    def add_accessory(self, accessory: Any) -> None: ...

    def start_service(self) -> None: ...

    def stop(self) -> None: ...

    def publish(
        self,
        data: Dict[str, Any],
        sender_client_addr: Optional[Tuple[str, int]] = None,
        immediate: bool = False,
    ) -> None: ...