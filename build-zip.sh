#!/bin/bash -e
BUILD_NUMBER=${1:-"dev"}
ROOT_DIR=$(pwd)
rm -rf ./build/*
mkdir -p build/organic
ls -alh

if ! [[ $BUILD_NUMBER == "dev" ]]; then
  sudo chown -R runner:docker .
fi

cp -r src/Organic build/organic/
cp src/organic.php build/organic/
cp src/readme.txt build/organic/
# macos sed has different options
if [[ $(uname) == 'Darwin' ]]; then
  sed -i '' "s/ORGANIC_PLUGIN_VERSION_VALUE/${BUILD_NUMBER}/" build/organic/organic.php build/organic/readme.txt
else
  sed -i "s/ORGANIC_PLUGIN_VERSION_VALUE/${BUILD_NUMBER}/" build/organic/organic.php build/organic/readme.txt
fi

cp src/composer.json build/organic/
cp src/composer.lock build/organic/

cd build/organic
composer install --no-dev
cd $ROOT_DIR

# cp -r vendor build/organic/
(cd src/blocks/ && npm ci && npm run build)
mkdir -p build/organic/blocks/affiliate/productCard build/organic/blocks/affiliate/productCarousel
cp -r src/blocks/affiliate/productCard/build build/organic/blocks/affiliate/productCard/
cp src/blocks/affiliate/productCard/block.json build/organic/blocks/affiliate/productCard/
cp -r src/blocks/affiliate/productCarousel/build build/organic/blocks/affiliate/productCarousel/
cp src/blocks/affiliate/productCarousel/block.json build/organic/blocks/affiliate/productCarousel/
cp src/blocks/initSDKOnPostLoad.js build/organic/blocks/
cd build
zip -r organic-${BUILD_NUMBER}.zip organic
cp organic-${BUILD_NUMBER}.zip organic.zip
