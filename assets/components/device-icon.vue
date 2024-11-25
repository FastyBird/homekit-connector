<template>
	<component :is="categoryIcon" />
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Component } from 'vue';

import {
	FabApple,
	FasBell,
	FasBridge,
	FasDoorOpen,
	FasDroplet,
	FasFan,
	FasFaucet,
	FasFire,
	FasGamepad,
	FasGauge,
	FasKey,
	FasLightbulb,
	FasMicrochip,
	FasPersonBooth,
	FasPlaneDeparture,
	FasPlug,
	FasTemperatureLow,
	FasTv,
	FasVideo,
	FasVolumeLow,
	FasWarehouse,
	FasWind,
} from '@fastybird/web-ui-icons';

import { DeviceCategory } from '../types';

import { DeviceIconProps } from './device-icon.types';

defineOptions({
	name: 'DeviceIcon',
});

const props = defineProps<DeviceIconProps>();

const categoryIcon = computed<Component>((): Component => {
	if (typeof props.category === 'undefined') {
		return FasMicrochip;
	}

	if ([DeviceCategory.BRIDGE].includes(props.category)) {
		return FasBridge;
	}
	if ([DeviceCategory.FAN].includes(props.category)) {
		return FasFan;
	}
	if ([DeviceCategory.GARAGE_DOOR_OPENER].includes(props.category)) {
		return FasWarehouse;
	}
	if ([DeviceCategory.LIGHT_BULB].includes(props.category)) {
		return FasLightbulb;
	}
	if ([DeviceCategory.DOOR_LOCK].includes(props.category)) {
		return FasKey;
	}
	if ([DeviceCategory.OUTLET, DeviceCategory.SWITCH, DeviceCategory.PROGRAMMABLE_SWITCH].includes(props.category)) {
		return FasPlug;
	}
	if ([DeviceCategory.THERMOSTAT].includes(props.category)) {
		return FasTemperatureLow;
	}
	if ([DeviceCategory.SENSOR].includes(props.category)) {
		return FasGauge;
	}
	if ([DeviceCategory.ALARM_SYSTEM].includes(props.category)) {
		return FasBell;
	}
	if ([DeviceCategory.DOOR, DeviceCategory.WINDOW].includes(props.category)) {
		return FasDoorOpen;
	}
	if ([DeviceCategory.WINDOW_COVERING].includes(props.category)) {
		return FasPersonBooth;
	}
	if ([DeviceCategory.CAMERA, DeviceCategory.VIDEO_DOOR_BELL].includes(props.category)) {
		return FasVideo;
	}
	if ([DeviceCategory.AIR_PURIFIER, DeviceCategory.AIR_CONDITIONER].includes(props.category)) {
		return FasWind;
	}
	if ([DeviceCategory.HEATER].includes(props.category)) {
		return FasFire;
	}
	if ([DeviceCategory.HUMIDIFIER, DeviceCategory.DEHUMIDIFIER].includes(props.category)) {
		return FasDroplet;
	}
	if ([DeviceCategory.APPLE_TV, DeviceCategory.HOMEPOD].includes(props.category)) {
		return FabApple;
	}
	if ([DeviceCategory.SPEAKER, DeviceCategory.AUDIO_RECEIVER].includes(props.category)) {
		return FasVolumeLow;
	}
	if ([DeviceCategory.AIRPORT].includes(props.category)) {
		return FasPlaneDeparture;
	}
	if ([DeviceCategory.SPRINKLER, DeviceCategory.FAUCET, DeviceCategory.SHOWER_HEAD].includes(props.category)) {
		return FasFaucet;
	}
	if ([DeviceCategory.TELEVISION, DeviceCategory.TV_SET_TOP_BOX, DeviceCategory.TV_STREAMING_STICK].includes(props.category)) {
		return FasTv;
	}
	if ([DeviceCategory.TARGET_CONTROLLER].includes(props.category)) {
		return FasGamepad;
	}

	return FasMicrochip;
});
</script>
