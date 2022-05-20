// @ts-check
const OFF = 'off';
const ERROR = 'error';
const WARNING = 'warn';

/** @type {import('eslint').Linter.RulesRecord} */
const COMMON_RULES = {
  // to ignore complaints about parserOptions.project property
  'import/no-cycle': ERROR,
  'arrow-body-style': OFF,
  'import/extensions': [
    WARNING,
    'ignorePackages',
    {
      js: 'never',
      jsx: 'never',
      ts: 'never',
      tsx: 'never',
    },
  ],
  'import/no-unresolved': [
    ERROR,
    {
      ignore: ['^@wordpress/.*'],
    },
  ],
  'import/prefer-default-export': OFF,
  'import/no-extraneous-dependencies': [
    WARNING,
    {
      devDependencies: true,
    },
  ],
  'import/order': [
    WARNING,
    {
      groups: [
        ['builtin', 'external'],
        'internal',
        ['index', 'parent', 'sibling'],
        ['object', 'type'],
      ],
      'newlines-between': 'always',
      alphabetize: {
        order: 'asc',
        caseInsensitive: true,
      },
    },
  ],
  'max-len': OFF,
  'react/function-component-definition': [
    WARNING,
    {
      namedComponents: 'arrow-function',
      unnamedComponents: 'arrow-function',
    },
  ],
  'react/jsx-max-props-per-line': [
    ERROR,
    {
      when: 'always',
    },
  ],
  'react/jsx-no-useless-fragment': [WARNING, { allowExpressions: true }],
  'react/jsx-sort-props': [
    ERROR,
    {
      shorthandLast: true,
      reservedFirst: true,
    },
  ],
  'react/no-unescaped-entities': [
    WARNING, {
      forbid: [
        '>',
        '}',
      ],
    },
  ],
  'react/react-in-jsx-scope': OFF,
  'react/prop-types': WARNING,
};

const CONFIG = {
  root: true,
  env: {
    browser: true,
    commonjs: true,
    es6: true,
    node: true,
    jest: true,
  },
  parser: '@babel/eslint-parser',
  parserOptions: {
    ecmaFeatures: {
      jsx: true,
    },
    ecmaVersionx: 2020,
    requireConfigFile: false,
  },
  plugins: [
    'import',
    'react',
    'react-hooks',
  ],
  rules: {
  },
  settings: {
    react: {
      version: 'detect',
    },
  },
  overrides: [
    {
      files: [
        '*.js',
        '*.jsx',
      ],
      extends: [
        'airbnb',
        'airbnb/hooks',
        'plugin:import/errors',
        'plugin:import/warnings',
      ],
      rules: COMMON_RULES,
    },
  ],
};

module.exports = CONFIG;
