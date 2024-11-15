import { App } from 'vue';
import defaultsDeep from 'lodash.defaultsdeep';

import { ConnectorSource } from '@fastybird/metadata-library';
import { connectorPlugins, IConnectorOptions } from '@fastybird/devices-module';

import { ConnectorDetail, ConnectorDevices, ConnectorSettingsEdit } from './components';

import { InstallFunction } from './types';
import locales from './locales';

export default function createHomeKitConnector(): InstallFunction {
	return {
		install(_app: App, options: IConnectorOptions): void {
			if (this.installed) {
				return;
			}
			this.installed = true;

			for (const [locale, translations] of Object.entries(locales)) {
				const currentMessages = options.i18n?.global.getLocaleMessage(locale);
				const mergedMessages = defaultsDeep(currentMessages, { devicesModule: translations });

				options.i18n?.global.setLocaleMessage(locale, mergedMessages);
			}

			connectorPlugins.push({
				type: 'homekit-connector',
				source: ConnectorSource.HOMEKIT,
				name: 'HomeKit',
				description: 'FastyBird IoT connector for HomeKit Accessory Protocol',
				links: {
					documentation: 'http://www.fastybird.com',
					devDocumentation: 'http://www.fastybird.com',
					bugsTracking: 'http://www.fastybird.com',
				},
				components: {
					connectorDetail: ConnectorDetail,
					connectorDevices: ConnectorDevices,
					editConnector: ConnectorSettingsEdit,
				},
				core: true,
			});
		},
	};
}

export * from './components';

export * from './types';
