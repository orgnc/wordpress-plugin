#!/bin/bash -e
BUILD_NUMBER=${1:-"dev"}
ROOT_DIR=$(pwd)
rm -rf ./build/*
mkdir -p build/organic
ls -alh

if ! [[ $BUILD_NUMBER == "dev" ]]; then
  sudo chown -R runner:docker .
fi

cp -r Organic build/organic/
cp organic.php build/organic/
cp readme.txt build/organic/
# macos sed has different options
if [[ $(uname) == 'Darwin' ]]; then
  sed -i '' "s/ORGANIC_PLUGIN_VERSION_VALUE/${BUILD_NUMBER}/" build/organic/organic.php build/organic/readme.txt
else
  sed -i "s/ORGANIC_PLUGIN_VERSION_VALUE/${BUILD_NUMBER}/" build/organic/organic.php build/organic/readme.txt
fi

cp composer.json build/organic/
cp composer.lock build/organic/

cd build/organic
composer install --no-dev
cd $ROOT_DIR

# cp -r vendor build/organic/
(cd affiliate/ && npm ci && npm run build)
mkdir -p build/organic/affiliate/blocks/productCard build/organic/affiliate/blocks/productCarousel
cp -r affiliate/blocks/productCard/build build/organic/affiliate/blocks/productCard/
cp affiliate/blocks/productCard/block.json build/organic/affiliate/blocks/productCard/
cp -r affiliate/blocks/productCarousel/build build/organic/affiliate/blocks/productCarousel/
cp affiliate/blocks/productCarousel/block.json build/organic/affiliate/blocks/productCarousel/
cd build
zip -r organic-${BUILD_NUMBER}.zip organic
cp organic-${BUILD_NUMBER}.zip organic.zip
