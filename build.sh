if [ ! -d release ]; then
    mkdir release
fi

version=$(xmllint --xpath '/config/modules/Divido_Pay/version/text()' src/app/code/community/Divido/Pay/etc/config.xml)

rm release/divido-magento-$version.zip
cd src/
zip -x \*.DS_Store -r ../release/divido-magento-$version.zip *
cd ../
