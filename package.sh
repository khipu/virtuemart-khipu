#!/usr/bin/env bash

rm -rf dist
mkdir dist
zip -r dist/khipu.zip khipu.php khipu.xml vendor
