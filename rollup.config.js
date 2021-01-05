import scss from 'rollup-plugin-scss'
import typescript from '@rollup/plugin-typescript';
import {terser} from "rollup-plugin-terser";

export default {
    input: `assets/backend.ts`,
    output: {
        file: `public/backend.min.js`,
        format: 'iife',
    },
    plugins: [
        typescript(),
        terser(),
        scss(),
    ]
};
