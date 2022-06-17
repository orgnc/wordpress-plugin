#!/bin/bash
BUILD_NUMBER=$1;
sed -i "s/VERSION/${BUILD_NUMBER}/" organic.php
mkdir -p build/organic
ls -alh
sudo chown -R runner:docker .
cp -r Organic build/organic/
cp organic.php build/organic/
cp readme.txt build/organic/
cp composer.json build/organic/
cp composer.lock build/organic/
cp -r vendor build/organic/
(cd affiliate/ && npm install && npm run build)
mkdir -p build/organic/affiliate/
cp -r affiliate/build build/organic/affiliate/
cd build
zip -r organic-${BUILD_NUMBER}.zip organic
cp organic-${BUILD_NUMBER}.zip organic.zip