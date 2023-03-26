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
(cd src/affiliate/ && npm ci && npm run build)
mkdir -p build/organic/affiliate/blocks/productCard build/organic/affiliate/blocks/productCarousel
cp -r src/affiliate/blocks/productCard/build build/organic/affiliate/blocks/productCard/
cp src/affiliate/blocks/productCard/block.json build/organic/affiliate/blocks/productCard/
cp -r src/affiliate/blocks/productCarousel/build build/organic/affiliate/blocks/productCarousel/
cp src/affiliate/blocks/productCarousel/block.json build/organic/affiliate/blocks/productCarousel/
cp src/affiliate/initSDKOnPostLoad.js build/organic/affiliate/
cd build
zip -r organic-${BUILD_NUMBER}.zip organic
cp organic-${BUILD_NUMBER}.zip organic.zip
