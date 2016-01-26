if [ ! -d "$DIRECTORY" ]; then
    mkdir build
fi

cd src/
zip  -r ../release/divido-magento.zip *
cd ../
