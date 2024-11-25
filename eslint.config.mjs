import pluginPrettier from 'eslint-plugin-prettier';
import pluginVue from 'eslint-plugin-vue';
import ts from 'typescript-eslint';

import js from '@eslint/js';

export default [
	js.configs.recommended,
	...ts.configs.recommended,
	...pluginVue.configs['flat/essential'],
	...pluginVue.configs['flat/strongly-recommended'],
	...pluginVue.configs['flat/recommended'],
	{
		plugins: {
			prettier: pluginPrettier,
		},
		languageOptions: {
			parserOptions: {
				parser: '@typescript-eslint/parser',
			},
			globals: {
				GlobalEventHandlers: 'readonly',
				ScrollToOptions: 'readonly',
			},
		},
		rules: {
			'lines-between-class-members': [
				'error',
				'always',
				{
					exceptAfterSingleLine: true,
				},
			],
			'no-useless-computed-key': 'off',
			'vue/no-setup-props-destructure': 'off',
			'vue/no-v-html': 'off',
			'vue/no-v-text-v-html-on-component': 'off',
			'vue/prefer-import-from-vue': 'off',
			'vue/html-indent': 'off',
			'vue/html-self-closing': [
				'error',
				{
					html: {
						void: 'always',
						normal: 'always',
					},
				},
			],
			'@typescript-eslint/explicit-function-return-type': ['error'],
			'@typescript-eslint/ban-ts-comment': 'off',
			'@typescript-eslint/no-explicit-any': 'off',
			'prettier/prettier': ['error'],
		},
	},
];
