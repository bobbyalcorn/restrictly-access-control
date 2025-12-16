/**
 * ESLint Configuration for Restrictly
 *
 * Ensures JavaScript code follows best practices and maintains WordPress standards.
 *
 * @file eslint.config.js
 * @description Defines ESLint rules, language options, and ignored files for linting.
 *
 * @module eslint-config
 * @see {@link https://eslint.org/docs/latest/user-guide/configuring}
 *
 * @config
 * @property {Array} ignores - List of paths and files to be ignored by ESLint.
 * @property {Object} languageOptions - Defines ECMAScript version and source type.
 * @property {Object} rules - Custom linting rules for code consistency and best practices.
 *
 * @package Restrictly
 */

module.exports = {
    ignores: ["node_modules/", "vendor/", "assets/js/*.min.js"],

    languageOptions: {
        ecmaVersion: "latest",
        sourceType: "module"
    },

    plugins: {
        prettier: require("eslint-plugin-prettier")
    },

    rules: {
        "no-unused-vars": "warn",
        "no-console": "warn",
        "indent": ["error", "tab"],
        "quotes": ["error", "single"],
        "semi": ["error", "always"],
        "prettier/prettier": "error"
    }
};
