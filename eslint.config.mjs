import js from '@eslint/js';
import ts from 'typescript-eslint';
import pluginPrettier from 'eslint-plugin-prettier/recommended';
import pluginVue from 'eslint-plugin-vue';

export default [
	js.configs.recommended,
	...ts.configs.recommended,
	...pluginVue.configs['flat/essential'],
	...pluginVue.configs['flat/strongly-recommended'],
	...pluginVue.configs['flat/recommended'],
	pluginPrettier,
	{
		languageOptions: {
			parserOptions: {
				parser: '@typescript-eslint/parser'
			},
			globals: {
				'GlobalEventHandlers': 'readonly',
				'ScrollToOptions': 'readonly'
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
			'@typescript-eslint/explicit-function-return-type': ['error'],
			'@typescript-eslint/ban-ts-comment': 'off',
			'@typescript-eslint/no-explicit-any': 'off',
			'prettier/prettier': ['error'],
		},
	},
];
