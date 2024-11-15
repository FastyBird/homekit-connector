<template>
	<el-row class="b-b b-b-solid">
		<el-col :span="10">
			<div class="flex flex-col items-center">
				<vue-qrcode
					v-if="xhmUriProperty !== null"
					:value="`${xhmUriProperty.value}`"
					:options="{ width: 180 }"
					class="block"
				/>
				<el-text
					size="large"
					class="font-600"
					>{{ pinCodeProperty?.value }}</el-text
				>
			</div>
		</el-col>
		<el-col :span="14">
			<dl class="grid m-0">
				<dt
					class="b-l b-l-solid b-b b-b-solid b-r b-r-solid py-1 px-2 flex items-center justify-end"
					style="background: var(--el-fill-color-light)"
				>
					{{ t('homeKitConnector.texts.connectors.devices') }}
				</dt>
				<dd class="col-start-2 b-b b-b-solid m-0 p-2 flex items-center">
					<el-text>
						<i18n-t
							keypath="homeKitConnector.texts.connectors.devicesCount"
							:plural="props.connectorData.devices.length"
						>
							<template #count>
								<strong>{{ props.connectorData.devices.length }}</strong>
							</template>
						</i18n-t>
					</el-text>
				</dd>
				<dt
					class="b-l b-l-solid b-b b-b-solid b-r b-r-solid py-1 px-2 flex items-center justify-end"
					style="background: var(--el-fill-color-light)"
				>
					{{ t('homeKitConnector.texts.connectors.status') }}
				</dt>
				<dd class="col-start-2 b-b b-b-solid m-0 p-2 flex items-center">
					<el-text>
						<el-tag
							:type="stateColor"
							size="small"
						>
							{{ t(`homeKitConnector.misc.state.${connectorState.toLowerCase()}`) }}
						</el-tag>
					</el-text>
				</dd>
				<dt
					class="b-l b-l-solid b-b b-b-solid b-r b-r-solid py-1 px-2 flex items-center justify-end"
					style="background: var(--el-fill-color-light)"
				>
					{{ t('homeKitConnector.texts.connectors.service') }}
				</dt>
				<dd class="col-start-2 b-b b-b-solid m-0 p-2 flex items-center">
					<el-text>
						<el-tag
							v-if="props.service === null"
							size="small"
							type="danger"
						>
							{{ t('homeKitConnector.misc.missing') }}
						</el-tag>
						<el-tag
							v-else
							:type="props.service.running ? 'success' : 'danger'"
							size="small"
						>
							{{ props.service.running ? t('homeKitConnector.misc.state.running') : t('homeKitConnector.misc.state.stopped') }}
						</el-tag>
					</el-text>
				</dd>
				<dt
					class="b-l b-l-solid b-b b-b-solid b-r b-r-solid py-1 px-2 flex items-center justify-end"
					style="background: var(--el-fill-color-light)"
				>
					{{ t('homeKitConnector.texts.connectors.bridges') }}
				</dt>
				<dd class="col-start-2 b-b b-b-solid m-0 p-2 flex items-center">
					<el-text>
						<i18n-t
							keypath="homeKitConnector.texts.connectors.bridgesCount"
							:plural="props.bridges.length"
						>
							<template #count>
								<strong>{{ props.bridges.length }}</strong>
							</template>
						</i18n-t>
					</el-text>
				</dd>
				<dt
					class="b-l b-l-solid b-b b-b-solid b-r b-r-solid py-1 px-2 flex items-center justify-end"
					style="background: var(--el-fill-color-light)"
				>
					{{ t('homeKitConnector.texts.connectors.alerts') }}
				</dt>
				<dd class="col-start-2 b-b b-b-solid m-0 p-2 flex items-center">
					<el-text>
						<el-tag
							size="small"
							:type="props.alerts.length === 0 ? 'success' : 'danger'"
						>
							<i18n-t
								keypath="homeKitConnector.texts.connectors.alertsCount"
								:plural="props.alerts.length"
							>
								<template #count>
									<strong>{{ props.alerts.length }}</strong>
								</template>
							</i18n-t>
						</el-tag>
					</el-text>
				</dd>
				<dt
					class="b-l b-l-solid b-r b-r-solid py-1 px-2 flex items-center justify-end"
					style="background: var(--el-fill-color-light)"
				>
					{{ t('homeKitConnector.misc.paired') }}
				</dt>
				<dd class="col-start-2 m-0 p-2 flex items-center">
					<el-text>
						<el-tag
							size="small"
							:type="pairedProperty?.value === true ? 'success' : 'info'"
						>
							{{ t('homeKitConnector.texts.connectors.paired') }}
						</el-tag>
					</el-text>
				</dd>
			</dl>
		</el-col>
	</el-row>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { I18nT, useI18n } from 'vue-i18n';
import { ElText, ElRow, ElCol, ElTag } from 'element-plus';

import VueQrcode from '@chenfengyuan/vue-qrcode';
import { ConnectionState } from '@fastybird/metadata-library';
import { useWampV1Client } from '@fastybird/vue-wamp-v1';
import { IConnectorDetailProps, IConnectorProperty, useConnectorState } from '@fastybird/devices-module';

import { ConnectorPropertyIdentifier } from '../types';

type StateColor = 'info' | 'warning' | 'success' | 'primary' | 'danger' | undefined;

defineOptions({
	name: 'ConnectorDetail',
});

const props = defineProps<IConnectorDetailProps>();

const { t } = useI18n();

const { status: wsStatus } = useWampV1Client();

const { state: connectorState } = useConnectorState(props.connectorData.connector);

const xhmUriProperty = computed<IConnectorProperty | null>((): IConnectorProperty | null => {
	return props.connectorData.properties.find((property) => property.identifier === ConnectorPropertyIdentifier.XHM_URI) ?? null;
});

const pinCodeProperty = computed<IConnectorProperty | null>((): IConnectorProperty | null => {
	return props.connectorData.properties.find((property) => property.identifier === ConnectorPropertyIdentifier.PIN_CODE) ?? null;
});

const pairedProperty = computed<IConnectorProperty | null>((): IConnectorProperty | null => {
	return props.connectorData.properties.find((property) => property.identifier === ConnectorPropertyIdentifier.PAIRED) ?? null;
});

const stateColor = computed<StateColor>((): StateColor => {
	if (!wsStatus || [ConnectionState.UNKNOWN].includes(connectorState.value)) {
		return undefined;
	}

	if ([ConnectionState.CONNECTED].includes(connectorState.value)) {
		return 'success';
	} else if ([ConnectionState.DISCONNECTED].includes(connectorState.value)) {
		return 'warning';
	}

	return 'danger';
});
</script>
