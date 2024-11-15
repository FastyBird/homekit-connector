<template>
	<div
		class="w-[125px] h-[125px] border-1 border-style-solid rounded-lg box-border p-2 flex flex-col overflow-hidden cursor-pointer"
		@click.prevent="emit('detail', $event)"
	>
		<div class="flex-grow flex flex-row justify-between">
			<el-icon
				v-if="categoryProperty !== null"
				:size="45"
			>
				<device-icon :category="category" />
			</el-icon>

			<div class="flex flex-col">
				<div class="text-center p-1">
					<el-text :type="stateColor">
						<el-icon>
							<component :is="stateIcon" />
						</el-icon>
					</el-text>
				</div>
				<div class="text-center p-1">
					<el-button
						:icon="FasCircleInfo"
						size="small"
						circle
						@click.prevent="emit('detail', $event)"
					/>
				</div>
			</div>
		</div>
		<el-tooltip
			:content="title"
			placement="top"
		>
			<el-text
				line-clamp="2"
				class="font-500"
			>
				{{ title }}
			</el-text>
		</el-tooltip>
	</div>
</template>

<script setup lang="ts">
import { type Component, computed } from 'vue';
import { ElButton, ElIcon, ElText, ElTooltip } from 'element-plus';

import {
	FasCircleExclamation,
	FasCircleInfo,
	FarCirclePause,
	FarCirclePlay,
	FarCircleQuestion,
	FarCircleStop,
	FarCircleUser,
} from '@fastybird/web-ui-icons';
import { ConnectionState } from '@fastybird/metadata-library';
import { useWampV1Client } from '@fastybird/vue-wamp-v1';
import { IConnectorDeviceProps, IDeviceProperty, useEntityTitle, useDeviceState, IConnectorDeviceEmits } from '@fastybird/devices-module';

import { DeviceIcon } from '../components';
import { DeviceCategory, DevicePropertyIdentifier } from '../types';

type StateColor = 'info' | 'warning' | 'success' | 'primary' | 'danger' | undefined;

defineOptions({
	name: 'ConnectorDevice',
});

const props = defineProps<IConnectorDeviceProps>();

const emit = defineEmits<IConnectorDeviceEmits>();

const title = useEntityTitle(props.deviceData.device);

const { status: wsStatus } = useWampV1Client();

const { state: deviceState } = useDeviceState(props.deviceData.device);

const categoryProperty = computed<IDeviceProperty | null>((): IDeviceProperty | null => {
	return props.deviceData.properties.find((property) => property.identifier === DevicePropertyIdentifier.CATEGORY) ?? null;
});

const stateIcon = computed<Component>((): Component => {
	if ([ConnectionState.RUNNING, ConnectionState.READY, ConnectionState.CONNECTED].includes(deviceState.value)) {
		return FarCirclePlay;
	} else if ([ConnectionState.SLEEPING].includes(deviceState.value)) {
		return FarCirclePause;
	} else if ([ConnectionState.STOPPED, ConnectionState.DISCONNECTED].includes(deviceState.value)) {
		return FarCircleStop;
	} else if ([ConnectionState.INIT].includes(deviceState.value)) {
		return FarCircleUser;
	} else if ([ConnectionState.ALERT].includes(deviceState.value)) {
		return FasCircleExclamation;
	}

	return FarCircleQuestion;
});

const stateColor = computed<StateColor>((): StateColor => {
	if (!wsStatus) {
		return undefined;
	}

	if ([ConnectionState.RUNNING, ConnectionState.READY, ConnectionState.CONNECTED].includes(deviceState.value)) {
		return 'success';
	} else if ([ConnectionState.INIT].includes(deviceState.value)) {
		return 'info';
	} else if ([ConnectionState.SLEEPING].includes(deviceState.value)) {
		return 'warning';
	}

	return 'danger';
});

const category = computed<DeviceCategory | undefined>((): DeviceCategory | undefined => {
	let value = categoryProperty.value?.value;

	if (value !== null && typeof value !== 'undefined') {
		if (Object.values(DeviceCategory).includes(value as any)) {
			return value as any;
		}
	}

	return undefined;
});
</script>
