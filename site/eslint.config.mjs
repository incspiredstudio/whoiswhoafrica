import js from "@eslint/js";
import globals from "globals";

export default [
  { ignores: ["dist/**", "build/coverage/**", "playwright-report/**", "test-results/**", "vendor/**", "**/assets/vendor/**", "node_modules/**", "wordpress/**", ".studio/**", "wp-content/plugins/*/vendor/**", "wp-content/plugins/two-factor/**", "wp-content/plugins/user-role-editor/**", "wp-content/plugins/wp-super-cache/**", "wp-content/plugins/action-scheduler/**"] },
  js.configs.recommended,
  {
    files: ["**/*.js", "**/*.mjs"],
    languageOptions: { ecmaVersion: 2022, sourceType: "module", globals: { ...globals.browser, ...globals.node } },
    rules: { "no-eval": "error", "no-implied-eval": "error", "no-restricted-globals": ["error", "jQuery", "$"] },
  },
];
