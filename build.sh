if [ ! -d release ]; then
    mkdir release
fi

version=$(xmllint --xpath '/config/modules/Divido_Pay/version/text()' src/app/code/community/Divido/Pay/etc/config.xml)

cd src/
zip  -r ../release/divido-magento-$version.zip *
cd ../
