// const defaultConfig = require('@wordpress/scripts/config/webpack.config');
//
// module.exports = [
//   {
//     ...defaultConfig,
//     entry: './src/productCard/index.js',
//     output: './build/productCard/',
//   },
//   {
//     ...defaultConfig,
//     entry: './src/productCarousel/index.js',
//     output: './build/productCarousel/',
//   },
// ];

/**
 * `@wordpress/scripts` path-based name multi-block Webpack configuration.
 * @see https://wordpress.stackexchange.com/questions/390282
 */

const config = require('@wordpress/scripts/config/webpack.config.js');
const CopyPlugin = require('copy-webpack-plugin');
const path = require('path');

/**
 * Resolve a series of path parts relative to `./src`.
 * @param string[] pathParts An array of path parts.
 * @returns string A normalized path, relative to `./src`.
 * */
const resolveSource = (...pathParts) => path.resolve(process.cwd(), 'src', ...pathParts);

/**
 * Resolve a block name to the path to it's main `index.js` entry-point.
 * @param string name The name of the block.
 * @returns string A normalized path to the block's entry-point file.
 * */
const resolveBlockEntry = (name) => resolveSource(name, 'index.js');

config.entry = {
  'productCard/index': resolveBlockEntry('productCard'),
  'productCarousel/index': resolveBlockEntry('productCarousel'),
};

// Add a CopyPlugin to copy over block.json files.
config.plugins.push(
  new CopyPlugin(
    {
      patterns: [
        {
          context: 'src',
          from: '*/block.json',
        },
      ],
    },
  ),
);

config.optimization.splitChunks.cacheGroups.style.name = (module, chunks, groupKey) => {
  const delimeter = config.optimization.splitChunks.cacheGroups.style.automaticNameDelimiter;

  return chunks[0].name.replace(
    /(\/?)([^/]+?)$/,
    `$1${groupKey}${delimeter}$2`,
  );
};

module.exports = config;
