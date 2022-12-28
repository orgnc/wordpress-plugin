#!/bin/bash -e
BUILD_NUMBER=$1;
mkdir -p build/organic
ls -alh
sudo chown -R runner:docker .
cp -r Organic build/organic/
cp organic.php build/organic/
cp readme.txt build/organic/

sed -i "s/ORGANIC_PLUGIN_VERSION_VALUE/${BUILD_NUMBER}/" build/organic/organic.php build/organic/readme.txt

cp composer.json build/organic/
cp composer.lock build/organic/
cp -r vendor build/organic/
(cd affiliate/ && npm ci && npm run build)
mkdir -p build/organic/affiliate/
cp -r affiliate/blocks/ build/organic/affiliate/
cd build
zip -r organic-${BUILD_NUMBER}.zip organic
cp organic-${BUILD_NUMBER}.zip organic.zip
