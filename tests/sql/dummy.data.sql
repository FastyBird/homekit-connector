INSERT
IGNORE INTO `fb_devices_module_connectors` (`connector_id`, `connector_identifier`, `connector_name`, `connector_comment`, `connector_enabled`, `connector_type`, `created_at`, `updated_at`) VALUES
(_binary 0xf5a8691b49174866878f5217193cf14b, 'homekit', 'HomeKit Bridge', null, true, 'blank', '2022-09-26 09:50:00', '2022-09-26 09:50:00');

INSERT
IGNORE INTO `fb_homekit_connector_clients` (`client_id`, `connector_id`, `client_uid`, `client_public_key`, `client_admin`, `created_at`, `updated_at`) VALUES
(binary 0x32998061e28e43aeb506fe7bc2311498, binary 0xf5a8691b49174866878f5217193cf14b, 'e348f5fc-42de-459e-926e-2f4cd039c665', X'B05CCFFFC6B16636573F3130517FF401539B60B6B86553D717F010BEA95A9BD9', 1, '2022-09-26 09:51:40', '2022-09-26 09:51:40');
