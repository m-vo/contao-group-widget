import { nodeResolve } from '@rollup/plugin-node-resolve';
import postcss from 'rollup-plugin-postcss'
import postcssImport from 'postcss-import';
import terser from "@rollup/plugin-terser";

export default {
    input: `assets/backend.js`,
    output: {
        file: `public/backend.min.js`,
        format: 'iife',
    },
    plugins: [
        nodeResolve(),
        postcss({
            extract: true,
            plugins: [postcssImport]
        }),
        terser(),
    ]
};
