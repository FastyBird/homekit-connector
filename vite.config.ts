import { resolve } from 'path';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import eslint from '@nabla/vite-plugin-eslint';
import dts from 'vite-plugin-dts';
import vueI18n from '@intlify/unplugin-vue-i18n/vite';
import vueTypeImports from 'vite-plugin-vue-type-imports';
import svgLoader from 'vite-svg-loader';
import del from 'rollup-plugin-delete';
import UnoCSS from 'unocss/vite';

// https://vitejs.dev/config/
export default defineConfig({
	plugins: [
		vue(),
		vueTypeImports(),
		vueI18n({
			include: [resolve(__dirname, './locales/**.json')],
		}),
		eslint(),
		dts({
			outDir: 'dist',
			staticImport: true,
			insertTypesEntry: true,
			rollupTypes: true,
		}),
		svgLoader(),
		UnoCSS(),
	],
	build: {
		lib: {
			entry: resolve(__dirname, './assets/entry.ts'),
			name: 'homekit-connector',
			fileName: (format) => `homekit-connector.${format}.js`,
		},
		rollupOptions: {
			plugins: [
				// @ts-ignore
				del({
					targets: [
						'dist/components',
						'dist/composables',
						'dist/errors',
						'dist/jsonapi',
						'dist/layouts',
						'dist/models',
						'dist/router',
						'dist/types',
						'dist/views',
						'dist/entry.ts',
						'dist/configuration.ts',
					],
					hook: 'generateBundle',
				}),
			],
			output: {
				assetFileNames: (chunkInfo) => {
					if (chunkInfo.name == 'style.css') return 'homekit-connector.css';

					return chunkInfo.name as string;
				},
			},
		},
		sourcemap: true,
		target: 'esnext',
	},
});
