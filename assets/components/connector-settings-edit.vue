<template>
	<el-form
		ref="connectorFormEl"
		:model="connectorForm"
		label-position="top"
		status-icon
		class="b-b b-b-solid"
	>
		<h3 class="b-b b-b-solid p-2">
			{{ t('devicesModule.headings.connectors.aboutConnector') }}
		</h3>

		<div class="px-2 md:px-4">
			<connector-default-connector-settings-rename
				v-model="connectorForm.details"
				:connector="props.connectorData.connector"
			/>

			<el-divider />

			<property-default-variable-properties-edit
				v-if="connectorForm.properties && connectorForm.properties.variable"
				v-model="connectorForm.properties.variable"
				:properties="variableProperties"
				:labels="variablePropertiesLabels"
				:readonly="variablePropertiesReadonly"
				@change="onPropertiesChanged"
			/>
		</div>
	</el-form>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import { ElDivider, ElForm, FormInstance } from 'element-plus';
import get from 'lodash.get';
import omit from 'lodash.omit';

import {
	ConnectorDefaultConnectorSettingsRename,
	FormResultTypes,
	IConnectorForm,
	IConnectorProperty,
	IEditConnectorEmits,
	IEditConnectorProps,
	PropertyDefaultVariablePropertiesEdit,
	PropertyType,
	useConnectorForm,
} from '@fastybird/devices-module';
import { flattenValue, useFlashMessage } from '@fastybird/tools';

import { ConnectorPropertyIdentifier } from '../types';

defineOptions({
	name: 'ConnectorSettingsEdit',
});

const props = withDefaults(defineProps<IEditConnectorProps>(), {
	remoteFormSubmit: false,
	remoteFormResult: FormResultTypes.NONE,
	remoteFormReset: false,
});

const emit = defineEmits<IEditConnectorEmits>();

const { t } = useI18n();

const flashMessage = useFlashMessage();

const { submit, formResult } = useConnectorForm(props.connectorData.connector);

const connectorFormEl = ref<FormInstance | undefined>(undefined);

const variableProperties = computed<IConnectorProperty[]>((): IConnectorProperty[] => {
	return props.connectorData.properties
		.filter((property) => property.type.type === PropertyType.VARIABLE)
		.filter((property) => [ConnectorPropertyIdentifier.XHM_URI, ConnectorPropertyIdentifier.PIN_CODE].includes(property.identifier as any));
});

const variablePropertiesLabels = computed<{ [key: string]: string }>((): { [key: string]: string } => {
	return Object.fromEntries(variableProperties.value.map((property) => [property.id, t(`homeKitConnector.misc.property.${property.identifier}`)]));
});

const variablePropertiesReadonly = computed<{ [key: string]: boolean }>((): { [key: string]: boolean } => {
	return Object.fromEntries(variableProperties.value.map((property) => [property.id, true]));
});

const connectorForm = reactive<IConnectorForm>({
	details: {
		identifier: props.connectorData.connector.identifier,
		name: props.connectorData.connector.name,
		comment: props.connectorData.connector.comment,
	},
	properties: {
		variable: Object.fromEntries(variableProperties.value.map((property) => [property.id, property.value])),
	},
});

const changed = ref<boolean>(false);

const onPropertiesChanged = (): void => {
	changed.value = true;
};

watch(
	(): boolean => props.remoteFormSubmit,
	async (val: boolean): Promise<void> => {
		if (val) {
			emit('update:remoteFormSubmit', false);

			connectorFormEl.value!.clearValidate();

			await connectorFormEl.value!.validate(async (valid: boolean): Promise<void> => {
				if (!valid) {
					return;
				}

				const errorMessage = t('homeKitConnector.messages.connectors.notEdited', {
					connector: props.connectorData.connector.title,
				});

				submit(omit(connectorForm, ['properties'])).catch((e: any) => {
					if (get(e, 'exception', null) !== null) {
						flashMessage.exception(get(e, 'exception', null), errorMessage);
					} else {
						flashMessage.error(errorMessage);
					}
				});
			});
		}
	}
);

watch(
	(): boolean => props.remoteFormReset,
	(val: boolean): void => {
		emit('update:remoteFormReset', false);

		if (val) {
			connectorForm.details.identifier = props.connectorData.connector.identifier;
			connectorForm.details.name = props.connectorData.connector.name;
			connectorForm.details.comment = props.connectorData.connector.comment;

			connectorForm.properties!.variable = Object.fromEntries(
				variableProperties.value.map((property) => [property.id, flattenValue(property.value)])
			);
		}
	}
);

watch(
	(): IConnectorProperty[] => variableProperties.value,
	(val: IConnectorProperty[]): void => {
		if (!changed.value) {
			connectorForm.properties!.variable = Object.fromEntries(val.map((property) => [property.id, flattenValue(property.value)]));
		}

		connectorFormEl.value!.clearValidate();
	}
);

watch(
	(): FormResultTypes => formResult.value,
	(val: FormResultTypes): void => {
		emit('update:remoteFormResult', val);
	}
);
</script>
