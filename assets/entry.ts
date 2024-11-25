import { App } from 'vue';

import defaultsDeep from 'lodash.defaultsdeep';

import { connectorPlugins } from '@fastybird/devices-module';
import { ConnectorSource } from '@fastybird/metadata-library';
import { IExtensionOptions } from '@fastybird/tools';

import { ConnectorDetail, ConnectorDevices, ConnectorSettingsEdit } from './components';
import locales, { MessageSchema } from './locales';

export default {
	install: (_app: App, options: IExtensionOptions<{ 'en-US': MessageSchema }>): void => {
		for (const [locale, translations] of Object.entries(locales)) {
			const currentMessages = options.i18n.global.getLocaleMessage(locale);
			const mergedMessages = defaultsDeep(currentMessages, { homeKitConnector: translations });

			options.i18n.global.setLocaleMessage(locale, mergedMessages);
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

export * from './components';

export * from './types';
