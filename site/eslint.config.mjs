import js from "@eslint/js";
import globals from "globals";

export default [
  { ignores: ["dist/**", "vendor/**", "node_modules/**", "wordpress/**", ".studio/**", "wp-content/plugins/*/vendor/**"] },
  js.configs.recommended,
  {
    files: ["**/*.js", "**/*.mjs"],
    languageOptions: { ecmaVersion: 2022, sourceType: "module", globals: { ...globals.browser, ...globals.node } },
    rules: { "no-eval": "error", "no-implied-eval": "error", "no-restricted-globals": ["error", "jQuery", "$"] },
  },
];
