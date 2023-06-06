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

set_plugin_version () {
  # workaround for https://forums.docker.com/t/sed-couldnt-open-temporary-file-xyz-permission-denied-when-using-virtiofs/125473/2
  local tmp=/tmp/sed.tmp
  sed "s/ORGANIC_PLUGIN_VERSION_VALUE/${BUILD_NUMBER}/" $1 > $tmp; cat $tmp > $1; rm $tmp
}
set_plugin_version build/organic/organic.php
set_plugin_version build/organic/readme.txt

cp src/composer.json build/organic/
cp src/composer.lock build/organic/

cd build/organic
composer install --no-dev
cd $ROOT_DIR

(cd src/blocks/ && npm ci && npm run build)
mkdir -p build/organic/blocks/affiliate/productCard build/organic/blocks/affiliate/productCarousel
cp -r src/blocks/affiliate/productCard/build build/organic/blocks/affiliate/productCard/
cp src/blocks/affiliate/productCard/block.json build/organic/blocks/affiliate/productCard/
cp -r src/blocks/affiliate/productCarousel/build build/organic/blocks/affiliate/productCarousel/
cp src/blocks/affiliate/productCarousel/block.json build/organic/blocks/affiliate/productCarousel/
cd build
zip -r organic-${BUILD_NUMBER}.zip organic
cp organic-${BUILD_NUMBER}.zip organic.zip
